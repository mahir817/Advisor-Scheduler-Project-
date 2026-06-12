<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.html"); exit;
}
require '../backend/db_connect.php';

$adv_res = $conn->query("SELECT a.advisor_id, u.full_name, d.department_name FROM advisor_profiles a JOIN users u ON a.user_id = u.user_id LEFT JOIN departments d ON a.department_id = d.department_id WHERE a.is_available = 1");
$advisors = $adv_res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book an Appointment</title>
<link rel="stylesheet" href="css/booking.css">
<style>
/* Adjust simple styling for new functional elements */
.select-styled {
    padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Inter', sans-serif;
    font-size: 14px; width: 100%; max-width: 300px;
}
.purpose-input {
    width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Inter', sans-serif;
    margin-top: 10px; margin-bottom: 20px;
}
</style>
</head>
<body>
    <div class="navbar">
        <div class="nav-left">
            <a href="student-dashboard.php"><div>&#8592;</div></a>
        </div>
        <div class="nav-center-container">
            <div class="nav-center">Book an Appointment</div>
            <div class="nav-center-para">Find and Select your Suitable time of Appointment</div>
        </div>
        <div class="nav-right"></div>
    </div>
    
    <div class="filters-container" style="display:flex; gap:20px; padding:20px 50px;">
        <div>
            <label style="display:block;margin-bottom:5px;font-weight:600;font-size:14px;color:#111827;">Select Advisor</label>
            <select id="advisor-select" class="select-styled">
                <option value="">-- Choose Advisor --</option>
                <?php foreach($advisors as $adv): ?>
                    <option value="<?php echo $adv['advisor_id']; ?>"><?php echo htmlspecialchars($adv['full_name'] . ' (' . $adv['department_name'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <div class="main-card">
        <div class="card-columns">
            <div class="col-left">
                <div class="section-header">
                    <div class="section-title">Select Date</div>
                </div>
                <!-- We just use a native date input for functionality instead of building a complex calendar logic -->
                <input type="date" id="appointment-date" class="select-styled" style="width:100%; max-width:none;" min="<?php echo date('Y-m-d'); ?>">
                
                <div class="section-header" style="margin-top:30px;">
                    <div class="section-title">Purpose</div>
                </div>
                <input type="text" id="appointment-purpose" class="purpose-input" placeholder="e.g., Pre-registration Advising">
                
                <label style="display:flex; align-items:center; gap:10px; font-size:14px;">
                    <input type="checkbox" id="appointment-urgent"> Mark as Urgent
                </label>
            </div>
            
            <div class="col-right">
                <div class="section-header">
                    <div class="section-title">Select Time</div>
                </div>
                <div class="time-grid" id="time-grid">
                    <!-- Simple hardcoded standard slots for demonstration of functionality -->
                    <div class="time-box" data-time="09:00:00">09:00 AM</div>
                    <div class="time-box" data-time="09:30:00">09:30 AM</div>
                    <div class="time-box" data-time="10:00:00">10:00 AM</div>
                    <div class="time-box" data-time="10:30:00">10:30 AM</div>
                    <div class="time-box" data-time="11:00:00">11:00 AM</div>
                    <div class="time-box" data-time="11:30:00">11:30 AM</div>
                    <div class="time-box" data-time="14:00:00">02:00 PM</div>
                    <div class="time-box" data-time="14:30:00">02:30 PM</div>
                </div>
                
                <div class="token-header" style="margin-top:30px;">
                    <div class="section-title">Confirmation</div>
                    <div class="token-avail" style="font-size:13px; color:#6B7280;">Token will be generated after booking.</div>
                </div>
            </div>
        </div>
        
        <div class="action-bar">
            <div class="btn-cancel" onclick="window.location.href='student-dashboard.php'">Cancel</div>
            <div class="btn-confirm" id="btn-confirm">Confirm Appointment</div>
        </div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    let selectedTime = null;

    const timeBoxes = document.querySelectorAll('.time-box');
    timeBoxes.forEach(box => {
        box.addEventListener('click', function() {
            timeBoxes.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            selectedTime = this.getAttribute('data-time');
        });
    });

    const confirmBtn = document.getElementById('btn-confirm');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            const advisorId = document.getElementById('advisor-select').value;
            const date = document.getElementById('appointment-date').value;
            const purpose = document.getElementById('appointment-purpose').value;
            const isUrgent = document.getElementById('appointment-urgent').checked;

            if (!advisorId || !date || !selectedTime) {
                alert('Please select an advisor, date, and time.');
                return;
            }

            confirmBtn.style.opacity = '0.5';
            confirmBtn.style.pointerEvents = 'none';
            confirmBtn.innerText = 'Booking...';

            fetch('../api/appointments/book.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    advisor_id: advisorId,
                    appointment_date: date,
                    appointment_time: selectedTime,
                    purpose: purpose,
                    is_urgent: isUrgent
                })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    alert('Appointment successfully booked! Your token number is: ' + data.token_number);
                    window.location.href = 'student-dashboard.php';
                } else {
                    alert('Error: ' + data.message);
                    confirmBtn.style.opacity = '1';
                    confirmBtn.style.pointerEvents = 'auto';
                    confirmBtn.innerText = 'Confirm Appointment';
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred');
                confirmBtn.style.opacity = '1';
                confirmBtn.style.pointerEvents = 'auto';
                confirmBtn.innerText = 'Confirm Appointment';
            });
        });
    }
});
</script>
</body>
</html>
