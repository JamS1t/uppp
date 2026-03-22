<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$q = trim($_GET['q'] ?? '');
$submissions = [];
$pagination = ['total' => 0, 'total_pages' => 0, 'page' => 1];

if ($q !== '') {
    $escaped = escape_like($q);
    $likeTerm = '%' . $escaped . '%';

    $pagination = paginate(
        $pdo,
        "SELECT COUNT(*) FROM submissions WHERE status = 'approved' AND (title LIKE ? OR description LIKE ? OR url LIKE ?)",
        [$likeTerm, $likeTerm, $likeTerm]
    );

    $stmt = $pdo->prepare("
        SELECT s.*, u.username, c.name AS category_name, c.slug AS category_slug,
               COALESCE(SUM(v.vote_type), 0) AS net_votes,
               (SELECT COUNT(*) FROM comments cm WHERE cm.submission_id = s.id) AS comment_count
        FROM submissions s
        JOIN users u ON s.user_id = u.id
        JOIN categories c ON s.category_id = c.id
        LEFT JOIN votes v ON v.submission_id = s.id
        WHERE s.status = 'approved' AND (s.title LIKE ? OR s.description LIKE ? OR s.url LIKE ?)
        GROUP BY s.id
        ORDER BY net_votes DESC, s.created_at DESC
        LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
    ");
    $stmt->execute([$likeTerm, $likeTerm, $likeTerm]);
    $submissions = $stmt->fetchAll();
}

$pageTitle = $q ? "Search: $q" : 'Search';
require_once 'includes/header.php';
?>

<h1>Search Results <?php if ($q): ?>for "<?= sanitize($q) ?>"<?php endif; ?></h1>
<p style="color: var(--color-text-muted); margin-bottom: 20px;">
    <?= $pagination['total'] ?> result<?= $pagination['total'] !== 1 ? 's' : '' ?> found
</p>

<?php if (empty($submissions) && $q): ?>
    <p style="color: var(--color-text-muted);">No results found. Try a different search term.</p>
<?php elseif (!$q): ?>
    <p style="color: var(--color-text-muted);">Enter a search term to find submissions.</p>
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
    <?= pagination_html($pagination, 'search.php?q=' . urlencode($q)) ?>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
