<?php
/**
 * Company Intelligence Matching API
 * Analyzes resume against company requirements and provides match scores
 */

require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$student_id = $_SESSION['user_id'];

switch ($action) {
    case 'analyze_match':
        analyzeResumeMatch($mysqli, $student_id);
        break;
    
    case 'get_company_data':
        getCompanyData($mysqli);
        break;
    
    case 'get_missing_skills':
        getMissingSkills($mysqli, $student_id);
        break;
    
    case 'get_recommendations':
        getRecommendations($mysqli, $student_id);
        break;
    
    case 'calculate_prs':
        calculatePlacementReadinessScore($mysqli, $student_id);
        break;
    
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function analyzeResumeMatch($mysqli, $student_id) {
    $company_name = $_POST['company_name'] ?? '';
    $resume_id = $_POST['resume_id'] ?? null;
    
    if (empty($company_name)) {
        echo json_encode(['error' => 'Company name required']);
        return;
    }
    
    // Fetch company data
    $stmt = $mysqli->prepare("SELECT * FROM company_intelligence WHERE company_name = ?");
    $stmt->bind_param('s', $company_name);
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$company) {
        echo json_encode(['error' => 'Company not found']);
        return;
    }
    
    // Fetch student skills
    $student_skills = [];
    $result = $mysqli->query("SELECT skill_name, proficiency_level FROM student_skills WHERE student_id = $student_id");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $student_skills[] = strtolower($row['skill_name']);
        }
    }
    
    // Parse company required skills
    $required_skills = json_decode($company['preferred_skills'], true) ?? [];
    $tech_stack = json_decode($company['tech_stack'], true) ?? [];
    $all_required = array_merge($required_skills, $tech_stack);
    
    // Calculate match
    $matched_skills = [];
    $missing_skills = [];
    
    foreach ($all_required as $skill) {
        $skill_lower = strtolower($skill);
        if (in_array($skill_lower, $student_skills)) {
            $matched_skills[] = $skill;
        } else {
            $missing_skills[] = $skill;
        }
    }
    
    $match_percentage = count($all_required) > 0 
        ? round((count($matched_skills) / count($all_required)) * 100, 2)
        : 0;
    
    // Fetch student projects and certifications for additional scoring
    $projects_count = 0;
    $result = $mysqli->query("SELECT COUNT(*) as count FROM student_projects WHERE student_id = $student_id");
    if ($result) {
        $row = $result->fetch_assoc();
        $projects_count = $row['count'];
    }
    
    $certifications_count = 0;
    $result = $mysqli->query("SELECT COUNT(*) as count FROM student_certifications WHERE student_id = $student_id");
    if ($result) {
        $row = $result->fetch_assoc();
        $certifications_count = $row['count'];
    }
    
    // Bonus points
    $bonus = 0;
    if ($projects_count >= 3) $bonus += 5;
    if ($certifications_count >= 2) $bonus += 5;
    
    $final_score = min(100, $match_percentage + $bonus);
    
    // Generate recommendations
    $recommendations = [];
    if ($final_score < 70) {
        $recommendations[] = "Add more projects showcasing " . implode(', ', array_slice($missing_skills, 0, 3));
        $recommendations[] = "Consider taking certifications in missing skill areas";
        $recommendations[] = "Highlight measurable achievements in your experience section";
    } else if ($final_score < 85) {
        $recommendations[] = "You're almost there! Focus on " . implode(', ', array_slice($missing_skills, 0, 2));
        $recommendations[] = "Add quantifiable metrics to your project descriptions";
    } else {
        $recommendations[] = "Excellent match! Your profile aligns well with " . $company_name;
        $recommendations[] = "Consider applying - you have a strong chance";
    }
    
    // Save match analysis
    if ($resume_id) {
        $stmt = $mysqli->prepare("INSERT INTO resume_job_matches (student_id, resume_id, job_id, match_percentage, matched_skills, missing_skills, recommendations) VALUES (?, ?, NULL, ?, ?, ?, ?)");
        $matched_json = json_encode($matched_skills);
        $missing_json = json_encode($missing_skills);
        $recommendations_json = json_encode($recommendations);
        $stmt->bind_param('iidsss', $student_id, $resume_id, $final_score, $matched_json, $missing_json, $recommendations_json);
        $stmt->execute();
        $stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'company' => $company_name,
        'match_percentage' => $final_score,
        'matched_skills' => $matched_skills,
        'missing_skills' => $missing_skills,
        'recommendations' => $recommendations,
        'projects_count' => $projects_count,
        'certifications_count' => $certifications_count,
        'industry' => $company['industry'],
        'package_range' => $company['avg_package_range']
    ]);
}

function getCompanyData($mysqli) {
    $result = $mysqli->query("SELECT company_name, industry, company_size, avg_package_range FROM company_intelligence ORDER BY company_name");
    $companies = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $companies[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'companies' => $companies
    ]);
}

function getMissingSkills($mysqli, $student_id) {
    $target_company = $_GET['company'] ?? '';
    
    if (empty($target_company)) {
        echo json_encode(['error' => 'Company name required']);
        return;
    }
    
    // Get company skills
    $stmt = $mysqli->prepare("SELECT preferred_skills, tech_stack FROM company_intelligence WHERE company_name = ?");
    $stmt->bind_param('s', $target_company);
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$company) {
        echo json_encode(['error' => 'Company not found']);
        return;
    }
    
    $required_skills = array_merge(
        json_decode($company['preferred_skills'], true) ?? [],
        json_decode($company['tech_stack'], true) ?? []
    );
    
    // Get student skills
    $student_skills = [];
    $result = $mysqli->query("SELECT skill_name FROM student_skills WHERE student_id = $student_id");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $student_skills[] = strtolower($row['skill_name']);
        }
    }
    
    // Find missing
    $missing = [];
    foreach ($required_skills as $skill) {
        if (!in_array(strtolower($skill), $student_skills)) {
            $missing[] = $skill;
        }
    }
    
    echo json_encode([
        'success' => true,
        'missing_skills' => $missing
    ]);
}

function getRecommendations($mysqli, $student_id) {
    // Fetch student data
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $recommendations = [
        'courses' => [],
        'projects' => [],
        'skills' => [],
        'certifications' => []
    ];
    
    // Get student's current skills
    $current_skills = [];
    $result = $mysqli->query("SELECT skill_name FROM student_skills WHERE student_id = $student_id");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $current_skills[] = strtolower($row['skill_name']);
        }
    }
    
    // Recommend based on branch
    $branch = strtolower($student['branch'] ?? '');
    
    if (strpos($branch, 'computer') !== false || strpos($branch, 'it') !== false) {
        if (!in_array('python', $current_skills)) {
            $recommendations['skills'][] = 'Python';
            $recommendations['courses'][] = 'Python for Data Science - Coursera';
        }
        if (!in_array('javascript', $current_skills)) {
            $recommendations['skills'][] = 'JavaScript';
            $recommendations['courses'][] = 'Full Stack Web Development - Udemy';
        }
        $recommendations['projects'][] = 'Build a REST API with authentication';
        $recommendations['projects'][] = 'Create a Machine Learning model for prediction';
        $recommendations['certifications'][] = 'AWS Certified Cloud Practitioner';
    }
    
    // General recommendations
    $recommendations['courses'][] = 'Data Structures and Algorithms - NPTEL';
    $recommendations['certifications'][] = 'Google Cloud Digital Leader';
    $recommendations['projects'][] = 'Contribute to open source projects on GitHub';
    
    echo json_encode([
        'success' => true,
        'recommendations' => $recommendations
    ]);
}

function calculatePlacementReadinessScore($mysqli, $student_id) {
    // Fetch student data
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Calculate component scores
    $scores = [
        'academic' => 0,
        'skills' => 0,
        'projects' => 0,
        'certifications' => 0,
        'experience' => 0,
        'profile_completeness' => 0
    ];
    
    // Academic Score (0-100)
    $cgpa = floatval($student['cgpa'] ?? 0);
    $scores['academic'] = min(100, ($cgpa / 10) * 100);
    
    // Skills Score
    $result = $mysqli->query("SELECT COUNT(*) as count FROM student_skills WHERE student_id = $student_id");
    if ($result) {
        $row = $result->fetch_assoc();
        $skills_count = $row['count'];
        $scores['skills'] = min(100, $skills_count * 10); // 10 skills = 100%
    }
    
    // Projects Score
    $result = $mysqli->query("SELECT COUNT(*) as count FROM student_projects WHERE student_id = $student_id");
    if ($result) {
        $row = $result->fetch_assoc();
        $projects_count = $row['count'];
        $scores['projects'] = min(100, $projects_count * 20); // 5 projects = 100%
    }
    
    // Certifications Score
    $result = $mysqli->query("SELECT COUNT(*) as count FROM student_certifications WHERE student_id = $student_id");
    if ($result) {
        $row = $result->fetch_assoc();
        $certs_count = $row['count'];
        $scores['certifications'] = min(100, $certs_count * 25); // 4 certs = 100%
    }
    
    // Experience Score
    $result = $mysqli->query("SELECT COUNT(*) as count FROM student_experience WHERE student_id = $student_id");
    if ($result) {
        $row = $result->fetch_assoc();
        $exp_count = $row['count'];
        $scores['experience'] = min(100, $exp_count * 50); // 2 experiences = 100%
    }
    
    // Profile Completeness
    $fields = ['full_name', 'email', 'phone', 'branch', 'semester', 'cgpa'];
    $completed = 0;
    foreach ($fields as $field) {
        if (!empty($student[$field])) $completed++;
    }
    $scores['profile_completeness'] = ($completed / count($fields)) * 100;
    
    // Overall Score (weighted average)
    $weights = [
        'academic' => 0.25,
        'skills' => 0.20,
        'projects' => 0.20,
        'certifications' => 0.15,
        'experience' => 0.15,
        'profile_completeness' => 0.05
    ];
    
    $overall_score = 0;
    foreach ($scores as $key => $score) {
        $overall_score += $score * $weights[$key];
    }
    
    // Determine strengths and weaknesses
    $strengths = [];
    $weaknesses = [];
    
    foreach ($scores as $key => $score) {
        if ($score >= 70) {
            $strengths[] = ucfirst(str_replace('_', ' ', $key));
        } else if ($score < 50) {
            $weaknesses[] = ucfirst(str_replace('_', ' ', $key));
        }
    }
    
    // Save to database
    $stmt = $mysqli->prepare("INSERT INTO placement_readiness_scores (student_id, overall_score, academic_score, skills_score, project_score, certification_score, experience_score, profile_completeness, strengths, weaknesses) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE overall_score = ?, academic_score = ?, skills_score = ?, project_score = ?, certification_score = ?, experience_score = ?, profile_completeness = ?, strengths = ?, weaknesses = ?");
    
    $strengths_json = json_encode($strengths);
    $weaknesses_json = json_encode($weaknesses);
    
    $stmt->bind_param('iddddddssddddddss', 
        $student_id, $overall_score, $scores['academic'], $scores['skills'], 
        $scores['projects'], $scores['certifications'], $scores['experience'], 
        $scores['profile_completeness'], $strengths_json, $weaknesses_json,
        $overall_score, $scores['academic'], $scores['skills'], 
        $scores['projects'], $scores['certifications'], $scores['experience'], 
        $scores['profile_completeness'], $strengths_json, $weaknesses_json
    );
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'overall_score' => round($overall_score, 2),
        'component_scores' => $scores,
        'strengths' => $strengths,
        'weaknesses' => $weaknesses,
        'grade' => getGrade($overall_score)
    ]);
}

function getGrade($score) {
    if ($score >= 90) return 'A+ (Excellent)';
    if ($score >= 80) return 'A (Very Good)';
    if ($score >= 70) return 'B+ (Good)';
    if ($score >= 60) return 'B (Average)';
    if ($score >= 50) return 'C (Below Average)';
    return 'D (Needs Improvement)';
}
?>
