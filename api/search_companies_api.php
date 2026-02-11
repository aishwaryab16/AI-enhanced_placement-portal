<?php
/**
 * AI-Powered Company Search API
 * Returns company recommendations based on user search query using OpenAI
 */

// Disable error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start output buffering to catch any accidental output
ob_start();

try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../openai_config.php';
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Configuration error: ' . $e->getMessage(), 'companies' => []]);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$search_query = $data['query'] ?? '';

if (empty($search_query)) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Search query is required', 'companies' => []]);
    exit;
}

// Optional: Try to fetch companies from database if available (for hybrid approach)
$all_companies = [];
$unique_companies = []; // Track unique company names to avoid duplicates

try {
    $mysqli = $GLOBALS['mysqli'] ?? null;
    
    if ($mysqli) {
        // Try companies table
        $result = @$mysqli->query("SELECT id, company_name, logo_url, industry, location, website, description, contact_person, contact_email, contact_phone, is_active FROM companies WHERE is_active = 1");
        if ($result) {
            $companies = $result->fetch_all(MYSQLI_ASSOC);
            foreach ($companies as $comp) {
                if (!isset($unique_companies[$comp['company_name']])) {
                    $unique_companies[$comp['company_name']] = true;
                    $all_companies[] = $comp;
                }
            }
        }
        
        // Try company_intelligence table
        $result = @$mysqli->query("SELECT id, company_name, NULL AS logo_url, industry, NULL AS location, NULL AS website, NULL AS description, NULL AS contact_person, NULL AS contact_email, NULL AS contact_phone, 1 AS is_active FROM company_intelligence");
        if ($result) {
            $companies = $result->fetch_all(MYSQLI_ASSOC);
            foreach ($companies as $comp) {
                if (!isset($unique_companies[$comp['company_name']])) {
                    $unique_companies[$comp['company_name']] = true;
                    $all_companies[] = $comp;
                }
            }
        }
    }
} catch (Exception $e) {
    // Database not available - will use AI-only mode
    error_log("Database not available for company search: " . $e->getMessage());
}

// Continue with AI generation even if database is empty
// This makes the system work without any database setup

// Check if OpenAI is configured
if (!isOpenAIConfigured()) {
    // Always show a curated default list when AI is not configured.
    // We deliberately DO NOT use database results here to ensure only AI-style
    // suggestions (or safe defaults) are shown.
    $default_companies = [
        ['name' => 'Google', 'industry' => 'Technology', 'description' => 'Leading technology company specializing in internet services and products', 'location' => 'Mountain View, CA', 'website' => 'https://www.google.com'],
        ['name' => 'Microsoft', 'industry' => 'Technology', 'description' => 'Multinational technology corporation producing computer software and hardware', 'location' => 'Redmond, WA', 'website' => 'https://www.microsoft.com'],
        ['name' => 'Amazon', 'industry' => 'E-commerce & Cloud', 'description' => 'E-commerce and cloud computing giant', 'location' => 'Seattle, WA', 'website' => 'https://www.amazon.com'],
        ['name' => 'Apple', 'industry' => 'Technology', 'description' => 'Consumer electronics and software company', 'location' => 'Cupertino, CA', 'website' => 'https://www.apple.com'],
        ['name' => 'Meta', 'industry' => 'Social Media', 'description' => 'Social media and technology conglomerate', 'location' => 'Menlo Park, CA', 'website' => 'https://www.meta.com'],
        ['name' => 'IBM', 'industry' => 'Technology', 'description' => 'Multinational technology and consulting corporation', 'location' => 'Armonk, NY', 'website' => 'https://www.ibm.com'],
        ['name' => 'Infosys', 'industry' => 'IT Services', 'description' => 'Global leader in consulting and technology services', 'location' => 'Bangalore, India', 'website' => 'https://www.infosys.com'],
        ['name' => 'TCS', 'industry' => 'IT Services', 'description' => 'IT services, consulting and business solutions', 'location' => 'Mumbai, India', 'website' => 'https://www.tcs.com'],
        ['name' => 'Wipro', 'industry' => 'IT Services', 'description' => 'Information technology and consulting services', 'location' => 'Bangalore, India', 'website' => 'https://www.wipro.com'],
        ['name' => 'Accenture', 'industry' => 'Consulting', 'description' => 'Professional services company specializing in IT and consulting', 'location' => 'Dublin, Ireland', 'website' => 'https://www.accenture.com']
    ];
    
    $filtered_defaults = [];
    foreach ($default_companies as $comp) {
        $filtered_defaults[] = [
            'id' => 0,
            'company_name' => $comp['name'],
            'logo_url' => null,
            'industry' => $comp['industry'],
            'location' => $comp['location'],
            'website' => $comp['website'],
            'description' => $comp['description'],
            'contact_person' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'is_active' => 1,
            'is_ai_suggested' => true
        ];
    }
    
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Showing suggested companies (AI not configured)', 'companies' => $filtered_defaults]);
    exit;
}

// Use OpenAI to find companies based on search query (works with or without database)
$api_key = getOpenAIKey();
$openai_endpoint = 'https://api.openai.com/v1/chat/completions';

// Prepare company data for AI (max 150 companies to avoid token limits, but let AI handle more intelligently)
$companies_for_ai = array_slice($all_companies, 0, 150);
$companies_list = [];
foreach ($companies_for_ai as $company) {
    $companies_list[] = [
        'name' => $company['company_name'],
        'industry' => $company['industry'] ?? 'General',
        'description' => substr($company['description'] ?? '', 0, 150)
    ];
}

// Create the prompt for OpenAI - AI generates companies dynamically
$db_context = count($companies_list) > 0 
    ? "Available Companies in Database (prioritize these if relevant):\n" . json_encode($companies_list, JSON_PRETTY_PRINT) . "\n\n" 
    : "No companies in database. Generate well-known companies that match the search criteria.\n\n";

$prompt = "You are a company information assistant. Generate a list of relevant companies based on the user's search query.

User Query: \"{$search_query}\"

{$db_context}Return ONLY a valid JSON array of 8-12 company objects with this exact structure:
[
  {
    \"name\": \"Company Name\",
    \"industry\": \"Industry/Sector\",
    \"description\": \"Brief 1-2 sentence description of the company and what they do\",
    \"in_database\": true or false,
    \"location\": \"City, Country or State\",
    \"website\": \"https://www.company.com\"
  }
]

Guidelines:
- Match companies to the search intent (e.g., 'tech companies' → technology companies, 'finance' → banks/financial institutions)
- Include well-known, reputable companies suitable for campus placements
- Provide accurate, real company information
- Include both global and Indian companies where relevant
- Ensure descriptions are informative and professional

Return ONLY the JSON array, no additional text or explanation.";

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
        ['role' => 'system', 'content' => 'You are a company matching assistant. Return only valid JSON arrays of company objects with the specified structure.'],
        ['role' => 'user', 'content' => $prompt]
    ],
    'temperature' => 0.3,
    'max_tokens' => 2000
]));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200 || !$response) {
    error_log("OpenAI API failed with HTTP code: $http_code");
    
    // Always return default popular companies when API fails
    $default_companies = [
        ['name' => 'Google', 'industry' => 'Technology', 'description' => 'Leading technology company specializing in internet services and products', 'location' => 'Mountain View, CA', 'website' => 'https://www.google.com'],
        ['name' => 'Microsoft', 'industry' => 'Technology', 'description' => 'Multinational technology corporation producing computer software and hardware', 'location' => 'Redmond, WA', 'website' => 'https://www.microsoft.com'],
        ['name' => 'Amazon', 'industry' => 'E-commerce & Cloud', 'description' => 'E-commerce and cloud computing giant', 'location' => 'Seattle, WA', 'website' => 'https://www.amazon.com'],
        ['name' => 'Apple', 'industry' => 'Technology', 'description' => 'Consumer electronics and software company', 'location' => 'Cupertino, CA', 'website' => 'https://www.apple.com'],
        ['name' => 'Meta', 'industry' => 'Social Media', 'description' => 'Social media and technology conglomerate', 'location' => 'Menlo Park, CA', 'website' => 'https://www.meta.com'],
        ['name' => 'IBM', 'industry' => 'Technology', 'description' => 'Multinational technology and consulting corporation', 'location' => 'Armonk, NY', 'website' => 'https://www.ibm.com'],
        ['name' => 'Infosys', 'industry' => 'IT Services', 'description' => 'Global leader in consulting and technology services', 'location' => 'Bangalore, India', 'website' => 'https://www.infosys.com'],
        ['name' => 'TCS', 'industry' => 'IT Services', 'description' => 'IT services, consulting and business solutions', 'location' => 'Mumbai, India', 'website' => 'https://www.tcs.com'],
        ['name' => 'Wipro', 'industry' => 'IT Services', 'description' => 'Information technology and consulting services', 'location' => 'Bangalore, India', 'website' => 'https://www.wipro.com'],
        ['name' => 'Accenture', 'industry' => 'Consulting', 'description' => 'Professional services company specializing in IT and consulting', 'location' => 'Dublin, Ireland', 'website' => 'https://www.accenture.com'],
        ['name' => 'Cognizant', 'industry' => 'IT Services', 'description' => 'IT services and consulting company', 'location' => 'Teaneck, NJ', 'website' => 'https://www.cognizant.com'],
        ['name' => 'Capgemini', 'industry' => 'Consulting', 'description' => 'Global consulting and technology services', 'location' => 'Paris, France', 'website' => 'https://www.capgemini.com']
    ];
    
    $filtered_defaults = [];
    foreach ($default_companies as $comp) {
        $filtered_defaults[] = [
            'id' => 0,
            'company_name' => $comp['name'],
            'logo_url' => null,
            'industry' => $comp['industry'],
            'location' => $comp['location'],
            'website' => $comp['website'],
            'description' => $comp['description'],
            'contact_person' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'is_active' => 1,
            'is_ai_suggested' => true
        ];
    }
    
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Showing popular companies (AI API temporarily unavailable)', 'companies' => $filtered_defaults]);
    exit;
}

// Parse AI response
$result = json_decode($response, true);
$ai_suggested_companies = [];

if (isset($result['choices'][0]['message']['content'])) {
    $content = $result['choices'][0]['message']['content'];
    
    // Extract JSON array from response
    preg_match('/\[.*\]/s', $content, $matches);
    if (isset($matches[0])) {
        $suggested_companies = json_decode($matches[0], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($suggested_companies)) {
            $ai_suggested_companies = $suggested_companies;
        }
    }
}

// Process AI suggestions only (do not replace with database entries)
$filtered_companies = [];
if (!empty($ai_suggested_companies)) {
    foreach ($ai_suggested_companies as $suggested) {
        $company_name = $suggested['name'] ?? '';
        $filtered_companies[] = [
            'id' => 0, // Always AI suggestion; no database ID
            'company_name' => $company_name,
            'logo_url' => null,
            'industry' => $suggested['industry'] ?? 'General',
            'location' => $suggested['location'] ?? '',
            'website' => $suggested['website'] ?? '',
            'description' => $suggested['description'] ?? '',
            'contact_person' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'is_active' => 1,
            'is_ai_suggested' => true
        ];
    }
}

// If no AI matches, provide a non-database fallback
if (empty($filtered_companies)) {
    $default_companies = [
        ['name' => 'Google', 'industry' => 'Technology', 'description' => 'Leading technology company specializing in internet services and products', 'location' => 'Mountain View, CA', 'website' => 'https://www.google.com'],
        ['name' => 'Microsoft', 'industry' => 'Technology', 'description' => 'Multinational technology corporation producing computer software and hardware', 'location' => 'Redmond, WA', 'website' => 'https://www.microsoft.com'],
        ['name' => 'Amazon', 'industry' => 'E-commerce & Cloud', 'description' => 'E-commerce and cloud computing giant', 'location' => 'Seattle, WA', 'website' => 'https://www.amazon.com']
    ];
    
    $filtered_defaults = [];
    foreach ($default_companies as $comp) {
        $filtered_defaults[] = [
            'id' => 0,
            'company_name' => $comp['name'],
            'logo_url' => null,
            'industry' => $comp['industry'],
            'location' => $comp['location'],
            'website' => $comp['website'],
            'description' => $comp['description'],
            'contact_person' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'is_active' => 1,
            'is_ai_suggested' => true
        ];
    }
    
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Showing popular companies', 'companies' => $filtered_defaults]);
    exit;
}

ob_end_clean();
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Companies found', 'companies' => $filtered_companies]);
exit;
?>

