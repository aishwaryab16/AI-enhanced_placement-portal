<?php
require_once __DIR__ . '/../config.php';
require_role('student');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$company_id = $input['company_id'] ?? 0;
$company_name = $input['company_name'] ?? '';
$industry = $input['industry'] ?? '';
$language = $input['language'] ?? '';
$is_ai_suggested = $input['ai_suggested'] ?? false;

if (empty($company_name) || empty($language)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// AI API Configuration
define('OPENAI_API_KEY', 'YOUR_OPENAI_API_KEY_HERE');
define('OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions');

function isAIConfigured() {
    return !empty(OPENAI_API_KEY) && OPENAI_API_KEY !== 'YOUR_OPENAI_API_KEY_HERE';
}

function generateLanguageQuiz($company_name, $industry, $language, $api_key, $api_endpoint) {
    if (!isAIConfigured()) {
        return [
            'error' => false,
            'quiz' => getDefaultLanguageQuiz($company_name, $language)
        ];
    }

    $prompt = "You are a senior technical interviewer and software engineering expert with 15+ years of experience at top tech companies. Create a COMPREHENSIVE technical quiz focused specifically on {$language} for interviews at {$company_name} in the {$industry} industry.

CONTEXT:
- Company: {$company_name} (Major tech company in {$industry})
- Technology: {$language}
- Industry: {$industry}
- Target Level: INTERMEDIATE to ADVANCED (not basic)
- REQUIRED: Generate EXACTLY 20 questions

REQUIREMENTS:
1. Questions must be TECHNICALLY FOCUSED on {$language} specifically
2. Test DEEP KNOWLEDGE of {$language} features, syntax, and best practices
3. Include REAL-WORLD scenarios using {$language}
4. Test PROBLEM-SOLVING with {$language} specifically
5. Include PERFORMANCE, SECURITY, and BEST PRACTICES for {$language}
6. Cover different aspects: syntax, data structures, algorithms, frameworks, design patterns
7. Questions should be CHALLENGING and require expertise
8. Generate EXACTLY 20 diverse, {$language}-specific questions

QUESTION DISTRIBUTION (20 questions total):
- 4 Core {$language} Fundamentals & Syntax questions
- 4 Data Structures & Algorithms in {$language}
- 3 {$language} Best Practices & Design Patterns
- 3 Performance Optimization in {$language}
- 2 Security & Error Handling in {$language}
- 2 Framework/Library specific questions (if applicable)
- 2 Advanced {$language} Concepts & Edge Cases

For each question, provide:
- A realistic technical scenario using {$language}
- 4 multiple choice options with technical depth
- The correct answer (0-3 index)
- Detailed technical explanation with best practices
- Difficulty level (Easy/Medium/Hard/Advanced)
- Time estimate for answering (2-4 minutes each)
- Specific {$language} topics being tested
- Interview tips for answering this type of question

Return ONLY valid JSON in this format:
{
  \"title\": \"{$language} Technical Assessment - {$company_name}\",
  \"description\": \"Comprehensive technical evaluation focused on {$language} for {$company_name} interviews\",
  \"difficulty\": \"Intermediate to Advanced\",
  \"estimated_time\": \"60-100 minutes\",
  \"total_questions\": 20,
  \"language\": \"{$language}\",
  \"questions\": [
    {
      \"question\": \"Complex technical scenario using {$language} with specific context\",
      \"options\": [
        \"Detailed technical option A with {$language} implementation specifics\",
        \"Detailed technical option B with {$language} performance considerations\",
        \"Detailed technical option C with {$language} security implications\",
        \"Detailed technical option D with {$language} best practices\"
      ],
      \"correct_answer\": 0,
      \"explanation\": \"Comprehensive technical explanation covering why this is correct in {$language}, alternatives considered, and best practices\",
      \"difficulty\": \"Medium\",
      \"time_estimate\": \"3-4 minutes\",
      \"topics_covered\": [\"Specific {$language} topics this question tests\"],
      \"interview_tip\": \"How to approach this type of {$language} question in technical interviews\",
      \"question_type\": \"Core Fundamentals\"
    }
  ]
}

CRITICAL REQUIREMENTS:
1. Generate EXACTLY 20 questions - NO MORE, NO LESS
2. Count your questions before returning the JSON
3. If you generate fewer than 20, add more questions
4. If you generate more than 20, remove excess questions
5. The questions array must contain exactly 20 items
6. All questions MUST be specifically about {$language}
7. Focus on {$language} syntax, features, and ecosystem

VALIDATION CHECKLIST:
- [ ] Exactly 20 questions in the questions array
- [ ] Each question has all required fields
- [ ] JSON is valid and properly formatted
- [ ] All questions are specifically about {$language}
- [ ] Questions cover various aspects of {$language}

IMPORTANT: 
- Make questions TECHNICAL and PROFESSIONAL-GRADE
- Focus SPECIFICALLY on {$language}
- Test real-world problem-solving with {$language}
- Include company-specific technical challenges
- Return ONLY valid JSON with EXACTLY 20 questions";

    $ch = curl_init($api_endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => 'You are an expert at creating technical interview questions. Return only valid JSON with exactly 20 questions.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.7,
        'max_tokens' => 4000
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return [
            'error' => true,
            'message' => 'AI API request failed',
            'quiz' => getDefaultLanguageQuiz($company_name, $language)
        ];
    }
    
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        $content = $result['choices'][0]['message']['content'];
        
        // Extract JSON from response
        preg_match('/\{.*\}/s', $content, $matches);
        if (isset($matches[0])) {
            $quiz = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && isset($quiz['questions']) && count($quiz['questions']) > 0) {
                // Ensure exactly 20 questions
                if (count($quiz['questions']) < 20) {
                    // Add default questions if needed
                    $defaultQuiz = getDefaultLanguageQuiz($company_name, $language);
                    $needed = 20 - count($quiz['questions']);
                    $quiz['questions'] = array_merge($quiz['questions'], array_slice($defaultQuiz['questions'], 0, $needed));
                } elseif (count($quiz['questions']) > 20) {
                    $quiz['questions'] = array_slice($quiz['questions'], 0, 20);
                }
                
                $quiz['total_questions'] = count($quiz['questions']);
                return ['error' => false, 'quiz' => $quiz];
            }
        }
    }
    
    return [
        'error' => true,
        'message' => 'Failed to parse AI response',
        'quiz' => getDefaultLanguageQuiz($company_name, $language)
    ];
}

function getDefaultLanguageQuiz($company_name, $language) {
    $questions = [
        [
            'question' => "What is a key feature of {$language} that distinguishes it from other programming languages?",
            'options' => [
                "Option A with {$language} specific details",
                "Option B with {$language} implementation approach",
                "Option C with {$language} performance characteristics",
                "Option D with {$language} best practices"
            ],
            'correct_answer' => 0,
            'explanation' => "This is the correct answer because it demonstrates understanding of {$language} core features.",
            'difficulty' => 'Easy',
            'time_estimate' => '2-3 minutes',
            'topics_covered' => ['Core Concepts', 'Language Fundamentals'],
            'interview_tip' => "Focus on understanding the unique aspects of {$language}.",
            'question_type' => 'Fundamentals'
        ]
    ];
    
    // Generate 20 questions by repeating and modifying
    $baseQuestions = [
        ["Core syntax and data types", "Easy"],
        ["Control flow and loops", "Easy"],
        ["Functions and methods", "Medium"],
        ["Object-oriented concepts", "Medium"],
        ["Error handling", "Medium"],
        ["Memory management", "Hard"],
        ["Concurrency and threading", "Hard"],
        ["Design patterns", "Hard"],
        ["Performance optimization", "Advanced"],
        ["Security best practices", "Advanced"],
        ["Data structures", "Medium"],
        ["Algorithms", "Medium"],
        ["Framework concepts", "Medium"],
        ["Testing strategies", "Medium"],
        ["Code organization", "Medium"],
        ["API design", "Hard"],
        ["Database integration", "Hard"],
        ["Caching strategies", "Hard"],
        ["Scalability considerations", "Advanced"],
        ["Debugging techniques", "Medium"]
    ];
    
    $allQuestions = [];
    foreach ($baseQuestions as $index => $base) {
        $allQuestions[] = [
            'question' => "Question " . ($index + 1) . ": {$base[0]} in {$language}?",
            'options' => [
                "Option A related to {$base[0]} in {$language}",
                "Option B with different approach to {$base[0]}",
                "Option C with alternative solution for {$base[0]}",
                "Option D with best practice for {$base[0]}"
            ],
            'correct_answer' => $index % 4,
            'explanation' => "Explanation for question about {$base[0]} in {$language}.",
            'difficulty' => $base[1],
            'time_estimate' => '2-4 minutes',
            'topics_covered' => [$base[0], $language],
            'interview_tip' => "Focus on practical {$language} knowledge for {$base[0]}.",
            'question_type' => $base[0]
        ];
    }
    
    return [
        'title' => "{$language} Technical Assessment - {$company_name}",
        'description' => "Practice questions focused on {$language} for {$company_name} interviews",
        'difficulty' => 'Intermediate to Advanced',
        'estimated_time' => '60-100 minutes',
        'total_questions' => 20,
        'language' => $language,
        'questions' => $allQuestions
    ];
}

// Generate quiz
$result = generateLanguageQuiz($company_name, $industry, $language, OPENAI_API_KEY, OPENAI_ENDPOINT);

if ($result['error']) {
    echo json_encode([
        'success' => true,
        'message' => 'Using default questions. ' . ($result['message'] ?? ''),
        'quiz' => $result['quiz']
    ]);
} else {
    echo json_encode([
        'success' => true,
        'quiz' => $result['quiz']
    ]);
}
?>

