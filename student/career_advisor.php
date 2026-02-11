<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../openai_config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// ENTERPRISE API KEY - Google Gemini AI (for backward compatibility)
define('ENTERPRISE_AI_API_KEY', 'AIzaSyDVqD_iw8pnMKlvoVUdVoF4ifiq4DGqvtU');
define('AI_API_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'set_target') {
            $target_role = $_POST['target_role'] ?? '';
            $target_company = $_POST['target_company'] ?? '';
            
            $_SESSION['target_role'] = $target_role;
            $_SESSION['target_company'] = $target_company;
            
            $success_message = 'Target role saved successfully!';
        }
    }
}

// Fetch student data
$stmt = $mysqli->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check if target is set (default values if not set)
$target_role = $_SESSION['target_role'] ?? 'Software Engineer';
$target_company = $_SESSION['target_company'] ?? '';
$has_target = true; // Always true, students can change it later

// Fetch comprehensive student data
// Fetch academic data
$academic_data = null;
$result = $mysqli->query("SELECT * FROM resume_academic_data WHERE student_id = $student_id");
if ($result) $academic_data = $result->fetch_assoc();

// Fetch skills
$skills = [];
$result = $mysqli->query("SELECT * FROM student_skills WHERE student_id = $student_id ORDER BY proficiency_level DESC");
if ($result) $skills = $result->fetch_all(MYSQLI_ASSOC);

// Fetch projects
$projects = [];
$result = $mysqli->query("SELECT * FROM student_projects WHERE student_id = $student_id ORDER BY start_date DESC");
if ($result) $projects = $result->fetch_all(MYSQLI_ASSOC);

// Fetch certifications
$certifications = [];
$result = $mysqli->query("SELECT * FROM student_certifications WHERE student_id = $student_id ORDER BY issue_date DESC");
if ($result) $certifications = $result->fetch_all(MYSQLI_ASSOC);

// Fetch experience
$experiences = [];
$result = $mysqli->query("SELECT * FROM student_experience WHERE student_id = $student_id ORDER BY start_date DESC");
if ($result) $experiences = $result->fetch_all(MYSQLI_ASSOC);

// Fetch interests
$interests = [];
if (!empty($student['interests'])) {
    $interests_json = $student['interests'];
    if (is_string($interests_json)) {
        $interests = json_decode($interests_json, true) ?: [];
    } elseif (is_array($interests_json)) {
        $interests = $interests_json;
    }
}

// Fetch PRS data
$prs_data = null;
$result = $mysqli->query("SELECT * FROM placement_readiness_scores WHERE student_id = $student_id");
if ($result) $prs_data = $result->fetch_assoc();

// Calculate batch average CGPA (calculate from database)
$batch_avg_cgpa = 7.6;
$batch_avg_result = $mysqli->query("SELECT AVG(cgpa) as avg_cgpa FROM users WHERE role = 'student' AND cgpa > 0");
if ($batch_avg_result && $row = $batch_avg_result->fetch_assoc()) {
    $batch_avg_cgpa = round($row['avg_cgpa'], 1);
}
$student_cgpa = $student['cgpa'] ?? ($academic_data['cgpa'] ?? 0);

/**
 * Generate career advice using OpenAI
 */
function generateCareerAdviceWithAI($student_profile, $target_role, $target_company = '') {
    if (!isOpenAIConfigured()) {
        return null;
    }
    
    $api_key = getOpenAIKey();
    if (!$api_key) {
        return null;
    }
    
    // Prepare profile summary for AI
    $profile_summary = "Student Profile:\n";
    $profile_summary .= "Name: " . ($student_profile['personal']['name'] ?? 'N/A') . "\n";
    $profile_summary .= "Branch: " . ($student_profile['academic']['branch'] ?? $student_profile['personal']['branch'] ?? 'N/A') . "\n";
    $profile_summary .= "CGPA: " . ($student_profile['academic']['cgpa'] ?? $student_profile['personal']['cgpa'] ?? 'N/A') . "\n";
    $profile_summary .= "Semester: " . ($student_profile['academic']['semester'] ?? $student_profile['personal']['semester'] ?? 'N/A') . "\n\n";
    
    $profile_summary .= "Skills (" . count($student_profile['skills']) . "):\n";
    foreach ($student_profile['skills'] as $skill) {
        $profile_summary .= "- " . ($skill['name'] ?? $skill['skill_name'] ?? '') . " (Proficiency: " . ($skill['proficiency'] ?? $skill['proficiency_level'] ?? 0) . "/5)\n";
    }
    $profile_summary .= "\n";
    
    $profile_summary .= "Projects (" . count($student_profile['projects']) . "):\n";
    foreach ($student_profile['projects'] as $project) {
        $profile_summary .= "- " . ($project['project_title'] ?? $project['title'] ?? '') . " (" . ($project['role'] ?? 'Developer') . ")\n";
    }
    $profile_summary .= "\n";
    
    $profile_summary .= "Certifications (" . count($student_profile['certifications']) . "):\n";
    foreach ($student_profile['certifications'] as $cert) {
        $profile_summary .= "- " . ($cert['certification_name'] ?? $cert['name'] ?? '') . " (" . ($cert['issuing_organization'] ?? 'N/A') . ")\n";
    }
    $profile_summary .= "\n";
    
    $profile_summary .= "Experience (" . count($student_profile['experience']) . "):\n";
    foreach ($student_profile['experience'] as $exp) {
        $profile_summary .= "- " . ($exp['job_title'] ?? '') . " at " . ($exp['company'] ?? '') . "\n";
    }
    $profile_summary .= "\n";
    
    if (!empty($student_profile['interests'])) {
        $profile_summary .= "Interests: " . implode(', ', $student_profile['interests']) . "\n";
    }
    
    $prompt = "Analyze the following student profile and provide comprehensive career advice for the target role: {$target_role}" . 
              ($target_company ? " at {$target_company}" : "") . ".\n\n" . 
              $profile_summary . 
              "\n\nProvide a detailed JSON response with the following structure:\n" .
              "{\n" .
              "  \"career_fit_breakdown\": {\n" .
              "    \"skill_alignment\": <score 0-100>,\n" .
              "    \"academic_score\": <score 0-100>,\n" .
              "    \"project_relevance\": <score 0-100>,\n" .
              "    \"communication\": <score 0-100>\n" .
              "  },\n" .
              "  \"missing_skills\": [\"skill1\", \"skill2\", \"skill3\", \"skill4\"],\n" .
              "  \"target_milestones\": [\n" .
              "    {\"id\": 1, \"task\": \"specific actionable task\", \"completed\": false},\n" .
              "    {\"id\": 2, \"task\": \"specific actionable task\", \"completed\": false},\n" .
              "    {\"id\": 3, \"task\": \"specific actionable task\", \"completed\": false}\n" .
              "  ],\n" .
              "  \"suggested_courses\": [\n" .
              "    {\"title\": \"course title\", \"platform\": \"platform name\", \"duration\": \"duration\", \"url\": \"#\"},\n" .
              "    {\"title\": \"course title\", \"platform\": \"platform name\", \"duration\": \"duration\", \"url\": \"#\"},\n" .
              "    {\"title\": \"course title\", \"platform\": \"platform name\", \"duration\": \"duration\", \"url\": \"#\"}\n" .
              "  ],\n" .
              "  \"recommendation_chips\": [\"#tag1\", \"#tag2\", \"#tag3\", \"#tag4\", \"#tag5\"],\n" .
              "  \"top_companies\": [\n" .
              "    {\"name\": \"company name\", \"role\": \"role title\", \"package\": \"package range\"},\n" .
              "    {\"name\": \"company name\", \"role\": \"role title\", \"package\": \"package range\"},\n" .
              "    {\"name\": \"company name\", \"role\": \"role title\", \"package\": \"package range\"}\n" .
              "  ],\n" .
              "  \"career_insights\": [\n" .
              "    {\"icon\": \"🔥\", \"text\": \"relevant insight\"},\n" .
              "    {\"icon\": \"📚\", \"text\": \"relevant insight\"},\n" .
              "    {\"icon\": \"💼\", \"text\": \"relevant insight\"},\n" .
              "    {\"icon\": \"🎯\", \"text\": \"relevant insight\"}\n" .
              "  ],\n" .
              "  \"roadmap\": [\n" .
              "    {\"quarter\": \"2025 Q1\", \"milestone\": \"milestone name\", \"status\": \"completed|in_progress|pending\"},\n" .
              "    {\"quarter\": \"2025 Q2\", \"milestone\": \"milestone name\", \"status\": \"completed|in_progress|pending\"},\n" .
              "    {\"quarter\": \"2025 Q3\", \"milestone\": \"milestone name\", \"status\": \"completed|in_progress|pending\"},\n" .
              "    {\"quarter\": \"2025 Q4\", \"milestone\": \"milestone name\", \"status\": \"completed|in_progress|pending\"}\n" .
              "  ],\n" .
              "  \"next_action\": \"specific next step recommendation\"\n" .
              "}\n\n" .
              "Be specific and actionable. Base scores on actual profile data. Return ONLY valid JSON, no additional text.";
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a career advisor. Analyze student profiles and provide actionable career advice in JSON format.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 2000,
            'temperature' => 0.7
        ]),
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 || !$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    if (!isset($data['choices'][0]['message']['content'])) {
        return null;
    }
    
    $content = $data['choices'][0]['message']['content'];
    
    // Extract JSON from response (handle markdown code blocks)
    if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
        $content = $matches[1];
    } elseif (preg_match('/```\s*(.*?)\s*```/s', $content, $matches)) {
        $content = $matches[1];
    }
    
    $career_advice = json_decode($content, true);
    return $career_advice;
}

// Prepare student profile for AI analysis
$student_profile = [
    'personal' => [
        'name' => $student['full_name'] ?? '',
        'branch' => $student['branch'] ?? '',
        'cgpa' => $student_cgpa,
        'semester' => $student['semester'] ?? '',
        'bio' => $student['bio'] ?? ''
    ],
    'academic' => $academic_data,
    'skills' => array_map(function($skill) {
        return [
            'name' => $skill['skill_name'] ?? '',
            'proficiency' => $skill['proficiency_level'] ?? 0
        ];
    }, $skills),
    'projects' => $projects,
    'certifications' => $certifications,
    'experience' => $experiences,
    'interests' => $interests
];

// Generate career advice using AI
$career_advice = null;
$career_advice_cache_ttl = 900; // 15 minutes
$career_advice_cache_key = 'career_advice_' . $student_id . '_' . md5($target_role . '|' . $target_company);

if (!isset($_SESSION['career_advice_cache'])) {
    $_SESSION['career_advice_cache'] = [];
}

if (isset($_SESSION['career_advice_cache'][$career_advice_cache_key])) {
    $cached_advice = $_SESSION['career_advice_cache'][$career_advice_cache_key];
    $cached_timestamp = $cached_advice['timestamp'] ?? 0;
    if (!empty($cached_advice['data']) && (time() - $cached_timestamp) < $career_advice_cache_ttl) {
        $career_advice = $cached_advice['data'];
    }
}

if ($career_advice === null && isOpenAIConfigured()) {
    $career_advice = generateCareerAdviceWithAI($student_profile, $target_role, $target_company);

    if (!empty($career_advice)) {
        $_SESSION['career_advice_cache'][$career_advice_cache_key] = [
            'timestamp' => time(),
            'data' => $career_advice
        ];

        if (count($_SESSION['career_advice_cache']) > 5) {
            $_SESSION['career_advice_cache'] = array_slice($_SESSION['career_advice_cache'], -5, null, true);
        }
    }
}

// Use AI-generated data or fallback to defaults
$fit_breakdown = $career_advice['career_fit_breakdown'] ?? [
    'skill_alignment' => 75,
    'academic_score' => 70,
    'project_relevance' => 70,
    'communication' => 75
];
$career_fit_score = round(array_sum($fit_breakdown) / count($fit_breakdown));

$missing_skills = $career_advice['missing_skills'] ?? ['Communication', 'Teamwork', 'Problem Solving', 'Time Management'];

$target_milestones = $career_advice['target_milestones'] ?? [
    ['id' => 1, 'task' => 'Complete your profile', 'completed' => false],
    ['id' => 2, 'task' => 'Add more projects', 'completed' => false],
    ['id' => 3, 'task' => 'Take mock interview', 'completed' => false]
];

$suggested_courses = $career_advice['suggested_courses'] ?? [
    ['title' => 'Build Your Profile', 'platform' => 'System', 'duration' => 'Ongoing', 'url' => '#'],
    ['title' => 'Add Skills & Projects', 'platform' => 'System', 'duration' => 'Ongoing', 'url' => '#'],
    ['title' => 'Practice Interviews', 'platform' => 'System', 'duration' => 'Ongoing', 'url' => '#']
];

$recommendation_chips = $career_advice['recommendation_chips'] ?? ['#Skills', '#Projects', '#Experience', '#Certifications', '#CareerGrowth'];

$top_companies = $career_advice['top_companies'] ?? [
    ['name' => 'Add Companies', 'role' => 'Your Role', 'package' => 'Competitive'],
    ['name' => 'Add Companies', 'role' => 'Your Role', 'package' => 'Competitive'],
    ['name' => 'Add Companies', 'role' => 'Your Role', 'package' => 'Competitive']
];

$career_insights = $career_advice['career_insights'] ?? [
    ['icon' => '💡', 'text' => 'Complete your profile to get personalized advice'],
    ['icon' => '📚', 'text' => 'Add skills and projects for better recommendations'],
    ['icon' => '💼', 'text' => 'Update your profile regularly'],
    ['icon' => '🎯', 'text' => 'Set your target role for customized guidance']
];

$roadmap = $career_advice['roadmap'] ?? [
    ['quarter' => date('Y') . ' Q1', 'milestone' => 'Profile Setup', 'status' => 'in_progress'],
    ['quarter' => date('Y') . ' Q2', 'milestone' => 'Skill Development', 'status' => 'pending'],
    ['quarter' => date('Y') . ' Q3', 'milestone' => 'Mock Interviews', 'status' => 'pending'],
    ['quarter' => date('Y') . ' Q4', 'milestone' => 'Job Applications', 'status' => 'pending']
];

$advice_sections = $career_advice['advice_sections'] ?? [
    'general' => 'Focus on strengthening your fundamentals. Keep your resume updated, showcase measurable impact in past projects, and align your goals with the target role.',
    'technical' => 'Prioritize one primary tech stack. Build depth through two strong projects, practice role-specific tech questions weekly, and document learnings.',
    'soft' => 'Sharpen communication and collaboration skills. Practice storytelling during mock interviews and request peer feedback on presentation style.',
    'interview' => 'Research recent interview stories for your target companies, track common question patterns, and rehearse crisp answers to “Why you?” questions.'
];

// Progress tracking
$completed_milestones = count(array_filter($target_milestones, fn($m) => $m['completed'] ?? false));
$career_plan_progress = $target_milestones ? round(($completed_milestones / count($target_milestones)) * 100) : 0;

// What's next recommendation
$next_action = $career_advice['next_action'] ?? ($career_plan_progress < 50 ? 'Complete Your Profile' : 'Take Mock Interview');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Intelligence Hub - <?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../includes/partials/sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            display: flex;
        }

        .advice-content-card {
            background: #fff9f5;
            border: 1px solid rgba(91, 31, 31, 0.12);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.6);
        }

        .advice-content-label {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: #b45309;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .advice-content-body {
            color: #4c1d1d;
            font-size: 15px;
            line-height: 1.6;
            white-space: pre-line;
        }

        #ai-pipeline-loader {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.35);
            backdrop-filter: blur(3px);
            z-index: 12000;
        }

        #ai-pipeline-loader.active {
            display: flex;
        }

        .ai-loader-card {
            background: white;
            border-radius: 18px;
            padding: 28px 32px;
            width: min(400px, 90%);
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
            border: 1px solid rgba(91, 31, 31, 0.08);
            animation: loader-pop 0.25s ease-out;
        }

        .ai-loader-label {
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.18em;
            color: #9ca3af;
            margin-bottom: 6px;
        }

        .ai-loader-status {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 14px;
        }

        .ai-loader-dots {
            display: flex;
            gap: 6px;
            margin-bottom: 12px;
        }

        .ai-loader-dots span {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: linear-gradient(135deg, #5b1f1f, #ecc35c);
            animation: ai-loader-dot 1s infinite ease-in-out;
        }

        .ai-loader-dots span:nth-child(2) {
            animation-delay: 0.15s;
        }

        .ai-loader-dots span:nth-child(3) {
            animation-delay: 0.3s;
        }

        .ai-loader-subtext {
            font-size: 13px;
            color: #6b7280;
        }

        .ai-loader-card.ai-loader-error .ai-loader-status {
            color: #991b1b;
        }

        @keyframes ai-loader-dot {
            0%, 80%, 100% { transform: scale(0.7); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }

        @keyframes loader-pop {
            0% { transform: translateY(12px) scale(0.96); opacity: 0; }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }

        /* Hamburger Menu Button */
        .hamburger-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            z-index: 1001;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5px;
            box-shadow: 0 4px 15px rgba(91, 31, 31, 0.3);
            transition: all 0.3s ease;
        }

        .hamburger-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(91, 31, 31, 0.4);
        }

        .hamburger-btn span {
            width: 24px;
            height: 3px;
            background: white;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .hamburger-btn.active span:nth-child(1) {
            transform: rotate(45deg) translate(7px, 7px);
        }

        .hamburger-btn.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger-btn.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Main Content */
        .main-wrapper {
            margin-left: 240px;
            flex: 1;
            padding: 20px;
            padding-top: 0;
            width: 100%;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .top-bar-left {
            display: flex;
            align-items: center;
        }

        .breadcrumb {
            color: #6b7280;
            font-size: 14px;
        }

        .top-bar-right {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .icon-btn:hover {
            background: #f3f4f6;
        }

        .header-banner {
            background: linear-gradient(135deg, #5b1f1f 0%, #8b3a3a 50%, #ecc35c 100%);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .header-banner h1 {
            color: white;
            font-size: 36px;
            font-weight: 700;
            line-height: 1.2;
            position: relative;
            z-index: 1;
        }

        .header-banner em {
            color: #ecc35c;
            font-style: normal;
        }

        /* Career Advice Card */
        .career-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .card-header h2 {
            color: #1f2937;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Career Fit Meter */
        .fit-meter {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            text-align: center;
        }

        .fit-score {
            font-size: 48px;
            font-weight: 700;
            color: #5b1f1f;
            margin-bottom: 10px;
        }

        .fit-label {
            font-size: 14px;
            color: #92400e;
            font-weight: 600;
        }

        .target-role {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid rgba(146, 64, 14, 0.2);
        }

        .target-role strong {
            color: #5b1f1f;
        }

        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 12px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin: 15px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #5b1f1f, #ecc35c);
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        /* Missing Skills */
        .skill-gap-section {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .skill-gap-section h3 {
            color: #991b1b;
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .skill-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .skill-tag {
            background: white;
            color: #dc2626;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            border: 2px solid #fecaca;
        }

        /* Course Recommendations */
        .course-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .course-item {
            background: #f9fafb;
            border-radius: 10px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
        }

        .course-item:hover {
            background: #f3f4f6;
            transform: translateX(5px);
        }

        .course-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #5b1f1f, #ecc35c);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .course-info {
            flex: 1;
        }

        .course-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .course-meta {
            font-size: 12px;
            color: #6b7280;
        }

        /* Roadmap Timeline */
        .roadmap-timeline {
            position: relative;
            padding-left: 30px;
        }

        .roadmap-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }

        .roadmap-item {
            position: relative;
            margin-bottom: 25px;
            padding-left: 30px;
        }

        .roadmap-dot {
            position: absolute;
            left: -20px;
            top: 5px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 3px solid #e5e7eb;
            background: white;
        }

        .roadmap-dot.completed {
            background: #10b981;
            border-color: #10b981;
        }

        .roadmap-dot.in_progress {
            background: #f59e0b;
            border-color: #f59e0b;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .roadmap-quarter {
            font-size: 12px;
            color: #6b7280;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .roadmap-milestone {
            font-size: 16px;
            color: #1f2937;
            font-weight: 600;
        }

        /* Action Buttons */
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 25px;
        }

        .btn {
            padding: 14px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(91, 31, 31, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #4b5563;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-gold {
            background: linear-gradient(135deg, #ecc35c, #d4a843);
            color: #5b1f1f;
        }

        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(236, 195, 92, 0.3);
        }

        /* Companies List */
        .company-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .company-item {
            background: #f9fafb;
            border-radius: 10px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .company-info h4 {
            color: #1f2937;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .company-info p {
            color: #6b7280;
            font-size: 13px;
        }

        .company-package {
            background: #d1fae5;
            color: #065f46;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }

        /* Benchmark Panel */
        .benchmark-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .benchmark-item {
            background: #f9fafb;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }

        .benchmark-value {
            font-size: 32px;
            font-weight: 700;
            color: #5b1f1f;
            margin-bottom: 5px;
        }

        .benchmark-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 600;
        }

        .benchmark-comparison {
            font-size: 11px;
            color: #10b981;
            margin-top: 5px;
        }

        /* AI Chat Widget */
        .ai-chat-widget {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        .chat-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #5b1f1f, #ecc35c);
            color: white;
            border: none;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(91, 31, 31, 0.3);
            transition: all 0.3s;
        }

        .chat-button:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(91, 31, 31, 0.4);
        }

        /* Tabs */
        .advice-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
        }

        .tab {
            padding: 10px 20px;
            border: none;
            background: transparent;
            color: #6b7280;
            font-weight: 600;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            transition: all 0.3s;
        }

        .tab.active {
            background: #5b1f1f;
            color: white;
        }

        .tab:hover:not(.active) {
            background: #f3f4f6;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .career-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Loading overlay -->
<div id="loader-overlay" style="position:fixed;z-index:9999;inset:0;display:flex;justify-content:center;align-items:center;background:rgba(250,250,250,0.87)">
  <div style="text-align:center;">
    <div class="lds-dual-ring"></div>
    <div style="font-size:18px;color:#8B3030;margin-top:20px;">Loading Career Intelligence Hub...</div>
  </div>
</div>
<style>
.lds-dual-ring { display:inline-block;width:64px;height:64px; }
.lds-dual-ring:after {
  content:" ";
  display:block;
  width:46px;height:46px;margin:1px;
  border-radius:50%;
  border:6px solid #b69130;
  border-color:#b69130 transparent #e7dbb4 transparent;
  animation:lds-dual-ring 1.2s linear infinite;
}
@keyframes lds-dual-ring {
  0% { transform:rotate(0deg);}
  100% { transform:rotate(360deg);}
}
</style>
    <!-- Hamburger Menu Button -->
    <button class="hamburger-btn" id="hamburgerBtn">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/partials/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="breadcrumb">Career / Advisor</div>
            </div>
        </div>

        <!-- Header Banner -->
        <div class="header-banner">
            <h1>🚀 Career Intelligence Hub<br><em>AI-Powered Guidance</em></h1>
        </div>

        <?php if (!isOpenAIConfigured()): ?>
            <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-info-circle"></i> <strong>Note:</strong> OpenAI API key is not configured. 
                Please configure your API key in <code>openai_config.php</code> to get AI-powered career analysis. 
                Currently showing default recommendations.
            </div>
        <?php elseif ($career_advice === null): ?>
            <div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <strong>Note:</strong> Failed to generate AI career advice. 
                Please check your OpenAI API configuration. Showing default recommendations.
            </div>
        <?php else: ?>
            <div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <strong>AI Analysis Complete:</strong> Your career advice is powered by AI analysis of your profile data.
            </div>
        <?php endif; ?>

        <!-- Career Grid -->
        <div class="career-grid">
            <!-- Main Career Advice Card -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-brain"></i> Career Advice</h2>
                </div>

                <!-- Advice Type Tabs -->
                <div class="advice-tabs">
                    <button class="tab active" onclick="switchTab('general', event)">General Advice</button>
                    <button class="tab" onclick="switchTab('technical', event)">Technical Skills</button>
                    <button class="tab" onclick="switchTab('soft', event)">Soft Skills</button>
                    <button class="tab" onclick="switchTab('interview', event)">Interview Prep</button>
                </div>

                <div class="advice-content-card" id="adviceContentCard">
                    <div class="advice-content-label" id="adviceContentLabel">General Advice</div>
                    <div class="advice-content-body" id="adviceContentBody"><?php echo htmlspecialchars($advice_sections['general']); ?></div>
                </div>

                <!-- Career Fit Meter -->
                <div class="fit-meter">
                    <div class="fit-score"><?php echo $career_fit_score; ?>%</div>
                    <div class="fit-label">CAREER FIT SCORE</div>
                    <div class="target-role" id="targetRoleDisplay">
                        Target Role: <strong id="roleText"><?php echo htmlspecialchars($target_role); ?><?php echo $target_company ? ' at ' . htmlspecialchars($target_company) : ''; ?></strong>
                        <button onclick="editTargetInline()" style="margin-left: 10px; background: transparent; border: 1px solid #5b1f1f; color: #5b1f1f; padding: 4px 12px; border-radius: 6px; cursor: pointer; font-size: 12px;">
                            <i class="fas fa-edit"></i> Change
                        </button>
                    </div>
                    <div id="targetRoleEdit" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 2px solid rgba(146, 64, 14, 0.2);">
                        <form method="POST" id="targetRoleForm" onsubmit="return handleTargetRoleSubmit(this)" style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <input type="hidden" name="action" value="set_target">
                            <input type="text" name="target_role" placeholder="Target Role" value="<?php echo htmlspecialchars($target_role); ?>" 
                                   style="flex: 1; min-width: 200px; padding: 8px 12px; border: 2px solid #5b1f1f; border-radius: 6px; font-size: 13px;">
                            <input type="text" name="target_company" placeholder="Company (Optional)" value="<?php echo htmlspecialchars($target_company); ?>" 
                                   style="flex: 1; min-width: 150px; padding: 8px 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 13px;">
                            <button type="submit" data-role="save-target" style="background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                <i class="fas fa-check"></i> Save
                            </button>
                            <button type="button" onclick="cancelTargetEdit()" style="background: #6b7280; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                Cancel
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Career Fit Breakdown Graph -->
                <div style="background: white; border-radius: 12px; padding: 20px; margin-bottom: 25px; border: 2px solid #e5e7eb;">
                    <h3 style="color: #1f2937; font-size: 16px; margin-bottom: 15px;">
                        <i class="fas fa-chart-bar"></i> Career Fit Breakdown
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach ($fit_breakdown as $label => $score): ?>
                            <div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span style="font-size: 13px; color: #6b7280; text-transform: capitalize;"><?php echo str_replace('_', ' ', $label); ?></span>
                                    <span style="font-weight: 700; color: #5b1f1f;"><?php echo $score; ?>%</span>
                                </div>
                                <div style="height: 8px; background: #e5e7eb; border-radius: 10px; overflow: hidden;">
                                    <div style="width: <?php echo $score; ?>%; height: 100%; background: linear-gradient(90deg, #5b1f1f, #ecc35c); border-radius: 10px;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Target Job Tracker -->
                <div style="background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                    <h3 style="color: #1e40af; font-size: 16px; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-bullseye"></i> Next Target Milestones
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($target_milestones as $milestone): ?>
                            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 10px; background: white; border-radius: 8px;">
                                <input type="checkbox" <?php echo $milestone['completed'] ? 'checked' : ''; ?> 
                                       onchange="toggleMilestone(<?php echo $milestone['id']; ?>, this.checked)"
                                       style="width: 18px; height: 18px; cursor: pointer;">
                                <span style="flex: 1; color: #1f2937; font-size: 14px;"><?php echo htmlspecialchars($milestone['task']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Skill Gap Analyzer -->
                <div class="skill-gap-section">
                    <h3><i class="fas fa-exclamation-triangle"></i> Missing Skills</h3>
                    <div class="skill-tags">
                        <?php foreach ($missing_skills as $skill): ?>
                            <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Career Plan Progress -->
                <div style="margin-bottom: 25px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="font-weight: 600; color: #1f2937;">Career Plan Execution</span>
                        <span style="font-weight: 700; color: #5b1f1f;"><?php echo $career_plan_progress; ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $career_plan_progress; ?>%;"></div>
                    </div>
                    <p style="font-size: 14px; color: #6b7280; margin-top: 10px;">
                        <strong>Next Step:</strong> Complete Mock Interview
                    </p>
                </div>

                <!-- Course Recommendations -->
                <h3 style="color: #1f2937; font-size: 18px; margin-bottom: 15px;">
                    <i class="fas fa-graduation-cap"></i> Suggested Learning Path
                </h3>
                
                <!-- Smart Recommendation Chips -->
                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px;">
                    <?php foreach ($recommendation_chips as $chip): ?>
                        <span onclick="exploreChip('<?php echo $chip; ?>')" 
                              style="background: linear-gradient(135deg, #5b1f1f, #8b3a3a); color: white; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.3s;"
                              onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                            <?php echo htmlspecialchars($chip); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                
                <div class="course-list">
                    <?php foreach ($suggested_courses as $course): ?>
                        <div class="course-item">
                            <div class="course-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="course-info">
                                <div class="course-title"><?php echo htmlspecialchars($course['title']); ?></div>
                                <div class="course-meta">
                                    <?php echo htmlspecialchars($course['platform']); ?> • <?php echo htmlspecialchars($course['duration']); ?>
                                </div>
                            </div>
                            <button class="btn btn-secondary" style="padding: 8px 16px;">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- What's Next Card -->
                <div style="background: linear-gradient(135deg, #10b981, #059669); border-radius: 12px; padding: 20px; margin-top: 25px; margin-bottom: 25px; color: white;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="font-size: 40px;">🎯</div>
                        <div style="flex: 1;">
                            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">Based on your current progress:</div>
                            <div style="font-size: 18px; font-weight: 700;">Next Step → <?php echo htmlspecialchars($next_action); ?></div>
                        </div>
                        <button onclick="takeAction()" style="background: white; color: #10b981; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer;">
                            Take Action
                        </button>
                    </div>
                </div>

                <!-- Job Role Comparator Button -->
                <button onclick="openRoleComparator()" class="btn btn-secondary" style="width: 100%; justify-content: center; margin-bottom: 25px;">
                    <i class="fas fa-balance-scale"></i> Compare with Other Roles
                </button>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="resume_builder.php" class="btn btn-primary">
                        <i class="fas fa-file-alt"></i> Generate AI Resume
                    </a>
                    <a href="ai_interview.php" class="btn btn-primary">
                        <i class="fas fa-microphone"></i> Start Mock Interview
                    </a>
                    <button class="btn btn-gold" onclick="openLearningPlan()">
                        <i class="fas fa-route"></i> Open Learning Plan
                    </button>
                    <button class="btn btn-secondary" onclick="compareFit()">
                        <i class="fas fa-users"></i> Compare Career Fit
                    </button>
                </div>
            </div>

            <!-- Sidebar Cards -->
            <div style="display: flex; flex-direction: column; gap: 30px;">
                <!-- Career Roadmap -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-map-marked-alt"></i> Career Roadmap</h2>
                    </div>
                    <div class="roadmap-timeline">
                        <?php foreach ($roadmap as $item): ?>
                            <div class="roadmap-item">
                                <div class="roadmap-dot <?php echo $item['status']; ?>"></div>
                                <div class="roadmap-quarter"><?php echo $item['quarter']; ?></div>
                                <div class="roadmap-milestone"><?php echo $item['milestone']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Top Hiring Companies -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-building"></i> Top Hiring Companies</h2>
                    </div>
                    <div class="company-list">
                        <?php foreach ($top_companies as $company): ?>
                            <div class="company-item">
                                <div class="company-info">
                                    <h4><?php echo htmlspecialchars($company['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($company['role']); ?></p>
                                </div>
                                <div class="company-package"><?php echo htmlspecialchars($company['package']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Peer Benchmark -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-chart-line"></i> Peer Benchmark</h2>
                    </div>
                    <div class="benchmark-grid">
                        <div class="benchmark-item">
                            <div class="benchmark-value"><?php echo number_format($student_cgpa, 1); ?></div>
                            <div class="benchmark-label">Your CGPA</div>
                            <div class="benchmark-comparison">
                                <?php echo $student_cgpa > $batch_avg_cgpa ? '↑' : '↓'; ?> 
                                Batch Avg: <?php echo $batch_avg_cgpa; ?>
                            </div>
                        </div>
                        <div class="benchmark-item">
                            <div class="benchmark-value"><?php echo count($skills); ?></div>
                            <div class="benchmark-label">Your Skills</div>
                            <div class="benchmark-comparison">↑ Above Average</div>
                        </div>
                    </div>
                </div>

                <!-- Career Insight Feed -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-rss"></i> Career Insights</h2>
                    </div>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($career_insights as $insight): ?>
                            <div style="padding: 12px; background: #f9fafb; border-radius: 8px; margin-bottom: 10px; display: flex; align-items: start; gap: 12px;">
                                <div style="font-size: 20px;"><?php echo $insight['icon']; ?></div>
                                <div style="flex: 1; font-size: 13px; color: #4b5563; line-height: 1.5;">
                                    <?php echo htmlspecialchars($insight['text']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Mentor Chat Widget -->
    <div class="ai-chat-widget">
        <button class="chat-button" onclick="openAIMentor()">
            <i class="fas fa-robot"></i>
        </button>
    </div>

    <script>
        // Enterprise API Configuration
        const API_KEY = '<?php echo ENTERPRISE_AI_API_KEY; ?>';
        const API_ENDPOINT = '<?php echo AI_API_ENDPOINT; ?>';
        const TARGET_ROLE = '<?php echo htmlspecialchars($target_role ?? ""); ?>';
        const TARGET_COMPANY = '<?php echo htmlspecialchars($target_company ?? ""); ?>';
        const AI_FETCH_TIMEOUT_MS = 27000;
        const ADVICE_SECTIONS = <?php echo json_encode($advice_sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const ADVICE_LABELS = {
            general: 'General Advice',
            technical: 'Technical Skills',
            soft: 'Soft Skills',
            interview: 'Interview Prep'
        };
        const AI_PIPELINE_STAGE_MESSAGES = [
            'Initializing secure session...',
            'Fetching insights from the cloud...',
            'Preparing tailored recommendations...',
            'Finalizing personalized roadmap...'
        ];
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Helper function to call AI via PHP backend
        async function callAI(prompt, context = {}) {
            const loader = createAIPipelineLoader();
            const controller = new AbortController();
            let timeoutId = null;

            try {
                showToast('Connecting to AI...', 'info');
                loader.start();
                loader.updateStage('Authenticating secure session...');

                timeoutId = setTimeout(() => controller.abort(), AI_FETCH_TIMEOUT_MS);

                const response = await fetch('../api/ai_proxy.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    signal: controller.signal,
                    body: JSON.stringify({
                        prompt,
                        target_role: TARGET_ROLE,
                        target_company: TARGET_COMPANY,
                        context
                    })
                });

                loader.updateStage('Fetching insights from the cloud...');

                if (!response.ok) {
                    throw new Error(`API request failed (${response.status})`);
                }

                const data = await response.json();
                loader.updateStage('Preparing tailored recommendations...');

                clearTimeout(timeoutId);

                if (data.success) {
                    loader.finish('AI insights ready ✨');
                    return { response: data.response, success: true };
                }

                loader.finish('Unable to fetch AI insights', true);
                return { error: data.error || 'Unknown error' };
            } catch (error) {
                if (timeoutId) {
                    clearTimeout(timeoutId);
                }
                const isAbortError = error.name === 'AbortError';
                loader.finish(isAbortError ? 'AI request timed out' : 'AI link failed', true);
                console.error('AI API Error:', error);
                return { error: isAbortError ? 'The AI request timed out. Please try again.' : 'Failed to connect to AI. Please try again.' };
            }
        }
        
        // Tab switching
        function switchTab(type, evt) {
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            if (evt && evt.currentTarget) {
                evt.currentTarget.classList.add('active');
            }
            console.log('Switched to:', type);
            
            // Load different advice based on type using API
            loadAdviceByType(type);
        }

        function handleTargetRoleSubmit(form) {
            if (!form || window.__savingTargetRole) {
                return true;
            }

            window.__savingTargetRole = true;
            const loader = createAIPipelineLoader();
            loader.start();
            loader.updateStage('Saving target preference...');

            const saveBtn = form.querySelector('[data-role="save-target"]');
            if (saveBtn) {
                saveBtn.dataset.originalContent = saveBtn.innerHTML;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving';
                saveBtn.disabled = true;
                saveBtn.style.opacity = '0.8';
            }

            setTimeout(() => {
                loader.updateStage('Regenerating insights...');
            }, 1200);

            return true;
        }
        
        // Load advice by type
        async function loadAdviceByType(type) {
            const labelEl = document.getElementById('adviceContentLabel');
            const bodyEl = document.getElementById('adviceContentBody');
            if (!labelEl || !bodyEl) {
                return;
            }

            const adviceText = ADVICE_SECTIONS[type] || 'Fresh insights will appear here soon.';
            const label = ADVICE_LABELS[type] || 'Advice';

            labelEl.textContent = label;
            bodyEl.innerHTML = escapeHtml(adviceText).replace(/\n/g, '<br>');

            if (!ADVICE_SECTIONS[type]) {
                showToast('AI insights not ready yet. Showing defaults.', 'info');
            }
        }

        // Edit target inline
        function editTargetInline() {
            document.getElementById('targetRoleDisplay').style.display = 'none';
            document.getElementById('targetRoleEdit').style.display = 'block';
        }

        // Cancel target edit
        function cancelTargetEdit() {
            document.getElementById('targetRoleDisplay').style.display = 'block';
            document.getElementById('targetRoleEdit').style.display = 'none';
        }

        // Toggle milestone completion
        function toggleMilestone(milestoneId, completed) {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=toggle_milestone&milestone_id=${milestoneId}&completed=${completed ? 1 : 0}`
            }).then(() => {
                showToast(completed ? 'Milestone completed! 🎉' : 'Milestone unchecked', 'success');
                setTimeout(() => location.reload(), 1000);
            });
        }

        // Explore recommendation chip
        function exploreChip(chip) {
            showToast(`Exploring ${chip}... Feature coming soon!`, 'info');
            // TODO: Integrate with course/resource API
        }

        // Take action on next step
        function takeAction() {
            const action = '<?php echo $next_action; ?>';
            if (action.includes('Mock Interview')) {
                window.location.href = 'ai_interview.php';
            } else if (action.includes('DSA')) {
                showToast('DSA Practice Test feature coming soon!', 'info');
            }
        }

        // Open role comparator
        function openRoleComparator() {
            // Create modal for role comparison
            const modal = document.createElement('div');
            modal.className = 'modal active';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 700px;">
                    <h2 style="color: #5b1f1f; margin-bottom: 20px;">🔍 Job Role Comparator</h2>
                    <div style="background: #f9fafb; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                        <h3 style="margin-bottom: 15px;">IBM AI Engineer vs TCS Data Analyst</h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <strong>Required Skills:</strong>
                                <div style="margin-top: 8px;">TensorFlow, Python, ML</div>
                            </div>
                            <div>
                                <strong>Required Skills:</strong>
                                <div style="margin-top: 8px;">SQL, Excel, Tableau</div>
                            </div>
                            <div>
                                <strong>Avg Package:</strong>
                                <div style="margin-top: 8px; color: #10b981; font-weight: 700;">₹8-12 LPA</div>
                            </div>
                            <div>
                                <strong>Avg Package:</strong>
                                <div style="margin-top: 8px; color: #10b981; font-weight: 700;">₹6-8 LPA</div>
                            </div>
                            <div>
                                <strong>Project Type:</strong>
                                <div style="margin-top: 8px;">AI/ML Projects</div>
                            </div>
                            <div>
                                <strong>Project Type:</strong>
                                <div style="margin-top: 8px;">Business Analytics</div>
                            </div>
                        </div>
                    </div>
                    <button onclick="this.closest('.modal').remove()" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        Close
                    </button>
                </div>
            `;
            document.body.appendChild(modal);
        }

        // Open learning plan
        function openLearningPlan() {
            showToast('Learning Plan feature - Integrate with your API!', 'info');
        }

        // Compare fit with peers
        function compareFit() {
            showToast('Career Fit Comparison - Integrate with your API!', 'info');
        }

        // Open AI Mentor Chat
        function openAIMentor() {
            // Create chat modal
            const modal = document.createElement('div');
            modal.className = 'modal active';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 500px;">
                    <h2 style="color: #5b1f1f; margin-bottom: 20px;">🤖 AI Career Mentor</h2>
                    <div id="chatMessages" style="height: 300px; overflow-y: auto; background: #f9fafb; border-radius: 12px; padding: 15px; margin-bottom: 15px;">
                        <div style="background: white; padding: 12px; border-radius: 8px; margin-bottom: 10px;">
                            <strong>AI Mentor:</strong> Hello! I'm your AI Career Mentor. Ask me anything about your career path!
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="chatInput" placeholder="Ask me anything..." 
                               style="flex: 1; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px;"
                               onkeypress="if(event.key==='Enter') sendAIMessage()">
                        <button onclick="sendAIMessage()" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <button onclick="this.closest('.modal').remove()" class="btn btn-secondary" style="width: 100%; justify-content: center; margin-top: 15px;">
                        Close Chat
                    </button>
                </div>
            `;
            document.body.appendChild(modal);
        }

        // Send AI message
        async function sendAIMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            if (!message) return;

            const chatMessages = document.getElementById('chatMessages');
            
            // Add user message
            chatMessages.innerHTML += `
                <div style="background: #5b1f1f; color: white; padding: 12px; border-radius: 8px; margin-bottom: 10px; margin-left: 50px;">
                    <strong>You:</strong> ${message}
                </div>
            `;
            
            input.value = '';
            chatMessages.scrollTop = chatMessages.scrollHeight;

            // Show loading indicator
            chatMessages.innerHTML += `
                <div id="loadingMsg" style="background: #f3f4f6; padding: 12px; border-radius: 8px; margin-bottom: 10px;">
                    <strong>AI Mentor:</strong> <i class="fas fa-spinner fa-spin"></i> Thinking...
                </div>
            `;
            chatMessages.scrollTop = chatMessages.scrollHeight;

            // Call AI API with enterprise key
            const response = await callAI(message, {
                conversation_type: 'career_mentoring',
                user_question: message
            });
            
            // Remove loading indicator
            document.getElementById('loadingMsg').remove();
            
            // Add AI response
            if (response.error) {
                // Escape HTML to prevent XSS
                const errorMsg = escapeHtml(response.error);
                chatMessages.innerHTML += `
                    <div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 12px; border-radius: 8px; margin-bottom: 10px;">
                        <strong>Error:</strong> ${errorMsg}
                        ${errorMsg.includes('API key') ? '<br><small style="color: #991b1b;">Please configure your OpenAI API key in <code>openai_config.php</code></small>' : ''}
                    </div>
                `;
            } else {
                const aiResponse = response.response || response.message || 'I received your question. Let me help you with that!';
                // Escape HTML for safety
                const safeResponse = escapeHtml(aiResponse).replace(/\n/g, '<br>');
                chatMessages.innerHTML += `
                    <div style="background: white; padding: 12px; border-radius: 8px; margin-bottom: 10px;">
                        <strong>AI Mentor:</strong> ${safeResponse}
                    </div>
                `;
            }
            
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function createAIPipelineLoader() {
            const existingOverlay = document.getElementById('ai-pipeline-loader');
            if (existingOverlay) {
                existingOverlay.remove();
            }

            const overlay = document.createElement('div');
            overlay.id = 'ai-pipeline-loader';
            overlay.innerHTML = `
                <div class="ai-loader-card">
                    <div class="ai-loader-label">AI Copilot</div>
                    <div class="ai-loader-status">Connecting to AI cloud...</div>
                    <div class="ai-loader-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <div class="ai-loader-subtext">Fetching secure insights...</div>
                </div>
            `;

            document.body.appendChild(overlay);

            const statusEl = overlay.querySelector('.ai-loader-status');
            const cardEl = overlay.querySelector('.ai-loader-card');
            const subtextEl = overlay.querySelector('.ai-loader-subtext');
            let stageIndex = 0;
            let stageInterval = null;

            return {
                start() {
                    overlay.classList.add('active');
                    statusEl.textContent = AI_PIPELINE_STAGE_MESSAGES[stageIndex];
                    stageInterval = setInterval(() => {
                        stageIndex = (stageIndex + 1) % AI_PIPELINE_STAGE_MESSAGES.length;
                        statusEl.textContent = AI_PIPELINE_STAGE_MESSAGES[stageIndex];
                    }, 1400);
                },
                updateStage(message) {
                    statusEl.textContent = message;
                },
                finish(finalMessage, isError = false) {
                    if (stageInterval) {
                        clearInterval(stageInterval);
                    }
                    if (finalMessage) {
                        statusEl.textContent = finalMessage;
                    }
                    subtextEl.textContent = isError ? 'Please retry in a moment.' : 'Insights ready!';
                    cardEl.classList.toggle('ai-loader-error', !!isError);

                    setTimeout(() => {
                        overlay.classList.remove('active');
                        overlay.remove();
                    }, 850);
                }
            };
        }

        // Toast notification
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 25px;
                background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
                color: white;
                border-radius: 10px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 10000;
                font-weight: 600;
                animation: slideIn 0.3s ease;
            `;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Animate progress bar on load
        window.addEventListener('load', function() {
            const progressFill = document.querySelector('.progress-fill');
            if (progressFill) {
                progressFill.style.width = '0%';
                setTimeout(() => {
                    progressFill.style.width = '<?php echo $career_plan_progress; ?>%';
                }, 500);
            }
        });

        // Handle reset target
        <?php if (isset($_GET['reset_target'])): ?>
            <?php 
            unset($_SESSION['target_role']);
            unset($_SESSION['target_company']);
            unset($_SESSION['api_key']);
            ?>
            window.location.href = 'career_advisor.php';
        <?php endif; ?>

        // Hamburger Menu Toggle
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            hamburgerBtn.classList.toggle('active');
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        }

        hamburgerBtn.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        const navLinks = sidebar.querySelectorAll('.nav-item, .logout-btn');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 1024) {
                    toggleSidebar();
                }
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        });
    </script>
    <script>
document.addEventListener("DOMContentLoaded", function() {
  setTimeout(function(){
    var el = document.getElementById('loader-overlay');
    if(el) el.style.display = 'none';
  }, 800);  // Adjust if you want longer/shorter spinner
});
</script>
</body>
</html>
