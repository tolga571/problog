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

$targetId = (string) ($_POST['target_id'] ?? '');

if ($targetId === $user['id']) {
    json_response(['message' => 'Kendinizi takip edemezsiniz.'], 400);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$targetId]);
if (!$stmt->fetch()) {
    json_response(['message' => 'Kullanıcı bulunamadı.'], 404);
}

$stmt = $pdo->prepare('SELECT id FROM follows WHERE follower_id = ? AND following_id = ?');
$stmt->execute([$user['id'], $targetId]);
$existing = $stmt->fetch();

if ($existing) {
    $pdo->prepare('DELETE FROM follows WHERE id = ?')->execute([$existing['id']]);
    $following = false;
} else {
    if (are_blocked($user['id'], $targetId)) {
        json_response(['message' => 'Bu kullanıcıyla etkileşim kuramazsın.'], 403);
    }
    $pdo->prepare('INSERT INTO follows (id, follower_id, following_id) VALUES (?, ?, ?)')
        ->execute([uuid(), $user['id'], $targetId]);
    $following = true;
    create_notification($targetId, $user['id'], 'follow');
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM follows WHERE following_id = ?');
$stmt->execute([$targetId]);
$followersCount = (int) $stmt->fetchColumn();

json_response(['following' => $following, 'followers_count' => $followersCount]);
