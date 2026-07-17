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

if ($currentPassword === '' || !password_verify($currentPassword, $user['password_hash'])) {
    flash_set('error', 'Hesabını silmek için şifreni doğru girmelisin.');
    redirect('/settings_account.php');
}

db()->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]);

logout_user();
redirect('/login.php?deleted=1');
