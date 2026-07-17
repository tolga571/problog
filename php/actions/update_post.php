<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}

verify_csrf();

$postId = (string) ($_POST['post_id'] ?? '');

$stmt = db()->prepare('SELECT * FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post || $post['author_id'] !== $user['id']) {
    redirect('/index.php');
}

$postType = $post['post_type'] ?? 'article';
$title = trim((string) ($_POST['title'] ?? ''));
$content = normalize_content((string) ($_POST['content'] ?? ''));
$tagsInput = trim((string) ($_POST['tags'] ?? ''));

if ($postType === 'article') {
    if (mb_strlen($title) < 4 || mb_strlen($content) < 20) {
        flash_set('error', 'Başlık en az 4, içerik en az 20 karakter olmalı.');
        flash_set_old(['title' => $title, 'content' => $content, 'tags' => $tagsInput]);
        redirect('/edit_post.php?id=' . urlencode($postId));
    }

    if (mb_strlen($title) > 120 || mb_strlen($content) > 100000) {
        flash_set('error', 'Başlık en fazla 120 karakter olabilir, içerik çok uzun.');
        flash_set_old(['title' => $title, 'content' => $content, 'tags' => $tagsInput]);
        redirect('/edit_post.php?id=' . urlencode($postId));
    }
} else {
    $title = '';

    if ($content === '') {
        flash_set('error', 'Gönderi boş olamaz.');
        flash_set_old(['title' => $title, 'content' => $content, 'tags' => $tagsInput]);
        redirect('/edit_post.php?id=' . urlencode($postId));
    }

    if (mb_strlen($content) > 500) {
        flash_set('error', 'Gönderi en fazla 500 karakter olabilir.');
        flash_set_old(['title' => $title, 'content' => $content, 'tags' => $tagsInput]);
        redirect('/edit_post.php?id=' . urlencode($postId));
    }
}

try {
    $media = handle_media_upload($_FILES['image'] ?? [], 'posts');
} catch (UploadException $e) {
    flash_set('error', $e->getMessage());
    flash_set_old(['title' => $title, 'content' => $content, 'tags' => $tagsInput]);
    redirect('/edit_post.php?id=' . urlencode($postId));
}

if ($media) {
    db()->prepare('UPDATE posts SET title = ?, content = ?, image_path = ?, media_type = ?, updated_at = ? WHERE id = ?')
        ->execute([$title, $content, $media['path'], $media['type'], now_utc(), $postId]);
} else {
    db()->prepare('UPDATE posts SET title = ?, content = ?, updated_at = ? WHERE id = ?')
        ->execute([$title, $content, now_utc(), $postId]);
}

sync_post_tags($postId, $tagsInput);

redirect('/index.php');
