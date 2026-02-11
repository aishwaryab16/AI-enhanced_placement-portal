-- Clean Database with Dummy Users
-- Removes all user data and adds minimal dummy users for testing

-- Clear all user-related tables
DELETE FROM users;
DELETE FROM student_skills;
DELETE FROM student_projects;
DELETE FROM student_interests;
DELETE FROM student_achievements;
DELETE FROM student_experience;
DELETE FROM student_additional_info;
DELETE FROM resume_academic_data;
DELETE FROM generated_resumes;
DELETE FROM job_applications;
DELETE FROM interviews;
DELETE FROM scheduled_interviews;
DELETE FROM interview_attendance;
DELETE FROM final_mock_interview_results;
DELETE FROM saved_jobs;
DELETE FROM saved_internships;
DELETE FROM internship_applications;
DELETE FROM internship_interviews;

-- Reset auto-increment counters
ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE student_skills AUTO_INCREMENT = 1;
ALTER TABLE student_projects AUTO_INCREMENT = 1;
ALTER TABLE student_interests AUTO_INCREMENT = 1;
ALTER TABLE student_achievements AUTO_INCREMENT = 1;
ALTER TABLE student_experience AUTO_INCREMENT = 1;
ALTER TABLE student_additional_info AUTO_INCREMENT = 1;
ALTER TABLE resume_academic_data AUTO_INCREMENT = 1;
ALTER TABLE generated_resumes AUTO_INCREMENT = 1;
ALTER TABLE job_applications AUTO_INCREMENT = 1;
ALTER TABLE interviews AUTO_INCREMENT = 1;
ALTER TABLE scheduled_interviews AUTO_INCREMENT = 1;
ALTER TABLE interview_attendance AUTO_INCREMENT = 1;
ALTER TABLE final_mock_interview_results AUTO_INCREMENT = 1;
ALTER TABLE saved_jobs AUTO_INCREMENT = 1;
ALTER TABLE saved_internships AUTO_INCREMENT = 1;
ALTER TABLE internship_applications AUTO_INCREMENT = 1;
ALTER TABLE internship_interviews AUTO_INCREMENT = 1;

-- Insert dummy users for each dashboard type
INSERT INTO users (username, password, role, full_name, email, branch, college) VALUES
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
