document.addEventListener('DOMContentLoaded', () => {
    
    // --- 1. Advisor List: Search Functionality ---
    const searchInputDiv = document.querySelector('.search-text');
    const searchContainer = document.querySelector('.search-container');
    
    if (searchContainer && searchInputDiv) {
        searchContainer.addEventListener('click', function() {
            if(searchInputDiv.tagName === 'DIV') {
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'search-text-input';
                input.placeholder = 'Search by Department or Name...';
                input.style.border = 'none';
                input.style.outline = 'none';
                input.style.background = 'transparent';
                input.style.color = '#333';
                input.style.width = '100%';
                input.style.fontSize = '14px';
                input.style.fontFamily = 'inherit';
                
                searchContainer.replaceChild(input, searchInputDiv);
                input.focus();
                
                input.addEventListener('keyup', function(e) {
                    const term = e.target.value.toLowerCase();
                    const cards = document.querySelectorAll('.bottom-section .card');
                    cards.forEach(card => {
                        const name = card.querySelector('.prof-name')?.textContent.toLowerCase() || '';
                        const title = card.querySelector('.prof-title')?.textContent.toLowerCase() || '';
                        if (name.includes(term) || title.includes(term)) {
                            card.style.display = 'flex';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            }
        });
    }

    // --- 2. Booking Page: Date and Time Selection ---
    const dateBoxes = document.querySelectorAll('.date-box:not(.faded)');
    dateBoxes.forEach(box => {
        box.addEventListener('click', function() {
            // Remove selected from all
            dateBoxes.forEach(b => b.classList.remove('selected'));
            // Add to clicked
            this.classList.add('selected');
        });
    });

    const timeBoxes = document.querySelectorAll('.time-box');
    timeBoxes.forEach(box => {
        box.addEventListener('click', function() {
            timeBoxes.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
        });
    });
    
    const confirmBtn = document.querySelector('.btn-confirm');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            alert('Appointment successfully booked!');
            window.location.href = 'student-dashboard.html';
        });
    }

    // For queue status page cancel button (btn-cancel) and booking page
    const cancelBtns = document.querySelectorAll('.btn-cancel');
    cancelBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            if(confirm('Are you sure you want to cancel/go back?')) {
                window.location.href = 'student-dashboard.html';
            }
        });
    });

    // --- 3. Queue Status Page Buttons ---
    const btnCancel2 = document.querySelector('.btn-cancel2'); // Upload Docs
    if (btnCancel2) {
        btnCancel2.addEventListener('click', () => {
            alert('Upload Documents feature coming soon.');
        });
    }
    
    // Quick Actions in Dashboard
    const quickUploadBtn = document.querySelector('.action-card.bg-grey');
    if (quickUploadBtn && quickUploadBtn.textContent.includes('Upload Documents')) {
        quickUploadBtn.addEventListener('click', () => {
            alert('Upload Documents feature coming soon.');
        });
    }
    
    const askAdvisorBtn = document.querySelector('.btn-ask');
    if (askAdvisorBtn) {
        askAdvisorBtn.addEventListener('click', () => {
            alert('Upload Documents dialog opened.');
        });
    }

    // Book Appointment in Dashboard Available Faculty
    const bookBtns = document.querySelectorAll('.col-faculty .btn-cyan');
    bookBtns.forEach(btn => {
        if(btn.textContent.trim() === 'Book an Appointment') {
            btn.addEventListener('click', () => {
                window.location.href = 'booking.html';
            });
        }
    });

    // Message buttons in Advisor List
    const msgBtns = document.querySelectorAll('.msg-btn');
    msgBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            alert('Messaging feature coming soon.');
        });
    });
    
    const availBtns = document.querySelectorAll('.avail-btn');
    availBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            window.location.href = 'booking.html';
        });
    });
});
