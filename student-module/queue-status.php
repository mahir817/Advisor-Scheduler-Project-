<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

require '../backend/db_connect.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$student = $res->fetch_assoc();
$student_id = $student['student_id'] ?? null;

$appt = null;
if ($student_id) {
    $appt_query = "SELECT a.status, a.appointment_date, a.appointment_time, a.advisor_id,
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live Queue Status</title>
<link rel="stylesheet" href="css/queue.css">
</head>
<body>
    <div class="navbar">
        <div class="nav-left">
            <a href="student-dashboard.php">
                <div>&#8592;</div>
            </a>
        </div>
        <div class="nav-center">Live Queue Status</div>
    </div>

    <div class="main-container">
        <?php if ($appt): ?>
        <div class="col-left">
            <div class="card">
                <div class="section-header">Queue Progress</div>
                <div class="divider"></div>
                
                <div class="status-boxes">
                    <div class="status-box status-box-serving">
                        <div class="status-box-title">Now Serving</div>
                        <div class="status-box-number-serving" id="qs-serving-token">#--</div>
                    </div>
                    <div class="status-box status-box-token">
                        <div class="status-box-title">Your Token</div>
                        <div class="status-box-number-token" id="qs-my-token">#<?php echo $appt['token_number'] ?? '--'; ?></div>
                    </div>
                </div>
                
                <div class="wait-time" id="qs-wait-time">Estimated Wait Time: <?php echo $appt['estimated_wait_minutes'] ?? 0; ?> minutes</div>
                
                <div class="timeline-container">
                    <div class="timeline-track"></div>
                    <div class="timeline-nodes">
                        <div class="timeline-node">
                            <div class="node-circle-grey" id="node-booked"></div>
                            <div class="node-label">Booked</div>
                        </div>
                        <div class="timeline-node">
                            <div class="node-circle-grey" id="node-waiting"></div>
                            <div class="node-label">Waiting</div>
                        </div>
                        <div class="timeline-node">
                            <div class="node-circle-grey" id="node-serving"></div>
                            <div class="node-label">Serving</div>
                        </div>
                    </div>
                </div>
                
                <div class="queue-list" id="queue-list">
                    <!-- dynamically injected via SSE -->
                </div>
            </div>
        </div>
        
        <div class="col-right">
            <div class="card">
                <div class="section-header">Advisor Details</div>
                <div class="divider"></div>
                
                <div class="profile-section">
                    <div class="profile-icon-bg">
                        <div class="profile-icon">&#128100;</div>
                    </div>
                    <div class="profile-name"><?php echo htmlspecialchars($appt['advisor_name']); ?></div>
                    <div class="profile-dept"><?php echo htmlspecialchars($appt['department_name'] ?? ''); ?></div>
                </div>
                
                <div class="info-section">
                    <div class="info-row">
                        <div class="info-icon">&#128197;</div>
                        <div><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon">&#128336;</div>
                        <div><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon">&#127968;</div>
                        <div>Room Number <?php echo htmlspecialchars($appt['room_number'] ?? ''); ?></div>
                    </div>
                </div>
                
                <div class="btn-cancel" onclick="window.location.href='booking.php'">Take Next Appointment</div>
                <div class="btn-cancel2">Upload Documents</div>
                <a href="booking.php" style="text-decoration: none;"><div class="btn-cancel3">View Schedule</div></a>
            </div>
        </div>
        <?php else: ?>
            <div class="col-left" style="width:100%; text-align:center;">
                <div class="card" style="padding: 50px;">
                    <h2>No Active Appointment</h2>
                    <p style="margin-top:20px;">You don't have an active queue to monitor.</p>
                    <a href="booking.php" class="btn btn-blue" style="margin-top:20px;display:inline-block;padding:10px 20px;text-decoration:none;border-radius:8px;">Book an Appointment</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
<script>
    <?php if ($appt && isset($appt['advisor_id'])): ?>
    const advisorId = <?php echo $appt['advisor_id']; ?>;
    const eventSource = new EventSource('../api/queue/stream.php?advisor_id=' + advisorId);
    
    eventSource.addEventListener('QUEUE_STATE_CHANGED', function(e) {
        const data = JSON.parse(e.data);
        console.log('Real-time Queue Update:', data);
        
        // Fetch specific queue status to update UI
        fetchQueueStatus();
    });

    function fetchQueueStatus() {
        fetch('../backend/get_queue_status.php')
            .then(res => res.json())
            .then(data => {
                if (data.has_appointment) {
                    const runEl = document.getElementById('qs-serving-token');
                    if (runEl) runEl.innerText = '#' + (data.running_token || '--');

                    const waitEl = document.getElementById('qs-wait-time');
                    if (waitEl) waitEl.innerText = 'Estimated Wait Time: ' + data.estimated_wait_minutes + ' minutes';

                    const rawStatus = data.raw_status;
                    const bookedNode = document.getElementById('node-booked');
                    const waitingNode = document.getElementById('node-waiting');
                    const servingNode = document.getElementById('node-serving');

                    if (bookedNode && waitingNode && servingNode) {
                        bookedNode.style.backgroundColor = ['booked', 'waiting', 'serving'].includes(rawStatus) ? '#16a34a' : '#ddd';
                        waitingNode.style.backgroundColor = ['waiting', 'serving'].includes(rawStatus) ? '#16a34a' : '#ddd';
                        servingNode.style.backgroundColor = ['serving'].includes(rawStatus) ? '#16a34a' : '#ddd';
                    }

                    // For the dynamic list below, we just fetch from live endpoint:
                    fetch(`../api/advisor/queue/live.php?advisor_id=${advisorId}`)
                    .then(res => res.json())
                    .then(livedata => {
                        if(livedata.success) {
                            const list = document.getElementById('queue-list');
                            list.innerHTML = '';
                            
                            if (livedata.data.currently_serving) {
                                list.innerHTML += `
                                    <div class="queue-row">
                                        <div class="queue-row-left">Token #${livedata.data.currently_serving.token_number}</div>
                                        <div class="queue-row-right green">In Session</div>
                                    </div>
                                `;
                            }
                            
                            if (livedata.data.next_in_queue && livedata.data.next_in_queue.length > 0) {
                                let i = 0;
                                for(let q of livedata.data.next_in_queue) {
                                    if(i >= 3) break; // show next 3
                                    let cls = (i === 0) ? 'orange' : 'grey';
                                    let txt = (i === 0) ? 'Next' : 'Waiting';
                                    list.innerHTML += `
                                        <div class="queue-row">
                                            <div class="queue-row-left">Token #${q.token_number}</div>
                                            <div class="queue-row-right ${cls}">${txt}</div>
                                        </div>
                                    `;
                                    i++;
                                }
                            }
                        }
                    });
                }
            })
            .catch(err => console.error('Error fetching queue status', err));
    }

    // Fetch immediately on load
    fetchQueueStatus();
    <?php endif; ?>
</script>
</body>
</html>
