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

// Handle save weekly schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_weekly') {
        // Delete existing slots and re-insert
        $conn->prepare("DELETE FROM availability_slots WHERE advisor_id = ?")->execute() ;
        $del = $conn->prepare("DELETE FROM availability_slots WHERE advisor_id = ?");
        $del->bind_param("i", $advisor_id);
        $del->execute();
        
        $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
        foreach ($days as $day) {
            $key = strtolower($day);
            if (!empty($_POST["start_$key"]) && !empty($_POST["end_$key"])) {
                $start = $_POST["start_$key"];
                $end = $_POST["end_$key"];
                $active = isset($_POST["active_$key"]) ? 1 : 0;
                $ins = $conn->prepare("INSERT INTO availability_slots (advisor_id, day_of_week, start_time, end_time, is_active) VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param("isssi", $advisor_id, $day, $start, $end, $active);
                $ins->execute();
            }
        }
        header("Location: time-management.php?saved=1"); exit;
    }
    
    if ($_POST['action'] === 'add_unavailable') {
        $date   = $_POST['date'] ?? '';
        $start  = $_POST['time_start'] ?? '';
        $end    = $_POST['time_end'] ?? '';
        $reason = $_POST['reason'] ?? '';
        $full_reason = "$start - $end: $reason";
        $ins = $conn->prepare("INSERT INTO advisor_unavailable_dates (advisor_id, unavailable_date, reason) VALUES (?, ?, ?)");
        $ins->bind_param("iss", $advisor_id, $date, $full_reason);
        $ins->execute();
        header("Location: time-management.php?saved=1"); exit;
    }
    
    if ($_POST['action'] === 'delete_unavailable') {
        $uid = (int)$_POST['unavailable_id'];
        $del = $conn->prepare("DELETE FROM advisor_unavailable_dates WHERE unavailable_id = ? AND advisor_id = ?");
        $del->bind_param("ii", $uid, $advisor_id);
        $del->execute();
        header("Location: time-management.php"); exit;
    }
}

// Fetch current availability slots
$slots = [];
$res = $conn->prepare("SELECT day_of_week, start_time, end_time, is_active FROM availability_slots WHERE advisor_id = ? ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
$res->bind_param("i", $advisor_id);
$res->execute();
$slot_rows = $res->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($slot_rows as $row) {
    $slots[strtolower($row['day_of_week'])] = $row;
}

// Fetch custom unavailable dates
$unavail_res = $conn->prepare("SELECT unavailable_id, unavailable_date, reason FROM advisor_unavailable_dates WHERE advisor_id = ? ORDER BY unavailable_date DESC");
$unavail_res->bind_param("i", $advisor_id);
$unavail_res->execute();
$unavail_list = $unavail_res->get_result()->fetch_all(MYSQLI_ASSOC);

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Management - UIU Advisor Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Management.css">
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
        .wrapper{margin-left:220px;padding:30px;font-family:'Inter',sans-serif;}
        .success-msg{background:#d1fae5;border:1px solid #16a34a;color:#065f46;padding:12px 20px;border-radius:8px;margin-bottom:20px;}
    </style>
</head>
<body>
<div class="sidebar">
    <div>
        <div class="brand">UIU ADVISOR</div>
        <ul class="menu">
            <li><a href="../Advisor Dashboard Overview/advisor-dashboard.php"><i class="fa-solid fa-house"></i><span>Home</span></a></li>
            <li><a href="../Student Appoinment Management/appointment-management.php"><i class="fa-solid fa-file"></i><span>Requests</span></a></li>
            <li class="active"><a href="time-management.php"><i class="fa-solid fa-calendar"></i><span>Schedule</span></a></li>
            <li><a href="../Statistics/statistics.php"><i class="fa-solid fa-chart-bar"></i><span>Statistics</span></a></li>
        </ul>
    </div>
    <div class="sidebar-bottom">
        <a href="../../backend/logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Log Out</span></a>
    </div>
</div>

<div class="wrapper">
    <?php if(isset($_GET['saved'])): ?>
    <div class="success-msg">✓ Changes saved successfully!</div>
    <?php endif; ?>

    <div class="topbar">
        <div><div class="portal-small">Advisor Portal</div><div class="title">Time Management</div></div>
        <div class="user">
            <div class="user-info"><h4><?php echo $full_name; ?></h4><p>Academic Advisor</p></div>
            <div class="avatar"><?php echo strtoupper(substr($full_name, 0, 1)); ?></div>
        </div>
    </div>

    <h1 class="section-title">Set Your Availability</h1>
    <p class="section-sub">Manage your weekly schedule and add custom unavailable times for students to see.</p>

    <div class="tabs">
        <button class="tab active" data-target="weekly-view">Weekly Availability</button>
        <button class="tab" data-target="custom-view">Custom Unavailable Times</button>
    </div>

    <div id="weekly-view" class="view-section">
        <form method="POST" action="time-management.php">
            <input type="hidden" name="action" value="save_weekly">
            <?php foreach($days as $day):
                $key = strtolower($day);
                $slot = $slots[$key] ?? null;
                $is_active = $slot ? $slot['is_active'] : 0;
            ?>
            <div class="day-card">
                <div class="day-top">
                    <div class="day-left">
                        <div class="icon-box">🗓</div>
                        <div><div class="day-name"><?php echo $day; ?></div>
                        <div class="slot-count <?php echo !$slot ? 'gray' : ''; ?>"><?php echo $slot ? '1 slot configured' : 'Not set'; ?></div></div>
                    </div>
                    <div class="status-area <?php echo !$is_active ? 'gray' : ''; ?>">
                        <?php echo $is_active ? 'Available' : 'Unavailable'; ?>
                        <input type="checkbox" name="active_<?php echo $key; ?>" <?php echo $is_active ? 'checked' : ''; ?> style="margin-left:8px;">
                    </div>
                </div>
                <div class="slots" style="display:flex;gap:15px;align-items:center;padding:10px 0;">
                    <label>Start: <input type="time" name="start_<?php echo $key; ?>" value="<?php echo $slot ? substr($slot['start_time'],0,5) : '09:00'; ?>" style="padding:6px;border:1px solid #ddd;border-radius:6px;"></label>
                    <label>End: <input type="time" name="end_<?php echo $key; ?>" value="<?php echo $slot ? substr($slot['end_time'],0,5) : '17:00'; ?>" style="padding:6px;border:1px solid #ddd;border-radius:6px;"></label>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="footer"><p>Changes are saved to your advisor profile.</p><button type="submit" class="save-btn">✔ Save Weekly Schedule</button></div>
        </form>
    </div>

    <div id="custom-view" class="view-section" style="display:none;">
        <div class="day-card" style="flex-direction:column;align-items:stretch;margin-bottom:20px;">
            <h3 style="margin-bottom:15px;color:#2f2c52;">Added Unavailable Times</h3>
            <?php if(empty($unavail_list)): ?>
                <p style="color:#888;padding:10px 0;">No custom unavailable times set.</p>
            <?php else: foreach($unavail_list as $u): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;border:1px solid #eee;border-radius:8px;margin-bottom:8px;">
                    <div><strong><?php echo date('M d, Y', strtotime($u['unavailable_date'])); ?></strong> — <?php echo htmlspecialchars($u['reason']); ?></div>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="action" value="delete_unavailable">
                        <input type="hidden" name="unavailable_id" value="<?php echo $u['unavailable_id']; ?>">
                        <button type="submit" style="background:none;border:none;cursor:pointer;font-size:18px;color:#dc2626;">×</button>
                    </form>
                </div>
            <?php endforeach; endif; ?>

            <form method="POST" action="time-management.php" style="margin-top:20px;display:flex;flex-wrap:wrap;gap:10px;">
                <input type="hidden" name="action" value="add_unavailable">
                <input type="date" name="date" required style="padding:10px;border:1px solid #ddd;border-radius:8px;">
                <input type="time" name="time_start" required style="padding:10px;border:1px solid #ddd;border-radius:8px;">
                <input type="time" name="time_end" required style="padding:10px;border:1px solid #ddd;border-radius:8px;">
                <input type="text" name="reason" placeholder="Reason (e.g. Leave)" style="padding:10px;flex:1;min-width:200px;border:1px solid #ddd;border-radius:8px;">
                <button type="submit" style="padding:10px 20px;background:#6c5ce7;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600;">＋ Add</button>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.view-section').forEach(v => v.style.display = 'none');
        tab.classList.add('active');
        document.getElementById(tab.dataset.target).style.display = 'block';
    });
});
</script>
</body>
</html>
