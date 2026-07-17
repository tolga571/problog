<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/post_card.php';
require_once __DIR__ . '/includes/right_rail.php';

$user = require_login();

$generalError = flash_get('error');

$feed = ($_GET['feed'] ?? 'all') === 'following' ? 'following' : 'all';

$rows = fetch_feed_posts($feed, $user['id'], FEED_PAGE_SIZE, 0);
$posts = array_map(fn($post) => post_with_details($post, $user['id']), $rows);
$hasMore = count($rows) === FEED_PAGE_SIZE;

$composerError = flash_get('composer_error');
$composerOld = flash_old('composer');
$composerTitle = $composerOld['title'] ?? '';
$composerContent = $composerOld['content'] ?? '';
$composerTags = $composerOld['tags'] ?? '';
$composerIsArticle = ($composerOld['post_type'] ?? '') === 'article';

$pageTitle = 'Ana Sayfa - ProBlog';
$activePage = 'home';
$useMarkdownEditor = true;
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[680px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-16 flex items-center px-6 justify-between">
      <h1 class="font-bold text-lg">Ana Sayfa</h1>
    </header>

    <?php if ($generalError): ?>
      <div class="px-4 pt-4">
        <div class="text-red-400 text-sm bg-red-500/10 border border-red-500/20 px-4 py-3 rounded-xl flex items-center gap-2">
          <span class="material-symbols-outlined text-lg">error</span>
          <?= h($generalError) ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="p-4 border-b border-border" id="composer">
      <?php $composerOpen = $composerError !== null; ?>
      <div class="card overflow-hidden" id="composer-card" style="border: 1px solid var(--color-border)">
        <button type="button" id="composer-trigger" class="w-full p-5 flex gap-4 items-center hover:border-accent/50 transition-colors <?= $composerOpen ? 'hidden' : '' ?>" style="text-align:left">
          <?= render_avatar($user) ?>
          <span class="flex-1 text-muted-2 text-lg">Ne düşünüyorsun?</span>
          <span class="material-symbols-outlined text-accent text-2xl">edit_note</span>
        </button>

        <div id="composer-panel" class="<?= $composerOpen ? '' : 'hidden' ?>">
          <div class="flex border-b border-border">
            <button type="button" id="tab-post" class="composer-tab <?= !$composerIsArticle ? 'active' : '' ?>">Gönderi</button>
            <button type="button" id="tab-article" class="composer-tab <?= $composerIsArticle ? 'active' : '' ?>">Makale</button>
            <button type="button" id="composer-close" class="ml-auto px-4 text-muted hover:text-white transition-colors" title="Kapat">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <form method="post" action="/actions/create_post.php" enctype="multipart/form-data" class="p-5">
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="/index.php" />
            <input type="hidden" name="post_type" id="post-type-field" value="<?= h($composerIsArticle ? 'article' : 'post') ?>" />

            <div id="title-wrap" class="<?= $composerIsArticle ? '' : 'hidden' ?>">
              <input
                id="title-field"
                class="w-full bg-transparent border-none text-white placeholder:text-muted-2 text-2xl font-extrabold py-2 mb-2"
                name="title"
                placeholder="Başlık"
                value="<?= h($composerTitle) ?>"
                minlength="4"
                maxlength="120"
                <?= $composerIsArticle ? 'required' : 'disabled' ?>
              />
            </div>

            <div id="post-content-wrap" class="<?= $composerIsArticle ? 'hidden' : '' ?>">
              <textarea
                id="post-content-field"
                class="w-full bg-transparent border-none resize-none text-white placeholder:text-muted-2 text-lg py-2"
                name="content"
                placeholder="Ne düşünüyorsun?"
                rows="3"
                maxlength="500"
                <?= $composerIsArticle ? 'disabled' : 'required' ?>
              ><?= !$composerIsArticle ? h($composerContent) : '' ?></textarea>
            </div>

            <div id="article-content-wrap" class="<?= $composerIsArticle ? '' : 'hidden' ?>">
              <div data-milkdown-target="new-post-content">
                <textarea
                  id="new-post-content"
                  class="w-full bg-transparent border-none resize-none text-white placeholder:text-muted-2 text-lg font-medium py-2"
                  name="content"
                  placeholder="Makaleni yaz... (Markdown destekli)"
                  rows="8"
                  minlength="20"
                  maxlength="100000"
                  <?= $composerIsArticle ? 'required' : 'disabled' ?>
                ><?= $composerIsArticle ? h($composerContent) : '' ?></textarea>
              </div>
            </div>

            <input
              class="w-full bg-transparent border-none text-white placeholder:text-muted-2 text-sm py-1"
              name="tags"
              placeholder="Etiketler (virgülle ayırın, örnek: yazılım, güvenlik)"
              value="<?= h($composerTags) ?>"
              maxlength="150"
            />
            <div class="flex items-center justify-between mt-4 pt-4 border-t border-border gap-3">
              <div class="flex items-center gap-3 media-picker">
                <label class="flex items-center gap-2 text-muted text-sm cursor-pointer hover:text-white transition-colors">
                  <span class="material-symbols-outlined text-xl">perm_media</span>
                  <span class="media-input-label">Medya</span>
                  <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime" class="hidden media-input" />
                </label>
                <img class="media-preview hidden" alt="Seçilen görsel" />
              </div>
              <div class="flex items-center gap-3">
                <label id="draft-wrap" class="flex items-center gap-1.5 text-muted text-sm cursor-pointer <?= $composerIsArticle ? '' : 'hidden' ?>">
                  <input type="checkbox" name="status" value="draft" />
                  Taslak olarak kaydet
                </label>
                <button type="submit" class="btn-primary text-sm px-5 py-2">Paylaş</button>
              </div>
            </div>
            <?php if ($composerError): ?>
              <p class="text-red-400 text-sm mt-3"><?= h($composerError) ?></p>
            <?php endif; ?>
          </form>
        </div>
      </div>
    </div>

    <div id="article-modal" class="confirm-modal-overlay hidden" style="z-index:110">
      <div class="article-modal-card" id="article-modal-body"></div>
    </div>

    <div class="flex border-b border-border px-4">
      <a href="/index.php?feed=all" class="px-5 h-12 flex items-center text-sm font-medium relative <?= $feed === 'all' ? 'text-accent' : 'text-muted hover:text-white' ?> transition-colors">
        Herkes
        <?php if ($feed === 'all'): ?><div class="absolute bottom-0 left-0 w-full h-0.5 bg-accent rounded-full"></div><?php endif; ?>
      </a>
      <a href="/index.php?feed=following" class="px-5 h-12 flex items-center text-sm font-medium relative <?= $feed === 'following' ? 'text-accent' : 'text-muted hover:text-white' ?> transition-colors">
        Takip Edilenler
        <?php if ($feed === 'following'): ?><div class="absolute bottom-0 left-0 w-full h-0.5 bg-accent rounded-full"></div><?php endif; ?>
      </a>
    </div>

    <div class="flex flex-col gap-4 p-4" id="posts-list">
      <?php if (count($posts) > 0): ?>
        <?php foreach ($posts as $post): ?>
          <?php render_post_card($post, $user['id']); ?>
        <?php endforeach; ?>
      <?php elseif ($feed === 'following'): ?>
        <div class="card p-10 text-center">
          <span class="material-symbols-outlined text-5xl text-muted-2 mb-4">group</span>
          <h2 class="text-xl font-bold text-white mb-2">Henüz kimseyi takip etmiyorsun</h2>
          <p class="text-muted">Takip ettiğin kişilerin paylaşımları burada görünecek.</p>
          <a href="/search.php" class="btn-primary mt-4 inline-flex items-center gap-2">
            <span class="material-symbols-outlined text-lg">search</span>
            Kullanıcı Ara
          </a>
        </div>
      <?php else: ?>
        <div class="card p-10 text-center">
          <span class="material-symbols-outlined text-5xl text-muted-2 mb-4">newspaper</span>
          <h2 class="text-xl font-bold text-white mb-2">Henüz paylaşım yok</h2>
          <p class="text-muted">İlk paylaşımı sen yap! Ne düşündüğünü merak ediyoruz.</p>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($hasMore): ?>
      <div class="flex justify-center pb-6">
        <button
          type="button"
          id="load-more-btn"
          class="btn-outline text-sm"
          data-feed="<?= h($feed) ?>"
          data-offset="<?= FEED_PAGE_SIZE ?>"
        >
          Daha fazla yükle
        </button>
      </div>
    <?php endif; ?>
  </main>

  <?php render_right_rail($user['id']); ?>

  <script>
    (function () {
      const trigger = document.getElementById('composer-trigger');
      const panel = document.getElementById('composer-panel');
      const composerCard = document.getElementById('composer-card');
      const closeBtn = document.getElementById('composer-close');
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
      const articleModal = document.getElementById('article-modal');
      const articleModalBody = document.getElementById('article-modal-body');
      if (!trigger || !panel) return;

      // Makale modunda ayni form (ayni node, klonlanmiyor - girilen icerik ve
      // event listener'lar korunuyor) rahat yazim icin buyuk bir modal'a
      // tasiniyor; Gonderi moduna donulunce kucuk karta geri tasiniyor.
      function moveToModal() {
        if (!articleModal.contains(panel)) {
          articleModalBody.appendChild(panel);
        }
        panel.classList.remove('hidden');
        composerCard.classList.add('hidden');
        articleModal.classList.remove('hidden');
      }

      function moveInline() {
        if (articleModal.contains(panel)) {
          composerCard.appendChild(panel);
        }
        articleModal.classList.add('hidden');
        composerCard.classList.remove('hidden');
      }

      function openComposer(mode) {
        trigger.classList.add('hidden');
        composerCard.classList.remove('hidden');
        panel.classList.remove('hidden');
        setComposerMode(mode || (tabArticle.classList.contains('active') ? 'article' : 'post'));
        (mode === 'article' ? titleField : postContentField)?.focus();
      }

      function closeComposer() {
        moveInline();
        panel.classList.add('hidden');
        trigger.classList.remove('hidden');
      }

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

        if (isArticle) {
          moveToModal();
        } else {
          moveInline();
        }
      }

      trigger.addEventListener('click', () => openComposer('post'));
      closeBtn.addEventListener('click', closeComposer);
      tabPost.addEventListener('click', () => setComposerMode('post'));
      tabArticle.addEventListener('click', () => openComposer('article'));
      articleModal.addEventListener('click', (event) => {
        if (event.target === articleModal) setComposerMode('post');
      });
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !articleModal.classList.contains('hidden')) {
          setComposerMode('post');
        }
      });

      // Sayfa dogrulama hatasiyla (composer_error) makale modunda yeniden
      // aciliyorsa, PHP zaten panel'i gorunur ve tab-article'i aktif render
      // etmis oluyor - baslangicta da modal'a tasi.
      if (!panel.classList.contains('hidden') && tabArticle.classList.contains('active')) {
        moveToModal();
      }
    })();
  </script>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
