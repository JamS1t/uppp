<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_login();

$submissionId = (int)($_GET['submission_id'] ?? $_POST['submission_id'] ?? 0);
$commentId = (int)($_GET['comment_id'] ?? $_POST['comment_id'] ?? 0);

// Must target exactly one
if (($submissionId <= 0 && $commentId <= 0) || ($submissionId > 0 && $commentId > 0)) {
    flash('error', 'Invalid report target.');
    redirect('index.php');
}

// Fetch the item being reported
if ($submissionId > 0) {
    $stmt = $pdo->prepare('SELECT s.title, s.url FROM submissions s WHERE s.id = ?');
    $stmt->execute([$submissionId]);
    $item = $stmt->fetch();
    $itemLabel = 'Submission: ' . ($item['title'] ?? 'Unknown');
    $backUrl = 'submission.php?id=' . $submissionId;
} else {
    $stmt = $pdo->prepare('SELECT cm.body, cm.submission_id FROM comments cm WHERE cm.id = ?');
    $stmt->execute([$commentId]);
    $item = $stmt->fetch();
    $itemLabel = 'Comment: "' . mb_substr($item['body'] ?? '', 0, 80) . '..."';
    $backUrl = 'submission.php?id=' . ($item['submission_id'] ?? 0);
}

if (!$item) {
    flash('error', 'Item not found.');
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid form submission.';
    } else {
        $reason = trim($_POST['reason'] ?? '');
        if (strlen($reason) < 5) {
            $errors[] = 'Please provide a reason (at least 5 characters).';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO reports (user_id, submission_id, comment_id, reason) VALUES (?, ?, ?, ?)');
            $stmt->execute([
                current_user_id(),
                $submissionId > 0 ? $submissionId : null,
                $commentId > 0 ? $commentId : null,
                $reason,
            ]);
            flash('success', 'Report submitted. Thank you.');
            redirect($backUrl);
        }
    }
}

$pageTitle = 'Report';
require_once 'includes/header.php';
?>

<div class="form-card">
    <h1>Report Content</h1>
    <p style="color: var(--color-text-muted); margin-bottom: 16px;"><?= sanitize($itemLabel) ?></p>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <?php foreach ($errors as $e): ?>
                <p><?= sanitize($e) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <?php if ($submissionId): ?>
            <input type="hidden" name="submission_id" value="<?= $submissionId ?>">
        <?php else: ?>
            <input type="hidden" name="comment_id" value="<?= $commentId ?>">
        <?php endif; ?>
        <div class="form-group">
            <label for="reason">Why are you reporting this?</label>
            <textarea id="reason" name="reason" required minlength="5" placeholder="Spam, inappropriate content, broken link, etc."></textarea>
        </div>
        <button type="submit" class="btn btn-danger btn-full">Submit Report</button>
    </form>
    <p class="auth-link"><a href="<?= sanitize($backUrl) ?>">Cancel</a></p>
</div>

<?php require_once 'includes/footer.php'; ?>
