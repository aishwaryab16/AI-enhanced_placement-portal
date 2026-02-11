<?php
/**
 * Resume Analysis API
 * Uses Google Gemini AI for intelligent resume analysis
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit();
}

// Configuration
define('GEMINI_API_KEY', 'AIzaSyAydm36D5YafWRCCpxYuL579P2R8CdkFbA');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent');

/**
 * Hardcore Resume Analyzer - No External API Required
 */
function analyzeResume($resumeText, $jobDescription = null) {
    $resume_lower = strtolower($resumeText);
    $word_count = str_word_count($resumeText);
    
    // Initialize analysis
    $strengths = [];
    $weaknesses = [];
    $suggestions = [];
    $score = 50;
    
    // Technical Skills Analysis
    $tech_skills = [
        'programming' => ['python', 'java', 'javascript', 'c++', 'php', 'ruby', 'go', 'rust', 'typescript', 'kotlin', 'swift', 'c#'],
        'web' => ['html', 'css', 'react', 'angular', 'vue', 'node', 'express', 'django', 'flask', 'laravel', 'spring'],
        'database' => ['sql', 'mysql', 'postgresql', 'mongodb', 'oracle', 'redis', 'firebase', 'dynamodb'],
        'cloud' => ['aws', 'azure', 'gcp', 'docker', 'kubernetes', 'jenkins', 'terraform', 'ansible'],
        'tools' => ['git', 'github', 'gitlab', 'jira', 'agile', 'scrum', 'ci/cd', 'devops']
    ];
    
    $skills_count = 0;
    foreach ($tech_skills as $category => $skills) {
        foreach ($skills as $skill) {
            if (stripos($resume_lower, $skill) !== false) {
                $skills_count++;
            }
        }
    }
    
    if ($skills_count >= 10) {
        $score += 15;
        $strengths[] = "Excellent technical skill set with {$skills_count} relevant technologies";
    } elseif ($skills_count >= 5) {
        $score += 10;
        $strengths[] = "Good range of technical skills ({$skills_count} technologies)";
    } else {
        $weaknesses[] = "Limited technical skills mentioned";
        $suggestions[] = "Add more relevant technical skills and tools";
    }
    
    // Contact Information
    $has_email = preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $resumeText);
    $has_phone = preg_match('/(\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', $resumeText);
    $has_linkedin = stripos($resume_lower, 'linkedin') !== false;
    $has_github = stripos($resume_lower, 'github') !== false;
    
    $contact_score = ($has_email ? 3 : 0) + ($has_phone ? 3 : 0) + ($has_linkedin ? 2 : 0) + ($has_github ? 2 : 0);
    $score += $contact_score;
    
    if ($contact_score >= 8) {
        $strengths[] = "Complete contact information with professional links";
    } else {
        $weaknesses[] = "Incomplete contact information";
        if (!$has_linkedin) $suggestions[] = "Add LinkedIn profile";
        if (!$has_github) $suggestions[] = "Include GitHub profile";
    }
    
    // Experience/Projects
    $has_experience = preg_match('/(experience|work history|employment)/i', $resumeText);
    $has_projects = preg_match('/(project|portfolio|built|developed|created)/i', $resumeText);
    $bullet_count = substr_count($resumeText, '•') + substr_count($resumeText, '-') + substr_count($resumeText, '*');
    
    if ($has_experience || $has_projects) {
        $score += 10;
        if ($bullet_count >= 8) {
            $score += 5;
            $strengths[] = "Well-structured experience with detailed descriptions";
        } else {
            $weaknesses[] = "Experience descriptions lack detail";
            $suggestions[] = "Use bullet points to describe achievements";
        }
    } else {
        $weaknesses[] = "No clear experience or projects section";
        $suggestions[] = "Add projects or experience section";
    }
    
    // Quantifiable Achievements
    if (preg_match('/(\d+%|\d+x|increased|decreased|improved|reduced|grew)/i', $resumeText)) {
        $score += 8;
        $strengths[] = "Includes quantifiable achievements and metrics";
    } else {
        $weaknesses[] = "Lacks quantifiable achievements";
        $suggestions[] = "Add metrics (e.g., 'Improved performance by 40%')";
    }
    
    // Education
    $has_education = preg_match('/(education|degree|bachelor|master|b\.tech|m\.tech|university|college)/i', $resumeText);
    if ($has_education) {
        $score += 5;
        if (preg_match('/(gpa|cgpa|percentage|\d\.\d+\/\d)/i', $resumeText)) {
            $score += 3;
            $strengths[] = "Education includes academic performance";
        }
    } else {
        $weaknesses[] = "Education section not clear";
        $suggestions[] = "Add education with degree and institution";
    }
    
    // Resume Length
    if ($word_count >= 200 && $word_count <= 600) {
        $score += 5;
        $strengths[] = "Appropriate resume length";
    } elseif ($word_count < 200) {
        $weaknesses[] = "Resume is too brief ({$word_count} words)";
        $suggestions[] = "Expand with more details";
    } else {
        $weaknesses[] = "Resume is too lengthy ({$word_count} words)";
        $suggestions[] = "Condense to 1-2 pages";
    }
    
    // Professional Summary
    if (preg_match('/(summary|objective|profile|about)/i', $resumeText)) {
        $score += 5;
        $strengths[] = "Includes professional summary";
    } else {
        $suggestions[] = "Add professional summary at top";
    }
    
    // Soft Skills
    $soft_skills = ['leadership', 'teamwork', 'communication', 'problem-solving', 'analytical'];
    $soft_count = 0;
    foreach ($soft_skills as $skill) {
        if (stripos($resume_lower, $skill) !== false) $soft_count++;
    }
    
    if ($soft_count >= 3) {
        $score += 5;
        $strengths[] = "Demonstrates soft skills";
    } else {
        $suggestions[] = "Highlight soft skills like teamwork and leadership";
    }
    
    // Certifications
    if (preg_match('/(certification|certified|certificate|course)/i', $resumeText)) {
        $score += 5;
        $strengths[] = "Includes certifications or training";
    }
    
    // Formatting
    if (preg_match_all('/(EXPERIENCE|EDUCATION|SKILLS|PROJECTS)/i', $resumeText) >= 3) {
        $score += 5;
        $strengths[] = "Well-organized with clear sections";
    } else {
        $weaknesses[] = "Lacks clear section organization";
    }
    
    // Job Description Matching
    if ($jobDescription) {
        $job_lower = strtolower($jobDescription);
        $match_count = 0;
        $job_words = preg_split('/\s+/', $job_lower);
        foreach ($job_words as $word) {
            if (strlen($word) > 4 && stripos($resume_lower, $word) !== false) {
                $match_count++;
            }
        }
        $match_percentage = min(100, ($match_count / max(1, count($job_words))) * 200);
        $score = ($score + $match_percentage) / 2;
        $strengths[] = "Resume matches {$match_percentage}% of job requirements";
    }
    
    $score = min(100, max(0, $score));
    
    // Ensure minimum items
    while (count($strengths) < 3) $strengths[] = "Professional presentation";
    while (count($weaknesses) < 3) $weaknesses[] = "Some sections could be more detailed";
    while (count($suggestions) < 3) $suggestions[] = "Proofread for errors";
    
    // Recommendation
    if ($score >= 85) $recommendation = 'Approve - Excellent Resume';
    elseif ($score >= 70) $recommendation = 'Approve with minor improvements';
    elseif ($score >= 55) $recommendation = 'Needs Improvement';
    else $recommendation = 'Reject - Significant improvements required';
    
    return [
        'score' => round($score),
        'strengths' => array_slice($strengths, 0, 5),
        'weaknesses' => array_slice($weaknesses, 0, 5),
        'suggestions' => array_slice($suggestions, 0, 5),
        'recommendation' => $recommendation
    ];
}

/**
 * Extract text from uploaded file
 */
function extractTextFromFile($file) {
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $tmpPath = $file['tmp_name'];
    
    switch ($fileType) {
        case 'txt':
            return file_get_contents($tmpPath);
            
        case 'pdf':
            // For PDF, we'll need a PDF parser library
            // For now, return instruction to use text or docx
            return null;
            
        case 'doc':
        case 'docx':
            // For Word documents, we'll need a library
            // For now, return instruction to use text
            return null;
            
        default:
            return null;
    }
}

// Main execution
try {
    $resumeText = null;
    $jobDescription = null;
    
    // Check if file was uploaded
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $resumeText = extractTextFromFile($_FILES['resume']);
        
        if (!$resumeText) {
            echo json_encode([
                'error' => 'Could not extract text from file. Please upload a .txt file or provide resume text directly.',
                'supported_formats' => ['txt']
            ]);
            exit();
        }
    }
    
    // Check for direct text input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$resumeText && isset($input['resume_text'])) {
        $resumeText = $input['resume_text'];
    }
    
    if (isset($input['job_description'])) {
        $jobDescription = $input['job_description'];
    }
    
    // Validate input
    if (empty($resumeText)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Resume text is required',
            'usage' => [
                'method' => 'POST',
                'content_type' => 'application/json',
                'body' => [
                    'resume_text' => 'Your resume content here',
                    'job_description' => 'Optional job description for matching'
                ]
            ]
        ]);
        exit();
    }
    
    // Perform analysis
    $analysis = analyzeResume($resumeText, $jobDescription);
    
    if (isset($analysis['error'])) {
        http_response_code(500);
        echo json_encode($analysis);
        exit();
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'analysis' => $analysis,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
