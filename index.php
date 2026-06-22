<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$pageTitle = 'Home';

// Top of the Week: approved submissions from last 7 days ranked by votes + recency
$topSql = "
    SELECT s.*, u.username, c.name AS category_name, c.slug AS category_slug,
           COALESCE(SUM(v.vote_type), 0) + GREATEST(0, 7 - DATEDIFF(NOW(), s.created_at)) AS rank_score,
           COALESCE(SUM(v.vote_type), 0) AS net_votes,
           (SELECT COUNT(*) FROM comments cm WHERE cm.submission_id = s.id AND cm.deleted_at IS NULL) AS comment_count
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    JOIN categories c ON s.category_id = c.id
    LEFT JOIN votes v ON v.submission_id = s.id AND v.deleted_at IS NULL
    WHERE s.status = 'approved' AND s.created_at >= NOW() - INTERVAL 7 DAY
      AND s.deleted_at IS NULL AND u.deleted_at IS NULL AND c.deleted_at IS NULL
    GROUP BY s.id
    ORDER BY rank_score DESC
    LIMIT 5
";
$topStmt = $pdo->query($topSql);
$topSubmissions = $topStmt->fetchAll();

// Recent approved submissions, paginated
$pagination = paginate($pdo, "
    SELECT COUNT(*) FROM submissions s
    JOIN users u ON s.user_id = u.id
    JOIN categories c ON s.category_id = c.id
    WHERE s.status = 'approved'
      AND s.deleted_at IS NULL AND u.deleted_at IS NULL AND c.deleted_at IS NULL
", []);
$recentSql = "
    SELECT s.*, u.username, c.name AS category_name, c.slug AS category_slug,
           COALESCE(SUM(v.vote_type), 0) AS net_votes,
           (SELECT COUNT(*) FROM comments cm WHERE cm.submission_id = s.id AND cm.deleted_at IS NULL) AS comment_count
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    JOIN categories c ON s.category_id = c.id
    LEFT JOIN votes v ON v.submission_id = s.id AND v.deleted_at IS NULL
    WHERE s.status = 'approved'
      AND s.deleted_at IS NULL AND u.deleted_at IS NULL AND c.deleted_at IS NULL
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
";
$recentStmt = $pdo->query($recentSql);
$recentSubmissions = $recentStmt->fetchAll();

require_once 'includes/header.php';
?>

<?php if (!empty($topSubmissions)): ?>
<div class="top-section">
    <h2 class="section-title">Top of the Week</h2>
    <div class="submission-list">
        <?php foreach ($topSubmissions as $sub): ?>
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
                    </div>
                    <div class="submission-url"><?= sanitize(get_domain($sub['url'])) ?></div>
                    <div class="submission-desc"><?= sanitize($sub['description']) ?></div>
                    <div class="submission-meta">
                        <span class="badge badge-primary"><?= sanitize($sub['category_name']) ?></span>
                        <span>by <a href="profile.php?id=<?= $sub['user_id'] ?>"><?= sanitize($sub['username']) ?></a></span>
                        <span><?= time_ago($sub['created_at']) ?></span>
                        <span><?= $sub['comment_count'] ?> comment<?= $sub['comment_count'] !== 1 ? 's' : '' ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<h2 class="section-title">Recent Submissions</h2>
<?php if (empty($recentSubmissions)): ?>
    <p style="color: var(--color-text-muted);">No submissions yet. Be the first to <a href="submit.php">share something</a>!</p>
<?php else: ?>
    <div class="submission-list">
        <?php foreach ($recentSubmissions as $sub): ?>
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
                    </div>
                    <div class="submission-url"><?= sanitize(get_domain($sub['url'])) ?></div>
                    <div class="submission-desc"><?= sanitize($sub['description']) ?></div>
                    <div class="submission-meta">
                        <span class="badge badge-primary"><?= sanitize($sub['category_name']) ?></span>
                        <span>by <a href="profile.php?id=<?= $sub['user_id'] ?>"><?= sanitize($sub['username']) ?></a></span>
                        <span><?= time_ago($sub['created_at']) ?></span>
                        <span><?= $sub['comment_count'] ?> comment<?= $sub['comment_count'] !== 1 ? 's' : '' ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?= pagination_html($pagination, 'index.php') ?>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
