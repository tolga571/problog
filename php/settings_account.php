<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();

$error = flash_get('error');
$info = flash_get('info');

$pageTitle = 'Hesap - ProBlog';
$activePage = 'settings';
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[680px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-14 flex items-center px-4 gap-4">
      <a href="/settings.php" class="p-1 hover:bg-surface-3 rounded-full transition-colors">
        <span class="material-symbols-outlined">arrow_back</span>
      </a>
      <h1 class="font-bold">Hesap</h1>
    </header>

    <div class="p-4 flex flex-col gap-4 max-w-lg">
      <?php if ($error): ?>
        <p class="text-red-400 text-sm bg-red-500/10 border border-red-500/20 px-4 py-3 rounded-xl flex items-center gap-2">
          <span class="material-symbols-outlined text-lg">error</span>
          <?= h($error) ?>
        </p>
      <?php endif; ?>
      <?php if ($info): ?>
        <p class="text-green-400 text-sm bg-surface-3 border border-border px-4 py-3 rounded-xl"><?= h($info) ?></p>
      <?php endif; ?>

      <div class="card p-5 sm:p-6">
        <h2 class="font-bold text-sm mb-4">E-posta Adresi</h2>
        <form method="post" action="/actions/update_email.php" class="space-y-3">
          <?= csrf_field() ?>
          <div>
            <label for="email-field" class="block text-sm font-medium text-muted mb-1.5">E-posta</label>
            <input id="email-field" class="input-field" type="email" name="email" value="<?= h($user['email']) ?>" autocomplete="email" required />
          </div>
          <div>
            <label for="email-current-password-field" class="block text-sm font-medium text-muted mb-1.5">Mevcut şifre</label>
            <input id="email-current-password-field" class="input-field" type="password" name="current_password" placeholder="Onaylamak için şifreni gir" autocomplete="current-password" required />
          </div>
          <button type="submit" class="btn-primary text-sm px-5 py-2">Kaydet</button>
        </form>
      </div>

      <div class="card p-5 sm:p-6">
        <h2 class="font-bold text-sm mb-4">Şifre Değiştir</h2>
        <form method="post" action="/actions/update_password.php" class="space-y-3">
          <?= csrf_field() ?>
          <div>
            <label for="current-password-field" class="block text-sm font-medium text-muted mb-1.5">Mevcut şifre</label>
            <input id="current-password-field" class="input-field" type="password" name="current_password" autocomplete="current-password" required />
          </div>
          <div>
            <label for="new-password-field" class="block text-sm font-medium text-muted mb-1.5">Yeni şifre</label>
            <input id="new-password-field" class="input-field" type="password" name="new_password" placeholder="En az 6 karakter" minlength="6" autocomplete="new-password" required />
          </div>
          <div>
            <label for="new-password-confirm-field" class="block text-sm font-medium text-muted mb-1.5">Yeni şifre (tekrar)</label>
            <input id="new-password-confirm-field" class="input-field" type="password" name="new_password_confirm" minlength="6" autocomplete="new-password" required />
          </div>
          <button type="submit" class="btn-primary text-sm px-5 py-2">Şifreyi Güncelle</button>
        </form>
      </div>

      <div class="danger-zone">
        <h2 class="font-bold text-sm mb-1 text-red-400">Hesabı Sil</h2>
        <p class="text-xs text-muted-2 mb-4">Bu işlem geri alınamaz. Tüm makalelerin, yorumların ve mesajların kalıcı olarak silinir.</p>
        <form method="post" action="/actions/delete_account.php" class="space-y-3" id="delete-account-form">
          <?= csrf_field() ?>
          <div>
            <label for="delete-current-password-field" class="block text-sm font-medium text-muted mb-1.5">Şifreni gir</label>
            <input id="delete-current-password-field" class="input-field" type="password" name="current_password" autocomplete="current-password" required />
          </div>
          <button type="submit" class="btn-outline text-sm px-5 py-2 hover-danger" style="border-color:rgba(251,44,54,0.3);color:var(--color-red-400)" id="delete-account-btn">
            Hesabımı Kalıcı Olarak Sil
          </button>
        </form>
      </div>
    </div>
  </main>

  <script>
    document.getElementById('delete-account-form').addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.target;
      const ok = await window.showConfirmModal({
        title: 'Hesabını Sil',
        message: 'Bu işlem geri alınamaz. Tüm makalelerin, yorumların ve mesajların kalıcı olarak silinecek.',
        confirmText: 'Evet, Hesabımı Sil',
      });
      if (ok) form.submit();
    });
  </script>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
