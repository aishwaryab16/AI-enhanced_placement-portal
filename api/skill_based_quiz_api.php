<?php
require_once __DIR__ . '/../config.php';
require_role('student');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$company_id = $input['company_id'] ?? 0;
$company_name = $input['company_name'] ?? '';
$industry = $input['industry'] ?? '';
$interest = $input['interest'] ?? '';
$interest_name = $input['interest_name'] ?? '';
$completed_skills = $input['completed_skills'] ?? [];
$skill_levels = $input['skill_levels'] ?? [];

if (empty($company_name) || empty($interest)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// AI API Configuration
$AI_API_KEY = $AI_API_KEY ?? '';
$AI_API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

function generateSkillBasedQuiz($company_name, $industry, $interest_name, $completed_skills, $skill_levels, $api_key, $api_endpoint) {
    if (empty($api_key)) {
        return [
            'error' => false,
            'quiz' => getDefaultAdvancedQuiz($company_name, $interest_name)
        ];
    }

    $skills_text = '';
    if (!empty($completed_skills)) {
        $skills_text = "COMPLETED SKILLS:\n";
        foreach ($completed_skills as $skill) {
            $level = $skill_levels[$skill] ?? 'Intermediate';
            $skills_text .= "- {$skill} (Level: {$level})\n";
        }
    }

    $prompt = "You are a senior technical interviewer and software engineering expert with 20+ years of experience at top tech companies. Create an ADVANCED, SKILL-BASED technical quiz for {$company_name} in the {$industry} industry.

CONTEXT:
- Company: {$company_name} (Major tech company in {$industry})
- Role: {$interest_name} (Senior-level position)
- Industry: {$industry}
- Target Level: ADVANCED/SENIOR (not basic)
- REQUIRED: Generate EXACTLY 20 questions

{$skills_text}

REQUIREMENTS:
1. Questions must be TECHNICALLY ADVANCED and INTERVIEW-LEVEL
2. Focus on the COMPLETED SKILLS listed above
3. Test DEEP TECHNICAL KNOWLEDGE of specific technologies
4. Include REAL-WORLD scenarios using these technologies
5. Test PROBLEM-SOLVING with these specific tools/frameworks
6. Include PERFORMANCE, SCALABILITY, and SECURITY considerations
7. Questions should be CHALLENGING and require expertise
8. Generate EXACTLY 20 diverse, skill-based questions

QUESTION DISTRIBUTION (20 questions total):
- 5 Advanced Implementation questions using completed skills
- 4 System Design questions incorporating these technologies
- 3 Performance Optimization questions with these tools
- 3 Security & Best Practices questions for these technologies
- 2 Debugging & Troubleshooting questions
- 2 Code Review & Architecture questions
- 1 Integration & Deployment question

For each question, provide:
- A complex, realistic technical scenario using specific technologies
- 4 multiple choice options with technical depth
- The correct answer (0-3 index)
- Detailed technical explanation with best practices
- Difficulty level (Advanced/Expert)
- Time estimate for answering (3-5 minutes each)
- Specific technologies being tested

Return ONLY valid JSON in this format:
{
  \"title\": \"Advanced Technical Assessment - {$company_name} {$interest_name} Role\",
  \"description\": \"Comprehensive technical evaluation based on completed skills: " . implode(', ', $completed_skills) . "\",
  \"difficulty\": \"Advanced/Expert\",
  \"estimated_time\": \"60-100 minutes\",
  \"total_questions\": 20,
  \"skills_tested\": " . json_encode($completed_skills) . ",
  \"questions\": [
    {
      \"question\": \"Complex technical scenario using specific technologies from completed skills\",
      \"options\": [
        \"Detailed technical option A with implementation specifics\",
        \"Detailed technical option B with performance considerations\",
        \"Detailed technical option C with security implications\",
        \"Detailed technical option D with scalability concerns\"
      ],
      \"correct_answer\": 0,
      \"explanation\": \"Comprehensive technical explanation covering why this is correct, alternatives considered, and best practices\",
      \"difficulty\": \"Advanced\",
      \"time_estimate\": \"3-5 minutes\",
      \"topics_covered\": [\"Specific technologies and concepts being tested\"],
      \"interview_tip\": \"How to approach this type of question in technical interviews\",
      \"skills_required\": [\"Specific skills from completed list that are needed\"],
      \"question_type\": \"Advanced Implementation\"
    }
  ],
  \"assessment_criteria\": [
    \"Technical depth and accuracy with specific technologies\",
    \"Problem-solving methodology using completed skills\",
    \"System design thinking with these tools\",
    \"Performance optimization awareness\",
    \"Security considerations for these technologies\",
    \"Code quality and best practices\"
  ],
  \"preparation_notes\": \"Specific areas to focus on for {$company_name} technical interviews using these technologies\"
}

CRITICAL REQUIREMENTS:
1. Generate EXACTLY 20 questions - NO MORE, NO LESS
2. Count your questions before returning the JSON
3. If you generate fewer than 20, add more questions
4. If you generate more than 20, remove excess questions
5. The questions array must contain exactly 20 items

VALIDATION CHECKLIST:
- [ ] Exactly 20 questions in the questions array
- [ ] Each question has all required fields
- [ ] JSON is valid and properly formatted
- [ ] All questions are advanced/technical level
- [ ] Questions focus on completed skills

IMPORTANT: 
- Make questions ADVANCED and PROFESSIONAL-GRADE
- Focus SPECIFICALLY on the completed skills
- Test real-world problem-solving with these technologies
- Include company-specific technical challenges
- Return ONLY valid JSON with EXACTLY 20 questions";

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
            ['role' => 'system', 'content' => 'You are a senior technical interviewer and software engineering expert with 20+ years of experience at FAANG companies. You specialize in creating advanced, skill-based technical assessments that evaluate deep technical knowledge of specific technologies. Your questions are challenging, realistic, and focused on production-level scenarios using the exact technologies the candidate has studied. Return only valid JSON.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.2,
        'max_tokens' => 5000
    ];
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return [
            'error' => true,
            'message' => 'AI API request failed',
            'quiz' => getDefaultAdvancedQuiz($company_name, $interest_name)
        ];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        $content = $result['choices'][0]['message']['content'];
        $quiz = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($quiz)) {
            // Validate that we have exactly 20 questions
            if (isset($quiz['questions']) && count($quiz['questions']) === 20) {
                return [
                    'error' => false,
                    'quiz' => $quiz
                ];
            } else {
                // If we don't have 20 questions, try to generate more
                $current_count = isset($quiz['questions']) ? count($quiz['questions']) : 0;
                $needed = 20 - $current_count;
                
                if ($needed > 0) {
                    $additional_questions = generateAdditionalSkillQuestions($company_name, $industry, $interest_name, $completed_skills, $needed, $api_key, $api_endpoint);
                    if ($additional_questions) {
                        $quiz['questions'] = array_merge($quiz['questions'], $additional_questions);
                        $quiz['total_questions'] = count($quiz['questions']);
                    }
                }
                
                return [
                    'error' => false,
                    'quiz' => $quiz
                ];
            }
        }
    }
    
    return [
        'error' => true,
        'message' => 'Failed to parse AI response',
        'quiz' => getDefaultAdvancedQuiz($company_name, $interest_name)
    ];
}

function getDefaultAdvancedQuiz($company_name, $interest_name) {
    // Use the same comprehensive 20-question quiz from quiz_api.php
    return [
        'title' => "Advanced Technical Assessment - {$company_name} {$interest_name} Role",
        'description' => "Comprehensive technical evaluation covering advanced concepts, system design, and industry-specific challenges",
        'difficulty' => 'Advanced/Expert',
        'estimated_time' => '60-100 minutes',
        'total_questions' => 20,
        'questions' => [
            [
                'question' => "You're designing a microservices architecture for {$company_name}'s platform handling 1M+ daily users. Which approach would you recommend for service communication?",
                'options' => [
                    'Synchronous REST APIs with circuit breakers and retry mechanisms',
                    'Asynchronous message queues with event-driven architecture',
                    'GraphQL federation with Apollo Gateway for unified API',
                    'gRPC with service mesh (Istio) for high-performance communication'
                ],
                'correct_answer' => 3,
                'explanation' => 'gRPC with service mesh provides the best performance, type safety, and observability for high-scale microservices. It offers binary serialization, HTTP/2 multiplexing, and built-in load balancing.',
                'difficulty' => 'Advanced',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Microservices', 'System Design', 'Performance'],
                'interview_tip' => 'Focus on scalability, reliability, and performance trade-offs',
                'question_type' => 'System Design'
            ],
            [
                'question' => "In a distributed system at {$company_name}, you need to implement distributed locking for a critical resource. Which approach ensures both consistency and availability?",
                'options' => [
                    'Redis with SET NX EX for simple distributed locks',
                    'Zookeeper with sequential ephemeral nodes',
                    'Consul with session-based locking',
                    'Database-based locking with optimistic concurrency control'
                ],
                'correct_answer' => 1,
                'explanation' => 'Zookeeper provides strong consistency guarantees and handles leader election automatically. Sequential ephemeral nodes ensure proper lock ordering and automatic cleanup on client disconnection.',
                'difficulty' => 'Expert',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Distributed Systems', 'Consistency', 'CAP Theorem'],
                'interview_tip' => 'Consider the CAP theorem trade-offs and failure scenarios',
                'question_type' => 'System Design'
            ],
            [
                'question' => "Your {$company_name} application is experiencing memory leaks in production. Which profiling approach would you use to identify the root cause?",
                'options' => [
                    'Heap dumps with MAT (Memory Analyzer Tool) for object retention analysis',
                    'CPU profiling with JProfiler to identify performance bottlenecks',
                    'Network monitoring with Wireshark to detect connection leaks',
                    'Database query analysis with EXPLAIN to find slow queries'
                ],
                'correct_answer' => 0,
                'explanation' => 'Heap dumps with MAT are specifically designed for memory leak detection. They show object retention paths and help identify which objects are preventing garbage collection.',
                'difficulty' => 'Advanced',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Memory Management', 'Debugging', 'Performance'],
                'interview_tip' => 'Focus on systematic debugging approaches and tool selection',
                'question_type' => 'Debugging & Troubleshooting'
            ],
            [
                'question' => "When implementing authentication for {$company_name}'s API, which approach provides the best security for stateless services?",
                'options' => [
                    'JWT tokens with short expiration and refresh token rotation',
                    'Session-based authentication with Redis storage',
                    'API keys with IP whitelisting and rate limiting',
                    'OAuth 2.0 with PKCE for public clients'
                ],
                'correct_answer' => 0,
                'explanation' => 'JWT with short expiration and refresh token rotation provides stateless authentication while maintaining security through token rotation and minimal exposure window.',
                'difficulty' => 'Advanced',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Security', 'Authentication', 'API Design'],
                'interview_tip' => 'Consider stateless requirements, security, and scalability',
                'question_type' => 'Security & Best Practices'
            ],
            [
                'question' => "Your {$company_name} database is experiencing slow query performance. Which optimization strategy would you implement first?",
                'options' => [
                    'Add appropriate indexes based on query patterns and execution plans',
                    'Implement database connection pooling to reduce connection overhead',
                    'Partition large tables by date or user ID to improve query performance',
                    'Upgrade to a more powerful database server with more CPU and memory'
                ],
                'correct_answer' => 0,
                'explanation' => 'Indexing is the most cost-effective optimization. Proper indexes can dramatically improve query performance without requiring infrastructure changes or code modifications.',
                'difficulty' => 'Advanced',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Database Optimization', 'Performance', 'Indexing'],
                'interview_tip' => 'Start with low-cost, high-impact optimizations',
                'question_type' => 'Performance Optimization'
            ],
            [
                'question' => "You're implementing a caching strategy for {$company_name}'s high-traffic web application. Which approach provides the best performance and consistency?",
                'options' => [
                    'Write-through caching with Redis and database synchronization',
                    'Write-behind caching with eventual consistency guarantees',
                    'Cache-aside pattern with TTL-based invalidation',
                    'Read-through caching with write-around for updates'
                ],
                'correct_answer' => 2,
                'explanation' => 'Cache-aside pattern provides the best balance of performance and consistency. It allows fine-grained control over cache invalidation and handles cache misses gracefully.',
                'difficulty' => 'Advanced',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Caching', 'Performance', 'Data Consistency'],
                'interview_tip' => 'Consider consistency requirements and performance trade-offs',
                'question_type' => 'Performance Optimization'
            ],
            [
                'question' => "When designing a real-time notification system for {$company_name}, which technology stack would you choose for optimal performance?",
                'options' => [
                    'WebSockets with Node.js and Socket.io for real-time communication',
                    'Server-Sent Events (SSE) with HTTP/2 for one-way communication',
                    'WebRTC for peer-to-peer communication with fallback to WebSockets',
                    'Message queues with WebSocket gateways for scalable real-time updates'
                ],
                'correct_answer' => 3,
                'explanation' => 'Message queues with WebSocket gateways provide the best scalability for real-time systems. They handle high concurrency and can distribute load across multiple servers.',
                'difficulty' => 'Expert',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Real-time Systems', 'WebSockets', 'Scalability'],
                'interview_tip' => 'Focus on scalability and reliability for real-time systems',
                'question_type' => 'System Design'
            ],
            [
                'question' => "Your {$company_name} application needs to handle 10TB of data processing daily. Which approach would you recommend for data pipeline architecture?",
                'options' => [
                    'Batch processing with Apache Spark and HDFS for large-scale data processing',
                    'Stream processing with Apache Kafka and Apache Flink for real-time analytics',
                    'Lambda architecture combining batch and stream processing',
                    'Micro-batch processing with Apache Storm for near real-time processing'
                ],
                'correct_answer' => 2,
                'explanation' => 'Lambda architecture provides the best of both worlds - real-time processing for immediate insights and batch processing for comprehensive analysis and data correction.',
                'difficulty' => 'Expert',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Big Data', 'Data Processing', 'Architecture'],
                'interview_tip' => 'Consider both real-time and batch processing requirements',
                'question_type' => 'System Design'
            ],
            [
                'question' => "When implementing CI/CD for {$company_name}'s microservices, which strategy ensures zero-downtime deployments?",
                'options' => [
                    'Blue-green deployment with load balancer switching',
                    'Canary deployment with gradual traffic shifting',
                    'Rolling deployment with health checks and rollback capability',
                    'Feature flags with toggles for gradual feature rollout'
                ],
                'correct_answer' => 1,
                'explanation' => 'Canary deployment provides the safest zero-downtime deployment by gradually shifting traffic to the new version while monitoring for issues.',
                'difficulty' => 'Advanced',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['DevOps', 'Deployment', 'CI/CD'],
                'interview_tip' => 'Focus on risk mitigation and monitoring during deployments',
                'question_type' => 'DevOps & Deployment'
            ],
            [
                'question' => "Your {$company_name} API is experiencing rate limiting issues. Which approach provides the most effective rate limiting strategy?",
                'options' => [
                    'Token bucket algorithm with Redis for distributed rate limiting',
                    'Sliding window counter with database persistence',
                    'Fixed window counter with in-memory storage',
                    'Leaky bucket algorithm with queue-based processing'
                ],
                'correct_answer' => 0,
                'explanation' => 'Token bucket algorithm with Redis provides the most effective distributed rate limiting. It allows burst traffic while maintaining overall rate limits and works across multiple servers.',
                'difficulty' => 'Advanced',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Rate Limiting', 'API Design', 'Distributed Systems'],
                'interview_tip' => 'Consider distributed systems and burst traffic handling',
                'question_type' => 'Performance Optimization'
            ],
            [
                'question' => "When implementing monitoring for {$company_name}'s production system, which metrics are most critical for system health?",
                'options' => [
                    'CPU usage, memory consumption, and disk I/O for resource monitoring',
                    'Response time, error rate, and throughput for application performance',
                    'Database connection pool, cache hit ratio, and queue depth for service health',
                    'All of the above with custom business metrics and alerting thresholds'
                ],
                'correct_answer' => 3,
                'explanation' => 'Comprehensive monitoring requires all levels - infrastructure, application, and business metrics. This provides complete visibility into system health and performance.',
                'difficulty' => 'Advanced',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Monitoring', 'Observability', 'System Health'],
                'interview_tip' => 'Consider multiple layers of monitoring for complete visibility',
                'question_type' => 'DevOps & Monitoring'
            ],
            [
                'question' => "Your {$company_name} application needs to handle sensitive data. Which encryption approach provides the best security?",
                'options' => [
                    'AES-256 encryption at rest with TLS 1.3 for data in transit',
                    'End-to-end encryption with client-side key management',
                    'Field-level encryption with application-specific keys',
                    'Hybrid approach with multiple encryption layers and key rotation'
                ],
                'correct_answer' => 3,
                'explanation' => 'Hybrid approach with multiple encryption layers provides defense in depth. Different data types may require different encryption strategies, and key rotation ensures long-term security.',
                'difficulty' => 'Expert',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Security', 'Encryption', 'Data Protection'],
                'interview_tip' => 'Consider defense in depth and different data sensitivity levels',
                'question_type' => 'Security & Best Practices'
            ],
            [
                'question' => "When designing a search feature for {$company_name}'s e-commerce platform, which approach provides the best search experience?",
                'options' => [
                    'Elasticsearch with custom analyzers and relevance scoring',
                    'Database full-text search with indexing optimization',
                    'Apache Solr with faceted search and auto-complete',
                    'Hybrid search combining multiple engines with machine learning ranking'
                ],
                'correct_answer' => 0,
                'explanation' => 'Elasticsearch provides the most flexible and powerful search capabilities with custom analyzers, relevance scoring, and real-time indexing for e-commerce applications.',
                'difficulty' => 'Advanced',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Search', 'Elasticsearch', 'E-commerce'],
                'interview_tip' => 'Focus on search relevance and user experience',
                'question_type' => 'Advanced Implementation'
            ],
            [
                'question' => "Your {$company_name} application needs to process payments securely. Which approach ensures PCI compliance?",
                'options' => [
                    'Tokenization with PCI-compliant payment processor integration',
                    'End-to-end encryption with secure key management',
                    'PCI DSS compliant infrastructure with encrypted data storage',
                    'Third-party payment gateway with tokenization and no card data storage'
                ],
                'correct_answer' => 3,
                'explanation' => 'Third-party payment gateway with tokenization eliminates the need to store card data, reducing PCI compliance scope and security risks significantly.',
                'difficulty' => 'Expert',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Security', 'PCI Compliance', 'Payments'],
                'interview_tip' => 'Focus on reducing PCI scope and security risks',
                'question_type' => 'Security & Best Practices'
            ],
            [
                'question' => "When implementing automated testing for {$company_name}'s microservices, which strategy provides the best test coverage?",
                'options' => [
                    'Unit tests with 90%+ code coverage and integration tests for APIs',
                    'End-to-end tests with Selenium and comprehensive user journey testing',
                    'Contract testing with Pact for service communication validation',
                    'Pyramid testing strategy with unit, integration, and E2E tests'
                ],
                'correct_answer' => 3,
                'explanation' => 'Pyramid testing strategy provides the best balance of test coverage, maintainability, and execution speed. It focuses on unit tests with fewer, more targeted integration and E2E tests.',
                'difficulty' => 'Advanced',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Testing', 'Quality Assurance', 'Microservices'],
                'interview_tip' => 'Consider test maintainability and execution speed',
                'question_type' => 'Code Review & Quality'
            ],
            [
                'question' => "Your {$company_name} application needs to handle international users. Which approach provides the best globalization support?",
                'options' => [
                    'i18n with locale-specific resource bundles and Unicode support',
                    'CDN with edge locations and content delivery optimization',
                    'Database sharding by geographic regions for performance',
                    'Multi-tenant architecture with region-specific configurations'
                ],
                'correct_answer' => 0,
                'explanation' => 'i18n with locale-specific resource bundles provides proper internationalization support for different languages, currencies, and cultural preferences.',
                'difficulty' => 'Advanced',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Internationalization', 'Localization', 'Global Applications'],
                'interview_tip' => 'Consider cultural and linguistic differences',
                'question_type' => 'Advanced Implementation'
            ],
            [
                'question' => "When implementing data backup for {$company_name}'s critical systems, which strategy ensures data recovery?",
                'options' => [
                    '3-2-1 backup strategy with multiple copies and offsite storage',
                    'Continuous backup with point-in-time recovery capabilities',
                    'Database replication with automated failover mechanisms',
                    'Hybrid approach with multiple backup types and regular testing'
                ],
                'correct_answer' => 3,
                'explanation' => 'Hybrid approach with multiple backup types ensures comprehensive data protection. Different data types may require different backup strategies, and regular testing validates recovery procedures.',
                'difficulty' => 'Advanced',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Backup', 'Disaster Recovery', 'Data Protection'],
                'interview_tip' => 'Consider different data types and recovery requirements',
                'question_type' => 'DevOps & Operations'
            ],
            [
                'question' => "Your {$company_name} application needs to handle file uploads securely. Which approach provides the best security?",
                'options' => [
                    'File type validation with virus scanning and secure storage',
                    'Pre-signed URLs with time-limited access and content validation',
                    'Client-side encryption with server-side decryption and validation',
                    'Multi-layer security with validation, scanning, and access controls'
                ],
                'correct_answer' => 3,
                'explanation' => 'Multi-layer security provides defense in depth against various attack vectors. It combines validation, scanning, access controls, and secure storage for comprehensive protection.',
                'difficulty' => 'Expert',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Security', 'File Upload', 'Data Validation'],
                'interview_tip' => 'Consider multiple attack vectors and defense strategies',
                'question_type' => 'Security & Best Practices'
            ],
            [
                'question' => "When optimizing {$company_name}'s mobile application performance, which approach provides the best user experience?",
                'options' => [
                    'Lazy loading with image optimization and code splitting',
                    'Progressive Web App (PWA) with offline capabilities and caching',
                    'Native performance optimization with platform-specific optimizations',
                    'Hybrid approach combining multiple optimization techniques'
                ],
                'correct_answer' => 3,
                'explanation' => 'Hybrid approach combines the best of all optimization techniques. Different scenarios may require different approaches, and a comprehensive strategy provides the best overall performance.',
                'difficulty' => 'Advanced',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['Mobile Development', 'Performance', 'User Experience'],
                'interview_tip' => 'Consider different user scenarios and device capabilities',
                'question_type' => 'Performance Optimization'
            ],
            [
                'question' => "Your {$company_name} application needs to integrate with third-party APIs. Which approach ensures reliable integration?",
                'options' => [
                    'Circuit breaker pattern with retry logic and fallback mechanisms',
                    'API gateway with rate limiting and authentication management',
                    'Event-driven architecture with message queues for decoupling',
                    'Comprehensive integration strategy with monitoring and error handling'
                ],
                'correct_answer' => 3,
                'explanation' => 'Comprehensive integration strategy combines multiple patterns and techniques. It includes circuit breakers, API gateways, monitoring, and error handling for robust third-party integrations.',
                'difficulty' => 'Expert',
                'time_estimate' => '3-5 minutes',
                'topics_covered' => ['API Integration', 'Reliability', 'System Design'],
                'interview_tip' => 'Consider reliability, monitoring, and error handling',
                'question_type' => 'System Design'
            ]
        ],
        'assessment_criteria' => [
            'Technical depth and accuracy',
            'Problem-solving methodology',
            'System design thinking',
            'Performance optimization awareness',
            'Security considerations',
            'Code quality and best practices'
        ],
        'preparation_notes' => "Focus on advanced system design, distributed systems, and performance optimization for {$company_name} technical interviews"
    ];
}

function generateAdditionalSkillQuestions($company_name, $industry, $interest_name, $completed_skills, $needed, $api_key, $api_endpoint) {
    if (empty($api_key)) {
        return null;
    }

    $skills_text = '';
    if (!empty($completed_skills)) {
        $skills_text = "COMPLETED SKILLS:\n";
        foreach ($completed_skills as $skill) {
            $skills_text .= "- {$skill}\n";
        }
    }

    $prompt = "Generate EXACTLY {$needed} additional advanced technical questions for {$company_name} in the {$industry} industry for {$interest_name} role.

{$skills_text}

REQUIREMENTS:
- Generate EXACTLY {$needed} questions (no more, no less)
- Questions must be ADVANCED and INTERVIEW-LEVEL
- Focus on the COMPLETED SKILLS listed above
- Include REAL-WORLD scenarios using these technologies
- Test PROBLEM-SOLVING with these specific tools/frameworks

Return ONLY a JSON array of questions in this format:
[
  {
    \"question\": \"Complex technical scenario using specific technologies from completed skills\",
    \"options\": [
      \"Detailed technical option A with implementation specifics\",
      \"Detailed technical option B with performance considerations\",
      \"Detailed technical option C with security implications\",
      \"Detailed technical option D with scalability concerns\"
    ],
    \"correct_answer\": 0,
    \"explanation\": \"Comprehensive technical explanation covering why this is correct, alternatives considered, and best practices\",
    \"difficulty\": \"Advanced\",
    \"time_estimate\": \"3-5 minutes\",
    \"topics_covered\": [\"Specific technologies and concepts being tested\"],
    \"interview_tip\": \"How to approach this type of question in technical interviews\",
    \"skills_required\": [\"Specific skills from completed list that are needed\"],
    \"question_type\": \"Advanced Implementation\"
  }
]

CRITICAL: Generate EXACTLY {$needed} questions. Count them before returning.";

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
            ['role' => 'system', 'content' => 'You are a senior technical interviewer. Generate exactly the requested number of advanced technical questions based on completed skills. Return only valid JSON array.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.2,
        'max_tokens' => 2000
    ];
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return null;
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        $content = $result['choices'][0]['message']['content'];
        $questions = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($questions)) {
            return $questions;
        }
    }
    
    return null;
}

// Generate skill-based quiz
$quizData = generateSkillBasedQuiz($company_name, $industry, $interest_name, $completed_skills, $skill_levels, $AI_API_KEY, $AI_API_ENDPOINT);

// Debug information
error_log("Quiz generation - Error: " . ($quizData['error'] ? 'true' : 'false'));
error_log("Quiz generation - Questions count: " . (isset($quizData['quiz']['questions']) ? count($quizData['quiz']['questions']) : 'not set'));

echo json_encode([
    'success' => true,
    'quiz' => $quizData['quiz'],
    'message' => $quizData['error'] ? 'Using advanced sample questions. Add OpenAI API key for AI-generated questions.' : 'Advanced skill-based quiz created successfully.'
]);
?>
