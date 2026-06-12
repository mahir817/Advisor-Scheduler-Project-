<?php
session_start();
header('Content-Type: application/json');

require '../../backend/db_connect.php';

// Check auth
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get student_id and status
$stmt = $conn->prepare("SELECT student_id, status FROM students WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Student record not found.']);
    exit;
}

if ($student['status'] === 'blocked') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Account restricted due to missed appointments.']);
    exit;
}

$student_id = $student['student_id'];

// Get JSON payload
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['advisor_id'], $data['appointment_date'], $data['appointment_time'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$advisor_id = (int)$data['advisor_id'];
$requested_date = $data['appointment_date'];
$requested_time = $data['appointment_time'];
$purpose = $data['purpose'] ?? 'General Advising';
$is_urgent = !empty($data['is_urgent']) ? 1 : 0;

// Verify Advisor Unavailability
$stmt = $conn->prepare("SELECT 1 FROM advisor_unavailable_dates WHERE advisor_id = ? AND unavailable_date = ?");
$stmt->bind_param("is", $advisor_id, $requested_date);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Advisor is unavailable on this date.']);
    exit;
}

// Verify Conflict
$stmt = $conn->prepare("SELECT 1 FROM appointments WHERE advisor_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN ('cancelled', 'auto_cancelled', 'missed')");
$stmt->bind_param("iss", $advisor_id, $requested_date, $requested_time);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Time slot already taken.']);
    exit;
}

$conn->begin_transaction();
try {
    // 1. Insert Appointment
    $stmt = $conn->prepare("INSERT INTO appointments (student_id, advisor_id, appointment_date, appointment_time, purpose, status, is_urgent) VALUES (?, ?, ?, ?, ?, 'booked', ?)");
    $stmt->bind_param("sisssi", $student_id, $advisor_id, $requested_date, $requested_time, $purpose, $is_urgent);
    $stmt->execute();
    $appointment_id = $conn->insert_id;

    // 2. Calculate Token Number dynamically
    $stmt = $conn->prepare("SELECT COALESCE(MAX(token_number), 0) + 1 AS next_token FROM queue_tokens WHERE advisor_id = ? AND queue_date = ?");
    $stmt->bind_param("is", $advisor_id, $requested_date);
    $stmt->execute();
    $next_token = $stmt->get_result()->fetch_assoc()['next_token'];

    // 3. Calculate Estimated Wait Minutes
    $stmt = $conn->prepare("SELECT COUNT(*) AS active_ahead FROM queue_tokens WHERE advisor_id = ? AND queue_date = ? AND status IN ('booked', 'waiting', 'serving')");
    $stmt->bind_param("is", $advisor_id, $requested_date);
    $stmt->execute();
    $active_ahead = $stmt->get_result()->fetch_assoc()['active_ahead'];
    $estimated_wait = $active_ahead * 10;

    // 4. Insert Queue Token
    $stmt = $conn->prepare("INSERT INTO queue_tokens (appointment_id, advisor_id, token_number, queue_date, status, estimated_wait_minutes) VALUES (?, ?, ?, ?, 'booked', ?)");
    $stmt->bind_param("iiisi", $appointment_id, $advisor_id, $next_token, $requested_date, $estimated_wait);
    $stmt->execute();

    $conn->commit();
    file_put_contents(__DIR__ . '/../queue/state.txt', time());
    
    echo json_encode(['success' => true, 'message' => 'Appointment successfully booked.', 'token_number' => $next_token, 'appointment_id' => $appointment_id]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
