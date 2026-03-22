<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle ?? 'uppp') ?> — uppp</title>
    <link rel="stylesheet" href="<?= $baseUrl ?? '' ?>assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="<?= $baseUrl ?? '' ?>index.php" class="logo">uppp</a>
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
                    <a href="<?= $baseUrl ?? '' ?>profile.php?id=<?= current_user_id() ?>"><?= sanitize($_SESSION['username']) ?></a>
                    <?php if (is_admin()): ?>
                        <a href="<?= $baseUrl ?? '' ?>admin/">Admin</a>
                    <?php endif; ?>
                    <a href="<?= $baseUrl ?? '' ?>logout.php">Logout</a>
                <?php else: ?>
                    <a href="<?= $baseUrl ?? '' ?>login.php">Login</a>
                    <a href="<?= $baseUrl ?? '' ?>register.php" class="btn btn-primary btn-sm">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="container">
        <?php foreach (get_flash_messages() as $msg): ?>
            <div class="flash flash-<?= sanitize($msg['type']) ?>">
                <?= sanitize($msg['message']) ?>
            </div>
        <?php endforeach; ?>
