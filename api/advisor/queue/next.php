<?php
session_start();
header('Content-Type: application/json');

require '../../../backend/db_connect.php';

// Check auth
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'advisor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get advisor_id
$stmt = $conn->prepare("SELECT advisor_id FROM advisor_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$adv = $stmt->get_result()->fetch_assoc();
if (!$adv) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Advisor profile not found.']);
    exit;
}
$advisor_id = $adv['advisor_id'];
$today = date('Y-m-d');

$conn->begin_transaction();
try {
    // Step 1: Complete ongoing session
    $stmt = $conn->prepare("UPDATE queue_tokens SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE advisor_id = ? AND queue_date = ? AND status = 'serving'");
    $stmt->bind_param("is", $advisor_id, $today);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE appointments a JOIN queue_tokens qt ON a.appointment_id = qt.appointment_id SET a.status = 'completed' WHERE qt.advisor_id = ? AND qt.queue_date = ? AND qt.status = 'completed' AND a.status = 'serving'");
    $stmt->bind_param("is", $advisor_id, $today);
    $stmt->execute();

    // Step 2 & 3: Retrieve next pending and set to serving
    $stmt = $conn->prepare("SELECT token_id, appointment_id, token_number FROM queue_tokens WHERE advisor_id = ? AND queue_date = ? AND status IN ('booked', 'waiting') ORDER BY token_number ASC LIMIT 1");
    $stmt->bind_param("is", $advisor_id, $today);
    $stmt->execute();
    $next_token = $stmt->get_result()->fetch_assoc();

    $next_token_number = null;
    $student_name = null;
    
    if ($next_token) {
        $token_id = $next_token['token_id'];
        $appointment_id = $next_token['appointment_id'];
        $next_token_number = $next_token['token_number'];

        // Update token and appointment
        $stmt = $conn->prepare("UPDATE queue_tokens SET status = 'serving', called_at = CURRENT_TIMESTAMP WHERE token_id = ?");
        $stmt->bind_param("i", $token_id);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE appointments SET status = 'serving' WHERE appointment_id = ?");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();

        // Get student name for broadcast
        $stmt = $conn->prepare("SELECT u.full_name FROM appointments a JOIN students s ON a.student_id = s.student_id JOIN users u ON s.user_id = u.user_id WHERE a.appointment_id = ?");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $student_name = $stmt->get_result()->fetch_assoc()['full_name'] ?? 'Unknown';

        // Update remaining wait times
        $stmt = $conn->prepare("UPDATE queue_tokens SET estimated_wait_minutes = GREATEST(0, (token_number - ?) * 10) WHERE advisor_id = ? AND queue_date = ? AND status IN ('booked', 'waiting')");
        $stmt->bind_param("iis", $next_token_number, $advisor_id, $today);
        $stmt->execute();
    }

    $conn->commit();

    // Trigger SSE state update (Update a timestamp file that SSE script watches)
    file_put_contents(__DIR__ . '/../../queue/state.txt', time());

    echo json_encode([
        'success' => true,
        'message' => $next_token ? 'Next student called.' : 'Queue is empty.',
        'data' => [
            'advisor_id' => $advisor_id,
            'current_serving_token' => $next_token_number,
            'current_serving_student_name' => $student_name,
            'timestamp' => date('c')
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
