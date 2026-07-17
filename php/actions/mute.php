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
    json_response(['message' => 'Kendini sessize alamazsın.'], 400);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$targetId]);
if (!$stmt->fetch()) {
    json_response(['message' => 'Kullanıcı bulunamadı.'], 404);
}

if (is_muted($user['id'], $targetId)) {
    $pdo->prepare('DELETE FROM mutes WHERE muter_id = ? AND muted_id = ?')->execute([$user['id'], $targetId]);
    json_response(['muted' => false]);
}

$pdo->prepare('INSERT INTO mutes (id, muter_id, muted_id) VALUES (?, ?, ?)')
    ->execute([uuid(), $user['id'], $targetId]);

json_response(['muted' => true]);
