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
$pdo = db();

$stmt = $pdo->prepare('SELECT id, user_id, post_id FROM comments WHERE id = ?');
$stmt->execute([$commentId]);
$comment = $stmt->fetch();
if (!$comment) {
    json_response(['message' => 'Yorum bulunamadı.'], 404);
}

$stmt = $pdo->prepare('SELECT id FROM comment_likes WHERE user_id = ? AND comment_id = ?');
$stmt->execute([$user['id'], $commentId]);
$existing = $stmt->fetch();

if ($existing) {
    $pdo->prepare('DELETE FROM comment_likes WHERE id = ?')->execute([$existing['id']]);
    $liked = false;
} else {
    $pdo->prepare('INSERT INTO comment_likes (id, user_id, comment_id) VALUES (?, ?, ?)')
        ->execute([uuid(), $user['id'], $commentId]);
    $liked = true;
    create_notification($comment['user_id'], $user['id'], 'comment_like', $comment['post_id']);
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM comment_likes WHERE comment_id = ?');
$stmt->execute([$commentId]);
$likesCount = (int) $stmt->fetchColumn();

json_response(['liked' => $liked, 'likes_count' => $likesCount]);
