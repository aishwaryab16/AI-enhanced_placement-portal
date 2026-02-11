<?php
/**
 * AI-Powered Company Search API
 * Returns company recommendations based on user search query using OpenAI
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/openai_config.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$search_query = $data['query'] ?? '';

if (empty($search_query)) {
    echo json_encode(['success' => false, 'message' => 'Search query is required', 'companies' => []]);
    exit;
}

$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Fetch all companies from database (all sources)
$all_companies = [];
$unique_companies = []; // Track unique company names to avoid duplicates

// Try company_resources table first
$result = $mysqli->query("SELECT id, company_name, logo_url, industry, location, website, description, contact_person, contact_email, contact_phone, is_active FROM company_resources WHERE is_active = 1");
if ($result) {
    $companies = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($companies as $comp) {
        if (!isset($unique_companies[$comp['company_name']])) {
            $unique_companies[$comp['company_name']] = true;
            $all_companies[] = $comp;
        }
    }
}

// Try companies table (from setup_placement_system)
$result = $mysqli->query("SELECT id, company_name, logo_url, industry, location, website, description, contact_person, contact_email, contact_phone, is_active FROM companies WHERE is_active = 1");
if ($result) {
    $companies = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($companies as $comp) {
        if (!isset($unique_companies[$comp['company_name']])) {
            $unique_companies[$comp['company_name']] = true;
            $all_companies[] = $comp;
        }
    }
}

// Try companies table (from setup_placement_cell with different schema)
$result = $mysqli->query("SELECT id, company_name, logo_url, industry, company_size AS location, company_website AS website, about_company AS description, NULL AS contact_person, company_email AS contact_email, company_phone AS contact_phone, 
    CASE WHEN status = 'active' THEN 1 ELSE 0 END AS is_active 
    FROM companies WHERE status = 'active'");
if ($result) {
    $companies = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($companies as $comp) {
        if (!isset($unique_companies[$comp['company_name']])) {
            $unique_companies[$comp['company_name']] = true;
            $all_companies[] = $comp;
        }
    }
}

// Try company_intelligence table (map fields appropriately)
$result = $mysqli->query("SELECT id, company_name, NULL AS logo_url, industry, NULL AS location, NULL AS website, NULL AS description, NULL AS contact_person, NULL AS contact_email, NULL AS contact_phone, 1 AS is_active FROM company_intelligence");
if ($result) {
    $companies = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($companies as $comp) {
        if (!isset($unique_companies[$comp['company_name']])) {
            $unique_companies[$comp['company_name']] = true;
            $all_companies[] = $comp;
        }
    }
}

if (empty($all_companies)) {
    echo json_encode(['success' => false, 'message' => 'No companies found in database', 'companies' => []]);
    exit;
}

// Check if OpenAI is configured
if (!isOpenAIConfigured()) {
    // Fallback: simple text search
    $filtered = [];
    $search_lower = strtolower($search_query);
    foreach ($all_companies as $company) {
        $name = strtolower($company['company_name']);
        $industry = strtolower($company['industry'] ?? '');
        $description = strtolower($company['description'] ?? '');
        
        if (strpos($name, $search_lower) !== false || 
            strpos($industry, $search_lower) !== false || 
            strpos($description, $search_lower) !== false) {
            $filtered[] = $company;
        }
    }
    echo json_encode(['success' => true, 'message' => 'Companies found using basic search', 'companies' => $filtered]);
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

// Create the prompt for OpenAI - now it can suggest companies NOT in the database
$prompt = "Based on the user's search query, provide relevant companies. If companies exist in the database, prioritize those. If not, suggest well-known companies that match the search criteria.

User Query: \"{$search_query}\"

" . (count($companies_list) > 0 ? "Available Companies in Database:\n" . json_encode($companies_list, JSON_PRETTY_PRINT) . "\n\n" : "") . "Return ONLY a valid JSON array of company objects with this exact structure:
[
  {
    \"name\": \"Company Name\",
    \"industry\": \"Industry/Sector\",
    \"description\": \"Brief description of the company\",
    \"in_database\": true or false,
    \"location\": \"Optional location\",
    \"website\": \"Optional website\"
  }
]

Consider factors like:
- Industry match
- Company description relevance  
- Name similarity
- Search intent (e.g., 'tech companies' should return technology companies)
- If suggesting NEW companies not in database, pick well-known, reputable companies

Return only the JSON array, no additional text.";

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
    // Fallback to basic search if API fails
    $filtered = [];
    $search_lower = strtolower($search_query);
    foreach ($all_companies as $company) {
        $name = strtolower($company['company_name']);
        $industry = strtolower($company['industry'] ?? '');
        $description = strtolower($company['description'] ?? '');
        
        if (strpos($name, $search_lower) !== false || 
            strpos($industry, $search_lower) !== false || 
            strpos($description, $search_lower) !== false) {
            $filtered[] = $company;
        }
    }
    echo json_encode(['success' => true, 'message' => 'Companies found using basic search', 'companies' => $filtered]);
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

// Process AI suggestions and match with database companies
$filtered_companies = [];
$database_company_map = [];
foreach ($all_companies as $comp) {
    $database_company_map[strtolower($comp['company_name'])] = $comp;
}

if (!empty($ai_suggested_companies)) {
    foreach ($ai_suggested_companies as $suggested) {
        $company_name = $suggested['name'] ?? '';
        $company_lower = strtolower($company_name);
        
        // Check if company exists in database
        if (isset($database_company_map[$company_lower])) {
            // Use database version
            $filtered_companies[] = $database_company_map[$company_lower];
        } else {
            // Create company object from AI suggestion
            $filtered_companies[] = [
                'id' => 0, // No database ID
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
                'is_ai_suggested' => true // Flag to identify AI suggestions
            ];
        }
    }
}

// If no AI matches, provide a fallback - show all companies from database
if (empty($filtered_companies)) {
    echo json_encode(['success' => true, 'message' => 'No specific matches found, showing all companies from database', 'companies' => $all_companies]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Companies found', 'companies' => $filtered_companies]);
?>

