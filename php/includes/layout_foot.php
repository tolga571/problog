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
      <?php if (!empty($unreadMsgCount)): ?>
        <span class="absolute bg-accent rounded-full" style="width:8px;height:8px;top:-2px;right:6px"></span>
      <?php endif; ?>
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
<?php endif; ?>
<script src="/assets/app.js?v=<?= filemtime(__DIR__ . '/../assets/app.js') ?>"></script>
</body>
</html>
