<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('index.php');

// Fetch submission
$stmt = $pdo->prepare("
    SELECT s.*, u.username, c.name AS category_name, c.slug AS category_slug
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    JOIN categories c ON s.category_id = c.id
    WHERE s.id = ? AND s.deleted_at IS NULL AND u.deleted_at IS NULL AND c.deleted_at IS NULL
");
$stmt->execute([$id]);
$sub = $stmt->fetch();

if (!$sub) {
    flash('error', 'Submission not found.');
    redirect('index.php');
}

// Only show non-approved to the submitter or admin
if ($sub['status'] !== 'approved' && (!is_logged_in() || (current_user_id() !== $sub['user_id'] && !is_admin()))) {
    flash('error', 'Submission not found.');
    redirect('index.php');
}

// Handle comment post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_body'])) {
    require_login();
    if (!verify_csrf()) {
        flash('error', 'Invalid form submission.');
    } else {
        $body = trim($_POST['comment_body'] ?? '');
        if (strlen($body) < 1) {
            flash('error', 'Comment cannot be empty.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO comments (user_id, submission_id, body) VALUES (?, ?, ?)');
            $stmt->execute([current_user_id(), $id, $body]);
            flash('success', 'Comment posted!');
        }
    }
    redirect('submission.php?id=' . $id);
}

// Handle comment delete
if (isset($_GET['delete_comment'])) {
    require_login();
    $commentId = (int)$_GET['delete_comment'];
    $stmt = $pdo->prepare('SELECT * FROM comments WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();
    if ($comment && (current_user_id() === $comment['user_id'] || is_admin())) {
        soft_delete($pdo, 'comments', $commentId);
        flash('success', 'Comment deleted.');
    }
    redirect('submission.php?id=' . $id);
}

$netVotes = get_vote_score($pdo, $id);
$userVote = get_user_vote($pdo, $id, current_user_id());

// Get comments
$commentStmt = $pdo->prepare("
    SELECT cm.*, u.username
    FROM comments cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.submission_id = ? AND cm.deleted_at IS NULL AND u.deleted_at IS NULL
    ORDER BY cm.created_at ASC
");
$commentStmt->execute([$id]);
$comments = $commentStmt->fetchAll();

$pageTitle = $sub['title'];
require_once 'includes/header.php';
?>

<div class="submission-detail">
    <?php if ($sub['status'] !== 'approved'): ?>
        <span class="badge badge-<?= $sub['status'] ?>" style="margin-bottom: 12px; display: inline-block;"><?= ucfirst($sub['status']) ?></span>
    <?php endif; ?>

    <h1><?= sanitize($sub['title']) ?></h1>
    <div class="submission-url">
        <a href="<?= sanitize($sub['url']) ?>" target="_blank" rel="noopener"><?= sanitize(get_domain($sub['url'])) ?> &rarr;</a>
    </div>

    <div class="detail-vote-box">
        <button class="vote-btn upvote <?= $userVote === 1 ? 'active' : '' ?>"
                data-id="<?= $sub['id'] ?>" data-type="1">&#9650;</button>
        <span class="vote-score" data-id="<?= $sub['id'] ?>"><?= $netVotes ?></span>
        <button class="vote-btn downvote <?= $userVote === -1 ? 'active' : '' ?>"
                data-id="<?= $sub['id'] ?>" data-type="-1">&#9660;</button>
    </div>

    <div class="submission-desc"><?= nl2br(sanitize($sub['description'])) ?></div>

    <div class="submission-meta">
        <span class="badge badge-primary"><?= sanitize($sub['category_name']) ?></span>
        <span>by <a href="profile.php?id=<?= $sub['user_id'] ?>"><?= sanitize($sub['username']) ?></a></span>
        <span><?= time_ago($sub['created_at']) ?></span>
        <?php if (is_logged_in()): ?>
            <a href="report.php?submission_id=<?= $sub['id'] ?>">Report</a>
        <?php endif; ?>
    </div>
</div>

<div class="comments-section">
    <h2><?= count($comments) ?> Comment<?= count($comments) !== 1 ? 's' : '' ?></h2>

    <?php foreach ($comments as $c): ?>
        <div class="comment">
            <div class="comment-header">
                <span><strong><a href="profile.php?id=<?= $c['user_id'] ?>"><?= sanitize($c['username']) ?></a></strong> &middot; <?= time_ago($c['created_at']) ?></span>
                <div class="comment-actions">
                    <?php if (is_logged_in()): ?>
                        <a href="report.php?comment_id=<?= $c['id'] ?>" style="margin-right: 8px;">Report</a>
                    <?php endif; ?>
                    <?php if (is_logged_in() && (current_user_id() === $c['user_id'] || is_admin())): ?>
                        <a href="submission.php?id=<?= $id ?>&delete_comment=<?= $c['id'] ?>"
                           onclick="return confirm('Delete this comment?')"
                           style="color: var(--color-error);">Delete</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="comment-body"><?= nl2br(sanitize($c['body'])) ?></div>
        </div>
    <?php endforeach; ?>

    <?php if (is_logged_in()): ?>
        <div class="comment-form">
            <form method="POST">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="comment_body">Leave a comment</label>
                    <textarea id="comment_body" name="comment_body" required placeholder="Share your thoughts..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Post Comment</button>
            </form>
        </div>
    <?php else: ?>
        <p style="margin-top: 16px; color: var(--color-text-muted);"><a href="login.php">Log in</a> to leave a comment.</p>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
