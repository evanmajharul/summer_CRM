<?php
declare(strict_types=1);

$configFile = __DIR__ . '/app_config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Application is not configured. Upload app_config.php or run install.php.');
}

$config = require $configFile;
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (($config['force_https'] ?? false) && !$isHttps && PHP_SAPI !== 'cli') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    if ($host !== '') {
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }
}

ini_set('display_errors', ($config['environment'] ?? 'production') === 'development' ? '1' : '0');
error_reporting(E_ALL);

session_name('summer_fair_crm_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_port'] ?? '3306',
        $config['db_name']
    );
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(503);
    exit('Database connection failed. Please check the live-server database settings.');
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}

function isLoggedIn(): bool { return isset($_SESSION['user']); }
function currentUser(): array { return $_SESSION['user'] ?? []; }
function isPrimaryAdmin(): bool { return (currentUser()['role'] ?? '') === 'primary_admin'; }
function isAdmin(): bool { return in_array(currentUser()['role'] ?? '', ['primary_admin', 'admin'], true); }

function requireLogin(): void {
    if (!isLoggedIn()) redirect('login.php');
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function requirePrimaryAdmin(): void {
    requireLogin();
    if (!isPrimaryAdmin()) {
        http_response_code(403);
        exit('Primary admin access only.');
    }
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function audit(PDO $pdo, string $action, string $entityType, ?int $entityId, string $details): void {
    $user = currentUser();
    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['id'] ?? null,
        $action,
        $entityType,
        $entityId,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}
