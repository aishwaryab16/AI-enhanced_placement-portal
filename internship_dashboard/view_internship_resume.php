<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/internship_backend.php';
require_once __DIR__ . '/../openai_config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'placement_officer' && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'internship_admin')) {
    header('Location: ../login.php');
    exit;
}

$application_id = $_GET['id'] ?? 0;
if (!$application_id) {
    die('Invalid application ID');
}

// Fetch application details using username
$stmt = $mysqli->prepare("
    SELECT 
        ia.*,
        u.username,
        u.full_name,
        u.email,
        u.phone,
        u.permanent_address,
        u.temporary_address,
        u.bio,
        u.cgpa,
        u.branch,
        u.semester,
        u.interests
    FROM internship_applications ia
    LEFT JOIN users u ON u.username = ia.username
    WHERE ia.id = ?
");
$stmt->bind_param('i', $application_id);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$application) {
    die('Application not found');
}

$resume_html = '';

// Try to get resume_json from internship_applications first
$resume_json = $application['resume_json'] ?? '';

// If not in internship_applications, try to get from generated_resumes table
if (empty($resume_json) && !empty($application['username'])) {
    $resume_stmt = $mysqli->prepare("SELECT resume_json FROM generated_resumes WHERE username = ? LIMIT 1");
    if ($resume_stmt) {
        $resume_stmt->bind_param('s', $application['username']);
        $resume_stmt->execute();
        $resume_result = $resume_stmt->get_result();
        if ($resume_row = $resume_result->fetch_assoc()) {
            $resume_json = $resume_row['resume_json'] ?? '';
        }
        $resume_stmt->close();
    }
}

// Function to create resume prompt (copied from view_resume.php)
function createResumePromptForViewer($profile) {
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
    
    // Projects
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

// If we have resume_json, regenerate the HTML resume
if (!empty($resume_json)) {
    $student_profile = json_decode($resume_json, true);
    if ($student_profile) {
        // Regenerate HTML resume using the same function
        $api_key = getOpenAIKey();
        
        if ($api_key) {
            $openai_endpoint = 'https://api.openai.com/v1/chat/completions';
            
            // Create comprehensive prompt for resume generation
            $prompt = createResumePromptForViewer($student_profile);
            
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
            
            if ($http_code === 200 && $response) {
                $result = json_decode($response, true);
                if (isset($result['choices'][0]['message']['content'])) {
                    $content = $result['choices'][0]['message']['content'];
                    
                    // Extract HTML from markdown code blocks if present
                    if (preg_match('/```html\s*(.*?)\s*```/s', $content, $matches)) {
                        $resume_html = $matches[1];
                    } elseif (preg_match('/```\s*(.*?)\s*```/s', $content, $matches)) {
                        $resume_html = $matches[1];
                    } elseif (preg_match('/<html[^>]*>(.*?)<\/html>/is', $content, $matches)) {
                        $resume_html = $matches[0];
                    } elseif (preg_match('/<body[^>]*>(.*?)<\/body>/is', $content, $matches)) {
                        $resume_html = $matches[0];
                    } elseif (preg_match('/<[^>]+>/', $content)) {
                        $resume_html = $content;
                    }
                }
            }
        }
    }
}

// If still no HTML, show a message
if (empty($resume_html)) {
    $resume_html = '<div style="padding: 40px; text-align: center; color: #666;">
        <h2>Resume Not Available</h2>
        <p>The resume for this application is not available in the system.</p>
        <p>Please contact the student to upload their resume.</p>
    </div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Resume - <?php echo htmlspecialchars($application['full_name'] ?? 'Student'); ?></title>
    <link rel="stylesheet" href="../assets/css/fontawesome-all.min.css">
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

        .viewer-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .viewer-header h1 {
            color: #5b1f1f;
            font-size: 24px;
        }

        .viewer-header .student-info {
            color: #666;
            font-size: 14px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #5b1f1f;
            color: white;
        }

        .btn-primary:hover {
            background: #3d1414;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #1f2937;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .resume-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            min-height: 800px;
        }

        #resumeContent {
            width: 100%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        #resumeContent > div[style*="display:flex"] {
            width: 100%;
            max-width: 100%;
        }

        #resumeContent * {
            box-sizing: border-box;
        }

        @media print {
            body {
                background: white;
            }
            .viewer-header {
                display: none;
            }
            .resume-container {
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="viewer-header">
        <div>
            <h1><i class="fas fa-file-alt"></i> Resume Viewer</h1>
            <div class="student-info">
                <strong>Student:</strong> <?php echo htmlspecialchars($application['full_name'] ?? 'N/A'); ?> 
                (<?php echo htmlspecialchars($application['username'] ?? 'N/A'); ?>)
                | <strong>Company:</strong> <?php echo htmlspecialchars($application['company_name'] ?? 'N/A'); ?>
                | <strong>Position:</strong> <?php echo htmlspecialchars($application['internship_role'] ?? $application['internship_title'] ?? 'N/A'); ?>
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="track_internship_applications.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="resume-container">
        <div id="resumeContent">
            <?php echo $resume_html; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        // Optional: Add download PDF functionality
        function downloadPDF() {
            const { jsPDF } = window.jspdf;
            const resumeContent = document.getElementById('resumeContent');
            
            if (!resumeContent) {
                alert('Resume content not found');
                return;
            }

            html2canvas(resumeContent, {
                scale: 2,
                useCORS: true,
                logging: false,
                backgroundColor: '#ffffff'
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const pdfWidth = 210; // A4 width in mm
                const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
                
                const pdf = new jsPDF('p', 'mm', 'a4');
                pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
                
                const studentName = '<?php echo htmlspecialchars($application["full_name"] ?? "Resume"); ?>';
                const filename = studentName.replace(/\s+/g, '_') + '_Resume.pdf';
                pdf.save(filename);
            }).catch(error => {
                console.error('Error generating PDF:', error);
                alert('Error generating PDF. Please try again.');
            });
        }
    </script>
</body>
</html>

