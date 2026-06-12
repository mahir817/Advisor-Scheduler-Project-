<?php
session_start();
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo "<script>alert('Please fill in all fields'); window.history.back();</script>";
        exit;
    }

    $stmt = $conn->prepare("SELECT user_id, password_hash, role, full_name FROM users WHERE email = ? AND is_active = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];

            if ($user['role'] === 'student') {
                header("Location: ../student-module/student-dashboard.html");
            } elseif ($user['role'] === 'advisor') {
                // Adjust to the actual advisor dashboard filename if different
                header("Location: ../advisor-module/advisor-dashboard.html"); 
            } elseif ($user['role'] === 'admin') {
                // Adjust to the actual admin dashboard filename if different
                header("Location: ../admin-module/admin-dashboard.html"); 
            }
            exit;
        } else {
            echo "<script>alert('Invalid password'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('User not found or inactive'); window.history.back();</script>";
    }
}
?>
