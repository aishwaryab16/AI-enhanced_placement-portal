<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

// Optional: load Composer autoload if present
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
	require_once $autoloadPath;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '[]', true);
$userMessage = trim($payload['message'] ?? '');
if ($userMessage === '') {
	echo json_encode(['error' => 'Empty message']);
	exit;
}

$apiKey = getenv('OPENAI_API_KEY') ?: (isset($OPENAI_API_KEY) ? $OPENAI_API_KEY : '');

$system = 'You are a professional AI interview coach helping students practice for job interviews. Your role is to:
1. Ask relevant technical and behavioral interview questions
2. Provide constructive feedback on answers
3. Suggest improvements and tips
4. Keep the conversation engaging and educational
5. Focus on common placement interview topics like programming, problem-solving, and soft skills
Be encouraging but honest in your feedback. Ask follow-up questions to dive deeper into topics.';

// Prefer SDK if available
if (class_exists('OpenAI')) {
	try {
		$client = \OpenAI::client($apiKey);
		$response = $client->chat()->create([
			'model' => 'gpt-4',
			'messages' => [
				['role' => 'system', 'content' => $system],
				['role' => 'user', 'content' => $userMessage],
			],
		]);
		$reply = $response['choices'][0]['message']['content'] ?? '';
		echo json_encode(['reply' => $reply]);
		exit;
	} catch (Throwable $e) {
		// fallthrough to curl
	}
}

// Try OpenAI API if key is available
if ($apiKey) {
	try {
		$body = json_encode([
			'model' => 'gpt-4o-mini',
			'messages' => [
				['role' => 'system', 'content' => $system],
				['role' => 'user', 'content' => $userMessage],
			],
		]);
		$ch = curl_init('https://api.openai.com/v1/chat/completions');
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => [
				'Authorization: Bearer ' . $apiKey,
				'Content-Type: application/json',
			],
			CURLOPT_POSTFIELDS => $body,
			CURLOPT_TIMEOUT => 30,
		]);
		$res = curl_exec($ch);
		if ($res !== false) {
			$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			$data = json_decode($res, true);
			if ($http < 400 && isset($data['choices'][0]['message']['content'])) {
				$reply = $data['choices'][0]['message']['content'];
				echo json_encode(['reply' => $reply]);
				exit;
			}
		}
	} catch (Throwable $e) {
		// Fall through to local responses
	}
}

// Fallback: Use local interview responses
$reply = generateInterviewResponse($userMessage);
echo json_encode(['reply' => $reply]);

function generateInterviewResponse($message) {
	$message = strtolower(trim($message));
	
	// Greeting responses
	if (preg_match('/\b(hi|hello|hey|start|begin)\b/', $message)) {
		$greetings = [
			"Hello! I'm excited to help you practice for your interview. Let's start with a common question: Tell me about yourself and why you're interested in this position.",
			"Great to meet you! Let's begin your interview practice. First question: What are your greatest strengths and how do they relate to this role?",
			"Welcome to your interview practice session! Let's start with: Walk me through your background and what makes you a good fit for this position.",
			"Hello! Ready to practice? Let's begin with a classic: Why should we hire you? What unique value do you bring?"
		];
		return $greetings[array_rand($greetings)];
	}
	
	// Technical questions
	if (preg_match('/\b(technical|programming|code|algorithm|data structure)\b/', $message)) {
		$technical = [
			"Great! Let's dive into technical questions. Can you explain the difference between a stack and a queue? When would you use each?",
			"Excellent! Here's a technical challenge: How would you reverse a linked list? Walk me through your approach step by step.",
			"Perfect! Let's talk algorithms. Can you explain how binary search works and what its time complexity is?",
			"Good! Here's a coding question: How would you find the largest element in an array? What's the most efficient approach?"
		];
		return $technical[array_rand($technical)];
	}
	
	// Behavioral questions
	if (preg_match('/\b(behavioral|experience|team|challenge|conflict)\b/', $message)) {
		$behavioral = [
			"Let's explore behavioral questions. Tell me about a time when you faced a significant challenge. How did you handle it?",
			"Great! Here's a behavioral question: Describe a situation where you had to work with a difficult team member. What was your approach?",
			"Excellent! Can you share an example of a time when you had to learn something new quickly? How did you approach it?",
			"Perfect! Tell me about a project you're particularly proud of. What was your role and what made it successful?"
		];
		return $behavioral[array_rand($behavioral)];
	}
	
	// Feedback on answers
	if (strlen($message) > 50) {
		$feedback = [
			"That's a solid answer! I appreciate the specific examples you provided. Let me ask a follow-up: How would you handle a situation where your initial approach doesn't work?",
			"Good response! You demonstrated clear thinking. Now, let's try this: What's your biggest weakness and how are you working to improve it?",
			"Excellent! I like how you structured your answer. Here's another question: Where do you see yourself in 5 years?",
			"Very thoughtful answer! You showed good problem-solving skills. Let's continue: What questions do you have about our company or this role?"
		];
		return $feedback[array_rand($feedback)];
	}
	
	// Short answers - encourage elaboration
	if (strlen($message) < 20) {
		$encourage = [
			"I'd love to hear more details about that. Can you elaborate with a specific example?",
			"That's a good start! Can you walk me through your thought process in more detail?",
			"Interesting! Can you provide more context or a specific situation where this applied?",
			"Good point! Can you expand on that with a concrete example from your experience?"
		];
		return $encourage[array_rand($encourage)];
	}
	
	// General interview questions
	$general = [
		"Tell me about a time when you had to meet a tight deadline. How did you manage your time and priorities?",
		"What motivates you in your work? What kind of environment do you thrive in?",
		"How do you handle stress and pressure? Can you give me an example?",
		"What's your approach to learning new technologies or skills?",
		"Describe your ideal work environment and team dynamics.",
		"What do you consider your greatest professional achievement so far?",
		"How do you prioritize tasks when you have multiple deadlines?",
		"Tell me about a mistake you made and how you learned from it.",
		"What interests you most about this industry/field?",
		"How do you stay updated with the latest trends in technology?"
	];
	
	return $general[array_rand($general)];
}
