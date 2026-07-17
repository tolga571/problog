<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();

$error = flash_get('error');
$old = flash_old('edit_profile');
$name = $old['name'] ?? $user['name'];
$bio = $old['bio'] ?? $user['bio'];
$city = $old['city'] ?? ($user['city'] ?? '');
$birthDate = $old['birth_date'] ?? ($user['birth_date'] ?? '');
$website = $old['website'] ?? ($user['website'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = trim((string) ($_POST['name'] ?? ''));
    $bio = trim((string) ($_POST['bio'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $birthDate = trim((string) ($_POST['birth_date'] ?? ''));
    $website = normalize_website((string) ($_POST['website'] ?? ''));

    $fieldsForRetry = ['name' => $name, 'bio' => $bio, 'city' => $city, 'birth_date' => $birthDate, 'website' => $website];

    if ($name === '' || mb_strlen($name) > 80) {
        flash_set('error', 'Ad 1-80 karakter arasında olmalı.');
        flash_set_old($fieldsForRetry);
        redirect('/edit_profile.php');
    }

    if (mb_strlen($bio) > 280) {
        flash_set('error', 'Biyografi en fazla 280 karakter olabilir.');
        flash_set_old($fieldsForRetry);
        redirect('/edit_profile.php');
    }

    if (mb_strlen($city) > 80) {
        flash_set('error', 'Şehir en fazla 80 karakter olabilir.');
        flash_set_old($fieldsForRetry);
        redirect('/edit_profile.php');
    }

    if ($birthDate !== '') {
        $parsed = DateTime::createFromFormat('Y-m-d', $birthDate);
        if (!$parsed || $parsed->format('Y-m-d') !== $birthDate || $parsed > new DateTime()) {
            flash_set('error', 'Geçerli bir doğum tarihi gir.');
            flash_set_old($fieldsForRetry);
            redirect('/edit_profile.php');
        }
    }

    if ($website !== '' && (mb_strlen($website) > 200 || !filter_var($website, FILTER_VALIDATE_URL))) {
        flash_set('error', 'Geçerli bir web sitesi adresi gir.');
        flash_set_old($fieldsForRetry);
        redirect('/edit_profile.php');
    }

    try {
        $avatarPath = handle_image_upload($_FILES['avatar'] ?? [], 'avatars');
        $coverPath = handle_image_upload($_FILES['cover'] ?? [], 'covers');
    } catch (UploadException $e) {
        flash_set('error', $e->getMessage());
        flash_set_old($fieldsForRetry);
        redirect('/edit_profile.php');
    }

    $birthDateValue = $birthDate !== '' ? $birthDate : null;

    $pdo = db();
    $pdo->prepare('UPDATE users SET name = ?, bio = ?, city = ?, birth_date = ?, website = ? WHERE id = ?')
        ->execute([$name, $bio, $city, $birthDateValue, $website, $user['id']]);

    if ($avatarPath) {
        $pdo->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')->execute([$avatarPath, $user['id']]);
    }
    if ($coverPath) {
        $pdo->prepare('UPDATE users SET cover_url = ? WHERE id = ?')->execute([$coverPath, $user['id']]);
    }

    redirect('/profile.php?id=' . urlencode($user['id']));
}

$pageTitle = 'Profili Düzenle - ProBlog';
$activePage = 'profile';
require __DIR__ . '/includes/layout_head.php';
?>

  <main class="flex-1 max-w-[680px] mx-auto w-full feed-border min-h-screen">
    <header class="sticky top-0 z-40 glass h-14 flex items-center px-4 gap-4">
      <a href="/profile.php?id=<?= h($user['id']) ?>" class="p-1 hover:bg-surface-3 rounded-full transition-colors">
        <span class="material-symbols-outlined">arrow_back</span>
      </a>
      <h1 class="font-bold">Profili Düzenle</h1>
    </header>

    <div class="p-4">
      <div class="card p-5 sm:p-6 max-w-lg">
        <form method="post" action="/edit_profile.php" enctype="multipart/form-data" class="space-y-5">
          <?= csrf_field() ?>

          <div>
            <label class="block text-sm font-medium text-muted mb-1.5">Kapak fotoğrafı</label>
            <div class="relative w-full h-56 rounded-xl overflow-hidden bg-surface-3">
              <?= render_cover($user) ?>
              <label class="absolute inset-0 flex items-center justify-center gap-2 bg-surface-1/50 opacity-0 hover:opacity-100 transition-opacity cursor-pointer text-sm font-semibold text-white">
                <span class="material-symbols-outlined text-lg">photo_camera</span>
                <span class="cover-file-label">Kapak fotoğrafı seç</span>
                <input type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" onchange="this.closest('label').querySelector('.cover-file-label').textContent = this.files[0] ? this.files[0].name : 'Kapak fotoğrafı seç'" />
              </label>
            </div>
          </div>

          <div class="flex items-center gap-4">
            <?= render_avatar($user, 'avatar-lg') ?>
            <div class="flex-1">
              <label class="block text-sm font-medium text-muted mb-1.5">Profil fotoğrafı</label>
              <label class="btn-outline text-sm inline-flex items-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-lg">upload</span>
                <span class="avatar-file-label">Dosya seç</span>
                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" onchange="this.closest('label').querySelector('.avatar-file-label').textContent = this.files[0] ? this.files[0].name : 'Dosya seç'" />
              </label>
            </div>
          </div>

          <div>
            <label for="name-field" class="block text-sm font-medium text-muted mb-1.5">Ad Soyad</label>
            <input id="name-field" class="input-field" name="name" value="<?= h($name) ?>" maxlength="80" required />
          </div>

          <div>
            <label for="bio-field" class="block text-sm font-medium text-muted mb-1.5">Biyografi</label>
            <textarea id="bio-field" class="input-field resize-none" name="bio" rows="3" maxlength="280"><?= h($bio) ?></textarea>
          </div>

          <div class="field-grid gap-4">
            <div>
              <label for="city-field" class="block text-sm font-medium text-muted mb-1.5">Şehir</label>
              <input id="city-field" class="input-field" name="city" value="<?= h($city) ?>" maxlength="80" placeholder="Örnek: İstanbul" />
            </div>
            <div>
              <label for="birth-date-field" class="block text-sm font-medium text-muted mb-1.5">Doğum tarihi</label>
              <input id="birth-date-field" class="input-field" type="date" name="birth_date" value="<?= h($birthDate) ?>" min="1900-01-01" max="<?= h(date('Y-m-d')) ?>" />
            </div>
          </div>

          <div>
            <label for="website-field" class="block text-sm font-medium text-muted mb-1.5">Web sitesi</label>
            <input id="website-field" class="input-field" type="url" name="website" value="<?= h($website) ?>" maxlength="200" placeholder="ornek.com" />
          </div>

          <?php if ($error): ?>
            <p class="text-red-400 text-sm bg-red-500/10 border border-red-500/20 px-4 py-3 rounded-xl flex items-center gap-2">
              <span class="material-symbols-outlined text-lg">error</span>
              <?= h($error) ?>
            </p>
          <?php endif; ?>

          <div class="flex gap-3">
            <button type="submit" class="btn-primary">Kaydet</button>
            <a href="/profile.php?id=<?= h($user['id']) ?>" class="btn-outline">Vazgeç</a>
          </div>
        </form>
      </div>
    </div>
  </main>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
