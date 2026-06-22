<?php
$baseUrl = '../';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_admin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($userId > 0 && $userId !== current_user_id()) {
        switch ($action) {
            case 'make_admin':
                $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute(['admin', $userId]);
                flash('success', 'User promoted to admin.');
                break;
            case 'make_user':
                $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute(['user', $userId]);
                flash('success', 'User demoted to regular user.');
                break;
            case 'ban':
                $pdo->prepare('UPDATE users SET is_banned = 1 WHERE id = ?')->execute([$userId]);
                flash('success', 'User banned.');
                break;
            case 'unban':
                $pdo->prepare('UPDATE users SET is_banned = 0 WHERE id = ?')->execute([$userId]);
                flash('success', 'User unbanned.');
                break;
            case 'warn':
                $reason = trim($_POST['warn_reason'] ?? '');
                if (strlen($reason) < 5) {
                    flash('error', 'Warning reason must be at least 5 characters.');
                } else {
                    warn_user($pdo, $userId, current_user_id(), $reason);
                    flash('success', 'Warning issued to user.');
                }
                break;
            case 'delete':
                soft_delete($pdo, 'users', $userId);
                flash('success', 'User deleted (soft).');
                break;
            case 'restore':
                restore_row($pdo, 'users', $userId);
                flash('success', 'User restored.');
                break;
        }
    }
    redirect('users.php');
}

$pagination = paginate($pdo, 'SELECT COUNT(*) FROM users', []);
$stmt = $pdo->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM submissions WHERE user_id = u.id) AS submission_count,
           (SELECT COUNT(*) FROM warnings w WHERE w.user_id = u.id AND w.deleted_at IS NULL) AS warning_count
    FROM users u
    ORDER BY u.created_at DESC
    LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
");
$stmt->execute();
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users';
require_once '../includes/header.php';
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/_sidebar.php'; ?>
    <div class="admin-content">
        <h1>Users</h1>

        <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Warnings</th>
                    <th>Submissions</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><a href="../profile.php?id=<?= $u['id'] ?>"><?= sanitize($u['username']) ?></a></td>
                        <td><?= sanitize($u['email']) ?></td>
                        <td><span class="badge <?= $u['role'] === 'admin' ? 'badge-primary' : '' ?>"><?= ucfirst($u['role']) ?></span></td>
                        <td>
                            <?php if (!empty($u['deleted_at'])): ?>
                                <span class="badge badge-rejected">Deleted</span>
                            <?php elseif ($u['is_banned']): ?>
                                <span class="badge badge-rejected">Banned</span>
                            <?php else: ?>
                                <span class="badge badge-approved">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $wcount = (int)$u['warning_count'];
                            $wtitle = '';
                            if ($wcount > 0) {
                                $reasons = array_map(function ($w) { return $w['reason']; }, get_warnings($pdo, $u['id']));
                                $wtitle = implode("\n", $reasons);
                            }
                            ?>
                            <span class="badge <?= $wcount > 0 ? 'badge-rejected' : '' ?>" title="<?= sanitize($wtitle) ?>">Warnings: <?= $wcount ?></span>
                        </td>
                        <td><?= $u['submission_count'] ?></td>
                        <td><?= time_ago($u['created_at']) ?></td>
                        <td>
                            <?php if ($u['id'] !== current_user_id()): ?>
                                <?php if (!empty($u['deleted_at'])): ?>
                                    <div class="admin-actions">
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="restore">
                                            <button type="submit" class="btn btn-success btn-sm">Restore</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                <div class="admin-actions">
                                    <?php if ($u['role'] === 'user'): ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="make_admin">
                                            <button type="submit" class="btn btn-outline btn-sm" title="Promote to admin">Promote</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="make_user">
                                            <button type="submit" class="btn btn-outline btn-sm" title="Revoke admin role">Revoke</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="POST" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="action" value="warn">
                                        <input type="text" name="warn_reason" placeholder="Warning reason" class="input-sm" style="width:140px;">
                                        <button type="submit" class="btn btn-outline btn-sm" title="Warn this user">Warn</button>
                                    </form>

                                    <?php if ($u['is_banned']): ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="unban">
                                            <button type="submit" class="btn btn-success btn-sm">Unban</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('This user has <?= (int)$u['warning_count'] ?> warning(s). Consider warning before banning. Ban this user?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="ban">
                                            <button type="submit" class="btn btn-danger btn-sm">Ban</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user (soft)?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: var(--color-text-muted);">(You)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?= pagination_html($pagination, 'users.php') ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
