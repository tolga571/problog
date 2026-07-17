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
$tags = $old['tags'] ?? '';

$initialType = ($old['post_type'] ?? ($_GET['type'] ?? '')) === 'article' ? 'article' : 'post';
$isArticle = $initialType === 'article';

$pageTitle = 'Yeni Gönderi - ProBlog';
$activePage = 'home';
$useMarkdownEditor = true;
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[900px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-14 flex items-center px-4 gap-4">
      <a href="/index.php" class="p-1 hover:bg-surface-3 rounded-full transition-colors">
        <span class="material-symbols-outlined">arrow_back</span>
      </a>
      <h1 class="font-bold">Yeni Paylaşım</h1>
    </header>

    <div class="p-4">
      <div class="card overflow-hidden">
        <div class="flex border-b border-border">
          <button type="button" id="tab-post" class="composer-tab <?= !$isArticle ? 'active' : '' ?>">Gönderi</button>
          <button type="button" id="tab-article" class="composer-tab <?= $isArticle ? 'active' : '' ?>">Makale</button>
        </div>

        <form method="post" action="/actions/create_post.php" enctype="multipart/form-data" class="p-5 sm:p-8">
          <?= csrf_field() ?>
          <input type="hidden" name="post_type" id="post-type-field" value="<?= h($initialType) ?>" />

          <div id="title-wrap" class="<?= $isArticle ? '' : 'hidden' ?>">
            <input
              id="title-field"
              class="w-full bg-transparent border-none text-white placeholder:text-muted-2 text-3xl font-extrabold py-2 mb-2"
              name="title"
              placeholder="Başlık"
              value="<?= h($title) ?>"
              minlength="4"
              maxlength="120"
              <?= $isArticle ? 'required' : 'disabled' ?>
            />
          </div>

          <div id="post-content-wrap" class="<?= $isArticle ? 'hidden' : '' ?>">
            <textarea
              id="post-content-field"
              class="w-full bg-transparent border-none resize-none text-white placeholder:text-muted-2 text-xl py-2"
              name="content"
              placeholder="Ne düşünüyorsun?"
              rows="4"
              maxlength="500"
              <?= $isArticle ? 'disabled' : 'required' ?>
            ><?= !$isArticle ? h($content) : '' ?></textarea>
          </div>

          <div id="article-content-wrap" class="<?= $isArticle ? '' : 'hidden' ?>">
            <div data-milkdown-target="new-post-content">
              <textarea
                id="new-post-content"
                class="w-full bg-transparent border-none resize-none text-white placeholder:text-muted-2 text-lg font-medium py-2"
                name="content"
                placeholder="Makaleni yaz... (Markdown destekli)"
                rows="10"
                minlength="20"
                maxlength="100000"
                <?= $isArticle ? 'required' : 'disabled' ?>
              ><?= $isArticle ? h($content) : '' ?></textarea>
            </div>
          </div>

          <input
            class="w-full bg-transparent border-none text-white placeholder:text-muted-2 text-sm py-1"
            name="tags"
            placeholder="Etiketler (virgülle ayırın, örnek: yazılım, güvenlik)"
            value="<?= h($tags) ?>"
            maxlength="150"
          />
          <div class="flex items-center justify-between mt-4 pt-4 border-t border-border gap-3">
            <label class="flex items-center gap-2 text-muted text-sm cursor-pointer hover:text-white transition-colors">
              <span class="material-symbols-outlined text-xl">perm_media</span>
              <span>Medya</span>
              <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime" class="hidden" onchange="this.previousElementSibling.textContent = this.files[0] ? this.files[0].name : 'Medya'" />
            </label>
            <div class="flex items-center gap-3">
              <label id="draft-wrap" class="flex items-center gap-1.5 text-muted text-sm cursor-pointer <?= $isArticle ? '' : 'hidden' ?>">
                <input type="checkbox" name="status" value="draft" />
                Taslak olarak kaydet
              </label>
              <button type="submit" class="btn-primary text-sm px-5 py-2">Paylaş</button>
            </div>
          </div>
          <?php if ($error): ?>
            <p class="text-red-400 text-sm mt-3"><?= h($error) ?></p>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </main>

  <script>
    (function () {
      const postTypeField = document.getElementById('post-type-field');
      const titleField = document.getElementById('title-field');
      const postContentField = document.getElementById('post-content-field');
      const articleContentField = document.getElementById('new-post-content');
      const titleWrap = document.getElementById('title-wrap');
      const postContentWrap = document.getElementById('post-content-wrap');
      const articleContentWrap = document.getElementById('article-content-wrap');
      const draftWrap = document.getElementById('draft-wrap');
      const tabPost = document.getElementById('tab-post');
      const tabArticle = document.getElementById('tab-article');

      function setComposerMode(mode) {
        const isArticle = mode === 'article';
        postTypeField.value = mode;

        titleWrap.classList.toggle('hidden', !isArticle);
        titleField.disabled = !isArticle;
        titleField.required = isArticle;

        postContentWrap.classList.toggle('hidden', isArticle);
        postContentField.disabled = isArticle;
        postContentField.required = !isArticle;

        articleContentWrap.classList.toggle('hidden', !isArticle);
        articleContentField.disabled = !isArticle;
        articleContentField.required = isArticle;

        draftWrap.classList.toggle('hidden', !isArticle);

        tabPost.classList.toggle('active', !isArticle);
        tabArticle.classList.toggle('active', isArticle);
      }

      tabPost.addEventListener('click', () => setComposerMode('post'));
      tabArticle.addEventListener('click', () => setComposerMode('article'));
    })();
  </script>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
