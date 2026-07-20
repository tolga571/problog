<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = current_user();

$articleId = (string) ($_GET['id'] ?? '');
$stmt = db()->prepare('SELECT * FROM articles WHERE id = ?');
$stmt->execute([$articleId]);
$article = $stmt->fetch();

if (!$article || ($article['status'] !== 'published' && (!$user || $user['id'] !== $article['author_id']))) {
    http_response_code(404);
    $pageTitle = 'Makale bulunamadı - ProBlog';
    $activePage = '';
    require __DIR__ . '/includes/layout_head.php';
    echo '<main class="flex-1 max-w-[900px] mx-auto w-full feed-border min-h-screen p-8 text-center text-muted">Makale bulunamadı.</main>';
    require __DIR__ . '/includes/layout_foot.php';
    exit;
}

$requestedLang = (string) ($_GET['lang'] ?? $article['source_language']);
if (!isset(ARTICLE_LANGUAGES[$requestedLang])) {
    $requestedLang = $article['source_language'];
}

$isSource = $requestedLang === $article['source_language'];
$translation = get_article_translation($articleId, $requestedLang);
$sentences = $translation['sentences'] ?? [];

$sourceTranslation = $isSource ? $translation : get_article_translation($articleId, $article['source_language']);
$sourceSentences = $sourceTranslation['sentences'] ?? [];
$percent = $isSource ? 100 : translation_percent($sourceSentences, $sentences);

$stmt = db()->prepare('SELECT id, name, username, avatar_url FROM users WHERE id = ?');
$stmt->execute([$article['author_id']]);
$author = $stmt->fetch();

$availableLangStmt = db()->prepare('SELECT language_id FROM article_translations WHERE article_id = ?');
$availableLangStmt->execute([$articleId]);
$availableLangs = array_column($availableLangStmt->fetchAll(), 'language_id');
if (!in_array($article['source_language'], $availableLangs, true)) {
    $availableLangs[] = $article['source_language'];
}

$byParagraph = [];
foreach ($sentences as $s) {
    $byParagraph[(int) ($s['p'] ?? 0)][] = $s;
}
ksort($byParagraph);

$displayTitle = trim((string) ($translation['title'] ?? ''));
if ($displayTitle === '') {
    $displayTitle = $article['title'];
}

$pageTitle = h($displayTitle) . ' - ProBlog';
$activePage = 'articles';
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[760px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-14 flex items-center px-4 gap-4">
      <a href="<?= $user ? '/articles.php' : '/index.php' ?>" class="p-1 hover:bg-surface-3 rounded-full transition-colors">
        <span class="material-symbols-outlined">arrow_back</span>
      </a>
      <h1 class="font-bold flex-1 truncate">Makale</h1>
      <form method="get" class="flex items-center gap-2">
        <input type="hidden" name="id" value="<?= h($articleId) ?>" />
        <select name="lang" onchange="this.form.submit()" class="bg-surface-3 border border-border rounded-lg px-2 py-1 text-xs">
          <?php foreach (ARTICLE_LANGUAGES as $code => $name): ?>
            <option value="<?= h($code) ?>" <?= $requestedLang === $code ? 'selected' : '' ?> <?= !in_array($code, $availableLangs, true) ? 'disabled' : '' ?>>
              <?= h($name) ?><?= $code === $article['source_language'] ? ' (Kaynak)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </header>

    <article class="p-6 sm:p-10">
      <?php if (!$isSource && $percent < 100): ?>
        <p class="text-xs text-amber-400 mb-4">Bu çeviri %<?= $percent ?> tamamlandı, eksik cümleler kaynak dilde görünmeyebilir.</p>
      <?php endif; ?>

      <h1 class="text-3xl sm:text-4xl font-extrabold mb-4"><?= h($displayTitle) ?></h1>

      <?php if ($author): ?>
        <a href="/profile.php?id=<?= h($author['id']) ?>" class="flex items-center gap-2 mb-8 text-sm text-muted hover:text-white transition-colors">
          <?= render_avatar($author, 'avatar avatar-sm') ?>
          <span><?= h($author['name']) ?></span>
        </a>
      <?php endif; ?>

      <div class="prose-content text-lg leading-relaxed">
        <?php if (!$byParagraph): ?>
          <p class="text-muted">Bu makalede henüz içerik yok.</p>
        <?php endif; ?>
        <?php foreach ($byParagraph as $paragraphSentences): ?>
          <p>
            <?php foreach ($paragraphSentences as $s):
                $text = trim((string) $s['text']);
                if (trim(strip_tags($text)) === '') {
                    continue;
                }
                echo $isSource ? h($text) . ' ' : $text . ' ';
            endforeach; ?>
          </p>
        <?php endforeach; ?>
      </div>
    </article>
  </main>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
