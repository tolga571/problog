<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/message_bubble.php';

$user = current_user();
if (!$user) {
    json_response(['message' => 'Oturum gerekli.'], 401);
}

$conversationId = (string) ($_GET['conversation_id'] ?? '');
$afterId = (string) ($_GET['after_id'] ?? '');

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM conversations WHERE id = ?');
$stmt->execute([$conversationId]);
$conversation = $stmt->fetch();

if (!$conversation || ($conversation['user_one_id'] !== $user['id'] && $conversation['user_two_id'] !== $user['id'])) {
    json_response(['message' => 'Bu sohbete erişimin yok.'], 403);
}

$partnerId = conversation_partner_id($conversation, $user['id']);
$stmt = $pdo->prepare('SELECT id, name, avatar_url FROM users WHERE id = ?');
$stmt->execute([$partnerId]);
$partner = $stmt->fetch() ?: null;

$seedDate = null;
if ($afterId !== '') {
    $stmt = $pdo->prepare(
        "SELECT * FROM messages
         WHERE conversation_id = ?
           AND created_at >= (SELECT created_at FROM messages WHERE id = ?)
           AND id != ?
         ORDER BY created_at ASC"
    );
    $stmt->execute([$conversationId, $afterId, $afterId]);

    $stmt2 = $pdo->prepare('SELECT created_at FROM messages WHERE id = ?');
    $stmt2->execute([$afterId]);
    $seedRow = $stmt2->fetch();
    $seedDate = $seedRow['created_at'] ?? null;
} else {
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC');
    $stmt->execute([$conversationId]);
}
$messages = $stmt->fetchAll();

$pdo->prepare(
    'UPDATE messages SET read_at = ?
     WHERE conversation_id = ? AND sender_id != ? AND read_at IS NULL'
)->execute([now_utc(), $conversationId, $user['id']]);

ob_start();
render_message_thread($messages, $user['id'], $partner, $seedDate);
$html = ob_get_clean();

json_response(['html' => $html, 'count' => count($messages)]);
