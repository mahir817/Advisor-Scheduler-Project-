document.addEventListener('DOMContentLoaded', () => {
    // Determine which page we are on based on URL or elements present
    const path = window.location.pathname;

    // ==============================================
    // 1. Dashboard Overview Logic
    // ==============================================
    const liveTimeElement = document.getElementById('live-time');
    const currentDateElement = document.getElementById('current-date');
    
    if (liveTimeElement) {
        function updateDateTime() {
            const now = new Date();
            liveTimeElement.textContent = now.toLocaleTimeString();
            
            if (currentDateElement) {
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                currentDateElement.textContent = now.toLocaleDateString('en-US', options);
            }
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Status Toggle Logic
        const statusBtn = document.getElementById('status-btn');
        if (statusBtn) {
            const statuses = ['Active', 'Busy', 'Away'];
            const colors = ['#e6f4ea', '#fce8e6', '#fef7e0'];
            const textColors = ['#1e8e3e', '#d93025', '#f9ab00'];
            let currentStatusIdx = 0;
            
            statusBtn.addEventListener('click', () => {
                currentStatusIdx = (currentStatusIdx + 1) % statuses.length;
                statusBtn.textContent = statuses[currentStatusIdx];
                statusBtn.style.backgroundColor = colors[currentStatusIdx];
                statusBtn.style.color = textColors[currentStatusIdx];
            });
        }

        // Dynamic Cards and Active Session Logic (Simulation)
        const cards = document.querySelectorAll('.card h2');
        const pendingCard = cards.length > 0 ? cards[0] : null;
        const todayApptsCard = cards.length > 1 ? cards[1] : null;
        const avgSessionCard = cards.length > 2 ? cards[2] : null;
        const noShowsCard = cards.length > 3 ? cards[3] : null;

        const sessionHeaderSpan = document.querySelector('.session-header span');
        const sessionContentP = document.querySelector('.session-content p');

        if (cards.length === 4) {
            // Update cards randomly
            setInterval(() => {
                if (Math.random() > 0.7) {
                    let pendingVal = parseInt(pendingCard.textContent);
                    pendingCard.textContent = pendingVal > 0 && Math.random() > 0.5 ? pendingVal - 1 : pendingVal + 1;
                }
                if (Math.random() > 0.8) {
                    let apptsVal = parseInt(todayApptsCard.textContent);
                    todayApptsCard.textContent = apptsVal + 1;
                }
                if (Math.random() > 0.7) {
                    let avgVal = parseInt(avgSessionCard.textContent);
                    avgSessionCard.innerHTML = (avgVal + (Math.random() > 0.5 ? 1 : -1)) + ' <span>min</span>';
                }
                if (Math.random() > 0.9) {
                    let noShowsVal = parseInt(noShowsCard.textContent);
                    noShowsCard.textContent = noShowsVal + 1;
                }
            }, 5000);

            // Toggle Active Session mock
            let isSessionActive = false;
            setInterval(() => {
                isSessionActive = !isSessionActive;
                if (isSessionActive) {
                    if (sessionHeaderSpan) sessionHeaderSpan.textContent = "In Progress";
                    if (sessionHeaderSpan) sessionHeaderSpan.style.color = "#1e8e3e"; // Green
                    if (sessionContentP) sessionContentP.innerHTML = "<strong>Meeting with Puja Biswas</strong><br>Token #03 - Course Advice";
                } else {
                    if (sessionHeaderSpan) sessionHeaderSpan.textContent = "Not started";
                    if (sessionHeaderSpan) sessionHeaderSpan.style.color = "#888";
                    if (sessionContentP) sessionContentP.innerHTML = "No active appointment right now.";
                }
            }, 10000); // Toggle every 10 seconds
        }
    }

    // ==============================================
    // 2. Appointment Management Logic
    // ==============================================
    const tabBtns = document.querySelectorAll('.tab-btn');
    if (tabBtns.length > 0) {
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                tabBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                document.querySelectorAll('.booking-list').forEach(list => {
                    list.style.display = 'none';
                });
                
                const targetId = btn.getAttribute('data-target');
                document.getElementById(targetId).style.display = 'block';
            });
        });
    }

    const bookingSection = document.querySelector('.booking-section');
    if (bookingSection) {
        bookingSection.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-approve')) {
                const row = e.target.closest('.request-row');
                const studentName = row.querySelector('.student-info strong').textContent;
                row.remove();
                alert(`Approved appointment request for ${studentName}.`);
            } else if (e.target.classList.contains('btn-decline')) {
                const row = e.target.closest('.request-row');
                const studentName = row.querySelector('.student-info strong').textContent;
                row.remove();
                alert(`Removed appointment request for ${studentName}.`);
            } else if (e.target.classList.contains('btn-desc')) {
                const row = e.target.closest('.request-row');
                const studentName = row.querySelector('.student-info strong').textContent;
                const pText = row.querySelector('.student-info p').textContent;
                const purpose = pText.split('•')[0].trim();
                
                const modal = document.getElementById('desc-modal');
                const descText = document.getElementById('desc-text');
                if (modal && descText) {
                    descText.innerHTML = `<strong>Student:</strong> ${studentName}<br><strong>Purpose:</strong> ${purpose}<br><br>The student has requested this appointment for ${purpose}. Please review their academic history before the session.`;
                    modal.style.display = 'block';
                }
            }
        });

        // Modal close logic
        const descModal = document.getElementById('desc-modal');
        if (descModal) {
            const closeBtn = document.getElementById('close-modal');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    descModal.style.display = 'none';
                });
            }
            window.addEventListener('click', (e) => {
                if (e.target === descModal) {
                    descModal.style.display = 'none';
                }
            });
        }
        const callNextBtn = document.querySelector('.btn-next');
        if (callNextBtn) {
            callNextBtn.addEventListener('click', () => {
                const currentTokenEl = document.querySelector('.current-token h1');
                const nextTokenEl = document.querySelector('.next-token h1');
                
                if (currentTokenEl && nextTokenEl) {
                    // Move Next -> Current
                    const nextTokenVal = nextTokenEl.textContent;
                    currentTokenEl.textContent = nextTokenVal;
                    document.querySelector('.current-token p:nth-of-type(2)').textContent = document.querySelector('.next-token p:nth-of-type(2)').textContent;
                    
                    // Fetch next from booking list if available
                    const firstRequest = document.querySelector('.booking-list .request-row');
                    if (firstRequest && nextTokenVal !== "--") {
                        const newTokenId = firstRequest.querySelector('.token-id').textContent;
                        const newStudentName = firstRequest.querySelector('.student-info strong').textContent;
                        
                        // "Course Add • 12:10PM" -> extract "12:10PM"
                        const pText = firstRequest.querySelector('.student-info p').textContent;
                        const timeParts = pText.split('•');
                        const newTime = timeParts.length > 1 ? timeParts[1].trim() : "TBD";
                        
                        nextTokenEl.textContent = newTokenId;
                        document.querySelector('.next-token p:nth-of-type(2)').textContent = newStudentName;
                        const nextTokenSpan = document.querySelector('.next-token span');
                        if(nextTokenSpan) nextTokenSpan.textContent = "Scheduled — " + newTime;
                        
                        firstRequest.remove();
                    } else {
                        // If no more requests, indicate queue is empty
                        nextTokenEl.textContent = "--";
                        document.querySelector('.next-token p:nth-of-type(2)').textContent = "Queue Empty";
                        const nextTokenSpan = document.querySelector('.next-token span');
                        if(nextTokenSpan) nextTokenSpan.textContent = "";
                    }
                }
            });
        }
    }

    // ==============================================
    // 3. Time Management Logic
    // ==============================================
    
    // Tab switching for Time Management
    const timeTabs = document.querySelectorAll('.tabs .tab');
    if (timeTabs.length > 0) {
        timeTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetId = tab.getAttribute('data-target');
                if (targetId) {
                    timeTabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    document.querySelectorAll('.view-section').forEach(view => {
                        view.style.display = 'none';
                    });
                    document.getElementById(targetId).style.display = 'block';
                }
            });
        });
    }

    // Add Custom Unavailable Time
    const addCustomBtn = document.getElementById('add-custom-btn');
    if (addCustomBtn) {
        addCustomBtn.addEventListener('click', () => {
            const dateVal = document.getElementById('custom-date').value;
            const startVal = document.getElementById('custom-time-start').value;
            const endVal = document.getElementById('custom-time-end').value;
            const reasonVal = document.getElementById('custom-reason').value;

            if (!dateVal || !startVal || !endVal || !reasonVal) {
                alert('Please fill out all fields.');
                return;
            }

            const dateObj = new Date(dateVal);
            const dateStr = dateObj.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

            const newSlot = document.createElement('div');
            newSlot.className = 'slot';
            newSlot.style.justifyContent = 'space-between';
            newSlot.style.borderLeft = 'none';
            newSlot.style.paddingLeft = '15px';
            newSlot.innerHTML = `
                <div><strong>${dateStr}</strong> <br> ${startVal} - ${endVal} - ${reasonVal}</div>
                <button class="delete-btn">×</button>
            `;
            
            document.querySelector('.custom-list').appendChild(newSlot);

            document.getElementById('custom-date').value = '';
            document.getElementById('custom-time-start').value = '';
            document.getElementById('custom-time-end').value = '';
            document.getElementById('custom-reason').value = '';
        });
    }

    const dayCards = document.querySelectorAll('.day-card');
    if (dayCards.length > 0) {
        // Toggle availability switch
        document.body.addEventListener('click', (e) => {
            if (e.target.classList.contains('toggle')) {
                e.target.classList.toggle('off');
                const statusArea = e.target.closest('.status-area');
                const dayCard = e.target.closest('.day-card');
                const slotCountText = dayCard.querySelector('.slot-count');
                
                if (e.target.classList.contains('off')) {
                    statusArea.classList.add('gray');
                    statusArea.childNodes[0].nodeValue = "Unavailable ";
                    if (slotCountText) {
                        slotCountText.classList.add('gray');
                        slotCountText.textContent = "Not available";
                    }
                } else {
                    statusArea.classList.remove('gray');
                    statusArea.childNodes[0].nodeValue = "Available ";
                    if (slotCountText) {
                        slotCountText.classList.remove('gray');
                        const slots = dayCard.querySelectorAll('.slot').length;
                        slotCountText.textContent = `${slots} slot${slots !== 1 ? 's' : ''} configured`;
                    }
                }
            }

            // Delete time slot
            if (e.target.classList.contains('delete-btn')) {
                const slot = e.target.closest('.slot');
                const dayCard = e.target.closest('.day-card');
                slot.remove();
                
                // Update slot count
                const slotCountText = dayCard.querySelector('.slot-count');
                if (slotCountText) {
                    const slots = dayCard.querySelectorAll('.slot').length;
                    if (!slotCountText.classList.contains('gray')) {
                        slotCountText.textContent = `${slots} slot${slots !== 1 ? 's' : ''} configured`;
                    }
                }
            }

            // Add time slot
            if (e.target.classList.contains('add-btn')) {
                const slotsContainer = e.target.closest('.slots');
                const newSlot = document.createElement('div');
                newSlot.className = 'slot';
                newSlot.innerHTML = `
                  <div class="slot-left">
                    <div class="dot"></div>
                    New Slot (e.g. 10:00 – 11:00)
                    <span class="badge">Available</span>
                  </div>
                  <button class="delete-btn">×</button>
                `;
                // Insert before the add button
                slotsContainer.insertBefore(newSlot, e.target);
                
                const dayCard = e.target.closest('.day-card');
                const slotCountText = dayCard.querySelector('.slot-count');
                const slots = dayCard.querySelectorAll('.slot').length;
                if (!slotCountText.classList.contains('gray')) {
                    slotCountText.textContent = `${slots} slot${slots !== 1 ? 's' : ''} configured`;
                }
            }

            // Save button
            if (e.target.classList.contains('save-btn')) {
                alert('Weekly schedule saved successfully to your advisor profile!');
            }
        });
    }

    // ==============================================
    // 4. Statistics Logic
    // ==============================================
    const contactBtns = document.querySelectorAll('.contact-btn');
    if (contactBtns.length > 0) {
        contactBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const row = e.target.closest('tr');
                const studentName = row.querySelector('td').textContent;
                alert(`Mock: Opening message interface to contact ${studentName}`);
            });
        });
    }

    // Dynamic stats logic
    const statsCards = document.querySelectorAll('.stats-grid .card h2');
    if (statsCards.length === 4) {
        setInterval(() => {
            if (Math.random() > 0.5) {
                let total = parseInt(statsCards[0].textContent);
                statsCards[0].textContent = total + 1;
                
                if (Math.random() > 0.8) {
                    let missed = parseInt(statsCards[2].textContent);
                    statsCards[2].textContent = missed + 1;
                } else {
                    let completed = parseInt(statsCards[1].textContent);
                    statsCards[1].textContent = completed + 1;
                }
                
                if (Math.random() > 0.7) {
                    let unique = parseInt(statsCards[3].textContent);
                    statsCards[3].textContent = unique + 1;
                }
            }
        }, 8000);
    }

    // Add High Risk Student
    const addHighRiskBtn = document.getElementById('add-high-risk-btn');
    if (addHighRiskBtn) {
        addHighRiskBtn.addEventListener('click', () => {
            const studentName = prompt('Enter Student Name for High Risk Alert:');
            if (studentName) {
                const tbody = document.querySelector('.table-card table tbody');
                if (tbody) {
                    const tr = document.createElement('tr');
                    const randId = Math.floor(1000 + Math.random() * 9000);
                    tr.innerHTML = `
                        <td>${studentName}</td>
                        <td>STU-${randId}</td>
                        <td>4 times</td>
                        <td>General</td>
                        <td>2026-06-10</td>
                        <td><span class="risk high">High Risk</span></td>
                        <td><button class="contact-btn">Contact</button></td>
                    `;
                    tbody.appendChild(tr);
                    
                    const newBtn = tr.querySelector('.contact-btn');
                    newBtn.addEventListener('click', () => {
                        alert(`Mock: Opening message interface to contact ${studentName}`);
                    });
                }
            }
        });
    }
});
