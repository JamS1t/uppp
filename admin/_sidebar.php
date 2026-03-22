<div class="admin-sidebar">
    <?php $current = basename($_SERVER['PHP_SELF']); ?>
    <a href="index.php" class="<?= $current === 'index.php' ? 'active' : '' ?>">Dashboard</a>
    <a href="submissions.php" class="<?= $current === 'submissions.php' ? 'active' : '' ?>">Submissions</a>
    <a href="users.php" class="<?= $current === 'users.php' ? 'active' : '' ?>">Users</a>
    <a href="categories.php" class="<?= $current === 'categories.php' ? 'active' : '' ?>">Categories</a>
    <a href="reports.php" class="<?= $current === 'reports.php' ? 'active' : '' ?>">Reports</a>
</div>
