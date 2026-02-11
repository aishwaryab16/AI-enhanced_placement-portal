<?php
// Job Opportunities Backend Logic
// This file contains all database setup and business logic for jobs

// Create tables if they don't exist
function setupJobTables($mysqli) {
    // Job opportunities table
    $mysqli->query("CREATE TABLE IF NOT EXISTS job_opportunities (
        id INT PRIMARY KEY AUTO_INCREMENT,
        company VARCHAR(100),
        role VARCHAR(100),
        location VARCHAR(100),
        ctc_min DECIMAL(10,2),
        ctc_max DECIMAL(10,2),
        skills_required TEXT,
        min_cgpa DECIMAL(3,2),
        eligible_years VARCHAR(50),
        description TEXT,
        deadline DATE,
        apply_link VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Check if role column exists, if not add it
    $check_role = $mysqli->query("SHOW COLUMNS FROM job_opportunities LIKE 'role'");
    if (!$check_role || $check_role->num_rows === 0) {
        // Try to add after company, or after id if company doesn't exist
        $check_company = $mysqli->query("SHOW COLUMNS FROM job_opportunities LIKE 'company'");
        if ($check_company && $check_company->num_rows > 0) {
            $mysqli->query("ALTER TABLE job_opportunities ADD COLUMN role VARCHAR(100) NULL AFTER company");
        } else {
            $mysqli->query("ALTER TABLE job_opportunities ADD COLUMN role VARCHAR(100) NULL AFTER id");
        }
    }
    
    // Also check and add other columns that might be needed (for manage_jobs.php compatibility)
    $check_job_title = $mysqli->query("SHOW COLUMNS FROM job_opportunities LIKE 'job_title'");
    if (!$check_job_title || $check_job_title->num_rows === 0) {
        // If job_title doesn't exist, try to add it (or use title if it exists)
        $check_title = $mysqli->query("SHOW COLUMNS FROM job_opportunities LIKE 'title'");
        if ($check_title && $check_title->num_rows > 0) {
            // Title exists, might need to add job_title as alias or just use title
        } else {
            $mysqli->query("ALTER TABLE job_opportunities ADD COLUMN job_title VARCHAR(255) NULL AFTER id");
        }
    }
    
    $check_company_name = $mysqli->query("SHOW COLUMNS FROM job_opportunities LIKE 'company_name'");
    if (!$check_company_name || $check_company_name->num_rows === 0) {
        // If company_name doesn't exist but company does, we can use company
        // Or add company_name as a separate column
        $check_company = $mysqli->query("SHOW COLUMNS FROM job_opportunities LIKE 'company'");
        if ($check_company && $check_company->num_rows > 0) {
            // Company exists, might add company_name as alias or duplicate
            $mysqli->query("ALTER TABLE job_opportunities ADD COLUMN company_name VARCHAR(255) NULL AFTER company");
        } else {
            $mysqli->query("ALTER TABLE job_opportunities ADD COLUMN company_name VARCHAR(255) NULL AFTER job_title");
        }
    }

    // Ensure legacy 'company' column exists (expected by several queries)
    $check_company = $mysqli->query("SHOW COLUMNS FROM job_opportunities LIKE 'company'");
    if (!$check_company || $check_company->num_rows === 0) {
        $position_column = $mysqli->query("SHOW COLUMNS FROM job_opportunities LIKE 'job_title'");
        if ($position_column && $position_column->num_rows > 0) {
            $mysqli->query("ALTER TABLE job_opportunities ADD COLUMN company VARCHAR(255) NULL AFTER job_title");
        } else {
            $mysqli->query("ALTER TABLE job_opportunities ADD COLUMN company VARCHAR(255) NULL AFTER id");
        }
    }

    // Keep company/company_name columns in sync (fill missing values)
    $mysqli->query("UPDATE job_opportunities SET company = company_name WHERE (company IS NULL OR company = '') AND company_name IS NOT NULL");
    $mysqli->query("UPDATE job_opportunities SET company_name = company WHERE (company_name IS NULL OR company_name = '') AND company IS NOT NULL");
    
    $check_job_description = $mysqli->query("SHOW COLUMNS FROM job_opportunities LIKE 'job_description'");
    if (!$check_job_description || $check_job_description->num_rows === 0) {
        $check_description = $mysqli->query("SHOW COLUMNS FROM job_opportunities LIKE 'description'");
        if ($check_description && $check_description->num_rows > 0) {
            // Description exists, add job_description as alias or duplicate
            $mysqli->query("ALTER TABLE job_opportunities ADD COLUMN job_description TEXT NULL AFTER description");
        }
    }

    // Ensure id column is a proper AUTO_INCREMENT PRIMARY KEY
    $id_column = $mysqli->query("SHOW COLUMNS FROM job_opportunities LIKE 'id'");
    $needs_id_fix = true;
    if ($id_column && $id_column->num_rows > 0) {
        $id_info = $id_column->fetch_assoc();
        $extra = strtolower($id_info['Extra'] ?? '');
        $needs_id_fix = (strpos($extra, 'auto_increment') === false);
        $primary_result = $mysqli->query("SHOW KEYS FROM job_opportunities WHERE Key_name = 'PRIMARY'");
        $has_primary = $primary_result && $primary_result->num_rows > 0;
        if (!$needs_id_fix && !$has_primary) {
            $needs_id_fix = true; // need to add primary key even if auto_increment already set
        }
    }

    if ($needs_id_fix) {
        resequenceJobOpportunityIds($mysqli);
    }

    // Job applications table (comprehensive version)
    $mysqli->query("CREATE TABLE IF NOT EXISTS job_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NULL,
        student_id INT NOT NULL,
        job_title VARCHAR(255) NOT NULL,
        company_name VARCHAR(255) NOT NULL,
        job_role VARCHAR(100) NOT NULL,
        location VARCHAR(255),
        salary_range VARCHAR(100),
        min_cgpa DECIMAL(3,2),
        required_skills TEXT,
        match_percentage INT DEFAULT 0,
        application_status ENUM('Applied', 'Shortlisted', 'Interviewed', 'Selected', 'Rejected') DEFAULT 'Applied',
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        notes TEXT,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (job_id) REFERENCES job_opportunities(id) ON DELETE SET NULL
    )");
    
    // Check if table exists and get its structure
    $table_exists = $mysqli->query("SHOW TABLES LIKE 'job_applications'");
    if ($table_exists && $table_exists->num_rows > 0) {
        // Table exists, check for required columns and add them if missing
        
        // Check and add student_id column if it doesn't exist
        $check_student_id = $mysqli->query("SHOW COLUMNS FROM job_applications LIKE 'student_id'");
        if (!$check_student_id || $check_student_id->num_rows === 0) {
            // Check if table has any rows
            $row_count = $mysqli->query("SELECT COUNT(*) as cnt FROM job_applications");
            $count = 0;
            if ($row_count) {
                $row = $row_count->fetch_assoc();
                $count = (int)($row['cnt'] ?? 0);
            }
            
            if ($count > 0) {
                // Table has data, add as nullable first, then update and make NOT NULL
                $mysqli->query("ALTER TABLE job_applications ADD COLUMN student_id INT NULL AFTER id");
                // Note: You may need to populate student_id for existing rows manually
            } else {
                // Table is empty, can add as NOT NULL
                $mysqli->query("ALTER TABLE job_applications ADD COLUMN student_id INT NOT NULL AFTER id");
            }
        }
        
        // Check and add job_id column if it doesn't exist
        $check_job_id = $mysqli->query("SHOW COLUMNS FROM job_applications LIKE 'job_id'");
        if (!$check_job_id || $check_job_id->num_rows === 0) {
            $mysqli->query("ALTER TABLE job_applications ADD COLUMN job_id INT NULL AFTER id");
        }
        
        // Check and add job_title column if it doesn't exist
        $check_job_title = $mysqli->query("SHOW COLUMNS FROM job_applications LIKE 'job_title'");
        if (!$check_job_title || $check_job_title->num_rows === 0) {
            $mysqli->query("ALTER TABLE job_applications ADD COLUMN job_title VARCHAR(255) NOT NULL DEFAULT '' AFTER student_id");
        }
        
        // Check and add company_name column if it doesn't exist
        $check_company_name = $mysqli->query("SHOW COLUMNS FROM job_applications LIKE 'company_name'");
        if (!$check_company_name || $check_company_name->num_rows === 0) {
            $mysqli->query("ALTER TABLE job_applications ADD COLUMN company_name VARCHAR(255) NOT NULL DEFAULT '' AFTER job_title");
        }
        
        // Check and add job_role column if it doesn't exist
        $check_job_role = $mysqli->query("SHOW COLUMNS FROM job_applications LIKE 'job_role'");
        if (!$check_job_role || $check_job_role->num_rows === 0) {
            $mysqli->query("ALTER TABLE job_applications ADD COLUMN job_role VARCHAR(100) NOT NULL DEFAULT '' AFTER company_name");
        }
        
        // Check and add application_status column if it doesn't exist (might be named 'status')
        $check_status = $mysqli->query("SHOW COLUMNS FROM job_applications LIKE 'application_status'");
        $check_status_old = $mysqli->query("SHOW COLUMNS FROM job_applications LIKE 'status'");
        if ((!$check_status || $check_status->num_rows === 0) && (!$check_status_old || $check_status_old->num_rows === 0)) {
            $mysqli->query("ALTER TABLE job_applications ADD COLUMN application_status ENUM('Applied', 'Shortlisted', 'Interviewed', 'Selected', 'Rejected') DEFAULT 'Applied'");
        } elseif ($check_status_old && $check_status_old->num_rows > 0 && (!$check_status || $check_status->num_rows === 0)) {
            // Rename 'status' to 'application_status' if it exists
            $mysqli->query("ALTER TABLE job_applications CHANGE COLUMN status application_status ENUM('Applied', 'Shortlisted', 'Interviewed', 'Selected', 'Rejected') DEFAULT 'Applied'");
        }
        
        // Check and add applied_at column if it doesn't exist (might be named 'application_date')
        $check_applied_at = $mysqli->query("SHOW COLUMNS FROM job_applications LIKE 'applied_at'");
        $check_applied_date = $mysqli->query("SHOW COLUMNS FROM job_applications LIKE 'application_date'");
        if ((!$check_applied_at || $check_applied_at->num_rows === 0) && (!$check_applied_date || $check_applied_date->num_rows === 0)) {
            $mysqli->query("ALTER TABLE job_applications ADD COLUMN applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        } elseif ($check_applied_date && $check_applied_date->num_rows > 0 && (!$check_applied_at || $check_applied_at->num_rows === 0)) {
            // Rename 'application_date' to 'applied_at' if it exists
            $mysqli->query("ALTER TABLE job_applications CHANGE COLUMN application_date applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        }
        
        // Add username column if table exists but column doesn't
        $check_username = $mysqli->query("SHOW COLUMNS FROM job_applications LIKE 'username'");
        if (!$check_username || $check_username->num_rows === 0) {
            $mysqli->query("ALTER TABLE job_applications ADD COLUMN username VARCHAR(100) NULL AFTER student_id");
        }
        
        // Add full_name column if table exists but column doesn't
        $check_fullname = $mysqli->query("SHOW COLUMNS FROM job_applications LIKE 'full_name'");
        if (!$check_fullname || $check_fullname->num_rows === 0) {
            $mysqli->query("ALTER TABLE job_applications ADD COLUMN full_name VARCHAR(255) NULL AFTER username");
        }
        
        // Add resume_path column if table exists but column doesn't
        $check_resume = $mysqli->query("SHOW COLUMNS FROM job_applications LIKE 'resume_path'");
        if (!$check_resume || $check_resume->num_rows === 0) {
            $mysqli->query("ALTER TABLE job_applications ADD COLUMN resume_path VARCHAR(500) NULL AFTER full_name");
        }
    }

    // Saved jobs table
    $mysqli->query("CREATE TABLE IF NOT EXISTS saved_jobs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT,
        job_id INT,
        saved_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_save (student_id, job_id),
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (job_id) REFERENCES job_opportunities(id) ON DELETE CASCADE
    )");

    // Placement drives table
    $mysqli->query("CREATE TABLE IF NOT EXISTS placement_drives (
        id INT PRIMARY KEY AUTO_INCREMENT,
        company VARCHAR(100),
        event_name VARCHAR(200),
        event_date DATE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Interviews table (for shortlisted students scheduling)
    // Create table without foreign keys first to avoid constraint errors
    $mysqli->query("CREATE TABLE IF NOT EXISTS interviews (
        id INT PRIMARY KEY AUTO_INCREMENT,
        job_application_id INT NULL,
        student_id INT NOT NULL,
        company VARCHAR(255) NOT NULL,
        job_role VARCHAR(255) NOT NULL,
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
    
    // Rename old columns if they exist (for backward compatibility)
    $column_check = $mysqli->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interviews' AND COLUMN_NAME = 'score'");
    if ($column_check && $column_check->num_rows > 0) {
        @$mysqli->query("ALTER TABLE interviews CHANGE COLUMN score overall_score INT NULL");
    }
    
    $feedback_check = $mysqli->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interviews' AND COLUMN_NAME = 'feedback'");
    if ($feedback_check && $feedback_check->num_rows > 0) {
        @$mysqli->query("ALTER TABLE interviews CHANGE COLUMN feedback overall_feedback TEXT");
    }
    
    // Add overall_score column if it doesn't exist
    $mysqli->query("ALTER TABLE interviews ADD COLUMN IF NOT EXISTS overall_score INT NULL AFTER status");
    // Add overall_feedback column if it doesn't exist
    $mysqli->query("ALTER TABLE interviews ADD COLUMN IF NOT EXISTS overall_feedback TEXT AFTER overall_score");
    
    // Add interview_rounds column if it doesn't exist
    $mysqli->query("ALTER TABLE interviews ADD COLUMN IF NOT EXISTS interview_rounds TEXT NULL AFTER panel_details");
    // Add current_round_index column if it doesn't exist
    $mysqli->query("ALTER TABLE interviews ADD COLUMN IF NOT EXISTS current_round_index INT DEFAULT 0 AFTER interview_rounds");
    
    // Create interview_attendance table
    $mysqli->query("CREATE TABLE IF NOT EXISTS interview_attendance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        interview_id INT NOT NULL,
        student_id INT NOT NULL,
        company VARCHAR(255) NOT NULL,
        job_role VARCHAR(255) NOT NULL,
        round_name VARCHAR(255) NOT NULL,
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
    // Add round_results column if it doesn't exist
    $mysqli->query("ALTER TABLE interviews ADD COLUMN IF NOT EXISTS round_results TEXT NULL AFTER current_round_index");

    // Final mock interview results table (single high‑stakes attempt per student/interview)
    $mysqli->query("CREATE TABLE IF NOT EXISTS final_mock_interview_results (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        interview_id INT NULL,
        company VARCHAR(255) NOT NULL,
        job_role VARCHAR(255) NOT NULL,
        score INT NULL,
        feedback TEXT,
        strengths TEXT,
        weaknesses TEXT,
        conversation LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_student_interview (student_id, interview_id),
        INDEX idx_student (student_id),
        INDEX idx_interview (interview_id),
        INDEX idx_company_role (company, job_role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Add foreign keys separately if they don't exist
    // Check if foreign key already exists before adding
    $fk_check = $mysqli->query("SELECT COUNT(*) as cnt FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'interviews' 
        AND CONSTRAINT_NAME = 'fk_interviews_student'");
    if ($fk_check && $fk_check->fetch_assoc()['cnt'] == 0) {
        @$mysqli->query("ALTER TABLE interviews ADD CONSTRAINT fk_interviews_student 
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE");
    }
    
    $fk_check2 = $mysqli->query("SELECT COUNT(*) as cnt FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'interviews' 
        AND CONSTRAINT_NAME = 'fk_interviews_job_application'");
    if ($fk_check2 && $fk_check2->fetch_assoc()['cnt'] == 0) {
        @$mysqli->query("ALTER TABLE interviews ADD CONSTRAINT fk_interviews_job_application 
            FOREIGN KEY (job_application_id) REFERENCES job_applications(id) ON DELETE SET NULL");
    }
}

function dropPrimaryKeyIfExists($mysqli, $table) {
    $pk_check = $mysqli->query("SHOW KEYS FROM {$table} WHERE Key_name = 'PRIMARY'");
    if ($pk_check && $pk_check->num_rows > 0) {
        $mysqli->query("ALTER TABLE {$table} DROP PRIMARY KEY");
    }
}

function resequenceJobOpportunityIds($mysqli) {
    // Quick sanity check
    $has_rows = $mysqli->query("SELECT id FROM job_opportunities LIMIT 1");
    if (!$has_rows || $has_rows->num_rows === 0) {
        // Table empty – simply ensure id column has correct definition
        $mysqli->query("ALTER TABLE job_opportunities MODIFY COLUMN id INT NOT NULL");
        dropPrimaryKeyIfExists($mysqli, 'job_opportunities');
        $mysqli->query("ALTER TABLE job_opportunities ADD PRIMARY KEY (id)");
        $mysqli->query("ALTER TABLE job_opportunities MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
        return;
    }

    // Detect duplicate or invalid ids (<= 0)
    $duplicate_ids = $mysqli->query("SELECT id FROM job_opportunities GROUP BY id HAVING COUNT(*) > 1 OR id IS NULL OR id <= 0");
    $needs_resequence = ($duplicate_ids && $duplicate_ids->num_rows > 0);

    if ($needs_resequence) {
        // Add temporary column to store new ids
        $mysqli->query("ALTER TABLE job_opportunities ADD COLUMN temp_new_id INT NULL");

        // Assign deterministic new ids based on creation order to temp column
        $result = $mysqli->query("SELECT id, COALESCE(created_at, '1970-01-01') AS created_at FROM job_opportunities ORDER BY created_at ASC, id ASC");
        if ($result) {
            $new_id = 1;
            while ($row = $result->fetch_assoc()) {
                $current_id = (int)($row['id'] ?? 0);
                $stmt = $mysqli->prepare("UPDATE job_opportunities SET temp_new_id = ? WHERE id = ? AND (temp_new_id IS NULL OR temp_new_id = 0) LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('ii', $new_id, $current_id);
                    $stmt->execute();
                    $stmt->close();
                }
                $new_id++;
            }
        }

        // Update referencing tables (job_applications, saved_jobs)
        $mysqli->query("UPDATE job_applications ja JOIN job_opportunities jo ON ja.job_id = jo.id SET ja.job_id = jo.temp_new_id WHERE jo.temp_new_id IS NOT NULL");
        $mysqli->query("UPDATE saved_jobs sj JOIN job_opportunities jo ON sj.job_id = jo.id SET sj.job_id = jo.temp_new_id WHERE jo.temp_new_id IS NOT NULL");

        // Apply new ids to the main table
        $mysqli->query("UPDATE job_opportunities SET id = temp_new_id WHERE temp_new_id IS NOT NULL");
        $mysqli->query("ALTER TABLE job_opportunities DROP COLUMN temp_new_id");
    }

    // Finally ensure id column has correct definition (primary + auto increment)
    dropPrimaryKeyIfExists($mysqli, 'job_opportunities');
    $mysqli->query("ALTER TABLE job_opportunities ADD PRIMARY KEY (id)");
    $mysqli->query("ALTER TABLE job_opportunities MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
}

// Insert sample data if tables are empty
function insertSampleData($mysqli) {
    $count = $mysqli->query("SELECT COUNT(*) as cnt FROM job_opportunities")->fetch_assoc()['cnt'];
    if ($count == 0) {
        $sampleJobs = [
            // Full-time Jobs
            ['IBM', 'AI Engineer', 'Bangalore', 8.00, 12.00, 'Python,TensorFlow,Machine Learning,REST API', 7.5, '3,4', 'Work on cutting-edge AI solutions for enterprise clients', '2025-11-30'],
            ['TCS', 'Data Analyst', 'Pune', 6.00, 8.00, 'SQL,Excel,Python,Data Visualization', 6.5, '3,4', 'Analyze business data and create insights', '2025-11-25'],
            ['Infosys', 'ML Engineer', 'Hyderabad', 7.00, 10.00, 'Python,Machine Learning,Deep Learning,PyTorch', 7.0, '3,4', 'Build ML models for enterprise solutions', '2025-12-15'],
            ['Google', 'Software Engineer', 'Bangalore', 15.00, 25.00, 'Java,Python,System Design,Algorithms,Data Structures', 8.0, '4', 'Develop scalable software systems', '2025-12-01'],
            ['Microsoft', 'Cloud Engineer', 'Hyderabad', 12.00, 18.00, 'Azure,Docker,Kubernetes,Python,DevOps', 7.5, '3,4', 'Build cloud infrastructure solutions', '2025-11-20'],
            ['Amazon', 'Backend Developer', 'Bangalore', 10.00, 15.00, 'Java,Spring Boot,AWS,Microservices,REST API', 7.0, '3,4', 'Develop backend services for e-commerce', '2025-12-10'],
            ['Wipro', 'Full Stack Developer', 'Chennai', 5.00, 7.00, 'React,Node.js,MongoDB,JavaScript,HTML,CSS', 6.0, '3,4', 'Build full-stack web applications', '2025-11-28'],
            ['Accenture', 'Business Analyst', 'Mumbai', 6.00, 9.00, 'SQL,Excel,Business Intelligence,Communication', 6.5, '3,4', 'Analyze business requirements', '2025-12-05'],
            ['Cognizant', 'Java Developer', 'Bangalore', 5.50, 7.50, 'Java,Spring,Hibernate,MySQL,REST API', 6.5, '3,4', 'Develop enterprise Java applications', '2025-11-22'],
            ['Capgemini', 'Frontend Developer', 'Pune', 5.00, 7.00, 'React,JavaScript,HTML,CSS,Redux', 6.0, '3,4', 'Create responsive web interfaces', '2025-12-08'],
            
            // Internships
            ['Google', 'Software Engineering Intern', 'Bangalore', 0.80, 1.20, 'Python,Java,Data Structures,Algorithms', 7.0, '2,3', '3-month internship with potential for full-time conversion', '2025-11-15'],
            ['Microsoft', 'Data Science Intern', 'Hyderabad', 0.60, 1.00, 'Python,Machine Learning,Statistics,SQL', 7.0, '2,3', '6-month internship working on real ML projects', '2025-12-01'],
            ['Amazon', 'Web Development Intern', 'Bangalore', 0.50, 0.80, 'React,JavaScript,HTML,CSS,Node.js', 6.5, '2,3', '4-month internship building web applications', '2025-11-20'],
            ['IBM', 'AI Research Intern', 'Pune', 0.70, 1.00, 'Python,TensorFlow,Deep Learning,Research', 7.5, '3,4', '6-month research internship in AI lab', '2025-12-10'],
            ['Flipkart', 'Product Management Intern', 'Bangalore', 0.40, 0.60, 'Communication,Analytics,Product Design', 6.5, '2,3', '3-month internship in product team', '2025-11-25']
        ];
        
        $stmt = $mysqli->prepare('INSERT INTO job_opportunities (company, role, location, ctc_min, ctc_max, skills_required, min_cgpa, eligible_years, description, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if ($stmt) {
            foreach ($sampleJobs as $job) {
                // types: company(s), role(s), location(s), ctc_min(d), ctc_max(d), skills_required(s), min_cgpa(d), eligible_years(s), description(s), deadline(s)
                $stmt->bind_param('sssddsdsss', $job[0], $job[1], $job[2], $job[3], $job[4], $job[5], $job[6], $job[7], $job[8], $job[9]);
                $stmt->execute();
            }
            $stmt->close();
        }
    }

    $driveCount = $mysqli->query("SELECT COUNT(*) as cnt FROM placement_drives")->fetch_assoc()['cnt'];
    if ($driveCount == 0) {
        $sampleDrives = [
            ['Infosys', 'Infosys Hackathon 2025', '2025-10-25', 'Coding competition for final year students'],
            ['TCS', 'TCS CodeVita Round', '2025-11-10', 'Global coding challenge'],
            ['Wipro', 'Wipro Aptitude Test', '2025-11-15', 'Aptitude and technical assessment'],
            ['Accenture', 'Accenture Campus Drive', '2025-11-20', 'On-campus recruitment drive'],
            ['Microsoft', 'Microsoft Engage Program', '2025-12-01', 'Mentorship and hiring program']
        ];
        
        $stmt = $mysqli->prepare('INSERT INTO placement_drives (company, event_name, event_date, description) VALUES (?, ?, ?, ?)');
        if ($stmt) {
            foreach ($sampleDrives as $drive) {
                $stmt->bind_param('ssss', $drive[0], $drive[1], $drive[2], $drive[3]);
                $stmt->execute();
            }
            $stmt->close();
        }
    }
}

// Calculate job match score
function calculateJobMatch($job, $student_skills, $student_cgpa, $student_year) {
    $job_skills = array_map('trim', array_map('strtolower', explode(',', $job['skills_required'])));
    $student_skills_lower = array_map('strtolower', $student_skills);
    
    // Skill match (50% weight)
    $matching_skills = array_intersect($student_skills_lower, $job_skills);
    $skill_match = count($job_skills) > 0 ? (count($matching_skills) / count($job_skills)) * 100 : 0;
    
    // CGPA match (30% weight)
    $cgpa_match = $student_cgpa >= $job['min_cgpa'] ? 100 : ($student_cgpa / $job['min_cgpa']) * 100;
    $cgpa_match = min($cgpa_match, 100);
    
    // Year eligibility (20% weight)
    $eligible_years = explode(',', $job['eligible_years']);
    $year_match = in_array($student_year, $eligible_years) ? 100 : 0;
    
    // Overall match score
    $match_score = ($skill_match * 0.5) + ($cgpa_match * 0.3) + ($year_match * 0.2);
    
    return [
        'match_score' => round($match_score),
        'skill_match' => round($skill_match),
        'cgpa_match' => round($cgpa_match),
        'year_match' => $year_match,
        'matching_skills' => $matching_skills,
        'missing_skills' => array_diff($job_skills, $student_skills_lower),
        'is_eligible' => $match_score >= 70
    ];
}

// Get all jobs with match scores
function getJobsWithMatches($mysqli, $student_id, $student_skills, $student_cgpa, $student_year) {
    $jobs = [];
    // Show jobs with future deadlines OR no deadline (NULL) OR zero date (some MySQL setups use '0000-00-00')
    // Fetch fresh data from database - no caching to ensure real-time updates
    // Show ALL jobs from job_opportunities table, only filter by deadline
    
    // Simple query - fetch all jobs from job_opportunities table
    // Only filter by deadline (show jobs with future deadlines or no deadline)
    $query = "SELECT * FROM job_opportunities 
              WHERE deadline IS NULL OR deadline = '0000-00-00' OR deadline >= CURDATE() 
              ORDER BY created_at DESC";
    
    $result = $mysqli->query($query);
    
    // Debug: Log if query fails
    if (!$result) {
        error_log("Job query failed: " . $mysqli->error . " | Query: " . $query);
    }
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Ensure required fields have sensible defaults
            if (empty($row['company']) && isset($row['company_name'])) {
                $row['company'] = $row['company_name'];
            }
            // Ensure company field is set
            if (empty($row['company'])) {
                $row['company'] = 'Unknown Company';
            }
            // Preserve actual values - don't override with defaults if they exist
            if (!isset($row['ctc_min']) || $row['ctc_min'] === null) { $row['ctc_min'] = 0; }
            if (!isset($row['ctc_max']) || $row['ctc_max'] === null) { $row['ctc_max'] = 0; }
            if (!isset($row['skills_required']) || $row['skills_required'] === null) { $row['skills_required'] = ''; }
            if (!isset($row['min_cgpa']) || $row['min_cgpa'] === null) { $row['min_cgpa'] = 0; }
            if (!isset($row['location']) || $row['location'] === null) { $row['location'] = ''; }
            if (!isset($row['description']) || $row['description'] === null) { $row['description'] = ''; }
            $match_data = calculateJobMatch($row, $student_skills, $student_cgpa, $student_year);
            $jobs[] = array_merge($row, $match_data);
        }
    }
    
    // Sort by match score
    usort($jobs, function($a, $b) {
        return $b['match_score'] - $a['match_score'];
    });
    
    return $jobs;
}

// Get saved job IDs for student
function getSavedJobIds($mysqli, $student_id) {
    $saved_ids = [];
    $result = $mysqli->query("SELECT job_id FROM saved_jobs WHERE student_id = $student_id");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $saved_ids[] = $row['job_id'];
        }
    }
    return $saved_ids;
}

// Get applied jobs for student (supports student_id and username fallback)
function getAppliedJobs($mysqli, $student_id, $student_username = null) {
    $applied_jobs = [];

    $base_query = "
        SELECT 
            ja.*,
            jo.company,
            jo.company_name AS jo_company_name,
            jo.role,
            jo.job_title AS jo_job_title,
            jo.location AS jo_location,
            jo.ctc_min,
            jo.ctc_max,
            jo.created_at AS job_created_at
        FROM job_applications ja 
        LEFT JOIN job_opportunities jo ON ja.job_id = jo.id 
        WHERE ja.student_id = ?
    ";

    $order_clause = " ORDER BY ja.applied_at DESC";

    if (!empty($student_username)) {
        $query = $base_query . " OR (ja.student_id IS NULL AND ja.username = ?)" . $order_clause;
        $stmt = $mysqli->prepare($query);
        if ($stmt) {
            $stmt->bind_param('is', $student_id, $student_username);
        }
    } else {
        $query = $base_query . $order_clause;
        $stmt = $mysqli->prepare($query);
        if ($stmt) {
            $stmt->bind_param('i', $student_id);
        }
    }

    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (empty($row['company'])) {
                $row['company'] = $row['jo_company_name'] ?? $row['company_name'] ?? '';
            }
            if (empty($row['role'])) {
                $row['role'] = $row['job_role'] ?? $row['jo_job_title'] ?? '';
            }
            if (empty($row['location']) && isset($row['jo_location'])) {
                $row['location'] = $row['jo_location'];
            }
            $applied_jobs[] = $row;
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare getAppliedJobs query: " . $mysqli->error);
    }

    return $applied_jobs;
}

// Get upcoming placement drives
function getUpcomingDrives($mysqli) {
    $drives = [];
    $result = $mysqli->query("SELECT * FROM placement_drives WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5");
    if ($result) {
        $drives = $result->fetch_all(MYSQLI_ASSOC);
    }
    return $drives;
}
?>
