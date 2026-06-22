<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= sanitize(csrf_token()) ?>">
    <title><?= sanitize($pageTitle ?? 'uppp') ?> — uppp</title>
    <link rel="stylesheet" href="<?= $baseUrl ?? '' ?>assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="<?= $baseUrl ?? '' ?>index.php" class="logo">uppp</a>

            <input type="checkbox" id="nav-toggle" class="nav-toggle-checkbox">
            <label for="nav-toggle" class="nav-hamburger" aria-label="Toggle navigation">
                <span></span><span></span><span></span>
            </label>

            <div class="nav-menu">
                <div class="nav-categories">
                    <?php
                    $navCategories = get_categories($pdo);
                    foreach (array_slice($navCategories, 0, 5) as $cat): ?>
                        <a href="<?= $baseUrl ?? '' ?>category.php?slug=<?= sanitize($cat['slug']) ?>"><?= sanitize($cat['name']) ?></a>
                    <?php endforeach; ?>
                </div>

                <form class="search-form" action="<?= $baseUrl ?? '' ?>search.php" method="GET">
                    <input type="text" name="q" placeholder="Search..." value="<?= sanitize($_GET['q'] ?? '') ?>">
                </form>

                <div class="nav-auth">
                    <?php if (is_logged_in()): ?>
                        <a href="<?= $baseUrl ?? '' ?>submit.php" class="btn btn-primary btn-sm">+ Submit</a>
                        <a href="<?= $baseUrl ?? '' ?>profile.php?id=<?= current_user_id() ?>" class="nav-user">
                            <span class="nav-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></span>
                            <?= sanitize($_SESSION['username']) ?>
                        </a>
                        <?php if (is_admin()): ?>
                            <a href="<?= $baseUrl ?? '' ?>admin/" class="badge badge-primary">Admin</a>
                        <?php endif; ?>
                        <a href="<?= $baseUrl ?? '' ?>logout.php" class="nav-logout" title="Log out">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        </a>
                    <?php else: ?>
                        <a href="<?= $baseUrl ?? '' ?>login.php" class="nav-login">Log in</a>
                        <a href="<?= $baseUrl ?? '' ?>register.php" class="btn btn-primary btn-sm">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main class="container">
        <?php foreach (get_flash_messages() as $msg): ?>
            <div class="flash flash-<?= sanitize($msg['type']) ?>">
                <?= sanitize($msg['message']) ?>
            </div>
        <?php endforeach; ?>
        <?php
        if (is_logged_in()) {
            $unack = get_unacknowledged_warnings($pdo, current_user_id());
            if (!empty($unack)) {
                foreach ($unack as $w) {
                    echo '<div class="flash flash-error flash-warning">';
                    echo '⚠ Warning from moderators: ' . sanitize($w['reason']);
                    echo '</div>';
                }
                acknowledge_warnings($pdo, current_user_id());
            }
        }
        ?>
