<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();

$postId = (string) ($_GET['post_id'] ?? '');
$stmt = db()->prepare("SELECT * FROM posts WHERE id = ? AND author_id = ? AND post_type = 'article'");
$stmt->execute([$postId, $user['id']]);
$post = $stmt->fetch();

if (!$post) {
    flash_set('error', 'Makale bulunamadı.');
    redirect('/index.php');
}

$pageTitle = 'Çeviriler: ' . h($post['title']) . ' - ProBlog';
$activePage = 'home';
$useArticleTranslator = true;
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[900px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-14 flex items-center px-4 gap-4">
      <a href="/edit_post.php?id=<?= h($post['id']) ?>" class="p-1 hover:bg-surface-3 rounded-full transition-colors">
        <span class="material-symbols-outlined">arrow_back</span>
      </a>
      <h1 class="font-bold flex-1 truncate">Çeviriler: <?= h($post['title']) ?></h1>
      <a href="/actions/article_export.php?article_id=<?= h($post['id']) ?>" class="p-2 rounded-full hover:bg-surface-3 transition-colors text-muted hover:text-white" title="Tüm dilleri JSON olarak indir">
        <span class="material-symbols-outlined text-lg">download</span>
      </a>
      <label class="p-2 rounded-full hover:bg-surface-3 transition-colors text-muted hover:text-white cursor-pointer" title="JSON dosyasından tüm dilleri içe aktar">
        <span class="material-symbols-outlined text-lg">upload</span>
        <input type="file" accept=".json,application/json" id="article-import-input" class="hidden" />
      </label>
      <a href="/article.php?post_id=<?= h($post['id']) ?>" class="btn-outline text-xs px-3 py-1.5" target="_blank" rel="noopener">Önizle</a>
    </header>

    <div class="p-4">
      <div class="card overflow-hidden">
        <div class="p-5 sm:p-8">
          <div
            data-article-translator
            data-article-id="<?= h($post['id']) ?>"
            data-source-language="<?= h($post['source_language']) ?>"
            data-languages='<?= h(json_encode(ARTICLE_LANGUAGES, JSON_UNESCAPED_UNICODE)) ?>'
          ></div>
        </div>
      </div>
    </div>
  </main>

  <script type="module">
    const input = document.getElementById('article-import-input');
    input?.addEventListener('change', async () => {
      const file = input.files?.[0];
      if (!file) return;
      try {
        const text = await file.text();
        const parsed = JSON.parse(text);
        const res = await fetch('/actions/article_export.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'X-Requested-With': 'fetch',
          },
          body: JSON.stringify({ article_id: '<?= h($post['id']) ?>', languages: parsed.languages || {} }),
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data?.ok) {
          alert(data?.message || 'İçe aktarma başarısız oldu.');
          return;
        }
        alert('İçe aktarıldı: ' + data.imported.join(', '));
        window.location.reload();
      } catch (e) {
        alert('Geçersiz JSON dosyası.');
      } finally {
        input.value = '';
      }
    });
  </script>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
