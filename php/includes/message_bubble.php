<?php

declare(strict_types=1);

function render_message_bubble(array $message, string $currentUserId, ?array $partner = null): void
{
    $isMine = $message['sender_id'] === $currentUserId;
    $isDeleted = !empty($message['deleted_at']);
    $rawContent = $isDeleted ? '' : (string) $message['content'];
    ?>
    <div class="msg-row <?= $isMine ? 'mine' : 'theirs' ?>" data-message-id="<?= h($message['id']) ?>">
      <?php if (!$isMine): ?>
        <?= $partner ? render_avatar($partner, 'avatar avatar-sm msg-avatar') : '<span class="avatar avatar-sm msg-avatar"></span>' ?>
      <?php endif; ?>
      <div class="msg-col">
        <div class="msg-meta">
          <?php if (!$isMine && $partner): ?>
            <span class="msg-meta-name"><?= h($partner['name']) ?></span>
          <?php endif; ?>
          <span class="msg-meta-time"><?= h(time_ago($message['created_at'])) ?></span>
        </div>
        <div class="flex items-center gap-1.5 <?= $isMine ? 'flex-row-reverse' : '' ?>">
          <div class="msg-bubble <?= $isDeleted ? 'deleted' : '' ?>" data-raw-content="<?= h($rawContent) ?>">
            <span class="msg-bubble-text"><?= $isDeleted ? 'Bu mesaj silindi' : nl2br(h($rawContent)) ?></span>
          </div>
          <?php if ($isMine && !$isDeleted): ?>
            <div class="action-menu">
              <button type="button" class="action-menu-btn" title="Seçenekler" aria-label="Seçenekler">
                <span class="material-symbols-outlined text-base">more_horiz</span>
              </button>
              <div class="action-menu-dropdown hidden">
                <button type="button" class="msg-edit-btn">Düzenle</button>
                <button type="button" class="msg-delete-btn">Sil</button>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <?php if ($isMine && !$isDeleted): ?>
          <p class="msg-status">
            <span class="material-symbols-outlined msg-status-icon <?= $message['read_at'] ? 'is-read' : '' ?>"><?= $message['read_at'] ? 'done_all' : 'done' ?></span>
            <?= $message['read_at'] ? 'Görüldü' : 'Gönderildi' ?><?php if (!empty($message['edited_at'])): ?> &middot; düzenlendi<?php endif; ?>
          </p>
        <?php elseif (!empty($message['edited_at']) && !$isDeleted): ?>
          <p class="msg-status">düzenlendi</p>
        <?php endif; ?>
      </div>
    </div>
    <?php
}

function render_message_thread(array $messages, string $currentUserId, ?array $partner = null, ?string $seedDate = null): void
{
    $prevDate = $seedDate;
    foreach ($messages as $message) {
        if (is_new_day($prevDate, $message['created_at'])) {
            echo '<div class="chat-date-separator">' . h(format_birthdate($message['created_at'])) . '</div>';
        }
        $prevDate = $message['created_at'];
        render_message_bubble($message, $currentUserId, $partner);
    }
}
