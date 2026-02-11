<?php
// Internship Opportunities Backend Logic
// This file contains all database setup and business logic for internships

// Create tables if they don't exist
function setupInternshipTables($mysqli) {
    // Internship opportunities table (similar to job_opportunities)
    $mysqli->query("CREATE TABLE IF NOT EXISTS internship_opportunities (
        id INT PRIMARY KEY AUTO_INCREMENT,
        company VARCHAR(100),
        role VARCHAR(100),
        location VARCHAR(100),
        stipend_min DECIMAL(10,2),
        stipend_max DECIMAL(10,2),
        skills_required TEXT,
        min_cgpa DECIMAL(3,2),
        eligible_years VARCHAR(50),
        description TEXT,
        duration VARCHAR(100),
        start_date DATE,
        deadline DATE,
        apply_link VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Internship applications table (similar to job_applications)
    $mysqli->query("CREATE TABLE IF NOT EXISTS internship_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        internship_id INT NULL,
        student_id INT NOT NULL,
        internship_title VARCHAR(255) NOT NULL,
        company_name VARCHAR(255) NOT NULL,
        internship_role VARCHAR(100) NOT NULL,
        location VARCHAR(255),
        stipend_range VARCHAR(100),
        min_cgpa DECIMAL(3,2),
        required_skills TEXT,
        match_percentage INT DEFAULT 0,
        application_status ENUM('Applied', 'Shortlisted', 'Interviewed', 'Selected', 'Rejected') DEFAULT 'Applied',
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        notes TEXT,
        resume_path VARCHAR(500),
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (internship_id) REFERENCES internship_opportunities(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Saved internships table
    $mysqli->query("CREATE TABLE IF NOT EXISTS saved_internships (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT,
        internship_id INT,
        saved_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_save (student_id, internship_id),
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (internship_id) REFERENCES internship_opportunities(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Internship interviews table (similar to interviews)
    $mysqli->query("CREATE TABLE IF NOT EXISTS internship_interviews (
        id INT PRIMARY KEY AUTO_INCREMENT,
        internship_application_id INT NULL,
        student_id INT NOT NULL,
        company VARCHAR(255) NOT NULL,
        internship_role VARCHAR(255) NOT NULL,
        scheduled_at DATETIME NULL,
        panel_details TEXT,
        interview_rounds TEXT NULL,
        current_round_index INT DEFAULT 0,
        round_results TEXT NULL,
        status ENUM('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
        overall_score INT NULL,
        overall_feedback TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Internship attendance table (similar to interview_attendance)
    $mysqli->query("CREATE TABLE IF NOT EXISTS internship_attendance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        interview_id INT NOT NULL,
        student_id INT NOT NULL,
        company VARCHAR(255) NOT NULL,
        internship_role VARCHAR(255) NOT NULL,
        started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        score INT NULL,
        total_rounds INT DEFAULT 1,
        completed_rounds INT DEFAULT 0,
        round_results TEXT NULL,
        status ENUM('started', 'in_progress', 'completed', 'abandoned') DEFAULT 'started',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_student_id (student_id),
        INDEX idx_interview_id (interview_id),
        INDEX idx_company (company),
        INDEX idx_status (status),
        INDEX idx_score (score)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Calculate internship match score
function calculateInternshipMatch($internship, $student_skills, $student_cgpa, $student_year) {
    $internship_skills = array_map('trim', array_map('strtolower', explode(',', $internship['skills_required'] ?? '')));
    $student_skills_lower = array_map('strtolower', $student_skills);
    
    // Skill match (50% weight)
    $matching_skills = array_intersect($student_skills_lower, $internship_skills);
    $skill_match = count($internship_skills) > 0 ? (count($matching_skills) / count($internship_skills)) * 100 : 0;
    
    // CGPA match (30% weight)
    $cgpa_match = $student_cgpa >= ($internship['min_cgpa'] ?? 0) ? 100 : (($internship['min_cgpa'] ?? 0) > 0 ? ($student_cgpa / $internship['min_cgpa']) * 100 : 0);
    $cgpa_match = min($cgpa_match, 100);
    
    // Year eligibility (20% weight)
    $eligible_years = explode(',', $internship['eligible_years'] ?? '');
    $year_match = in_array($student_year, $eligible_years) ? 100 : 0;
    
    // Overall match score
    $match_score = ($skill_match * 0.5) + ($cgpa_match * 0.3) + ($year_match * 0.2);
    
    return [
        'match_score' => round($match_score),
        'skill_match' => round($skill_match),
        'cgpa_match' => round($cgpa_match),
        'year_match' => $year_match,
        'matching_skills' => $matching_skills,
        'missing_skills' => array_diff($internship_skills, $student_skills_lower),
        'is_eligible' => $match_score >= 70
    ];
}

// Get all internships with match scores
function getInternshipsWithMatches($mysqli, $student_id, $student_skills, $student_cgpa, $student_year) {
    $internships = [];
    
    $query = "SELECT * FROM internship_opportunities 
              WHERE deadline IS NULL OR deadline = '0000-00-00' OR deadline >= CURDATE() 
              ORDER BY created_at DESC";
    
    $result = $mysqli->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (empty($row['company'])) {
                $row['company'] = 'Unknown Company';
            }
            if (!isset($row['stipend_min']) || $row['stipend_min'] === null) { $row['stipend_min'] = 0; }
            if (!isset($row['stipend_max']) || $row['stipend_max'] === null) { $row['stipend_max'] = 0; }
            if (!isset($row['min_cgpa']) || $row['min_cgpa'] === null) { $row['min_cgpa'] = 0; }
            if (empty($row['skills_required'])) { $row['skills_required'] = ''; }
            if (empty($row['eligible_years'])) { $row['eligible_years'] = ''; }
            
            $match_data = calculateInternshipMatch($row, $student_skills, $student_cgpa, $student_year);
            $row = array_merge($row, $match_data);
            $internships[] = $row;
        }
    }
    
    return $internships;
}

// Get saved internship IDs for a student
function getSavedInternshipIds($mysqli, $student_id) {
    $saved = [];
    $result = $mysqli->query("SELECT internship_id FROM saved_internships WHERE student_id = $student_id");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $saved[] = (int)$row['internship_id'];
        }
    }
    return $saved;
}

// Get applied internships for a student
function getAppliedInternships($mysqli, $student_id) {
    $applied = [];
    $query = "SELECT ia.*, io.company, io.role 
              FROM internship_applications ia
              LEFT JOIN internship_opportunities io ON io.id = ia.internship_id
              WHERE ia.student_id = $student_id
              ORDER BY ia.applied_at DESC";
    $result = $mysqli->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $applied[] = $row;
        }
    }
    return $applied;
}
?>

