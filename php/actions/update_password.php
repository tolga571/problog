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

$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

if ($currentPassword === '' || !password_verify($currentPassword, $user['password_hash'])) {
    flash_set('error', 'Mevcut şifren yanlış.');
    redirect('/settings_account.php');
}

if (strlen($newPassword) < 6) {
    flash_set('error', 'Yeni şifre en az 6 karakter olmalı.');
    redirect('/settings_account.php');
}

if ($newPassword !== $newPasswordConfirm) {
    flash_set('error', 'Yeni şifreler eşleşmiyor.');
    redirect('/settings_account.php');
}

$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$passwordHash, $user['id']]);

flash_set('info', 'Şifren güncellendi.');
redirect('/settings_account.php');
