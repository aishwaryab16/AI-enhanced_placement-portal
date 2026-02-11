-- Complete Database Structure with Clean Users
-- All tables created empty except for dummy users

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Create all tables
CREATE TABLE IF NOT EXISTS `admin_golden_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `chapter_id` int(11) DEFAULT NULL,
  `module_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bot_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message` text NOT NULL,
  `response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `chapters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `website` varchar(300) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `hr_contact` varchar(200) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `company_name` varchar(255) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `logo_url` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `companies_view` (
  `id` int(11) DEFAULT NULL,
  `company_name` varchar(200) DEFAULT NULL,
  `logo_url` binary(0) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `website` varchar(300) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `contact_person` varchar(200) DEFAULT NULL,
  `contact_email` binary(0) DEFAULT NULL,
  `contact_phone` binary(0) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `company_intelligence` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(150) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `company_size` enum('startup','small','medium','large','enterprise') DEFAULT NULL,
  `preferred_skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferred_skills`)),
  `tech_stack` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tech_stack`)),
  `hiring_criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`hiring_criteria`)),
  `culture_keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`culture_keywords`)),
  `avg_package_range` varchar(50) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `company_resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `website` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tech_stack` varchar(500) DEFAULT NULL,
  `interview_focus` varchar(500) DEFAULT NULL,
  `job_roles` varchar(500) DEFAULT NULL,
  `hiring_for` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_title` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `event_type` enum('interview','deadline','workshop','placement','other') DEFAULT 'other',
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `is_personal` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `faqs_assigned` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faq_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `chapter_id` int(11) DEFAULT NULL,
  `module_id` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `final_mock_interview_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `interview_id` int(11) DEFAULT NULL,
  `company` varchar(255) NOT NULL,
  `job_role` varchar(255) NOT NULL,
  `score` int(11) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `strengths` text DEFAULT NULL,
  `weaknesses` text DEFAULT NULL,
  `conversation` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_student_interview` (`student_id`,`interview_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_interview` (`interview_id`),
  KEY `idx_company_role` (`company`,`job_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `generated_resumes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `resume_json` mediumtext NOT NULL,
  `generated_resume` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `internship_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `internship_title` varchar(255) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `internship_role` varchar(100) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `stipend_range` varchar(100) DEFAULT NULL,
  `min_cgpa` decimal(3,2) DEFAULT NULL,
  `required_skills` text DEFAULT NULL,
  `match_percentage` int(11) DEFAULT 0,
  `application_status` enum('Applied','Shortlisted','Interviewed','Selected','Rejected') DEFAULT 'Applied',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` text DEFAULT NULL,
  `resume_path` varchar(500) DEFAULT NULL,
  `resume_json` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `internship_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `interview_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `company` varchar(255) NOT NULL,
  `internship_role` varchar(255) NOT NULL,
  `started_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `total_rounds` int(11) DEFAULT 1,
  `completed_rounds` int(11) DEFAULT 0,
  `round_results` text DEFAULT NULL,
  `status` enum('started','in_progress','completed','abandoned') DEFAULT 'started',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `internship_interviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_application_id` int(11) DEFAULT NULL,
  `student_id` int(11) NOT NULL,
  `company` varchar(255) NOT NULL,
  `internship_role` varchar(255) NOT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `panel_details` text DEFAULT NULL,
  `interview_rounds` text DEFAULT NULL,
  `current_round_index` int(11) DEFAULT 0,
  `round_results` text DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `overall_score` int(11) DEFAULT NULL,
  `overall_feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `internship_opportunities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company` varchar(100) NOT NULL,
  `role` varchar(100) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `stipend_min` decimal(10,2) DEFAULT NULL,
  `stipend_max` decimal(10,2) DEFAULT NULL,
  `skills_required` text DEFAULT NULL,
  `min_cgpa` decimal(3,2) DEFAULT NULL,
  `eligible_years` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `apply_link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `interviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_application_id` int(11) DEFAULT NULL,
  `student_id` int(11) NOT NULL,
  `company` varchar(255) NOT NULL,
  `job_role` varchar(255) NOT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `panel_details` text DEFAULT NULL,
  `interview_rounds` text DEFAULT NULL,
  `current_round_index` int(11) DEFAULT 0,
  `round_results` text DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `overall_score` int(11) DEFAULT NULL,
  `overall_feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_interviews_student` (`student_id`),
  KEY `fk_interviews_job_application` (`job_application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `interview_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `interview_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `company` varchar(255) NOT NULL,
  `job_role` varchar(255) NOT NULL,
  `started_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `total_rounds` int(11) DEFAULT 1,
  `completed_rounds` int(11) DEFAULT 0,
  `round_results` text DEFAULT NULL,
  `status` enum('started','in_progress','completed','abandoned') DEFAULT 'started',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `interview_domains` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain_name` varchar(100) NOT NULL,
  `domain_description` text DEFAULT NULL,
  `prompt_template` text NOT NULL,
  `difficulty_level` enum('beginner','intermediate','advanced','expert') DEFAULT 'intermediate',
  `is_active` tinyint(1) DEFAULT 1,
  `icon` varchar(10) DEFAULT '?',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `resume_path` varchar(500) DEFAULT NULL,
  `job_title` varchar(255) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `job_role` varchar(100) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `salary_range` varchar(100) DEFAULT NULL,
  `min_cgpa` decimal(3,2) DEFAULT NULL,
  `required_skills` text DEFAULT NULL,
  `match_percentage` int(11) DEFAULT 0,
  `application_status` enum('Applied','Shortlisted','Interviewed','Selected','Rejected') DEFAULT 'Applied',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` text DEFAULT NULL,
  `resume_json` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `job_opportunities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_title` varchar(255) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `company_name` varchar(255) NOT NULL,
  `role` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `ctc_max` decimal(10,2) DEFAULT NULL,
  `skills_required` text DEFAULT NULL,
  `min_cgpa` decimal(3,2) DEFAULT NULL,
  `eligible_years` varchar(50) DEFAULT NULL,
  `job_description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `job_type` varchar(50) DEFAULT 'Full-time',
  `salary` varchar(100) DEFAULT NULL,
  `application_deadline` date DEFAULT NULL,
  `posted_by` int(11) NOT NULL,
  `posted_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `deadline` date DEFAULT NULL,
  `apply_link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `min_year` int(11) DEFAULT 1,
  `applications_count` int(11) DEFAULT 0,
  `type` varchar(20) DEFAULT 'job',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chapter_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `youtube_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `placement_broadcasts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `audience` varchar(100) DEFAULT 'All Students',
  `message` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `placement_drives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `job_title` varchar(255) NOT NULL,
  `job_description` text DEFAULT NULL,
  `job_type` enum('Full-time','Internship','Part-time','Contract') DEFAULT 'Full-time',
  `location` varchar(255) DEFAULT NULL,
  `salary_package` varchar(100) DEFAULT NULL,
  `eligibility_criteria` text DEFAULT NULL,
  `min_cgpa` decimal(3,2) DEFAULT 0.00,
  `allowed_branches` text DEFAULT NULL,
  `allowed_backlogs` int(11) DEFAULT 0,
  `application_deadline` date DEFAULT NULL,
  `drive_date` date DEFAULT NULL,
  `drive_time` time DEFAULT NULL,
  `drive_mode` enum('Online','Offline','Hybrid') DEFAULT 'Offline',
  `total_positions` int(11) DEFAULT 1,
  `status` enum('draft','active','closed','completed') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `placement_drives_view` (
  `id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `event_name` varchar(255) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `job_description` text DEFAULT NULL,
  `job_type` enum('Full-time','Internship','Part-time','Contract') DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `salary_package` varchar(100) DEFAULT NULL,
  `eligibility_criteria` text DEFAULT NULL,
  `min_cgpa` decimal(3,2) DEFAULT NULL,
  `allowed_branches` text DEFAULT NULL,
  `allowed_backlogs` int(11) DEFAULT NULL,
  `application_deadline` date DEFAULT NULL,
  `drive_date` date DEFAULT NULL,
  `drive_time` time DEFAULT NULL,
  `drive_mode` enum('Online','Offline','Hybrid') DEFAULT NULL,
  `total_positions` int(11) DEFAULT NULL,
  `status` enum('draft','active','closed','completed') DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `question_bank_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `language_name` varchar(255) NOT NULL,
  `questions_json` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `resume_academic_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `roll_number` varchar(50) DEFAULT NULL,
  `branch` varchar(100) DEFAULT NULL,
  `cgpa` decimal(3,2) DEFAULT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `backlogs` int(11) DEFAULT 0,
  `attendance_percentage` decimal(5,2) DEFAULT 0.00,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `saved_internships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) DEFAULT NULL,
  `internship_id` int(11) DEFAULT NULL,
  `saved_on` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `saved_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `job_id` int(11) DEFAULT NULL,
  `saved_on` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `scheduled_interviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_application_id` int(11) DEFAULT NULL,
  `student_id` int(11) NOT NULL,
  `company` varchar(255) NOT NULL,
  `job_role` varchar(255) NOT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `panel_details` text DEFAULT NULL,
  `interview_rounds` text DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_job_app` (`job_application_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_achievements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `achievement_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `student_additional_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `github` varchar(300) DEFAULT NULL,
  `linkedin` varchar(300) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `github_link` varchar(255) DEFAULT NULL,
  `linkedin_link` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student` (`student_id`),
  UNIQUE KEY `uniq_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `student_experience` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `employment_type` enum('internship','full_time','part_time','contract','freelance') DEFAULT 'internship',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `location` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `achievements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`achievements`)),
  `skills_used` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`skills_used`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `student_interests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `interest_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `student_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `project_title` varchar(255) NOT NULL,
  `role` varchar(255) DEFAULT NULL,
  `project_url` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `skills_used` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_ongoing` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `student_skills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `skill_name` varchar(100) NOT NULL,
  `proficiency_level` enum('beginner','intermediate','advanced','expert') DEFAULT 'intermediate',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_username` (`username`),
  KEY `idx_skill` (`skill_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','student','placement_officer','internship_officer') NOT NULL DEFAULT 'student',
  `full_name` varchar(150) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch` varchar(100) DEFAULT NULL,
  `semester` int(11) DEFAULT NULL,
  `sgpa` decimal(3,2) DEFAULT NULL,
  `backlogs` int(11) DEFAULT 0,
  `year_of_passing` int(11) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_placed` tinyint(1) DEFAULT 0,
  `bio` text DEFAULT NULL,
  `father_phone` varchar(20) DEFAULT NULL,
  `aadhar_number` varchar(12) DEFAULT NULL,
  `college` varchar(100) NOT NULL DEFAULT 'FET',
  `profile_photo` varchar(500) DEFAULT NULL,
  `interests` text DEFAULT NULL,
  `self_intro_video` text DEFAULT NULL,
  `address` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key constraints
ALTER TABLE `interviews` 
  ADD CONSTRAINT `fk_interviews_job_application` FOREIGN KEY (`job_application_id`) REFERENCES `job_applications` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_interviews_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `scheduled_interviews` 
  ADD CONSTRAINT `fk_sched_interview_application` FOREIGN KEY (`job_application_id`) REFERENCES `job_applications` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sched_interview_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Insert dummy users
INSERT INTO `users` (username, password, role, full_name, email, branch, college) VALUES
('admin', 'admin123', 'admin', 'System Administrator', 'admin@placement.com', 'Computer Science', 'FET'),
('placement_officer', 'place123', 'placement_officer', 'Placement Officer', 'placement@placement.com', 'Management', 'FET'),
('internship_officer', 'intern123', 'internship_officer', 'Internship Officer', 'internship@placement.com', 'Management', 'FET'),
('student_user', 'stud123', 'student', 'Test Student', 'student@placement.com', 'Computer Science', 'FET'),
('cse_admin', 'dept123', 'admin', 'CSE Admin', 'cse@placement.com', 'Computer Science and Engineering', 'FET'),
('ece_admin', 'dept123', 'admin', 'ECE Admin', 'ece@placement.com', 'Electronics and Communication Engineering', 'FET'),
('eee_admin', 'dept123', 'admin', 'EEE Admin', 'eee@placement.com', 'Electrical and Electronics Engineering', 'FET'),
('me_admin', 'dept123', 'admin', 'ME Admin', 'me@placement.com', 'Mechanical Engineering', 'FET'),
('ce_admin', 'dept123', 'admin', 'CE Admin', 'ce@placement.com', 'Civil Engineering', 'FET'),
('bca_admin', 'dept123', 'admin', 'BCA Admin', 'bca@placement.com', 'BCA - General', 'FCIT'),
('bcom_admin', 'dept123', 'admin', 'BCom Admin', 'bcom@placement.com', 'BCom - General', 'FCM');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
