<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

require '../../backend/db_connect.php';

// Turn off output buffering
if (ob_get_level()) ob_end_clean();

$state_file = __DIR__ . '/state.txt';
if (!file_exists($state_file)) {
    file_put_contents($state_file, time());
}

$last_mtime = 0;
$advisor_id = isset($_GET['advisor_id']) ? (int)$_GET['advisor_id'] : null;

while (true) {
    clearstatcache();
    $current_mtime = filemtime($state_file);

    if ($current_mtime > $last_mtime) {
        $last_mtime = $current_mtime;
        
        if ($advisor_id) {
            $today = date('Y-m-d');
            
            // Get Current Serving
            $stmt = $conn->prepare("SELECT qt.token_number, u.full_name as student_name FROM queue_tokens qt JOIN appointments a ON qt.appointment_id = a.appointment_id JOIN students s ON a.student_id = s.student_id JOIN users u ON s.user_id = u.user_id WHERE qt.advisor_id = ? AND qt.queue_date = ? AND qt.status = 'serving' ORDER BY qt.token_number ASC LIMIT 1");
            $stmt->bind_param("is", $advisor_id, $today);
            $stmt->execute();
            $serving = $stmt->get_result()->fetch_assoc();

            // Get Next Token
            $stmt = $conn->prepare("SELECT token_number FROM queue_tokens WHERE advisor_id = ? AND queue_date = ? AND status IN ('booked', 'waiting') ORDER BY token_number ASC LIMIT 1");
            $stmt->bind_param("is", $advisor_id, $today);
            $stmt->execute();
            $next = $stmt->get_result()->fetch_assoc();

            // Get Remaining Queue Count
            $stmt = $conn->prepare("SELECT COUNT(*) as remaining FROM queue_tokens WHERE advisor_id = ? AND queue_date = ? AND status IN ('booked', 'waiting')");
            $stmt->bind_param("is", $advisor_id, $today);
            $stmt->execute();
            $remaining = $stmt->get_result()->fetch_assoc()['remaining'];

            $data = [
                'advisor_id' => $advisor_id,
                'current_serving_token' => $serving ? $serving['token_number'] : null,
                'current_serving_student_name' => $serving ? $serving['student_name'] : null,
                'next_token_in_line' => $next ? $next['token_number'] : null,
                'remaining_queue_count' => $remaining,
                'timestamp' => date('c')
            ];

            echo "event: QUEUE_STATE_CHANGED\n";
            echo "data: " . json_encode($data) . "\n\n";
            flush();
        }
    }
    
    // Check connection status to exit loop if client disconnected
    if (connection_aborted()) {
        break;
    }
    
    sleep(1);
}
?>
