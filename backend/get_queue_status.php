<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$student_id = $student['student_id'] ?? null;

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    exit;
}

$appt_query = "SELECT a.status, qt.token_number, qt.advisor_id, qt.queue_date
               FROM appointments a 
               LEFT JOIN queue_tokens qt ON a.appointment_id = qt.appointment_id 
               WHERE a.student_id = ? AND a.status IN ('booked', 'waiting', 'serving') 
               ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 1";
$stmt = $conn->prepare($appt_query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();

if (!$appt) {
    echo json_encode(['has_appointment' => false]);
    exit;
}

// Calculate estimated wait time based on running token
$advisor_id = $appt['advisor_id'];
$date = $appt['queue_date'];
$user_token = $appt['token_number'];

// Get running token (currently serving)
$stmt_run = $conn->prepare("SELECT token_number FROM queue_tokens WHERE advisor_id = ? AND queue_date = ? AND status = 'serving' ORDER BY token_number DESC LIMIT 1");
$stmt_run->bind_param("is", $advisor_id, $date);
$stmt_run->execute();
$run_res = $stmt_run->get_result();
$run_row = $run_res->fetch_assoc();
$running_token = $run_row ? $run_row['token_number'] : null; 

if (!$running_token) {
    // If no one is serving, find the next waiting
    $stmt_wait = $conn->prepare("SELECT token_number FROM queue_tokens WHERE advisor_id = ? AND queue_date = ? AND status IN ('waiting', 'booked') ORDER BY token_number ASC LIMIT 1");
    $stmt_wait->bind_param("is", $advisor_id, $date);
    $stmt_wait->execute();
    $wait_res = $stmt_wait->get_result();
    $wait_row = $wait_res->fetch_assoc();
    $running_token = $wait_row ? $wait_row['token_number'] : $user_token;
}

$user_queue_position = max(0, $user_token - $running_token);
$est_wait = $user_queue_position * 10;

// Also update estimated wait time in db
$update_stmt = $conn->prepare("UPDATE queue_tokens SET estimated_wait_minutes = ?, status = ? WHERE advisor_id = ? AND queue_date = ? AND token_number = ?");
// Wait, we shouldn't overwrite the status blindly here unless we have logic to move booked->waiting. Let's just update wait time.
$update_stmt = $conn->prepare("UPDATE queue_tokens SET estimated_wait_minutes = ? WHERE advisor_id = ? AND queue_date = ? AND token_number = ?");
$update_stmt->bind_param("iisi", $est_wait, $advisor_id, $date, $user_token);
$update_stmt->execute();

echo json_encode([
    'has_appointment' => true,
    'status' => ucfirst($appt['status']),
    'raw_status' => $appt['status'],
    'running_token' => $running_token,
    'estimated_wait_minutes' => $est_wait
]);
?>
