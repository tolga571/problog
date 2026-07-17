<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/settings_account.php');
}

verify_csrf();

$newUsername = mb_strtolower(trim((string) ($_POST['username'] ?? '')), 'UTF-8');

if (!preg_match('/^[a-z0-9_]{3,20}$/', $newUsername)) {
    flash_set('error', 'Kullanıcı adı 3-20 karakter olmalı ve sadece küçük harf, rakam, alt çizgi içermeli.');
    redirect('/settings_account.php');
}

$pdo = db();

if ($newUsername !== $user['username']) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
    $stmt->execute([$newUsername, $user['id']]);
    if ($stmt->fetch()) {
        flash_set('error', 'Bu kullanıcı adı zaten alınmış.');
        redirect('/settings_account.php');
    }

    $pdo->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$newUsername, $user['id']]);
}

flash_set('info', 'Kullanıcı adın güncellendi.');
redirect('/settings_account.php');
