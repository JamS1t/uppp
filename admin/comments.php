<?php
$baseUrl = '../';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_admin();

// Handle delete/restore
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $commentId = (int)($_POST['comment_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($commentId > 0 && $action === 'delete') {
        soft_delete($pdo, 'comments', $commentId);
        flash('success', 'Comment deleted.');
    } elseif ($commentId > 0 && $action === 'restore') {
        restore_row($pdo, 'comments', $commentId);
        flash('success', 'Comment restored.');
    }
    redirect('comments.php?view=' . ($_GET['view'] ?? 'active'));
}

$view = $_GET['view'] ?? 'active';
if (!in_array($view, ['active', 'deleted'])) $view = 'active';
$viewingDeleted = $view === 'deleted';
$where = $viewingDeleted ? 'cm.deleted_at IS NOT NULL' : 'cm.deleted_at IS NULL';

$pagination = paginate($pdo, "SELECT COUNT(*) FROM comments cm WHERE $where", []);

$stmt = $pdo->prepare("
    SELECT cm.*, u.username, s.title AS submission_title
    FROM comments cm
    JOIN users u ON cm.user_id = u.id
    JOIN submissions s ON cm.submission_id = s.id
    WHERE $where
    ORDER BY " . ($viewingDeleted ? 'cm.deleted_at' : 'cm.created_at') . " DESC
    LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
");
$stmt->execute();
$comments = $stmt->fetchAll();
$deletedCount = deleted_count($pdo, 'comments');

$pageTitle = 'Manage Comments';
require_once '../includes/header.php';
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/_sidebar.php'; ?>
    <div class="admin-content">
        <h1>Comments</h1>

        <div class="filter-tabs">
            <a href="?view=active" class="<?= !$viewingDeleted ? 'active' : '' ?>">Active</a>
            <a href="?view=deleted" class="<?= $viewingDeleted ? 'active' : '' ?>">Deleted<?= $deletedCount > 0 ? ' (' . $deletedCount . ')' : '' ?></a>
        </div>

        <?php if (empty($comments)): ?>
            <p style="color: var(--color-text-muted);">No <?= $viewingDeleted ? 'deleted' : 'active' ?> comments.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Comment</th>
                        <th>Author</th>
                        <th>On</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comments as $c): ?>
                        <tr>
                            <td>
                                <a href="../submission.php?id=<?= $c['submission_id'] ?>">
                                    <?= sanitize(mb_substr($c['body'], 0, 80)) ?><?= mb_strlen($c['body']) > 80 ? '…' : '' ?>
                                </a>
                            </td>
                            <td><a href="../profile.php?id=<?= $c['user_id'] ?>"><?= sanitize($c['username']) ?></a></td>
                            <td><a href="../submission.php?id=<?= $c['submission_id'] ?>"><?= sanitize(mb_substr($c['submission_title'], 0, 40)) ?></a></td>
                            <td><?= $viewingDeleted ? 'deleted ' . time_ago($c['deleted_at']) : time_ago($c['created_at']) ?></td>
                            <td>
                                <div class="admin-actions">
                                    <?php if ($viewingDeleted): ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                                            <input type="hidden" name="action" value="restore">
                                            <button type="submit" class="btn btn-success btn-sm">Restore</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this comment?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?= pagination_html($pagination, 'comments.php?view=' . $view) ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
