<?php
function sanitize(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash_messages(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function escape_like(string $term): string {
    return str_replace(['%', '_'], ['\\%', '\\_'], $term);
}

function paginate(PDO $pdo, string $countSql, array $countParams, int $perPage = 20): array {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($countParams);
    $total = (int)$stmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'limit' => $perPage,
    ];
}

function pagination_html(array $pagination, string $baseUrl): string {
    if ($pagination['total_pages'] <= 1) return '';

    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    $html = '<div class="pagination">';
    if ($pagination['page'] > 1) {
        $html .= '<a href="' . $baseUrl . $sep . 'page=' . ($pagination['page'] - 1) . '" class="btn btn-sm">&laquo; Prev</a>';
    }
    $html .= '<span class="pagination-info">Page ' . $pagination['page'] . ' of ' . $pagination['total_pages'] . '</span>';
    if ($pagination['page'] < $pagination['total_pages']) {
        $html .= '<a href="' . $baseUrl . $sep . 'page=' . ($pagination['page'] + 1) . '" class="btn btn-sm">Next &raquo;</a>';
    }
    $html .= '</div>';
    return $html;
}

function time_ago(string $datetime): string {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . 'y ago';
    if ($diff->m > 0) return $diff->m . 'mo ago';
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'just now';
}

function get_domain(string $url): string {
    $parsed = parse_url($url);
    return $parsed['host'] ?? $url;
}

function get_vote_score(PDO $pdo, int $submissionId): int {
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(vote_type), 0) FROM votes WHERE submission_id = ? AND deleted_at IS NULL');
    $stmt->execute([$submissionId]);
    return (int)$stmt->fetchColumn();
}

function get_user_vote(PDO $pdo, int $submissionId, ?int $userId): int {
    if (!$userId) return 0;
    $stmt = $pdo->prepare('SELECT vote_type FROM votes WHERE submission_id = ? AND user_id = ? AND deleted_at IS NULL');
    $stmt->execute([$submissionId, $userId]);
    $result = $stmt->fetchColumn();
    return $result !== false ? (int)$result : 0;
}

function get_comment_count(PDO $pdo, int $submissionId): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE submission_id = ? AND deleted_at IS NULL');
    $stmt->execute([$submissionId]);
    return (int)$stmt->fetchColumn();
}

function get_categories(PDO $pdo): array {
    return $pdo->query('SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
}

/* ---------------------------------------------------------------------------
 * Soft delete
 *
 * Nothing is ever physically removed. "Deleting" a row sets its deleted_at
 * timestamp. Every read query must filter `deleted_at IS NULL`.
 * ------------------------------------------------------------------------- */

// Whitelist of tables that support soft delete. Used to guard the dynamic
// table name in soft_delete()/restore_row() against SQL injection.
const SOFT_DELETE_TABLES = ['submissions', 'comments', 'votes', 'categories', 'users', 'warnings', 'reports'];

function soft_delete(PDO $pdo, string $table, int $id): bool {
    if (!in_array($table, SOFT_DELETE_TABLES, true)) {
        throw new InvalidArgumentException("Table '$table' is not soft-deletable.");
    }
    $stmt = $pdo->prepare("UPDATE `$table` SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

function restore_row(PDO $pdo, string $table, int $id): bool {
    if (!in_array($table, SOFT_DELETE_TABLES, true)) {
        throw new InvalidArgumentException("Table '$table' is not soft-deletable.");
    }
    $stmt = $pdo->prepare("UPDATE `$table` SET deleted_at = NULL WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

// Count of soft-deleted rows in a table (for "Deleted" tab badges).
// $table comes only from the trusted SOFT_DELETE_TABLES whitelist, never input.
function deleted_count(PDO $pdo, string $table): int {
    if (!in_array($table, SOFT_DELETE_TABLES, true)) {
        throw new InvalidArgumentException("Table '$table' is not soft-deletable.");
    }
    return (int)$pdo->query("SELECT COUNT(*) FROM `$table` WHERE deleted_at IS NOT NULL")->fetchColumn();
}

// Initials for an avatar chip (1-2 letters derived from a username, no storage).
function initials(string $name): string {
    $name = trim($name);
    if ($name === '') return '?';
    $parts = preg_split('/[\s_\-.]+/', $name, -1, PREG_SPLIT_NO_EMPTY);
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }
    return strtoupper(mb_substr($name, 0, 2));
}

/* ---------------------------------------------------------------------------
 * User warnings
 *
 * Admins issue warnings before resorting to a ban. Warnings are shown to the
 * user (header banner) until acknowledged. Warnings are themselves soft-deletable.
 * ------------------------------------------------------------------------- */

function warn_user(PDO $pdo, int $userId, ?int $adminId, string $reason): void {
    $stmt = $pdo->prepare('INSERT INTO warnings (user_id, issued_by, reason) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $adminId, $reason]);
}

// Active (non-deleted) warnings for a user, newest first.
function get_warnings(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('
        SELECT w.*, a.username AS issued_by_name
        FROM warnings w
        LEFT JOIN users a ON w.issued_by = a.id
        WHERE w.user_id = ? AND w.deleted_at IS NULL
        ORDER BY w.created_at DESC
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function get_warning_count(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM warnings WHERE user_id = ? AND deleted_at IS NULL');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

// Warnings the user has not yet seen/acknowledged (for the login banner).
function get_unacknowledged_warnings(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('
        SELECT * FROM warnings
        WHERE user_id = ? AND deleted_at IS NULL AND acknowledged_at IS NULL
        ORDER BY created_at DESC
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function acknowledge_warnings(PDO $pdo, int $userId): void {
    $stmt = $pdo->prepare('UPDATE warnings SET acknowledged_at = NOW() WHERE user_id = ? AND acknowledged_at IS NULL AND deleted_at IS NULL');
    $stmt->execute([$userId]);
}
