<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_login();

$errors = [];
$title = '';
$url = '';
$description = '';
$category_id = '';

$categories = get_categories($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid form submission.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);

        if (strlen($title) < 3 || strlen($title) > 255) {
            $errors[] = 'Title must be 3-255 characters.';
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || strlen($url) > 500) {
            $errors[] = 'Please enter a valid URL.';
        }
        if (strlen($description) < 10) {
            $errors[] = 'Description must be at least 10 characters.';
        }
        if ($category_id <= 0) {
            $errors[] = 'Please select a category.';
        }

        // Check duplicate URL among approved submissions
        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM submissions WHERE url = ? AND status = ?');
            $stmt->execute([$url, 'approved']);
            if ($stmt->fetch()) {
                $errors[] = 'This URL has already been submitted and approved.';
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO submissions (user_id, category_id, title, url, description) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([current_user_id(), $category_id, $title, $url, $description]);
            flash('success', 'Your submission is pending admin approval. Thanks!');
            redirect('index.php');
        }
    }
}

$pageTitle = 'Submit a Link';
require_once 'includes/header.php';
?>

<div class="form-card">
    <h1>Submit a Link</h1>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <?php foreach ($errors as $e): ?>
                <p><?= sanitize($e) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= sanitize($title) ?>" required minlength="3" maxlength="255" placeholder="e.g. VS Code — Free code editor by Microsoft">
        </div>
        <div class="form-group">
            <label for="url">URL</label>
            <input type="url" id="url" name="url" value="<?= sanitize($url) ?>" required maxlength="500" placeholder="https://example.com">
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" required minlength="10" placeholder="Tell us what makes this resource great..."><?= sanitize($description) ?></textarea>
        </div>
        <div class="form-group">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" required>
                <option value="">Select a category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                        <?= sanitize($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-full">Submit</button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
