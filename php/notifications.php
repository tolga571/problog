<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();
$pdo = db();

$stmt = $pdo->prepare(
    'SELECT n.*, u.name AS actor_name, u.avatar_url AS actor_avatar_url, p.title AS post_title, p.author_id AS post_author_id
     FROM notifications n
     JOIN users u ON u.id = n.actor_id
     LEFT JOIN posts p ON p.id = n.post_id
     WHERE n.user_id = ?
     ORDER BY n.created_at DESC
     LIMIT 50'
);
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

$pdo->prepare('UPDATE notifications SET read_at = datetime(\'now\') WHERE user_id = ? AND read_at IS NULL')
    ->execute([$user['id']]);

function notification_text(array $n): string
{
    $actor = '<b class="font-semibold text-white">' . h($n['actor_name']) . '</b>';
    switch ($n['type']) {
        case 'like':
            return $actor . ' gönderini beğendi: ' . h($n['post_title'] ?? '');
        case 'comment':
            return $actor . ' gönderine yorum yaptı: ' . h($n['post_title'] ?? '');
        case 'comment_reply':
            return $actor . ' yorumuna yanıt verdi: ' . h($n['post_title'] ?? '');
        case 'mention':
            return $actor . ' bir yorumda senden bahsetti: ' . h($n['post_title'] ?? '');
        case 'follow':
            return $actor . ' seni takip etmeye başladı';
        case 'friend_request':
            return $actor . ' sana arkadaşlık isteği gönderdi';
        case 'friend_accept':
            return $actor . ' arkadaşlık isteğini kabul etti';
        case 'message':
            return $actor . ' sana mesaj gönderdi';
        default:
            return $actor . ' bir işlem yaptı';
    }
}

function notification_icon(array $n): string
{
    return match ($n['type']) {
        'like' => 'favorite',
        'comment' => 'chat_bubble',
        'comment_reply' => 'reply',
        'mention' => 'alternate_email',
        'follow' => 'person_add',
        'friend_request', 'friend_accept' => 'group',
        'message' => 'chat',
        default => 'notifications',
    };
}

function notification_badge_class(array $n): string
{
    return $n['type'] === 'like' ? 'notif-badge-like' : 'notif-badge-accent';
}

function notification_link(array $n, string $ownerId): string
{
    if (in_array($n['type'], ['follow', 'friend_request', 'friend_accept'], true)) {
        return '/profile.php?id=' . urlencode($n['actor_id']);
    }
    if ($n['type'] === 'message') {
        return '/messages.php?with=' . urlencode($n['actor_id']);
    }
    // Yazı ile ilgili bildirimler (like/comment/comment_reply/mention) yazının
    // yazarının profiline gider - orada görünür (henüz tekil yazı/permalink
    // sayfası yok). comment_reply/mention için bildirim sahibi post yazarı
    // olmayabilir, bu yüzden $ownerId yerine post_author_id kullanılıyor.
    return '/profile.php?id=' . urlencode($n['post_author_id'] ?? $ownerId);
}

$pageTitle = 'Bildirimler - ProBlog';
$activePage = 'notifications';
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[680px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-16 flex items-center px-6 justify-between">
      <h1 class="font-bold text-lg">Bildirimler</h1>
    </header>

    <div class="flex flex-col gap-4 p-4">
      <?php if (count($notifications) > 0): ?>
        <div class="card overflow-hidden">
          <?php foreach ($notifications as $n): ?>
            <div class="notif-row <?= $n['read_at'] ? '' : 'unread' ?>">
              <a href="<?= h(notification_link($n, $user['id'])) ?>" class="notif-avatar-wrap flex-shrink-0">
                <?= render_avatar(['name' => $n['actor_name'], 'avatar_url' => $n['actor_avatar_url']], 'avatar avatar-sm') ?>
                <span class="notif-badge <?= h(notification_badge_class($n)) ?>">
                  <span class="material-symbols-outlined"><?= h(notification_icon($n)) ?></span>
                </span>
              </a>
              <a href="<?= h(notification_link($n, $user['id'])) ?>" class="flex-1 min-w-0">
                <p class="text-sm leading-tight text-muted"><?= notification_text($n) ?></p>
                <p class="text-muted-2 text-xs" style="margin-top:4px"><?= h(time_ago($n['created_at'])) ?></p>
              </a>
              <?php if ($n['type'] === 'friend_request' && friendship_status($user['id'], $n['actor_id']) === 'pending_received'): ?>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <button type="button" class="friend-accept-btn btn-primary text-xs px-3 py-1.5" data-target-id="<?= h($n['actor_id']) ?>">Kabul Et</button>
                  <button type="button" class="friend-decline-btn btn-outline text-xs p-2" data-target-id="<?= h($n['actor_id']) ?>" title="Reddet" aria-label="Reddet">
                    <span class="material-symbols-outlined text-base">close</span>
                  </button>
                </div>
              <?php elseif (!$n['read_at']): ?>
                <span class="notif-dot flex-shrink-0"></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="card p-10 text-center">
          <span class="material-symbols-outlined text-5xl text-muted-2 mb-4">notifications</span>
          <h2 class="text-xl font-bold text-white mb-2">Henüz bildirim yok</h2>
          <p class="text-muted">Biri seni beğenip yorum yapıp takip ettiğinde burada görünecek.</p>
        </div>
      <?php endif; ?>
    </div>
  </main>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
