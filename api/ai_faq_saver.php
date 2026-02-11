<?php
require_once __DIR__ . '/../config.php';
require_role('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$faqs = $input['faqs'] ?? [];
$chapterId = (int)($input['chapter_id'] ?? 0);
$moduleId = isset($input['module_id']) && $input['module_id'] !== '' ? (int)$input['module_id'] : null;

if (empty($faqs) || $chapterId <= 0) {
    echo json_encode(['error' => 'FAQs data and chapter ID are required']);
    exit;
}

$savedCount = 0;
$errors = [];

// Start transaction
$mysqli->begin_transaction();

try {
    foreach ($faqs as $faq) {
        $question = trim($faq['question'] ?? '');
        $answer = trim($faq['answer'] ?? '');
        $displayOrder = (int)($faq['display_order'] ?? 0);
        
        if (empty($question) || empty($answer)) {
            $errors[] = 'FAQ question and answer are required';
            continue;
        }
        
        $stmt = $mysqli->prepare('INSERT INTO faqs (question, answer, chapter_id, module_id, created_by, display_order) VALUES (?, ?, ?, ?, ?, ?)');
        if ($stmt) {
            $creator = (int)($_SESSION['user_id'] ?? 0);
            $stmt->bind_param('ssiiii', $question, $answer, $chapterId, $moduleId, $creator, $displayOrder);
            if ($stmt->execute()) {
                $savedCount++;
            } else {
                $errors[] = 'Failed to save FAQ: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = 'Prepare failed: ' . $mysqli->error;
        }
    }
    
    if ($savedCount > 0) {
        $mysqli->commit();
        echo json_encode([
            'success' => true,
            'message' => "Successfully saved $savedCount FAQ(s)",
            'saved_count' => $savedCount,
            'errors' => $errors
        ]);
    } else {
        $mysqli->rollback();
        echo json_encode([
            'success' => false,
            'error' => 'No FAQs were saved',
            'errors' => $errors
        ]);
    }
    
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to save FAQs: ' . $e->getMessage()
    ]);
}
?>
