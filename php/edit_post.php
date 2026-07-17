<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();
$postId = (string) ($_GET['id'] ?? '');

$stmt = db()->prepare('SELECT * FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post || $post['author_id'] !== $user['id']) {
    redirect('/index.php');
}

$stmt = db()->prepare('SELECT t.name FROM tags t JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name');
$stmt->execute([$postId]);
$currentTags = implode(', ', array_column($stmt->fetchAll(), 'name'));

$error = flash_get('error');
$old = flash_old('edit_post');
$title = $old['title'] ?? $post['title'];
$content = $old['content'] ?? $post['content'];
$tags = $old['tags'] ?? $currentTags;
$postType = $post['post_type'] ?? 'article';
$isArticle = $postType === 'article';

$pageTitle = ($isArticle ? 'Makaleyi Düzenle' : 'Gönderiyi Düzenle') . ' - ProBlog';
$activePage = 'home';
$useMarkdownEditor = $isArticle;
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[900px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-14 flex items-center px-4 gap-4">
      <a href="/index.php" class="p-1 hover:bg-surface-3 rounded-full transition-colors">
        <span class="material-symbols-outlined">arrow_back</span>
      </a>
      <h1 class="font-bold"><?= $isArticle ? 'Makaleyi Düzenle' : 'Gönderiyi Düzenle' ?></h1>
    </header>

    <div class="p-4">
      <div class="card p-5 sm:p-8">
        <form method="post" action="/actions/update_post.php" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="post_id" value="<?= h($post['id']) ?>" />
          <?php if ($isArticle): ?>
            <input
              class="w-full bg-transparent border-none text-white placeholder:text-muted-2 text-3xl font-extrabold py-2 mb-2"
              name="title"
              placeholder="Başlık"
              value="<?= h($title) ?>"
              minlength="4"
              maxlength="120"
              required
            />
            <div data-milkdown-target="edit-post-content">
              <textarea
                id="edit-post-content"
                class="w-full bg-transparent border-none resize-none text-white placeholder:text-muted-2 text-lg font-medium py-2"
                name="content"
                placeholder="Ne düşünüyorsun? (Markdown destekli)"
                rows="6"
                minlength="20"
                maxlength="100000"
                required
              ><?= h($content) ?></textarea>
            </div>
          <?php else: ?>
            <textarea
              class="w-full bg-transparent border-none resize-none text-white placeholder:text-muted-2 text-xl py-2"
              name="content"
              placeholder="Ne düşünüyorsun?"
              rows="4"
              maxlength="500"
              required
            ><?= h($content) ?></textarea>
          <?php endif; ?>
          <input
            class="w-full bg-transparent border-none text-white placeholder:text-muted-2 text-sm py-1"
            name="tags"
            placeholder="Etiketler (virgülle ayırın, örnek: yazılım, güvenlik)"
            value="<?= h($tags) ?>"
            maxlength="150"
          />
          <?php if (!empty($post['image_path'])): ?>
            <p class="text-muted-2 text-xs mt-2">Mevcut medya korunacak, yeni bir dosya seçersen değiştirilir.</p>
          <?php endif; ?>
          <div class="flex items-center justify-between mt-4 pt-4 border-t border-border gap-3">
            <label class="flex items-center gap-2 text-muted text-sm cursor-pointer hover:text-white transition-colors">
              <span class="material-symbols-outlined text-xl">perm_media</span>
              <span>Medya</span>
              <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime" class="hidden" onchange="this.previousElementSibling.textContent = this.files[0] ? this.files[0].name : 'Medya'" />
            </label>
            <div class="flex items-center gap-3">
              <a href="/index.php" class="btn-outline text-sm px-5 py-2">Vazgeç</a>
              <button type="submit" class="btn-primary text-sm px-5 py-2">Kaydet</button>
            </div>
          </div>
          <?php if ($error): ?>
            <p class="text-red-400 text-sm mt-3"><?= h($error) ?></p>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </main>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
