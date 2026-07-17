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

$messageId = (string) ($_POST['message_id'] ?? '');
$content = trim((string) ($_POST['content'] ?? ''));

if ($content === '') {
    json_response(['message' => 'Mesaj boş olamaz.'], 400);
}

if (mb_strlen($content) > 2000) {
    json_response(['message' => 'Mesaj en fazla 2000 karakter olabilir.'], 400);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM messages WHERE id = ?');
$stmt->execute([$messageId]);
$existing = $stmt->fetch();

if (!$existing || $existing['sender_id'] !== $user['id'] || $existing['deleted_at']) {
    json_response(['message' => 'Bu mesajı düzenleme yetkin yok.'], 403);
}

$pdo->prepare('UPDATE messages SET content = ?, edited_at = ? WHERE id = ?')
    ->execute([$content, now_utc(), $messageId]);

json_response(['content' => $content]);
