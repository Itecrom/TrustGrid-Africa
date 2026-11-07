<?php
declare(strict_types=1);

final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $details = []
    ) {
        parent::__construct($message);
    }
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_json(): array
{
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > 65536) {
        throw new ApiException(413, 'Request body is too large.');
    }

    $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($contentType !== 'application/json') {
        throw new ApiException(415, 'Content-Type must be application/json.');
    }

    $body = file_get_contents('php://input', false, null, 0, 65537);
    if ($body !== false && strlen($body) > 65536) {
        throw new ApiException(413, 'Request body is too large.');
    }
    try {
        $data = json_decode($body === false ? '' : $body, false, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new ApiException(400, 'Request body must contain valid JSON.');
    }

    if (!($data instanceof stdClass)) {
        throw new ApiException(400, 'Request body must be a JSON object.');
    }

    return get_object_vars($data);
}

function require_api_user(): array
{
    try {
        $user = current_user();
    } catch (Throwable $exception) {
        error_log('TrustGrid API authentication lookup failed: ' . $exception->getMessage());
        throw new ApiException(503, 'The service is temporarily unavailable.');
    }

    if ($user === null) {
        throw new ApiException(401, 'Please sign in to continue.');
    }

    return $user;
}

function require_csrf(): void
{
    $provided = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $expected = (string) ($_SESSION['csrf_token'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        throw new ApiException(419, 'Your session token has expired. Refresh the page and try again.');
    }
}

function require_write_role(array $user): void
{
    if (!in_array($user['role'], ['admin', 'analyst', 'contributor'], true)) {
        throw new ApiException(403, 'Your account does not have permission to make this change.');
    }
}

function enforce_registration_rate_limit(PDO $pdo): void
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $ipHash = hash_hmac('sha256', 'registration:' . $ip, app_config()['app_key']);
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT IGNORE INTO signup_rate_limits (ip_hash, attempts, window_started_at)
             VALUES (:ip_hash, 0, UTC_TIMESTAMP())'
        )->execute(['ip_hash' => $ipHash]);
        $statement = $pdo->prepare(
            'SELECT attempts, window_started_at
             FROM signup_rate_limits WHERE ip_hash = :ip_hash FOR UPDATE'
        );
        $statement->execute(['ip_hash' => $ipHash]);
        $attempt = $statement->fetch();
        if (strtotime((string) $attempt['window_started_at']) < time() - 3600) {
            $pdo->prepare(
                'UPDATE signup_rate_limits
                 SET attempts = 0, window_started_at = UTC_TIMESTAMP()
                 WHERE ip_hash = :ip_hash'
            )->execute(['ip_hash' => $ipHash]);
            $attempt['attempts'] = 0;
        }
        if ((int) $attempt['attempts'] >= 3) {
            $pdo->commit();
            throw new ApiException(429, 'Too many workspaces have been created from this network. Please try again later.');
        }
        $pdo->prepare(
            'UPDATE signup_rate_limits SET attempts = attempts + 1 WHERE ip_hash = :ip_hash'
        )->execute(['ip_hash' => $ipHash]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function required_string(array $input, string $key, int $maxLength, string $label): string
{
    $value = $input[$key] ?? null;
    if (!is_string($value)) {
        throw new ApiException(422, $label . ' is required.');
    }

    $value = trim($value);
    if ($value === '' || mb_strlen($value) > $maxLength) {
        throw new ApiException(422, $label . ' must be between 1 and ' . $maxLength . ' characters.');
    }

    return $value;
}

function optional_string(array $input, string $key, int $maxLength, string $label): string
{
    $value = $input[$key] ?? '';
    if (!is_string($value)) {
        throw new ApiException(422, $label . ' must be text.');
    }

    $value = trim($value);
    if (mb_strlen($value) > $maxLength) {
        throw new ApiException(422, $label . ' must be no more than ' . $maxLength . ' characters.');
    }

    return $value;
}

function organization_id(array $user): int
{
    return (int) $user['organization_id'];
}
