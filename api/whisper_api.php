<?php
header('Content-Type: application/json');

$apiKey = getenv('OPENAI_API_KEY') ?: (isset($OPENAI_API_KEY) ? $OPENAI_API_KEY : '');
if (!$apiKey) {
	echo json_encode(['error' => 'Missing OPENAI_API_KEY']);
	exit;
}

if (!isset($_FILES['audio'])) {
	echo json_encode(['error' => 'No audio provided']);
	exit;
}

try {
	$transcript = transcribe($_FILES['audio'], $apiKey);
	echo json_encode(['transcript' => $transcript]);
} catch (Throwable $e) {
	echo json_encode(['error' => $e->getMessage()]);
}

function transcribe($file, $apiKey){
	$ch = curl_init();
	$post = [
		'model' => 'whisper-1',
		'file' => new CURLFile($file['tmp_name'], $file['type'] ?: 'audio/webm', $file['name'] ?: 'audio.webm'),
	];
	curl_setopt_array($ch, [
		CURLOPT_URL => 'https://api.openai.com/v1/audio/transcriptions',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST => true,
		CURLOPT_HTTPHEADER => [
			'Authorization: Bearer ' . $apiKey
		],
		CURLOPT_POSTFIELDS => $post,
	]);
	$res = curl_exec($ch);
	if ($res === false) throw new Exception('Transcription request failed');
	$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	$data = json_decode($res, true);
	if ($http >= 400) throw new Exception($data['error']['message'] ?? 'Transcription error');
	return $data['text'] ?? '';
}
