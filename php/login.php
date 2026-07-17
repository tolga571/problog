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
$info = flash_get('info') ?? (($_GET['deleted'] ?? '') === '1' ? 'Hesabın kalıcı olarak silindi.' : null);
$old = flash_old('login');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $old = ['email' => $email];

    if ($email === '' || $password === '') {
        flash_set('error', 'E-posta ve şifre zorunludur.');
        flash_set_old($old);
        redirect('/login.php');
    }

    $lockedSeconds = login_locked_seconds($email);
    if ($lockedSeconds !== null) {
        $minutes = (int) ceil($lockedSeconds / 60);
        flash_set('error', "Çok fazla başarısız deneme yapıldı. Lütfen {$minutes} dakika sonra tekrar deneyin.");
        flash_set_old($old);
        redirect('/login.php');
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        login_register_failure($email);
        flash_set('error', 'E-posta veya şifre hatalı.');
        flash_set_old($old);
        redirect('/login.php');
    }

    login_clear_attempts($email);
    login_user($user['id']);
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
  <title>Giriş Yap - ProBlog</title>
</head>
<body>
  <div class="min-h-screen auth-gradient flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <div class="bg-surface-2 border border-border rounded-3xl overflow-hidden shadow-2xl shadow-black/40">
        <div class="h-1.5" style="background:linear-gradient(to right,var(--color-accent),#d68b5e)"></div>
        <div class="p-8 sm:p-10">
          <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-accent/10 mb-4 shadow-lg shadow-accent-glow">
              <span class="material-symbols-outlined text-3xl text-accent">lock</span>
            </div>
            <h2 class="text-2xl font-bold text-white mb-1">Tekrar Hoşgeldin</h2>
            <p class="text-muted text-sm">Hesabına giriş yaparak devam et</p>
          </div>

          <?php if ($info): ?>
            <p class="text-green-400 text-sm bg-surface-3 border border-border px-4 py-3 rounded-xl mb-5">
              <?= h($info) ?>
            </p>
          <?php endif; ?>

          <form class="space-y-5" method="post" action="/login.php">
            <?= csrf_field() ?>
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
                <input type="password" id="password-field" class="input-field pl-11 pr-11" name="password" placeholder="Şifreni gir" autocomplete="current-password" required />
                <button type="button" id="password-toggle" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-muted-2 hover:text-white transition-colors">
                  <span class="material-symbols-outlined text-xl" id="password-toggle-icon">visibility</span>
                </button>
              </div>
            </div>

            <?php if ($error): ?>
              <p class="text-red-400 text-sm bg-red-500/10 border border-red-500/20 px-4 py-3 rounded-xl flex items-center gap-2">
                <span class="material-symbols-outlined text-lg">error</span>
                <?= h($error) ?>
              </p>
            <?php endif; ?>

            <button type="submit" class="btn-primary w-full flex items-center justify-center gap-2 py-3.5">
              Giriş Yap
              <span class="material-symbols-outlined text-lg">arrow_forward</span>
            </button>
          </form>

          <p class="mt-4 text-center text-sm">
            <a href="/forgot_password.php" class="text-muted hover:text-white transition-colors">Şifremi unuttum</a>
          </p>

          <p class="mt-4 text-center text-sm text-muted">
            Hesabın yok mu?
            <a href="/register.php" class="text-accent hover:text-accent-hover font-semibold hover:underline transition-colors">Kayıt ol</a>
          </p>
        </div>
      </div>
    </div>
  </div>
  <script>
    document.getElementById('password-toggle').addEventListener('click', () => {
      const input = document.getElementById('password-field');
      const icon = document.getElementById('password-toggle-icon');
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      icon.textContent = isPassword ? 'visibility_off' : 'visibility';
    });
  </script>
</body>
</html>
