<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/message_bubble.php';

$user = require_login();
$pdo = db();

$withId = (string) ($_GET['with'] ?? '');
$activeConversationId = null;
$activePartner = null;
$activeMessages = [];

if ($withId !== '') {
    if ($withId === $user['id'] || !are_friends($user['id'], $withId)) {
        flash_set('error', 'Sadece arkadaşlarınla mesajlaşabilirsin.');
        redirect('/messages.php');
    }

    $stmt = $pdo->prepare('SELECT id, name, username, bio, avatar_url FROM users WHERE id = ?');
    $stmt->execute([$withId]);
    $activePartner = $stmt->fetch();

    if (!$activePartner) {
        redirect('/messages.php');
    }

    $one = $user['id'] < $withId ? $user['id'] : $withId;
    $two = $user['id'] < $withId ? $withId : $user['id'];
    $stmt = $pdo->prepare('SELECT id FROM conversations WHERE user_one_id = ? AND user_two_id = ?');
    $stmt->execute([$one, $two]);
    $convRow = $stmt->fetch();

    if ($convRow) {
        $activeConversationId = $convRow['id'];
        $stmt = $pdo->prepare('SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC');
        $stmt->execute([$activeConversationId]);
        $activeMessages = $stmt->fetchAll();

        $pdo->prepare(
            'UPDATE messages SET read_at = ?
             WHERE conversation_id = ? AND sender_id != ? AND read_at IS NULL'
        )->execute([now_utc(), $activeConversationId, $user['id']]);
    }
}

$stmt = $pdo->prepare(
    "SELECT c.*,
        (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_content,
        (SELECT deleted_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_deleted_at,
        (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_created_at,
        (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != ? AND read_at IS NULL AND deleted_at IS NULL) AS unread_count
     FROM conversations c
     WHERE c.user_one_id = ? OR c.user_two_id = ?
     ORDER BY c.updated_at DESC"
);
$stmt->execute([$user['id'], $user['id'], $user['id']]);
$conversationRows = $stmt->fetchAll();

$conversations = [];
foreach ($conversationRows as $row) {
    $partnerId = conversation_partner_id($row, $user['id']);
    $stmt = $pdo->prepare('SELECT id, name, avatar_url FROM users WHERE id = ?');
    $stmt->execute([$partnerId]);
    $partner = $stmt->fetch();
    if (!$partner) {
        continue;
    }
    $row['partner'] = $partner;
    $conversations[] = $row;
}

$generalError = flash_get('error');
$pageTitle = 'Mesajlar - ProBlog';
$activePage = 'messages';
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 min-h-screen">
    <div class="messages-shell <?= $activeConversationId || $activePartner ? 'has-active' : '' ?>">
      <div class="messages-list">
        <header class="sticky top-0 z-10 glass h-16 flex items-center px-5 flex-shrink-0">
          <h1 class="font-bold text-lg">Mesajlar</h1>
        </header>

        <?php if ($generalError): ?>
          <div class="px-4 pt-4">
            <p class="text-red-400 text-sm bg-red-500/10 border border-red-500/20 px-4 py-3 rounded-xl"><?= h($generalError) ?></p>
          </div>
        <?php endif; ?>

        <?php if (count($conversations) > 0): ?>
          <div class="px-4 py-3 border-b border-border">
            <div class="relative">
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-muted-2 text-lg">search</span>
              <input type="text" id="conversation-search" class="input-field pl-10 text-sm" placeholder="Sohbetlerde ara..." autocomplete="off" />
            </div>
          </div>
        <?php endif; ?>

        <?php if (count($conversations) === 0): ?>
          <div class="p-5 text-center">
            <span class="material-symbols-outlined text-4xl text-muted-2 mb-4">forum</span>
            <p class="text-muted text-sm mb-4">Henüz bir sohbetin yok.</p>
            <a href="/friends.php?id=<?= h($user['id']) ?>" class="btn-primary text-sm inline-flex items-center gap-2">
              <span class="material-symbols-outlined text-lg">group</span>
              Arkadaşlarım
            </a>
          </div>
        <?php else: ?>
          <?php foreach ($conversations as $conv): ?>
            <?php
              $isActive = $activePartner && $conv['partner']['id'] === $activePartner['id'];
              $preview = $conv['last_deleted_at'] ? 'Bu mesaj silindi' : (string) ($conv['last_content'] ?? '');
            ?>
            <a href="/messages.php?with=<?= h($conv['partner']['id']) ?>"
               class="conversation-row flex items-center gap-3 px-4 py-3.5 border-b border-border hover:bg-surface-3 transition-colors <?= $isActive ? 'bg-surface-3' : '' ?>"
               data-name="<?= h(mb_strtolower($conv['partner']['name'])) ?>">
              <?= render_avatar($conv['partner']) ?>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                  <p class="text-sm font-semibold text-white truncate"><?= h($conv['partner']['name']) ?></p>
                  <?php if ($conv['last_created_at']): ?>
                    <span class="text-muted-2 text-xs flex-shrink-0"><?= h(time_ago($conv['last_created_at'])) ?></span>
                  <?php endif; ?>
                </div>
                <p class="text-xs truncate <?= (int) $conv['unread_count'] > 0 ? 'text-white font-medium' : 'text-muted-2' ?>">
                  <?= h(mb_strimwidth($preview, 0, 60, '...')) ?>
                </p>
              </div>
              <?php if ((int) $conv['unread_count'] > 0): ?>
                <span class="bg-accent text-white text-xs font-semibold rounded-full flex-shrink-0" style="padding:2px 8px"><?= (int) $conv['unread_count'] ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="messages-view" data-conversation-id="<?= h((string) $activeConversationId) ?>" data-partner-id="<?= h((string) ($activePartner['id'] ?? '')) ?>">
        <?php if ($activePartner): ?>
          <header class="sticky top-0 z-10 glass h-16 flex items-center px-4 gap-3 flex-shrink-0">
            <a href="/messages.php" class="md:hidden p-1 hover:bg-surface-3 rounded-full transition-colors">
              <span class="material-symbols-outlined">arrow_back</span>
            </a>
            <a href="/profile.php?id=<?= h($activePartner['id']) ?>" class="flex items-center gap-3 min-w-0 flex-1">
              <?= render_avatar($activePartner) ?>
              <span class="min-w-0">
                <span class="font-semibold text-sm truncate block"><?= h($activePartner['name']) ?></span>
                <?php if (!empty($activePartner['username'])): ?>
                  <span class="text-muted-2 text-xs truncate block">@<?= h($activePartner['username']) ?></span>
                <?php endif; ?>
              </span>
            </a>
            <a href="/profile.php?id=<?= h($activePartner['id']) ?>" class="chat-widget-icon-btn" title="Profili gör" aria-label="Profili gör">
              <span class="material-symbols-outlined">person</span>
            </a>
          </header>

          <div class="flex-1 overflow-y-auto px-4 py-4" id="messages-scroll">
            <div id="messages-list-inner">
              <?php if (count($activeMessages) === 0): ?>
                <p class="text-muted-2 text-sm text-center mt-8"><?= h($activePartner['name']) ?> ile sohbetin başlangıcı. İlk mesajı sen yaz!</p>
              <?php else: ?>
                <?php render_message_thread($activeMessages, $user['id'], $activePartner); ?>
              <?php endif; ?>
            </div>
          </div>

          <form id="message-form" class="chat-composer px-4 py-3 border-t border-border flex-shrink-0">
            <input type="text" name="content" class="chat-composer-input" placeholder="Bir mesaj yaz..." maxlength="2000" autocomplete="off" required />
            <button type="submit" class="chat-composer-send" title="Gönder" aria-label="Gönder">
              <span class="material-symbols-outlined text-lg">send</span>
            </button>
          </form>
        <?php else: ?>
          <div class="flex-1 flex items-center justify-center flex-col gap-3 text-center p-8">
            <span class="material-symbols-outlined text-5xl text-muted-2">chat_bubble</span>
            <p class="text-muted text-sm">Sohbet etmek için soldan bir kişi seç.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
