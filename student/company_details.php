<?php
require_once __DIR__ . '/../includes/config.php';
require_role('student');

$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Get company ID or name from URL
$company_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$company_name = isset($_GET['name']) ? trim($_GET['name']) : '';
$is_ai_suggested = isset($_GET['ai_suggested']) && $_GET['ai_suggested'] == '1';

// Fetch company details
$company = null;

// Handle AI-suggested companies (passed by name)
if ($is_ai_suggested && !empty($company_name)) {
    // Create a virtual company object for AI-suggested companies
    // We'll use company_id = 0 to indicate it's not in the database
    $company = [
        'id' => 0,
        'company_name' => $company_name,
        'logo_url' => null,
        'industry' => 'Technology', // Default, will be enriched by AI
        'location' => '',
        'website' => '',
        'description' => '',
        'contact_person' => null,
        'contact_email' => null,
        'contact_phone' => null,
        'is_active' => 1,
        'is_ai_suggested' => true
    ];
    
    // Try to find more info from database if it exists
    $stmt = $mysqli->prepare("SELECT id, company_name, logo_url, industry, location, website, description, contact_person, contact_email, contact_phone, is_active FROM company_resources WHERE company_name = ? AND is_active = 1");
    $stmt->bind_param('s', $company_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $db_company = $result->fetch_assoc();
    if ($db_company) {
        $company = $db_company; // Use database version if found
        $company_id = $company['id'];
    }
} elseif ($company_id > 0) {
    // Handle regular companies (passed by ID)
    $stmt = $mysqli->prepare("SELECT id, company_name, logo_url, industry, location, website, description, contact_person, contact_email, contact_phone, is_active FROM company_resources WHERE id = ? AND is_active = 1");
    $stmt->bind_param('i', $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $company = $result->fetch_assoc();

    if (!$company) {
        $stmt = $mysqli->prepare("SELECT id, company_name, logo_url, industry, location, website, description, contact_person, contact_email, contact_phone, is_active FROM companies WHERE id = ? AND is_active = 1");
        $stmt->bind_param('i', $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $company = $result->fetch_assoc();
    }

    if (!$company) {
        $stmt = $mysqli->prepare("SELECT id, company_name, NULL AS logo_url, industry, location, website, about_company AS description, NULL AS contact_person, NULL AS contact_email, NULL AS contact_phone, 1 AS is_active FROM company_intelligence WHERE id = ?");
        $stmt->bind_param('i', $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $company = $result->fetch_assoc();
    }
}

if (!$company) {
    header('Location: resources.php');
    exit;
}

// Set company_id for consistency (use 0 for AI-suggested companies not in DB)
if (!isset($company_id) || $company_id <= 0) {
    $company_id = $company['id'] ?? 0;
}

// ============ AI CONFIGURATION ============
define('OPENAI_API_KEY', 'YOUR_OPENAI_API_KEY_HERE');
define('OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions');

// Function to check if API is configured
function isAIConfigured() {
    return !empty(OPENAI_API_KEY) && OPENAI_API_KEY !== 'YOUR_OPENAI_API_KEY_HERE';
}

// Function to generate language requirements
function generateLanguageRequirements($company) {
    if (!isAIConfigured()) {
        return [
            'error' => true,
            'message' => 'AI API not configured. Please configure OpenAI API key to generate company-specific skills.',
            'languages' => []
        ];
    }

    $company_name = $company['company_name'] ?? 'Unknown Company';
    $industry = $company['industry'] ?? 'General';
    $description = !empty($company['description']) ? substr($company['description'], 0, 500) : '';
    $location = $company['location'] ?? '';
    
    $prompt = "Based on the following company information, generate a JSON array of 5-8 skills/technologies that are SPECIFIC to this company's domain and industry. These should be relevant skills that students need to work at this particular company.

Company Name: {$company_name}
Industry/Domain: {$industry}
Location: {$location}
Company Description: {$description}

IMPORTANT REQUIREMENTS:
1. Skills must be SPECIFIC to this company's industry/domain (e.g., if it's a finance company, include financial analysis tools, not just generic programming languages)
2. Include a mix of:
   - Technical skills (programming languages, frameworks, tools) relevant to their domain
   - Domain-specific skills (industry knowledge, specialized tools, certifications)
   - Company-specific technologies/platforms they likely use
3. Skills should reflect what this company actually needs, not generic technical skills
4. Consider the industry: Finance companies need different skills than Healthcare, Manufacturing, E-commerce, etc.

For each skill, provide:
1. name: The skill/technology name (e.g., 'Python', 'SAP ERP', 'Financial Modeling', 'AWS', 'HIPAA Compliance', 'Supply Chain Management')
2. level: Required proficiency level (Basic/Intermediate/Advanced)
3. priority: How critical it is for this company (High/Medium/Low)
4. description: A brief 1-2 sentence explanation of why this skill is important for THIS specific company and industry

Return ONLY a valid JSON array with no additional text or markdown formatting.";

    $ch = curl_init(OPENAI_ENDPOINT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a career advisor specializing in company-specific skill requirements. Generate skills that are tailored to each company\'s industry and domain. Return only valid JSON arrays with no markdown formatting or additional text.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.7,
        'max_tokens' => 1500
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return ['error' => true, 'message' => 'AI API request failed', 'languages' => []];
    }
    
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        $content = $result['choices'][0]['message']['content'];
        
        preg_match('/\[.*\]/s', $content, $matches);
        if (isset($matches[0])) {
            $languages = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($languages)) {
                return ['error' => false, 'languages' => $languages];
            }
        }
    }
    
    return ['error' => true, 'message' => 'Failed to parse AI response', 'languages' => []];
}

// Function to generate question bank summary
function generateQuestionBankSummary($language, $level, $company) {
    global $mysqli;
    
    // Check cache first
    $cacheStmt = $mysqli->prepare("SELECT questions_json FROM question_bank_cache WHERE company_id = ? AND language_name = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $cacheStmt->bind_param("is", $company['id'], $language);
    $cacheStmt->execute();
    $cacheResult = $cacheStmt->get_result();
    
    if ($cached = $cacheResult->fetch_assoc()) {
        $questions = json_decode($cached['questions_json'], true);
        return [
            'total_questions' => count($questions),
            'difficulty_breakdown' => [
                'Easy' => count(array_filter($questions, fn($q) => $q['difficulty'] === 'Easy')),
                'Medium' => count(array_filter($questions, fn($q) => $q['difficulty'] === 'Medium')),
                'Hard' => count(array_filter($questions, fn($q) => $q['difficulty'] === 'Hard'))
            ],
            'topics' => array_unique(array_column($questions, 'type'))
        ];
    }
    
    if (!isAIConfigured()) {
        return [
            'total_questions' => rand(18, 25),
            'difficulty_breakdown' => [
                'Easy' => rand(6, 10),
                'Medium' => rand(5, 10),
                'Hard' => rand(3, 8)
            ],
            'topics' => [
                'Fundamentals & Syntax',
                'Data Structures',
                'Algorithms',
                'Problem Solving',
                'Best Practices'
            ]
        ];
    }

    $prompt = "Generate a question bank summary for {$language} at {$level} level for {$company['company_name']} interviews.

Return ONLY valid JSON with:
{
  \"total_questions\": 20,
  \"difficulty_breakdown\": {\"Easy\": 7, \"Medium\": 8, \"Hard\": 5},
  \"topics\": [\"Fundamentals & Syntax\", \"Data Structures\", \"Algorithms\", \"Design Patterns\", \"Best Practices\"]
}";

    $ch = curl_init(OPENAI_ENDPOINT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => 'You are an expert at creating interview question banks. Return only valid JSON.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.7,
        'max_tokens' => 500
    ]));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) {
            $content = $data['choices'][0]['message']['content'];
            
            preg_match('/\{.*\}/s', $content, $matches);
            if (isset($matches[0])) {
                $qBank = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $qBank;
                }
            }
        }
    }
    
    return [
        'total_questions' => 20,
        'difficulty_breakdown' => ['Easy' => 7, 'Medium' => 8, 'Hard' => 5],
        'topics' => ['Fundamentals', 'Data Structures', 'Algorithms', 'Design Patterns']
    ];
}

// Generate data
$languageData = generateLanguageRequirements($company);
$questionBanks = [];
foreach ($languageData['languages'] as $lang) {
    $questionBanks[$lang['name']] = generateQuestionBankSummary($lang['name'], $lang['level'], $company);
}

?>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>

<style>
.details-container { max-width: 1200px; margin: 30px auto; padding: 20px; }
.back-link { 
    display: inline-flex; 
    align-items: center; 
    gap: 8px; 
    color: #5b1f1f; 
    text-decoration: none; 
    font-weight: 600; 
    margin-bottom: 20px;
    transition: all 0.3s;
}
.back-link:hover { 
    color: #8b3a3a; 
    transform: translateX(-4px);
}
.company-detail-header {
    background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
    color: white;
    padding: 40px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(91, 31, 31, 0.2);
}
.header-content {
    display: flex;
    gap: 30px;
    align-items: flex-start;
}
.company-detail-logo-wrapper {
    width: 120px;
    height: 120px;
    border-radius: 15px;
    overflow: hidden;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.company-detail-logo {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 15px;
}
.company-detail-logo-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 48px;
    color: #5b1f1f;
    background: linear-gradient(135deg, #f4e6c3, #ffe9a8);
}
.header-info { flex: 1; }
.company-detail-name { 
    font-size: 32px; 
    font-weight: 700; 
    margin-bottom: 8px; 
}
.company-detail-industry {
    font-size: 18px;
    opacity: 0.9;
    margin-bottom: 20px;
}
.company-meta {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
    margin-top: 20px;
}
.meta-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 15px;
}
.meta-item i { font-size: 18px; }
.meta-item a {
    color: white;
    text-decoration: none;
}
.meta-item a:hover { text-decoration: underline; }

.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}
.info-section {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.section-title {
    font-size: 22px;
    font-weight: 700;
    color: #5b1f1f;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.info-row {
    display: flex;
    gap: 12px;
    margin-bottom: 15px;
    align-items: flex-start;
}
.info-row i {
    color: #5b1f1f;
    width: 20px;
    margin-top: 3px;
}
.info-row-label {
    font-weight: 600;
    color: #333;
    min-width: 120px;
}
.info-row-value {
    color: #666;
    flex: 1;
}
.info-row-value a {
    color: #5b1f1f;
    text-decoration: none;
}
.info-row-value a:hover { text-decoration: underline; }
.description-section { grid-column: 1 / -1; }
.description-text {
    color: #555;
    line-height: 1.8;
    font-size: 15px;
}

.languages-section, .questions-section {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 30px;
}

.ai-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    margin-left: 10px;
}

.languages-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.language-card {
    background: linear-gradient(135deg, #f8f9fa, #ffffff);
    padding: 20px;
    border-radius: 12px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
    cursor: pointer;
}
.language-card:hover {
    border-color: #5b1f1f;
    transform: translateY(-4px);
    box-shadow: 0 6px 20px rgba(91, 31, 31, 0.15);
}
.language-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.language-name {
    font-size: 18px;
    font-weight: 700;
    color: #5b1f1f;
}
.priority-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}
.priority-high { background: #ffebee; color: #c62828; }
.priority-medium { background: #fff3e0; color: #ef6c00; }
.priority-low { background: #e8f5e9; color: #2e7d32; }

.language-level {
    font-size: 14px;
    color: #666;
    margin-bottom: 10px;
    font-weight: 600;
}
.language-description {
    font-size: 13px;
    color: #555;
    line-height: 1.6;
}

/* Question Bank Card Styles */
.question-bank-card {
    background: white;
    border: 2px solid #e3f2fd;
    border-radius: 12px;
    padding: 24px;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.question-bank-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.question-bank-card:hover::before {
    transform: scaleX(1);
}

.question-bank-card:hover {
    border-color: #667eea;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.2);
    transform: translateY(-4px);
}

.qb-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 18px;
    padding-bottom: 16px;
    border-bottom: 2px solid #f0f4ff;
}

.qb-title {
    font-size: 20px;
    font-weight: 700;
    color: #5b1f1f;
    margin-bottom: 6px;
}

.qb-subtitle {
    font-size: 13px;
    color: #999;
    font-weight: 600;
}

.qb-count {
    text-align: right;
}

.qb-count-number {
    font-size: 36px;
    font-weight: 700;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1;
}

.qb-count-label {
    font-size: 12px;
    color: #999;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.qb-stats {
    display: flex;
    gap: 12px;
    margin-bottom: 18px;
}

.qb-stat {
    flex: 1;
    background: #f8f9fa;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
    transition: all 0.3s ease;
}

.qb-stat:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.qb-stat-value {
    font-size: 20px;
    font-weight: 700;
    color: #333;
}

.qb-stat-label {
    font-size: 11px;
    color: #999;
    text-transform: uppercase;
    margin-top: 4px;
    font-weight: 600;
}

.qb-stat.easy .qb-stat-value { color: #2e7d32; }
.qb-stat.medium .qb-stat-value { color: #ef6c00; }
.qb-stat.hard .qb-stat-value { color: #c62828; }

.qb-topics {
    margin-bottom: 18px;
}

.qb-topics-title {
    font-size: 12px;
    color: #999;
    text-transform: uppercase;
    font-weight: 700;
    margin-bottom: 10px;
    letter-spacing: 0.5px;
}

.qb-topics-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.qb-topic-tag {
    background: #e3f2fd;
    color: #1565c0;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.2s ease;
}

.qb-topic-tag:hover {
    background: #1565c0;
    color: white;
}

.qb-action {
    text-align: center;
    padding-top: 18px;
    border-top: 2px solid #f0f4ff;
}

.qb-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s;
    width: 100%;
}

.qb-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}

.placeholder-notice {
    background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
    border: 2px solid #667eea;
    color: #0d47a1;
    padding: 15px 20px;
    border-radius: 10px;
    margin-top: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.placeholder-notice i {
    font-size: 24px;
    color: #667eea;
}

/* Loading Overlay */
.loading-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    backdrop-filter: blur(5px);
}

.loading-overlay.active {
    display: flex;
}

.loading-spinner {
    width: 60px;
    height: 60px;
    border: 5px solid rgba(255, 255, 255, 0.3);
    border-top-color: #ecc35c;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.loading-text {
    color: white;
    font-size: 18px;
    font-weight: 600;
    margin-top: 20px;
    text-align: center;
}

.loading-subtext {
    color: rgba(255, 255, 255, 0.8);
    font-size: 14px;
    margin-top: 8px;
    text-align: center;
}

@media (max-width: 768px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    .header-content {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    .company-meta {
        justify-content: center;
    }
    .languages-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="details-container">
    <a href="resources.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Companies
    </a>

    <div class="company-detail-header">
        <div class="header-content">
            <div class="company-detail-logo-wrapper">
                <?php if (!empty($company['logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($company['logo_url']); ?>" 
                         alt="<?php echo htmlspecialchars($company['company_name']); ?> logo" 
                         class="company-detail-logo"
                         onerror="this.parentElement.innerHTML='<div class=\'company-detail-logo-placeholder\'><?php echo htmlspecialchars(strtoupper(substr($company['company_name'],0,1))); ?></div>'" />
                <?php else: ?>
                    <div class="company-detail-logo-placeholder">
                        <?php echo htmlspecialchars(strtoupper(substr($company['company_name'],0,1))); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="header-info">
                <h1 class="company-detail-name"><?php echo htmlspecialchars($company['company_name']); ?></h1>
                <div class="company-detail-industry"><?php echo htmlspecialchars($company['industry'] ?? 'Technology'); ?></div>
                
                <div class="company-meta">
                    <?php if (!empty($company['location'])): ?>
                        <div class="meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($company['location']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($company['website'])): ?>
                        <div class="meta-item">
                            <i class="fas fa-globe"></i>
                            <a href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank">Visit Website</a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="meta-item">
                        <i class="fas fa-circle"></i>
                        <span><?php echo $company['is_active'] ? 'Active' : 'Inactive'; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-grid">
        <?php if (!empty($company['contact_person']) || !empty($company['contact_email']) || !empty($company['contact_phone'])): ?>
            <div class="info-section">
                <h2 class="section-title">
                    <i class="fas fa-address-card"></i> Contact Information
                </h2>
                
                <?php if (!empty($company['contact_person'])): ?>
                    <div class="info-row">
                        <i class="fas fa-user"></i>
                        <span class="info-row-label">Contact Person:</span>
                        <span class="info-row-value"><?php echo htmlspecialchars($company['contact_person']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($company['contact_email'])): ?>
                    <div class="info-row">
                        <i class="fas fa-envelope"></i>
                        <span class="info-row-label">Email:</span>
                        <span class="info-row-value">
                            <a href="mailto:<?php echo htmlspecialchars($company['contact_email']); ?>">
                                <?php echo htmlspecialchars($company['contact_email']); ?>
                            </a>
                        </span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($company['contact_phone'])): ?>
                    <div class="info-row">
                        <i class="fas fa-phone"></i>
                        <span class="info-row-label">Phone:</span>
                        <span class="info-row-value"><?php echo htmlspecialchars($company['contact_phone']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="info-section">
            <h2 class="section-title">
                <i class="fas fa-info-circle"></i> Company Details
            </h2>
            
            <div class="info-row">
                <i class="fas fa-industry"></i>
                <span class="info-row-label">Industry:</span>
                <span class="info-row-value"><?php echo htmlspecialchars($company['industry'] ?? 'Not specified'); ?></span>
            </div>
            
            <div class="info-row">
                <i class="fas fa-map-marker-alt"></i>
                <span class="info-row-label">Location:</span>
                <span class="info-row-value"><?php echo htmlspecialchars($company['location'] ?? 'Not specified'); ?></span>
            </div>
            
            <?php if (!empty($company['website'])): ?>
                <div class="info-row">
                    <i class="fas fa-link"></i>
                    <span class="info-row-label">Website:</span>
                    <span class="info-row-value">
                        <a href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank">
                            <?php echo htmlspecialchars($company['website']); ?>
                        </a>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($company['description'])): ?>
            <div class="info-section description-section">
                <h2 class="section-title">
                    <i class="fas fa-file-alt"></i> About Company
                </h2>
                <div class="description-text">
                    <?php echo nl2br(htmlspecialchars($company['description'])); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Required Skills Section (Domain & Company Specific) -->
    <div class="languages-section">
        <h2 class="section-title">
            <i class="fas fa-code"></i> Required Skills & Technologies
            <span class="ai-badge">
                <i class="fas fa-magic"></i> AI Generated
            </span>
        </h2>

        <?php if (!isAIConfigured() || (isset($languageData['error']) && $languageData['error'])): ?>
            <div class="placeholder-notice">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong><?php echo !isAIConfigured() ? 'AI Configuration Required:' : 'Unable to Generate Skills:'; ?></strong>
                    <?php 
                    if (!isAIConfigured()) {
                        echo 'Add your OpenAI API key to generate personalized company-specific and domain-specific skills. Edit line 80 in the PHP file.';
                    } else {
                        echo isset($languageData['message']) ? htmlspecialchars($languageData['message']) : 'Failed to generate company-specific skills. Please try again later.';
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!isset($languageData['error']) || !$languageData['error']): ?>
        <div class="languages-grid">
            <?php if (empty($languageData['languages'])): ?>
                <div class="placeholder-notice">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>No Skills Generated:</strong> Unable to fetch company-specific skills at this time. Please ensure the OpenAI API key is configured correctly.
                    </div>
                </div>
            <?php else: ?>
            <?php foreach ($languageData['languages'] as $lang): ?>
                <?php 
                // Link to student-level language modules page
                $modulesUrl = 'language_modules.php?company_id=' . $company_id . '&company_name=' . urlencode($company['company_name']) . '&language=' . urlencode($lang['name']) . '&level=' . urlencode($lang['level']);
                if ($company_id == 0) {
                    $modulesUrl .= '&ai_suggested=1';
                }
                ?>
                <div class="language-card" onclick="showLoading('<?php echo htmlspecialchars($lang['name'], ENT_QUOTES); ?>'); location.href='<?php echo $modulesUrl; ?>';">
                    <div class="language-header">
                        <div class="language-name"><?php echo htmlspecialchars($lang['name']); ?></div>
                        <div class="priority-badge priority-<?php echo strtolower($lang['priority']); ?>">
                            <?php echo htmlspecialchars($lang['priority']); ?>
                        </div>
                    </div>
                    <div class="language-level">
                        <i class="fas fa-signal"></i> <?php echo htmlspecialchars($lang['level']); ?> Level
                    </div>
                    <div class="language-description">
                        <?php echo htmlspecialchars($lang['description']); ?>
                    </div>
                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e9ecef; text-align: center;">
                        <span style="color: #5b1f1f; font-weight: 600; font-size: 13px;">
                            <i class="fas fa-book"></i> View Learning Path
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Question Banks Section -->
    <div class="questions-section">
        <h2 class="section-title">
            <i class="fas fa-question-circle"></i> Practice Question Banks
            <span class="ai-badge">
                <i class="fas fa-robot"></i> AI Curated
            </span>
        </h2>

        <?php if (!isAIConfigured() || (isset($languageData['error']) && $languageData['error'])): ?>
            <div class="placeholder-notice">
                <i class="fas fa-lightbulb"></i>
                <div>
                    <strong><?php echo !isAIConfigured() ? 'AI Configuration Required:' : 'Unable to Generate Questions:'; ?></strong>
                    <?php 
                    if (!isAIConfigured()) {
                        echo 'Configure OpenAI API key to generate company-specific interview questions tailored to each skill.';
                    } else {
                        echo 'Unable to generate question banks. Please ensure skills are generated first.';
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!isset($languageData['error']) || !$languageData['error']): ?>
        <div class="languages-grid">
            <?php if (empty($languageData['languages'])): ?>
                <div class="placeholder-notice">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>No Skills Available:</strong> Question banks require skills to be generated first. Please ensure the OpenAI API key is configured correctly.
                    </div>
                </div>
            <?php else: ?>
            <?php foreach ($languageData['languages'] as $lang): ?>
                <?php 
                    $langName = $lang['name'];
                    $qBank = isset($questionBanks[$langName]) ? $questionBanks[$langName] : null;
                    // Link to student-level question bank page
                    $questionsUrl = 'question_bank.php?company_id=' . $company_id . '&company_name=' . urlencode($company['company_name']) . '&language=' . urlencode($langName);
                    if ($company_id == 0) {
                        $questionsUrl .= '&ai_suggested=1';
                    }
                ?>
                
                <?php if ($qBank): ?>
                <div class="question-bank-card" onclick="location.href='<?php echo $questionsUrl; ?>';">
                    <div class="qb-header">
                        <div>
                            <div class="qb-title"><?php echo htmlspecialchars($langName); ?></div>
                            <div class="qb-subtitle">Interview Questions</div>
                        </div>
                        <div class="qb-count">
                            <div class="qb-count-number"><?php echo $qBank['total_questions']; ?></div>
                            <div class="qb-count-label">Questions</div>
                        </div>
                    </div>

                    <div class="qb-stats">
                        <div class="qb-stat easy">
                            <div class="qb-stat-value"><?php echo $qBank['difficulty_breakdown']['Easy']; ?></div>
                            <div class="qb-stat-label">Easy</div>
                        </div>
                        <div class="qb-stat medium">
                            <div class="qb-stat-value"><?php echo $qBank['difficulty_breakdown']['Medium']; ?></div>
                            <div class="qb-stat-label">Medium</div>
                        </div>
                        <div class="qb-stat hard">
                            <div class="qb-stat-value"><?php echo $qBank['difficulty_breakdown']['Hard']; ?></div>
                            <div class="qb-stat-label">Hard</div>
                        </div>
                    </div>

                    <div class="qb-topics">
                        <div class="qb-topics-title">Topics Covered</div>
                        <div class="qb-topics-list">
                            <?php foreach (array_slice($qBank['topics'], 0, 4) as $topic): ?>
                                <span class="qb-topic-tag"><?php echo htmlspecialchars($topic); ?></span>
                            <?php endforeach; ?>
                            <?php if (count($qBank['topics']) > 4): ?>
                                <span class="qb-topic-tag">+<?php echo count($qBank['topics']) - 4; ?> more</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="qb-action">
                        <a href="<?php echo $questionsUrl; ?>" class="qb-button" onclick="event.stopPropagation();">
                            <i class="fas fa-play-circle"></i> Start Practice
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-spinner"></div>
    <div class="loading-text">Loading...</div>
    <div class="loading-subtext" id="loadingSubtext">Please wait</div>
</div>

<script>
function showLoading(skillName) {
    const overlay = document.getElementById('loadingOverlay');
    const subtext = document.getElementById('loadingSubtext');
    if (overlay) {
        subtext.textContent = 'Loading ' + skillName + ' learning path...';
        overlay.classList.add('active');
    }
}

// Show loading on page unload (when navigating away)
window.addEventListener('beforeunload', function() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('active');
    }
});
</script>

<script>
// Smooth scroll behavior
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

// Add animation on scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

document.querySelectorAll('.language-card, .question-bank-card').forEach(card => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(card);
});
</script>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>



