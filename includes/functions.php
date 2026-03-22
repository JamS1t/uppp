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
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(vote_type), 0) FROM votes WHERE submission_id = ?');
    $stmt->execute([$submissionId]);
    return (int)$stmt->fetchColumn();
}

function get_user_vote(PDO $pdo, int $submissionId, ?int $userId): int {
    if (!$userId) return 0;
    $stmt = $pdo->prepare('SELECT vote_type FROM votes WHERE submission_id = ? AND user_id = ?');
    $stmt->execute([$submissionId, $userId]);
    $result = $stmt->fetchColumn();
    return $result !== false ? (int)$result : 0;
}

function get_comment_count(PDO $pdo, int $submissionId): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE submission_id = ?');
    $stmt->execute([$submissionId]);
    return (int)$stmt->fetchColumn();
}

function get_categories(PDO $pdo): array {
    return $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
}
