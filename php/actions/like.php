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
$pdo = db();

$stmt = $pdo->prepare('SELECT id, author_id FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();
if (!$post) {
    json_response(['message' => 'Yazı bulunamadı.'], 404);
}

$stmt = $pdo->prepare('SELECT id FROM likes WHERE user_id = ? AND post_id = ?');
$stmt->execute([$user['id'], $postId]);
$existing = $stmt->fetch();

if ($existing) {
    $pdo->prepare('DELETE FROM likes WHERE id = ?')->execute([$existing['id']]);
    $liked = false;
} else {
    $pdo->prepare('INSERT INTO likes (id, user_id, post_id) VALUES (?, ?, ?)')
        ->execute([uuid(), $user['id'], $postId]);
    $liked = true;
    create_notification($post['author_id'], $user['id'], 'like', $postId);
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM likes WHERE post_id = ?');
$stmt->execute([$postId]);
$likesCount = (int) $stmt->fetchColumn();

json_response(['liked' => $liked, 'likes_count' => $likesCount]);
