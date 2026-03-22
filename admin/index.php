<?php
$baseUrl = '../';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_admin();

$pendingSubs = $pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'pending'")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalSubs = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
$pendingReports = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();

$pageTitle = 'Admin Dashboard';
require_once '../includes/header.php';
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/_sidebar.php'; ?>
    <div class="admin-content">
        <h1>Dashboard</h1>
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $pendingSubs ?></div>
                <div class="stat-label">Pending Submissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $totalUsers ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $totalSubs ?></div>
                <div class="stat-label">Total Submissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $pendingReports ?></div>
                <div class="stat-label">Pending Reports</div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
