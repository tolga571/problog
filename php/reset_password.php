<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (current_user()) {
    redirect('/index.php');
}

function find_reset(PDO $pdo, string $token): ?array
{
    if ($token === '') {
        return null;
    }
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token_hash = ? AND expires_at > datetime('now')");
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();
    return $row ?: null;
}

$pdo = db();
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$error = flash_get('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $reset = find_reset($pdo, $token);
    if (!$reset) {
        flash_set('error', 'Bağlantı geçersiz veya süresi dolmuş. Yeni bir sıfırlama bağlantısı iste.');
        redirect('/forgot_password.php');
    }

    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    if (strlen($password) < 6) {
        flash_set('error', 'Şifre en az 6 karakter olmalı.');
        redirect('/reset_password.php?token=' . urlencode($token));
    }

    if ($password !== $passwordConfirm) {
        flash_set('error', 'Şifreler eşleşmiyor.');
        redirect('/reset_password.php?token=' . urlencode($token));
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$passwordHash, $reset['user_id']]);
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$reset['user_id']]);

    flash_set('info', 'Şifren güncellendi. Şimdi yeni şifrenle giriş yapabilirsin.');
    redirect('/login.php');
}

$reset = find_reset($pdo, $token);
$pageValid = $reset !== null;
?>
<!doctype html>
<html lang="tr" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Lora:ital,wght@0,500;0,600;1,500&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>" />
  <title>Şifre Sıfırla - ProBlog</title>
</head>
<body>
  <div class="min-h-screen auth-gradient flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <div class="bg-surface-2 border border-border rounded-3xl overflow-hidden shadow-2xl shadow-black/40">
        <div class="h-1.5" style="background:linear-gradient(to right,var(--color-accent),#d68b5e)"></div>
        <div class="p-8 sm:p-10">
          <?php if (!$pageValid): ?>
            <div class="text-center mb-6">
              <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-red-500/10 mb-4">
                <span class="material-symbols-outlined text-3xl text-red-400">error</span>
              </div>
              <h2 class="text-2xl font-bold text-white mb-1">Bağlantı geçersiz</h2>
              <p class="text-muted text-sm">Bu sıfırlama bağlantısının süresi dolmuş ya da daha önce kullanılmış.</p>
            </div>
            <a href="/forgot_password.php" class="btn-primary w-full flex items-center justify-center gap-2 py-3.5">
              Yeni bağlantı iste
            </a>
          <?php else: ?>
            <div class="text-center mb-8">
              <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-accent/10 mb-4 shadow-lg shadow-accent-glow">
                <span class="material-symbols-outlined text-3xl text-accent">lock_reset</span>
              </div>
              <h2 class="text-2xl font-bold text-white mb-1">Yeni Şifre Belirle</h2>
              <p class="text-muted text-sm">Hesabın için yeni bir şifre gir</p>
            </div>

            <form class="space-y-5" method="post" action="/reset_password.php">
              <?= csrf_field() ?>
              <input type="hidden" name="token" value="<?= h($token) ?>" />

              <div>
                <label for="new-password-field" class="block text-sm font-medium text-muted mb-1.5">Yeni Şifre</label>
                <div class="relative">
                  <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-2 text-xl">lock</span>
                  <input type="password" id="new-password-field" class="input-field pl-11" name="password" placeholder="En az 6 karakter" minlength="6" autocomplete="new-password" required />
                </div>
              </div>

              <div>
                <label for="new-password-confirm-field" class="block text-sm font-medium text-muted mb-1.5">Yeni Şifre Tekrar</label>
                <div class="relative">
                  <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-2 text-xl">lock</span>
                  <input type="password" id="new-password-confirm-field" class="input-field pl-11" name="password_confirm" placeholder="Şifreni tekrar gir" minlength="6" autocomplete="new-password" required />
                </div>
                <p id="password-mismatch-hint" class="text-red-400 text-xs mt-1.5 hidden">Şifreler eşleşmiyor.</p>
              </div>

              <?php if ($error): ?>
                <p class="text-red-400 text-sm bg-red-500/10 border border-red-500/20 px-4 py-3 rounded-xl flex items-center gap-2">
                  <span class="material-symbols-outlined text-lg">error</span>
                  <?= h($error) ?>
                </p>
              <?php endif; ?>

              <button type="submit" class="btn-primary w-full flex items-center justify-center gap-2 py-3.5">
                Şifreyi Güncelle
              </button>
            </form>
            <script>
              const newPasswordField = document.getElementById('new-password-field');
              const newPasswordConfirmField = document.getElementById('new-password-confirm-field');
              const newPasswordMismatchHint = document.getElementById('password-mismatch-hint');
              const resetForm = newPasswordField.closest('form');

              function checkNewPasswordsMatch() {
                const mismatch = newPasswordConfirmField.value !== '' && newPasswordField.value !== newPasswordConfirmField.value;
                newPasswordMismatchHint.classList.toggle('hidden', !mismatch);
                newPasswordConfirmField.setCustomValidity(mismatch ? 'Şifreler eşleşmiyor.' : '');
                return !mismatch;
              }

              newPasswordField.addEventListener('input', checkNewPasswordsMatch);
              newPasswordConfirmField.addEventListener('input', checkNewPasswordsMatch);
              resetForm.addEventListener('submit', (event) => {
                if (!checkNewPasswordsMatch()) {
                  event.preventDefault();
                }
              });
            </script>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
