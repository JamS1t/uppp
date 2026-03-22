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
        }
    }
    redirect('users.php');
}

$pagination = paginate($pdo, 'SELECT COUNT(*) FROM users', []);
$stmt = $pdo->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM submissions WHERE user_id = u.id) AS submission_count
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

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
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
                            <?php if ($u['is_banned']): ?>
                                <span class="badge badge-rejected">Banned</span>
                            <?php else: ?>
                                <span class="badge badge-approved">Active</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $u['submission_count'] ?></td>
                        <td><?= time_ago($u['created_at']) ?></td>
                        <td>
                            <?php if ($u['id'] !== current_user_id()): ?>
                                <div class="admin-actions">
                                    <?php if ($u['role'] === 'user'): ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="make_admin">
                                            <button type="submit" class="btn btn-outline btn-sm">Make Admin</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="make_user">
                                            <button type="submit" class="btn btn-outline btn-sm">Remove Admin</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($u['is_banned']): ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="unban">
                                            <button type="submit" class="btn btn-success btn-sm">Unban</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="ban">
                                            <button type="submit" class="btn btn-danger btn-sm">Ban</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--color-text-muted);">(You)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?= pagination_html($pagination, 'users.php') ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
