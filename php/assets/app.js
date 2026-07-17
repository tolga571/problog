(function () {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const currentUserId = document.querySelector('meta[name="current-user-id"]')?.content || '';

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
  }

  // Flowbite tarzi onay modali - sil/cikis yap gibi ana islemlerde native
  // confirm() ve eski "iki kez tikla" deseni yerine tum sitede tutarli tek
  // bir dialog. Promise<boolean> dondurur, kullanici "Sil"e basarsa true.
  function showConfirmModal({ title = 'Emin misin?', message = '', confirmText = 'Sil', cancelText = 'Vazgeç', danger = true } = {}) {
    const modal = document.getElementById('confirm-modal');
    if (!modal) return Promise.resolve(true);

    return new Promise((resolve) => {
      const titleEl = document.getElementById('confirm-modal-title');
      const messageEl = document.getElementById('confirm-modal-message');
      const confirmBtn = document.getElementById('confirm-modal-confirm');
      const cancelBtn = document.getElementById('confirm-modal-cancel');
      const iconEl = document.getElementById('confirm-modal-icon');

      titleEl.textContent = title;
      messageEl.textContent = message;
      confirmBtn.textContent = confirmText;
      cancelBtn.textContent = cancelText;
      iconEl.classList.toggle('confirm-modal-icon-neutral', !danger);
      iconEl.querySelector('.material-symbols-outlined').textContent = danger ? 'warning' : 'logout';
      confirmBtn.classList.toggle('confirm-modal-btn-danger', danger);
      confirmBtn.classList.toggle('btn-primary', !danger);

      modal.classList.remove('hidden');
      confirmBtn.focus();

      function cleanup(result) {
        modal.classList.add('hidden');
        confirmBtn.removeEventListener('click', onConfirm);
        cancelBtn.removeEventListener('click', onCancel);
        modal.removeEventListener('click', onBackdrop);
        document.removeEventListener('keydown', onKeydown);
        resolve(result);
      }
      function onConfirm() { cleanup(true); }
      function onCancel() { cleanup(false); }
      function onBackdrop(event) { if (event.target === modal) cleanup(false); }
      function onKeydown(event) { if (event.key === 'Escape') cleanup(false); }

      confirmBtn.addEventListener('click', onConfirm);
      cancelBtn.addEventListener('click', onCancel);
      modal.addEventListener('click', onBackdrop);
      document.addEventListener('keydown', onKeydown);
    });
  }
  window.showConfirmModal = showConfirmModal;

  const logoutForm = document.getElementById('logout-form');
  if (logoutForm) {
    logoutForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const ok = await showConfirmModal({
        title: 'Çıkış yap',
        message: 'Hesabından çıkış yapmak istediğine emin misin?',
        confirmText: 'Çıkış Yap',
        danger: false,
      });
      if (ok) logoutForm.submit();
    });
  }

  function initials(name) {
    return (name || '')
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0])
      .join('')
      .toUpperCase();
  }

  // Makale govdesindeki (render_markdown ciktisi) kod bloklarini bir
  // .code-block sarmalayicisina alip hover'da beliren bir kopyala butonu
  // ekler. Uzun kod bloklari CSS'te max-height + overflow ile kendi icinde
  // kaydirilir, boylece post karti asiri uzamaz.
  function enhanceCodeBlocks(root) {
    root.querySelectorAll('.prose-content pre').forEach((pre) => {
      if (pre.closest('.code-block')) return;

      const wrapper = document.createElement('div');
      wrapper.className = 'code-block';
      pre.replaceWith(wrapper);
      wrapper.appendChild(pre);

      const copyBtn = document.createElement('button');
      copyBtn.type = 'button';
      copyBtn.className = 'code-copy-btn';
      copyBtn.title = 'Kopyala';
      copyBtn.setAttribute('aria-label', 'Kopyala');
      copyBtn.innerHTML = '<span class="material-symbols-outlined">content_copy</span><span class="code-copy-label">Kopyala</span>';
      wrapper.appendChild(copyBtn);

      copyBtn.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(pre.textContent || '');
          copyBtn.classList.add('copied');
          copyBtn.querySelector('.code-copy-label').textContent = 'Kopyalandı!';
          copyBtn.querySelector('.material-symbols-outlined').textContent = 'check';
          setTimeout(() => {
            copyBtn.classList.remove('copied');
            copyBtn.querySelector('.code-copy-label').textContent = 'Kopyala';
            copyBtn.querySelector('.material-symbols-outlined').textContent = 'content_copy';
          }, 1500);
        } catch (err) {
          console.error('Kopyalama hatası:', err);
        }
      });
    });
  }

  enhanceCodeBlocks(document);

  async function postJson(url, body) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': csrfToken,
        'X-Requested-With': 'fetch',
      },
      body: new URLSearchParams(body),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.message || 'Bir hata oluştu.');
    }
    return data;
  }

  async function getJson(url) {
    const response = await fetch(url, {
      headers: { 'X-Requested-With': 'fetch' },
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.message || 'Bir hata oluştu.');
    }
    return data;
  }

  // Paylas menusundeki "Diger uygulamalarla paylas" secenegi, sadece
  // tarayici gercekten native share sheet destekliyorsa gosterilir.
  if (navigator.share) {
    document.querySelectorAll('.share-native-btn').forEach((btn) => { btn.hidden = false; });
  }

  function postShareUrl(card) {
    return `${window.location.origin}/profile.php?id=${encodeURIComponent(card.dataset.authorId)}#post-${encodeURIComponent(card.dataset.postId)}`;
  }

  function openShareChatModal(card) {
    const modal = document.getElementById('share-chat-modal');
    const body = document.getElementById('share-chat-body');
    if (!modal || !body) return;

    const closeModal = () => {
      modal.classList.add('hidden');
      document.removeEventListener('keydown', onKeydown);
    };
    function onKeydown(event) { if (event.key === 'Escape') closeModal(); }

    modal.classList.remove('hidden');
    body.innerHTML = '<p class="share-chat-empty">Arkadaşların yükleniyor...</p>';

    modal.querySelector('#share-chat-close').onclick = closeModal;
    modal.onclick = (event) => { if (event.target === modal) closeModal(); };
    document.addEventListener('keydown', onKeydown);

    getJson('/actions/friends_list.php')
      .then((data) => {
        if (!data.friends.length) {
          body.innerHTML = `
            <p class="share-chat-empty">Henüz arkadaşın yok. Mesajla paylaşmak için önce birini arkadaş eklemelisin.</p>
          `;
          return;
        }

        const list = document.createElement('div');
        list.className = 'share-chat-friend-list';
        data.friends.forEach((friend) => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'share-chat-friend-btn';
          btn.innerHTML = `
            ${friend.avatar_url
              ? `<img src="${escapeHtml(friend.avatar_url)}" class="avatar avatar-sm" style="object-fit:cover" alt="" />`
              : `<div class="avatar avatar-sm">${escapeHtml(initials(friend.name))}</div>`}
            <span class="share-chat-friend-name">${escapeHtml(friend.name)}</span>
            <span class="share-chat-status"></span>
          `;
          btn.addEventListener('click', async () => {
            btn.disabled = true;
            const status = btn.querySelector('.share-chat-status');
            status.textContent = 'Gönderiliyor...';
            const title = card.dataset.postTitle;
            const content = `${title ? title + '\n' : ''}${postShareUrl(card)}`;
            try {
              await postJson('/actions/message_send.php', { partner_id: friend.id, content });
              status.textContent = 'Gönderildi ✓';
              setTimeout(() => { window.location.href = `/messages.php?with=${encodeURIComponent(friend.id)}`; }, 500);
            } catch (err) {
              status.textContent = '';
              btn.disabled = false;
              console.error('Sohbetle gönderme hatası:', err);
            }
          });
          list.appendChild(btn);
        });
        body.innerHTML = '';
        body.appendChild(list);
      })
      .catch((err) => {
        body.innerHTML = '<p class="share-chat-empty">Arkadaş listesi yüklenemedi.</p>';
        console.error('Arkadaş listesi hatası:', err);
      });
  }

  document.addEventListener('click', async (event) => {
    const likeBtn = event.target.closest('.like-btn');
    if (likeBtn) {
      if (likeBtn.disabled) return;
      const card = likeBtn.closest('.post-card');
      const postId = card.dataset.postId;
      try {
        const data = await postJson('/actions/like.php', { post_id: postId });
        likeBtn.dataset.liked = data.liked ? '1' : '0';
        likeBtn.classList.toggle('text-accent', data.liked);
        likeBtn.querySelector('.like-icon').textContent = data.liked ? 'favorite' : 'favorite_border';
        likeBtn.querySelector('.like-count').textContent = data.likes_count;
      } catch (err) {
        console.error('Beğeni hatası:', err);
      }
      return;
    }

    const commentLikeBtn = event.target.closest('.comment-like-btn');
    if (commentLikeBtn) {
      if (commentLikeBtn.disabled) return;
      const node = commentLikeBtn.closest('.comment-node');
      try {
        const data = await postJson('/actions/comment_like.php', { comment_id: node.dataset.commentId });
        commentLikeBtn.dataset.liked = data.liked ? '1' : '0';
        commentLikeBtn.classList.toggle('text-accent', data.liked);
        commentLikeBtn.querySelector('.like-icon').textContent = data.liked ? 'favorite' : 'favorite_border';
        commentLikeBtn.querySelector('.like-count').textContent = data.likes_count;
      } catch (err) {
        console.error('Yorum beğeni hatası:', err);
      }
      return;
    }

    const bookmarkBtn = event.target.closest('.bookmark-btn');
    if (bookmarkBtn) {
      if (bookmarkBtn.disabled) return;
      const card = bookmarkBtn.closest('.post-card');
      const postId = card.dataset.postId;
      try {
        const data = await postJson('/actions/bookmark.php', { post_id: postId });
        bookmarkBtn.dataset.bookmarked = data.bookmarked ? '1' : '0';
        bookmarkBtn.classList.toggle('text-accent', data.bookmarked);
        bookmarkBtn.querySelector('.bookmark-icon').textContent = data.bookmarked ? 'bookmark' : 'bookmark_border';
      } catch (err) {
        console.error('Kaydetme hatası:', err);
      }
      return;
    }

    const shareCopyBtn = event.target.closest('.share-copy-btn');
    if (shareCopyBtn) {
      const card = shareCopyBtn.closest('.post-card');
      const url = postShareUrl(card);
      shareCopyBtn.closest('.action-menu-dropdown')?.classList.add('hidden');
      try {
        await navigator.clipboard.writeText(url);
        const label = shareCopyBtn.lastChild;
        const original = label.textContent;
        label.textContent = 'Kopyalandı!';
        setTimeout(() => { label.textContent = original; }, 1500);
      } catch (err) {
        console.error('Kopyalama hatası:', err);
      }
      return;
    }

    const shareNativeBtn = event.target.closest('.share-native-btn');
    if (shareNativeBtn) {
      const card = shareNativeBtn.closest('.post-card');
      shareNativeBtn.closest('.action-menu-dropdown')?.classList.add('hidden');
      if (navigator.share) {
        navigator.share({ title: card.dataset.postTitle || 'ProBlog', url: postShareUrl(card) }).catch(() => {});
      }
      return;
    }

    const shareChatBtn = event.target.closest('.share-chat-btn');
    if (shareChatBtn) {
      const card = shareChatBtn.closest('.post-card');
      shareChatBtn.closest('.action-menu-dropdown')?.classList.add('hidden');
      openShareChatModal(card);
      return;
    }

    const commentToggleBtn = event.target.closest('.comment-toggle-btn');
    if (commentToggleBtn) {
      const card = commentToggleBtn.closest('.post-card');
      const section = card.querySelector('.comments-section');
      const isHidden = section.classList.contains('hidden');

      if (!isHidden) {
        section.classList.add('hidden');
        return;
      }

      section.classList.remove('hidden');
      if (section.dataset.loaded === '1') return;

      const list = section.querySelector('.comments-list');
      list.innerHTML = '<p class="text-muted-2 text-sm">Yorumlar yükleniyor...</p>';

      try {
        const data = await getJson(`/actions/comment_list.php?post_id=${encodeURIComponent(card.dataset.postId)}`);
        section.dataset.loaded = '1';
        renderComments(list, data.comments);
      } catch (err) {
        list.innerHTML = '<p class="text-muted-2 text-sm">Yorumlar yüklenemedi.</p>';
        console.error('Yorum yükleme hatası:', err);
      }
      return;
    }

    const postExpandBtn = event.target.closest('.post-expand-btn');
    if (postExpandBtn) {
      const wrapper = postExpandBtn.previousElementSibling;
      if (wrapper && wrapper.classList.contains('post-content-wrapper')) {
        const expanded = wrapper.classList.toggle('expanded');
        wrapper.classList.toggle('clamped', !expanded);
        postExpandBtn.textContent = expanded ? 'Daha az göster' : 'Devamını oku';
      }
      return;
    }

    const loadMoreBtn = event.target.closest('#load-more-btn');
    if (loadMoreBtn) {
      const originalText = loadMoreBtn.textContent;
      loadMoreBtn.disabled = true;
      loadMoreBtn.textContent = 'Yükleniyor...';
      try {
        const data = await getJson(
          `/actions/load_more_posts.php?feed=${encodeURIComponent(loadMoreBtn.dataset.feed)}&offset=${loadMoreBtn.dataset.offset}`
        );
        const postsList = document.getElementById('posts-list');
        postsList.insertAdjacentHTML('beforeend', data.html);
        enhanceCodeBlocks(postsList);
        loadMoreBtn.dataset.offset = String(Number(loadMoreBtn.dataset.offset) + 20);
        if (!data.has_more) {
          loadMoreBtn.remove();
        } else {
          loadMoreBtn.disabled = false;
          loadMoreBtn.textContent = originalText;
        }
      } catch (err) {
        loadMoreBtn.disabled = false;
        loadMoreBtn.textContent = originalText;
        console.error('Daha fazla yükleme hatası:', err);
      }
      return;
    }

    const postDeleteBtn = event.target.closest('.post-delete-btn');
    if (postDeleteBtn) {
      event.preventDefault();
      const proceed = await showConfirmModal({
        title: 'Gönderiyi sil',
        message: 'Bu gönderiyi silmek istediğine emin misin? Bu işlem geri alınamaz.',
      });
      if (proceed) postDeleteBtn.closest('form').submit();
      return;
    }

    const commentDeleteBtn = event.target.closest('.comment-delete-btn');
    if (commentDeleteBtn) {
      const proceed = await showConfirmModal({
        title: 'Yorumu sil',
        message: 'Bu yorumu silmek istediğine emin misin?',
      });
      if (!proceed) return;
      const node = commentDeleteBtn.closest('.comment-node');
      const card = commentDeleteBtn.closest('.post-card');
      try {
        const data = await postJson('/actions/delete_comment.php', { comment_id: node.dataset.commentId });
        node.remove();
        card.querySelector('.comment-count').textContent = data.comments_count;
        const list = card.querySelector('.comments-list');
        if (!list.children.length) {
          list.innerHTML = '<p class="text-muted-2 text-sm">Henüz yorum yok.</p>';
        }
      } catch (err) {
        console.error('Yorum silme hatası:', err);
      }
      return;
    }

    const actionMenuBtn = event.target.closest('.action-menu-btn');
    if (actionMenuBtn) {
      const dropdown = actionMenuBtn.nextElementSibling;
      const wasHidden = dropdown.classList.contains('hidden');
      document.querySelectorAll('.action-menu-dropdown').forEach((d) => d.classList.add('hidden'));
      if (wasHidden) dropdown.classList.remove('hidden');
      return;
    }

    if (!event.target.closest('.action-menu')) {
      document.querySelectorAll('.action-menu-dropdown').forEach((d) => d.classList.add('hidden'));
    }

    const commentEditBtn = event.target.closest('.comment-edit-btn');
    if (commentEditBtn) {
      const node = commentEditBtn.closest('.comment-node');
      commentEditBtn.closest('.action-menu-dropdown')?.classList.add('hidden');
      const textEl = node.querySelector(':scope > .comment-row .comment-content-text');
      if (textEl.querySelector('textarea')) return;

      const raw = node.dataset.rawContent;
      const wrapper = document.createElement('div');
      wrapper.className = 'flex gap-2 items-start mt-1';
      const textarea = document.createElement('textarea');
      textarea.className = 'input-field text-sm flex-1';
      textarea.maxLength = 500;
      textarea.rows = 2;
      textarea.value = raw;
      const saveBtn = document.createElement('button');
      saveBtn.type = 'button';
      saveBtn.className = 'btn-primary text-sm px-3';
      saveBtn.textContent = 'Kaydet';
      wrapper.append(textarea, saveBtn);

      const originalHtml = textEl.innerHTML;
      textEl.innerHTML = '';
      textEl.appendChild(wrapper);
      attachMentionAutocomplete(textarea);
      textarea.focus();

      const cancel = () => { textEl.innerHTML = originalHtml; };

      saveBtn.addEventListener('click', async () => {
        const newValue = textarea.value.trim();
        if (!newValue) { cancel(); return; }
        try {
          const data = await postJson('/actions/comment_edit.php', { comment_id: node.dataset.commentId, content: newValue });
          node.dataset.rawContent = data.content;
          textEl.innerHTML = data.content_html;
          const indicator = node.querySelector(':scope > .comment-row .comment-edited-indicator');
          if (indicator) indicator.innerHTML = '&middot; düzenlendi';
        } catch (err) {
          console.error('Yorum düzenleme hatası:', err);
          cancel();
        }
      });
      textarea.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { e.preventDefault(); cancel(); }
      });
      return;
    }

    const commentReplyBtn = event.target.closest('.comment-reply-btn');
    if (commentReplyBtn) {
      const node = commentReplyBtn.closest('.comment-node');
      const slot = node.querySelector(':scope > .comment-row .comment-reply-form-slot');
      if (slot.children.length) {
        slot.innerHTML = '';
        return;
      }
      slot.appendChild(buildReplyForm(node.dataset.commentId));
      slot.querySelector('textarea[name="content"]').focus();
      return;
    }

    const commentCollapseToggle = event.target.closest('.comment-collapse-toggle');
    if (commentCollapseToggle) {
      const node = commentCollapseToggle.closest('.comment-node');
      const children = node.querySelector(':scope > .comment-children');
      const collapsed = children.classList.toggle('collapsed');
      commentCollapseToggle.textContent = collapsed
        ? commentCollapseToggle.dataset.collapsedLabel
        : commentCollapseToggle.dataset.expandedLabel;
      return;
    }

    const followBtn = event.target.closest('.follow-btn');
    if (followBtn) {
      try {
        const data = await postJson('/actions/follow.php', { target_id: followBtn.dataset.targetId });
        followBtn.dataset.following = data.following ? '1' : '0';
        followBtn.textContent = data.following ? 'Takip Ediliyor' : 'Takip Et';
        followBtn.classList.toggle('is-following', data.following);
        const followersCountEl = document.getElementById('followers-count');
        if (followersCountEl) followersCountEl.textContent = data.followers_count;
      } catch (err) {
        console.error('Takip hatası:', err);
      }
      return;
    }

    const copyProfileLinkBtn = event.target.closest('.copy-profile-link-btn');
    if (copyProfileLinkBtn) {
      const url = `${window.location.origin}/profile.php?id=${encodeURIComponent(copyProfileLinkBtn.dataset.profileId)}`;
      const originalText = copyProfileLinkBtn.textContent;
      try {
        await navigator.clipboard.writeText(url);
        copyProfileLinkBtn.textContent = 'Kopyalandı!';
        setTimeout(() => { copyProfileLinkBtn.textContent = originalText; }, 1500);
      } catch (err) {
        console.error('Kopyalama hatası:', err);
      }
      return;
    }

    const muteBtn = event.target.closest('.mute-btn');
    if (muteBtn) {
      try {
        const data = await postJson('/actions/mute.php', { target_id: muteBtn.dataset.targetId });
        muteBtn.dataset.muted = data.muted ? '1' : '0';
        muteBtn.textContent = data.muted ? 'Sesi Aç' : 'Sessize Al';
      } catch (err) {
        console.error('Sessize alma hatası:', err);
      }
      return;
    }

    const blockBtn = event.target.closest('.block-btn');
    if (blockBtn) {
      const isBlocked = blockBtn.dataset.blocked === '1';
      if (!isBlocked) {
        const proceed = await showConfirmModal({
          title: 'Kullanıcıyı engelle',
          message: 'Bu kullanıcıyı engellemek istediğine emin misin? Birbirinizi takip edemez, mesajlaşamaz ve arkadaş olamazsınız.',
          confirmText: 'Engelle',
        });
        if (!proceed) return;
      }
      try {
        await postJson('/actions/block.php', { target_id: blockBtn.dataset.targetId });
        window.location.reload();
      } catch (err) {
        console.error('Engelleme hatası:', err);
      }
      return;
    }

    const reportBtn = event.target.closest('.report-btn');
    if (reportBtn) {
      const proceed = await showConfirmModal({
        title: 'Kullanıcıyı şikayet et',
        message: 'Bu kullanıcıyı uygunsuz davranış nedeniyle şikayet etmek istediğine emin misin?',
        confirmText: 'Şikayet Et',
      });
      if (!proceed) return;
      try {
        await postJson('/actions/report.php', { target_id: reportBtn.dataset.targetId, reason: 'Uygunsuz davranış' });
        reportBtn.textContent = 'Şikayet edildi';
        reportBtn.disabled = true;
      } catch (err) {
        console.error('Şikayet hatası:', err);
      }
      return;
    }

    const friendBtn = event.target.closest('.friend-btn');
    if (friendBtn) {
      const status = friendBtn.dataset.status;
      if (status === 'friends') {
        const proceed = await showConfirmModal({
          title: 'Arkadaşlıktan çıkar',
          message: 'Bu kişiyi arkadaş listenden çıkarmak istediğine emin misin?',
        });
        if (!proceed) return;
      }
      const action = status === 'friends' ? 'remove' : status === 'pending_sent' ? 'cancel' : 'request';
      try {
        await postJson('/actions/friend_action.php', { target_id: friendBtn.dataset.targetId, action });
        window.location.reload();
      } catch (err) {
        console.error('Arkadaşlık isteği hatası:', err);
      }
      return;
    }

    const friendAcceptBtn = event.target.closest('.friend-accept-btn');
    if (friendAcceptBtn) {
      try {
        await postJson('/actions/friend_action.php', { target_id: friendAcceptBtn.dataset.targetId, action: 'accept' });
        window.location.reload();
      } catch (err) {
        console.error('Arkadaşlık kabul hatası:', err);
      }
      return;
    }

    const friendDeclineBtn = event.target.closest('.friend-decline-btn');
    if (friendDeclineBtn) {
      try {
        await postJson('/actions/friend_action.php', { target_id: friendDeclineBtn.dataset.targetId, action: 'decline' });
        window.location.reload();
      } catch (err) {
        console.error('Arkadaşlık reddetme hatası:', err);
      }
      return;
    }

    const msgEditBtn = event.target.closest('.msg-edit-btn');
    if (msgEditBtn) {
      msgEditBtn.closest('.action-menu-dropdown')?.classList.add('hidden');
      const row = msgEditBtn.closest('[data-message-id]');
      const bubble = row.querySelector('.msg-bubble');
      if (bubble.querySelector('input')) return;

      const raw = bubble.dataset.rawContent;
      const textSpan = bubble.querySelector('.msg-bubble-text');
      const input = document.createElement('input');
      input.type = 'text';
      input.value = raw;
      input.maxLength = 2000;
      input.style.cssText = 'background:transparent;border:none;color:inherit;font:inherit;width:100%;outline:none';
      textSpan.replaceWith(input);
      input.focus();
      input.setSelectionRange(input.value.length, input.value.length);

      const finish = async (commit) => {
        const newValue = input.value.trim();
        if (commit && newValue && newValue !== raw) {
          try {
            const data = await postJson('/actions/message_edit.php', { message_id: row.dataset.messageId, content: newValue });
            bubble.dataset.rawContent = data.content;
            const span = document.createElement('span');
            span.className = 'msg-bubble-text';
            span.innerHTML = escapeHtml(data.content).replace(/\n/g, '<br>');
            input.replaceWith(span);
            return;
          } catch (err) {
            console.error('Mesaj düzenleme hatası:', err);
          }
        }
        const span = document.createElement('span');
        span.className = 'msg-bubble-text';
        span.innerHTML = escapeHtml(raw).replace(/\n/g, '<br>');
        input.replaceWith(span);
      };

      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); finish(true); }
        if (e.key === 'Escape') { e.preventDefault(); finish(false); }
      });
      input.addEventListener('blur', () => finish(true));
      return;
    }

    const msgDeleteBtn = event.target.closest('.msg-delete-btn');
    if (msgDeleteBtn) {
      const proceed = await showConfirmModal({
        title: 'Mesajı sil',
        message: 'Bu mesajı silmek istediğine emin misin?',
      });
      if (!proceed) return;
      const row = msgDeleteBtn.closest('[data-message-id]');
      try {
        await postJson('/actions/message_delete.php', { message_id: row.dataset.messageId });
        const bubble = row.querySelector('.msg-bubble');
        bubble.classList.add('deleted');
        bubble.querySelector('.msg-bubble-text').textContent = 'Bu mesaj silindi';
        msgDeleteBtn.closest('.action-menu').remove();
      } catch (err) {
        console.error('Mesaj silme hatası:', err);
      }
    }
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('.comment-form');
    if (!form) return;
    event.preventDefault();

    const card = form.closest('.post-card');
    const input = form.querySelector('textarea[name="content"]');
    const content = input.value.trim();
    if (!content) return;
    const parentId = form.querySelector('input[name="parent_id"]')?.value || '';

    try {
      const data = await postJson('/actions/comment_add.php', {
        post_id: card.dataset.postId,
        parent_id: parentId,
        content,
      });
      const list = card.querySelector('.comments-list');
      if (parentId) {
        const parentNode = list.querySelector(`.comment-node[data-comment-id="${parentId}"]`);
        const childrenContainer = parentNode.querySelector(':scope > .comment-children');
        childrenContainer.classList.remove('collapsed');
        appendCommentNode(childrenContainer, data.comment, depthOf(parentNode) + 1);
        form.remove();
      } else {
        if (!list.children.length || list.textContent.includes('Henüz yorum yok')) {
          list.innerHTML = '';
        }
        appendCommentNode(list, data.comment, 0);
        input.value = '';
        input.style.height = 'auto';
      }
      card.querySelector('.comment-count').textContent = data.comments_count;
    } catch (err) {
      console.error('Yorum gönderme hatası:', err);
    }
  });

  const MAX_VISUAL_DEPTH = 6;
  const COLLAPSE_DEPTH = 3;

  function depthOf(node) {
    return node ? Number(node.dataset.depth || '0') : -1;
  }

  function buildCommentTree(comments) {
    const byId = new Map();
    comments.forEach((c) => byId.set(c.id, { ...c, children: [] }));
    const roots = [];
    byId.forEach((c) => {
      if (c.parent_id && byId.has(c.parent_id)) {
        byId.get(c.parent_id).children.push(c);
      } else {
        roots.push(c);
      }
    });
    return roots;
  }

  function buildReplyForm(parentId) {
    const form = document.createElement('form');
    form.className = 'comment-form flex gap-3 items-end mt-2';
    form.innerHTML = `
      <input type="hidden" name="parent_id" value="${escapeHtml(parentId)}" />
      <textarea class="input-field text-sm flex-1 resize-none comment-textarea" placeholder="Yanıtını yaz..." name="content" maxlength="500" rows="1" required autocomplete="off"></textarea>
      <button type="submit" class="btn-primary text-sm px-4">Gönder</button>
    `;
    const textarea = form.querySelector('textarea[name="content"]');
    attachMentionAutocomplete(textarea);
    enhanceCommentTextarea(textarea);
    return form;
  }

  function renderComments(list, comments) {
    if (!comments.length) {
      list.innerHTML = '<p class="text-muted-2 text-sm">Henuz yorum yok.</p>';
      return;
    }
    list.innerHTML = '';
    const tree = buildCommentTree(comments);
    tree.forEach((comment) => appendCommentNode(list, comment, 0));
  }

  function appendCommentNode(container, comment, depth) {
    const node = document.createElement('div');
    node.className = 'comment-node';
    node.dataset.commentId = comment.id;
    node.dataset.depth = String(depth);
    node.dataset.rawContent = comment.content;
    if (depth >= MAX_VISUAL_DEPTH) node.dataset.depthCapped = 'true';

    const canDelete = currentUserId && comment.user_id === currentUserId;
    const editedLabel = comment.edited_at ? ' &middot; düzenlendi' : '';

    node.innerHTML = `
      <div class="comment-row flex items-start gap-3">
        <a href="/profile.php?id=${escapeHtml(comment.user_id)}" class="flex-shrink-0">
          ${comment.avatar_url
            ? `<img src="${escapeHtml(comment.avatar_url)}" class="avatar avatar-sm" style="object-fit:cover" alt="" />`
            : `<div class="avatar avatar-sm">${escapeHtml(initials(comment.name))}</div>`}
        </a>
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-2">
            <p class="text-sm leading-tight">
              <span class="font-semibold text-white">${escapeHtml(comment.name)}</span>
              <span class="text-muted-2 text-xs" title="${escapeHtml(comment.full_date || '')}">&middot; ${escapeHtml(comment.time_ago || '')}</span>
              <span class="text-muted-2 text-xs comment-edited-indicator">${editedLabel}</span>
            </p>
            ${canDelete ? `
              <div class="action-menu flex-shrink-0">
                <button type="button" class="action-menu-btn" title="Seçenekler" aria-label="Seçenekler">
                  <span class="material-symbols-outlined text-base">more_horiz</span>
                </button>
                <div class="action-menu-dropdown hidden">
                  <button type="button" class="comment-edit-btn">Düzenle</button>
                  <button type="button" class="comment-delete-btn">Sil</button>
                </div>
              </div>
            ` : ''}
          </div>
          <p class="text-muted text-sm mt-0.5 leading-tight comment-content-text">${comment.content_html}</p>
          <div class="flex items-center gap-4" style="margin-top:6px">
            <button type="button" class="comment-like-btn ${comment.user_liked ? 'text-accent' : ''}" data-liked="${comment.user_liked ? '1' : '0'}" title="Beğen" aria-label="Beğen" ${currentUserId ? '' : 'disabled'}>
              <span class="material-symbols-outlined text-sm like-icon">${comment.user_liked ? 'favorite' : 'favorite_border'}</span>
              <span class="like-count">${comment.likes_count || 0}</span>
            </button>
            ${currentUserId ? '<button type="button" class="comment-reply-btn"><span class="material-symbols-outlined text-sm">reply</span>Yanıtla</button>' : ''}
          </div>
          <div class="comment-reply-form-slot"></div>
        </div>
      </div>
      <div class="comment-children"></div>
    `;

    container.appendChild(node);

    const childrenContainer = node.querySelector(':scope > .comment-children');
    if (comment.children && comment.children.length) {
      const startCollapsed = depth >= COLLAPSE_DEPTH;
      if (startCollapsed) childrenContainer.classList.add('collapsed');

      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'comment-collapse-toggle block';
      const count = comment.children.length;
      toggle.dataset.collapsedLabel = `${count} yanıtı gör`;
      toggle.dataset.expandedLabel = 'Yanıtları gizle';
      toggle.textContent = startCollapsed ? toggle.dataset.collapsedLabel : toggle.dataset.expandedLabel;
      node.querySelector(':scope > .comment-row > .flex-1').appendChild(toggle);

      comment.children.forEach((child) => appendCommentNode(childrenContainer, child, depth + 1));
    }

    return node;
  }

  function attachMentionAutocomplete(inputEl) {
    let dropdown = null;
    let activeIndex = -1;
    let debounceTimer = null;

    function closeDropdown() {
      if (dropdown) { dropdown.remove(); dropdown = null; }
      activeIndex = -1;
    }

    function currentTokenRange() {
      const caret = inputEl.selectionStart ?? inputEl.value.length;
      const uptoCaret = inputEl.value.slice(0, caret);
      const match = uptoCaret.match(/@([\p{L}0-9_ ]{0,40})$/u);
      if (!match) return null;
      return { start: caret - match[0].length, end: caret, query: match[1].trim() };
    }

    function selectUser(user, range) {
      const token = `@[${user.name}](${user.id})`;
      const before = inputEl.value.slice(0, range.start);
      const after = inputEl.value.slice(range.end);
      inputEl.value = `${before}${token} ${after}`;
      const newCaret = (before + token + ' ').length;
      inputEl.focus();
      inputEl.setSelectionRange(newCaret, newCaret);
      closeDropdown();
    }

    function renderDropdown(users, range) {
      closeDropdown();
      if (!users.length) return;
      dropdown = document.createElement('div');
      dropdown.className = 'mention-dropdown';
      users.forEach((user, i) => {
        const item = document.createElement('div');
        item.className = 'mention-dropdown-item' + (i === 0 ? ' active' : '');
        item.innerHTML = `<span class="avatar avatar-sm">${escapeHtml(initials(user.name))}</span><span>${escapeHtml(user.name)}</span>`;
        item.addEventListener('mousedown', (e) => { e.preventDefault(); selectUser(user, range); });
        dropdown.appendChild(item);
      });
      activeIndex = 0;
      const parent = inputEl.parentElement;
      parent.style.position = parent.style.position || 'relative';
      dropdown.style.top = `${inputEl.offsetTop + inputEl.offsetHeight + 4}px`;
      dropdown.style.left = `${inputEl.offsetLeft}px`;
      parent.appendChild(dropdown);
    }

    inputEl.addEventListener('input', () => {
      const range = currentTokenRange();
      clearTimeout(debounceTimer);
      if (!range || range.query.length < 1) { closeDropdown(); return; }
      debounceTimer = setTimeout(async () => {
        try {
          const data = await getJson(`/actions/friends_search.php?q=${encodeURIComponent(range.query)}`);
          renderDropdown(data.users, range);
        } catch (err) {
          closeDropdown();
        }
      }, 200);
    });

    inputEl.addEventListener('keydown', (e) => {
      if (!dropdown) return;
      const items = [...dropdown.querySelectorAll('.mention-dropdown-item')];
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex = Math.min(activeIndex + 1, items.length - 1);
        items.forEach((it, i) => it.classList.toggle('active', i === activeIndex));
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex = Math.max(activeIndex - 1, 0);
        items.forEach((it, i) => it.classList.toggle('active', i === activeIndex));
      } else if (e.key === 'Enter' && activeIndex >= 0) {
        e.preventDefault();
        items[activeIndex].dispatchEvent(new MouseEvent('mousedown'));
      } else if (e.key === 'Escape') {
        closeDropdown();
      }
    });

    inputEl.addEventListener('blur', () => setTimeout(closeDropdown, 150));
  }

  function enhanceCommentTextarea(textarea) {
    textarea.addEventListener('input', () => {
      textarea.style.height = 'auto';
      textarea.style.height = `${textarea.scrollHeight}px`;
    });
    textarea.addEventListener('keydown', (e) => {
      if (e.defaultPrevented) return;
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        textarea.closest('form')?.requestSubmit();
      }
    });
  }

  document.querySelectorAll('.comment-form textarea[name="content"]').forEach((textarea) => {
    attachMentionAutocomplete(textarea);
    enhanceCommentTextarea(textarea);
  });

  function buildMessageBubble(message, viewerId) {
    const isMine = message.sender_id === viewerId;
    const row = document.createElement('div');
    row.className = 'msg-bubble-row ' + (isMine ? 'mine' : 'theirs');
    row.dataset.messageId = message.id;
    const safeContent = escapeHtml(message.content).replace(/\n/g, '<br>');
    row.innerHTML = `
      <div class="flex flex-col" style="max-width:78%;align-items:${isMine ? 'flex-end' : 'flex-start'}">
        <div class="flex items-center gap-1.5" style="${isMine ? 'flex-direction:row-reverse' : ''}">
          <div class="msg-bubble" data-raw-content="${escapeHtml(message.content)}"><span class="msg-bubble-text">${safeContent}</span></div>
          ${isMine ? `<div class="action-menu">
            <button type="button" class="action-menu-btn" title="Seçenekler" aria-label="Seçenekler"><span class="material-symbols-outlined text-base">more_horiz</span></button>
            <div class="action-menu-dropdown hidden">
              <button type="button" class="msg-edit-btn">Düzenle</button>
              <button type="button" class="msg-delete-btn">Sil</button>
            </div>
          </div>` : ''}
        </div>
        <p class="text-muted-2 text-xs mt-0.5" style="padding:0 4px">Az önce</p>
      </div>
    `;
    return row;
  }

  const messagesView = document.querySelector('.messages-view[data-partner-id]');
  if (messagesView && messagesView.dataset.partnerId) {
    let conversationId = messagesView.dataset.conversationId || '';
    const scrollBox = document.getElementById('messages-scroll');
    const listInner = document.getElementById('messages-list-inner');

    function lastMessageId() {
      const rows = listInner.querySelectorAll('[data-message-id]');
      return rows.length ? rows[rows.length - 1].dataset.messageId : '';
    }

    function scrollToBottom() {
      if (scrollBox) scrollBox.scrollTop = scrollBox.scrollHeight;
    }
    scrollToBottom();

    async function pollMessages() {
      if (!conversationId) return;
      try {
        const data = await getJson(
          `/actions/messages_poll.php?conversation_id=${encodeURIComponent(conversationId)}&after_id=${encodeURIComponent(lastMessageId())}`
        );
        if (data.count > 0) {
          const temp = document.createElement('div');
          temp.innerHTML = data.html;
          [...temp.children].forEach((node) => {
            if (!listInner.querySelector(`[data-message-id="${node.dataset.messageId}"]`)) {
              listInner.appendChild(node);
            }
          });
          scrollToBottom();
        }
      } catch (err) {
        // sessizce gec, bir sonraki denemede tekrar dene
      }
    }

    setInterval(pollMessages, 4000);

    const messageForm = document.getElementById('message-form');
    if (messageForm) {
      messageForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = messageForm.querySelector('input[name="content"]');
        const content = input.value.trim();
        if (!content) return;

        try {
          const data = await postJson('/actions/message_send.php', {
            partner_id: messagesView.dataset.partnerId,
            content,
          });
          conversationId = data.conversation_id;
          messagesView.dataset.conversationId = conversationId;
          const emptyState = listInner.querySelector('p');
          if (emptyState && !listInner.querySelector('[data-message-id]')) {
            listInner.innerHTML = '';
          }
          listInner.appendChild(buildMessageBubble(data.sent_message, currentUserId));
          input.value = '';
          scrollToBottom();
        } catch (err) {
          console.error('Mesaj gönderme hatası:', err);
        }
      });
    }
  }

  document.addEventListener('change', (event) => {
    const input = event.target.closest('.media-input');
    if (!input) return;
    const picker = input.closest('.media-picker');
    const label = picker?.querySelector('.media-input-label');
    const preview = picker?.querySelector('.media-preview');
    const file = input.files[0];

    if (label) label.textContent = file ? file.name : 'Medya';

    if (preview) {
      if (file && file.type.startsWith('image/')) {
        preview.src = URL.createObjectURL(file);
        preview.classList.remove('hidden');
      } else {
        preview.classList.add('hidden');
        preview.removeAttribute('src');
      }
    }
  });

  const conversationSearch = document.getElementById('conversation-search');
  if (conversationSearch) {
    conversationSearch.addEventListener('input', () => {
      const query = conversationSearch.value.trim().toLowerCase();
      document.querySelectorAll('.conversation-row').forEach((row) => {
        row.style.display = row.dataset.name.includes(query) ? '' : 'none';
      });
    });
  }

  // Sag-alt kose canli sohbet widget'i - her sayfada (mesajlar sayfasi
  // haric, layout_foot.php orada zaten render etmiyor) tek panelli
  // liste/thread gecisli mini sohbet penceresi.
  const chatWidget = document.getElementById('chat-widget');
  if (chatWidget) {
    const fab = document.getElementById('chat-widget-fab');
    const fabBadge = document.getElementById('chat-widget-fab-badge');
    const panel = document.getElementById('chat-widget-panel');
    const backBtn = document.getElementById('chat-widget-back');
    const newBtn = document.getElementById('chat-widget-new');
    const closeBtn = document.getElementById('chat-widget-close');
    const titleEl = document.getElementById('chat-widget-title');
    const widgetBody = document.getElementById('chat-widget-body');
    const footer = document.getElementById('chat-widget-footer');

    let view = 'closed';
    let activeConversationId = '';
    let activePartnerId = '';
    let threadPollTimer = null;

    function setUnreadBadge(count) {
      fabBadge.textContent = count;
      fabBadge.classList.toggle('hidden', count <= 0);
      document.querySelectorAll('[data-nav-messages-badge-count]').forEach((el) => {
        el.textContent = count;
        el.classList.toggle('hidden', count <= 0);
      });
      document.querySelectorAll('[data-nav-messages-badge-dot]').forEach((el) => {
        el.classList.toggle('hidden', count <= 0);
      });
    }

    function stopThreadPoll() {
      if (threadPollTimer) { clearInterval(threadPollTimer); threadPollTimer = null; }
    }

    function lastWidgetMessageId() {
      const rows = widgetBody.querySelectorAll('[data-message-id]');
      return rows.length ? rows[rows.length - 1].dataset.messageId : '';
    }

    function scrollWidgetToBottom() {
      widgetBody.scrollTop = widgetBody.scrollHeight;
    }

    async function showList() {
      stopThreadPoll();
      view = 'list';
      activeConversationId = '';
      activePartnerId = '';
      backBtn.classList.add('hidden');
      newBtn.classList.remove('hidden');
      footer.classList.add('hidden');
      footer.innerHTML = '';
      titleEl.textContent = 'Mesajlar';
      widgetBody.innerHTML = '<p class="chat-widget-empty">Yükleniyor...</p>';
      try {
        const data = await getJson('/actions/conversations_list.php');
        setUnreadBadge(data.unread_total);
        if (!data.conversations.length) {
          widgetBody.innerHTML = '<p class="chat-widget-empty">Henüz bir sohbetin yok.</p>';
          return;
        }
        widgetBody.innerHTML = '';
        data.conversations.forEach((conv) => {
          const row = document.createElement('button');
          row.type = 'button';
          row.className = 'chat-widget-conv-row';
          row.innerHTML = `
            ${conv.partner.avatar_url
              ? `<img src="${escapeHtml(conv.partner.avatar_url)}" class="avatar avatar-sm" style="object-fit:cover" alt="" />`
              : `<div class="avatar avatar-sm">${escapeHtml(initials(conv.partner.name))}</div>`}
            <div class="flex-1 min-w-0">
              <p class="chat-widget-conv-name truncate">${escapeHtml(conv.partner.name)}</p>
              <p class="chat-widget-conv-preview truncate">${escapeHtml(conv.preview || '')}</p>
            </div>
            <div class="chat-widget-conv-meta">
              <span class="chat-widget-conv-time">${escapeHtml(conv.time_ago || '')}</span>
              ${conv.unread_count > 0 ? `<span class="bg-accent text-white text-xs font-semibold rounded-full" style="padding:1px 7px">${conv.unread_count}</span>` : ''}
            </div>
          `;
          row.addEventListener('click', () => openThread(conv.partner));
          widgetBody.appendChild(row);
        });
      } catch (err) {
        widgetBody.innerHTML = '<p class="chat-widget-empty">Sohbetler yüklenemedi.</p>';
        console.error('Sohbet listesi hatası (widget):', err);
      }
    }

    async function showFriendPicker() {
      stopThreadPoll();
      view = 'new';
      backBtn.classList.remove('hidden');
      newBtn.classList.add('hidden');
      footer.classList.add('hidden');
      titleEl.textContent = 'Yeni sohbet';
      widgetBody.innerHTML = '<p class="chat-widget-empty">Arkadaşların yükleniyor...</p>';
      try {
        const data = await getJson('/actions/friends_list.php');
        if (!data.friends.length) {
          widgetBody.innerHTML = '<p class="chat-widget-empty">Mesajlaşmak için önce bir arkadaş eklemelisin.</p>';
          return;
        }
        widgetBody.innerHTML = '';
        data.friends.forEach((friend) => {
          const row = document.createElement('button');
          row.type = 'button';
          row.className = 'chat-widget-conv-row';
          row.innerHTML = `
            ${friend.avatar_url
              ? `<img src="${escapeHtml(friend.avatar_url)}" class="avatar avatar-sm" style="object-fit:cover" alt="" />`
              : `<div class="avatar avatar-sm">${escapeHtml(initials(friend.name))}</div>`}
            <span class="chat-widget-conv-name">${escapeHtml(friend.name)}</span>
          `;
          row.addEventListener('click', () => openThread(friend));
          widgetBody.appendChild(row);
        });
      } catch (err) {
        widgetBody.innerHTML = '<p class="chat-widget-empty">Arkadaş listesi yüklenemedi.</p>';
        console.error('Arkadaş listesi hatası (widget):', err);
      }
    }

    function buildComposer() {
      footer.innerHTML = `
        <form class="chat-composer" id="chat-widget-form">
          <input type="text" class="chat-composer-input" name="content" placeholder="Bir mesaj yaz..." maxlength="2000" autocomplete="off" required />
          <button type="submit" class="chat-composer-send" aria-label="Gönder">
            <span class="material-symbols-outlined text-lg">send</span>
          </button>
        </form>
      `;
      footer.classList.remove('hidden');
      const form = footer.querySelector('form');
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = form.querySelector('input');
        const content = input.value.trim();
        if (!content || !activePartnerId) return;
        try {
          const data = await postJson('/actions/message_send.php', { partner_id: activePartnerId, content });
          activeConversationId = data.conversation_id;
          const emptyState = widgetBody.querySelector('.chat-widget-empty');
          if (emptyState) widgetBody.innerHTML = '';
          widgetBody.appendChild(buildMessageBubble(data.sent_message, currentUserId));
          input.value = '';
          scrollWidgetToBottom();
        } catch (err) {
          console.error('Mesaj gönderme hatası (widget):', err);
        }
      });
    }

    async function openThread(partner) {
      stopThreadPoll();
      view = 'thread';
      activePartnerId = partner.id;
      backBtn.classList.remove('hidden');
      newBtn.classList.add('hidden');
      titleEl.textContent = partner.name;
      widgetBody.innerHTML = '<p class="chat-widget-empty">Yükleniyor...</p>';
      buildComposer();

      try {
        const data = await getJson(`/actions/messages_thread.php?with=${encodeURIComponent(partner.id)}`);
        activeConversationId = data.conversation_id || '';
        widgetBody.innerHTML = data.count > 0
          ? data.html
          : `<p class="chat-widget-empty">${escapeHtml(partner.name)} ile sohbetin başlangıcı. İlk mesajı sen yaz!</p>`;
        scrollWidgetToBottom();

        threadPollTimer = setInterval(async () => {
          if (!activeConversationId) return;
          try {
            const poll = await getJson(
              `/actions/messages_poll.php?conversation_id=${encodeURIComponent(activeConversationId)}&after_id=${encodeURIComponent(lastWidgetMessageId())}`
            );
            if (poll.count > 0) {
              const temp = document.createElement('div');
              temp.innerHTML = poll.html;
              [...temp.children].forEach((node) => {
                if (!widgetBody.querySelector(`[data-message-id="${node.dataset.messageId}"]`)) {
                  widgetBody.appendChild(node);
                }
              });
              scrollWidgetToBottom();
            }
          } catch (err) {
            // sessizce gec, bir sonraki denemede tekrar dene
          }
        }, 4000);
      } catch (err) {
        widgetBody.innerHTML = '<p class="chat-widget-empty">Sohbet yüklenemedi.</p>';
        console.error('Sohbet yükleme hatası (widget):', err);
      }
    }

    fab.addEventListener('click', () => {
      chatWidget.classList.add('open');
      panel.classList.remove('hidden');
      showList();
    });
    closeBtn.addEventListener('click', () => {
      chatWidget.classList.remove('open');
      panel.classList.add('hidden');
      stopThreadPoll();
      view = 'closed';
    });
    backBtn.addEventListener('click', showList);
    newBtn.addEventListener('click', showFriendPicker);

    async function refreshUnreadBadge() {
      try {
        const data = await getJson('/actions/conversations_list.php');
        setUnreadBadge(data.unread_total);
      } catch (err) {
        // sessizce gec
      }
    }
    setInterval(refreshUnreadBadge, 15000);
  }
})();
