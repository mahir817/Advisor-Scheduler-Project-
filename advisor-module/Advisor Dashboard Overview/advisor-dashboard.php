<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'advisor') {
    header("Location: ../../index.html");
    exit;
}

require '../../backend/db_connect.php';

$user_id = $_SESSION['user_id'];
$full_name = htmlspecialchars($_SESSION['full_name']);

// Get advisor profile
$stmt = $conn->prepare("SELECT ap.advisor_id, ap.room_number, ap.office_hours, ap.is_available, d.department_name 
                         FROM advisor_profiles ap 
                         LEFT JOIN departments d ON ap.department_id = d.department_id 
                         WHERE ap.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$advisor = $stmt->get_result()->fetch_assoc();
$advisor_id = $advisor['advisor_id'];
$today = date('Y-m-d');

// Stats: today's appointments
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(status='completed') as done,
    SUM(status='missed') as no_shows,
    SUM(status='booked') as pending
    FROM appointments WHERE advisor_id = ? AND appointment_date = ?");
$stmt->bind_param("is", $advisor_id, $today);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Active session: currently serving
$stmt = $conn->prepare("SELECT a.appointment_id, a.status, u.full_name as student_name, qt.token_number
    FROM appointments a
    JOIN students s ON a.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN queue_tokens qt ON a.appointment_id = qt.appointment_id
    WHERE a.advisor_id = ? AND a.appointment_date = ? AND a.status = 'serving'
    ORDER BY qt.token_number ASC LIMIT 1");
$stmt->bind_param("is", $advisor_id, $today);
$stmt->execute();
$active = $stmt->get_result()->fetch_assoc();

// Next in queue
$stmt = $conn->prepare("SELECT a.appointment_id, u.full_name as student_name, a.appointment_time, qt.token_number
    FROM appointments a
    JOIN students s ON a.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN queue_tokens qt ON a.appointment_id = qt.appointment_id
    WHERE a.advisor_id = ? AND a.appointment_date = ? AND a.status IN ('booked','waiting')
    ORDER BY qt.token_number ASC LIMIT 1");
$stmt->bind_param("is", $advisor_id, $today);
$stmt->execute();
$next = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advisor Dashboard - UIU Advisor Scheduler</title>
    <link rel="stylesheet" href="DashboardOverview.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .sidebar { width:220px; background:#2f2c52; color:white; display:flex; flex-direction:column; justify-content:space-between; padding:20px 0; position:fixed; height:100vh; }
        .sidebar-top { flex:1; }
        .sidebar .brand { text-align:center; font-size:13px; font-weight:700; color:#cfe2ff; padding:0 20px 20px; letter-spacing:1px; }
        .menu { list-style:none; margin:0; padding:0; }
        .menu li a { display:flex; align-items:center; gap:15px; padding:16px 30px; color:#cfcfcf; text-decoration:none; transition:0.2s; font-size:14px; }
        .menu li a:hover, .menu li.active a { background:#44406b; color:white; }
        .sidebar-bottom { padding:0 0 10px; border-top:1px solid rgba(255,255,255,0.1); }
        .sidebar-bottom a { display:flex; align-items:center; gap:15px; padding:16px 30px; color:#cfcfcf; text-decoration:none; font-size:14px; transition:0.2s; }
        .sidebar-bottom a:hover { background:#44406b; color:white; }
        .main-content { margin-left:220px; flex:1; padding:20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="sidebar">
        <div class="sidebar-top">
            <div class="brand">UIU ADVISOR</div>
            <ul class="menu">
                <li class="active"><a href="advisor-dashboard.php"><i class="fa-solid fa-house"></i><span>Home</span></a></li>
                <li><a href="../Student Appoinment Management/appointment-management.php"><i class="fa-solid fa-file"></i><span>Requests</span></a></li>
                <li><a href="../Time Management/time-management.php"><i class="fa-solid fa-calendar"></i><span>Schedule</span></a></li>
                <li><a href="../Statistics/statistics.php"><i class="fa-solid fa-clock-rotate-left"></i><span>Statistics</span></a></li>
            </ul>
        </div>
        <div class="sidebar-bottom">
            <a href="../../backend/logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Log Out</span></a>
        </div>
    </div>

    <div class="main-content">
        <div class="topbar">
            <div class="breadcrumbs">Pages &gt; Dashboard</div>
            <div class="top-right">
                <div class="time" id="live-time"></div>
                <button class="status-btn" id="status-btn" onclick="toggleAvailability()" data-advisor="<?php echo $advisor_id; ?>">
                    <?php echo $advisor['is_available'] ? 'Active' : 'Inactive'; ?>
                </button>
            </div>
        </div>

        <div class="header">
            <div>
                <h1>Welcome back, <?php echo $full_name; ?> 👋</h1>
                <p id="current-date"><?php echo date('l, F j Y'); ?></p>
            </div>
            <div class="office-hours">Office Hours: <?php echo htmlspecialchars($advisor['office_hours'] ?? 'Not set'); ?></div>
        </div>

        <div class="cards">
            <div class="card pending">
                <div class="card-top"><p>PENDING</p><i class="fa-solid fa-circle-exclamation yellow"></i></div>
                <h2><?php echo $stats['pending'] ?? 0; ?></h2>
                <div class="card-bottom">
                    <a href="../Student Appoinment Management/appointment-management.php">View All →</a>
                </div>
            </div>
            <div class="card">
                <div class="card-top"><p>TODAY'S APPTS</p><i class="fa-solid fa-calendar green"></i></div>
                <h2><?php echo $stats['total'] ?? 0; ?></h2>
                <div class="card-bottom">
                    <span class="done"><?php echo $stats['done'] ?? 0; ?> done</span>
                    <?php if($next): ?>
                        <small>Next: <?php echo date('h:i A', strtotime($next['appointment_time'])); ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-top"><p>AVG. SESSION</p><i class="fa-solid fa-clock blue"></i></div>
                <h2>10 <span>min</span></h2>
                <div class="card-bottom"><span class="efficient">● Efficient</span></div>
            </div>
            <div class="card">
                <div class="card-top"><p>NO-SHOWS</p><i class="fa-solid fa-circle-xmark red"></i></div>
                <h2><?php echo $stats['no_shows'] ?? 0; ?></h2>
                <div class="card-bottom"><span class="cancelled">Auto-cancelled</span></div>
            </div>
        </div>

        <div class="session-box">
            <div class="session-header">
                <h3>● ACTIVE SESSION</h3>
                <?php if($active): ?>
                    <span style="color:#16a34a;">In Progress</span>
                <?php else: ?>
                    <span>Not started</span>
                <?php endif; ?>
            </div>
            <div class="session-content">
                <?php if($active): ?>
                    <p>Now serving: <strong><?php echo htmlspecialchars($active['student_name']); ?></strong> (Token #<?php echo $active['token_number']; ?>)</p>
                    <div style="margin-top:10px; display:flex; gap:10px;">
                        <button onclick="markComplete(<?php echo $active['appointment_id']; ?>)" style="background:#16a34a;color:white;border:none;padding:10px 20px;border-radius:8px;cursor:pointer;font-weight:600;">✓ Mark Complete</button>
                        <button onclick="markMissed(<?php echo $active['appointment_id']; ?>)" style="background:#dc2626;color:white;border:none;padding:10px 20px;border-radius:8px;cursor:pointer;font-weight:600;">✗ Mark Missed</button>
                    </div>
                <?php elseif($next): ?>
                    <p>No active session. Next student: <strong><?php echo htmlspecialchars($next['student_name']); ?></strong> (Token #<?php echo $next['token_number']; ?>)</p>
                    <button onclick="callNext(<?php echo $next['appointment_id']; ?>)" style="margin-top:10px;background:#2563eb;color:white;border:none;padding:10px 20px;border-radius:8px;cursor:pointer;font-weight:600;">Call Next Student →</button>
                <?php else: ?>
                    <p>No active appointment right now.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function updateTime() {
    const now = new Date();
    document.getElementById('live-time').textContent = now.toLocaleTimeString();
}
setInterval(updateTime, 1000); updateTime();

function toggleAvailability() {
    const btn = document.getElementById('status-btn');
    const advisorId = btn.dataset.advisor;
    fetch('../../backend/advisor_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=toggle_availability&advisor_id=${advisorId}`
    }).then(r => r.json()).then(d => {
        if(d.success) { btn.textContent = d.is_available ? 'Active' : 'Inactive'; location.reload(); }
    });
}

function callNext(appointmentId) {
    fetch('../../backend/advisor_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=call_next&appointment_id=${appointmentId}`
    }).then(r => r.json()).then(d => { if(d.success) location.reload(); else alert(d.message); });
}

function markComplete(appointmentId) {
    fetch('../../backend/advisor_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=mark_complete&appointment_id=${appointmentId}`
    }).then(r => r.json()).then(d => { if(d.success) location.reload(); else alert(d.message); });
}

function markMissed(appointmentId) {
    if(!confirm('Mark this student as missed?')) return;
    fetch('../../backend/advisor_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=mark_missed&appointment_id=${appointmentId}`
    }).then(r => r.json()).then(d => { if(d.success) location.reload(); else alert(d.message); });
}

// Listen for incoming new bookings via SSE
const advisorId = <?php echo $advisor_id; ?>;
const eventSource = new EventSource('../../api/queue/stream.php?advisor_id=' + advisorId);
eventSource.addEventListener('QUEUE_STATE_CHANGED', function(e) {
    console.log('Queue state changed remotely. Reloading dashboard...');
    location.reload();
});
</script>
</body>
</html>
