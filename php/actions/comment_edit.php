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

$commentId = (string) ($_POST['comment_id'] ?? '');
$content = normalize_content((string) ($_POST['content'] ?? ''));

if ($content === '') {
    json_response(['message' => 'Yorum içeriği zorunludur.'], 400);
}

if (mb_strlen($content) > 500) {
    json_response(['message' => 'Yorum en fazla 500 karakter olabilir.'], 400);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM comments WHERE id = ?');
$stmt->execute([$commentId]);
$existing = $stmt->fetch();

if (!$existing || $existing['user_id'] !== $user['id']) {
    json_response(['message' => 'Bu yorumu düzenleme yetkin yok.'], 403);
}

$pdo->prepare('UPDATE comments SET content = ?, edited_at = ? WHERE id = ?')
    ->execute([$content, now_utc(), $commentId]);

// Düzenlenen içerikte yeni mention'lar da bildirim tetikler (mesaj
// düzenlemede olduğu gibi, kaldırılan eski mention'lar için
// "bildirimi geri al" gibi bir diffing yapılmıyor).
$alreadyNotified = [$user['id']];
if (!empty($existing['parent_id'])) {
    $stmt = $pdo->prepare('SELECT user_id FROM comments WHERE id = ?');
    $stmt->execute([$existing['parent_id']]);
    $parentAuthor = $stmt->fetchColumn();
    if ($parentAuthor) {
        $alreadyNotified[] = $parentAuthor;
    }
} else {
    $stmt = $pdo->prepare('SELECT author_id FROM posts WHERE id = ?');
    $stmt->execute([$existing['post_id']]);
    $postAuthor = $stmt->fetchColumn();
    if ($postAuthor) {
        $alreadyNotified[] = $postAuthor;
    }
}

$friendIds = friend_ids($user['id']);
foreach (extract_mentions($content) as $mention) {
    if (in_array($mention['id'], $alreadyNotified, true)) {
        continue;
    }
    if (!in_array($mention['id'], $friendIds, true)) {
        continue;
    }
    create_notification($mention['id'], $user['id'], 'mention', $existing['post_id']);
    $alreadyNotified[] = $mention['id'];
}

json_response(['content' => $content, 'content_html' => render_comment_content($content)]);
