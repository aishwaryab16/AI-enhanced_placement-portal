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
$chapterName = trim($input['chapter_name'] ?? '');
$moduleName = trim($input['module_name'] ?? '');

if (empty($chapterName) && empty($moduleName)) {
    echo json_encode(['error' => 'Chapter name or module name is required']);
    exit;
}

// AI Content Generation Function
function generateAIContent($chapterName, $moduleName) {
    $content = [];
    
    if (!empty($chapterName)) {
        $content['chapter'] = generateChapterContent($chapterName);
    }
    
    if (!empty($moduleName)) {
        $content['module'] = generateModuleContent($moduleName, $chapterName);
    }
    
    return $content;
}

function generateChapterContent($chapterName) {
    // Generate comprehensive chapter information
    $topics = [
        'Introduction and Overview',
        'Key Concepts and Fundamentals',
        'Practical Applications',
        'Common Challenges and Solutions',
        'Best Practices and Tips',
        'Real-world Examples',
        'Assessment and Evaluation'
    ];
    
    $description = "This chapter covers the fundamental concepts of " . $chapterName . ". Students will learn the core principles, practical applications, and real-world examples that demonstrate the importance and relevance of this topic in modern contexts.";
    
    $keyPoints = [
        "Understanding the basic concepts and terminology",
        "Learning practical applications and use cases",
        "Developing problem-solving skills",
        "Gaining hands-on experience through examples",
        "Understanding industry best practices"
    ];
    
    $learningObjectives = [
        "Define and explain key concepts related to " . $chapterName,
        "Identify practical applications and real-world scenarios",
        "Apply learned concepts to solve problems",
        "Evaluate different approaches and methodologies",
        "Demonstrate understanding through practical exercises"
    ];
    
    return [
        'title' => $chapterName,
        'description' => $description,
        'topics' => $topics,
        'key_points' => $keyPoints,
        'learning_objectives' => $learningObjectives,
        'estimated_duration' => '2-3 hours',
        'difficulty_level' => 'Intermediate',
        'prerequisites' => ['Basic understanding of fundamental concepts', 'Willingness to learn and practice']
    ];
}

function generateModuleContent($moduleName, $chapterName = '') {
    $description = "This module provides detailed coverage of " . $moduleName;
    if (!empty($chapterName)) {
        $description .= " within the context of " . $chapterName;
    }
    $description .= ". Students will gain practical knowledge and skills through structured learning activities.";
    
    $content = [
        'Introduction to ' . $moduleName,
        'Core concepts and principles',
        'Step-by-step implementation',
        'Common pitfalls and how to avoid them',
        'Practical exercises and examples',
        'Summary and next steps'
    ];
    
    $activities = [
        'Interactive reading and note-taking',
        'Hands-on practice exercises',
        'Problem-solving scenarios',
        'Peer discussion and collaboration',
        'Self-assessment and reflection'
    ];
    
    $resources = [
        'Comprehensive study materials',
        'Interactive examples and demos',
        'Practice exercises with solutions',
        'Additional reading and references',
        'Video tutorials and explanations'
    ];
    
    return [
        'title' => $moduleName,
        'description' => $description,
        'content_sections' => $content,
        'learning_activities' => $activities,
        'resources' => $resources,
        'estimated_time' => '45-60 minutes',
        'difficulty' => 'Beginner to Intermediate',
        'assessment_methods' => ['Practical exercises', 'Self-assessment quizzes', 'Peer review', 'Instructor feedback']
    ];
}

try {
    $aiContent = generateAIContent($chapterName, $moduleName);
    
    echo json_encode([
        'success' => true,
        'data' => $aiContent,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to generate content: ' . $e->getMessage()
    ]);
}
?>
