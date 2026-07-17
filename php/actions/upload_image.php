<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = current_user();
if (!$user) {
    json_response(['message' => 'Oturum gerekli.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['message' => 'Method not allowed.'], 405);
}

verify_csrf();

try {
    $path = handle_image_upload($_FILES['file'] ?? [], 'post-images');
} catch (UploadException $e) {
    json_response(['message' => $e->getMessage()], 400);
}

if (!$path) {
    json_response(['message' => 'Dosya seçilmedi.'], 400);
}

json_response(['url' => $path]);
