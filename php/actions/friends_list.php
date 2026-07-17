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

$ids = friend_ids($user['id']);
$friends = [];

if (count($ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT id, name, avatar_url FROM users WHERE id IN ($placeholders) ORDER BY name"
    );
    $stmt->execute($ids);
    $friends = $stmt->fetchAll();
}

json_response(['friends' => $friends]);
