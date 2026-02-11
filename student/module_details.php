<?php
require_once __DIR__ . '/../config.php';
require_role('student');

$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Get parameters
$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;
$company_name = isset($_GET['company_name']) ? trim($_GET['company_name']) : '';
$is_ai_suggested = isset($_GET['ai_suggested']) && $_GET['ai_suggested'] == '1';
$language = isset($_GET['language']) ? $_GET['language'] : '';
$module_id = isset($_GET['module_id']) ? intval($_GET['module_id']) : 0;
$module_title = isset($_GET['module_title']) ? $_GET['module_title'] : '';

if (($company_id <= 0 && empty($company_name)) || empty($language) || $module_id <= 0) {
    header('Location: resources.php');
    exit;
}

// Fetch company details (support AI-suggested)
$company = null;
if ($company_id > 0) {
    $stmt = $mysqli->prepare("SELECT id, company_name, industry FROM company_resources WHERE id = ? AND is_active = 1");
    $stmt->bind_param('i', $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $company = $result->fetch_assoc();

    if (!$company) {
        $stmt = $mysqli->prepare("SELECT id, company_name, industry FROM companies WHERE id = ? AND is_active = 1");
        $stmt->bind_param('i', $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $company = $result->fetch_assoc();
    }
} else {
    // Create virtual company using name
    $company = [
        'id' => 0,
        'company_name' => $company_name,
        'industry' => 'Technology'
    ];

    // Try to enrich from DB if company exists by name
    if (!empty($company_name)) {
        $stmt = $mysqli->prepare("SELECT id, company_name, industry FROM company_resources WHERE company_name = ? AND is_active = 1");
        $stmt->bind_param('s', $company_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $db_company = $result->fetch_assoc();
        if ($db_company) {
            $company = $db_company;
            $company_id = $company['id'];
        }
    }
}

if (!$company) {
    header('Location: resources.php');
    exit;
}

// AI API Configuration - Add your API key here
$AI_API_KEY = 'YOUR_OPENAI_API_KEY_HERE'; // TODO: Add your OpenAI/Anthropic API key here
$AI_API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

// Function to generate module content using AI
function generateModuleContent($language, $module_title, $company, $api_key, $api_endpoint) {
    if (empty($api_key)) {
        return [
            'error' => false,
            'content' => [
                'overview' => 'This module covers the fundamentals and key concepts. Configure AI API to get detailed, personalized explanations tailored to ' . $company['company_name'] . '\'s requirements.',
                'learning_objectives' => [
                    'Understand the core concepts and principles',
                    'Apply theoretical knowledge to practical scenarios',
                    'Develop problem-solving skills',
                    'Build foundational knowledge for advanced topics'
                ],
                'key_concepts' => [
                    [
                        'title' => 'Concept 1',
                        'description' => 'Fundamental principle that forms the foundation of this topic.',
                        'example' => 'Practical example demonstrating the concept in action.'
                    ],
                    [
                        'title' => 'Concept 2',
                        'description' => 'Important technique used in real-world applications.',
                        'example' => 'Industry-standard implementation example.'
                    ],
                    [
                        'title' => 'Concept 3',
                        'description' => 'Advanced pattern commonly used by professionals.',
                        'example' => 'Code snippet showing best practices.'
                    ]
                ],
                'practical_applications' => [
                    'Real-world use case in ' . $company['industry'],
                    'Common scenarios in professional development',
                    'Integration with existing systems'
                ],
                'resources' => [
                    'Official documentation and guides',
                    'Online tutorials and courses',
                    'Community forums and support',
                    'Books and reference materials'
                ],
                'next_steps' => 'After completing this module, you\'ll be ready to move on to more advanced topics and apply these concepts in practical projects.'
            ]
        ];
    }

    $prompt = "You are an expert software engineering educator and industry consultant with 15+ years of experience. Create comprehensive, advanced learning content for the module: '{$module_title}' in {$language} programming.

CONTEXT: This content is for a student preparing for technical interviews and career opportunities at {$company['company_name']} in the {$company['industry']} industry. The student needs industry-level knowledge, not just basic tutorials.

REQUIREMENTS:
1. Content must be ADVANCED and INDUSTRY-RELEVANT
2. Include real-world code examples and best practices
3. Focus on what professionals actually use in production
4. Include performance considerations and optimization techniques
5. Cover common interview questions and technical challenges
6. Provide actionable insights for career development

Generate detailed JSON with the following ENHANCED structure:
{
    \"overview\": \"Comprehensive 4-5 paragraph overview covering: what this module teaches, why it's important in the industry, how it applies to {$company['company_name']}'s tech stack, and what level of expertise students will achieve\",
    \"learning_objectives\": [
        \"Master advanced concepts and implementation patterns\",
        \"Apply industry best practices and design patterns\",
        \"Solve complex real-world problems using {$language}\",
        \"Optimize code for performance and scalability\",
        \"Prepare for technical interviews with confidence\"
    ],
    \"key_concepts\": [
        {
            \"title\": \"Advanced Concept Name\",
            \"description\": \"Detailed technical explanation (4-5 sentences) covering theory, implementation, and industry applications. Include performance implications and best practices.\",
            \"example\": \"Complete, production-ready code example with comments explaining each part, error handling, and optimization techniques\",
            \"interview_tips\": \"Common interview questions and how to answer them\",
            \"industry_usage\": \"How this concept is used at major tech companies\"
        }
    ],
    \"advanced_topics\": [
        \"Performance optimization techniques\",
        \"Memory management and garbage collection\",
        \"Concurrency and parallel processing\",
        \"Security considerations and best practices\",
        \"Testing strategies and quality assurance\"
    ],
    \"practical_projects\": [
        {
            \"title\": \"Project Name\",
            \"description\": \"Detailed project description with specific requirements\",
            \"technologies\": [\"List of technologies and frameworks used\"],
            \"learning_outcomes\": [\"What students will learn from this project\"],
            \"difficulty\": \"Beginner/Intermediate/Advanced\",
            \"estimated_time\": \"Time to complete\"
        }
    ],
    \"code_examples\": [
        {
            \"title\": \"Example Name\",
            \"description\": \"What this example demonstrates\",
            \"code\": \"Complete, well-commented code\",
            \"explanation\": \"Line-by-line explanation of the code\",
            \"variations\": \"Different ways to implement the same concept\"
        }
    ],
    \"interview_preparation\": {
        \"common_questions\": [
            \"Technical question with detailed answer\",
            \"Problem-solving scenario with solution approach\",
            \"System design question relevant to {$company['industry']}\"
        ],
        \"coding_challenges\": [
            \"Algorithm problem with complexity analysis\",
            \"Data structure implementation\",
            \"System optimization challenge\"
        ],
        \"best_practices\": [
            \"How to approach technical interviews\",
            \"Communication strategies\",
            \"Code review and debugging techniques\"
        ]
    },
    \"industry_insights\": {
        \"current_trends\": \"Latest trends in {$language} development and {$company['industry']}\",
        \"salary_expectations\": \"Typical salary ranges for {$language} developers\",
        \"career_paths\": \"Possible career progression paths\",
        \"company_specific\": \"How {$company['company_name']} uses {$language} in their tech stack\"
    },
    \"resources\": {
        \"official_docs\": \"Official documentation and API references\",
        \"advanced_tutorials\": \"High-quality tutorials for advanced topics\",
        \"books\": \"Recommended books for deep learning\",
        \"communities\": \"Professional communities and forums\",
        \"tools\": \"Development tools and IDEs\"
    },
    \"next_steps\": \"Detailed roadmap for continued learning, including specific modules to take next, projects to build, and skills to develop for career advancement\"
}

IMPORTANT: 
- Make content ADVANCED and PROFESSIONAL-GRADE
- Include specific code examples with explanations
- Focus on what's actually used in production environments
- Provide actionable career advice
- Ensure content is interview-ready
- Return ONLY valid JSON";

    $ch = curl_init($api_endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    
    $data = [
        'model' => 'gpt-4',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a senior software engineer and technical lead with 15+ years of experience at top tech companies. You specialize in creating advanced, industry-relevant educational content. Your responses are comprehensive, technically accurate, and focused on real-world applications. Always provide production-ready code examples and industry best practices. Return only valid JSON.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.3,
        'max_tokens' => 4000
    ];
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return [
            'error' => true,
            'message' => 'AI API request failed. Please check your API configuration.',
            'content' => []
        ];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        $content_text = $result['choices'][0]['message']['content'];
        $content = json_decode($content_text, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($content)) {
            return [
                'error' => false,
                'content' => $content
            ];
        }
    }
    
    return [
        'error' => true,
        'message' => 'Failed to parse AI response',
        'content' => []
    ];
}

$moduleContent = generateModuleContent($language, $module_title, $company, $AI_API_KEY, $AI_API_ENDPOINT);

?>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>

<style>
.module-details-container { max-width: 1000px; margin: 30px auto; padding: 20px; }
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    color: #666;
    flex-wrap: wrap;
}
.breadcrumb a {
    color: #5b1f1f;
    text-decoration: none;
    font-weight: 600;
}
.breadcrumb a:hover {
    text-decoration: underline;
}
.breadcrumb i {
    font-size: 12px;
    color: #999;
}
.module-details-header {
    background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
    color: white;
    padding: 40px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(91, 31, 31, 0.2);
}
.module-details-header h1 {
    font-size: 32px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
}
.module-icon {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #5b1f1f;
    font-size: 20px;
}
.section-title {
    font-size: 22px;
    font-weight: 700;
    color: #333;
}
.overview-text {
    color: #555;
    line-height: 1.8;
    font-size: 15px;
}
.objectives-list {
    list-style: none;
    padding: 0;
}
.objectives-list li {
    padding: 12px 0;
    padding-left: 35px;
    position: relative;
    color: #555;
    line-height: 1.6;
    border-bottom: 1px solid #f5f5f5;
}
.objectives-list li:last-child {
    border-bottom: none;
}
.objectives-list li::before {
    content: '\f00c';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    position: absolute;
    left: 0;
    color: #4caf50;
    font-size: 16px;
}
.concept-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    border-left: 4px solid #5b1f1f;
}
.concept-card:last-child {
    margin-bottom: 0;
}
.concept-title {
    font-size: 18px;
    font-weight: 700;
    color: #5b1f1f;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.concept-description {
    color: #555;
    line-height: 1.7;
    margin-bottom: 12px;
}
.concept-example {
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    color: #333;
    white-space: pre-wrap;
    margin-top: 10px;
}
.concept-example-label {
    font-size: 12px;
    font-weight: 600;
    color: #666;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.applications-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}
.application-item {
    background: linear-gradient(135deg, #f8f9fa, #ffffff);
    padding: 18px;
    border-radius: 10px;
    border: 2px solid #e9ecef;
    color: #555;
    line-height: 1.6;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.application-item i {
    color: #5b1f1f;
    font-size: 20px;
    margin-top: 2px;
}
.resources-list {
    list-style: none;
    padding: 0;
}
.resources-list li {
    padding: 15px;
    margin-bottom: 12px;
    background: #f8f9fa;
    border-radius: 10px;
    color: #555;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s;
}
.resources-list li:hover {
    background: #e9ecef;
    transform: translateX(5px);
}
.resources-list li i {
    color: #5b1f1f;
    font-size: 18px;
}
.next-steps-box {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    padding: 25px;
    border-radius: 12px;
    border-left: 4px solid #2196f3;
    color: #0d47a1;
    line-height: 1.8;
}
.placeholder-notice {
    background: #e3f2fd;
    border: 2px solid #2196f3;
    color: #0d47a1;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.placeholder-notice i {
    font-size: 24px;
}
.error-message {
    background: #fff3cd;
    border: 2px solid #ffc107;
    color: #856404;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.error-message i {
    font-size: 24px;
}
.navigation-buttons {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}
.btn-nav {
    flex: 1;
    padding: 15px 25px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    text-align: center;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}
.btn-back {
    background: #f8f9fa;
    color: #5b1f1f;
    border: 2px solid #5b1f1f;
}
.btn-back:hover {
    background: #5b1f1f;
    color: white;
}

/* New Section Styles */
.projects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.project-item {
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
}

.project-item:hover {
    border-color: #5b1f1f;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(91, 31, 31, 0.1);
}

.project-title {
    color: #5b1f1f;
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
}

.project-description {
    color: #666;
    line-height: 1.6;
    margin-bottom: 15px;
}

.project-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
}

.project-difficulty {
    background: #e3f2fd;
    color: #1976d2;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.project-time {
    background: #f3e5f5;
    color: #7b1fa2;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.project-technologies {
    color: #666;
    font-size: 14px;
}

.code-examples-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.code-example-item {
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
}

.code-example-item:hover {
    border-color: #5b1f1f;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(91, 31, 31, 0.1);
}

.example-title {
    color: #5b1f1f;
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
}

.example-description {
    color: #666;
    line-height: 1.6;
    margin-bottom: 15px;
}

.code-block {
    background: #2d3748;
    color: #e2e8f0;
    border-radius: 8px;
    padding: 15px;
    margin: 15px 0;
    overflow-x: auto;
}

.code-block pre {
    margin: 0;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    line-height: 1.5;
}

.code-explanation {
    background: #e8f5e9;
    border-left: 4px solid #4caf50;
    padding: 15px;
    margin-top: 15px;
    border-radius: 0 8px 8px 0;
}

.interview-content {
    margin-top: 20px;
}

.interview-section {
    margin-bottom: 30px;
}

.interview-section h3 {
    color: #5b1f1f;
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 15px;
}

.interview-list {
    list-style: none;
    padding: 0;
}

.interview-list li {
    background: #f8f9fa;
    border-left: 4px solid #5b1f1f;
    padding: 12px 15px;
    margin-bottom: 10px;
    border-radius: 0 8px 8px 0;
    transition: all 0.3s ease;
}

.interview-list li:hover {
    background: #e3f2fd;
    transform: translateX(5px);
}

@media (max-width: 768px) {
    .applications-grid {
        grid-template-columns: 1fr;
    }
    .navigation-buttons {
        flex-direction: column;
    }
    .projects-grid,
    .code-examples-grid {
        grid-template-columns: 1fr;
    }
    .code-block {
        font-size: 12px;
    }
}
</style>

<div class="module-details-container">
    <div class="breadcrumb">
        <a href="resources.php">Companies</a>
        <i class="fas fa-chevron-right"></i>
        <a href="company_details.php?<?php echo ($company_id > 0) ? ('id=' . $company_id) : ('name=' . urlencode($company['company_name']) . '&ai_suggested=1'); ?>"><?php echo htmlspecialchars($company['company_name']); ?></a>
        <i class="fas fa-chevron-right"></i>
        <a href="language_modules.php?company_id=<?php echo $company_id; ?>&company_name=<?php echo urlencode($company['company_name']); ?>&language=<?php echo urlencode($language); ?><?php echo ($company_id == 0) ? '&ai_suggested=1' : ''; ?>"><?php echo htmlspecialchars($language); ?></a>
        <i class="fas fa-chevron-right"></i>
        <span><?php echo htmlspecialchars($module_title); ?></span>
    </div>

    <div class="module-details-header">
        <h1>
            <div class="module-icon">
                <i class="fas fa-book-open"></i>
            </div>
            <span><?php echo htmlspecialchars($module_title); ?></span>
        </h1>
        <div style="opacity: 0.9;">Module for <?php echo htmlspecialchars($language); ?> Learning Path</div>
        <div class="ai-generated-badge">
            <i class="fas fa-magic"></i> AI Generated Content
        </div>
    </div>

    <?php if (empty($AI_API_KEY)): ?>
        <div class="placeholder-notice">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>AI Configuration Required:</strong> Add your API key to generate detailed, personalized module content. The content below is a generic placeholder.
            </div>
        </div>
    <?php endif; ?>

    <?php if ($moduleContent['error'] && !empty($AI_API_KEY)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Error:</strong> <?php echo htmlspecialchars($moduleContent['message']); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($moduleContent['content'])): ?>
        <!-- Overview Section -->
        <div class="content-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <h2 class="section-title">Module Overview</h2>
            </div>
            <div class="overview-text">
                <?php echo nl2br(htmlspecialchars($moduleContent['content']['overview'])); ?>
            </div>
        </div>

        <!-- Learning Objectives Section -->
        <div class="content-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-bullseye"></i>
                </div>
                <h2 class="section-title">Learning Objectives</h2>
            </div>
            <ul class="objectives-list">
                <?php foreach ($moduleContent['content']['learning_objectives'] as $objective): ?>
                    <li><?php echo htmlspecialchars($objective); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Key Concepts Section -->
        <div class="content-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <h2 class="section-title">Key Concepts</h2>
            </div>
            <?php foreach ($moduleContent['content']['key_concepts'] as $concept): ?>
                <div class="concept-card">
                    <div class="concept-title">
                        <i class="fas fa-star"></i>
                        <?php echo htmlspecialchars($concept['title']); ?>
                    </div>
                    <div class="concept-description">
                        <?php echo htmlspecialchars($concept['description']); ?>
                    </div>
                    <?php if (!empty($concept['example'])): ?>
                        <div class="concept-example-label">
                            <i class="fas fa-code"></i> Example:
                        </div>
                        <div class="concept-example"><?php echo htmlspecialchars($concept['example']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Practical Applications Section -->
        <div class="content-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <h2 class="section-title">Practical Applications</h2>
            </div>
            <div class="applications-grid">
                <?php 
                // Handle both old and new content structures
                $applications = [];
                if (isset($moduleContent['content']['practical_applications'])) {
                    $applications = $moduleContent['content']['practical_applications'];
                } elseif (isset($moduleContent['content']['practical_projects'])) {
                    foreach ($moduleContent['content']['practical_projects'] as $project) {
                        $applications[] = $project['title'] . ': ' . $project['description'];
                    }
                } elseif (isset($moduleContent['content']['advanced_topics'])) {
                    $applications = $moduleContent['content']['advanced_topics'];
                } else {
                    $applications = [
                        'Real-world use case in ' . $company['industry'],
                        'Common scenarios in professional development',
                        'Integration with existing systems'
                    ];
                }
                
                foreach ($applications as $application): ?>
                    <div class="application-item">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($application); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Practical Projects Section (if available) -->
        <?php if (isset($moduleContent['content']['practical_projects']) && is_array($moduleContent['content']['practical_projects'])): ?>
        <div class="content-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <h2 class="section-title">Practical Projects</h2>
            </div>
            <div class="projects-grid">
                <?php foreach ($moduleContent['content']['practical_projects'] as $project): ?>
                    <div class="project-item">
                        <h3 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h3>
                        <p class="project-description"><?php echo htmlspecialchars($project['description']); ?></p>
                        <div class="project-meta">
                            <span class="project-difficulty"><?php echo htmlspecialchars($project['difficulty']); ?></span>
                            <span class="project-time"><?php echo htmlspecialchars($project['estimated_time']); ?></span>
                        </div>
                        <?php if (isset($project['technologies'])): ?>
                        <div class="project-technologies">
                            <strong>Technologies:</strong> <?php echo htmlspecialchars(implode(', ', $project['technologies'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Code Examples Section (if available) -->
        <?php if (isset($moduleContent['content']['code_examples']) && is_array($moduleContent['content']['code_examples'])): ?>
        <div class="content-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-code"></i>
                </div>
                <h2 class="section-title">Code Examples</h2>
            </div>
            <div class="code-examples-grid">
                <?php foreach ($moduleContent['content']['code_examples'] as $example): ?>
                    <div class="code-example-item">
                        <h3 class="example-title"><?php echo htmlspecialchars($example['title']); ?></h3>
                        <p class="example-description"><?php echo htmlspecialchars($example['description']); ?></p>
                        <?php if (isset($example['code'])): ?>
                        <div class="code-block">
                            <pre><code><?php echo htmlspecialchars($example['code']); ?></code></pre>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($example['explanation'])): ?>
                        <div class="code-explanation">
                            <strong>Explanation:</strong> <?php echo htmlspecialchars($example['explanation']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Interview Preparation Section (if available) -->
        <?php if (isset($moduleContent['content']['interview_preparation'])): ?>
        <div class="content-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <h2 class="section-title">Interview Preparation</h2>
            </div>
            <div class="interview-content">
                <?php if (isset($moduleContent['content']['interview_preparation']['common_questions'])): ?>
                <div class="interview-section">
                    <h3>Common Questions</h3>
                    <ul class="interview-list">
                        <?php foreach ($moduleContent['content']['interview_preparation']['common_questions'] as $question): ?>
                            <li><?php echo htmlspecialchars($question); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (isset($moduleContent['content']['interview_preparation']['coding_challenges'])): ?>
                <div class="interview-section">
                    <h3>Coding Challenges</h3>
                    <ul class="interview-list">
                        <?php foreach ($moduleContent['content']['interview_preparation']['coding_challenges'] as $challenge): ?>
                            <li><?php echo htmlspecialchars($challenge); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Resources Section -->
        <div class="content-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-book"></i>
                </div>
                <h2 class="section-title">Recommended Resources</h2>
            </div>
            <ul class="resources-list">
                <?php 
                // Handle both old and new resource structures
                $resources = [];
                if (isset($moduleContent['content']['resources']) && is_array($moduleContent['content']['resources'])) {
                    $resources = $moduleContent['content']['resources'];
                } elseif (isset($moduleContent['content']['resources']) && is_object($moduleContent['content']['resources'])) {
                    // Handle new object structure
                    foreach ($moduleContent['content']['resources'] as $category => $resource) {
                        if (is_string($resource)) {
                            $resources[] = ucfirst($category) . ': ' . $resource;
                        } elseif (is_array($resource)) {
                            foreach ($resource as $item) {
                                $resources[] = $item;
                            }
                        }
                    }
                } else {
                    $resources = [
                        'Official documentation and API references',
                        'Advanced tutorials and courses',
                        'Professional communities and forums',
                        'Industry research and best practices'
                    ];
                }
                
                foreach ($resources as $resource): ?>
                    <li>
                        <i class="fas fa-external-link-alt"></i>
                        <?php echo htmlspecialchars($resource); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Next Steps Section -->
        <div class="content-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-arrow-right"></i>
                </div>
                <h2 class="section-title">Next Steps</h2>
            </div>
            <div class="next-steps-box">
                <strong><i class="fas fa-graduation-cap"></i> What's Next?</strong><br><br>
                <?php echo htmlspecialchars($moduleContent['content']['next_steps']); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="navigation-buttons">
        <a href="language_modules.php?company_id=<?php echo $company_id; ?>&company_name=<?php echo urlencode($company['company_name']); ?>&language=<?php echo urlencode($language); ?><?php echo ($company_id == 0) ? '&ai_suggested=1' : ''; ?>" class="btn-nav btn-back">
            <i class="fas fa-arrow-left"></i> Back to Modules
        </a>
    </div>
</div>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
    font-size: 28px;
}
.ai-generated-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    margin-top: 10px;
}
.content-section {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 25px;
}
.section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}
.section-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #f4e6c3, #ffe9a8);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;