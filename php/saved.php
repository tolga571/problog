<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/post_card.php';
require_once __DIR__ . '/includes/right_rail.php';

$user = require_login();

$stmt = db()->prepare(
    "SELECT p.* FROM posts p
     JOIN bookmarks b ON b.post_id = p.id
     WHERE b.user_id = ? AND p.status = 'published'
     ORDER BY b.created_at DESC"
);
$stmt->execute([$user['id']]);
$posts = array_map(fn($post) => post_with_details($post, $user['id']), $stmt->fetchAll());

$pageTitle = 'Kaydedilenler - ProBlog';
$activePage = 'saved';
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[680px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-14 flex items-center px-4 gap-4">
      <a href="/index.php" class="p-1 hover:bg-surface-3 rounded-full transition-colors">
        <span class="material-symbols-outlined">arrow_back</span>
      </a>
      <h1 class="font-bold">Kaydedilenler</h1>
    </header>

    <div class="flex flex-col gap-4 p-4">
      <?php if (count($posts) > 0): ?>
        <?php foreach ($posts as $post): ?>
          <?php render_post_card($post, $user['id']); ?>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="card p-10 text-center">
          <span class="material-symbols-outlined text-5xl text-muted-2 mb-4">bookmark_border</span>
          <h2 class="text-xl font-bold text-white mb-2">Henüz kaydedilen yazı yok</h2>
          <p class="text-muted">Daha sonra tekrar okumak istediğin yazıları kaydet butonuyla buraya ekleyebilirsin.</p>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <?php render_right_rail($user['id']); ?>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
