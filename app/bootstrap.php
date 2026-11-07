<?php
declare(strict_types=1);

function app_config(): array
{
    static $config;
    if ($config !== null) {
        return $config;
    }

    $local = is_file(__DIR__ . '/../config/local.php')
        ? require __DIR__ . '/../config/local.php'
        : [];
    $local = is_array($local) ? $local : [];
    $env = static fn (string $key, mixed $default = null): mixed =>
        (($value = getenv($key)) !== false && $value !== '') ? $value : $default;

    $config = [
        'app_key' => (string) $env('APP_KEY', $local['app_key'] ?? ''),
        'database' => [
            'host' => (string) $env('DB_HOST', $local['database']['host'] ?? '127.0.0.1'),
            'port' => (int) $env('DB_PORT', $local['database']['port'] ?? 3306),
            'name' => (string) $env('DB_NAME', $local['database']['name'] ?? 'trustgrid_africa'),
            'user' => (string) $env('DB_USER', $local['database']['user'] ?? 'trustgrid_app'),
            'password' => (string) $env('DB_PASSWORD', $local['database']['password'] ?? ''),
        ],
    ];

    if (strlen($config['app_key']) < 32 || str_contains($config['app_key'], 'replace-with')) {
        throw new RuntimeException('Configure APP_KEY with a generated secret of at least 32 characters.');
    }

    return $config;
}

function app_base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $lastSlash = strrpos($script, '/');
    $directory = $lastSlash === false ? '' : substr($script, 0, $lastSlash);
    if ($directory === '/') {
        $directory = '';
    }
    if (str_ends_with($directory, '/api')) {
        $directory = substr($directory, 0, -4);
    }

    return $directory === '' ? '' : $directory;
}

function app_url(string $path = ''): string
{
    return app_base_path() . '/' . ltrim($path, '/');
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $database = app_config()['database'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $database['host'],
        $database['port'],
        $database['name']
    );
    $pdo = new PDO($dsn, $database['user'], $database['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
    ]);

    return $pdo;
}

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('trustgrid_session');
    $basePath = app_base_path();
    $cookiePath = $basePath === ''
        ? '/'
        : implode('/', array_map('rawurlencode', explode('/', $basePath))) . '/';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }

    $loaded = true;
    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if ($userId === false || $userId === null) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT u.id, u.organization_id, u.full_name, u.email, u.role,
                o.name AS organization_name, o.country
         FROM users u
         JOIN organizations o ON o.id = u.organization_id
         WHERE u.id = :id AND u.status = "active" AND o.status = "active"
         LIMIT 1'
    );
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch() ?: null;
    if ($user === null) {
        unset($_SESSION['user_id']);
    }

    return $user;
}

function require_page_user(): array
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . app_url('login.php'));
        exit;
    }

    try {
        $user = current_user();
    } catch (Throwable $exception) {
        error_log('TrustGrid page authentication failed: ' . $exception->getMessage());
        http_response_code(503);
        exit('The service is temporarily unavailable. Please try again later.');
    }

    if ($user === null) {
        header('Location: ' . app_url('login.php'));
        exit;
    }

    return $user;
}

function safe_user(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'organization_id' => (int) $user['organization_id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'organization_name' => $user['organization_name'],
        'country' => $user['country'],
    ];
}

function security_headers(): void
{
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Permitted-Cross-Domain-Policies: none');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; frame-src https://maps.google.com https://www.google.com; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

date_default_timezone_set('UTC');
security_headers();
try {
    app_config();
} catch (Throwable $exception) {
    error_log('TrustGrid configuration error: ' . $exception->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('TrustGrid Africa is not configured. Follow the local setup instructions and try again.');
}
start_app_session();
