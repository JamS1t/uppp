<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (is_logged_in()) redirect('index.php');

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid form submission.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = login_user($pdo, $email, $password);
        if ($result === true) {
            flash('success', 'Welcome back!');
            redirect('index.php');
        } else {
            $errors[] = $result;
        }
    }
}

$pageTitle = 'Log In';
require_once 'includes/header.php';
?>

<div class="auth-form">
    <h1>Log In</h1>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <?php foreach ($errors as $e): ?>
                <p><?= sanitize($e) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= sanitize($email) ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full">Log In</button>
    </form>
    <p class="auth-link">Don't have an account? <a href="register.php">Sign up</a></p>
</div>

<?php require_once 'includes/footer.php'; ?>
