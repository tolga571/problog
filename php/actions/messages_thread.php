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

$withId = (string) ($_GET['with'] ?? '');
if ($withId === $user['id'] || !are_friends($user['id'], $withId)) {
    json_response(['message' => 'Sadece arkadaşlarınla mesajlaşabilirsin.'], 403);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, name, avatar_url FROM users WHERE id = ?');
$stmt->execute([$withId]);
$partner = $stmt->fetch();
if (!$partner) {
    json_response(['message' => 'Kullanıcı bulunamadı.'], 404);
}

$one = $user['id'] < $withId ? $user['id'] : $withId;
$two = $user['id'] < $withId ? $withId : $user['id'];
$stmt = $pdo->prepare('SELECT id FROM conversations WHERE user_one_id = ? AND user_two_id = ?');
$stmt->execute([$one, $two]);
$convRow = $stmt->fetch();

$conversationId = null;
$messages = [];

if ($convRow) {
    $conversationId = $convRow['id'];
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC');
    $stmt->execute([$conversationId]);
    $messages = $stmt->fetchAll();

    $pdo->prepare(
        'UPDATE messages SET read_at = ?
         WHERE conversation_id = ? AND sender_id != ? AND read_at IS NULL'
    )->execute([now_utc(), $conversationId, $user['id']]);
}

ob_start();
render_message_thread($messages, $user['id']);
$html = ob_get_clean();

json_response([
    'partner' => $partner,
    'conversation_id' => $conversationId,
    'html' => $html,
    'count' => count($messages),
]);
