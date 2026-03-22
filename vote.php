<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}

$submissionId = (int)($_POST['submission_id'] ?? 0);
$voteType = (int)($_POST['vote_type'] ?? 0);

if ($submissionId <= 0 || !in_array($voteType, [1, -1])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$userId = current_user_id();

// Check existing vote
$stmt = $pdo->prepare('SELECT vote_type FROM votes WHERE user_id = ? AND submission_id = ?');
$stmt->execute([$userId, $submissionId]);
$existing = $stmt->fetchColumn();

if ($existing === false) {
    // No vote yet — insert
    $stmt = $pdo->prepare('INSERT INTO votes (user_id, submission_id, vote_type) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $submissionId, $voteType]);
    $userVote = $voteType;
} elseif ((int)$existing === $voteType) {
    // Same vote — toggle off
    $stmt = $pdo->prepare('DELETE FROM votes WHERE user_id = ? AND submission_id = ?');
    $stmt->execute([$userId, $submissionId]);
    $userVote = 0;
} else {
    // Opposite vote — switch
    $stmt = $pdo->prepare('UPDATE votes SET vote_type = ? WHERE user_id = ? AND submission_id = ?');
    $stmt->execute([$voteType, $userId, $submissionId]);
    $userVote = $voteType;
}

$score = get_vote_score($pdo, $submissionId);

echo json_encode([
    'score' => $score,
    'user_vote' => $userVote,
]);
