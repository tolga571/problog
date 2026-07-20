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
$sentences = is_array($body['sentences'] ?? null) ? $body['sentences'] : [];
$targetLanguage = (string) ($body['target_language'] ?? '');

$clean = [];
foreach ($sentences as $s) {
    if (!is_array($s) || empty($s['code']) || trim((string) ($s['text'] ?? '')) === '') {
        continue;
    }
    $clean[] = ['code' => (string) $s['code'], 'text' => (string) $s['text']];
}

if (!$clean || !isset(ARTICLE_LANGUAGES[$targetLanguage])) {
    json_response(['message' => 'Geçersiz istek.'], 400);
}

$apiKey = env('GEMINI_API_KEY');
if (!$apiKey) {
    json_response([
        'ok' => false,
        'message' => 'AI çeviri servisi yapılandırılmamış (.env dosyasına GEMINI_API_KEY eklenmeli).',
    ]);
}

$languageName = ARTICLE_LANGUAGES[$targetLanguage];
$lines = array_map(fn(array $s) => "[{$s['code']}] {$s['text']}", $clean);
$prompt = "Aşağıdaki numaralı cümlelerin her birini {$languageName} diline çevir. Her cümle için [code] etiketini koru, "
    . "gerekiyorsa kısa bir çevirmen notu ekle (deyim/kültürel eşdeğerlik açıklaması gibi, yoksa boş bırak). "
    . "Yalnızca aşağıdaki şemaya uyan bir JSON dizisi döndür, başka hiçbir şey yazma.\n\n"
    . implode("\n", $lines);

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . urlencode($apiKey);
$payload = json_encode([
    'contents' => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => [
        'responseMimeType' => 'application/json',
        'thinkingConfig' => ['thinkingBudget' => 0],
        'responseSchema' => [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'code' => ['type' => 'STRING'],
                    'text' => ['type' => 'STRING'],
                    'note' => ['type' => 'STRING'],
                ],
                'required' => ['code', 'text'],
            ],
        ],
    ],
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 55,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode >= 400) {
    json_response([
        'ok' => false,
        'message' => 'AI çeviri servisi şu anda yanıt vermiyor (' . ($curlError ?: 'HTTP ' . $httpCode) . ').',
    ]);
}

$decoded = json_decode((string) $response, true);
$text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
$parsed = $text ? json_decode((string) $text, true) : null;

if (!is_array($parsed)) {
    json_response(['ok' => false, 'message' => 'AI yanıtı işlenemedi.']);
}

$translations = [];
foreach ($parsed as $item) {
    if (is_array($item) && !empty($item['code']) && isset($item['text'])) {
        $translations[] = [
            'code' => (string) $item['code'],
            'text' => (string) $item['text'],
            'note' => (string) ($item['note'] ?? ''),
        ];
    }
}

json_response(['ok' => true, 'translations' => $translations]);
