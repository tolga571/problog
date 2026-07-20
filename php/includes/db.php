<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session_handler.php';

function db(bool $reset = false): ?PDO
{
    static $pdo = null;

    if ($reset) {
        $pdo = null;
        return null;
    }

    if ($pdo !== null) {
        return $pdo;
    }

    $databaseUrl = env('DATABASE_URL');

    if ($databaseUrl) {
        $parts = parse_url($databaseUrl);
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $parts['host'] ?? 'localhost',
            $parts['port'] ?? 5432,
            ltrim($parts['path'] ?? '', '/')
        );
        if (!empty($query['sslmode'])) {
            $dsn .= ';sslmode=' . $query['sslmode'];
        }

        $pdo = new PDO(
            $dsn,
            isset($parts['user']) ? urldecode($parts['user']) : null,
            isset($parts['pass']) ? urldecode($parts['pass']) : null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $check = $pdo->query("SELECT to_regclass('public.users') AS t")->fetch();
        $isNew = empty($check['t']);

        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } else {
        $isNew = !file_exists(DB_PATH);

        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    if ($isNew) {
        init_schema($pdo);
    }

    run_migrations($pdo);

    return $pdo;
}

function db_driver(PDO $pdo): string
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function adapt_schema_sql(string $sql, PDO $pdo): string
{
    if (db_driver($pdo) === 'pgsql') {
        return str_replace(
            "datetime('now')",
            "to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS')",
            $sql
        );
    }
    return $sql;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    if (db_driver($pdo) === 'pgsql') {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetch();
    }

    $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
    foreach ($stmt->fetchAll() as $row) {
        if ($row['name'] === $column) {
            return true;
        }
    }
    return false;
}

function run_migrations(PDO $pdo): void
{
    if (!column_exists($pdo, 'posts', 'updated_at')) {
        $pdo->exec('ALTER TABLE posts ADD COLUMN updated_at TEXT');
    }

    if (!column_exists($pdo, 'comments', 'edited_at')) {
        $pdo->exec('ALTER TABLE comments ADD COLUMN edited_at TEXT');
    }

    if (!column_exists($pdo, 'comments', 'parent_id')) {
        $pdo->exec('ALTER TABLE comments ADD COLUMN parent_id TEXT');
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_parent_id ON comments(parent_id)');

    if (!column_exists($pdo, 'users', 'cover_url')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN cover_url TEXT DEFAULT ''");
    }

    if (!column_exists($pdo, 'users', 'username')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN username TEXT');
    }
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_username ON users(username)');

    if (!column_exists($pdo, 'users', 'birth_date')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN birth_date TEXT');
    }

    if (!column_exists($pdo, 'users', 'city')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN city TEXT DEFAULT ''");
    }

    if (!column_exists($pdo, 'users', 'website')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN website TEXT DEFAULT ''");
    }

    $pdo->exec(adapt_schema_sql('
        CREATE TABLE IF NOT EXISTS bookmarks (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            post_id TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime(\'now\')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            UNIQUE(user_id, post_id)
        );
        CREATE INDEX IF NOT EXISTS idx_bookmarks_user_id ON bookmarks(user_id);
        CREATE INDEX IF NOT EXISTS idx_bookmarks_post_id ON bookmarks(post_id);
    ', $pdo));

    if (!column_exists($pdo, 'posts', 'post_type')) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN post_type TEXT NOT NULL DEFAULT 'article'");
    }

    $pdo->exec(adapt_schema_sql('
        CREATE TABLE IF NOT EXISTS blocks (
            id TEXT PRIMARY KEY,
            blocker_id TEXT NOT NULL,
            blocked_id TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime(\'now\')),
            FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(blocker_id, blocked_id)
        );
        CREATE INDEX IF NOT EXISTS idx_blocks_blocker_id ON blocks(blocker_id);
        CREATE INDEX IF NOT EXISTS idx_blocks_blocked_id ON blocks(blocked_id);

        CREATE TABLE IF NOT EXISTS mutes (
            id TEXT PRIMARY KEY,
            muter_id TEXT NOT NULL,
            muted_id TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime(\'now\')),
            FOREIGN KEY (muter_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (muted_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(muter_id, muted_id)
        );
        CREATE INDEX IF NOT EXISTS idx_mutes_muter_id ON mutes(muter_id);

        CREATE TABLE IF NOT EXISTS reports (
            id TEXT PRIMARY KEY,
            reporter_id TEXT NOT NULL,
            reported_id TEXT NOT NULL,
            reason TEXT NOT NULL DEFAULT \'\',
            created_at TEXT DEFAULT (datetime(\'now\')),
            FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE
        );
        CREATE INDEX IF NOT EXISTS idx_reports_reported_id ON reports(reported_id);

        CREATE TABLE IF NOT EXISTS comment_likes (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            comment_id TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime(\'now\')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            UNIQUE(user_id, comment_id)
        );
        CREATE INDEX IF NOT EXISTS idx_comment_likes_comment_id ON comment_likes(comment_id);
        CREATE INDEX IF NOT EXISTS idx_comment_likes_user_id ON comment_likes(user_id);

        CREATE TABLE IF NOT EXISTS sessions (
            id TEXT PRIMARY KEY,
            data TEXT NOT NULL DEFAULT \'\',
            last_activity INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS idx_sessions_last_activity ON sessions(last_activity);

        CREATE TABLE IF NOT EXISTS articles (
            id TEXT PRIMARY KEY,
            author_id TEXT NOT NULL,
            title TEXT NOT NULL,
            source_language TEXT NOT NULL DEFAULT \'tr\',
            status TEXT NOT NULL DEFAULT \'draft\',
            created_at TEXT DEFAULT (datetime(\'now\')),
            updated_at TEXT,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
        );
        CREATE INDEX IF NOT EXISTS idx_articles_author_id ON articles(author_id);

        CREATE TABLE IF NOT EXISTS article_translations (
            id TEXT PRIMARY KEY,
            article_id TEXT NOT NULL,
            language_id TEXT NOT NULL,
            is_source INTEGER NOT NULL DEFAULT 0,
            sentences_json TEXT NOT NULL DEFAULT \'[]\',
            updated_at TEXT DEFAULT (datetime(\'now\')),
            FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
            UNIQUE(article_id, language_id)
        );
        CREATE INDEX IF NOT EXISTS idx_article_translations_article_id ON article_translations(article_id);
    ', $pdo));
}

function init_schema(PDO $pdo): void
{
    $pdo->exec(adapt_schema_sql('
        CREATE TABLE IF NOT EXISTS users (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            bio TEXT DEFAULT \'\',
            avatar_url TEXT DEFAULT \'\',
            cover_url TEXT DEFAULT \'\',
            birth_date TEXT,
            city TEXT DEFAULT \'\',
            website TEXT DEFAULT \'\',
            password_hash TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime(\'now\'))
        );

        CREATE TABLE IF NOT EXISTS posts (
            id TEXT PRIMARY KEY,
            author_id TEXT NOT NULL,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            image_path TEXT DEFAULT \'\',
            media_type TEXT DEFAULT \'\',
            status TEXT NOT NULL DEFAULT \'published\',
            created_at TEXT DEFAULT (datetime(\'now\')),
            updated_at TEXT,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS tags (
            id TEXT PRIMARY KEY,
            name TEXT UNIQUE NOT NULL
        );

        CREATE TABLE IF NOT EXISTS post_tags (
            post_id TEXT NOT NULL,
            tag_id TEXT NOT NULL,
            PRIMARY KEY (post_id, tag_id),
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS likes (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            post_id TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime(\'now\')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            UNIQUE(user_id, post_id)
        );

        CREATE TABLE IF NOT EXISTS comments (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            post_id TEXT NOT NULL,
            parent_id TEXT,
            content TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime(\'now\')),
            edited_at TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS follows (
            id TEXT PRIMARY KEY,
            follower_id TEXT NOT NULL,
            following_id TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime(\'now\')),
            FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(follower_id, following_id)
        );

        CREATE TABLE IF NOT EXISTS password_resets (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            token_hash TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime(\'now\')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS login_attempts (
            identifier TEXT PRIMARY KEY,
            attempts INTEGER NOT NULL DEFAULT 0,
            locked_until TEXT
        );

        CREATE TABLE IF NOT EXISTS notifications (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL,
            actor_id TEXT NOT NULL,
            type TEXT NOT NULL,
            post_id TEXT,
            created_at TEXT DEFAULT (datetime(\'now\')),
            read_at TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS friend_requests (
            id TEXT PRIMARY KEY,
            sender_id TEXT NOT NULL,
            receiver_id TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'pending\',
            created_at TEXT DEFAULT (datetime(\'now\')),
            responded_at TEXT,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(sender_id, receiver_id)
        );

        CREATE TABLE IF NOT EXISTS conversations (
            id TEXT PRIMARY KEY,
            user_one_id TEXT NOT NULL,
            user_two_id TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime(\'now\')),
            updated_at TEXT DEFAULT (datetime(\'now\')),
            FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(user_one_id, user_two_id)
        );

        CREATE TABLE IF NOT EXISTS messages (
            id TEXT PRIMARY KEY,
            conversation_id TEXT NOT NULL,
            sender_id TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime(\'now\')),
            edited_at TEXT,
            deleted_at TEXT,
            read_at TEXT,
            FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE INDEX IF NOT EXISTS idx_posts_author_id ON posts(author_id);
        CREATE INDEX IF NOT EXISTS idx_friend_requests_receiver ON friend_requests(receiver_id);
        CREATE INDEX IF NOT EXISTS idx_friend_requests_sender ON friend_requests(sender_id);
        CREATE INDEX IF NOT EXISTS idx_conversations_user_one ON conversations(user_one_id);
        CREATE INDEX IF NOT EXISTS idx_conversations_user_two ON conversations(user_two_id);
        CREATE INDEX IF NOT EXISTS idx_messages_conversation_id ON messages(conversation_id);
        CREATE INDEX IF NOT EXISTS idx_post_tags_tag_id ON post_tags(tag_id);
        CREATE INDEX IF NOT EXISTS idx_likes_post_id ON likes(post_id);
        CREATE INDEX IF NOT EXISTS idx_likes_user_id ON likes(user_id);
        CREATE INDEX IF NOT EXISTS idx_comments_post_id ON comments(post_id);
        CREATE INDEX IF NOT EXISTS idx_comments_user_id ON comments(user_id);
        CREATE INDEX IF NOT EXISTS idx_comments_parent_id ON comments(parent_id);
        CREATE INDEX IF NOT EXISTS idx_follows_follower_id ON follows(follower_id);
        CREATE INDEX IF NOT EXISTS idx_follows_following_id ON follows(following_id);
        CREATE INDEX IF NOT EXISTS idx_password_resets_user_id ON password_resets(user_id);
        CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id);
    ', $pdo));
}

function uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_save_handler(new DbSessionHandler(db()), true);
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => IS_PRODUCTION,
    ]);
    session_start();
}
