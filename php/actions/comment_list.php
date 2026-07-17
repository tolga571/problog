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

$postId = (string) ($_GET['post_id'] ?? '');

$stmt = db()->prepare('SELECT c.*, u.name, u.avatar_url FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = ? ORDER BY c.created_at ASC');
$stmt->execute([$postId]);

$comments = array_map(function (array $c) use ($user) {
    $c['content_html'] = render_comment_content($c['content']);
    $c['time_ago'] = time_ago($c['created_at']);
    $c['full_date'] = format_date($c['created_at']);
    return array_merge($c, comment_like_state($c['id'], $user['id']));
}, $stmt->fetchAll());

json_response(['comments' => $comments]);
