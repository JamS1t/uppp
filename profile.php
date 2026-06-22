<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('index.php');

$stmt = $pdo->prepare('SELECT id, username, bio, avatar_path, website_url, role, created_at FROM users WHERE id = ?');
$stmt->execute([$id]);
$profile = $stmt->fetch();

if (!$profile) {
    flash('error', 'User not found.');
    redirect('index.php');
}

$isOwn = is_logged_in() && current_user_id() === $profile['id'];

// Show all statuses for own profile, only approved for others
if ($isOwn) {
    $countSql = "SELECT COUNT(*) FROM submissions s
                 JOIN categories c ON s.category_id = c.id
                 WHERE s.user_id = ? AND s.deleted_at IS NULL AND c.deleted_at IS NULL";
} else {
    $countSql = "SELECT COUNT(*) FROM submissions s
                 JOIN categories c ON s.category_id = c.id
                 WHERE s.user_id = ? AND s.status = 'approved' AND s.deleted_at IS NULL AND c.deleted_at IS NULL";
}

$pagination = paginate($pdo, $countSql, [$id]);

if ($isOwn) {
    $statusFilter = "";
} else {
    $statusFilter = "AND s.status = 'approved'";
}

$stmt = $pdo->prepare("
    SELECT s.*, c.name AS category_name, c.slug AS category_slug,
           COALESCE(SUM(v.vote_type), 0) AS net_votes,
           (SELECT COUNT(*) FROM comments cm WHERE cm.submission_id = s.id AND cm.deleted_at IS NULL) AS comment_count
    FROM submissions s
    JOIN categories c ON s.category_id = c.id
    LEFT JOIN votes v ON v.submission_id = s.id AND v.deleted_at IS NULL
    WHERE s.user_id = ? AND s.deleted_at IS NULL AND c.deleted_at IS NULL $statusFilter
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
");
$stmt->execute([$id]);
$submissions = $stmt->fetchAll();

$pageTitle = $profile['username'];
require_once 'includes/header.php';
?>

<div class="profile-header">
    <?php if ($profile['avatar_path']): ?>
        <img src="<?= sanitize($profile['avatar_path']) ?>" alt="Avatar" class="profile-avatar">
    <?php else: ?>
        <div class="profile-avatar" style="display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--color-text-muted);">
            <?= strtoupper(mb_substr($profile['username'], 0, 1)) ?>
        </div>
    <?php endif; ?>
    <div class="profile-info">
        <h1><?= sanitize($profile['username']) ?>
            <?php if ($profile['role'] === 'admin'): ?>
                <span class="badge badge-primary">Admin</span>
            <?php endif; ?>
        </h1>
        <p>Member since <?= date('F Y', strtotime($profile['created_at'])) ?></p>
        <?php if ($profile['website_url']): ?>
            <p><a href="<?= sanitize($profile['website_url']) ?>" target="_blank" rel="noopener"><?= sanitize(get_domain($profile['website_url'])) ?></a></p>
        <?php endif; ?>
        <?php if ($profile['bio']): ?>
            <p class="bio"><?= nl2br(sanitize($profile['bio'])) ?></p>
        <?php endif; ?>
        <?php if ($isOwn): ?>
            <a href="edit-profile.php" class="btn btn-outline btn-sm" style="margin-top: 8px;">Edit Profile</a>
        <?php endif; ?>
    </div>
</div>

<h2 class="section-title">Submissions</h2>

<?php if (empty($submissions)): ?>
    <p style="color: var(--color-text-muted);">No submissions yet.</p>
<?php else: ?>
    <div class="submission-list">
        <?php foreach ($submissions as $sub): ?>
            <?php $userVote = get_user_vote($pdo, $sub['id'], current_user_id()); ?>
            <div class="submission-card">
                <div class="vote-box">
                    <button class="vote-btn upvote <?= $userVote === 1 ? 'active' : '' ?>"
                            data-id="<?= $sub['id'] ?>" data-type="1">&#9650;</button>
                    <span class="vote-score" data-id="<?= $sub['id'] ?>"><?= $sub['net_votes'] ?></span>
                    <button class="vote-btn downvote <?= $userVote === -1 ? 'active' : '' ?>"
                            data-id="<?= $sub['id'] ?>" data-type="-1">&#9660;</button>
                </div>
                <div class="submission-content">
                    <div class="submission-title">
                        <a href="submission.php?id=<?= $sub['id'] ?>"><?= sanitize($sub['title']) ?></a>
                        <?php if ($isOwn && $sub['status'] !== 'approved'): ?>
                            <span class="badge badge-<?= $sub['status'] ?>"><?= ucfirst($sub['status']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="submission-url"><?= sanitize(get_domain($sub['url'])) ?></div>
                    <div class="submission-meta">
                        <span class="badge badge-primary"><?= sanitize($sub['category_name']) ?></span>
                        <span><?= time_ago($sub['created_at']) ?></span>
                        <span><?= $sub['comment_count'] ?> comment<?= $sub['comment_count'] !== 1 ? 's' : '' ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?= pagination_html($pagination, 'profile.php?id=' . $id) ?>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
