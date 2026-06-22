<?php
/**
 * Database bootstrap + global runtime configuration.
 *
 * Credentials are read from environment variables when present so the same
 * codebase runs in dev (XAMPP) and production without edits. Falls back to
 * the local XAMPP defaults.
 */
date_default_timezone_set('Asia/Manila');

// --- Environment ----------------------------------------------------------
// Set APP_ENV=production on the live server (e.g. via SetEnv in .htaccess).
$appEnv = getenv('APP_ENV') ?: 'development';
$isProduction = ($appEnv === 'production');

// In production never leak errors to the browser; log them instead.
error_reporting(E_ALL);
ini_set('display_errors', $isProduction ? '0' : '1');
ini_set('log_errors', '1');

// --- Database credentials -------------------------------------------------
$host     = getenv('DB_HOST')     ?: '127.0.0.1';
$dbname   = getenv('DB_NAME')     ?: 'uppp';
$username = getenv('DB_USER')     ?: 'root';
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
$charset  = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Log the real reason, show a generic message — never expose connection
    // details (host/user) to visitors.
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(503);
    die('Service temporarily unavailable. Please try again later.');
}
