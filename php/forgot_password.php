<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (current_user()) {
    redirect('/index.php');
}

$info = flash_get('info');
$devResetLink = flash_get('dev_reset_link');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $pdo = db();

    if ($email !== '') {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$user['id']]);

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + 1800);

            $pdo->prepare('INSERT INTO password_resets (id, user_id, token_hash, expires_at) VALUES (?, ?, ?, ?)')
                ->execute([uuid(), $user['id'], $tokenHash, $expiresAt]);

            if (!IS_PRODUCTION) {
                flash_set('dev_reset_link', '/reset_password.php?token=' . $token);
            }
        }
    }

    flash_set('info', 'Bu e-posta adresi sistemde kayıtlıysa, şifre sıfırlama bağlantısı hazırlandı.');
    redirect('/forgot_password.php');
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
  <title>Şifremi Unuttum - ProBlog</title>
</head>
<body>
  <div class="min-h-screen auth-gradient flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <div class="bg-surface-2 border border-border rounded-3xl overflow-hidden shadow-2xl shadow-black/40">
        <div class="h-1.5" style="background:linear-gradient(to right,var(--color-accent),#d68b5e)"></div>
        <div class="p-8 sm:p-10">
          <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-accent/10 mb-4 shadow-lg shadow-accent-glow">
              <span class="material-symbols-outlined text-3xl text-accent">key</span>
            </div>
            <h2 class="text-2xl font-bold text-white mb-1">Şifremi Unuttum</h2>
            <p class="text-muted text-sm">E-posta adresini gir, şifreni sıfırlaman için bir bağlantı hazırlayalım</p>
          </div>

          <?php if ($info): ?>
            <p class="text-muted text-sm bg-surface-3 border border-border px-4 py-3 rounded-xl mb-5">
              <?= h($info) ?>
            </p>
          <?php endif; ?>

          <?php if ($devResetLink): ?>
            <div class="text-sm bg-accent/10 border border-accent/50 px-4 py-3 rounded-xl mb-5 flex flex-col gap-2">
              <p class="text-accent font-semibold">Geliştirme ortamı notu</p>
              <p class="text-muted">Bu projede henüz gerçek e-posta gönderimi kurulu değil. Normalde bu bağlantı e-posta ile gönderilir; şu an için doğrudan buradan kullanabilirsin:</p>
              <a href="<?= h($devResetLink) ?>" class="text-accent hover:underline" style="word-break:break-all"><?= h($devResetLink) ?></a>
            </div>
          <?php endif; ?>

          <form class="space-y-5" method="post" action="/forgot_password.php">
            <?= csrf_field() ?>
            <div>
              <label for="email-field" class="block text-sm font-medium text-muted mb-1.5">E-posta</label>
              <div class="relative">
                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-2 text-xl">mail</span>
                <input type="email" id="email-field" class="input-field pl-11" name="email" placeholder="örnek@email.com" autocomplete="email" required />
              </div>
            </div>

            <button type="submit" class="btn-primary w-full flex items-center justify-center gap-2 py-3.5">
              Sıfırlama Bağlantısı Gönder
              <span class="material-symbols-outlined text-lg">arrow_forward</span>
            </button>
          </form>

          <p class="mt-8 text-center text-sm text-muted">
            <a href="/login.php" class="text-accent hover:text-accent-hover font-semibold hover:underline transition-colors">Giriş sayfasına dön</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
