<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();

$error = flash_get('composer_error');
$old = flash_old('composer');
$title = $old['title'] ?? '';
$content = $old['content'] ?? '';

$pageTitle = 'Yeni Makale - ProBlog';
$activePage = 'articles';
$useMarkdownEditor = true;
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[900px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-14 flex items-center px-4 gap-4">
      <a href="/articles.php" class="p-1 hover:bg-surface-3 rounded-full transition-colors">
        <span class="material-symbols-outlined">arrow_back</span>
      </a>
      <h1 class="font-bold">Yeni Makale (Çok Dilli)</h1>
    </header>

    <div class="p-4">
      <div class="card overflow-hidden">
        <form method="post" action="/actions/create_article.php" class="p-5 sm:p-8">
          <?= csrf_field() ?>

          <input
            id="title-field"
            class="w-full bg-transparent border-none text-white placeholder:text-muted-2 text-3xl font-extrabold py-2 mb-2"
            name="title"
            placeholder="Başlık"
            value="<?= h($title) ?>"
            minlength="4"
            maxlength="120"
            required
          />

          <div data-milkdown-target="new-article-content">
            <textarea
              id="new-article-content"
              class="w-full bg-transparent border-none resize-none text-white placeholder:text-muted-2 text-lg font-medium py-2"
              name="content"
              placeholder="Makaleni yaz... (Markdown destekli)"
              rows="14"
              minlength="20"
              maxlength="100000"
              required
            ><?= h($content) ?></textarea>
          </div>

          <div class="flex items-center justify-between mt-4 pt-4 border-t border-border gap-3">
            <label class="flex items-center gap-1.5 text-muted text-sm cursor-pointer">
              <input type="checkbox" name="status" value="draft" />
              Taslak olarak kaydet
            </label>
            <button type="submit" class="btn-primary text-sm px-5 py-2">Makaleyi Oluştur</button>
          </div>
          <?php if ($error): ?>
            <p class="text-red-400 text-sm mt-3"><?= h($error) ?></p>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </main>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
