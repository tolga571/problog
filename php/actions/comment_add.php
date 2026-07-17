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

$postId = (string) ($_POST['post_id'] ?? '');
$parentId = (string) ($_POST['parent_id'] ?? '');
$content = normalize_content((string) ($_POST['content'] ?? ''));

if ($content === '') {
    json_response(['message' => 'Yorum içeriği zorunludur.'], 400);
}

if (mb_strlen($content) > 500) {
    json_response(['message' => 'Yorum en fazla 500 karakter olabilir.'], 400);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, author_id FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();
if (!$post) {
    json_response(['message' => 'Yazı bulunamadı.'], 404);
}

$parentComment = null;
if ($parentId !== '') {
    $stmt = $pdo->prepare('SELECT id, user_id, post_id FROM comments WHERE id = ?');
    $stmt->execute([$parentId]);
    $parentComment = $stmt->fetch();

    if (!$parentComment || $parentComment['post_id'] !== $postId) {
        json_response(['message' => 'Yanıt verilecek yorum bulunamadı.'], 404);
    }
}

$commentId = uuid();
$pdo->prepare('INSERT INTO comments (id, user_id, post_id, parent_id, content) VALUES (?, ?, ?, ?, ?)')
    ->execute([$commentId, $user['id'], $postId, $parentComment ? $parentComment['id'] : null, $content]);

// Dedupe kuralı: bir yanıt sadece üst yorumun yazarına 'comment_reply'
// bildirimi gönderir, post yazarına ayrıca 'comment' gitmez (thread
// başladığında zaten bir bildirim almıştı).
$alreadyNotified = [$user['id']];
if ($parentComment) {
    create_notification($parentComment['user_id'], $user['id'], 'comment_reply', $postId);
    $alreadyNotified[] = $parentComment['user_id'];
} else {
    create_notification($post['author_id'], $user['id'], 'comment', $postId);
    $alreadyNotified[] = $post['author_id'];
}

$friendIds = friend_ids($user['id']);
foreach (extract_mentions($content) as $mention) {
    if (in_array($mention['id'], $alreadyNotified, true)) {
        continue;
    }
    if (!in_array($mention['id'], $friendIds, true)) {
        continue;
    }
    create_notification($mention['id'], $user['id'], 'mention', $postId);
    $alreadyNotified[] = $mention['id'];
}

$stmt = $pdo->prepare('SELECT c.*, u.name, u.avatar_url FROM comments c JOIN users u ON c.user_id = u.id WHERE c.id = ?');
$stmt->execute([$commentId]);
$comment = $stmt->fetch();
$comment['content_html'] = render_comment_content($comment['content']);
$comment['time_ago'] = time_ago($comment['created_at']);
$comment['full_date'] = format_date($comment['created_at']);
$comment = array_merge($comment, comment_like_state($comment['id'], $user['id']));

$stmt = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE post_id = ?');
$stmt->execute([$postId]);
$commentsCount = (int) $stmt->fetchColumn();

json_response(['comment' => $comment, 'comments_count' => $commentsCount], 201);
