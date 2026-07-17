<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();

$pageTitle = 'Ayarlar - ProBlog';
$activePage = 'settings';
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[680px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-14 flex items-center px-4 gap-4">
      <a href="/index.php" class="p-1 hover:bg-surface-3 rounded-full transition-colors">
        <span class="material-symbols-outlined">arrow_back</span>
      </a>
      <h1 class="font-bold">Ayarlar</h1>
    </header>

    <div class="p-4">
      <div class="card overflow-hidden max-w-lg">
        <a href="/edit_profile.php" class="settings-row">
          <span class="material-symbols-outlined text-xl text-muted-2">person</span>
          <span class="flex-1">
            <span class="block text-sm font-medium text-white">Profili Düzenle</span>
            <span class="block text-xs text-muted-2">Ad, biyografi, fotoğraf ve diğer profil bilgilerin</span>
          </span>
          <span class="material-symbols-outlined text-lg text-muted-2">chevron_right</span>
        </a>
        <a href="/settings_account.php" class="settings-row">
          <span class="material-symbols-outlined text-xl text-muted-2">manage_accounts</span>
          <span class="flex-1">
            <span class="block text-sm font-medium text-white">Hesap</span>
            <span class="block text-xs text-muted-2">E-posta, şifre ve hesap silme</span>
          </span>
          <span class="material-symbols-outlined text-lg text-muted-2">chevron_right</span>
        </a>
      </div>
    </div>
  </main>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
