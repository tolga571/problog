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

$stmt = $pdo->prepare('SELECT user_id, post_id FROM comments WHERE id = ?');
$stmt->execute([$commentId]);
$comment = $stmt->fetch();

if (!$comment || $comment['user_id'] !== $user['id']) {
    json_response(['message' => 'Bu yorumu silme yetkin yok.'], 403);
}

$pdo->prepare(
    'DELETE FROM comments WHERE id IN (
        WITH RECURSIVE tree(id) AS (
            SELECT id FROM comments WHERE id = :root
            UNION ALL
            SELECT c.id FROM comments c JOIN tree t ON c.parent_id = t.id
        )
        SELECT id FROM tree
    )'
)->execute(['root' => $commentId]);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE post_id = ?');
$stmt->execute([$comment['post_id']]);
$commentsCount = (int) $stmt->fetchColumn();

json_response(['comments_count' => $commentsCount]);
