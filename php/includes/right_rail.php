<?php

declare(strict_types=1);

function render_right_rail(string $currentUserId): void
{
    $pdo = db();

    $topPosts = $pdo->query(
        "SELECT p.id, p.title, u.id AS author_id, u.name AS author_name,
                (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS like_count
         FROM posts p
         JOIN users u ON u.id = p.author_id
         WHERE p.status = 'published'
         ORDER BY like_count DESC, p.created_at DESC
         LIMIT 3"
    )->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT id, name, bio, avatar_url FROM users
         WHERE id != ?
           AND id NOT IN (SELECT following_id FROM follows WHERE follower_id = ?)
         ORDER BY created_at DESC
         LIMIT 4"
    );
    $stmt->execute([$currentUserId, $currentUserId]);
    $suggestionRows = $stmt->fetchAll();
    $hasMoreSuggestions = count($suggestionRows) > 3;
    $suggestions = array_slice($suggestionRows, 0, 3);
    ?>
    <aside class="hidden lg:flex flex-col w-[350px] h-screen sticky top-0 px-4 py-6 gap-6 overflow-y-auto">
      <form action="/search.php" method="get" class="relative flex-shrink-0">
        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-2 text-lg">search</span>
        <input type="text" name="q" class="input-field rounded-full pl-11" placeholder="Ara" autocomplete="off" />
      </form>

      <?php if (count($topPosts) > 0): ?>
        <section class="card overflow-hidden">
          <h3 class="font-bold text-sm px-5 py-4 border-b border-border">Popüler Yazılar</h3>
          <?php foreach ($topPosts as $topPost): ?>
            <a href="/profile.php?id=<?= h($topPost['author_id']) ?>"
               class="block px-5 py-3 hover:bg-surface-3 transition-colors border-b border-border last:border-0">
              <p class="text-sm font-medium text-white truncate"><?= h($topPost['title']) ?></p>
              <p class="text-xs text-muted-2 mt-0.5">
                <?= h($topPost['author_name']) ?> &middot; <?= (int) $topPost['like_count'] ?> beğeni
              </p>
            </a>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>

      <?php if (count($suggestions) > 0): ?>
        <section class="card overflow-hidden">
          <h3 class="font-bold text-sm px-5 py-4 border-b border-border">Kimi takip etmeli</h3>
          <?php foreach ($suggestions as $suggestion): ?>
            <div class="flex items-center gap-3 px-5 py-3 border-b border-border last:border-0">
              <a href="/profile.php?id=<?= h($suggestion['id']) ?>" class="flex-shrink-0">
                <?= render_avatar($suggestion, 'avatar avatar-sm') ?>
              </a>
              <a href="/profile.php?id=<?= h($suggestion['id']) ?>" class="flex-1 min-w-0">
                <p class="text-sm font-medium text-white truncate"><?= h($suggestion['name']) ?></p>
                <p class="text-xs text-muted-2 truncate"><?= h($suggestion['bio'] ?: 'ProBlog üyesi') ?></p>
              </a>
              <button
                type="button"
                class="follow-btn suggest-follow-btn flex-shrink-0"
                data-target-id="<?= h($suggestion['id']) ?>"
                data-following="0"
              >Takip Et</button>
            </div>
          <?php endforeach; ?>
          <?php if ($hasMoreSuggestions): ?>
            <a href="/search.php" class="block px-5 py-3.5 text-accent text-sm font-medium hover:bg-surface-3 transition-colors">Daha fazlasını gör</a>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <footer class="px-3 text-muted-2 text-xs flex-shrink-0">
        <nav class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
          <a href="#" class="hover:underline">Şartlar</a>
          <span>&middot;</span>
          <a href="#" class="hover:underline">Gizlilik</a>
          <span>&middot;</span>
          <a href="#" class="hover:underline">Çerezler</a>
          <span>&middot;</span>
          <a href="#" class="hover:underline">Erişilebilirlik</a>
          <span>&middot;</span>
          <a href="#" class="hover:underline">Reklam Bilgisi</a>
          <span>&middot;</span>
          <a href="#" class="hover:underline">Daha fazla</a>
        </nav>
        <p class="mt-2">&copy; 2026 ProBlog</p>
      </footer>
    </aside>
    <?php
}
