<?php

declare(strict_types=1);

function render_message_bubble(array $message, string $currentUserId): void
{
    $isMine = $message['sender_id'] === $currentUserId;
    $isDeleted = !empty($message['deleted_at']);
    $rawContent = $isDeleted ? '' : (string) $message['content'];
    ?>
    <div class="msg-bubble-row <?= $isMine ? 'mine' : 'theirs' ?>" data-message-id="<?= h($message['id']) ?>">
      <div class="flex flex-col" style="max-width:78%;align-items:<?= $isMine ? 'flex-end' : 'flex-start' ?>">
        <div class="flex items-center gap-1.5" style="<?= $isMine ? 'flex-direction:row-reverse' : '' ?>">
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
        <p class="text-muted-2 text-xs mt-0.5" style="padding:0 4px">
          <?= h(time_ago($message['created_at'])) ?><?php if (!empty($message['edited_at']) && !$isDeleted): ?> &middot; düzenlendi<?php endif; ?>
        </p>
      </div>
    </div>
    <?php
}
