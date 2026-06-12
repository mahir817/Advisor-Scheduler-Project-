<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'advisor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];
$conn->begin_transaction();

try {
    // Get advisor_id
    $stmt = $conn->prepare("SELECT advisor_id FROM advisor_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $adv = $stmt->get_result()->fetch_assoc();
    $advisor_id = $adv['advisor_id'];

    if ($action === 'toggle_availability') {
        $stmt = $conn->prepare("UPDATE advisor_profiles SET is_available = NOT is_available WHERE advisor_id = ?");
        $stmt->bind_param("i", $advisor_id);
        $stmt->execute();
        $stmt = $conn->prepare("SELECT is_available FROM advisor_profiles WHERE advisor_id = ?");
        $stmt->bind_param("i", $advisor_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $conn->commit();
        echo json_encode(['success' => true, 'is_available' => (bool)$row['is_available']]);

    } elseif ($action === 'call_next') {
        $appt_id = (int)($_POST['appointment_id'] ?? 0);
        $today = date('Y-m-d');

        // Complete any currently serving appointment first
        $stmt = $conn->prepare("UPDATE appointments SET status='waiting' WHERE advisor_id = ? AND appointment_date = ? AND status = 'serving'");
        $stmt->bind_param("is", $advisor_id, $today);
        $stmt->execute();
        $stmt = $conn->prepare("UPDATE queue_tokens qt JOIN appointments a ON qt.appointment_id = a.appointment_id SET qt.status='waiting' WHERE a.advisor_id = ? AND qt.queue_date = ? AND qt.status = 'serving'");
        $stmt->bind_param("is", $advisor_id, $today);
        $stmt->execute();

        // Set chosen appointment to 'serving'
        $stmt = $conn->prepare("UPDATE appointments SET status='serving' WHERE appointment_id = ? AND advisor_id = ?");
        $stmt->bind_param("ii", $appt_id, $advisor_id);
        $stmt->execute();
        $stmt = $conn->prepare("UPDATE queue_tokens SET status='serving', called_at=NOW() WHERE appointment_id = ?");
        $stmt->bind_param("i", $appt_id);
        $stmt->execute();

        // Update all remaining token wait times
        $stmt = $conn->prepare("SELECT token_number FROM queue_tokens WHERE appointment_id = ?");
        $stmt->bind_param("i", $appt_id);
        $stmt->execute();
        $serving_token = $stmt->get_result()->fetch_assoc()['token_number'] ?? 0;

        $stmt = $conn->prepare("SELECT qt.appointment_id, qt.token_number FROM queue_tokens qt JOIN appointments a ON qt.appointment_id = a.appointment_id WHERE a.advisor_id = ? AND qt.queue_date = ? AND qt.status IN ('booked','waiting')");
        $stmt->bind_param("is", $advisor_id, $today);
        $stmt->execute();
        $waiting = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($waiting as $w) {
            $pos = max(0, $w['token_number'] - $serving_token);
            $est = $pos * 10;
            $upd = $conn->prepare("UPDATE queue_tokens SET estimated_wait_minutes = ? WHERE appointment_id = ?");
            $upd->bind_param("ii", $est, $w['appointment_id']);
            $upd->execute();
        }

        $conn->commit();
        echo json_encode(['success' => true]);

    } elseif ($action === 'mark_complete') {
        $appt_id = (int)($_POST['appointment_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE appointments SET status='completed' WHERE appointment_id = ? AND advisor_id = ?");
        $stmt->bind_param("ii", $appt_id, $advisor_id);
        $stmt->execute();
        $stmt = $conn->prepare("UPDATE queue_tokens SET status='completed', completed_at=NOW() WHERE appointment_id = ?");
        $stmt->bind_param("i", $appt_id);
        $stmt->execute();
        $conn->commit();
        echo json_encode(['success' => true]);

    } elseif ($action === 'mark_missed') {
        $appt_id = (int)($_POST['appointment_id'] ?? 0);

        // Get student_id for missed count update
        $stmt = $conn->prepare("SELECT student_id FROM appointments WHERE appointment_id = ?");
        $stmt->bind_param("i", $appt_id);
        $stmt->execute();
        $appt_row = $stmt->get_result()->fetch_assoc();
        $student_id = $appt_row['student_id'] ?? null;

        $stmt = $conn->prepare("UPDATE appointments SET status='missed' WHERE appointment_id = ? AND advisor_id = ?");
        $stmt->bind_param("ii", $appt_id, $advisor_id);
        $stmt->execute();
        $stmt = $conn->prepare("UPDATE queue_tokens SET status='missed' WHERE appointment_id = ?");
        $stmt->bind_param("i", $appt_id);
        $stmt->execute();

        // Increment student missed_count
        if ($student_id) {
            $stmt = $conn->prepare("UPDATE students SET missed_count = missed_count + 1 WHERE student_id = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
        }

        $conn->commit();
        echo json_encode(['success' => true]);

    } else {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
