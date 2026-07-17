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

$postType = ($_POST['post_type'] ?? '') === 'article' ? 'article' : 'post';
$title = trim((string) ($_POST['title'] ?? ''));
$content = normalize_content((string) ($_POST['content'] ?? ''));
$tagsInput = trim((string) ($_POST['tags'] ?? ''));
$status = ($_POST['status'] ?? '') === 'draft' && $postType === 'article' ? 'draft' : 'published';
$returnTo = in_array($_POST['return_to'] ?? '', ['/index.php', '/new_post.php'], true) ? $_POST['return_to'] : '/new_post.php';

$oldData = ['post_type' => $postType, 'title' => $title, 'content' => $content, 'tags' => $tagsInput];

if ($postType === 'article') {
    if (mb_strlen($title) < 4 || mb_strlen($content) < 20) {
        flash_set('composer_error', 'Başlık en az 4, içerik en az 20 karakter olmalı.');
        flash_set_old($oldData);
        redirect($returnTo);
    }

    if (mb_strlen($title) > 120 || mb_strlen($content) > 100000) {
        flash_set('composer_error', 'Başlık en fazla 120 karakter olabilir, içerik çok uzun.');
        flash_set_old($oldData);
        redirect($returnTo);
    }
} else {
    $title = '';

    if ($content === '') {
        flash_set('composer_error', 'Gönderi boş olamaz.');
        flash_set_old($oldData);
        redirect($returnTo);
    }

    if (mb_strlen($content) > 500) {
        flash_set('composer_error', 'Gönderi en fazla 500 karakter olabilir.');
        flash_set_old($oldData);
        redirect($returnTo);
    }
}

try {
    $media = handle_media_upload($_FILES['image'] ?? [], 'posts');
} catch (UploadException $e) {
    flash_set('composer_error', $e->getMessage());
    flash_set_old($oldData);
    redirect($returnTo);
}

$postId = uuid();
db()->prepare('INSERT INTO posts (id, author_id, title, content, image_path, media_type, status, post_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([$postId, $user['id'], $title, $content, $media['path'] ?? '', $media['type'] ?? '', $status, $postType]);

sync_post_tags($postId, $tagsInput);

redirect('/index.php');
