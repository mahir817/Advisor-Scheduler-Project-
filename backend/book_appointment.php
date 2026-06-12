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
        $token_num = rand(1, 10);
        $est_wait = rand(10, 30);
        $stmt_token = $conn->prepare("INSERT INTO queue_tokens (appointment_id, advisor_id, token_number, queue_date, status, estimated_wait_minutes) VALUES (?, ?, ?, ?, 'booked', ?)");
        $stmt_token->bind_param("iiisi", $appointment_id, $advisor_id, $token_num, $date, $est_wait);
        $stmt_token->execute();
        
        echo json_encode(['success' => true, 'message' => 'Appointment booked successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to book appointment']);
    }
}
?>
