<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/platform.php';

$readInput = static function (string $prompt): string {
    fwrite(STDOUT, $prompt);
    $value = fgets(STDIN);
    return is_string($value) ? rtrim($value, "\r\n") : '';
};
$email = strtolower(trim($readInput('Super admin email: ')));
$name = trim($readInput('Super admin name: '));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || mb_strlen($name) > 120) {
    fwrite(STDERR, "A valid email address and a name of 1-120 characters are required.\n");
    exit(1);
}

$pdo = db();
$lock = (int) $pdo->query('SELECT GET_LOCK("trustgrid_super_admin_bootstrap", 0)')->fetchColumn();
if ($lock !== 1) {
    fwrite(STDERR, "Another super admin bootstrap is in progress. Try again later.\n");
    exit(1);
}

try {
    if ((int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "super_admin"')->fetchColumn() > 0) {
        fwrite(STDERR, "A super admin already exists; bootstrap is available only once.\n");
        exit(1);
    }

    $password = rtrim(strtr(base64_encode(random_bytes(30)), '+/', '-_'), '=');
    $pdo->beginTransaction();
    $pdo->prepare(
        'INSERT INTO organizations (name, country, status)
         VALUES ("TrustGrid Platform Administration", "MW", "active")'
    )->execute();
    $organizationId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO users (organization_id, full_name, email, password_hash, role)
         VALUES (:organization_id, :full_name, :email, :password_hash, "super_admin")'
    )->execute([
        'organization_id' => $organizationId,
        'full_name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    $userId = (int) $pdo->lastInsertId();
    record_user_action($userId, $organizationId, 'platform.super_admin_bootstrapped', 'user', $userId);
    $pdo->commit();
    fwrite(STDOUT, "Super admin created.\nEmail: {$email}\nTemporary password (shown once): {$password}\n");
    fwrite(STDOUT, "Sign in and change this password immediately from your profile.\n");
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Super admin creation failed: " . $exception->getMessage() . "\n");
    exit(1);
} finally {
    $pdo->query('SELECT RELEASE_LOCK("trustgrid_super_admin_bootstrap")');
}
