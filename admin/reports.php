<?php
$baseUrl = '../';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_admin();

// Handle resolve/dismiss
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($reportId > 0 && in_array($action, ['resolved', 'dismissed'])) {
        $stmt = $pdo->prepare('UPDATE reports SET status = ? WHERE id = ?');
        $stmt->execute([$action, $reportId]);
        flash('success', 'Report ' . $action . '.');
    }
    redirect('reports.php?status=' . ($_GET['status'] ?? 'pending'));
}

$statusFilter = $_GET['status'] ?? 'pending';
if (!in_array($statusFilter, ['pending', 'resolved', 'dismissed'])) $statusFilter = 'pending';

$pagination = paginate(
    $pdo,
    'SELECT COUNT(*) FROM reports WHERE status = ?',
    [$statusFilter]
);

$stmt = $pdo->prepare("
    SELECT r.*, ru.username AS reporter_name,
           s.title AS submission_title, s.id AS sub_id,
           cm.body AS comment_body, cm.submission_id AS comment_sub_id
    FROM reports r
    JOIN users ru ON r.user_id = ru.id
    LEFT JOIN submissions s ON r.submission_id = s.id
    LEFT JOIN comments cm ON r.comment_id = cm.id
    WHERE r.status = ?
    ORDER BY r.created_at DESC
    LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
");
$stmt->execute([$statusFilter]);
$reports = $stmt->fetchAll();

$pageTitle = 'Manage Reports';
require_once '../includes/header.php';
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/_sidebar.php'; ?>
    <div class="admin-content">
        <h1>Reports</h1>

        <div class="filter-tabs">
            <a href="?status=pending" class="<?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
            <a href="?status=resolved" class="<?= $statusFilter === 'resolved' ? 'active' : '' ?>">Resolved</a>
            <a href="?status=dismissed" class="<?= $statusFilter === 'dismissed' ? 'active' : '' ?>">Dismissed</a>
        </div>

        <?php if (empty($reports)): ?>
            <p style="color: var(--color-text-muted);">No <?= $statusFilter ?> reports.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Item</th>
                        <th>Reporter</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td>
                                <?php if ($r['submission_id']): ?>
                                    <span class="badge badge-primary">Submission</span>
                                <?php else: ?>
                                    <span class="badge">Comment</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['submission_title']): ?>
                                    <a href="../submission.php?id=<?= $r['sub_id'] ?>"><?= sanitize(mb_substr($r['submission_title'], 0, 40)) ?></a>
                                <?php elseif ($r['comment_body']): ?>
                                    <a href="../submission.php?id=<?= $r['comment_sub_id'] ?>">"<?= sanitize(mb_substr($r['comment_body'], 0, 40)) ?>..."</a>
                                <?php else: ?>
                                    <span style="color: var(--color-text-muted);">(Deleted)</span>
                                <?php endif; ?>
                            </td>
                            <td><a href="../profile.php?id=<?= $r['user_id'] ?>"><?= sanitize($r['reporter_name']) ?></a></td>
                            <td><?= sanitize(mb_substr($r['reason'], 0, 80)) ?></td>
                            <td><?= time_ago($r['created_at']) ?></td>
                            <td>
                                <?php if ($r['status'] === 'pending'): ?>
                                    <div class="admin-actions">
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="action" value="resolved">
                                            <button type="submit" class="btn btn-success btn-sm">Resolve</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="action" value="dismissed">
                                            <button type="submit" class="btn btn-outline btn-sm">Dismiss</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="badge"><?= ucfirst($r['status']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?= pagination_html($pagination, 'reports.php?status=' . $statusFilter) ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
