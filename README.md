আমাদের project টা modular ভাবে ভাগ করা হয়েছে যাতে ৩ জন একসাথে কাজ করতে পারি, conflict কম হয়, এবং পরে backend add করা সহজ হয়।

সবাই নিজের folder এ কাজ করবে।
কেউ অন্যের folder modify করবে না (unless needed)।

🌐 Root Structure
advisor-student-appointment/

এটাই main project folder।


📂 assets/

এখানে থাকবে:

icons
images
logos  


👨‍🎓 Member 1 → Student Module
student-module/

Student side এর সব page এখানে থাকবে।

📄 HTML Pages
student-dashboard.html

Student এর main dashboard।

দেখাবে:

upcoming appointment
token number
queue status
advisor-list.html

Advisor list page।

দেখাবে:

advisor নাম
availability
booking.html

Appointment booking page।

queue-status.html

Live queue tracking।

দেখাবে:

currently serving
student position
estimated wait time
upload-document.html

Document upload UI।

📂 css/student.css

শুধু student pages এর styling এখানে।

📂 js/student.js

Student module এর JS logic এখানে।

যেমন:

booking interaction
queue update simulation
👨‍🏫 Member 2 → Advisor Module
advisor-module/

Advisor side এর সব pages এখানে থাকবে।

📄 HTML Pages
advisor-dashboard.html

Advisor main dashboard।

booking-requests.html

Approve / decline requests।

daily-schedule.html

আজকের appointment schedule।

availability-settings.html

Unavailable time add করা।

live-session.html

Current advising session screen।

দেখাবে:

current student
uploaded document
next student
📂 css/advisor.css

Advisor pages এর styling।

📂 js/advisor.js

Advisor side interactions।

👨‍💼 Member 3 → Admin Module
admin-module/

Admin side pages এখানে থাকবে।

📄 HTML Pages
admin-dashboard.html

Admin overview।

add-advisor.html

New advisor add form।

appointment-logs.html

Appointment history table।

queue-management.html

Queue logic visualization।

দেখাবে:

missed appointment
auto shift
next student serving
📂 css/admin.css

Admin styling।

📂 js/admin.js

Admin interactions ও logic simulation।