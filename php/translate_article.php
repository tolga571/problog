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

$pageTitle = 'Çeviriler: ' . h($article['title']) . ' - ProBlog';
$activePage = 'articles';
$useArticleTranslator = true;
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[900px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-14 flex items-center px-4 gap-4">
      <a href="/edit_article.php?id=<?= h($article['id']) ?>" class="p-1 hover:bg-surface-3 rounded-full transition-colors">
        <span class="material-symbols-outlined">arrow_back</span>
      </a>
      <h1 class="font-bold flex-1 truncate">Çeviriler: <?= h($article['title']) ?></h1>
      <a href="/article.php?id=<?= h($article['id']) ?>" class="btn-outline text-xs px-3 py-1.5" target="_blank" rel="noopener">Önizle</a>
    </header>

    <div class="p-4">
      <div class="card overflow-hidden">
        <div class="p-5 sm:p-8">
          <div
            data-article-translator
            data-article-id="<?= h($article['id']) ?>"
            data-source-language="<?= h($article['source_language']) ?>"
            data-languages='<?= h(json_encode(ARTICLE_LANGUAGES, JSON_UNESCAPED_UNICODE)) ?>'
          ></div>
        </div>
      </div>
    </div>
  </main>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
