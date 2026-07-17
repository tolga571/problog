<?php /** @var array|null $user */ ?>
<?php if ($user): ?>
  <nav class="md:hidden fixed bottom-0 left-0 w-full bg-surface-2/90 backdrop-blur-md border-t border-border z-50 flex justify-around items-center h-16 px-4">
    <a href="/index.php" class="flex flex-col items-center <?= ($activePage ?? '') === 'home' ? 'text-accent' : 'text-muted' ?>">
      <span class="material-symbols-outlined">home</span>
    </a>
    <a href="/search.php" class="flex flex-col items-center <?= ($activePage ?? '') === 'search' ? 'text-accent' : 'text-muted' ?>">
      <span class="material-symbols-outlined">search</span>
    </a>
    <a href="/messages.php" class="relative flex flex-col items-center <?= ($activePage ?? '') === 'messages' ? 'text-accent' : 'text-muted' ?>">
      <span class="material-symbols-outlined">chat_bubble</span>
      <span class="absolute bg-accent rounded-full <?= !empty($unreadMsgCount) ? '' : 'hidden' ?>" style="width:8px;height:8px;top:-2px;right:6px" data-nav-messages-badge-dot></span>
    </a>
    <a href="/notifications.php" class="relative flex flex-col items-center <?= ($activePage ?? '') === 'notifications' ? 'text-accent' : 'text-muted' ?>">
      <span class="material-symbols-outlined">notifications</span>
      <?php if (!empty($unreadCount)): ?>
        <span class="absolute bg-accent rounded-full" style="width:8px;height:8px;top:-2px;right:6px"></span>
      <?php endif; ?>
    </a>
    <a href="/profile.php?id=<?= h($user['id']) ?>" class="flex flex-col items-center <?= ($activePage ?? '') === 'profile' ? 'text-accent' : 'text-muted' ?>">
      <span class="material-symbols-outlined">person</span>
    </a>
  </nav>
</div>

  <div id="confirm-modal" class="confirm-modal-overlay hidden">
    <div class="confirm-modal-card" role="alertdialog" aria-modal="true" aria-labelledby="confirm-modal-title">
      <div class="confirm-modal-icon" id="confirm-modal-icon">
        <span class="material-symbols-outlined">warning</span>
      </div>
      <h3 class="confirm-modal-title" id="confirm-modal-title">Emin misin?</h3>
      <p class="confirm-modal-message" id="confirm-modal-message"></p>
      <div class="confirm-modal-actions">
        <button type="button" class="btn-outline flex-1" id="confirm-modal-cancel">Vazgeç</button>
        <button type="button" class="confirm-modal-btn-danger flex-1" id="confirm-modal-confirm">Sil</button>
      </div>
    </div>
  </div>

  <div id="share-chat-modal" class="confirm-modal-overlay hidden">
    <div class="share-chat-card" role="dialog" aria-modal="true" aria-labelledby="share-chat-title">
      <div class="flex items-center justify-between mb-4">
        <h3 class="confirm-modal-title" id="share-chat-title" style="margin-bottom:0">Sohbetle gönder</h3>
        <button type="button" class="p-1 hover:bg-surface-3 rounded-full transition-colors" id="share-chat-close" aria-label="Kapat">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <div id="share-chat-body"></div>
    </div>
  </div>

  <?php if (($activePage ?? '') !== 'messages'): ?>
    <div id="chat-widget">
      <button type="button" id="chat-widget-fab" aria-label="Mesajlar">
        <span class="material-symbols-outlined">chat_bubble</span>
        <span id="chat-widget-fab-badge" class="chat-widget-badge <?= empty($unreadMsgCount) ? 'hidden' : '' ?>"><?= (int) ($unreadMsgCount ?? 0) ?></span>
      </button>
      <div id="chat-widget-panel" class="hidden">
        <div id="chat-widget-header">
          <button type="button" id="chat-widget-back" class="chat-widget-icon-btn hidden" aria-label="Geri">
            <span class="material-symbols-outlined">arrow_back</span>
          </button>
          <div class="flex-1 min-w-0">
            <span id="chat-widget-title">Mesajlar</span>
          </div>
          <button type="button" id="chat-widget-new" class="chat-widget-icon-btn" title="Yeni sohbet" aria-label="Yeni sohbet">
            <span class="material-symbols-outlined">edit_square</span>
          </button>
          <button type="button" id="chat-widget-close" class="chat-widget-icon-btn" aria-label="Kapat">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div id="chat-widget-body"></div>
        <div id="chat-widget-footer" class="hidden"></div>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>
<script src="/assets/app.js?v=<?= filemtime(__DIR__ . '/../assets/app.js') ?>"></script>
</body>
</html>
