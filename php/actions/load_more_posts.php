<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/post_card.php';

$user = current_user();
if (!$user) {
    json_response(['message' => 'Oturum gerekli.'], 401);
}

$feed = ($_GET['feed'] ?? 'all') === 'following' ? 'following' : 'all';
$offset = max(0, (int) ($_GET['offset'] ?? 0));

$rows = fetch_feed_posts($feed, $user['id'], FEED_PAGE_SIZE, $offset);
$posts = array_map(fn($post) => post_with_details($post, $user['id']), $rows);

ob_start();
foreach ($posts as $post) {
    render_post_card($post, $user['id']);
}
$html = ob_get_clean();

json_response([
    'html' => $html,
    'has_more' => count($rows) === FEED_PAGE_SIZE,
]);
