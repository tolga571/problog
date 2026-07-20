<?php
/** @var array|null $user */
/** @var string $pageTitle */
/** @var string $activePage */
/** @var bool $useMarkdownEditor */
/** @var bool $useArticleTranslator */
$useMarkdownEditor = $useMarkdownEditor ?? false;
$useArticleTranslator = $useArticleTranslator ?? false;
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
  <link rel="stylesheet" href="/assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>" />
  <?php if ($useMarkdownEditor): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@milkdown/crepe@7.21.3/lib/theme/common/style.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@milkdown/crepe@7.21.3/lib/theme/crepe-dark/style.css" />
  <?php endif; ?>
  <?php if ($useArticleTranslator): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prosemirror-view@1/style/prosemirror.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prosemirror-menu@1/style/menu.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prosemirror-example-setup@1/style/style.css" />
  <?php endif; ?>
  <?php if ($useMarkdownEditor || $useArticleTranslator): ?>
    <script type="importmap">
    {
      "imports": {
        <?php $imports = []; ?>
        <?php if ($useMarkdownEditor): ?>
          <?php $imports[] = '"@milkdown/crepe": "https://esm.sh/@milkdown/crepe@7.21.3?deps=codemirror@6.0.1"'; ?>
        <?php endif; ?>
        <?php if ($useArticleTranslator): ?>
          <?php
            $imports[] = '"prosemirror-state": "https://esm.sh/prosemirror-state@1"';
            $imports[] = '"prosemirror-view": "https://esm.sh/prosemirror-view@1"';
            $imports[] = '"prosemirror-model": "https://esm.sh/prosemirror-model@1"';
            $imports[] = '"prosemirror-schema-basic": "https://esm.sh/prosemirror-schema-basic@1"';
            $imports[] = '"prosemirror-example-setup": "https://esm.sh/prosemirror-example-setup@1?deps=prosemirror-state@1,prosemirror-view@1,prosemirror-model@1"';
          ?>
        <?php endif; ?>
        <?= implode(",\n        ", $imports) ?>

      }
    }
    </script>
  <?php endif; ?>
  <?php if ($useMarkdownEditor): ?>
    <script type="module" src="/assets/milkdown-editor.js?v=<?= filemtime(__DIR__ . '/../assets/milkdown-editor.js') ?>"></script>
  <?php endif; ?>
  <?php if ($useArticleTranslator): ?>
    <script type="module" src="/assets/article-translator.js?v=<?= filemtime(__DIR__ . '/../assets/article-translator.js') ?>"></script>
  <?php endif; ?>
  <?php if ($user): ?>
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>" />
    <meta name="current-user-id" content="<?= h($user['id']) ?>" />
  <?php endif; ?>
  <title><?= h($pageTitle ?? APP_NAME) ?></title>
</head>
<body>
<?php if ($user): ?>
<div class="flex min-h-screen">
  <aside class="hidden md:flex flex-col h-screen w-[280px] sticky top-0 px-4 py-6 border-r border-border flex-shrink-0">
    <div class="flex flex-col h-full">
      <a href="/index.php" class="brand-logo-link block px-3 mb-8">
        <h1 class="text-xl font-extrabold tracking-tight">
          <span class="text-accent">Pro</span><span class="text-white">Blog</span>
        </h1>
        <p class="text-muted-2 text-xs uppercase tracking-widest mt-0.5 font-medium">Social Blog Platform</p>
      </a>

      <?php
        $unreadCount = unread_notifications_count($user['id']);
        $unreadMsgCount = unread_messages_count($user['id']);
      ?>
      <nav class="flex flex-col gap-1 flex-1">
        <a href="/index.php" class="sidebar-link <?= ($activePage ?? '') === 'home' ? 'active' : '' ?>">
          <span class="material-symbols-outlined nav-icon">home</span>
          <span>Ana Sayfa</span>
        </a>
        <a href="/settings.php" class="sidebar-link <?= ($activePage ?? '') === 'settings' ? 'active' : '' ?>">
          <span class="material-symbols-outlined nav-icon">settings</span>
          <span>Ayarlar</span>
        </a>
        <a href="/messages.php" class="sidebar-link <?= ($activePage ?? '') === 'messages' ? 'active' : '' ?>">
          <span class="material-symbols-outlined nav-icon">chat_bubble</span>
          <span class="flex-1">Mesajlar</span>
          <span class="bg-accent text-white text-xs font-semibold rounded-full <?= $unreadMsgCount > 0 ? '' : 'hidden' ?>" style="padding:2px 8px" data-nav-messages-badge-count><?= $unreadMsgCount ?></span>
        </a>
        <a href="/notifications.php" class="sidebar-link <?= ($activePage ?? '') === 'notifications' ? 'active' : '' ?>">
          <span class="material-symbols-outlined nav-icon">notifications</span>
          <span class="flex-1">Bildirimler</span>
          <?php if ($unreadCount > 0): ?>
            <span class="bg-accent text-white text-xs font-semibold rounded-full" style="padding:2px 8px"><?= $unreadCount ?></span>
          <?php endif; ?>
        </a>
        <a href="/saved.php" class="sidebar-link <?= ($activePage ?? '') === 'saved' ? 'active' : '' ?>">
          <span class="material-symbols-outlined nav-icon">bookmark</span>
          <span>Kaydedilenler</span>
        </a>
        <a href="/profile.php?id=<?= h($user['id']) ?>" class="sidebar-link <?= ($activePage ?? '') === 'profile' ? 'active' : '' ?>">
          <span class="material-symbols-outlined nav-icon">person</span>
          <span>Profilim</span>
        </a>
      </nav>

      <a href="/new_post.php" class="btn-primary w-full mb-6 flex items-center justify-center gap-2">
        <span class="material-symbols-outlined text-lg">edit_note</span>
        <span>Yeni Yazı</span>
      </a>

      <form method="post" action="/logout.php" id="logout-form">
        <?= csrf_field() ?>
        <button type="submit" class="w-full flex items-center gap-3 px-3 py-3 hover:bg-surface-3 rounded-xl transition-colors duration-200 cursor-pointer group" style="text-align:left">
          <?= render_avatar($user, 'avatar avatar-sm') ?>
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-sm truncate"><?= h($user['name']) ?></p>
            <p class="text-muted-2 text-xs truncate">Çıkış yap</p>
          </div>
          <span class="material-symbols-outlined text-muted-2 text-lg opacity-0 group-hover:opacity-100 transition-opacity">logout</span>
        </button>
      </form>
    </div>
  </aside>
<?php endif; ?>
