<?php
session_start();
header('Content-Type: application/json');

require '../../../backend/db_connect.php';

$advisor_id = null;

if (isset($_GET['advisor_id'])) {
    $advisor_id = (int)$_GET['advisor_id'];
} elseif (isset($_SESSION['user_id']) && $_SESSION['role'] === 'advisor') {
    $stmt = $conn->prepare("SELECT advisor_id FROM advisor_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $adv = $stmt->get_result()->fetch_assoc();
    if ($adv) $advisor_id = $adv['advisor_id'];
}

if (!$advisor_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Advisor ID is required.']);
    exit;
}

$today = date('Y-m-d');

// 1. Currently Serving
$serving = null;
$stmt = $conn->prepare("
    SELECT qt.token_number, qt.called_at, u.full_name as student_name, s.student_id, a.purpose, d.file_name, d.file_path 
    FROM queue_tokens qt
    JOIN appointments a ON qt.appointment_id = a.appointment_id
    JOIN students s ON a.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN documents d ON a.appointment_id = d.appointment_id
    WHERE qt.advisor_id = ? AND qt.queue_date = ? AND qt.status = 'serving'
    ORDER BY qt.token_number ASC LIMIT 1
");
$stmt->bind_param("is", $advisor_id, $today);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $serving = $res->fetch_assoc();
}

// 2. Next In Queue
$stmt = $conn->prepare("
    SELECT qt.token_number, qt.status, qt.estimated_wait_minutes, u.full_name as student_name, a.appointment_time
    FROM queue_tokens qt
    JOIN appointments a ON qt.appointment_id = a.appointment_id
    JOIN students s ON a.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    WHERE qt.advisor_id = ? AND qt.queue_date = ? AND qt.status IN ('booked', 'waiting')
    ORDER BY qt.token_number ASC
");
$stmt->bind_param("is", $advisor_id, $today);
$stmt->execute();
$next_in_queue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'data' => [
        'advisor_id' => $advisor_id,
        'currently_serving' => $serving,
        'next_in_queue' => $next_in_queue
    ]
]);
?>
