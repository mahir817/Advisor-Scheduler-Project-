<?php
require '../../../backend/db_connect.php';

// Find tokens currently 'serving' where called_at is older than 5 minutes
$stmt = $conn->prepare("
    SELECT qt.token_id, qt.appointment_id, qt.advisor_id, qt.queue_date, a.student_id 
    FROM queue_tokens qt
    JOIN appointments a ON qt.appointment_id = a.appointment_id
    WHERE qt.status = 'serving' AND qt.called_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
");
$stmt->execute();
$expired_tokens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($expired_tokens)) {
    echo json_encode(['success' => true, 'message' => 'No timeouts detected.']);
    exit;
}

$state_changed = false;

foreach ($expired_tokens as $token) {
    $conn->begin_transaction();
    try {
        $token_id = $token['token_id'];
        $appt_id = $token['appointment_id'];
        $student_id = $token['student_id'];
        $advisor_id = $token['advisor_id'];
        $queue_date = $token['queue_date'];

        // 1. Mark as missed initially
        $upd = $conn->prepare("UPDATE appointments SET status = 'missed' WHERE appointment_id = ?");
        $upd->bind_param("i", $appt_id);
        $upd->execute();

        // 2. Increment penalty tally
        $upd = $conn->prepare("UPDATE students SET missed_count = missed_count + 1 WHERE student_id = ?");
        $upd->bind_param("s", $student_id);
        $upd->execute();

        // Fetch updated tally
        $st_res = $conn->prepare("SELECT missed_count FROM students WHERE student_id = ?");
        $st_res->bind_param("s", $student_id);
        $st_res->execute();
        $missed_count = $st_res->get_result()->fetch_assoc()['missed_count'];

        // 3. Apply sanctions
        if ($missed_count == 1) {
            $upd = $conn->prepare("UPDATE students SET status = 'warned' WHERE student_id = ?");
            $upd->bind_param("s", $student_id);
            $upd->execute();

            $ins = $conn->prepare("INSERT INTO penalties (student_id, penalty_type, reason, missed_count_at_time, risk_level) VALUES (?, 'warning', 'Automated System Check: No-show timeout window exceeded.', ?, 'low')");
            $ins->bind_param("si", $student_id, $missed_count);
            $ins->execute();
        } elseif ($missed_count >= 3) {
            $upd = $conn->prepare("UPDATE students SET status = 'blocked' WHERE student_id = ?");
            $upd->bind_param("s", $student_id);
            $upd->execute();

            $ins = $conn->prepare("INSERT INTO penalties (student_id, penalty_type, reason, missed_count_at_time, risk_level) VALUES (?, 'block', 'Automated Lockout System: Exceeded max allowed strike violations.', ?, 'high')");
            $ins->bind_param("si", $student_id, $missed_count);
            $ins->execute();
        }

        // 4. Move to Last Place (Alternative Dynamic Queue Rule)
        $max_res = $conn->prepare("SELECT COALESCE(MAX(token_number), 0) + 1 AS next_pos FROM queue_tokens WHERE advisor_id = ? AND queue_date = ?");
        $max_res->bind_param("is", $advisor_id, $queue_date);
        $max_res->execute();
        $next_pos = $max_res->get_result()->fetch_assoc()['next_pos'];

        $upd_qt = $conn->prepare("UPDATE queue_tokens SET token_number = ?, status = 'waiting', called_at = NULL, estimated_wait_minutes = ? WHERE token_id = ?");
        // Re-estimate wait: Active ahead count * 10
        $active_res = $conn->prepare("SELECT COUNT(*) AS active_ahead FROM queue_tokens WHERE advisor_id = ? AND queue_date = ? AND status IN ('booked', 'waiting', 'serving') AND token_id != ?");
        $active_res->bind_param("isi", $advisor_id, $queue_date, $token_id);
        $active_res->execute();
        $active_ahead = $active_res->get_result()->fetch_assoc()['active_ahead'];
        $new_est_wait = $active_ahead * 10;
        
        $upd_qt->bind_param("iii", $next_pos, $new_est_wait, $token_id);
        $upd_qt->execute();
        
        // Ensure appointment is set back to waiting/booked so it's not permanently missed if they are just moved
        $upd_appt_wait = $conn->prepare("UPDATE appointments SET status = 'waiting' WHERE appointment_id = ?");
        $upd_appt_wait->bind_param("i", $appt_id);
        $upd_appt_wait->execute();

        $conn->commit();
        $state_changed = true;
    } catch (Exception $e) {
        $conn->rollback();
    }
}

if ($state_changed) {
    file_put_contents(__DIR__ . '/../../queue/state.txt', time());
    echo json_encode(['success' => true, 'message' => 'Processed timeouts and updated queue.']);
} else {
    echo json_encode(['success' => true, 'message' => 'No timeouts detected.']);
}
?>
