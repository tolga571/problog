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

$newEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
$currentPassword = (string) ($_POST['current_password'] ?? '');

if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'Geçerli bir e-posta adresi gir.');
    redirect('/settings_account.php');
}

if ($currentPassword === '' || !password_verify($currentPassword, $user['password_hash'])) {
    flash_set('error', 'Mevcut şifren yanlış.');
    redirect('/settings_account.php');
}

$pdo = db();

if ($newEmail !== $user['email']) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$newEmail, $user['id']]);
    if ($stmt->fetch()) {
        flash_set('error', 'Bu e-posta zaten kullanımda.');
        redirect('/settings_account.php');
    }

    $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$newEmail, $user['id']]);
}

flash_set('info', 'E-posta adresin güncellendi.');
redirect('/settings_account.php');
