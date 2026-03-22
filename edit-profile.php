<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_login();

$user = current_user($pdo);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid form submission.';
    } else {
        $bio = trim($_POST['bio'] ?? '');
        $website_url = trim($_POST['website_url'] ?? '');

        if ($website_url && !filter_var($website_url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Please enter a valid website URL.';
        }
        if (strlen($website_url) > 255) {
            $errors[] = 'Website URL is too long.';
        }

        // Handle avatar upload
        $avatarPath = $user['avatar_path'];
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];

            if ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Avatar must be under 2MB.';
            } else {
                $imageInfo = getimagesize($file['tmp_name']);
                if (!$imageInfo || !in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF])) {
                    $errors[] = 'Avatar must be a JPG, PNG, or GIF image.';
                } else {
                    $ext = match ($imageInfo[2]) {
                        IMAGETYPE_JPEG => 'jpg',
                        IMAGETYPE_PNG => 'png',
                        IMAGETYPE_GIF => 'gif',
                    };
                    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
                    $destPath = 'assets/uploads/avatars/' . $newName;

                    // Resize to max 200x200 using GD
                    $srcImage = match ($imageInfo[2]) {
                        IMAGETYPE_JPEG => imagecreatefromjpeg($file['tmp_name']),
                        IMAGETYPE_PNG => imagecreatefrompng($file['tmp_name']),
                        IMAGETYPE_GIF => imagecreatefromgif($file['tmp_name']),
                    };

                    $srcW = $imageInfo[0];
                    $srcH = $imageInfo[1];
                    $maxDim = 200;

                    if ($srcW > $maxDim || $srcH > $maxDim) {
                        $ratio = min($maxDim / $srcW, $maxDim / $srcH);
                        $newW = (int)($srcW * $ratio);
                        $newH = (int)($srcH * $ratio);
                        $dstImage = imagecreatetruecolor($newW, $newH);

                        // Preserve transparency for PNG/GIF
                        if ($imageInfo[2] === IMAGETYPE_PNG || $imageInfo[2] === IMAGETYPE_GIF) {
                            imagecolortransparent($dstImage, imagecolorallocatealpha($dstImage, 0, 0, 0, 127));
                            imagealphablending($dstImage, false);
                            imagesavealpha($dstImage, true);
                        }

                        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
                        imagedestroy($srcImage);
                        $srcImage = $dstImage;
                    }

                    match ($ext) {
                        'jpg' => imagejpeg($srcImage, $destPath, 85),
                        'png' => imagepng($srcImage, $destPath),
                        'gif' => imagegif($srcImage, $destPath),
                    };
                    imagedestroy($srcImage);

                    // Delete old avatar
                    if ($avatarPath && file_exists($avatarPath)) {
                        unlink($avatarPath);
                    }
                    $avatarPath = $destPath;
                }
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('UPDATE users SET bio = ?, website_url = ?, avatar_path = ? WHERE id = ?');
            $stmt->execute([$bio, $website_url ?: null, $avatarPath, current_user_id()]);
            flash('success', 'Profile updated!');
            redirect('profile.php?id=' . current_user_id());
        }
    }
}

$pageTitle = 'Edit Profile';
require_once 'includes/header.php';
?>

<div class="form-card">
    <h1>Edit Profile</h1>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <?php foreach ($errors as $e): ?>
                <p><?= sanitize($e) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="avatar">Avatar</label>
            <?php if ($user['avatar_path']): ?>
                <img src="<?= sanitize($user['avatar_path']) ?>" alt="Current avatar" style="width:64px;height:64px;border-radius:50%;object-fit:cover;margin-bottom:8px;display:block;">
            <?php endif; ?>
            <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif">
            <small style="color: var(--color-text-muted);">Max 2MB. JPG, PNG, or GIF.</small>
        </div>
        <div class="form-group">
            <label for="bio">Bio</label>
            <textarea id="bio" name="bio" placeholder="Tell us about yourself..."><?= sanitize($user['bio'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="website_url">Website URL</label>
            <input type="url" id="website_url" name="website_url" value="<?= sanitize($user['website_url'] ?? '') ?>" placeholder="https://yoursite.com">
        </div>
        <button type="submit" class="btn btn-primary btn-full">Save Changes</button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
