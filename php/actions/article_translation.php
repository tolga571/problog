<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_login();

function load_owned_article(string $articleId, string $userId): array
{
    $stmt = db()->prepare('SELECT * FROM articles WHERE id = ? AND author_id = ?');
    $stmt->execute([$articleId, $userId]);
    $article = $stmt->fetch();
    if (!$article) {
        json_response(['message' => 'Makale bulunamadı.'], 404);
    }
    return $article;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $articleId = (string) ($_GET['article_id'] ?? '');
    $languageId = (string) ($_GET['language_id'] ?? '');

    if (!isset(ARTICLE_LANGUAGES[$languageId])) {
        json_response(['message' => 'Geçersiz dil.'], 400);
    }

    $article = load_owned_article($articleId, $user['id']);
    $isSource = $languageId === $article['source_language'];

    $sourceTranslation = get_article_translation($articleId, $article['source_language']);
    $sourceSentences = $sourceTranslation['sentences'] ?? [];

    $translation = get_article_translation($articleId, $languageId);
    if ($translation) {
        $sentences = $translation['sentences'];
    } elseif ($isSource) {
        $sentences = $sourceSentences;
    } else {
        // Bu dil için ilk kez açılıyor: kaynak cümle sayısı ve sırasıyla boş satırlar oluştur.
        $sentences = array_map(
            fn(array $s) => ['code' => $s['code'], 'sort' => $s['sort'], 'p' => $s['p'], 'text' => '', 'note' => ''],
            $sourceSentences
        );
    }

    json_response([
        'article_id' => $articleId,
        'language_id' => $languageId,
        'is_source' => $isSource,
        'source_language' => $article['source_language'],
        'source_sentences' => $sourceSentences,
        'sentences' => $sentences,
        'percent' => $isSource ? 100 : translation_percent($sourceSentences, $sentences),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $articleId = (string) ($body['article_id'] ?? '');
    $languageId = (string) ($body['language_id'] ?? '');
    $sentences = is_array($body['sentences'] ?? null) ? $body['sentences'] : [];

    if (!isset(ARTICLE_LANGUAGES[$languageId])) {
        json_response(['message' => 'Geçersiz dil.'], 400);
    }

    $article = load_owned_article($articleId, $user['id']);
    $isSource = $languageId === $article['source_language'];

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
        ];
    }

    $sourceTranslation = get_article_translation($articleId, $article['source_language']);
    $sourceSentences = $sourceTranslation['sentences'] ?? [];

    if ($isSource) {
        // Kaynak sekmede sadece not alanı düzenlenebilir; metin/sıra/kod kaynağın kendisinden gelir.
        $notesByCode = [];
        foreach ($clean as $s) {
            $notesByCode[$s['code']] = $s['note'];
        }
        $saved = array_map(
            fn(array $s) => [...$s, 'note' => $notesByCode[$s['code']] ?? ($s['note'] ?? '')],
            $sourceSentences
        );
    } else {
        $saved = $clean;
    }

    $saved = save_article_translation($articleId, $languageId, $saved, $isSource);

    json_response([
        'ok' => true,
        'percent' => $isSource ? 100 : translation_percent($sourceSentences, $saved),
    ]);
}

json_response(['message' => 'Desteklenmeyen yöntem.'], 405);
