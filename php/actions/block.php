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
    json_response(['message' => 'Kendini engelleyemezsin.'], 400);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$targetId]);
if (!$stmt->fetch()) {
    json_response(['message' => 'Kullanıcı bulunamadı.'], 404);
}

if (has_blocked($user['id'], $targetId)) {
    $pdo->prepare('DELETE FROM blocks WHERE blocker_id = ? AND blocked_id = ?')->execute([$user['id'], $targetId]);
    json_response(['blocked' => false]);
}

$pdo->prepare('INSERT INTO blocks (id, blocker_id, blocked_id) VALUES (?, ?, ?)')
    ->execute([uuid(), $user['id'], $targetId]);

// Engelleme, karsilikli takip/arkadaslik/konusma baglantisini da temizler -
// aksi halde engellenen kisi hala mesaj gecmisinde/arkadas listesinde
// gorunmeye devam ederdi.
$pdo->prepare('DELETE FROM follows WHERE (follower_id = ? AND following_id = ?) OR (follower_id = ? AND following_id = ?)')
    ->execute([$user['id'], $targetId, $targetId, $user['id']]);
$pdo->prepare('DELETE FROM friend_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)')
    ->execute([$user['id'], $targetId, $targetId, $user['id']]);

json_response(['blocked' => true]);
