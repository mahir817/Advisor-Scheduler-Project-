<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // We are simulating booking for now with the first available advisor if not specified
    // In a real scenario, these would come from the frontend
    $advisor_id = $_POST['advisor_id'] ?? 2; // Assuming 2 is Mahmudul Hasan
    $date = date('Y-m-d', strtotime('+1 day')); // Tomorrow
    $time = '10:00:00';
    
    // Get student_id
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $student = $res->fetch_assoc();
    $student_id = $student['student_id'];
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student profile not found']);
        exit;
    }

    // Insert appointment
    $stmt = $conn->prepare("INSERT INTO appointments (student_id, advisor_id, appointment_date, appointment_time, status) VALUES (?, ?, ?, ?, 'booked')");
    $stmt->bind_param("siss", $student_id, $advisor_id, $date, $time);
    
    if ($stmt->execute()) {
        $appointment_id = $stmt->insert_id;
        
        // Generate Token
        $stmt_token_max = $conn->prepare("SELECT MAX(token_number) as max_token FROM queue_tokens WHERE advisor_id = ? AND queue_date = ?");
        $stmt_token_max->bind_param("is", $advisor_id, $date);
        $stmt_token_max->execute();
        $res_token_max = $stmt_token_max->get_result();
        $max_token_row = $res_token_max->fetch_assoc();
        $token_num = ($max_token_row['max_token'] ?? 0) + 1;

        // Get current running token to calculate estimated wait time
        $stmt_running = $conn->prepare("SELECT token_number FROM queue_tokens WHERE advisor_id = ? AND queue_date = ? AND status IN ('serving', 'waiting') ORDER BY token_number ASC LIMIT 1");
        $stmt_running->bind_param("is", $advisor_id, $date);
        $stmt_running->execute();
        $res_running = $stmt_running->get_result();
        $running_row = $res_running->fetch_assoc();
        $running_token = $running_row['token_number'] ?? $token_num;
        
        $user_queue_position = max(0, $token_num - $running_token);
        $est_wait = $user_queue_position * 10;

        $stmt_token = $conn->prepare("INSERT INTO queue_tokens (appointment_id, advisor_id, token_number, queue_date, status, estimated_wait_minutes) VALUES (?, ?, ?, ?, 'booked', ?)");
        $stmt_token->bind_param("iiisi", $appointment_id, $advisor_id, $token_num, $date, $est_wait);
        $stmt_token->execute();
        
        echo json_encode(['success' => true, 'message' => 'Appointment booked successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to book appointment']);
    }
}
?>
