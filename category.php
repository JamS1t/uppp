<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) redirect('index.php');

$stmt = $pdo->prepare('SELECT * FROM categories WHERE slug = ?');
$stmt->execute([$slug]);
$category = $stmt->fetch();

if (!$category) {
    flash('error', 'Category not found.');
    redirect('index.php');
}

$pagination = paginate(
    $pdo,
    "SELECT COUNT(*) FROM submissions WHERE category_id = ? AND status = 'approved'",
    [$category['id']]
);

$stmt = $pdo->prepare("
    SELECT s.*, u.username, c.name AS category_name, c.slug AS category_slug,
           COALESCE(SUM(v.vote_type), 0) AS net_votes,
           (SELECT COUNT(*) FROM comments cm WHERE cm.submission_id = s.id) AS comment_count
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    JOIN categories c ON s.category_id = c.id
    LEFT JOIN votes v ON v.submission_id = s.id
    WHERE s.category_id = ? AND s.status = 'approved'
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
");
$stmt->execute([$category['id']]);
$submissions = $stmt->fetchAll();

$pageTitle = $category['name'];
require_once 'includes/header.php';
?>

<div style="text-align: center; margin-bottom: 20px;">
    <h1><?= sanitize($category['name']) ?></h1>
    <?php if ($category['description']): ?>
        <p style="color: var(--color-text-muted); margin-bottom: 20px;"><?= sanitize($category['description']) ?></p>
    <?php endif; ?>
</div>

<?php if (empty($submissions)): ?>
    <p style="color: var(--color-text-muted); text-align: center;">No submissions in this category yet.</p>
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
    <?= pagination_html($pagination, 'category.php?slug=' . urlencode($slug)) ?>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
