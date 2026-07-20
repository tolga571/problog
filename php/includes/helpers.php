<?php

declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Gözden kaçan fazla boş satırların yayınlanan içerikte art arda
 * <br> / paragraf boşluğuna dönüşmesini engeller: her satırın sonundaki
 * boşlukları kırpar, 3+ ardışık satır sonunu tek boş satıra indirir.
 *
 * Zengin metin (Milkdown) editöründe kullanıcı sadece Enter'a basıp bir
 * paragrafı boş bırakırsa, markdown'a çevrilirken bu boş paragraf sessizce
 * kaybolmasın diye tek başına bir ters eğik çizgiye ("\") ya da görünmez bir
 * boşluk karakterine (nbsp / sıfır genişlikli boşluk) dönüştürülüyor.
 * Parsedown bu satırı gerçekten boş saymadığı için ayrı bir <p>\</p>
 * paragrafı olarak basıyor - kullanıcının gördüğü istenmeyen fazladan
 * satır/boşluk budur. Bu satırları gerçek boş satıra indirgeyip normal
 * boş-satır sadeleştirmesine dahil ediyoruz.
 */
function normalize_content(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = str_replace(["\u{00A0}", "\u{200B}"], '', $text);
    $text = preg_replace('/^\\\\[ \t]*$/m', '', $text) ?? $text;
    $text = preg_replace('/^\s*<br\s*\/?>\s*$/mi', '', $text) ?? $text;
    $text = preg_replace('/[ \t]+$/m', '', $text) ?? $text;
    $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
    return trim($text);
}

class UploadException extends RuntimeException
{
}

function handle_image_upload(array $file, string $subdir): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new UploadException('Dosya yüklenirken bir hata oluştu.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new UploadException('Dosya boyutu en fazla 5MB olabilir.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($extensions[$mime])) {
        throw new UploadException('Sadece JPG, PNG, WEBP veya GIF dosyaları yüklenebilir.');
    }

    $dir = __DIR__ . '/../uploads/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $filename = uuid() . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        throw new UploadException('Dosya kaydedilemedi.');
    }

    return '/uploads/' . $subdir . '/' . $filename;
}

function handle_media_upload(array $file, string $subdir): ?array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new UploadException('Dosya yüklenirken bir hata oluştu.');
    }

    if ($file['size'] > 30 * 1024 * 1024) {
        throw new UploadException('Dosya boyutu en fazla 30MB olabilir.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $imageExtensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $videoExtensions = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];

    if (isset($imageExtensions[$mime])) {
        $mediaType = 'image';
        $ext = $imageExtensions[$mime];
    } elseif (isset($videoExtensions[$mime])) {
        $mediaType = 'video';
        $ext = $videoExtensions[$mime];
    } else {
        throw new UploadException('Sadece JPG, PNG, WEBP, GIF görsel ya da MP4, WEBM, MOV video dosyası yüklenebilir.');
    }

    $dir = __DIR__ . '/../uploads/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $filename = uuid() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        throw new UploadException('Dosya kaydedilemedi.');
    }

    return ['path' => '/uploads/' . $subdir . '/' . $filename, 'type' => $mediaType];
}

function render_friend_button(string $status, string $targetId): void
{
    switch ($status) {
        case 'friends':
            ?>
            <button type="button" class="friend-btn btn-outline text-sm flex items-center gap-1.5" data-target-id="<?= h($targetId) ?>" data-status="friends" title="Arkadaşlıktan çıkar" aria-label="Arkadaşlıktan çıkar">
              <span class="material-symbols-outlined text-lg">how_to_reg</span>
              <span class="label-text">Arkadaşsınız</span>
            </button>
            <?php
            break;

        case 'pending_sent':
            ?>
            <button type="button" class="friend-btn btn-outline text-sm flex items-center gap-1.5" data-target-id="<?= h($targetId) ?>" data-status="pending_sent">
              <span class="material-symbols-outlined text-lg">hourglass_top</span>
              <span class="label-text">İstek Gönderildi</span>
            </button>
            <?php
            break;

        case 'pending_received':
            ?>
            <div class="flex items-center gap-2">
              <button type="button" class="friend-accept-btn btn-primary text-sm flex items-center gap-1.5" data-target-id="<?= h($targetId) ?>">
                <span class="material-symbols-outlined text-lg">check</span>
                Kabul Et
              </button>
              <button type="button" class="friend-decline-btn btn-outline text-sm p-2" data-target-id="<?= h($targetId) ?>" title="Reddet" aria-label="Reddet">
                <span class="material-symbols-outlined text-lg">close</span>
              </button>
            </div>
            <?php
            break;

        default:
            ?>
            <button type="button" class="friend-btn btn-outline text-sm flex items-center gap-1.5" data-target-id="<?= h($targetId) ?>" data-status="none">
              <span class="material-symbols-outlined text-lg">person_add</span>
              <span class="label-text">Arkadaş Ekle</span>
            </button>
            <?php
    }
}

function render_avatar(array $user, string $sizeClass = 'avatar'): string
{
    if (!empty($user['avatar_url'])) {
        return '<img src="' . h($user['avatar_url']) . '" class="' . h($sizeClass) . '" style="object-fit:cover" alt="" />';
    }
    return '<div class="' . h($sizeClass) . '">' . h(initials($user['name'])) . '</div>';
}

function render_cover(array $user): string
{
    if (!empty($user['cover_url'])) {
        return '<img src="' . h($user['cover_url']) . '" class="cover-photo" alt="" />';
    }
    return '<div class="cover-gradient absolute inset-0"></div>';
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $expected = $_SESSION['csrf_token'] ?? '';

    if (!$expected || !$submitted || !hash_equals($expected, $submitted)) {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
            json_response(['message' => 'Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.'], 403);
        }

        flash_set('error', 'Oturum süresi doldu, lütfen tekrar deneyin.');
        redirect($_SERVER['HTTP_REFERER'] ?? $_SERVER['REQUEST_URI']);
    }
}

function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (empty($_SESSION['flash'][$key])) {
        return null;
    }
    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $message;
}

function flash_old(string $key): array
{
    $old = $_SESSION['old'] ?? [];
    unset($_SESSION['old']);
    return $old;
}

function flash_set_old(array $data): void
{
    $_SESSION['old'] = $data;
}

$GLOBALS['TR_MONTHS'] = [
    1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
    7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
];

function format_date(string $isoDate): string
{
    $ts = strtotime($isoDate . (str_ends_with($isoDate, 'Z') ? '' : 'Z'));
    if ($ts === false) {
        return $isoDate;
    }
    $day = (int) date('j', $ts);
    $month = $GLOBALS['TR_MONTHS'][(int) date('n', $ts)];
    $year = date('Y', $ts);
    $time = date('H:i', $ts);
    return "{$day} {$month} {$year} {$time}";
}

function format_birthdate(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }
    $day = (int) date('j', $ts);
    $month = $GLOBALS['TR_MONTHS'][(int) date('n', $ts)];
    $year = date('Y', $ts);
    return "{$day} {$month} {$year}";
}

function is_new_day(?string $prevCreatedAt, string $currentCreatedAt): bool
{
    if ($prevCreatedAt === null) {
        return true;
    }
    return substr($prevCreatedAt, 0, 10) !== substr($currentCreatedAt, 0, 10);
}

function generate_unique_username(string $name): string
{
    $trMap = [
        'ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'Ğ' => 'g', 'ü' => 'u', 'Ü' => 'u',
        'ş' => 's', 'Ş' => 's', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
    ];
    $base = strtr(mb_strtolower($name, 'UTF-8'), $trMap);
    $base = preg_replace('/[^a-z0-9]+/', '', $base) ?? '';
    $base = mb_substr($base, 0, 20);
    if ($base === '') {
        $base = 'kullanici';
    }

    $stmt = db()->prepare('SELECT id FROM users WHERE username = ?');
    $username = $base;
    while (true) {
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            return $username;
        }
        $username = $base . random_int(100, 9999);
    }
}

function normalize_website(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return $url;
}

function time_ago(string $isoDate): string
{
    $then = strtotime($isoDate . (str_ends_with($isoDate, 'Z') ? '' : 'Z'));
    if ($then === false) {
        return $isoDate;
    }
    $diff = time() - $then;

    if ($diff < 60) return 'Az önce';
    if ($diff < 3600) return floor($diff / 60) . 'dk';
    if ($diff < 86400) return floor($diff / 3600) . 'sa';
    if ($diff < 2592000) return floor($diff / 86400) . 'g';
    return format_date($isoDate);
}

require_once __DIR__ . '/vendor/Parsedown.php';

const MENTION_PATTERN = '/@\[([^\]\[]{1,80})\]\(([a-f0-9\-]{36})\)/';

function render_markdown(string $markdown): string
{
    static $parser = null;
    if ($parser === null) {
        $parser = new Parsedown();
        $parser->setSafeMode(true);
    }

    // @[Ad](id) mention sözdizimi Parsedown'un kendi [metin](url) link
    // sözdizimiyle çakışır; Parsedown çalışmadan önce geçici, markdown'a
    // özel karakter içermeyen bir token'a dönüştürüp, render sonrası
    // gerçek profil linkiyle değiştiriyoruz.
    $mentions = [];
    $tokenized = preg_replace_callback(
        MENTION_PATTERN,
        function (array $m) use (&$mentions) {
            $token = 'MENTIONTOKEN' . count($mentions) . 'ENDTOKEN';
            $mentions[$token] = '<a href="/profile.php?id=' . h($m[2]) . '" class="mention-link">@' . h($m[1]) . '</a>';
            return $token;
        },
        $markdown
    );

    return strtr($parser->text($tokenized), $mentions);
}

/**
 * Ham yorum/mesaj içeriğindeki @[Ad](id) mention token'larını çıkarır.
 * @return array<int, array{name: string, id: string}>
 */
function extract_mentions(string $content): array
{
    if (!preg_match_all(MENTION_PATTERN, $content, $matches, PREG_SET_ORDER)) {
        return [];
    }

    $seen = [];
    $mentions = [];
    foreach ($matches as $m) {
        if (isset($seen[$m[2]])) {
            continue;
        }
        $seen[$m[2]] = true;
        $mentions[] = ['name' => $m[1], 'id' => $m[2]];
    }
    return $mentions;
}

/**
 * Yorum içeriğini (düz metin, markdown DEĞİL) escape edip @[Ad](id)
 * mention token'larını profil linkine çevirir. Satır sonları <br>'e dönüşür.
 */
function render_comment_content(string $content): string
{
    $mentions = [];
    $tokenized = preg_replace_callback(
        MENTION_PATTERN,
        function (array $m) use (&$mentions) {
            $token = 'MENTIONTOKEN' . count($mentions) . 'ENDTOKEN';
            $mentions[$token] = '<a href="/profile.php?id=' . h($m[2]) . '" class="mention-link">@' . h($m[1]) . '</a>';
            return $token;
        },
        $content
    );
    return strtr(nl2br(h($tokenized)), $mentions);
}

function create_notification(string $userId, string $actorId, string $type, ?string $postId = null): void
{
    if ($userId === $actorId) {
        return;
    }
    db()->prepare('INSERT INTO notifications (id, user_id, actor_id, type, post_id) VALUES (?, ?, ?, ?, ?)')
        ->execute([uuid(), $userId, $actorId, $type, $postId]);
}

function unread_notifications_count(string $userId): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL AND type != 'message'");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function friendship_row(string $userIdA, string $userIdB): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM friend_requests
         WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)'
    );
    $stmt->execute([$userIdA, $userIdB, $userIdB, $userIdA]);
    return $stmt->fetch() ?: null;
}

function friendship_status(string $viewerId, string $otherId): string
{
    if ($viewerId === $otherId) {
        return 'self';
    }

    $row = friendship_row($viewerId, $otherId);
    if (!$row) {
        return 'none';
    }
    if ($row['status'] === 'accepted') {
        return 'friends';
    }
    return $row['sender_id'] === $viewerId ? 'pending_sent' : 'pending_received';
}

function are_friends(string $userIdA, string $userIdB): bool
{
    return friendship_status($userIdA, $userIdB) === 'friends';
}

function friends_count(string $userId): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM friend_requests WHERE status = 'accepted' AND (sender_id = ? OR receiver_id = ?)"
    );
    $stmt->execute([$userId, $userId]);
    return (int) $stmt->fetchColumn();
}

function friend_ids(string $userId): array
{
    $stmt = db()->prepare(
        "SELECT sender_id, receiver_id FROM friend_requests WHERE status = 'accepted' AND (sender_id = ? OR receiver_id = ?)"
    );
    $stmt->execute([$userId, $userId]);
    $ids = [];
    foreach ($stmt->fetchAll() as $row) {
        $ids[] = $row['sender_id'] === $userId ? $row['receiver_id'] : $row['sender_id'];
    }
    return $ids;
}

function find_or_create_conversation(string $userIdA, string $userIdB): string
{
    $one = $userIdA < $userIdB ? $userIdA : $userIdB;
    $two = $userIdA < $userIdB ? $userIdB : $userIdA;

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM conversations WHERE user_one_id = ? AND user_two_id = ?');
    $stmt->execute([$one, $two]);
    $row = $stmt->fetch();
    if ($row) {
        return $row['id'];
    }

    $id = uuid();
    $pdo->prepare('INSERT INTO conversations (id, user_one_id, user_two_id) VALUES (?, ?, ?)')
        ->execute([$id, $one, $two]);
    return $id;
}

function conversation_partner_id(array $conversation, string $viewerId): string
{
    return $conversation['user_one_id'] === $viewerId
        ? $conversation['user_two_id']
        : $conversation['user_one_id'];
}

function unread_messages_count(string $userId): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM messages m
         JOIN conversations c ON c.id = m.conversation_id
         WHERE (c.user_one_id = ? OR c.user_two_id = ?)
           AND m.sender_id != ?
           AND m.read_at IS NULL
           AND m.deleted_at IS NULL"
    );
    $stmt->execute([$userId, $userId, $userId]);
    return (int) $stmt->fetchColumn();
}

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS = 900;

function login_locked_seconds(string $identifier): ?int
{
    $stmt = db()->prepare('SELECT locked_until FROM login_attempts WHERE identifier = ?');
    $stmt->execute([$identifier]);
    $row = $stmt->fetch();

    if (!$row || !$row['locked_until']) {
        return null;
    }

    $remaining = strtotime($row['locked_until']) - time();
    return $remaining > 0 ? $remaining : null;
}

function login_register_failure(string $identifier): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT attempts FROM login_attempts WHERE identifier = ?');
    $stmt->execute([$identifier]);
    $row = $stmt->fetch();

    $attempts = ($row['attempts'] ?? 0) + 1;
    $lockedUntil = $attempts >= LOGIN_MAX_ATTEMPTS
        ? date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_SECONDS)
        : null;

    $pdo->prepare(
        'INSERT INTO login_attempts (identifier, attempts, locked_until) VALUES (?, ?, ?)
         ON CONFLICT(identifier) DO UPDATE SET attempts = excluded.attempts, locked_until = excluded.locked_until'
    )->execute([$identifier, $attempts, $lockedUntil]);
}

function login_clear_attempts(string $identifier): void
{
    db()->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([$identifier]);
}

function sync_post_tags(string $postId, string $rawTags): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM post_tags WHERE post_id = ?')->execute([$postId]);

    $names = array_unique(array_filter(array_map(
        fn($t) => mb_strtolower(trim($t)),
        explode(',', $rawTags)
    )));

    foreach (array_slice($names, 0, 5) as $name) {
        if ($name === '' || mb_strlen($name) > 30) {
            continue;
        }

        $stmt = $pdo->prepare('SELECT id FROM tags WHERE name = ?');
        $stmt->execute([$name]);
        $tag = $stmt->fetch();

        $tagId = $tag['id'] ?? uuid();
        if (!$tag) {
            $pdo->prepare('INSERT INTO tags (id, name) VALUES (?, ?)')->execute([$tagId, $name]);
        }

        $pdo->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?) ON CONFLICT (post_id, tag_id) DO NOTHING')
            ->execute([$postId, $tagId]);
    }
}

const FEED_PAGE_SIZE = 20;

function fetch_feed_posts(string $feed, string $userId, int $limit, int $offset): array
{
    $hiddenAuthorsClause = "author_id NOT IN (
        SELECT blocked_id FROM blocks WHERE blocker_id = :me
        UNION
        SELECT blocker_id FROM blocks WHERE blocked_id = :me
        UNION
        SELECT muted_id FROM mutes WHERE muter_id = :me
    )";

    if ($feed === 'following') {
        $stmt = db()->prepare(
            "SELECT * FROM posts
             WHERE status = 'published'
               AND (author_id = :me
                    OR author_id IN (SELECT following_id FROM follows WHERE follower_id = :me))
               AND {$hiddenAuthorsClause}
             ORDER BY created_at DESC, id DESC
             LIMIT :limit OFFSET :offset"
        );
    } else {
        $stmt = db()->prepare(
            "SELECT * FROM posts WHERE status = 'published' AND {$hiddenAuthorsClause}
             ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset"
        );
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':me', $userId);
    $stmt->execute();

    return $stmt->fetchAll();
}

function are_blocked(string $userIdA, string $userIdB): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)'
    );
    $stmt->execute([$userIdA, $userIdB, $userIdB, $userIdA]);
    return ((int) $stmt->fetchColumn()) > 0;
}

function has_blocked(string $blockerId, string $blockedId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM blocks WHERE blocker_id = ? AND blocked_id = ?');
    $stmt->execute([$blockerId, $blockedId]);
    return ((int) $stmt->fetchColumn()) > 0;
}

function is_muted(string $muterId, string $mutedId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM mutes WHERE muter_id = ? AND muted_id = ?');
    $stmt->execute([$muterId, $mutedId]);
    return ((int) $stmt->fetchColumn()) > 0;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        if ($part !== '') {
            $letters .= mb_substr($part, 0, 1);
        }
    }
    return mb_strtoupper($letters, 'UTF-8');
}

function comment_like_state(string $commentId, ?string $currentUserId): array
{
    $pdo = db();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM comment_likes WHERE comment_id = ?');
    $stmt->execute([$commentId]);
    $likesCount = (int) $stmt->fetchColumn();

    $userLiked = false;
    if ($currentUserId) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM comment_likes WHERE comment_id = ? AND user_id = ?');
        $stmt->execute([$commentId, $currentUserId]);
        $userLiked = ((int) $stmt->fetchColumn()) > 0;
    }

    return ['likes_count' => $likesCount, 'user_liked' => $userLiked];
}

function post_with_details(array $post, ?string $currentUserId): array
{
    $pdo = db();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM likes WHERE post_id = ?');
    $stmt->execute([$post['id']]);
    $likesCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE post_id = ?');
    $stmt->execute([$post['id']]);
    $commentsCount = (int) $stmt->fetchColumn();

    $userLiked = false;
    $userBookmarked = false;
    if ($currentUserId) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM likes WHERE post_id = ? AND user_id = ?');
        $stmt->execute([$post['id'], $currentUserId]);
        $userLiked = ((int) $stmt->fetchColumn()) > 0;

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM bookmarks WHERE post_id = ? AND user_id = ?');
        $stmt->execute([$post['id'], $currentUserId]);
        $userBookmarked = ((int) $stmt->fetchColumn()) > 0;
    }

    $stmt = $pdo->prepare('SELECT id, name, username, bio, avatar_url FROM users WHERE id = ?');
    $stmt->execute([$post['author_id']]);
    $author = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT t.name FROM tags t JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name'
    );
    $stmt->execute([$post['id']]);
    $tags = array_column($stmt->fetchAll(), 'name');

    return [
        'id' => $post['id'],
        'title' => $post['title'],
        'content' => $post['content'],
        'image_path' => $post['image_path'] ?? '',
        'media_type' => $post['media_type'] ?? '',
        'status' => $post['status'] ?? 'published',
        'post_type' => $post['post_type'] ?? 'article',
        'created_at' => $post['created_at'],
        'updated_at' => $post['updated_at'] ?? null,
        'likes_count' => $likesCount,
        'comments_count' => $commentsCount,
        'user_liked' => $userLiked,
        'user_bookmarked' => $userBookmarked,
        'author' => $author,
        'tags' => $tags,
    ];
}

const ARTICLE_LANGUAGES = [
    'tr' => 'Türkçe',
    'en' => 'İngilizce',
    'ar' => 'Arapça',
    'es' => 'İspanyolca',
    'fr' => 'Fransızca',
    'ru' => 'Rusça',
    'ja' => 'Japonca',
    'zh' => 'Çince',
];

/**
 * Bir kaynak metni paragraf ve cümlelere böler. Her cümleye diller arası
 * eşleştirme için kararlı bir "code" ve gösterim sırası için "sort" atanır.
 * Bilinen kısaltmalardan (Dr., vb., Prof. ...) sonraki noktada bölünmez.
 * @return array<int, array{code:string, sort:int, p:int, text:string, note:string}>
 */
function split_into_sentences(string $text): array
{
    $text = normalize_content($text);
    if ($text === '') {
        return [];
    }

    $abbreviations = ['dr', 'prof', 'doç', 'vs', 'vb', 'örn', 'sn', 'mr', 'mrs', 'ms', 'st', 'no', 'a.g.e', 'a.g.m'];
    $paragraphs = preg_split('/\n{2,}/', $text) ?: [$text];

    $sentences = [];
    $index = 0;
    foreach ($paragraphs as $pIndex => $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            continue;
        }

        $parts = preg_split(
            '/(?<=[.!?…。！？])\s+(?=[A-ZÇĞİÖŞÜ0-9"«“(\p{Lu}\p{Han}\p{Hiragana}\p{Katakana}]|$)/u',
            $paragraph
        ) ?: [$paragraph];

        $buffer = '';
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $buffer = $buffer === '' ? $part : $buffer . ' ' . $part;

            $lastWord = '';
            if (preg_match('/([a-zA-ZçğıöşüÇĞİÖŞÜ.]+)\.$/u', $buffer, $m)) {
                $lastWord = mb_strtolower(rtrim($m[1], '.'), 'UTF-8');
            }
            if ($lastWord !== '' && in_array($lastWord, $abbreviations, true)) {
                continue;
            }

            $index++;
            $sentences[] = ['code' => 's' . $index, 'sort' => $index, 'p' => $pIndex, 'text' => $buffer, 'note' => '', 'meta' => new stdClass()];
            $buffer = '';
        }

        if ($buffer !== '') {
            $index++;
            $sentences[] = ['code' => 's' . $index, 'sort' => $index, 'p' => $pIndex, 'text' => $buffer, 'note' => '', 'meta' => new stdClass()];
        }
    }

    return $sentences;
}

/** @param array<int, array<string, mixed>> $sentences */
function renumber_sentences(array $sentences): array
{
    $sentences = array_values($sentences);
    foreach ($sentences as $i => &$sentence) {
        $sentence['sort'] = $i + 1;
        $sentence['code'] = (string) ($sentence['code'] ?? ('s' . ($i + 1)));
        $sentence['p'] = (int) ($sentence['p'] ?? 0);
        $sentence['text'] = (string) ($sentence['text'] ?? '');
        $sentence['note'] = (string) ($sentence['note'] ?? '');
        $sentence['meta'] = clean_sentence_meta($sentence['meta'] ?? []);
    }
    unset($sentence);
    return $sentences;
}

/**
 * additionalProperties benzeri serbest key-value cift dizisi: sadece string
 * anahtar/deger kabul eder, bos deger/anahtarlari eler, 20 ciftle sinirlar.
 * @param mixed $meta
 */
function clean_sentence_meta($meta): object
{
    $clean = [];
    if (is_array($meta) || is_object($meta)) {
        $i = 0;
        foreach ((array) $meta as $key => $value) {
            if (++$i > 20) {
                break;
            }
            $key = trim((string) $key);
            $value = trim((string) $value);
            if ($key === '' || $value === '') {
                continue;
            }
            $clean[mb_substr($key, 0, 40)] = mb_substr($value, 0, 200);
        }
    }
    return (object) $clean;
}

/**
 * @param array<int, array<string, mixed>> $sourceSentences
 * @param array<int, array<string, mixed>> $targetSentences
 */
function translation_percent(array $sourceSentences, array $targetSentences): int
{
    if (!$sourceSentences) {
        return 0;
    }

    $byCode = [];
    foreach ($targetSentences as $sentence) {
        $byCode[$sentence['code']] = $sentence;
    }

    $translated = 0;
    foreach ($sourceSentences as $sentence) {
        $match = $byCode[$sentence['code']] ?? null;
        if ($match && trim(strip_tags((string) ($match['text'] ?? ''))) !== '') {
            $translated++;
        }
    }

    return (int) round($translated / count($sourceSentences) * 100);
}

/** @param array<int, array<string, mixed>> $sentences */
function sentences_to_plain_text(array $sentences): string
{
    $byParagraph = [];
    foreach ($sentences as $sentence) {
        $byParagraph[(int) ($sentence['p'] ?? 0)][] = trim(strip_tags((string) ($sentence['text'] ?? '')));
    }
    ksort($byParagraph);

    $paragraphs = array_map(fn(array $texts) => implode(' ', array_filter($texts, fn($t) => $t !== '')), $byParagraph);
    return implode("\n\n", array_filter($paragraphs, fn($p) => $p !== ''));
}

function get_article_translation(string $articleId, string $languageId): ?array
{
    $stmt = db()->prepare('SELECT * FROM article_translations WHERE article_id = ? AND language_id = ?');
    $stmt->execute([$articleId, $languageId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['sentences'] = json_decode((string) $row['sentences_json'], true) ?: [];
    return $row;
}

/** @param array<int, array<string, mixed>> $sentences */
function save_article_translation(string $articleId, string $languageId, array $sentences, bool $isSource = false, ?string $title = null): array
{
    $sentences = renumber_sentences($sentences);
    $json = json_encode($sentences, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, title FROM article_translations WHERE article_id = ? AND language_id = ?');
    $stmt->execute([$articleId, $languageId]);
    $existing = $stmt->fetch();

    $title = $title !== null ? mb_substr(trim($title), 0, 120) : ($existing['title'] ?? '');

    if ($existing) {
        $pdo->prepare("UPDATE article_translations SET sentences_json = ?, is_source = ?, title = ?, updated_at = datetime('now') WHERE id = ?")
            ->execute([$json, $isSource ? 1 : 0, $title, $existing['id']]);
    } else {
        $pdo->prepare('INSERT INTO article_translations (id, article_id, language_id, is_source, title, sentences_json) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([uuid(), $articleId, $languageId, $isSource ? 1 : 0, $title, $json]);
    }

    return $sentences;
}
