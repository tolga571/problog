<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_login();

function load_owned_article_for_export(string $postId, string $userId): array
{
    $stmt = db()->prepare("SELECT * FROM posts WHERE id = ? AND author_id = ? AND post_type = 'article'");
    $stmt->execute([$postId, $userId]);
    $post = $stmt->fetch();
    if (!$post) {
        json_response(['message' => 'Makale bulunamadı.'], 404);
    }
    return $post;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $postId = (string) ($_GET['article_id'] ?? '');
    $post = load_owned_article_for_export($postId, $user['id']);

    $stmt = db()->prepare('SELECT language_id, title, sentences_json FROM post_translations WHERE post_id = ?');
    $stmt->execute([$postId]);

    $languages = [];
    foreach ($stmt->fetchAll() as $row) {
        $languages[$row['language_id']] = [
            'title' => $row['title'],
            'sentences' => json_decode((string) $row['sentences_json'], true) ?: [],
        ];
    }

    $payload = [
        'article_id' => $post['id'],
        'title' => $post['title'],
        'source_language' => $post['source_language'],
        'languages' => $languages,
        'exported_at' => now_utc(),
    ];

    $filename = preg_replace('/[^a-z0-9]+/i', '_', $post['title']) ?: 'makale';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . strtolower($filename) . '_ceviriler.json"');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $postId = (string) ($body['article_id'] ?? '');
    $post = load_owned_article_for_export($postId, $user['id']);

    $languages = is_array($body['languages'] ?? null) ? $body['languages'] : [];
    if (!$languages) {
        json_response(['message' => 'Geçersiz JSON: "languages" alanı bulunamadı.'], 400);
    }

    $imported = [];
    foreach ($languages as $languageId => $entry) {
        $languageId = (string) $languageId;
        if (!isset(ARTICLE_LANGUAGES[$languageId]) || !is_array($entry)) {
            continue;
        }

        $isSource = $languageId === $post['source_language'];
        $sentences = is_array($entry['sentences'] ?? null) ? $entry['sentences'] : [];

        $clean = [];
        foreach ($sentences as $s) {
            if (!is_array($s) || !isset($s['code'])) {
                continue;
            }
            $clean[] = [
                'code' => (string) $s['code'],
                'sort' => (int) ($s['sort'] ?? 0),
                'p' => (int) ($s['p'] ?? 0),
                'text' => strip_tags((string) ($s['text'] ?? ''), '<p><br><strong><em><code><a><blockquote><ul><ol><li><pre><h1><h2><h3>'),
                'note' => mb_substr(trim((string) ($s['note'] ?? '')), 0, 300),
                'meta' => clean_sentence_meta($s['meta'] ?? []),
            ];
        }

        $title = $isSource ? $post['title'] : mb_substr(trim((string) ($entry['title'] ?? '')), 0, 120);
        save_post_translation($postId, $languageId, $clean, $isSource, $title);
        $imported[] = $languageId;
    }

    if (!$imported) {
        json_response(['message' => 'İçe aktarılacak geçerli dil bulunamadı.'], 400);
    }

    json_response(['ok' => true, 'imported' => $imported]);
}

json_response(['message' => 'Desteklenmeyen yöntem.'], 405);
