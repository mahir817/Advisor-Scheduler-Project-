<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'advisor') {
    header("Location: ../../index.html"); exit;
}
require '../../backend/db_connect.php';

$user_id = $_SESSION['user_id'];
$full_name = htmlspecialchars($_SESSION['full_name']);
$stmt = $conn->prepare("SELECT advisor_id FROM advisor_profiles WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$adv = $stmt->get_result()->fetch_assoc();
$advisor_id = $adv['advisor_id'];
$today = date('Y-m-d');

// Get queue summary
$stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(status='completed') as served, SUM(status IN ('booked','waiting')) as remaining FROM queue_tokens WHERE advisor_id = ? AND queue_date = ?");
$stmt->bind_param("is", $advisor_id, $today);
$stmt->execute();
$queue = $stmt->get_result()->fetch_assoc();

// Current serving
$stmt = $conn->prepare("SELECT a.appointment_id, u.full_name as student_name, s.student_id, a.purpose, qt.token_number FROM appointments a JOIN students s ON a.student_id = s.student_id JOIN users u ON s.user_id = u.user_id LEFT JOIN queue_tokens qt ON a.appointment_id = qt.appointment_id WHERE a.advisor_id = ? AND a.appointment_date = ? AND a.status = 'serving' ORDER BY qt.token_number ASC LIMIT 1");
$stmt->bind_param("is", $advisor_id, $today);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();

// Next token
$stmt = $conn->prepare("SELECT a.appointment_id, u.full_name as student_name, a.appointment_time, qt.token_number FROM appointments a JOIN students s ON a.student_id = s.student_id JOIN users u ON s.user_id = u.user_id LEFT JOIN queue_tokens qt ON a.appointment_id = qt.appointment_id WHERE a.advisor_id = ? AND a.appointment_date = ? AND a.status IN ('booked','waiting') ORDER BY qt.token_number ASC LIMIT 1");
$stmt->bind_param("is", $advisor_id, $today);
$stmt->execute();
$next = $stmt->get_result()->fetch_assoc();

// Booking requests
$stmt = $conn->prepare("SELECT a.appointment_id, u.full_name as student_name, s.student_id, a.purpose, a.appointment_time, qt.token_number FROM appointments a JOIN students s ON a.student_id = s.student_id JOIN users u ON s.user_id = u.user_id LEFT JOIN queue_tokens qt ON a.appointment_id = qt.appointment_id WHERE a.advisor_id = ? AND a.appointment_date = ? AND a.status = 'booked' ORDER BY qt.token_number ASC");
$stmt->bind_param("is", $advisor_id, $today);
$stmt->execute();
$booked_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Waiting list
$stmt = $conn->prepare("SELECT a.appointment_id, u.full_name as student_name, s.student_id, a.purpose, a.appointment_time, qt.token_number FROM appointments a JOIN students s ON a.student_id = s.student_id JOIN users u ON s.user_id = u.user_id LEFT JOIN queue_tokens qt ON a.appointment_id = qt.appointment_id WHERE a.advisor_id = ? AND a.appointment_date = ? AND a.status = 'waiting' ORDER BY qt.token_number ASC");
$stmt->bind_param("is", $advisor_id, $today);
$stmt->execute();
$waiting_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total = $queue['total'] ?? 0;
$served = $queue['served'] ?? 0;
$pct = $total > 0 ? round(($served / $total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Management - UIU Advisor Portal</title>
    <link rel="stylesheet" href="AppoinmentManagement.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body{display:flex;margin:0;font-family:'Segoe UI',sans-serif;background:#f0f4f8;}
        .sidebar{width:220px;background:#2f2c52;color:white;display:flex;flex-direction:column;justify-content:space-between;padding:20px 0;position:fixed;top:0;left:0;height:100vh;z-index:100;}
        .sidebar .brand{text-align:center;font-size:13px;font-weight:700;color:#cfe2ff;padding:0 20px 20px;letter-spacing:1px;}
        .menu{list-style:none;margin:0;padding:0;}
        .menu li a{display:flex;align-items:center;gap:15px;padding:16px 30px;color:#cfcfcf;text-decoration:none;transition:0.2s;font-size:14px;}
        .menu li a:hover,.menu li.active a{background:#44406b;color:white;}
        .sidebar-bottom{padding:0 0 10px;border-top:1px solid rgba(255,255,255,0.1);}
        .sidebar-bottom a{display:flex;align-items:center;gap:15px;padding:16px 30px;color:#cfcfcf;text-decoration:none;font-size:14px;transition:0.2s;}
        .sidebar-bottom a:hover{background:#44406b;color:white;}
        .page-content{margin-left:220px;flex:1;padding:20px;}
        .action-btns{display:flex;gap:8px;flex-wrap:wrap;}
        .action-btns button,.btn-next{padding:7px 14px;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px;}
        .btn-approve{background:#16a34a;color:white;}
        .btn-decline{background:#dc2626;color:white;}
        .btn-next{background:#2563eb;color:white;}
        .empty-msg{color:#888;padding:20px;text-align:center;}
    </style>
</head>
<body>
    <div class="sidebar">
        <div>
            <div class="brand">UIU ADVISOR</div>
            <ul class="menu">
                <li><a href="../Advisor Dashboard Overview/advisor-dashboard.php"><i class="fa-solid fa-house"></i><span>Home</span></a></li>
                <li class="active"><a href="appointment-management.php"><i class="fa-solid fa-file"></i><span>Requests</span></a></li>
                <li><a href="../Time Management/time-management.php"><i class="fa-solid fa-calendar"></i><span>Schedule</span></a></li>
                <li><a href="../Statistics/statistics.php"><i class="fa-solid fa-chart-bar"></i><span>Statistics</span></a></li>
            </ul>
        </div>
        <div class="sidebar-bottom">
            <a href="../../backend/logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Log Out</span></a>
        </div>
    </div>

    <div class="page-content">
        <div class="dashboard-container">
            <header class="header">
                <h2><a href="../Advisor Dashboard Overview/advisor-dashboard.php" style="text-decoration:none;color:inherit;">← Advisor Command Center</a></h2>
                <div class="user-profile">
                    <span class="status-dot"></span> <?php echo $full_name; ?> — Online
                    <div class="avatar"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
                </div>
            </header>

            <div class="stats-grid">
                <div class="card current-token">
                    <p>CURRENT TOKEN</p>
                    <?php if($current): ?>
                        <h1>#<?php echo str_pad($current['token_number'], 2, '0', STR_PAD_LEFT); ?></h1>
                        <p><?php echo htmlspecialchars($current['student_name']); ?></p>
                        <div class="action-btns" style="margin-top:10px;">
                            <button class="btn-approve btn-next" onclick="markComplete(<?php echo $current['appointment_id']; ?>)">✓ Complete</button>
                            <button class="btn-decline" onclick="markMissed(<?php echo $current['appointment_id']; ?>)">✗ Missed</button>
                        </div>
                    <?php else: ?>
                        <h1>--</h1><p>No active session</p>
                        <?php if($next): ?><button class="btn-next" onclick="callNext(<?php echo $next['appointment_id']; ?>)">Call Next →</button><?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="card next-token">
                    <p>NEXT TOKEN</p>
                    <?php if($next): ?>
                        <h1>#<?php echo str_pad($next['token_number'], 2, '0', STR_PAD_LEFT); ?></h1>
                        <p><?php echo htmlspecialchars($next['student_name']); ?></p>
                        <span>Scheduled — <?php echo date('h:i A', strtotime($next['appointment_time'])); ?></span>
                    <?php else: ?><h1>--</h1><p>Queue empty</p><?php endif; ?>
                </div>

                <div class="card queue-status">
                    <p>TODAY'S QUEUE</p>
                    <div class="queue-numbers">
                        <div><strong><?php echo $total; ?></strong><br>Total</div>
                        <div><strong><?php echo $served; ?></strong><br>Served</div>
                        <div><strong><?php echo $queue['remaining'] ?? 0; ?></strong><br>Left</div>
                    </div>
                    <div class="progress-bar"><div class="progress" style="width:<?php echo $pct; ?>%;"></div></div>
                    <small><?php echo $pct; ?>% complete</small>
                </div>

                <div class="card avg-session"><p>AVG SESSION</p><h1>10</h1><p>min / student</p><span class="track-text">✓ On Track</span></div>
            </div>

            <div class="booking-section">
                <div class="tabs">
                    <button class="tab-btn active" data-target="approval-queue">Booking Requests (<?php echo count($booked_list); ?>)</button>
                    <button class="tab-btn" data-target="waiting-list">Waiting List (<?php echo count($waiting_list); ?>)</button>
                </div>

                <div class="booking-list" id="approval-queue">
                    <h3>Booking Requests</h3>
                    <?php if(empty($booked_list)): ?>
                        <p class="empty-msg">No pending requests for today.</p>
                    <?php else: foreach($booked_list as $req): ?>
                        <div class="request-row" id="row-<?php echo $req['appointment_id']; ?>">
                            <div class="token-id">#<?php echo str_pad($req['token_number'], 2, '0', STR_PAD_LEFT); ?></div>
                            <div class="student-info">
                                <strong><?php echo htmlspecialchars($req['student_name']); ?></strong> <span><?php echo htmlspecialchars($req['student_id']); ?></span>
                                <p><?php echo htmlspecialchars($req['purpose'] ?? 'General Advising'); ?> • <?php echo date('h:i A', strtotime($req['appointment_time'])); ?></p>
                            </div>
                            <div class="actions action-btns">
                                <button class="btn-approve" onclick="callNext(<?php echo $req['appointment_id']; ?>)">Call Now</button>
                                <button class="btn-decline" onclick="markMissed(<?php echo $req['appointment_id']; ?>)">Decline</button>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <div class="booking-list" id="waiting-list" style="display:none;">
                    <h3>Waiting List</h3>
                    <?php if(empty($waiting_list)): ?>
                        <p class="empty-msg">No students currently waiting.</p>
                    <?php else: foreach($waiting_list as $req): ?>
                        <div class="request-row" id="row-<?php echo $req['appointment_id']; ?>">
                            <div class="token-id">#<?php echo str_pad($req['token_number'], 2, '0', STR_PAD_LEFT); ?></div>
                            <div class="student-info">
                                <strong><?php echo htmlspecialchars($req['student_name']); ?></strong> <span><?php echo htmlspecialchars($req['student_id']); ?></span>
                                <p><?php echo htmlspecialchars($req['purpose'] ?? 'General Advising'); ?> • <?php echo date('h:i A', strtotime($req['appointment_time'])); ?></p>
                            </div>
                            <div class="actions action-btns">
                                <button class="btn-decline" onclick="markMissed(<?php echo $req['appointment_id']; ?>)">Remove</button>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>

<script>
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.booking-list').forEach(l => l.style.display = 'none');
        btn.classList.add('active');
        document.getElementById(btn.dataset.target).style.display = 'block';
    });
});
function apiCall(action, appointmentId) {
    fetch('../../backend/advisor_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=${action}&appointment_id=${appointmentId}`
    }).then(r => r.json()).then(d => { if(d.success) location.reload(); else alert(d.message); });
}
function callNext(id) { apiCall('call_next', id); }
function markComplete(id) { apiCall('mark_complete', id); }
function markMissed(id) { if(confirm('Mark as missed/declined?')) apiCall('mark_missed', id); }
</script>
</body>
</html>
