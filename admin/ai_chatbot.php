<?php
require_once __DIR__ . '/../includes/config.php';
require_role('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$chapterName = trim($input['chapter_name'] ?? '');
$moduleName = trim($input['module_name'] ?? '');

if (empty($chapterName) && empty($moduleName)) {
    echo json_encode(['error' => 'Chapter name or module name is required']);
    exit;
}

// Enhanced AI Content Generation Function for Admin
function generateAdminAIContent($chapterName, $moduleName) {
    $content = [];
    
    if (!empty($chapterName)) {
        $content['chapter'] = generateAdminChapterContent($chapterName);
    }
    
    if (!empty($moduleName)) {
        $content['module'] = generateAdminModuleContent($moduleName, $chapterName);
    }
    
    return $content;
}

function generateAdminChapterContent($chapterName) {
    // Generate comprehensive chapter information for admin use
    $topics = [
        'Introduction and Overview',
        'Core Concepts and Fundamentals',
        'Advanced Topics and Applications',
        'Practical Implementation',
        'Industry Best Practices',
        'Common Challenges and Solutions',
        'Assessment and Evaluation Methods',
        'Resources and Further Reading'
    ];
    
    $description = "This comprehensive chapter covers " . $chapterName . " from fundamental concepts to advanced applications. Students will gain both theoretical knowledge and practical skills through structured learning modules, hands-on exercises, and real-world case studies.";
    
    $keyPoints = [
        "Master the fundamental concepts and terminology of " . $chapterName,
        "Understand practical applications and real-world use cases",
        "Develop problem-solving and analytical thinking skills",
        "Gain hands-on experience through practical exercises",
        "Learn industry best practices and current trends",
        "Apply knowledge to solve complex problems",
        "Evaluate different approaches and methodologies"
    ];
    
    $learningObjectives = [
        "Define and explain key concepts related to " . $chapterName,
        "Identify and analyze practical applications in various contexts",
        "Apply learned concepts to solve real-world problems",
        "Evaluate different methodologies and approaches",
        "Demonstrate proficiency through practical exercises",
        "Synthesize knowledge to create innovative solutions",
        "Communicate findings effectively to different audiences"
    ];
    
    $assessmentMethods = [
        "Written examinations and quizzes",
        "Practical assignments and projects",
        "Case study analysis",
        "Peer review and collaboration",
        "Portfolio development",
        "Presentation and demonstration",
        "Self-assessment and reflection"
    ];
    
    $resources = [
        "Comprehensive study materials and textbooks",
        "Interactive online resources and tutorials",
        "Video lectures and demonstrations",
        "Practice exercises with solutions",
        "Additional reading and references",
        "Industry reports and case studies",
        "Software tools and applications"
    ];
    
    return [
        'title' => $chapterName,
        'description' => $description,
        'topics' => $topics,
        'key_points' => $keyPoints,
        'learning_objectives' => $learningObjectives,
        'assessment_methods' => $assessmentMethods,
        'resources' => $resources,
        'estimated_duration' => '4-6 hours',
        'difficulty_level' => 'Intermediate to Advanced',
        'prerequisites' => [
            'Basic understanding of fundamental concepts',
            'Willingness to engage in hands-on learning',
            'Access to required software and tools',
            'Commitment to complete all assignments'
        ],
        'target_audience' => 'Students, professionals, and learners seeking comprehensive knowledge',
        'learning_outcomes' => [
            'Comprehensive understanding of ' . $chapterName,
            'Practical skills applicable in real-world scenarios',
            'Enhanced problem-solving capabilities',
            'Improved analytical thinking',
            'Professional competency in the subject area'
        ]
    ];
}

function generateAdminModuleContent($moduleName, $chapterName = '') {
    $description = "This detailed module provides comprehensive coverage of " . $moduleName;
    if (!empty($chapterName)) {
        $description .= " within the context of " . $chapterName;
    }
    $description .= ". Students will engage in structured learning activities designed to build both theoretical understanding and practical skills.";
    
    $content = [
        'Introduction to ' . $moduleName,
        'Core concepts and fundamental principles',
        'Step-by-step implementation guide',
        'Advanced techniques and methodologies',
        'Common pitfalls and troubleshooting',
        'Best practices and optimization',
        'Real-world applications and case studies',
        'Summary and next steps'
    ];
    
    $activities = [
        'Interactive reading and note-taking',
        'Hands-on practice exercises',
        'Problem-solving scenarios and case studies',
        'Peer collaboration and discussion',
        'Self-assessment and reflection',
        'Project-based learning',
        'Peer review and feedback'
    ];
    
    $resources = [
        'Comprehensive study materials and guides',
        'Interactive examples and demonstrations',
        'Practice exercises with detailed solutions',
        'Video tutorials and explanations',
        'Additional reading and references',
        'Software tools and applications',
        'Assessment rubrics and guidelines'
    ];
    
    $assessmentCriteria = [
        'Understanding of core concepts (30%)',
        'Practical application skills (25%)',
        'Problem-solving ability (20%)',
        'Communication and presentation (15%)',
        'Collaboration and teamwork (10%)'
    ];
    
    return [
        'title' => $moduleName,
        'description' => $description,
        'content_sections' => $content,
        'learning_activities' => $activities,
        'resources' => $resources,
        'assessment_criteria' => $assessmentCriteria,
        'estimated_time' => '60-90 minutes',
        'difficulty' => 'Intermediate',
        'assessment_methods' => [
            'Practical exercises and assignments',
            'Self-assessment quizzes',
            'Peer review and collaboration',
            'Instructor feedback and evaluation',
            'Portfolio development',
            'Presentation and demonstration'
        ],
        'learning_outcomes' => [
            'Master the core concepts of ' . $moduleName,
            'Apply knowledge in practical scenarios',
            'Develop critical thinking skills',
            'Enhance problem-solving capabilities',
            'Build confidence in the subject area'
        ],
        'prerequisites' => [
            'Basic understanding of related concepts',
            'Access to required materials and tools',
            'Commitment to active participation',
            'Willingness to engage in collaborative learning'
        ]
    ];
}

try {
    $aiContent = generateAdminAIContent($chapterName, $moduleName);
    
    echo json_encode([
        'success' => true,
        'data' => $aiContent,
        'generated_at' => date('Y-m-d H:i:s'),
        'admin_features' => [
            'comprehensive_content' => true,
            'assessment_criteria' => true,
            'learning_outcomes' => true,
            'resource_planning' => true
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to generate content: ' . $e->getMessage()
    ]);
}
?>
