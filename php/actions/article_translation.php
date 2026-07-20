<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_login();

function load_owned_article_post(string $postId, string $userId): array
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
    $languageId = (string) ($_GET['language_id'] ?? '');

    if (!isset(ARTICLE_LANGUAGES[$languageId])) {
        json_response(['message' => 'Geçersiz dil.'], 400);
    }

    $post = load_owned_article_post($postId, $user['id']);
    $isSource = $languageId === $post['source_language'];

    $sourceTranslation = get_post_translation($postId, $post['source_language']);
    $sourceSentences = $sourceTranslation['sentences'] ?? [];

    $translation = get_post_translation($postId, $languageId);
    if ($isSource) {
        $sentences = $sourceSentences;
    } else {
        // Kaynaktaki her cümle icin (varsa) mevcut ceviriyi kullan, yoksa bos satir ac.
        // Boylece kaynak icerik sonradan duzenlenip yeni cumle eklendiginde/silindiginde
        // ceviri listesi kaynakla senkron kalir.
        $existingByCode = [];
        foreach ($translation['sentences'] ?? [] as $s) {
            $existingByCode[$s['code']] = $s;
        }
        $sentences = array_map(
            fn(array $s) => $existingByCode[$s['code']] ?? ['code' => $s['code'], 'sort' => $s['sort'], 'p' => $s['p'], 'text' => '', 'note' => '', 'meta' => new stdClass()],
            $sourceSentences
        );
    }

    $title = $isSource ? $post['title'] : (string) ($translation['title'] ?? '');

    json_response([
        'article_id' => $postId,
        'language_id' => $languageId,
        'is_source' => $isSource,
        'source_language' => $post['source_language'],
        'source_sentences' => $sourceSentences,
        'sentences' => $sentences,
        'title' => $title,
        'source_title' => $post['title'],
        'percent' => $isSource ? 100 : translation_percent($sourceSentences, $sentences),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $postId = (string) ($body['article_id'] ?? '');
    $languageId = (string) ($body['language_id'] ?? '');
    $sentences = is_array($body['sentences'] ?? null) ? $body['sentences'] : [];

    if (!isset(ARTICLE_LANGUAGES[$languageId])) {
        json_response(['message' => 'Geçersiz dil.'], 400);
    }

    $post = load_owned_article_post($postId, $user['id']);
    $isSource = $languageId === $post['source_language'];

    $clean = [];
    foreach ($sentences as $s) {
        if (!isset($s['code'])) {
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

    $sourceTranslation = get_post_translation($postId, $post['source_language']);
    $sourceSentences = $sourceTranslation['sentences'] ?? [];

    if ($isSource) {
        // Kaynak sekmede sadece not/metadata alanları düzenlenebilir; metin/sıra/kod içerikten (post.content) gelir.
        $notesByCode = [];
        $metaByCode = [];
        foreach ($clean as $s) {
            $notesByCode[$s['code']] = $s['note'];
            $metaByCode[$s['code']] = $s['meta'];
        }
        $saved = array_map(
            fn(array $s) => array_merge($s, [
                'note' => $notesByCode[$s['code']] ?? ($s['note'] ?? ''),
                'meta' => $metaByCode[$s['code']] ?? ($s['meta'] ?? new stdClass()),
            ]),
            $sourceSentences
        );
    } else {
        $saved = $clean;
    }

    $title = $isSource
        ? $post['title']
        : mb_substr(trim((string) ($body['title'] ?? '')), 0, 120);

    $saved = save_post_translation($postId, $languageId, $saved, $isSource, $title);

    json_response([
        'ok' => true,
        'title' => $title,
        'percent' => $isSource ? 100 : translation_percent($sourceSentences, $saved),
    ]);
}

json_response(['message' => 'Desteklenmeyen yöntem.'], 405);
