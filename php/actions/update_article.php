<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/articles.php');
}

verify_csrf();

$articleId = (string) ($_POST['article_id'] ?? '');

$stmt = db()->prepare('SELECT * FROM articles WHERE id = ? AND author_id = ?');
$stmt->execute([$articleId, $user['id']]);
$article = $stmt->fetch();

if (!$article) {
    flash_set('error', 'Makale bulunamadı.');
    redirect('/articles.php');
}

$title = trim((string) ($_POST['title'] ?? ''));
$content = normalize_content((string) ($_POST['content'] ?? ''));
$status = ($_POST['status'] ?? '') === 'draft' ? 'draft' : 'published';

if (mb_strlen($title) < 4 || mb_strlen($content) < 20 || mb_strlen($title) > 120 || mb_strlen($content) > 100000) {
    flash_set('error', 'Başlık en az 4 en fazla 120, içerik en az 20 en fazla 100000 karakter olmalı.');
    redirect('/edit_article.php?id=' . $articleId);
}

db()->prepare("UPDATE articles SET title = ?, status = ?, updated_at = datetime('now') WHERE id = ?")
    ->execute([$title, $status, $articleId]);

$existing = get_article_translation($articleId, $article['source_language']);
$notesByText = [];
if ($existing) {
    foreach ($existing['sentences'] as $sentence) {
        $notesByText[trim((string) ($sentence['text'] ?? ''))] = (string) ($sentence['note'] ?? '');
    }
}

$sentences = split_into_sentences($content);
foreach ($sentences as &$sentence) {
    $sentence['note'] = $notesByText[trim($sentence['text'])] ?? '';
}
unset($sentence);

save_article_translation($articleId, $article['source_language'], $sentences, true);

flash_set('info', 'Makale güncellendi.');
redirect('/edit_article.php?id=' . $articleId);
