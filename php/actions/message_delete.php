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

$pdo = db();
$stmt = $pdo->prepare('SELECT sender_id FROM messages WHERE id = ?');
$stmt->execute([$messageId]);
$existing = $stmt->fetch();

if (!$existing || $existing['sender_id'] !== $user['id']) {
    json_response(['message' => 'Bu mesajı silme yetkin yok.'], 403);
}

$pdo->prepare("UPDATE messages SET deleted_at = datetime('now') WHERE id = ?")->execute([$messageId]);

json_response(['ok' => true]);
