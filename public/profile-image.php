<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$user = current_user();
if ($user === null) {
    http_response_code(404);
    exit;
}
$requestedUserId = filter_var(
    $_GET['user_id'] ?? $user['id'],
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($requestedUserId === false) {
    http_response_code(404);
    exit;
}

$statement = db()->prepare(
    'SELECT p.avatar_path
     FROM user_profiles p
     JOIN users u ON u.id = p.user_id
     WHERE p.user_id = :user_id AND u.organization_id = :organization_id AND u.status = "active"'
);
$statement->execute([
    'user_id' => (int) $requestedUserId,
    'organization_id' => (int) $user['organization_id'],
]);
$avatarPath = $statement->fetchColumn();
if (!is_string($avatarPath)
    || preg_match('#^uploads/profiles/([a-f0-9]{40}\.(?:jpg|png|webp))$#', $avatarPath, $matches) !== 1) {
    http_response_code(404);
    exit;
}

$filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR
    . 'profiles' . DIRECTORY_SEPARATOR . $matches[1];
if (!is_file($filePath)) {
    http_response_code(404);
    exit;
}

$mimeType = match (strtolower(pathinfo($filePath, PATHINFO_EXTENSION))) {
    'jpg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    default => 'application/octet-stream',
};
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: inline');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
