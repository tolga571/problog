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

$title = trim((string) ($_POST['title'] ?? ''));
$content = normalize_content((string) ($_POST['content'] ?? ''));
$sourceLanguage = (string) ($_POST['source_language'] ?? 'tr');
$status = ($_POST['status'] ?? '') === 'draft' ? 'draft' : 'published';

if (!isset(ARTICLE_LANGUAGES[$sourceLanguage])) {
    $sourceLanguage = 'tr';
}

$oldData = ['title' => $title, 'content' => $content, 'source_language' => $sourceLanguage];

if (mb_strlen($title) < 4 || mb_strlen($content) < 20) {
    flash_set('composer_error', 'Başlık en az 4, içerik en az 20 karakter olmalı.');
    flash_set_old($oldData);
    redirect('/new_article.php');
}

if (mb_strlen($title) > 120 || mb_strlen($content) > 100000) {
    flash_set('composer_error', 'Başlık en fazla 120 karakter olabilir, içerik çok uzun.');
    flash_set_old($oldData);
    redirect('/new_article.php');
}

$articleId = uuid();
db()->prepare('INSERT INTO articles (id, author_id, title, source_language, status) VALUES (?, ?, ?, ?, ?)')
    ->execute([$articleId, $user['id'], $title, $sourceLanguage, $status]);

$sentences = split_into_sentences($content);
save_article_translation($articleId, $sourceLanguage, $sentences, true);

redirect('/edit_article.php?id=' . $articleId);
