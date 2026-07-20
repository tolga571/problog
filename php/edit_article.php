<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();

$articleId = (string) ($_GET['id'] ?? '');
$stmt = db()->prepare('SELECT * FROM articles WHERE id = ? AND author_id = ?');
$stmt->execute([$articleId, $user['id']]);
$article = $stmt->fetch();

if (!$article) {
    flash_set('error', 'Makale bulunamadı.');
    redirect('/articles.php');
}

$sourceTranslation = get_article_translation($articleId, $article['source_language']);
$sourceContent = sentences_to_plain_text($sourceTranslation['sentences'] ?? []);
$error = flash_get('error');
$info = flash_get('info');

$pageTitle = h($article['title']) . ' - ProBlog';
$activePage = 'articles';
$useMarkdownEditor = true;
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[900px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-14 flex items-center px-4 gap-4">
      <a href="/articles.php" class="p-1 hover:bg-surface-3 rounded-full transition-colors">
        <span class="material-symbols-outlined">arrow_back</span>
      </a>
      <h1 class="font-bold flex-1 truncate"><?= h($article['title']) ?></h1>
      <a href="/translate_article.php?id=<?= h($article['id']) ?>" class="btn-outline text-xs px-3 py-1.5">Çevirileri Yönet</a>
      <a href="/article.php?id=<?= h($article['id']) ?>" class="btn-outline text-xs px-3 py-1.5" target="_blank" rel="noopener">Önizle</a>
    </header>

    <?php if ($error): ?><p class="text-red-400 text-sm px-4 pt-4"><?= h($error) ?></p><?php endif; ?>
    <?php if ($info): ?><p class="text-green-400 text-sm px-4 pt-4"><?= h($info) ?></p><?php endif; ?>

    <div class="p-4">
      <div class="card overflow-hidden mb-4">
        <div class="p-4 border-b border-border font-bold text-sm text-muted">
          Kaynak İçerik (<?= h(ARTICLE_LANGUAGES[$article['source_language']] ?? $article['source_language']) ?>)
        </div>
        <form method="post" action="/actions/update_article.php" class="p-5 sm:p-8">
          <?= csrf_field() ?>
          <input type="hidden" name="article_id" value="<?= h($article['id']) ?>" />

          <input
            class="w-full bg-transparent border-none text-white placeholder:text-muted-2 text-3xl font-extrabold py-2 mb-2"
            name="title"
            placeholder="Başlık"
            value="<?= h($article['title']) ?>"
            minlength="4"
            maxlength="120"
            required
          />

          <div data-milkdown-target="edit-article-content">
            <textarea
              id="edit-article-content"
              class="w-full bg-transparent border-none resize-none text-white placeholder:text-muted-2 text-lg font-medium py-2"
              name="content"
              rows="12"
              minlength="20"
              maxlength="100000"
              required
            ><?= h($sourceContent) ?></textarea>
          </div>

          <div class="flex items-center justify-between mt-4 pt-4 border-t border-border gap-3">
            <label class="flex items-center gap-1.5 text-muted text-sm cursor-pointer">
              <input type="checkbox" name="status" value="draft" <?= $article['status'] === 'draft' ? 'checked' : '' ?> />
              Taslak olarak kaydet
            </label>
            <button type="submit" class="btn-primary text-sm px-5 py-2">Kaynağı Kaydet</button>
          </div>
        </form>
      </div>
    </div>
  </main>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
