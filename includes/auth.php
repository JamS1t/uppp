<?php
// --- Security response headers (sent on every page; before any output) ----
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 0');
}

// Send the secure cookie flag automatically when served over HTTPS so the
// session cookie is not leaked over plain HTTP in production.
$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $secureCookie,
]);
session_start();

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function is_admin(): bool {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function current_user(PDO $pdo): ?array {
    if (!is_logged_in()) return null;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([current_user_id()]);
    $user = $stmt->fetch();
    // Session points at an account that no longer exists / was deleted.
    if (!$user) {
        logout_user();
        return null;
    }
    return $user;
}

function login_user(PDO $pdo, string $email, string $password): string|true {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return 'Invalid email or password.';
    }
    if ($user['is_banned']) {
        return 'Your account has been banned.';
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['username'] = $user['username'];
    return true;
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(): void {
    if (!is_logged_in()) {
        flash('error', 'You must be logged in to do that.');
        redirect('login.php');
    }
}

function require_admin(): void {
    if (!is_admin()) {
        flash('error', 'Access denied.');
        redirect('../index.php');
    }
}
