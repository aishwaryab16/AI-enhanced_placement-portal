<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../openai_config.php';
require_role('student');

$student_id = $_SESSION['user_id'];

// Fetch all student data
$stmt = $mysqli->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch academic data
$academic_data = null;
$result = $mysqli->query("SELECT * FROM resume_academic_data WHERE student_id = $student_id");
if ($result) $academic_data = $result->fetch_assoc();

// Fetch skills
$skills = [];
$result = $mysqli->query("SELECT * FROM student_skills WHERE student_id = $student_id ORDER BY proficiency_level DESC");
if ($result) $skills = $result->fetch_all(MYSQLI_ASSOC);

// Fetch projects by username (primary key for student_projects table)
$projects = [];
$username = $student['username'] ?? '';
if (!empty($username)) {
    $stmt = $mysqli->prepare("SELECT * FROM student_projects WHERE username = ? ORDER BY (is_ongoing=1) DESC, start_date DESC");
    if ($stmt) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

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

// Handle resume generation and editing
$generated_resume = null;
$error_message = '';
$success_message = '';
$is_edit_mode = false;

// Handle clearing session for new resume
if (isset($_GET['new']) && $_GET['new'] === '1') {
    unset($_SESSION['generated_resume']);
    header('Location: resume_builder.php');
    exit;
}

// Get saved resume from session if exists
if (isset($_SESSION['generated_resume'])) {
    $generated_resume = $_SESSION['generated_resume'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'generate_resume') {
        // Collect all student data for AI analysis
        $student_profile = [
            'personal' => [
                'name' => $student['full_name'] ?? '',
                'email' => $student['email'] ?? '',
                'phone' => $student['phone'] ?? '',
                'location' => $student['permanent_address'] ?? $student['temporary_address'] ?? '',
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
        
        // Generate resume using OpenAI
        $generated_resume = generateResumeWithAI($student_profile);
        
        if (!$generated_resume) {
            $error_message = 'Failed to generate resume. Please ensure your OpenAI API key is configured.';
            // Clear old resume if generation failed
            unset($_SESSION['generated_resume']);
        } else {
            // Save to session
            $_SESSION['generated_resume'] = $generated_resume;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'save_edited_resume') {
        // Save edited resume
        $edited_resume = $_POST['resume_content'] ?? '';
        if (!empty($edited_resume)) {
            $_SESSION['generated_resume'] = $edited_resume;
            $generated_resume = $edited_resume;
            $success_message = 'Resume saved successfully!';
        }
    }
}

// Check if edit mode is requested
if (isset($_GET['edit']) && $_GET['edit'] === '1') {
    $is_edit_mode = true;
}

/**
 * Generate resume using OpenAI
 * 
 * Note: To use this feature, configure your OpenAI API key in openai_config.php
 * The API key should be added in the openai_config.php file
 */
function generateResumeWithAI($profile) {
    $api_key = getOpenAIKey();
    
    if (!$api_key) {
        return null;
    }
    
    $openai_endpoint = 'https://api.openai.com/v1/chat/completions';
    
    // Create comprehensive prompt for resume generation
    $prompt = createResumePrompt($profile);
    
    $ch = curl_init($openai_endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => OPENAI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a professional resume writer. Generate a clean, professional, one-page resume in HTML format with a TWO-COLUMN LAYOUT. 

CRITICAL FORMAT REQUIREMENTS:

1. TWO-COLUMN STRUCTURE:
   - Left column: 30-35% width, white background
   - Right column: 65-70% width, white background
   - Use CSS display: flex or CSS grid for layout

2. LEFT COLUMN CONTENT (in this order):
   - Avatar/Initials Box: Large white square box with border, candidate initials (2 letters), decorative thin lines above/below
   - Contact Information: Location (pin icon), Phone (phone icon), Email (envelope icon) - each with small dark red icons
   - EDUCATION AND TRAINING section (bold uppercase title)
   - LANGUAGES section (bold uppercase title) with proficiency bars (dark red fill, light gray background) and levels (e.g., C2, B2)

3. RIGHT COLUMN CONTENT (in this order):
   - Name Header: Full name in large bold uppercase white text on dark red/maroon background bar (#5b1f1f or #8b3a3a)
   - SUMMARY section: Bold uppercase title, thin horizontal separator line below, paragraph text
   - SKILLS section: Bold uppercase title, thin horizontal separator, two-column bulleted list of skills
   - EXPERIENCE section: Bold uppercase title, thin horizontal separator, job entries with:
     * Job title (bold), Company | Location | Dates on same line
     * Bulleted list of achievements/responsibilities

4. DESIGN SPECIFICATIONS:
   - Color scheme: Dark red/maroon (#5b1f1f or #8b3a3a) for header and accents, white background, dark gray text (#333)
   - Font: Clean sans-serif (Arial, Helvetica, or similar)
   - Thin horizontal separator lines (1-2px, dark gray) between sections in right column
   - Generous white space between sections
   - All styling must be inline CSS
   - Print-friendly (8.5x11 inches)

5. HTML STRUCTURE:
   - Use div elements with inline styles
   - Start with a container div with display:flex
   - Left column div, Right column div
   - Semantic section organization

Return ONLY valid HTML code without markdown formatting, code blocks, or explanations. Start directly with <div> tags.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.3,
        'max_tokens' => 4000
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 || !$response) {
        return null;
    }
    
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        $content = $result['choices'][0]['message']['content'];
        
        // Extract HTML from markdown code blocks if present
        if (preg_match('/```html\s*(.*?)\s*```/s', $content, $matches)) {
            return $matches[1];
        } elseif (preg_match('/```\s*(.*?)\s*```/s', $content, $matches)) {
            return $matches[1];
        } elseif (preg_match('/<html[^>]*>(.*?)<\/html>/is', $content, $matches)) {
            return $matches[0];
        } elseif (preg_match('/<body[^>]*>(.*?)<\/body>/is', $content, $matches)) {
            return $matches[0];
        }
        
        // If content contains HTML tags, return it as is
        if (preg_match('/<[^>]+>/', $content)) {
            return $content;
        }
        
        return $content;
    }
    
    return null;
}

/**
 * Create prompt for OpenAI
 */
function createResumePrompt($profile) {
    $prompt = "Generate a professional, one-page resume in HTML format for the following candidate:\n\n";
    
    // Personal Information
    $prompt .= "PERSONAL INFORMATION:\n";
    $prompt .= "Name: " . ($profile['personal']['name'] ?? 'N/A') . "\n";
    $prompt .= "Email: " . ($profile['personal']['email'] ?? 'N/A') . "\n";
    $prompt .= "Phone: " . ($profile['personal']['phone'] ?? 'N/A') . "\n";
    $prompt .= "Location: " . ($profile['personal']['location'] ?? 'N/A') . "\n";
    if (!empty($profile['personal']['bio'])) {
        $prompt .= "Professional Summary: " . substr($profile['personal']['bio'], 0, 200) . "\n";
    }
    $prompt .= "\n";
    
    // Academic Information
    if ($profile['academic']) {
        $prompt .= "ACADEMIC QUALIFICATIONS:\n";
        $prompt .= "Degree: " . ($profile['academic']['degree'] ?? 'Bachelor of Engineering') . "\n";
        $prompt .= "Branch: " . ($profile['academic']['branch'] ?? $profile['academic']['specialization'] ?? 'N/A') . "\n";
        $prompt .= "CGPA: " . ($profile['academic']['cgpa'] ?? 'N/A') . "\n";
        $prompt .= "Semester: " . ($profile['academic']['semester'] ?? 'N/A') . "\n";
        $prompt .= "Roll Number: " . ($profile['academic']['roll_number'] ?? 'N/A') . "\n";
        $prompt .= "\n";
    }
    
    // Skills
    if (!empty($profile['skills'])) {
        $prompt .= "SKILLS:\n";
        foreach ($profile['skills'] as $skill) {
            $proficiency = '';
            if (isset($skill['proficiency'])) {
                if ($skill['proficiency'] >= 4) $proficiency = ' (Expert)';
                elseif ($skill['proficiency'] >= 3) $proficiency = ' (Advanced)';
                elseif ($skill['proficiency'] >= 2) $proficiency = ' (Intermediate)';
                else $proficiency = ' (Beginner)';
            }
            $prompt .= "- " . ($skill['name'] ?? '') . $proficiency . "\n";
        }
        $prompt .= "\n";
    }
    
    // Experience
    if (!empty($profile['experience'])) {
        $prompt .= "EXPERIENCE:\n";
        foreach ($profile['experience'] as $exp) {
            $prompt .= "Position: " . ($exp['job_title'] ?? $exp['position'] ?? 'N/A') . "\n";
            $prompt .= "Company: " . ($exp['company'] ?? $exp['company_name'] ?? 'N/A') . "\n";
            $prompt .= "Duration: " . ($exp['start_date'] ?? '') . " - " . (($exp['is_ongoing'] ?? $exp['is_current'] ?? false) ? 'Present' : ($exp['end_date'] ?? '')) . "\n";
            if (!empty($exp['description'])) {
                $prompt .= "Description: " . substr($exp['description'], 0, 150) . "\n";
            }
            $prompt .= "\n";
        }
    }
    
    // Projects (IMPORTANT for students)
    if (!empty($profile['projects'])) {
        $prompt .= "PROJECTS (IMPORTANT - Include all projects prominently):\n";
        foreach ($profile['projects'] as $project) {
            $prompt .= "Title: " . ($project['project_title'] ?? $project['title'] ?? 'N/A') . "\n";
            if (!empty($project['role'])) {
                $prompt .= "Role: " . $project['role'] . "\n";
            }
            if (!empty($project['technologies']) || !empty($project['skills_used'])) {
                $prompt .= "Technologies: " . ($project['technologies'] ?? $project['skills_used'] ?? '') . "\n";
            }
            if (!empty($project['description'])) {
                $prompt .= "Description: " . $project['description'] . "\n";
            }
            if (!empty($project['achievements'])) {
                $prompt .= "Achievements: " . $project['achievements'] . "\n";
            }
            if (!empty($project['start_date'])) {
                $prompt .= "Duration: " . $project['start_date'] . " - " . (($project['is_ongoing'] ?? false) ? 'Present' : ($project['end_date'] ?? 'Completed')) . "\n";
            }
            if (!empty($project['project_url'])) {
                $prompt .= "Project URL: " . $project['project_url'] . "\n";
            }
            if (!empty($project['github_url'])) {
                $prompt .= "GitHub: " . $project['github_url'] . "\n";
            }
            $prompt .= "\n";
        }
    }
    
    // Certifications
    if (!empty($profile['certifications'])) {
        $prompt .= "CERTIFICATIONS:\n";
        foreach ($profile['certifications'] as $cert) {
            $prompt .= "- " . ($cert['certification_name'] ?? '') . " from " . ($cert['issuing_organization'] ?? '') . "\n";
            if (!empty($cert['issue_date'])) {
                $prompt .= "  Issued: " . $cert['issue_date'] . "\n";
            }
        }
        $prompt .= "\n";
    }
    
    // Interests
    if (!empty($profile['interests'])) {
        $prompt .= "INTERESTS:\n";
        $prompt .= implode(', ', $profile['interests']) . "\n\n";
    }
    
    $prompt .= "\n\nCRITICAL FORMATTING INSTRUCTIONS:\n";
    $prompt .= "Generate a professional TWO-COLUMN resume layout in HTML.\n\n";
    
    $prompt .= "LEFT COLUMN (30-35% width) must contain:\n";
    $prompt .= "1. Initials/Avatar box: Extract first 2 letters from the candidate's name (e.g., 'John Doe' = 'JD'), display in large bold text in a white square box with thin dark gray border, add thin decorative lines above and below the initials\n";
    $prompt .= "2. Contact info: Location with pin icon (🔴), Phone with phone icon (🔴), Email with envelope icon (🔴) - icons should be small and dark red color, text in dark gray\n";
    $prompt .= "3. EDUCATION AND TRAINING section: Bold uppercase title 'EDUCATION AND TRAINING', then list education details\n";
    $prompt .= "4. LANGUAGES section: Bold uppercase title 'LANGUAGES', then list languages with proficiency bars (visual bars with dark red fill, light gray background) and CEFR levels like C2 (Proficient), B2 (Upper-intermediate), or 'Native speaker' - if no languages provided, default to: English (C2 - Proficient), Hindi (Native)\n\n";
    
    $prompt .= "RIGHT COLUMN (65-70% width) must contain:\n";
    $prompt .= "1. Name header bar: Full name in EXTRA LARGE BOLD UPPERCASE white text centered on a dark red background bar (#5b1f1f or #8b3a3a), this bar spans full width of right column\n";
    $prompt .= "2. SUMMARY section: Bold uppercase title 'SUMMARY', thin dark gray horizontal line separator below title, then write a 2-3 sentence professional summary highlighting:\n";
    $prompt .= "   - Candidate's professional focus/expertise\n";
    $prompt .= "   - Years of experience (if available)\n";
    $prompt .= "   - Key strengths and achievements\n";
    $prompt .= "   - Use professional language (e.g., 'Customer-focused professional', 'Demonstrated record of exceeding targets')\n";
    $prompt .= "3. SKILLS section: Bold uppercase title 'SKILLS', thin dark gray horizontal line separator, then organize skills in a TWO-COLUMN bulleted list (equal columns)\n";
    $prompt .= "4. EXPERIENCE section (if work experience provided): Bold uppercase title 'EXPERIENCE', thin dark gray horizontal line separator, then for each job:\n";
    $prompt .= "   - Job title in BOLD (e.g., 'SOFTWARE ENGINEER')\n";
    $prompt .= "   - Next line: Company name | Location | Date range (e.g., 'ABC Corp | City, Country | January 2020 - Present')\n";
    $prompt .= "   - Bulleted list of achievements/responsibilities (use • or -)\n";
    $prompt .= "5. PROJECTS section (MANDATORY if projects provided): Bold uppercase title 'PROJECTS' or 'EXPERIENCE' (if no work experience), thin separator, then for each project:\n";
    $prompt .= "   - Project title in BOLD (e.g., 'E-COMMERCE PLATFORM')\n";
    $prompt .= "   - Next line: Role | Technologies | Date range (e.g., 'Lead Developer | Python, React | March 2023 - Present')\n";
    $prompt .= "   - Bulleted list of key features, achievements, and impact\n";
    $prompt .= "   - IMPORTANT: Projects are critical for students - give them equal prominence as work experience\n";
    $prompt .= "6. CERTIFICATIONS section (if provided): Bold uppercase title 'CERTIFICATIONS', thin separator, list certifications with issuer and date\n\n";
    
    $prompt .= "Design Requirements:\n";
    $prompt .= "- Dark red/maroon (#5b1f1f or #8b3a3a) for header and accent icons\n";
    $prompt .= "- White background throughout\n";
    $prompt .= "- Dark gray text (#333 or #222)\n";
    $prompt .= "- Thin horizontal lines (1-2px, dark gray) as section separators in right column\n";
    $prompt .= "- Clean sans-serif font (Arial, Helvetica, Segoe UI)\n";
    $prompt .= "- Generous white space between sections\n";
    $prompt .= "- All CSS must be inline\n";
    $prompt .= "- Use display:flex for two-column layout\n";
    $prompt .= "- Fit on one 8.5x11 inch page\n\n";
    
    $prompt .= "Return ONLY the complete HTML code starting with <div style=\"display:flex;\"> - NO markdown, NO code blocks, NO explanations.";
    
    return $prompt;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic Resume Builder - AI Powered</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(91, 31, 31, 0.2);
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header p {
            opacity: 0.9;
            font-size: 15px;
        }

        .ai-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            margin-left: 10px;
        }

        .control-panel {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(91, 31, 31, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .resume-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            min-height: 800px;
        }

        .loading {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .loading i {
            font-size: 48px;
            color: #5b1f1f;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #ef4444;
            margin-bottom: 20px;
        }

        .info-message {
            background: #e0f2fe;
            color: #0c4a6e;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #0ea5e9;
            margin-bottom: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        #resumeContent {
            width: 100%;
            page-break-after: always;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        /* Ensure two-column layout works properly */
        #resumeContent > div[style*="display:flex"] {
            width: 100%;
            max-width: 100%;
        }

        /* Default styling for generated resume content */
        #resumeContent * {
            box-sizing: border-box;
        }
        
        /* Ensure proper spacing */
        #resumeContent section,
        #resumeContent div[style*="margin"] {
            margin-bottom: 15px;
        }

        /* Edit Mode Styles */
        #resumeContent[contenteditable="true"] {
            outline: 2px solid #0ea5e9 !important;
            outline-offset: 2px;
        }
        
        #resumeContent[contenteditable="true"]:focus {
            outline-color: #5b1f1f;
            box-shadow: 0 0 0 3px rgba(91, 31, 31, 0.1);
        }
        
        #resumeContent[contenteditable="true"] * {
            cursor: text;
        }
        
        #resumeContent[contenteditable="true"] img,
        #resumeContent[contenteditable="true"] svg {
            cursor: pointer;
        }

        #htmlEditor textarea {
            font-family: 'Courier New', 'Consolas', monospace;
            line-height: 1.6;
        }

        .edit-mode-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        /* Ensure print compatibility */
        @media print {
            body {
                background: white;
            }
            .header, .control-panel {
                display: none;
            }
            .resume-container {
                box-shadow: none;
                padding: 0;
            }
            #resumeContent {
                page-break-inside: avoid;
            }
            #resumeContent[contenteditable="true"] {
                outline: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-file-alt"></i> Dynamic Resume Builder
                <span class="ai-badge"><i class="fas fa-magic"></i> AI Powered</span>
            </h1>
            <p>Generate a professional, one-page resume using AI analysis of your profile</p>
        </div>

        <div class="control-panel">
            <?php if (!isOpenAIConfigured()): ?>
                <div class="info-message">
                    <i class="fas fa-info-circle"></i>
                    <strong>API Key Required:</strong> Please configure your OpenAI API key in <code>openai_config.php</code> to generate resumes.
                </div>
            <?php endif; ?>

            <form method="POST" id="generateForm">
                <input type="hidden" name="action" value="generate_resume">
                <button type="submit" class="btn btn-primary" id="generateBtn" <?php echo !isOpenAIConfigured() ? 'disabled' : ''; ?>>
                    <i class="fas fa-magic"></i> Generate Resume
                </button>
            </form>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="success-message" style="background: #d1fae5; color: #065f46; padding: 20px; border-radius: 10px; border-left: 4px solid #10b981; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <div class="resume-container">
            <?php if ($generated_resume): ?>
                <?php if ($is_edit_mode): ?>
                    <!-- Edit Mode -->
                    <div style="margin-bottom: 20px;">
                        <div style="background: #e0f2fe; color: #0c4a6e; padding: 15px; border-radius: 10px; border-left: 4px solid #0ea5e9; margin-bottom: 20px;">
                            <i class="fas fa-info-circle"></i> <strong>Edit Mode:</strong> You can now edit your resume. Use the visual editor or HTML editor below.
                        </div>
                        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                            <button onclick="switchEditMode('visual')" class="btn btn-secondary" id="visualEditBtn">
                                <i class="fas fa-eye"></i> Visual Editor
                            </button>
                            <button onclick="switchEditMode('html')" class="btn btn-secondary" id="htmlEditBtn">
                                <i class="fas fa-code"></i> HTML Editor
                            </button>
                        </div>
                    </div>
                    
                    <!-- Visual Editor (ContentEditable) -->
                    <div id="visualEditor" style="display: block;">
                        <div id="resumeContent" contenteditable="true" style="min-height: 800px; padding: 20px; border: 2px solid #e5e7eb; border-radius: 10px; background: white; outline: 2px solid #0ea5e9;">
                            <?php echo $generated_resume; ?>
                        </div>
                    </div>
                    
                    <!-- HTML Editor (Textarea) -->
                    <div id="htmlEditor" style="display: none;">
                        <textarea id="resumeHtmlContent" style="width: 100%; min-height: 800px; padding: 15px; border: 2px solid #e5e7eb; border-radius: 10px; font-family: 'Courier New', monospace; font-size: 13px;"><?php echo htmlspecialchars($generated_resume); ?></textarea>
                    </div>
                    
                    <div style="margin-top: 30px; text-align: center; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                        <button onclick="saveEditedResume()" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button onclick="cancelEdit()" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button onclick="downloadPDF()" class="btn btn-success">
                            <i class="fas fa-download"></i> Download as PDF
                        </button>
                    </div>
                <?php else: ?>
                    <!-- View Mode -->
                    <div id="resumeContent">
                        <?php echo $generated_resume; ?>
                    </div>
                    <div style="margin-top: 30px; text-align: center; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                        <button onclick="window.location.href='?edit=1'" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Resume
                        </button>
                        <button onclick="downloadPDF()" class="btn btn-success">
                            <i class="fas fa-download"></i> Download as PDF
                        </button>
                        <button onclick="window.print()" class="btn btn-secondary">
                            <i class="fas fa-print"></i> Print Resume
                        </button>
                        <button onclick="generateNewResume()" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Generate New
                        </button>
                    </div>
                <?php endif; ?>
            <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_resume'): ?>
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Generating your resume...</p>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <p style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Ready to Generate</p>
                    <p>Click "Generate Resume" to create a professional resume based on your profile data.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Show loading state on form submit
        document.getElementById('generateForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('generateBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            }
        });

        // Download PDF function
        async function downloadPDF() {
            const { jsPDF } = window.jspdf;
            let resumeContent = document.getElementById('resumeContent');
            
            // If in edit mode with HTML editor, sync to visual editor first
            if (typeof currentEditMode !== 'undefined' && currentEditMode === 'html') {
                const htmlContent = document.getElementById('resumeHtmlContent');
                if (htmlContent && resumeContent) {
                    resumeContent.innerHTML = htmlContent.value;
                    // Wait a moment for rendering
                    await new Promise(resolve => setTimeout(resolve, 100));
                }
            }
            
            if (!resumeContent) {
                alert('Resume content not found');
                return;
            }

            try {
                // Show loading
                const loadingToast = document.createElement('div');
                loadingToast.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 10000; text-align: center;';
                loadingToast.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #5b1f1f; margin-bottom: 10px;"></i><p>Generating PDF...</p>';
                document.body.appendChild(loadingToast);

                // Temporarily hide edit mode UI if in edit mode
                const htmlEditor = document.getElementById('htmlEditor');
                if (htmlEditor) {
                    htmlEditor.style.display = 'none';
                }

                // Use html2canvas to capture the resume
                const canvas = await html2canvas(resumeContent, {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    backgroundColor: '#ffffff'
                });

                const imgData = canvas.toDataURL('image/png');
                
                // Calculate PDF dimensions (A4 size)
                const pdfWidth = 210; // A4 width in mm
                const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
                
                const pdf = new jsPDF('p', 'mm', 'a4');
                pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
                
                // Generate filename
                const studentName = '<?php echo htmlspecialchars($student["full_name"] ?? "Resume"); ?>';
                const filename = studentName.replace(/\s+/g, '_') + '_Resume.pdf';
                
                pdf.save(filename);
                
                // Remove loading toast
                loadingToast.remove();
            } catch (error) {
                console.error('Error generating PDF:', error);
                alert('Error generating PDF. Please try again.');
            }
        }

        // Edit Mode Functions
        let currentEditMode = 'visual'; // Default to visual editor
        
        // Initialize edit mode button states if in edit mode
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($is_edit_mode): ?>
            // In edit mode, start with visual editor
            if (document.getElementById('visualEditBtn')) {
                switchEditMode('visual');
            }
            <?php endif; ?>
        });
        
        function switchEditMode(mode) {
            currentEditMode = mode;
            const visualEditor = document.getElementById('visualEditor');
            const htmlEditor = document.getElementById('htmlEditor');
            const visualBtn = document.getElementById('visualEditBtn');
            const htmlBtn = document.getElementById('htmlEditBtn');
            
            if (mode === 'visual') {
                visualEditor.style.display = 'block';
                htmlEditor.style.display = 'none';
                
                // Sync content from HTML editor to visual editor when switching to visual
                const resumeContent = document.getElementById('resumeContent');
                const htmlContent = document.getElementById('resumeHtmlContent');
                if (resumeContent && htmlContent && htmlContent.value) {
                    resumeContent.innerHTML = htmlContent.value;
                }
                
                if (visualBtn) {
                    visualBtn.style.background = 'linear-gradient(135deg, #5b1f1f, #8b3a3a)';
                    visualBtn.style.color = 'white';
                }
                if (htmlBtn) {
                    htmlBtn.style.background = '#f3f4f6';
                    htmlBtn.style.color = '#374151';
                }
            } else {
                visualEditor.style.display = 'none';
                htmlEditor.style.display = 'block';
                
                // Sync content from visual to HTML editor
                const resumeContent = document.getElementById('resumeContent');
                const htmlContent = document.getElementById('resumeHtmlContent');
                if (resumeContent && htmlContent) {
                    htmlContent.value = resumeContent.innerHTML;
                }
                
                if (htmlBtn) {
                    htmlBtn.style.background = 'linear-gradient(135deg, #5b1f1f, #8b3a3a)';
                    htmlBtn.style.color = 'white';
                }
                if (visualBtn) {
                    visualBtn.style.background = '#f3f4f6';
                    visualBtn.style.color = '#374151';
                }
            }
        }

        function saveEditedResume() {
            let resumeContent = '';
            
            if (currentEditMode === 'visual') {
                const resumeContentEl = document.getElementById('resumeContent');
                if (resumeContentEl) {
                    resumeContent = resumeContentEl.innerHTML;
                }
            } else {
                const htmlContent = document.getElementById('resumeHtmlContent');
                if (htmlContent) {
                    resumeContent = htmlContent.value;
                }
            }
            
            if (!resumeContent.trim()) {
                alert('Resume content is empty. Please add some content.');
                return;
            }
            
            // Show loading
            const loadingToast = document.createElement('div');
            loadingToast.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 10000; text-align: center;';
            loadingToast.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #5b1f1f; margin-bottom: 10px;"></i><p>Saving resume...</p>';
            document.body.appendChild(loadingToast);
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'save_edited_resume';
            form.appendChild(actionInput);
            
            const contentInput = document.createElement('input');
            contentInput.type = 'hidden';
            contentInput.name = 'resume_content';
            contentInput.value = resumeContent;
            form.appendChild(contentInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        function cancelEdit() {
            if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
                window.location.href = 'resume_builder.php';
            }
        }

        function generateNewResume() {
            if (confirm('This will clear your current resume. Are you sure you want to generate a new one?')) {
                window.location.href = 'resume_builder.php?new=1';
            }
        }

        // Sync HTML editor content to visual editor when switching back
        document.addEventListener('DOMContentLoaded', function() {
            const htmlContent = document.getElementById('resumeHtmlContent');
            if (htmlContent) {
                htmlContent.addEventListener('blur', function() {
                    // Update visual editor when HTML is edited
                    if (currentEditMode === 'html') {
                        const resumeContent = document.getElementById('resumeContent');
                        if (resumeContent) {
                            resumeContent.innerHTML = this.value;
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>

