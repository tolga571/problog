<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['message' => 'Desteklenmeyen yöntem.'], 405);
}

verify_csrf();

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$sentence = trim((string) ($body['sentence'] ?? ''));
$targetLanguage = (string) ($body['target_language'] ?? '');

if ($sentence === '' || !isset(ARTICLE_LANGUAGES[$targetLanguage])) {
    json_response(['message' => 'Geçersiz istek.'], 400);
}

$apiKey = env('GEMINI_API_KEY');
if (!$apiKey) {
    json_response([
        'ok' => false,
        'message' => 'AI çeviri servisi yapılandırılmamış (.env dosyasına GEMINI_API_KEY eklenmeli). "AI için Kopyala" ile manuel çevirebilirsin.',
    ]);
}

$languageName = ARTICLE_LANGUAGES[$targetLanguage];
$prompt = "Aşağıdaki cümleyi {$languageName} diline çevir. Yalnızca çeviriyi ve varsa kısa bir çevirmen notunu şu JSON formatında döndür: "
    . '{"text":"...","note":"..."}' . "\n\nCümle: \"{$sentence}\"";

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . urlencode($apiKey);
$payload = json_encode([
    'contents' => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => [
        'responseMimeType' => 'application/json',
        // "Dusunme" modu bu basit ceviri gorevi icin gereksiz yere yavaslatiyor (10sn+ -> 20sn timeout'u asiyordu).
        'thinkingConfig' => ['thinkingBudget' => 0],
    ],
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode >= 400) {
    json_response([
        'ok' => false,
        'message' => 'AI çeviri servisi şu anda yanıt vermiyor (' . ($curlError ?: 'HTTP ' . $httpCode) . '). "AI için Kopyala" ile manuel çevirebilirsin.',
    ]);
}

$decoded = json_decode((string) $response, true);
$text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
$parsed = $text ? json_decode((string) $text, true) : null;

if (!is_array($parsed) || empty($parsed['text'])) {
    json_response([
        'ok' => false,
        'message' => 'AI yanıtı işlenemedi. "AI için Kopyala" ile manuel çevirebilirsin.',
    ]);
}

json_response([
    'ok' => true,
    'text' => (string) $parsed['text'],
    'note' => (string) ($parsed['note'] ?? ''),
]);
