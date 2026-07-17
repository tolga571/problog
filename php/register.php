<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (current_user()) {
    redirect('/index.php');
}

$error = flash_get('error');
$old = flash_old('register');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        // Honeypot dolu geldi: bot. Kullanıcıyı bilgilendirmeden sessizce düş.
        redirect('/login.php');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    $bio = trim((string) ($_POST['bio'] ?? ''));

    $old = ['name' => $name, 'email' => $email, 'bio' => $bio];

    if ($name === '' || $email === '' || $password === '') {
        flash_set('error', 'Ad, e-posta ve şifre zorunludur.');
        flash_set_old($old);
        redirect('/register.php');
    }

    if (mb_strlen($name) > 80 || mb_strlen($bio) > 280) {
        flash_set('error', 'Ad en fazla 80, biyografi en fazla 280 karakter olabilir.');
        flash_set_old($old);
        redirect('/register.php');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_set('error', 'Geçerli bir e-posta adresi gir.');
        flash_set_old($old);
        redirect('/register.php');
    }

    if (strlen($password) < 6) {
        flash_set('error', 'Şifre en az 6 karakter olmalı.');
        flash_set_old($old);
        redirect('/register.php');
    }

    if ($password !== $passwordConfirm) {
        flash_set('error', 'Şifreler eşleşmiyor.');
        flash_set_old($old);
        redirect('/register.php');
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        flash_set('error', 'Bu e-posta zaten kayıtlı.');
        flash_set_old($old);
        redirect('/register.php');
    }

    $userId = uuid();
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->prepare('INSERT INTO users (id, name, email, bio, password_hash) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $name, $email, $bio, $passwordHash]);

    login_user($userId);
    redirect('/index.php');
}
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
  <title>Hesap Oluştur - ProBlog</title>
</head>
<body>
  <div class="min-h-screen auth-gradient flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <div class="bg-surface-2 border border-border rounded-3xl overflow-hidden shadow-2xl shadow-black/40">
        <div class="h-1.5" style="background:linear-gradient(to right,var(--color-accent),#d68b5e)"></div>
        <div class="p-8 sm:p-10">
          <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-accent/10 mb-4 shadow-lg shadow-accent-glow">
              <span class="material-symbols-outlined text-3xl text-accent">person_add</span>
            </div>
            <h2 class="text-2xl font-bold text-white mb-1">Hesap Oluştur</h2>
            <p class="text-muted text-sm">Topluluğa katıl ve paylaşmaya başla</p>
          </div>

          <form class="space-y-5" method="post" action="/register.php">
            <?= csrf_field() ?>
            <div style="position:absolute;left:-9999px" aria-hidden="true">
              <label for="website">Bu alanı boş bırakın</label>
              <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" />
            </div>
            <div>
              <label for="name-field" class="block text-sm font-medium text-muted mb-1.5">Ad Soyad</label>
              <div class="relative">
                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-2 text-xl">person</span>
                <input id="name-field" class="input-field pl-11" name="name" value="<?= h($old['name'] ?? '') ?>" placeholder="Adınız Soyadınız" maxlength="80" autocomplete="name" required />
              </div>
            </div>

            <div>
              <label for="email-field" class="block text-sm font-medium text-muted mb-1.5">E-posta</label>
              <div class="relative">
                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-2 text-xl">mail</span>
                <input type="email" id="email-field" class="input-field pl-11" name="email" value="<?= h($old['email'] ?? '') ?>" placeholder="örnek@email.com" autocomplete="email" required />
              </div>
            </div>

            <div>
              <label for="password-field" class="block text-sm font-medium text-muted mb-1.5">Şifre</label>
              <div class="relative">
                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-2 text-xl">lock</span>
                <input type="password" id="password-field" class="input-field pl-11 pr-11" name="password" placeholder="En az 6 karakter" minlength="6" autocomplete="new-password" required />
                <button type="button" id="password-toggle" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-muted-2 hover:text-white transition-colors">
                  <span class="material-symbols-outlined text-xl" id="password-toggle-icon">visibility</span>
                </button>
              </div>
            </div>

            <div>
              <label for="password-confirm-field" class="block text-sm font-medium text-muted mb-1.5">Şifre Tekrar</label>
              <div class="relative">
                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-2 text-xl">lock</span>
                <input type="password" id="password-confirm-field" class="input-field pl-11 pr-11" name="password_confirm" placeholder="Şifreni tekrar gir" minlength="6" autocomplete="new-password" required />
                <button type="button" id="password-confirm-toggle" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-muted-2 hover:text-white transition-colors">
                  <span class="material-symbols-outlined text-xl" id="password-confirm-toggle-icon">visibility</span>
                </button>
              </div>
              <p id="password-mismatch-hint" class="text-red-400 text-xs mt-1.5 hidden">Şifreler eşleşmiyor.</p>
            </div>

            <div>
              <label for="bio-field" class="block text-sm font-medium text-muted mb-1.5">Biyografi</label>
              <textarea id="bio-field" class="input-field resize-none" name="bio" placeholder="Kendinden kısaca bahset..." rows="3" maxlength="280"><?= h($old['bio'] ?? '') ?></textarea>
            </div>

            <?php if ($error): ?>
              <p class="text-red-400 text-sm bg-red-500/10 border border-red-500/20 px-4 py-3 rounded-xl flex items-center gap-2">
                <span class="material-symbols-outlined text-lg">error</span>
                <?= h($error) ?>
              </p>
            <?php endif; ?>

            <button type="submit" class="btn-primary w-full flex items-center justify-center gap-2 py-3.5">
              Hesap Oluştur
              <span class="material-symbols-outlined text-lg">arrow_forward</span>
            </button>
          </form>

          <p class="mt-8 text-center text-sm text-muted">
            Zaten hesabın var mı?
            <a href="/login.php" class="text-accent hover:text-accent-hover font-semibold hover:underline transition-colors">Giriş yap</a>
          </p>
        </div>
      </div>
    </div>
  </div>
  <script>
    function wireToggle(buttonId, inputId, iconId) {
      document.getElementById(buttonId).addEventListener('click', () => {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        icon.textContent = isPassword ? 'visibility_off' : 'visibility';
      });
    }
    wireToggle('password-toggle', 'password-field', 'password-toggle-icon');
    wireToggle('password-confirm-toggle', 'password-confirm-field', 'password-confirm-toggle-icon');

    const passwordField = document.getElementById('password-field');
    const passwordConfirmField = document.getElementById('password-confirm-field');
    const mismatchHint = document.getElementById('password-mismatch-hint');
    const form = passwordField.closest('form');

    function checkPasswordsMatch() {
      const mismatch = passwordConfirmField.value !== '' && passwordField.value !== passwordConfirmField.value;
      mismatchHint.classList.toggle('hidden', !mismatch);
      passwordConfirmField.setCustomValidity(mismatch ? 'Şifreler eşleşmiyor.' : '');
      return !mismatch;
    }

    passwordField.addEventListener('input', checkPasswordsMatch);
    passwordConfirmField.addEventListener('input', checkPasswordsMatch);
    form.addEventListener('submit', (event) => {
      if (!checkPasswordsMatch()) {
        event.preventDefault();
      }
    });
  </script>
</body>
</html>
