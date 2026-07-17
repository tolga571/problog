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

$stmt = db()->prepare('SELECT author_id FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();

if ($post && $post['author_id'] === $user['id']) {
    db()->prepare("UPDATE posts SET status = 'published' WHERE id = ?")->execute([$postId]);
}

redirect($_SERVER['HTTP_REFERER'] ?? '/index.php');
