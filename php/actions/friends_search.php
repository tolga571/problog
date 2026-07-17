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

$q = trim((string) ($_GET['q'] ?? ''));
$ids = friend_ids($user['id']);

if ($q === '' || count($ids) === 0) {
    json_response(['users' => []]);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = db()->prepare(
    "SELECT id, name, avatar_url FROM users WHERE id IN ($placeholders) AND LOWER(name) LIKE LOWER(?) ORDER BY name LIMIT 10"
);
$stmt->execute([...$ids, '%' . $q . '%']);

json_response(['users' => $stmt->fetchAll()]);
