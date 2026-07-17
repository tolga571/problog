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

$pdo = db();

$stmt = $pdo->prepare(
    "SELECT c.*,
        (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_content,
        (SELECT deleted_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_deleted_at,
        (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_created_at,
        (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != ? AND read_at IS NULL AND deleted_at IS NULL) AS unread_count
     FROM conversations c
     WHERE c.user_one_id = ? OR c.user_two_id = ?
     ORDER BY c.updated_at DESC"
);
$stmt->execute([$user['id'], $user['id'], $user['id']]);
$rows = $stmt->fetchAll();

$conversations = [];
$unreadTotal = 0;
foreach ($rows as $row) {
    $partnerId = conversation_partner_id($row, $user['id']);
    $stmt = $pdo->prepare('SELECT id, name, avatar_url FROM users WHERE id = ?');
    $stmt->execute([$partnerId]);
    $partner = $stmt->fetch();
    if (!$partner) {
        continue;
    }

    $unreadCount = (int) $row['unread_count'];
    $unreadTotal += $unreadCount;

    $conversations[] = [
        'partner' => $partner,
        'preview' => $row['last_deleted_at'] ? 'Bu mesaj silindi' : (string) ($row['last_content'] ?? ''),
        'time_ago' => $row['last_created_at'] ? time_ago($row['last_created_at']) : '',
        'unread_count' => $unreadCount,
    ];
}

json_response(['conversations' => $conversations, 'unread_total' => $unreadTotal]);
