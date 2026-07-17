<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/post_card.php';
require_once __DIR__ . '/includes/right_rail.php';

$user = require_login();
$profileId = (string) ($_GET['id'] ?? '');

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$profileId]);
$profile = $stmt->fetch();

if (!$profile) {
    $pageTitle = 'Profil bulunamadı - ProBlog';
    $activePage = '';
    require __DIR__ . '/includes/layout_head.php';
    ?>
    <main class="flex-1 flex items-center justify-center min-h-screen">
      <div class="text-center card p-8 max-w-md">
        <span class="material-symbols-outlined text-5xl text-muted-2 mb-4">error</span>
        <h2 class="text-xl font-bold text-white mb-2">Profil bulunamadı</h2>
        <p class="text-muted mb-4">Bu kullanıcı mevcut değil veya silinmiş olabilir.</p>
        <a href="/index.php" class="btn-primary inline-flex">Ana Sayfaya Dön</a>
      </div>
    </main>
    <?php
    require __DIR__ . '/includes/layout_foot.php';
    exit;
}

$isOwnProfile = $user['id'] === $profile['id'];

$isFollowing = false;
$friendStatus = 'self';
$isBlockedByMe = false;
$hasBlockedMe = false;
$isMuted = false;
if (!$isOwnProfile) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = ?');
    $stmt->execute([$user['id'], $profileId]);
    $isFollowing = ((int) $stmt->fetchColumn()) > 0;
    $friendStatus = friendship_status($user['id'], $profileId);
    $isBlockedByMe = has_blocked($user['id'], $profileId);
    $hasBlockedMe = has_blocked($profileId, $user['id']);
    $isMuted = is_muted($user['id'], $profileId);
}

$friendsCount = friends_count($profileId);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM follows WHERE following_id = ?');
$stmt->execute([$profileId]);
$followersCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM follows WHERE follower_id = ?');
$stmt->execute([$profileId]);
$followingCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM likes WHERE post_id IN (SELECT id FROM posts WHERE author_id = ?)');
$stmt->execute([$profileId]);
$totalLikes = (int) $stmt->fetchColumn();

$restricted = $hasBlockedMe;

$tab = (string) ($_GET['tab'] ?? 'posts');
if (!in_array($tab, ['posts', 'articles', 'media'], true)) {
    $tab = 'posts';
}

$statusSql = $isOwnProfile ? '1=1' : "status = 'published'";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE author_id = ? AND {$statusSql}");
$stmt->execute([$profileId]);
$totalPostCount = (int) $stmt->fetchColumn();

$profilePosts = [];
if (!$restricted && !$isBlockedByMe) {
    if ($tab === 'articles') {
        $typeSql = "post_type = 'article'";
    } elseif ($tab === 'media') {
        $typeSql = "image_path != ''";
    } else {
        $typeSql = "post_type = 'post'";
    }

    $stmt = $pdo->prepare("SELECT * FROM posts WHERE author_id = ? AND {$statusSql} AND {$typeSql} ORDER BY created_at DESC");
    $stmt->execute([$profileId]);
    $profilePosts = array_map(fn($post) => post_with_details($post, $user['id']), $stmt->fetchAll());
}

$pageTitle = h($profile['name']) . ' - ProBlog';
$activePage = $isOwnProfile ? 'profile' : '';
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[680px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-14 flex items-center px-4 gap-4">
      <a href="/index.php" class="p-1 hover:bg-surface-3 rounded-full transition-colors">
        <span class="material-symbols-outlined">arrow_back</span>
      </a>
      <div>
        <h1 class="font-bold"><?= h($profile['name']) ?></h1>
        <p class="text-muted-2 text-xs"><?= $totalPostCount ?> gönderi</p>
      </div>
    </header>

    <div class="relative">
      <div class="w-full h-56 overflow-hidden relative">
        <?= render_cover($profile) ?>
        <div class="absolute inset-0 bg-gradient-to-t from-surface-1 via-transparent to-transparent"></div>
        <?php if ($isOwnProfile): ?>
          <a href="/edit_profile.php" class="cover-edit-btn btn-outline text-sm flex items-center gap-1.5" style="background:var(--color-surface-1); backdrop-filter:blur(4px)">
            <span class="material-symbols-outlined text-lg">photo_camera</span>
            Kapak fotoğrafı düzenle
          </a>
        <?php endif; ?>
      </div>

      <div class="px-6 pb-6 -mt-20 relative z-10">
        <div class="flex justify-between items-end mb-5">
          <div class="p-1 bg-surface-2 rounded-2xl shadow-xl shadow-black/30">
            <?= render_avatar($profile, 'avatar-xl') ?>
          </div>
          <div class="flex items-center gap-2 mb-2">
            <div class="action-menu">
              <button type="button" class="action-menu-btn profile-icon-btn" title="Seçenekler" aria-label="Seçenekler">
                <span class="material-symbols-outlined text-lg">more_horiz</span>
              </button>
              <div class="action-menu-dropdown hidden" style="right:0;">
                <button type="button" class="copy-profile-link-btn" data-profile-id="<?= h($profile['id']) ?>">Profil linkini kopyala</button>
                <?php if (!$isOwnProfile): ?>
                  <button type="button" class="mute-btn" data-target-id="<?= h($profile['id']) ?>" data-muted="<?= $isMuted ? '1' : '0' ?>"><?= $isMuted ? 'Sesi Aç' : 'Sessize Al' ?></button>
                  <button type="button" class="block-btn" data-target-id="<?= h($profile['id']) ?>" data-blocked="<?= $isBlockedByMe ? '1' : '0' ?>"><?= $isBlockedByMe ? 'Engeli Kaldır' : 'Engelle' ?></button>
                  <?php if (!$isBlockedByMe): ?>
                    <button type="button" class="report-btn" data-target-id="<?= h($profile['id']) ?>">Şikayet Et</button>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>

            <?php if ($isOwnProfile): ?>
              <a href="/edit_profile.php" class="btn-outline rounded-full text-sm flex items-center gap-1.5">
                <span class="material-symbols-outlined text-lg">edit</span>
                Düzenle
              </a>
            <?php elseif ($restricted): ?>
              <!-- profil sahibi seni engellemis: baska aksiyon gosterilmez -->
            <?php elseif ($isBlockedByMe): ?>
              <button type="button" class="block-btn btn-outline rounded-full text-sm px-6" data-target-id="<?= h($profile['id']) ?>" data-blocked="1">Engeli Kaldır</button>
            <?php else: ?>
              <?php if ($friendStatus === 'friends'): ?>
                <a href="/messages.php?with=<?= h($profile['id']) ?>" class="profile-icon-btn" title="Mesaj gönder" aria-label="Mesaj gönder">
                  <span class="material-symbols-outlined text-lg">chat_bubble</span>
                </a>
              <?php endif; ?>
              <?php render_friend_button($friendStatus, $profile['id']); ?>
              <button
                type="button"
                class="follow-btn btn-primary rounded-full text-sm px-6 <?= $isFollowing ? 'is-following' : '' ?>"
                data-target-id="<?= h($profile['id']) ?>"
                data-following="<?= $isFollowing ? '1' : '0' ?>"
              >
                <?= $isFollowing ? 'Takip Ediliyor' : 'Takip Et' ?>
              </button>
            <?php endif; ?>
          </div>
        </div>

        <div class="space-y-3">
          <div>
            <h2 class="text-2xl font-extrabold tracking-tight"><?= h($profile['name']) ?></h2>
            <p class="text-accent text-sm font-medium uppercase tracking-wide"><?= h($profile['bio'] ?: 'ProBlog yazarı') ?></p>
          </div>

          <?php if ($profile['bio']): ?>
            <div class="border-l-2 border-accent/50 pl-4 py-1">
              <p class="text-muted text-sm leading-relaxed"><?= h($profile['bio']) ?></p>
            </div>
          <?php endif; ?>

          <div class="profile-about-row text-muted text-sm">
            <?php if (!empty($profile['city'])): ?>
              <span class="flex items-center gap-1">
                <span class="material-symbols-outlined text-base">location_on</span>
                <?= h($profile['city']) ?>
              </span>
            <?php endif; ?>
            <?php if (!empty($profile['birth_date'])): ?>
              <span class="flex items-center gap-1">
                <span class="material-symbols-outlined text-base">cake</span>
                <?= h(format_birthdate($profile['birth_date'])) ?>
              </span>
            <?php endif; ?>
            <?php if (!empty($profile['website'])): ?>
              <span class="flex items-center gap-1">
                <span class="material-symbols-outlined text-base">link</span>
                <a href="<?= h($profile['website']) ?>" target="_blank" rel="noopener noreferrer nofollow" class="hover:text-accent transition-colors hover:underline"><?= h(preg_replace('#^https?://#i', '', $profile['website'])) ?></a>
              </span>
            <?php endif; ?>
            <span class="flex items-center gap-1">
              <span class="material-symbols-outlined text-base">calendar_month</span>
              Katılım: <?= h(format_date($profile['created_at'])) ?>
            </span>
          </div>

          <div class="flex gap-2 pt-4 border-t border-border flex-wrap">
            <div class="profile-stat"><span class="profile-stat-num" id="followers-count"><?= $followersCount ?></span><span class="profile-stat-label">Takipçi</span></div>
            <div class="profile-stat"><span class="profile-stat-num"><?= $followingCount ?></span><span class="profile-stat-label">Takip</span></div>
            <a href="/friends.php?id=<?= h($profile['id']) ?>" class="profile-stat profile-stat-link"><span class="profile-stat-num" id="friends-count"><?= $friendsCount ?></span><span class="profile-stat-label">Arkadaş</span></a>
            <div class="profile-stat"><span class="profile-stat-num"><?= $totalLikes ?></span><span class="profile-stat-label">Beğeni</span></div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($restricted): ?>
      <div class="card p-10 text-center m-4">
        <span class="material-symbols-outlined text-5xl text-muted-2 mb-4">block</span>
        <h2 class="text-xl font-bold text-white mb-2">Bu hesaba erişemezsin</h2>
        <p class="text-muted"><?= h($profile['name']) ?> seni engellemiş.</p>
      </div>
    <?php elseif ($isBlockedByMe): ?>
      <div class="card p-10 text-center m-4">
        <span class="material-symbols-outlined text-5xl text-muted-2 mb-4">block</span>
        <h2 class="text-xl font-bold text-white mb-2">Bu kullanıcıyı engellediniz</h2>
        <p class="text-muted">Gönderilerini görmek için engeli kaldırman gerekir.</p>
      </div>
    <?php else: ?>
      <div class="flex border-b border-border sticky top-14 z-30 glass">
        <a href="?id=<?= h($profileId) ?>&tab=posts" class="composer-tab <?= $tab === 'posts' ? 'active' : '' ?>">Gönderiler</a>
        <a href="?id=<?= h($profileId) ?>&tab=articles" class="composer-tab <?= $tab === 'articles' ? 'active' : '' ?>">Makale</a>
        <a href="?id=<?= h($profileId) ?>&tab=media" class="composer-tab <?= $tab === 'media' ? 'active' : '' ?>">Medya</a>
      </div>

      <div class="flex flex-col gap-4 p-4">
        <?php if (count($profilePosts) > 0): ?>
          <?php foreach ($profilePosts as $post): ?>
            <?php render_post_card($post, $user['id']); ?>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="card p-10 text-center">
            <?php if ($tab === 'articles'): ?>
              <span class="material-symbols-outlined text-5xl text-muted-2 mb-4">description</span>
              <h2 class="text-xl font-bold text-white mb-2">Henüz makale yok</h2>
              <p class="text-muted">
                <?= $isOwnProfile ? 'İlk makaleni yayınlamaya ne dersin?' : 'Bu kullanıcı henüz makale paylaşmadı.' ?>
              </p>
              <?php if ($isOwnProfile): ?>
                <a href="/new_post.php?type=article" class="btn-primary mt-4 inline-flex items-center gap-2">
                  <span class="material-symbols-outlined text-lg">edit_note</span>
                  Makale Yaz
                </a>
              <?php endif; ?>
            <?php elseif ($tab === 'media'): ?>
              <span class="material-symbols-outlined text-5xl text-muted-2 mb-4">perm_media</span>
              <h2 class="text-xl font-bold text-white mb-2">Henüz medya yok</h2>
              <p class="text-muted">
                <?= $isOwnProfile ? 'Görsel veya video paylaştığın gönderiler burada listelenecek.' : 'Bu kullanıcı henüz görsel veya video paylaşmadı.' ?>
              </p>
            <?php else: ?>
              <span class="material-symbols-outlined text-5xl text-muted-2 mb-4">chat_bubble</span>
              <h2 class="text-xl font-bold text-white mb-2">Henüz gönderi yok</h2>
              <p class="text-muted">
                <?= $isOwnProfile ? 'İlk gönderini paylaşmaya ne dersin?' : 'Bu kullanıcı henüz gönderi paylaşmadı.' ?>
              </p>
              <?php if ($isOwnProfile): ?>
                <a href="/new_post.php" class="btn-primary mt-4 inline-flex items-center gap-2">
                  <span class="material-symbols-outlined text-lg">edit_note</span>
                  Gönderi Paylaş
                </a>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>

  <?php render_right_rail($user['id']); ?>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
