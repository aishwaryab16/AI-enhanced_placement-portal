<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_role('student');

$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Get parameters
$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;
$company_name = isset($_GET['company_name']) ? trim($_GET['company_name']) : '';
$is_ai_suggested = isset($_GET['ai_suggested']) && $_GET['ai_suggested'] == '1';
$language = isset($_GET['language']) ? $_GET['language'] : '';
$level = isset($_GET['level']) ? $_GET['level'] : 'Intermediate';

if (($company_id <= 0 && empty($company_name)) || empty($language)) {
    header('Location: resources.php');
    exit;
}

// Fetch company details (support AI-suggested without DB id)
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
    // AI-suggested company passed by name
    $company = [
        'id' => 0,
        'company_name' => $company_name,
        'industry' => 'Technology',
    ];

    // Try to enrich from DB if available by name
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

// Function to generate learning modules using AI
function generateLearningModules($language, $level, $company, $api_key, $api_endpoint) {
    if (empty($api_key)) {
        return [
            'error' => false,
            'modules' => [
                [
                    'id' => 1,
                    'title' => 'Introduction to ' . $language,
                    'difficulty' => 'Basic',
                    'duration' => '2 hours',
                    'topics' => ['Syntax Basics', 'Variables', 'Data Types', 'Setup & Installation']
                ],
                [
                    'id' => 2,
                    'title' => 'Control Structures',
                    'difficulty' => 'Basic',
                    'duration' => '3 hours',
                    'topics' => ['If-Else', 'Loops', 'Switch Statements', 'Boolean Logic']
                ],
                [
                    'id' => 3,
                    'title' => 'Functions & Methods',
                    'difficulty' => 'Intermediate',
                    'duration' => '4 hours',
                    'topics' => ['Function Declaration', 'Parameters', 'Return Values', 'Scope']
                ],
                [
                    'id' => 4,
                    'title' => 'Data Structures',
                    'difficulty' => 'Intermediate',
                    'duration' => '5 hours',
                    'topics' => ['Arrays', 'Lists', 'Dictionaries', 'Sets']
                ],
                [
                    'id' => 5,
                    'title' => 'Object-Oriented Programming',
                    'difficulty' => 'Intermediate',
                    'duration' => '6 hours',
                    'topics' => ['Classes', 'Objects', 'Inheritance', 'Polymorphism']
                ],
                [
                    'id' => 6,
                    'title' => 'Advanced Concepts',
                    'difficulty' => 'Advanced',
                    'duration' => '8 hours',
                    'topics' => ['Design Patterns', 'Performance', 'Best Practices', 'Testing']
                ]
            ]
        ];
    }

    $prompt = "You are a senior software engineering curriculum designer with expertise in creating industry-relevant learning paths for top tech companies. Generate a comprehensive, advanced learning path for {$language} programming language at {$level} level.

CONTEXT: This learning path is for students preparing for technical interviews and career opportunities at {$company['company_name']} in the {$company['industry']} industry. The content must be industry-grade and interview-ready.

REQUIREMENTS:
1. Create 8-10 modules progressing from Basic to Advanced
2. Each module must be PRACTICAL and INDUSTRY-RELEVANT
3. Include topics that are actually used in production environments
4. Focus on skills that are tested in technical interviews
5. Consider the specific needs of {$company['industry']} industry
6. Include modern frameworks, tools, and best practices

Each module should include:
1. id: Sequential number (1, 2, 3, etc.)
2. title: Specific, descriptive module name
3. difficulty: 'Basic', 'Intermediate', or 'Advanced'
4. duration: Realistic learning time (e.g., '4-6 hours', '8-10 hours')
5. topics: Array of 5-7 specific, advanced topics
6. learning_outcomes: What students will achieve
7. prerequisites: Required knowledge before taking this module
8. industry_relevance: How this applies to {$company['company_name']} and {$company['industry']}

Return ONLY a valid JSON array. Example format:
[
    {
        \"id\": 1,
        \"title\": \"Advanced {$language} Fundamentals & Modern Syntax\",
        \"difficulty\": \"Basic\",
        \"duration\": \"4-6 hours\",
        \"topics\": [
            \"Modern syntax and ES6+ features\",
            \"Memory management and performance optimization\",
            \"Error handling and debugging techniques\",
            \"Code organization and modularity\",
            \"Development tools and environment setup\",
            \"Testing fundamentals and TDD principles\"
        ],
        \"learning_outcomes\": [
            \"Master modern {$language} syntax and best practices\",
            \"Understand memory management and performance implications\",
            \"Implement proper error handling and debugging strategies\"
        ],
        \"prerequisites\": \"Basic programming knowledge\",
        \"industry_relevance\": \"Essential foundation for all {$language} development at {$company['company_name']}\"
    }
]

IMPORTANT: 
- Make modules ADVANCED and PROFESSIONAL-GRADE
- Include modern frameworks and tools
- Focus on interview-relevant topics
- Ensure progression from basic to expert level
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
            ['role' => 'system', 'content' => 'You are a senior software engineering curriculum designer with 15+ years of experience creating learning paths for top tech companies like Google, Microsoft, and Amazon. You specialize in creating industry-relevant, interview-focused educational content. Your responses are comprehensive, technically accurate, and focused on real-world applications. Return only valid JSON arrays.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.3,
        'max_tokens' => 3000
    ];
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return [
            'error' => true,
            'message' => 'AI API request failed',
            'modules' => []
        ];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        $content = $result['choices'][0]['message']['content'];
        $modules = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($modules)) {
            return [
                'error' => false,
                'modules' => $modules
            ];
        }
    }
    
    return [
        'error' => true,
        'message' => 'Failed to parse AI response',
        'modules' => []
    ];
}

$moduleData = generateLearningModules($language, $level, $company, $AI_API_KEY, $AI_API_ENDPOINT);

?>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>

<style>
.modules-container { max-width: 1200px; margin: 30px auto; padding: 20px; }
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    color: #666;
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
.modules-header {
    background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
    color: white;
    padding: 40px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(91, 31, 31, 0.2);
}
.language-icon {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    margin-bottom: 20px;
}
.modules-header h1 {
    font-size: 32px;
    margin-bottom: 8px;
}
.modules-header-subtitle {
    font-size: 16px;
    opacity: 0.9;
}
.level-badge {
    display: inline-block;
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    margin-top: 15px;
}
.learning-path {
    position: relative;
    padding-left: 40px;
}
.learning-path::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, #5b1f1f, #8b3a3a, #5b1f1f);
}
.module-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border: 2px solid transparent;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}
.module-card:hover {
    border-color: #f4e6c3;
    transform: translateX(10px);
    box-shadow: 0 8px 24px rgba(91, 31, 31, 0.15);
}
.module-card::before {
    content: '';
    position: absolute;
    left: -28px;
    top: 30px;
    width: 16px;
    height: 16px;
    background: white;
    border: 3px solid #5b1f1f;
    border-radius: 50%;
    z-index: 1;
}
.module-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}
.module-number {
    background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    flex-shrink: 0;
}
.module-info {
    flex: 1;
    margin: 0 20px;
}
.module-title {
    font-size: 20px;
    font-weight: 700;
    color: #333;
    margin-bottom: 8px;
}
.module-meta {
    display: flex;
    gap: 20px;
    font-size: 13px;
    color: #666;
}
.module-meta span {
    display: flex;
    align-items: center;
    gap: 6px;
}
.difficulty-badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}
.difficulty-basic {
    background: #e8f5e9;
    color: #2e7d32;
}
.difficulty-intermediate {
    background: #fff3e0;
    color: #ef6c00;
}
.difficulty-advanced {
    background: #ffebee;
    color: #c62828;
}
.topics-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #f0f0f0;
}
.topic-tag {
    background: #f8f9fa;
    color: #555;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    border: 1px solid #e9ecef;
}
.click-hint {
    text-align: center;
    color: #5b1f1f;
    font-size: 13px;
    font-weight: 600;
    margin-top: 12px;
    opacity: 0;
    transition: opacity 0.3s;
}
.module-card:hover .click-hint {
    opacity: 1;
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
</style>

<div class="modules-container">
    <div class="breadcrumb">
        <a href="resources.php">Companies</a>
        <i class="fas fa-chevron-right"></i>
        <a href="company_details.php?<?php echo ($company_id > 0) ? ('id=' . $company_id) : ('name=' . urlencode($company['company_name']) . '&ai_suggested=1'); ?>"><?php echo htmlspecialchars($company['company_name']); ?></a>
        <i class="fas fa-chevron-right"></i>
        <span><?php echo htmlspecialchars($language); ?> Learning Path</span>
    </div>

    <div class="modules-header">
        <div class="language-icon">
            <i class="fas fa-code"></i>
        </div>
        <h1><?php echo htmlspecialchars($language); ?> Learning Path</h1>
        <div class="modules-header-subtitle">
            Curated for <?php echo htmlspecialchars($company['company_name']); ?> - <?php echo htmlspecialchars($company['industry']); ?>
        </div>
        <div class="level-badge">
            <i class="fas fa-signal"></i> Target Level: <?php echo htmlspecialchars($level); ?>
        </div>
    </div>

    <?php if (empty($AI_API_KEY)): ?>
        <div class="placeholder-notice">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>AI Configuration Required:</strong> Add your API key to generate personalized learning modules. The modules below are generic placeholders.
            </div>
        </div>
    <?php endif; ?>

    <?php if ($moduleData['error'] && !empty($AI_API_KEY)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Error:</strong> <?php echo htmlspecialchars($moduleData['message']); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="learning-path">
        <?php foreach ($moduleData['modules'] as $module): ?>
            <div class="module-card" onclick="window.location.href='module_details.php?company_id=<?php echo $company_id; ?>&company_name=<?php echo urlencode($company['company_name']); ?>&language=<?php echo urlencode($language); ?>&module_id=<?php echo $module['id']; ?>&module_title=<?php echo urlencode($module['title']); ?><?php echo ($company_id == 0 || $is_ai_suggested) ? '&ai_suggested=1' : ''; ?>';">
                <div class="module-header">
                    <div class="module-number"><?php echo $module['id']; ?></div>
                    <div class="module-info">
                        <div class="module-title"><?php echo htmlspecialchars($module['title']); ?></div>
                        <div class="module-meta">
                            <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($module['duration']); ?></span>
                            <span><i class="fas fa-list"></i> <?php echo count($module['topics']); ?> Topics</span>
                        </div>
                    </div>
                    <div class="difficulty-badge difficulty-<?php echo strtolower($module['difficulty']); ?>">
                        <?php echo htmlspecialchars($module['difficulty']); ?>
                    </div>
                </div>
                
                <div class="topics-list">
                    <?php foreach ($module['topics'] as $topic): ?>
                        <div class="topic-tag">
                            <i class="fas fa-check-circle" style="color: #5b1f1f; font-size: 11px;"></i>
                            <?php echo htmlspecialchars($topic); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="click-hint">
                    <i class="fas fa-arrow-right"></i> Click to view detailed explanation
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
