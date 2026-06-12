Advisor–Student Appointment 

👥 Team Distribution (3 Members)
🔵 Member 1: Student Experience Designer (Frontend Flow Owner)
👉 Focus: Everything a student sees & does
🎯 Responsibilities:
Design the full student journey
📱 Screens to Design:
Student Dashboard
Upcoming appointment
Token number (e.g., “You are #3 in queue”)
Wait time estimate
Advisor List Page
List of advisors
Show availability (Available / Busy / Offline)
Advisor Profile + Availability
Time slots (calendar style or list)
Select slot
Booking Flow
Select time
Confirm booking
Generate token
Upload Document Screen
Upload file UI
Show uploaded file preview
Queue Status Screen (IMPORTANT)
Current serving: “Student 1”
You are: “#4”
Estimated wait time
Missed Appointment Scenario
Show: “You missed your slot → moved to last”

🧠 Key UX Ideas:
Use progress indicators (Booked → Waiting → Serving)
Keep it minimal + mobile-friendly
Think like apps such as queue systems in banks/hospitals

🟢 Member 2: Advisor Dashboard Designer (Core Logic UI)
👉 Focus: Advisor control panel + real-time flow
🎯 Responsibilities:
Design how advisors manage students & appointments

💻 Screens to Design:
Advisor Dashboard
“Currently Serving: Student X”
Next in queue list
Token queue view
Appointment Requests Page
List of booking requests
Buttons: ✅ Approve / ❌ Decline
Daily Schedule View
Timeline (like Google Calendar)
All booked slots
Availability Settings
Add unavailable times
Example:
“Lunch break 1PM–2PM”
“Meeting blocked”
Live Advising Screen (IMPORTANT)
Show:
Current student
Uploaded document
Button: “Mark Complete”
On click → next student auto comes

🧠 Key UX Ideas:
Dashboard should feel like control center
Real-time feel (queue updates visually)
Clean table/list UI

🟡 Member 3: Admin + System Logic Designer (Structure + Edge Cases)
👉 Focus: Admin panel + system behavior

🎯 Responsibilities:
Design backend-facing UI + system rules visualization

🖥️ Screens to Design:
Admin Dashboard
Total advisors
Total appointments
Daily stats
Add Advisor Page
Form:
Name
Department
Availability
Appointment Logs
Table:
Student name
Advisor
Status (Completed / Missed / Cancelled)
System Behavior Screens (VERY IMPORTANT for marks)
🔁 Auto-Cancel Flow:
If student late > 5 mins:
Show popup/system message:
“Student moved to end of queue”
⏭️ Queue Update Logic:
If missed:
Next student becomes “Now Serving”
👉 You can represent this as:
Flow diagram (Figma arrows)
OR system state screens

🧠 Key UX Ideas:
Focus on logic visualization
Use flow diagrams + status labels
This part impresses teachers a LOT


Core Design System (Use This Exactly)
🎯 1. Color Palette (Minimal + Clean)
✅ Primary Color
Deep Blue → #2563EB
 👉 Used for: buttons, highlights, active states

✅ Secondary
Soft Blue → #DBEAFE
 👉 Background highlights, selected items

✅ Background
White → #FFFFFF

Light Gray → #F9FAFB

✅ Text Colors
Primary Text → #111827 (almost black)

Secondary Text → #6B7280 (gray)

✅ Status Colors (IMPORTANT for your system)
🟢 Success / Available → #16A34A

🟡 Pending / Waiting → #F59E0B

🔴 Missed / Cancelled → #DC2626


💡 Usage Rule (Very Important)
Don’t use more than 3 colors on one screen

Keep 70% white space


🔤 2. Typography (Clean & Modern)
Font Family:
👉 Inter (Best for UI, very clean)

Font Sizes:
Heading 1 → 24px (Dashboard titles)
Heading 2 → 18px (Section titles)
Body → 14px (normal text)
Small text → 12px (labels, hints)

Font Weights:
Bold (600) → headings
Medium (500) → buttons
Regular (400) → body

🧩 3. Components (Keep It Consistent)
🔘 Buttons
Border radius: 8px
Padding: 10px–14px
Primary:
Blue background
White text
Secondary:
White background
Gray border

📦 Cards / Containers
Background: white
Border: 1px solid #E5E7EB
Radius: 10–12px
Shadow: very light (or none)


📊 Status Badges (VERY IMPORTANT)
Use small rounded labels:
🟢 “Available”
🟡 “Waiting”
🔴 “Missed”
👉 Rounded pill style (border-radius: 20px)

All 3 members MUST:
Use same colors 🎨
Same font 🔤
Same spacing 📐



