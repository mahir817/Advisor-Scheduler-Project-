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

// Get student_id
$stmt = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Student record not found.']);
    exit;
}
$student_id = $student['student_id'];

if (!isset($_FILES['document'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
    exit;
}

$file = $_FILES['document'];
$appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;

// Validate upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Upload error code: ' . $file['error']]);
    exit;
}

// Ensure assets/documents directory exists
$target_dir = "../../assets/documents/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// Generate safe filename
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed_exts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

if (!in_array($file_ext, $allowed_exts)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: pdf, doc, docx, jpg, png.']);
    exit;
}

$new_filename = $student_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
$target_path = $target_dir . $new_filename;
$public_url = '/advisor-student-appointment/assets/documents/' . $new_filename;

if (move_uploaded_file($file['tmp_name'], $target_path)) {
    // Insert into DB
    $stmt = $conn->prepare("INSERT INTO documents (appointment_id, student_id, file_name, file_path, file_type, file_size_bytes) VALUES (?, ?, ?, ?, ?, ?)");
    $size = $file['size'];
    $original_name = $file['name'];
    $stmt->bind_param("issssi", $appointment_id, $student_id, $original_name, $public_url, $file_ext, $size);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully.',
            'file_url' => $public_url,
            'document_id' => $conn->insert_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save to database.']);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
}
?>
