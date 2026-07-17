<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();

$q = trim((string) ($_GET['q'] ?? ''));
$results = [];

if ($q !== '') {
    $stmt = db()->prepare(
        'SELECT id, name, bio, avatar_url FROM users WHERE name LIKE ? AND id != ? ORDER BY name LIMIT 20'
    );
    $stmt->execute(['%' . $q . '%', $user['id']]);
    $results = $stmt->fetchAll();
}

$pageTitle = 'Ara - ProBlog';
$activePage = 'search';
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[680px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-16 flex items-center px-6 justify-between">
      <h1 class="font-bold text-lg">Ara</h1>
    </header>

    <div class="p-4 border-b border-border">
      <form method="get" action="/search.php" class="relative">
        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-muted-2">search</span>
        <input
          class="w-full bg-surface-2 border border-border rounded-xl pl-11 pr-4 py-3 text-white placeholder:text-muted-2 focus:border-accent focus:ring-1 focus:ring-accent/30 transition-all"
          type="text"
          name="q"
          value="<?= h($q) ?>"
          placeholder="Kullanıcı adıyla ara..."
          autofocus
        />
      </form>
    </div>

    <div class="flex flex-col gap-4 p-4">
      <?php if ($q === ''): ?>
        <div class="card p-10 text-center">
          <span class="material-symbols-outlined text-5xl text-muted-2 mb-4">search</span>
          <h2 class="text-xl font-bold text-white mb-2">Kullanıcı ara</h2>
          <p class="text-muted">Bulmak istediğin kişinin adını yaz.</p>
        </div>
      <?php elseif (count($results) === 0): ?>
        <div class="card p-10 text-center">
          <span class="material-symbols-outlined text-5xl text-muted-2 mb-4">person_search</span>
          <h2 class="text-xl font-bold text-white mb-2">Sonuç bulunamadı</h2>
          <p class="text-muted">"<?= h($q) ?>" ile eşleşen bir kullanıcı yok.</p>
        </div>
      <?php else: ?>
        <div class="card overflow-hidden">
          <?php foreach ($results as $result): ?>
            <a href="/profile.php?id=<?= h($result['id']) ?>"
               class="flex items-center gap-3 px-5 py-4 hover:bg-surface-3 transition-colors border-b border-border last:border-0">
              <?= render_avatar($result) ?>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-white truncate"><?= h($result['name']) ?></p>
                <p class="text-xs text-muted-2 truncate"><?= h($result['bio'] ?: 'ProBlog üyesi') ?></p>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
