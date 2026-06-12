-- =========================================================================
-- SYSTEM ARCHITECTURE REBOOT: ADVISOR-STUDENT APPOINTMENT SYSTEM
-- Optimized for Antigravity IDE and Real-time Backend Generation
-- =========================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `penalties`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `documents`;
DROP TABLE IF EXISTS `queue_tokens`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `availability_slots`;
DROP TABLE IF EXISTS `advisor_unavailable_dates`;
DROP TABLE IF EXISTS `advisor_profiles`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `departments`;
SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------------------
-- 1. DEPARTMENTS TABLE
-- -------------------------------------------------------------------------
CREATE TABLE `departments` (
  `department_id` INT(11) NOT NULL AUTO_INCREMENT,
  `department_name` VARCHAR(150) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `idx_unique_dept` (`department_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -------------------------------------------------------------------------
-- 2. USERS CORE TABLE (Authentication & Identity)
-- -------------------------------------------------------------------------
CREATE TABLE `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('student', 'advisor', 'admin') NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `idx_unique_email` (`email`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -------------------------------------------------------------------------
-- 3. STUDENTS EXTENDED PROFILE
-- -------------------------------------------------------------------------
CREATE TABLE `students` (
  `student_id` VARCHAR(20) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `department_id` INT(11) DEFAULT NULL,
  `status` ENUM('active', 'warned', 'blocked') DEFAULT 'active',
  `missed_count` INT(11) DEFAULT 0,
  `total_appointments` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `idx_student_user` (`user_id`),
  INDEX `idx_student_status` (`status`),
  CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -------------------------------------------------------------------------
-- 4. ADVISOR EXTENDED PROFILE
-- -------------------------------------------------------------------------
CREATE TABLE `advisor_profiles` (
  `advisor_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `department_id` INT(11) DEFAULT NULL,
  `title` VARCHAR(100) DEFAULT NULL,
  `room_number` VARCHAR(50) DEFAULT NULL,
  `office_hours` VARCHAR(100) DEFAULT NULL,
  `is_available` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`advisor_id`),
  UNIQUE KEY `idx_advisor_user` (`user_id`),
  INDEX `idx_advisor_status` (`is_available`),
  CONSTRAINT `fk_advisor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_advisor_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -------------------------------------------------------------------------
-- 5. ADVISOR LEAVE / BLACKOUT DATES
-- -------------------------------------------------------------------------
CREATE TABLE `advisor_unavailable_dates` (
  `unavailable_id` INT(11) NOT NULL AUTO_INCREMENT,
  `advisor_id` INT(11) NOT NULL,
  `unavailable_date` DATE NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`unavailable_id`),
  INDEX `idx_unavail_date` (`advisor_id`, `unavailable_date`),
  CONSTRAINT `fk_unavail_advisor` FOREIGN KEY (`advisor_id`) REFERENCES `advisor_profiles` (`advisor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -------------------------------------------------------------------------
-- 6. WEEKLY STRUCTURAL AVAILABILITY SLOTS
-- -------------------------------------------------------------------------
CREATE TABLE `availability_slots` (
  `slot_id` INT(11) NOT NULL AUTO_INCREMENT,
  `advisor_id` INT(11) NOT NULL,
  `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`slot_id`),
  INDEX `idx_weekly_slots` (`advisor_id`, `day_of_week`),
  CONSTRAINT `fk_slots_advisor` FOREIGN KEY (`advisor_id`) REFERENCES `advisor_profiles` (`advisor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -------------------------------------------------------------------------
-- 7. APPOINTMENTS TRANSACTIONS
-- -------------------------------------------------------------------------
CREATE TABLE `appointments` (
  `appointment_id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(20) NOT NULL,
  `advisor_id` INT(11) NOT NULL,
  `appointment_date` DATE NOT NULL,
  `appointment_time` TIME NOT NULL,
  `purpose` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('booked', 'waiting', 'serving', 'completed', 'missed', 'cancelled', 'auto_cancelled') DEFAULT 'booked',
  `is_urgent` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`appointment_id`),
  INDEX `idx_lookup_date` (`appointment_date`),
  INDEX `idx_lookup_matrix` (`advisor_id`, `appointment_date`, `status`),
  INDEX `idx_lookup_student` (`student_id`, `status`),
  CONSTRAINT `fk_appt_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appt_advisor` FOREIGN KEY (`advisor_id`) REFERENCES `advisor_profiles` (`advisor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -------------------------------------------------------------------------
-- 8. REAL-TIME QUEUE SYSTEM TOKENS
-- -------------------------------------------------------------------------
CREATE TABLE `queue_tokens` (
  `token_id` INT(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` INT(11) NOT NULL,
  `advisor_id` INT(11) NOT NULL,
  `token_number` INT(11) NOT NULL,
  `queue_date` DATE NOT NULL,
  `status` ENUM('available', 'booked', 'waiting', 'serving', 'completed', 'missed', 'cancelled') DEFAULT 'available',
  `estimated_wait_minutes` INT(11) DEFAULT NULL,
  `called_at` TIMESTAMP NULL DEFAULT NULL,
  `served_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `idx_unique_appt_token` (`appointment_id`),
  UNIQUE KEY `idx_daily_token_sequence` (`advisor_id`, `queue_date`, `token_number`),
  INDEX `idx_active_queue` (`advisor_id`, `queue_date`, `status`),
  CONSTRAINT `fk_token_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_token_advisor` FOREIGN KEY (`advisor_id`) REFERENCES `advisor_profiles` (`advisor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -------------------------------------------------------------------------
-- 9. TRANSACTIONAL USER DOCUMENTS
-- -------------------------------------------------------------------------
CREATE TABLE `documents` (
  `document_id` INT(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` INT(11) DEFAULT NULL,
  `student_id` VARCHAR(20) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_type` VARCHAR(50) DEFAULT NULL,
  `file_size_bytes` BIGINT(20) DEFAULT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_id`),
  INDEX `idx_doc_appt` (`appointment_id`),
  INDEX `idx_doc_student` (`student_id`),
  CONSTRAINT `fk_doc_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_doc_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -------------------------------------------------------------------------
-- 10. REAL-TIME SYSTEM CHAT MESSAGES
-- -------------------------------------------------------------------------
CREATE TABLE `messages` (
  `message_id` INT(11) NOT NULL AUTO_INCREMENT,
  `sender_id` INT(11) NOT NULL,
  `receiver_id` INT(11) NOT NULL,
  `message_text` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `sent_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`),
  INDEX `idx_chat_stream` (`sender_id`, `receiver_id`),
  INDEX `idx_unread_inbox` (`receiver_id`, `is_read`),
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -------------------------------------------------------------------------
-- 11. AUTOMATED COMPLIANCE & PENALTY RECODING
-- -------------------------------------------------------------------------
CREATE TABLE `penalties` (
  `penalty_id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(20) NOT NULL,
  `penalty_type` ENUM('warning', 'block', 'unblock') NOT NULL,
  `issued_by` INT(11) DEFAULT NULL,
  `reason` VARCHAR(500) DEFAULT NULL,
  `missed_count_at_time` INT(11) DEFAULT NULL,
  `risk_level` ENUM('low', 'medium', 'high') DEFAULT NULL,
  `issued_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`penalty_id`),
  INDEX `idx_compliance_history` (`student_id`),
  CONSTRAINT `fk_penalty_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_penalty_issuer` FOREIGN KEY (`issued_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =========================================================================
-- HIGH-FIDELITY SEED DATA (Perfectly Synchronized with Front-end Layouts)
-- =========================================================================

-- Seed Academic Departments
INSERT INTO `departments` (`department_id`, `department_name`) VALUES
(1, 'Computer Science & Engineering'),
(2, 'Electrical & Electronic Engineering'),
(3, 'Civil Engineering'),
(4, 'Mathematics'),
(5, 'Business'),
(6, 'Engineering');

-- Seed System Users
INSERT INTO `users` (`user_id`, `email`, `password_hash`, `role`, `full_name`) VALUES
(1, 'mamun@uiu.ac.bd', '$2y$10$dQVTvoJY8grKhrBExxAXY.4taTsFhSTq/WkCjAdn1PtvtKOGW5s2G', 'advisor', 'Dr. Khondaker A. Mamun'),
(2, 'swakkhar@uiu.ac.bd', '$2y$10$dQVTvoJY8grKhrBExxAXY.4taTsFhSTq/WkCjAdn1PtvtKOGW5s2G', 'advisor', 'Dr. Swakkhar Shatabda'),
(3, 'rezwan@uiu.ac.bd', '$2y$10$dQVTvoJY8grKhrBExxAXY.4taTsFhSTq/WkCjAdn1PtvtKOGW5s2G', 'advisor', 'Prof. Dr. M. Rezwan Khan'),
(4, 'suman@uiu.ac.bd', '$2y$10$dQVTvoJY8grKhrBExxAXY.4taTsFhSTq/WkCjAdn1PtvtKOGW5s2G', 'advisor', 'Suman Ahmmed'),
(5, 'anika@uiu.ac.bd', '$2y$10$dQVTvoJY8grKhrBExxAXY.4taTsFhSTq/WkCjAdn1PtvtKOGW5s2G', 'student', 'Anika Rahman'),
(6, 'tanvir@uiu.ac.bd', '$2y$10$dQVTvoJY8grKhrBExxAXY.4taTsFhSTq/WkCjAdn1PtvtKOGW5s2G', 'student', 'Tanvir Islam'),
(7, 'sajid@uiu.ac.bd', '$2y$10$dQVTvoJY8grKhrBExxAXY.4taTsFhSTq/WkCjAdn1PtvtKOGW5s2G', 'student', 'Sajid Hasan'),
(8, 'nusrat@uiu.ac.bd', '$2y$10$dQVTvoJY8grKhrBExxAXY.4taTsFhSTq/WkCjAdn1PtvtKOGW5s2G', 'student', 'Nusrat Jahan'),
(9, 'fahim@uiu.ac.bd', '$2y$10$dQVTvoJY8grKhrBExxAXY.4taTsFhSTq/WkCjAdn1PtvtKOGW5s2G', 'student', 'Fahim Faisal'),
(10, 'bad_stu@uiu.ac.bd', '$2y$10$dQVTvoJY8grKhrBExxAXY.4taTsFhSTq/WkCjAdn1PtvtKOGW5s2G', 'student', 'Zayan Malik');

-- Seed Student Records
INSERT INTO `students` (`student_id`, `user_id`, `department_id`, `status`, `missed_count`, `total_appointments`) VALUES
('0112000666', 10, 1, 'blocked', 3, 5),
('0112210001', 5, 1, 'active', 0, 3),
('0112210002', 6, 1, 'active', 0, 2),
('0112210999', 9, 1, 'active', 0, 0),
('0112220115', 7, 2, 'warned', 1, 4),
('0112230420', 8, 5, 'active', 0, 1);

-- Seed Faculty Advisor Roles
INSERT INTO `advisor_profiles` (`advisor_id`, `user_id`, `department_id`, `title`, `room_number`, `office_hours`, `is_available`) VALUES
(1, 1, 1, 'Professor', '412', '09:00 AM - 01:00 PM', 1),
(2, 2, 1, 'Professor & Head', '515', '02:00 PM - 05:00 PM', 1),
(3, 3, 2, 'Distinguished Professor', '302', '11:00 AM - 03:00 PM', 1),
(4, 4, 5, 'Assistant Professor', '618', '09:00 AM - 04:00 PM', 0); -- Suman Ahmmed is Busy/Offline

-- Seed Standard Working Office Hours Matrix
INSERT INTO `availability_slots` (`advisor_id`, `day_of_week`, `start_time`, `end_time`) VALUES
(1, 'Sunday', '10:00:00', '12:00:00'),
(1, 'Tuesday', '10:00:00', '12:00:00'),
(1, 'Wednesday', '13:00:00', '15:00:00'),
(2, 'Monday', '09:00:00', '11:00:00'),
(2, 'Wednesday', '09:00:00', '11:00:00'),
(3, 'Tuesday', '14:00:00', '16:00:00'),
(3, 'Thursday', '14:00:00', '16:00:00'),
(4, 'Sunday', '11:00:00', '13:00:00'),
(4, 'Monday', '11:00:00', '13:00:00');

-- Seed Structural Blackout/Leave Days
INSERT INTO `advisor_unavailable_dates` (`advisor_id`, `unavailable_date`, `reason`) VALUES
(1, '2026-06-15', 'Attending International Conference'),
(2, '2026-06-18', 'Departmental Academic Council Meeting'),
(3, '2026-06-22', 'Medical Leave');

-- Seed Live State System Appointments (Aligned with June 13 Student View)
INSERT INTO `appointments` (`appointment_id`, `student_id`, `advisor_id`, `appointment_date`, `appointment_time`, `purpose`, `status`) VALUES
(1, '0112210999', 2, '2026-06-13', '10:00:00', 'Pre-registration Advising', 'booked'),
(2, '0112000666', 2, '2026-06-13', '10:10:00', 'Probation Guideline Meet', 'booked');

-- Seed Real-time Queue Tracking Tokens
INSERT INTO `queue_tokens` (`token_id`, `appointment_id`, `advisor_id`, `token_number`, `queue_date`, `status`, `estimated_wait_minutes`) VALUES
(1, 1, 2, 1, '2026-06-13', 'booked', 0),  -- Token #1: Active on Dashboard
(2, 2, 2, 2, '2026-06-13', 'booked', 10); -- Token #2: Directly Behind

-- Commit Transactions cleanly
COMMIT;