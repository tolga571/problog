<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/right_rail.php';

$user = require_login();
$profileId = (string) ($_GET['id'] ?? '');

$pdo = db();
$stmt = $pdo->prepare('SELECT id, name FROM users WHERE id = ?');
$stmt->execute([$profileId]);
$profile = $stmt->fetch();

if (!$profile) {
    redirect('/index.php');
}

$ids = friend_ids($profileId);
$friends = [];
if (count($ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, bio, avatar_url FROM users WHERE id IN ($placeholders) ORDER BY name");
    $stmt->execute($ids);
    $friends = $stmt->fetchAll();
}

$isOwnProfile = $user['id'] === $profileId;
$pageTitle = h($profile['name']) . ' - Arkadaşlar - ProBlog';
$activePage = '';
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[680px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-14 flex items-center px-4 gap-4">
      <a href="/profile.php?id=<?= h($profileId) ?>" class="p-1 hover:bg-surface-3 rounded-full transition-colors">
        <span class="material-symbols-outlined">arrow_back</span>
      </a>
      <div>
        <h1 class="font-bold"><?= $isOwnProfile ? 'Arkadaşlarım' : h($profile['name']) . ' - Arkadaşlar' ?></h1>
        <p class="text-muted-2 text-xs"><?= count($friends) ?> arkadaş</p>
      </div>
    </header>

    <div class="flex flex-col gap-4 p-4">
      <?php if (count($friends) > 0): ?>
        <div class="card overflow-hidden">
          <?php foreach ($friends as $friend): ?>
            <a href="/profile.php?id=<?= h($friend['id']) ?>"
               class="flex items-center gap-3 px-5 py-4 hover:bg-surface-3 transition-colors border-b border-border last:border-0">
              <?= render_avatar($friend) ?>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-white truncate"><?= h($friend['name']) ?></p>
                <p class="text-xs text-muted-2 truncate"><?= h($friend['bio'] ?: 'ProBlog üyesi') ?></p>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="card p-10 text-center">
          <span class="material-symbols-outlined text-5xl text-muted-2 mb-4">group</span>
          <h2 class="text-xl font-bold text-white mb-2">Henüz arkadaş yok</h2>
          <p class="text-muted">
            <?= $isOwnProfile ? 'Arama sayfasından yeni insanlar bulup arkadaş ekleyebilirsin.' : 'Bu kullanıcının henüz arkadaşı yok.' ?>
          </p>
          <?php if ($isOwnProfile): ?>
            <a href="/search.php" class="btn-primary mt-4 inline-flex items-center gap-2">
              <span class="material-symbols-outlined text-lg">search</span>
              Kullanıcı Ara
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <?php render_right_rail($user['id']); ?>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
