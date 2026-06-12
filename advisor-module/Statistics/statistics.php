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

// Overall stats
$stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(status='completed') as completed, SUM(status='missed') as missed, SUM(status='cancelled') as cancelled FROM appointments WHERE advisor_id = ?");
$stmt->bind_param("i", $advisor_id);
$stmt->execute();
$overall = $stmt->get_result()->fetch_assoc();

$total = $overall['total'] ?: 1;
$completion_rate = round(($overall['completed'] / $total) * 100);
$miss_rate = round(($overall['missed'] / $total) * 100);

// Unique students
$stmt = $conn->prepare("SELECT COUNT(DISTINCT student_id) as unique_students FROM appointments WHERE advisor_id = ?");
$stmt->bind_param("i", $advisor_id);
$stmt->execute();
$unique = $stmt->get_result()->fetch_assoc();

// This week
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end   = date('Y-m-d', strtotime('sunday this week'));
$stmt = $conn->prepare("SELECT SUM(status='completed') as done, SUM(status='missed') as missed FROM appointments WHERE advisor_id = ? AND appointment_date BETWEEN ? AND ?");
$stmt->bind_param("iss", $advisor_id, $week_start, $week_end);
$stmt->execute();
$week = $stmt->get_result()->fetch_assoc();
$week_total = ($week['done'] + $week['missed']) ?: 1;
$week_rate = round(($week['done'] / $week_total) * 100);

// At-risk students (missed >= 2)
$stmt = $conn->prepare("SELECT s.student_id, u.full_name, s.missed_count FROM students s JOIN users u ON s.user_id = u.user_id JOIN appointments a ON s.student_id = a.student_id WHERE a.advisor_id = ? AND s.missed_count >= 2 GROUP BY s.student_id ORDER BY s.missed_count DESC");
$stmt->bind_param("i", $advisor_id);
$stmt->execute();
$at_risk = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - UIU Advisor Portal</title>
    <link rel="stylesheet" href="satistics.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .sidebar{width:220px;background:#2f2c52;color:white;display:flex;flex-direction:column;justify-content:space-between;padding:20px 0;position:fixed;top:0;left:0;height:100vh;z-index:100;}
        .sidebar .brand{text-align:center;font-size:13px;font-weight:700;color:#cfe2ff;padding:0 20px 20px;letter-spacing:1px;}
        .menu{list-style:none;margin:0;padding:0;}
        .menu li a{display:flex;align-items:center;gap:15px;padding:16px 30px;color:#cfcfcf;text-decoration:none;transition:0.2s;font-size:14px;}
        .menu li a:hover,.menu li.active a{background:#44406b;color:white;}
        .sidebar-bottom{padding:0 0 10px;border-top:1px solid rgba(255,255,255,0.1);}
        .sidebar-bottom a{display:flex;align-items:center;gap:15px;padding:16px 30px;color:#cfcfcf;text-decoration:none;font-size:14px;transition:0.2s;}
        .sidebar-bottom a:hover{background:#44406b;color:white;}
        body{display:flex;margin:0;}
        .container{margin-left:220px;flex:1;padding:30px;font-family:'Inter',sans-serif;}
        .empty-risk{color:#888;padding:20px;text-align:center;}
    </style>
</head>
<body>
<div class="sidebar">
    <div>
        <div class="brand">UIU ADVISOR</div>
        <ul class="menu">
            <li><a href="../Advisor Dashboard Overview/advisor-dashboard.php"><i class="fa-solid fa-house"></i><span>Home</span></a></li>
            <li><a href="../Student Appoinment Management/appointment-management.php"><i class="fa-solid fa-file"></i><span>Requests</span></a></li>
            <li><a href="../Time Management/time-management.php"><i class="fa-solid fa-calendar"></i><span>Schedule</span></a></li>
            <li class="active"><a href="statistics.php"><i class="fa-solid fa-chart-bar"></i><span>Statistics</span></a></li>
        </ul>
    </div>
    <div class="sidebar-bottom">
        <a href="../../backend/logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Log Out</span></a>
    </div>
</div>

<div class="container">
    <div class="header">
        <div class="header-left">
            <div class="logo-box">📊</div>
            <div><h1>Statistics<span>& Analytics</span></h1><p>Track your appointment patterns and performance</p></div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="card">
            <div class="card-top"><p>TOTAL APPOINTMENTS</p><div class="icon blue">📋</div></div>
            <h2><?php echo $overall['total']; ?></h2>
            <span class="green-text">All time</span>
        </div>
        <div class="card">
            <div class="card-top"><p>COMPLETED</p><div class="icon green">✓</div></div>
            <h2><?php echo $overall['completed']; ?></h2>
            <span><?php echo $completion_rate; ?>% completion rate</span>
        </div>
        <div class="card">
            <div class="card-top"><p>MISSED</p><div class="icon red">✕</div></div>
            <h2><?php echo $overall['missed']; ?></h2>
            <span><?php echo $miss_rate; ?>% miss rate</span>
        </div>
        <div class="card">
            <div class="card-top"><p>UNIQUE STUDENTS</p><div class="icon purple">👥</div></div>
            <h2><?php echo $unique['unique_students']; ?></h2>
            <span>Active this semester</span>
        </div>
    </div>

    <div class="middle-grid">
        <div class="overview-card">
            <div class="section-title"><h3>This Week's Overview</h3><p>Completed vs missed appointments</p></div>
            <div style="padding:20px;display:flex;gap:20px;">
                <div class="summary green-bg"><h4><?php echo $week['done']; ?></h4><p>Completed this week</p></div>
                <div class="summary red-bg"><h4><?php echo $week['missed']; ?></h4><p>Missed this week</p></div>
                <div class="summary blue-bg"><h4><?php echo $week_rate; ?>%</h4><p>Week rate</p></div>
            </div>
        </div>
        <div class="side-column">
            <div class="side-card">
                <div class="section-title"><h3>Appointment Breakdown</h3><p>Status distribution</p></div>
                <div class="course">
                    <div class="course-head"><span>Completed</span><span><?php echo $overall['completed']; ?></span></div>
                    <div class="line"><div class="line-fill" style="width:<?php echo $completion_rate; ?>%;"></div></div>
                </div>
                <div class="course">
                    <div class="course-head"><span>Missed</span><span><?php echo $overall['missed']; ?></span></div>
                    <div class="line"><div class="line-fill" style="width:<?php echo $miss_rate; ?>%;background:#dc2626;"></div></div>
                </div>
                <div class="course">
                    <div class="course-head"><span>Cancelled</span><span><?php echo $overall['cancelled']; ?></span></div>
                    <div class="line"><div class="line-fill" style="width:<?php echo round(($overall['cancelled']/$total)*100); ?>%;background:#f59e0b;"></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="table-header">
            <div><h3>At-Risk Students Alert</h3><p>Students who missed 2+ appointments</p></div>
        </div>
        <table>
            <thead>
                <tr><th>STUDENT</th><th>STUDENT ID</th><th>MISSED</th><th>RISK LEVEL</th><th>ACTION</th></tr>
            </thead>
            <tbody>
                <?php if(empty($at_risk)): ?>
                    <tr><td colspan="5" class="empty-risk">No at-risk students currently.</td></tr>
                <?php else: foreach($at_risk as $s):
                    $risk = $s['missed_count'] >= 4 ? 'High Risk' : 'At Risk';
                    $risk_class = $s['missed_count'] >= 4 ? 'high' : 'medium';
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($s['student_id']); ?></td>
                        <td><?php echo $s['missed_count']; ?> times</td>
                        <td><span class="risk <?php echo $risk_class; ?>"><?php echo $risk; ?></span></td>
                        <td><button class="contact-btn" onclick="alert('Contact feature coming soon')">Contact</button></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
