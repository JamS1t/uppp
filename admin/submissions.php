<?php
$baseUrl = '../';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_admin();

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $subId = (int)($_POST['submission_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($subId > 0 && in_array($action, ['approved', 'rejected'])) {
        $stmt = $pdo->prepare('UPDATE submissions SET status = ? WHERE id = ?');
        $stmt->execute([$action, $subId]);
        flash('success', 'Submission ' . $action . '.');
    }
    redirect('submissions.php?status=' . ($_GET['status'] ?? 'pending'));
}

$statusFilter = $_GET['status'] ?? 'pending';
if (!in_array($statusFilter, ['pending', 'approved', 'rejected'])) $statusFilter = 'pending';

$pagination = paginate(
    $pdo,
    'SELECT COUNT(*) FROM submissions WHERE status = ?',
    [$statusFilter]
);

$stmt = $pdo->prepare("
    SELECT s.*, u.username, c.name AS category_name
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    JOIN categories c ON s.category_id = c.id
    WHERE s.status = ?
    ORDER BY s.created_at DESC
    LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
");
$stmt->execute([$statusFilter]);
$submissions = $stmt->fetchAll();

$pageTitle = 'Manage Submissions';
require_once '../includes/header.php';
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/_sidebar.php'; ?>
    <div class="admin-content">
        <h1>Submissions</h1>

        <div class="filter-tabs">
            <a href="?status=pending" class="<?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
            <a href="?status=approved" class="<?= $statusFilter === 'approved' ? 'active' : '' ?>">Approved</a>
            <a href="?status=rejected" class="<?= $statusFilter === 'rejected' ? 'active' : '' ?>">Rejected</a>
        </div>

        <?php if (empty($submissions)): ?>
            <p style="color: var(--color-text-muted);">No <?= $statusFilter ?> submissions.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>URL</th>
                        <th>User</th>
                        <th>Category</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $sub): ?>
                        <tr>
                            <td><a href="../submission.php?id=<?= $sub['id'] ?>"><?= sanitize($sub['title']) ?></a></td>
                            <td><a href="<?= sanitize($sub['url']) ?>" target="_blank" rel="noopener"><?= sanitize(get_domain($sub['url'])) ?></a></td>
                            <td><a href="../profile.php?id=<?= $sub['user_id'] ?>"><?= sanitize($sub['username']) ?></a></td>
                            <td><?= sanitize($sub['category_name']) ?></td>
                            <td><?= time_ago($sub['created_at']) ?></td>
                            <td>
                                <div class="admin-actions">
                                    <?php if ($sub['status'] !== 'approved'): ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                                            <input type="hidden" name="action" value="approved">
                                            <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($sub['status'] !== 'rejected'): ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                                            <input type="hidden" name="action" value="rejected">
                                            <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?= pagination_html($pagination, 'submissions.php?status=' . $statusFilter) ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
