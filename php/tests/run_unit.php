<?php

declare(strict_types=1);

/**
 * Bagimsiz, harici kutuphane gerektirmeyen birim test kosucusu.
 * Calistir: php tests/run_unit.php
 * Gercek data/problog.sqlite dosyasina hic dokunmaz, gecici bir DB kullanir.
 */

$testDbPath = sys_get_temp_dir() . '/problog_test_' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('DB_PATH=' . $testDbPath);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$failures = [];
$passed = 0;

function check(string $label, bool $condition): void
{
    global $failures, $passed;
    if ($condition) {
        $passed++;
        echo "  ok   - {$label}\n";
    } else {
        $failures[] = $label;
        echo "  FAIL - {$label}\n";
    }
}

echo "ProBlog birim testleri\n";
echo "Test DB: {$testDbPath}\n\n";

$pdo = db(); // sema burada olusturulur

echo "uuid()\n";
$a = uuid();
$b = uuid();
check('benzersiz uretiyor', $a !== $b);
check('formati dogru (36 karakter)', strlen($a) === 36);

echo "initials()\n";
check('tek kelime', initials('Ahmet') === 'A');
check('iki kelime', initials('Ahmet Yilmaz') === 'AY');
check('fazladan bosluklari yok sayiyor', initials('  Ahmet   Yilmaz  ') === 'AY');

echo "time_ago()\n";
check('az once', time_ago(gmdate('Y-m-d\TH:i:s\Z')) === 'Az once');

echo "h() (XSS kacislama)\n";
check('ozel karakterleri kacisliyor', h('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;');

echo "login hiz sinirlama\n";
$email = 'ratelimit@unit.local';
check('baslangicta kilitli degil', login_locked_seconds($email) === null);
for ($i = 0; $i < 5; $i++) {
    login_register_failure($email);
}
check('5 basarisiz denemeden sonra kilitleniyor', login_locked_seconds($email) !== null);
login_clear_attempts($email);
check('basarili giristen sonra kilit temizleniyor', login_locked_seconds($email) === null);

echo "bildirimler\n";
$userId = uuid();
$actorId = uuid();
$pdo->prepare('INSERT INTO users (id, name, email, bio, password_hash) VALUES (?, ?, ?, ?, ?)')
    ->execute([$userId, 'Alici Kullanici', 'alici@unit.local', '', password_hash('sifre123', PASSWORD_DEFAULT)]);
$pdo->prepare('INSERT INTO users (id, name, email, bio, password_hash) VALUES (?, ?, ?, ?, ?)')
    ->execute([$actorId, 'Aktor Kullanici', 'aktor@unit.local', '', password_hash('sifre123', PASSWORD_DEFAULT)]);

check('baslangicta bildirim yok', unread_notifications_count($userId) === 0);
create_notification($userId, $actorId, 'follow');
check('bildirim olusuyor', unread_notifications_count($userId) === 1);
create_notification($userId, $userId, 'like');
check('kendine bildirim gitmiyor', unread_notifications_count($userId) === 1);

echo "akis filtreleri (takip + taslak)\n";
$publishedId = uuid();
$pdo->prepare("INSERT INTO posts (id, author_id, title, content, status) VALUES (?, ?, ?, ?, 'published')")
    ->execute([$publishedId, $actorId, 'Aktorun yazisi', str_repeat('x', 25)]);

check('herkes akisinda gorunuyor', count(fetch_feed_posts('all', $userId, 20, 0)) === 1);
check('takip edilmeyen kullanicinin yazisi takip akisinda yok', count(fetch_feed_posts('following', $userId, 20, 0)) === 0);

$pdo->prepare('INSERT INTO follows (id, follower_id, following_id) VALUES (?, ?, ?)')
    ->execute([uuid(), $userId, $actorId]);
check('takip ettikten sonra takip akisinda gorunuyor', count(fetch_feed_posts('following', $userId, 20, 0)) === 1);

$draftId = uuid();
$pdo->prepare("INSERT INTO posts (id, author_id, title, content, status) VALUES (?, ?, ?, ?, 'draft')")
    ->execute([$draftId, $actorId, 'Taslak yazi', str_repeat('y', 25)]);
check('taslak herkes akisinda gorunmuyor', count(fetch_feed_posts('all', $userId, 20, 0)) === 1);

echo "etiketler\n";
sync_post_tags($publishedId, 'PHP, Guvenlik, php, ');
$stmt = $pdo->prepare('SELECT t.name FROM tags t JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name');
$stmt->execute([$publishedId]);
check('kucuk harfe cevrilip tekillestiriliyor', array_column($stmt->fetchAll(), 'name') === ['guvenlik', 'php']);

echo "\n";
unset($pdo, $stmt);
db(true); // PDO baglantisini kapat, Windows'ta dosya kilidi kalkmadan silinemiyor
gc_collect_cycles();
unlink($testDbPath);
foreach (['-wal', '-shm'] as $suffix) {
    if (file_exists($testDbPath . $suffix)) {
        unlink($testDbPath . $suffix);
    }
}

if (count($failures) === 0) {
    echo "TUMU BASARILI ({$passed} test)\n";
    exit(0);
}

echo "BASARISIZ: " . count($failures) . " test basarisiz, {$passed} basarili\n";
foreach ($failures as $f) {
    echo "  - {$f}\n";
}
exit(1);
