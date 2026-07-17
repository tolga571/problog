<?php

declare(strict_types=1);

function render_post_card(array $post, ?string $currentUserId): void
{
    $author = $post['author'];
    ?>
    <article id="post-<?= h($post['id']) ?>" class="card-hover p-5 sm:p-6 post-card" data-post-id="<?= h($post['id']) ?>" data-author-id="<?= h($author['id']) ?>" data-post-title="<?= h($post['title']) ?>">
      <div class="flex items-center gap-3 mb-4">
        <a href="/profile.php?id=<?= h($author['id']) ?>">
          <?= render_avatar($author) ?>
        </a>
        <div class="flex-1 min-w-0">
          <a href="/profile.php?id=<?= h($author['id']) ?>" class="font-semibold text-sm hover:text-accent transition-colors">
            <?= h($author['name']) ?>
          </a>
          <?php if (!empty($author['username'])): ?>
            <span class="text-muted-2 text-xs">@<?= h($author['username']) ?></span>
          <?php endif; ?>
          <p class="text-muted-2 text-xs">
            <?= h($author['bio'] ?: 'ProBlog üyesi') ?> &middot; <span title="<?= h(format_date($post['created_at'])) ?>"><?= h(time_ago($post['created_at'])) ?></span>
            <?php if (!empty($post['updated_at'])): ?>
              &middot; düzenlendi
            <?php endif; ?>
            <?php if ($post['status'] === 'draft'): ?>
              &middot; <span class="text-accent font-semibold">Taslak</span>
            <?php endif; ?>
          </p>
        </div>
        <?php if ($currentUserId && $currentUserId === $author['id']): ?>
          <div class="action-menu flex-shrink-0">
            <button type="button" class="action-menu-btn" title="Seçenekler" aria-label="Seçenekler">
              <span class="material-symbols-outlined text-lg">more_horiz</span>
            </button>
            <div class="action-menu-dropdown hidden">
              <?php if ($post['status'] === 'draft'): ?>
                <form method="post" action="/actions/publish_post.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="post_id" value="<?= h($post['id']) ?>" />
                  <button type="submit">Yayınla</button>
                </form>
              <?php endif; ?>
              <a href="/edit_post.php?id=<?= h($post['id']) ?>">Düzenle</a>
              <form method="post" action="/actions/delete_post.php" class="post-delete-form">
                <?= csrf_field() ?>
                <input type="hidden" name="post_id" value="<?= h($post['id']) ?>" />
                <button type="submit" class="post-delete-btn">Sil</button>
              </form>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($post['post_type'] === 'article' && $post['title'] !== ''): ?>
        <h2 class="text-xl font-bold text-white mb-2 leading-tight"><?= h($post['title']) ?></h2>
      <?php endif; ?>
      <?php $isLongPost = mb_strlen($post['content']) > 600; ?>
      <div class="post-content-wrapper <?= $isLongPost ? 'clamped' : '' ?> mb-4 <?= $post['post_type'] === 'post' ? 'text-lg' : '' ?>">
        <div class="prose-content"><?= render_markdown($post['content']) ?></div>
      </div>
      <?php if ($isLongPost): ?>
        <button type="button" class="post-expand-btn text-accent text-sm font-semibold mb-4 hover:underline">
          Devamını oku
        </button>
      <?php endif; ?>

      <?php if (!empty($post['image_path']) && $post['media_type'] === 'video'): ?>
        <video src="<?= h($post['image_path']) ?>" controls preload="metadata" class="w-full rounded-xl mb-4" style="max-height:420px"></video>
      <?php elseif (!empty($post['image_path'])): ?>
        <div class="w-full rounded-xl mb-4 overflow-hidden" style="max-height:560px;background:var(--color-surface-3)">
          <img src="<?= h($post['image_path']) ?>" alt="<?= h($post['title'] ?: 'Gönderi görseli') ?>" class="w-full h-full" style="object-fit:contain;max-height:560px" />
        </div>
      <?php endif; ?>

      <?php if (!empty($post['tags'])): ?>
        <div class="flex flex-wrap gap-2 mb-4">
          <?php foreach ($post['tags'] as $tag): ?>
            <a href="/tag.php?name=<?= urlencode($tag) ?>" class="bg-surface-3 hover:bg-surface-5 text-muted-2 hover:text-white text-xs px-3 py-1 rounded-full transition-colors">#<?= h($tag) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="flex items-center justify-between pt-4 border-t border-border">
        <div class="flex items-center gap-6">
          <button
            class="post-interact-btn like-btn <?= $post['user_liked'] ? 'text-accent' : '' ?>"
            data-liked="<?= $post['user_liked'] ? '1' : '0' ?>"
            title="Beğen"
            aria-label="Beğen"
            <?= $currentUserId ? '' : 'disabled' ?>
          >
            <span class="material-symbols-outlined text-xl like-icon"><?= $post['user_liked'] ? 'favorite' : 'favorite_border' ?></span>
            <span class="like-count"><?= (int) $post['likes_count'] ?></span>
          </button>
          <button class="post-interact-btn comment-toggle-btn" title="Yorum yap" aria-label="Yorum yap">
            <span class="material-symbols-outlined text-xl">chat_bubble</span>
            <span class="comment-count"><?= (int) $post['comments_count'] ?></span>
          </button>
        </div>
        <div class="flex items-center gap-4">
          <div class="action-menu">
            <button type="button" class="post-interact-btn action-menu-btn share-menu-btn" title="Paylaş" aria-label="Paylaş">
              <span class="material-symbols-outlined text-xl">ios_share</span>
            </button>
            <div class="action-menu-dropdown action-menu-dropdown-icons hidden">
              <button type="button" class="share-chat-btn">
                <span class="material-symbols-outlined text-base">forum</span>
                Sohbetle gönder
              </button>
              <button type="button" class="share-copy-btn">
                <span class="material-symbols-outlined text-base">link</span>
                Bağlantıyı kopyala
              </button>
              <button type="button" class="share-native-btn" hidden>
                <span class="material-symbols-outlined text-base">ios_share</span>
                Diğer uygulamalarla paylaş
              </button>
            </div>
          </div>
          <button
            type="button"
            class="post-interact-btn bookmark-btn <?= $post['user_bookmarked'] ? 'text-accent' : '' ?>"
            data-bookmarked="<?= $post['user_bookmarked'] ? '1' : '0' ?>"
            title="Kaydet"
            aria-label="Kaydet"
            <?= $currentUserId ? '' : 'disabled' ?>
          >
            <span class="material-symbols-outlined text-xl bookmark-icon"><?= $post['user_bookmarked'] ? 'bookmark' : 'bookmark_border' ?></span>
          </button>
        </div>
      </div>

      <div class="comments-section mt-4 pt-4 border-t border-border space-y-4 hidden">
        <?php if ($currentUserId): ?>
          <form class="comment-form flex gap-3 items-end">
            <textarea class="input-field text-sm flex-1 resize-none comment-textarea" placeholder="Yorum yaz..." name="content" maxlength="500" rows="1" required></textarea>
            <button type="submit" class="btn-primary text-sm px-4">Gönder</button>
          </form>
        <?php endif; ?>
        <div class="comments-list"></div>
      </div>
    </article>
    <?php
}
