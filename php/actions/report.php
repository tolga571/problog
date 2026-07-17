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
$reason = trim((string) ($_POST['reason'] ?? ''));

if ($targetId === $user['id']) {
    json_response(['message' => 'Kendini şikayet edemezsin.'], 400);
}

if (mb_strlen($reason) > 500) {
    json_response(['message' => 'Şikayet metni en fazla 500 karakter olabilir.'], 400);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$targetId]);
if (!$stmt->fetch()) {
    json_response(['message' => 'Kullanıcı bulunamadı.'], 404);
}

$pdo->prepare('INSERT INTO reports (id, reporter_id, reported_id, reason) VALUES (?, ?, ?, ?)')
    ->execute([uuid(), $user['id'], $targetId, $reason]);

json_response(['reported' => true]);
