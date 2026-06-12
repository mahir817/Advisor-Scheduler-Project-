<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

require '../backend/db_connect.php';

$user_id = $_SESSION['user_id'];
$full_name = htmlspecialchars($_SESSION['full_name']);
$first_name = explode(' ', $full_name)[0];

// Get student_id
$stmt = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$student = $res->fetch_assoc();
$student_id = $student['student_id'] ?? null;

// Get upcoming appointment
$appt = null;
if ($student_id) {
    $appt_query = "SELECT a.status, a.appointment_date, a.appointment_time, 
                          ap.room_number, d.department_name, u.full_name as advisor_name, 
                          qt.token_number, qt.estimated_wait_minutes 
                   FROM appointments a 
                   JOIN advisor_profiles ap ON a.advisor_id = ap.advisor_id 
                   JOIN users u ON ap.user_id = u.user_id 
                   LEFT JOIN departments d ON ap.department_id = d.department_id 
                   LEFT JOIN queue_tokens qt ON a.appointment_id = qt.appointment_id 
                   WHERE a.student_id = ? AND a.status IN ('booked', 'waiting', 'serving') 
                   ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 1";
    $stmt = $conn->prepare($appt_query);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $appt_res = $stmt->get_result();
    if ($appt_res->num_rows > 0) {
        $appt = $appt_res->fetch_assoc();
    }
}

// Get available faculty
$faculty_query = "SELECT u.full_name, d.department_name, ap.is_available 
                  FROM advisor_profiles ap 
                  JOIN users u ON ap.user_id = u.user_id 
                  LEFT JOIN departments d ON ap.department_id = d.department_id 
                  WHERE u.is_active = 1 
                  LIMIT 5";
$faculty_res = $conn->query($faculty_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="css/student.css">
    <style>
        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 8px 16px;
            background-color: #ff4d4d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .logout-btn:hover { background-color: #cc0000; }
    </style>
</head>
<body>
    <a href="../backend/logout.php" class="logout-btn">Logout</a>
    <div class="main-container">
        <div class="header-title">
            <div class="hello">Hello,</div>
            <div class="name"><?php echo $first_name; ?>!</div>
        </div>
        <div class="header-subtitle">Welcome back to your advising portal.</div>
        <div class="row">
            <div class="col-token">
                <div class="card token-card">
                    <div class="token-label">Token Number</div>
                    <?php if ($appt && $appt['token_number']): ?>
                        <div class="token-status">#<?php echo $appt['token_number']; ?></div>
                        <div class="token-sub">Status: <?php echo ucfirst($appt['status']); ?></div>
                        <div class="token-right">
                            <div class="token-time"><?php echo $appt['estimated_wait_minutes'] ?? '0'; ?> mins</div>
                            <div class="token-wait">&#128336; Est. wait</div>
                        </div>
                    <?php elseif ($appt): ?>
                        <div class="token-status">No Token Yet</div>
                        <div class="token-sub">Your token will be generated on the appointment day.</div>
                        <div class="token-right">
                            <div class="token-time">--</div>
                            <div class="token-wait">&#128336; Est. wait</div>
                        </div>
                    <?php else: ?>
                        <div class="token-status">No Advising</div>
                        <div class="token-sub">You have to book an appointment first</div>
                        <div class="token-right">
                            <div class="token-time">0 mins</div>
                            <div class="token-wait">&#128336; Est. wait</div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="progress-bar-container">
                        <div class="progress-line"></div>
                        <div class="progress-nodes">
                            <?php 
                            $status = $appt['status'] ?? '';
                            $booked = in_array($status, ['booked', 'waiting', 'serving']) ? 'style="background-color: #007bff;"' : '';
                            $waiting = in_array($status, ['waiting', 'serving']) ? 'style="background-color: #007bff;"' : '';
                            $serving = in_array($status, ['serving']) ? 'style="background-color: #007bff;"' : '';
                            ?>
                            <div class="node-wrapper">
                                <div class="node-circle" <?php echo $booked; ?>></div>
                                <div class="node-label">Booked</div>
                            </div>
                            <div class="node-wrapper">
                                <div class="node-circle" <?php echo $waiting; ?>></div>
                                <div class="node-label">Waiting</div>
                            </div>
                            <div class="node-wrapper">
                                <div class="node-circle" <?php echo $serving; ?>></div>
                                <div class="node-label">Serving</div>
                            </div>
                            <div class="node-wrapper">
                                <div class="node-circle"></div>
                                <div class="node-label">Canceled</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-quick-actions">
                <div class="section-title">Quick Actions</div>
                <div class="quick-cards-wrapper">
                    <a href="booking.html" class="action-card bg-white" style="text-decoration: none;">
                        <div class="action-icon">&#128197;</div>
                        <div class="action-text">Book Appointment</div>
                    </a>
                    <div class="action-card bg-grey">
                        <div class="action-icon">&#128196;</div>
                        <div class="action-text">Upload Documents</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-advisor">
                <div class="section-title">Attending Advisors</div>
                <div class="card">
                    <?php if ($appt): ?>
                        <div class="ca-title">Current Advisor</div>
                        <div class="user-info">
                            <div class="user-icon-circle">&#128100;</div>
                            <div class="user-details">
                                <div class="user-name"><?php echo htmlspecialchars($appt['advisor_name']); ?></div>
                                <div class="user-dept"><?php echo htmlspecialchars($appt['department_name']); ?></div>
                            </div>
                        </div>
                        
                        <div class="run-token-label">Running token Number</div>
                        <div class="run-token-num">#<?php echo $appt['token_number'] ?? '--'; ?></div>
                        
                        <div class="info-row">
                            <div class="info-item">&#128197; <?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></div>
                            <div class="info-item">&#128336; <?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></div>
                            <div class="info-item">&#127968; Room <?php echo htmlspecialchars($appt['room_number']); ?></div>
                        </div>
                        
                        <div class="btn-group">
                            <div class="btn btn-cyan btn-ask">Upoad Documents</div>
                            <a href="queue-status.html" class="btn btn-blue btn-view" style="text-decoration: none;">Appointment Room</a>
                        </div>
                    <?php else: ?>
                        <div style="padding: 30px; text-align: center; color: #666;">
                            No upcoming appointments.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-faculty">
                <div class="section-title">Available Faculty</div>
                <div class="card" style="padding-bottom: 50px;">
                    <div class="faculty-list">
                        <?php while($faculty = $faculty_res->fetch_assoc()): ?>
                            <div class="faculty-row">
                                <div class="faculty-left">
                                    <div class="user-info" style="margin-bottom:0;">
                                        <div class="user-icon-circle">&#128100;</div>
                                        <div class="user-details">
                                            <div class="user-name"><?php echo htmlspecialchars($faculty['full_name']); ?></div>
                                            <div class="user-dept"><?php echo htmlspecialchars($faculty['department_name']); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="faculty-right">
                                    <div class="btn btn-cyan" style="padding: 5px 10px; font-size:12px;">Book an Appointment</div>
                                    <?php if($faculty['is_available']): ?>
                                        <div class="pill pill-green">Available</div>
                                    <?php else: ?>
                                        <div class="pill pill-orange">Busy</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    <a href="advisor-list.html" class="btn btn-blue btn-view-all" style="text-decoration: none;">View All</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/student.js"></script>
</body>
</html>
