<?php
require 'backend/db_connect.php';

$appts = $conn->query("SELECT * FROM appointments ORDER BY appointment_id DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$tokens = $conn->query("SELECT * FROM queue_tokens ORDER BY token_id DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

echo "Appointments:\n";
print_r($appts);
echo "\nTokens:\n";
print_r($tokens);
?>
