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
$action = (string) ($_POST['action'] ?? '');

if ($targetId === $user['id']) {
    json_response(['message' => 'Kendinle arkadaş olamazsın.'], 400);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$targetId]);
if (!$stmt->fetch()) {
    json_response(['message' => 'Kullanıcı bulunamadı.'], 404);
}

if (are_blocked($user['id'], $targetId)) {
    json_response(['message' => 'Bu kullanıcıyla etkileşim kuramazsın.'], 403);
}

$row = friendship_row($user['id'], $targetId);

switch ($action) {
    case 'request':
        if ($row && $row['status'] === 'accepted') {
            json_response(['status' => 'friends']);
        }

        if ($row && $row['sender_id'] === $targetId) {
            // Karşı taraf zaten istek göndermiş: kabul etmiş oluyoruz.
            $pdo->prepare("UPDATE friend_requests SET status = 'accepted', responded_at = ? WHERE id = ?")
                ->execute([now_utc(), $row['id']]);
            create_notification($targetId, $user['id'], 'friend_accept');
            json_response(['status' => 'friends']);
        }

        if ($row) {
            json_response(['status' => 'pending_sent']);
        }

        $pdo->prepare('INSERT INTO friend_requests (id, sender_id, receiver_id) VALUES (?, ?, ?)')
            ->execute([uuid(), $user['id'], $targetId]);
        create_notification($targetId, $user['id'], 'friend_request');
        json_response(['status' => 'pending_sent']);
        break;

    case 'accept':
        if (!$row || $row['status'] !== 'pending' || $row['sender_id'] !== $targetId) {
            json_response(['message' => 'Bekleyen bir istek bulunamadı.'], 400);
        }
        $pdo->prepare("UPDATE friend_requests SET status = 'accepted', responded_at = ? WHERE id = ?")
            ->execute([now_utc(), $row['id']]);
        create_notification($targetId, $user['id'], 'friend_accept');
        json_response(['status' => 'friends']);
        break;

    case 'decline':
    case 'cancel':
    case 'remove':
        if ($row) {
            $pdo->prepare('DELETE FROM friend_requests WHERE id = ?')->execute([$row['id']]);
        }
        json_response(['status' => 'none']);
        break;

    default:
        json_response(['message' => 'Geçersiz işlem.'], 400);
}
