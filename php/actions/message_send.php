<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = current_user();
if (!$user) {
    json_response(['message' => 'Oturum gerekli.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['message' => 'Method not allowed.'], 405);
}

verify_csrf();

$partnerId = (string) ($_POST['partner_id'] ?? '');
$content = trim((string) ($_POST['content'] ?? ''));

if ($partnerId === $user['id'] || !are_friends($user['id'], $partnerId)) {
    json_response(['message' => 'Sadece arkadaşlarınla mesajlaşabilirsin.'], 403);
}

if ($content === '') {
    json_response(['message' => 'Mesaj boş olamaz.'], 400);
}

if (mb_strlen($content) > 2000) {
    json_response(['message' => 'Mesaj en fazla 2000 karakter olabilir.'], 400);
}

$pdo = db();
$conversationId = find_or_create_conversation($user['id'], $partnerId);

$messageId = uuid();
$pdo->prepare('INSERT INTO messages (id, conversation_id, sender_id, content) VALUES (?, ?, ?, ?)')
    ->execute([$messageId, $conversationId, $user['id'], $content]);

$pdo->prepare('UPDATE conversations SET updated_at = ? WHERE id = ?')
    ->execute([now_utc(), $conversationId]);

$stmt = $pdo->prepare('SELECT * FROM messages WHERE id = ?');
$stmt->execute([$messageId]);
$message = $stmt->fetch();

json_response([
    'sent_message' => $message,
    'conversation_id' => $conversationId,
]);
