<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();

$stmt = db()->prepare('SELECT * FROM articles WHERE author_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$articles = $stmt->fetchAll();

$langStmt = db()->prepare('SELECT language_id, is_source, sentences_json FROM article_translations WHERE article_id = ?');

$pageTitle = 'Makalelerim - ProBlog';
$activePage = 'articles';
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[900px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-14 flex items-center px-4 gap-4">
      <a href="/index.php" class="p-1 hover:bg-surface-3 rounded-full transition-colors">
        <span class="material-symbols-outlined">arrow_back</span>
      </a>
      <h1 class="font-bold flex-1">Makalelerim</h1>
      <a href="/new_article.php" class="btn-primary text-sm px-4 py-1.5 flex items-center gap-1.5">
        <span class="material-symbols-outlined text-lg">add</span>
        Yeni Makale
      </a>
    </header>

    <div class="p-4 flex flex-col gap-3">
      <?php if (!$articles): ?>
        <p class="text-muted text-sm p-6 text-center">Henüz çok dilli bir makale oluşturmadın.</p>
      <?php endif; ?>

      <?php foreach ($articles as $article):
        $langStmt->execute([$article['id']]);
        $translations = $langStmt->fetchAll();
        $sourceRow = null;
        foreach ($translations as $t) {
            if ((int) $t['is_source'] === 1) {
                $sourceRow = $t;
            }
        }
        $sourceSentences = $sourceRow ? (json_decode((string) $sourceRow['sentences_json'], true) ?: []) : [];
        $langCount = count($translations);
      ?>
        <div class="card p-4 flex items-center justify-between gap-3 hover:bg-surface-3 transition-colors">
          <a href="/edit_article.php?id=<?= h($article['id']) ?>" class="min-w-0 flex-1">
            <p class="font-bold truncate"><?= h($article['title']) ?></p>
            <p class="text-muted-2 text-xs mt-1">
              Kaynak: <?= h(ARTICLE_LANGUAGES[$article['source_language']] ?? $article['source_language']) ?>
              · <?= count($sourceSentences) ?> cümle
              · <?= $langCount ?> dil
              · <?= $article['status'] === 'draft' ? 'Taslak' : 'Yayında' ?>
            </p>
          </a>
          <a href="/translate_article.php?id=<?= h($article['id']) ?>" class="flex-shrink-0 p-2 rounded-full hover:bg-surface-3 transition-colors text-muted hover:text-white" title="Çevirileri yönet">
            <span class="material-symbols-outlined text-lg">translate</span>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
