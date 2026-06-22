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
    // Preserve current filters on redirect.
    $qs = http_build_query(array_filter([
        'view'   => $_GET['view']   ?? null,
        'q'      => $_GET['q']      ?? null,
        'role'   => $_GET['role']   ?? null,
        'status' => $_GET['status'] ?? null,
    ], fn($v) => $v !== null && $v !== ''));
    redirect('users.php' . ($qs ? '?' . $qs : ''));
}

// ----- Filters -----
$view = $_GET['view'] ?? 'active';
if (!in_array($view, ['active', 'deleted'])) $view = 'active';
$viewingDeleted = $view === 'deleted';

$q = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? 'all';
if (!in_array($roleFilter, ['all', 'admin', 'user'])) $roleFilter = 'all';
$statusFilter = $_GET['status'] ?? 'all';
if (!in_array($statusFilter, ['all', 'active', 'banned'])) $statusFilter = 'all';

$where = [];
$params = [];

$where[] = $viewingDeleted ? 'u.deleted_at IS NOT NULL' : 'u.deleted_at IS NULL';

if ($q !== '') {
    $like = '%' . escape_like($q) . '%';
    $where[] = '(u.username LIKE ? OR u.email LIKE ?)';
    $params[] = $like;
    $params[] = $like;
}
if ($roleFilter !== 'all') {
    $where[] = 'u.role = ?';
    $params[] = $roleFilter;
}
// Status filter only applies in the Active view (Deleted view has its own meaning).
if (!$viewingDeleted && $statusFilter !== 'all') {
    $where[] = 'u.is_banned = ?';
    $params[] = $statusFilter === 'banned' ? 1 : 0;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$pagination = paginate($pdo, "SELECT COUNT(*) FROM users u $whereSql", $params);

$stmt = $pdo->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM submissions WHERE user_id = u.id) AS submission_count,
           (SELECT COUNT(*) FROM warnings w WHERE w.user_id = u.id AND w.deleted_at IS NULL) AS warning_count
    FROM users u
    $whereSql
    ORDER BY " . ($viewingDeleted ? 'u.deleted_at' : 'u.created_at') . " DESC
    LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$users = $stmt->fetchAll();
$deletedUsersCount = deleted_count($pdo, 'users');

// Query string for pagination links (keeps filters).
$filterQs = http_build_query(array_filter([
    'view'   => $viewingDeleted ? 'deleted' : null,
    'q'      => $q !== '' ? $q : null,
    'role'   => $roleFilter !== 'all' ? $roleFilter : null,
    'status' => $statusFilter !== 'all' ? $statusFilter : null,
]));

$pageTitle = 'Manage Users';
require_once '../includes/header.php';
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/_sidebar.php'; ?>
    <div class="admin-content">
        <h1>Users</h1>

        <div class="filter-tabs">
            <a href="?view=active" class="<?= !$viewingDeleted ? 'active' : '' ?>">Active</a>
            <a href="?view=deleted" class="<?= $viewingDeleted ? 'active' : '' ?>">Deleted<?= $deletedUsersCount > 0 ? ' (' . $deletedUsersCount . ')' : '' ?></a>
        </div>

        <form method="GET" class="users-toolbar">
            <input type="hidden" name="view" value="<?= $viewingDeleted ? 'deleted' : 'active' ?>">
            <input type="text" name="q" value="<?= sanitize($q) ?>" placeholder="Search username or email…" class="users-search">
            <select name="role">
                <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All roles</option>
                <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>User</option>
            </select>
            <?php if (!$viewingDeleted): ?>
            <select name="status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="banned" <?= $statusFilter === 'banned' ? 'selected' : '' ?>>Banned</option>
            </select>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <?php if ($q !== '' || $roleFilter !== 'all' || $statusFilter !== 'all'): ?>
                <a href="?view=<?= $viewingDeleted ? 'deleted' : 'active' ?>" class="btn btn-outline btn-sm">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (empty($users)): ?>
            <p style="color: var(--color-text-muted);">No users match.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Warnings</th>
                    <th>Subs</th>
                    <th><?= $viewingDeleted ? 'Deleted' : 'Joined' ?></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php $wcount = (int)$u['warning_count']; ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <span class="user-avatar-sm"><?= sanitize(initials($u['username'])) ?></span>
                                <div class="user-cell-text">
                                    <a href="../profile.php?id=<?= $u['id'] ?>"><?= sanitize($u['username']) ?></a>
                                    <span class="user-cell-email"><?= sanitize($u['email']) ?></span>
                                </div>
                            </div>
                        </td>
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
                            <?php if ($wcount > 0): ?>
                                <details class="warnings-cell">
                                    <summary><span class="badge badge-rejected">Warnings: <?= $wcount ?> ▾</span></summary>
                                    <ul class="warning-list">
                                        <?php foreach (get_warnings($pdo, $u['id']) as $w): ?>
                                            <li>
                                                <span class="warning-reason"><?= sanitize($w['reason']) ?></span>
                                                <span class="warning-meta">by <?= sanitize($w['issued_by_name'] ?? 'system') ?> · <?= time_ago($w['created_at']) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php else: ?>
                                <span class="badge">0</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $u['submission_count'] ?></td>
                        <td><?= time_ago($viewingDeleted ? $u['deleted_at'] : $u['created_at']) ?></td>
                        <td>
                            <?php if ($u['id'] === current_user_id()): ?>
                                <span style="color: var(--color-text-muted);">(You)</span>
                            <?php elseif ($viewingDeleted): ?>
                                <form method="POST" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="action" value="restore">
                                    <button type="submit" class="btn btn-success btn-sm">Restore</button>
                                </form>
                            <?php else: ?>
                                <details class="row-menu">
                                    <summary class="btn btn-outline btn-sm">Manage ▾</summary>
                                    <div class="row-menu-panel">
                                        <?php if ($u['role'] === 'user'): ?>
                                            <form method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="action" value="make_admin">
                                                <button type="submit" class="row-menu-item">Promote to admin</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="action" value="make_user">
                                                <button type="submit" class="row-menu-item">Revoke admin</button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" class="row-menu-warn">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="warn">
                                            <textarea name="warn_reason" rows="2" placeholder="Warning reason (min 5 chars)…"></textarea>
                                            <button type="submit" class="btn btn-outline btn-sm">Issue warning</button>
                                        </form>

                                        <?php if ($u['is_banned']): ?>
                                            <form method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="action" value="unban">
                                                <button type="submit" class="row-menu-item row-menu-item-success">Unban user</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" onsubmit="return confirm('This user has <?= $wcount ?> warning(s). Consider warning before banning. Ban this user?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="action" value="ban">
                                                <button type="submit" class="row-menu-item row-menu-item-danger">Ban user</button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" onsubmit="return confirm('Delete this user (soft)?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="row-menu-item row-menu-item-danger">Delete user</button>
                                        </form>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?= pagination_html($pagination, 'users.php' . ($filterQs ? '?' . $filterQs : '')) ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
