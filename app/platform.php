<?php
declare(strict_types=1);

function require_super_admin(array $user): void
{
    if (($user['role'] ?? '') !== 'super_admin') {
        throw new ApiException(403, 'Super administrator access is required.');
    }
}

function record_user_action(
    ?int $actorUserId,
    ?int $organizationId,
    string $action,
    string $targetType,
    ?int $targetId = null,
    array $details = []
): void {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $ipHash = $ip === '' ? null : hash_hmac('sha256', 'audit:' . $ip, app_config()['app_key']);
    $statement = db()->prepare(
        'INSERT INTO user_action_logs
            (organization_id, actor_user_id, action, target_type, target_id, details_json, ip_hash)
         VALUES
            (:organization_id, :actor_user_id, :action, :target_type, :target_id, :details_json, :ip_hash)'
    );
    $statement->execute([
        'organization_id' => $organizationId,
        'actor_user_id' => $actorUserId,
        'action' => $action,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'details_json' => $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        'ip_hash' => $ipHash,
    ]);
}

function enforce_public_form_limit(PDO $pdo, string $purpose, int $maximum, int $windowSeconds): void
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $hash = hash_hmac('sha256', $purpose . ':' . $ip, app_config()['app_key']);
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT IGNORE INTO public_form_rate_limits (purpose, ip_hash, attempts, window_started_at)
             VALUES (:purpose, :ip_hash, 0, UTC_TIMESTAMP())'
        )->execute(['purpose' => $purpose, 'ip_hash' => $hash]);
        $statement = $pdo->prepare(
            'SELECT attempts, window_started_at FROM public_form_rate_limits
             WHERE purpose = :purpose AND ip_hash = :ip_hash FOR UPDATE'
        );
        $statement->execute(['purpose' => $purpose, 'ip_hash' => $hash]);
        $limit = $statement->fetch();
        if (strtotime((string) $limit['window_started_at']) < time() - $windowSeconds) {
            $pdo->prepare(
                'UPDATE public_form_rate_limits
                 SET attempts = 0, window_started_at = UTC_TIMESTAMP()
                 WHERE purpose = :purpose AND ip_hash = :ip_hash'
            )->execute(['purpose' => $purpose, 'ip_hash' => $hash]);
            $limit['attempts'] = 0;
        }
        if ((int) $limit['attempts'] >= $maximum) {
            $pdo->commit();
            throw new ApiException(429, 'Too many submissions. Please wait before trying again.');
        }
        $pdo->prepare(
            'UPDATE public_form_rate_limits SET attempts = attempts + 1
             WHERE purpose = :purpose AND ip_hash = :ip_hash'
        )->execute(['purpose' => $purpose, 'ip_hash' => $hash]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function issue_public_form_challenge(): array
{
    $left = random_int(2, 12);
    $right = random_int(2, 12);
    $challengeId = bin2hex(random_bytes(16));
    $_SESSION['public_form_challenge'] = [
        'id' => $challengeId,
        'answer_hash' => hash_hmac('sha256', (string) ($left + $right), app_config()['app_key']),
        'issued_at' => time(),
    ];
    return ['challenge_id' => $challengeId, 'question' => $left . ' + ' . $right . ' = ?'];
}

function verify_public_form_challenge(array $input): void
{
    if (trim((string) ($input['website'] ?? '')) !== '') {
        throw new ApiException(422, 'The submission could not be accepted.');
    }
    $challenge = $_SESSION['public_form_challenge'] ?? null;
    $challengeId = $input['challenge_id'] ?? null;
    $answer = $input['challenge_answer'] ?? null;
    if (!is_array($challenge) || !is_string($challengeId) || !is_string($answer)
        || !hash_equals((string) $challenge['id'], $challengeId)
        || time() - (int) $challenge['issued_at'] < 2
        || time() - (int) $challenge['issued_at'] > 900
        || !hash_equals((string) $challenge['answer_hash'], hash_hmac('sha256', trim($answer), app_config()['app_key']))) {
        throw new ApiException(422, 'Complete the human check and try again.');
    }
    unset($_SESSION['public_form_challenge']);
}

function slugify_news_title(string $title): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
    $slug = strtolower(trim((string) $ascii));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '') {
        throw new ApiException(422, 'Choose a title that can be used to create an article link.');
    }
    return substr($slug, 0, 170);
}

function sanitize_news_html(string $html): string
{
    if (strlen($html) > 500000) {
        throw new ApiException(422, 'Article text must be 500,000 bytes or fewer.');
    }
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML(
        '<?xml encoding="UTF-8"><div id="news-content">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        throw new ApiException(422, 'Article formatting could not be processed.');
    }
    $root = $document->getElementById('news-content');
    if (!$root instanceof DOMElement) {
        throw new ApiException(422, 'Article formatting could not be processed.');
    }
    $allowed = ['p', 'br', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'blockquote', 'a', 'hr'];
    $clean = static function (DOMNode $parent) use (&$clean, $allowed): void {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (!in_array($tag, $allowed, true)) {
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'svg', 'math', 'form'], true)) {
                        $parent->removeChild($child);
                    } else {
                        $clean($child);
                        while ($child->firstChild !== null) {
                            $parent->insertBefore($child->firstChild, $child);
                        }
                        $parent->removeChild($child);
                    }
                    continue;
                }
                $href = $tag === 'a' ? trim($child->getAttribute('href')) : '';
                foreach (iterator_to_array($child->attributes) as $attribute) {
                    $child->removeAttribute($attribute->name);
                }
                if ($tag === 'a' && $href !== ''
                    && (preg_match('/^(https?:\/\/|mailto:|\/(?!\/)|#)/i', $href) === 1)) {
                    $child->setAttribute('href', $href);
                    if (preg_match('/^https?:\/\//i', $href) === 1) {
                        $child->setAttribute('rel', 'noopener noreferrer');
                    }
                }
                $clean($child);
            } elseif (!$child instanceof DOMText) {
                $parent->removeChild($child);
            }
        }
    };
    $clean($root);
    $output = '';
    foreach ($root->childNodes as $child) {
        $output .= $document->saveHTML($child);
    }
    return trim($output);
}

function store_news_featured_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_string($file['tmp_name'] ?? null)
        || !is_uploaded_file($file['tmp_name'])
        || (int) ($file['size'] ?? 0) < 1
        || (int) $file['size'] > 5 * 1024 * 1024) {
        throw new ApiException(422, 'Choose an image file smaller than 5 MB.');
    }
    $image = getimagesize($file['tmp_name']);
    $mime = is_array($image) ? ($image['mime'] ?? '') : '';
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || (int) ($image[0] ?? 0) < 120 || (int) ($image[1] ?? 0) < 80) {
        throw new ApiException(422, 'Use a JPEG, PNG or WebP image at least 120 by 80 pixels.');
    }
    $directory = dirname(__DIR__) . '/uploads/news';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The news image directory could not be created.');
    }
    $filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('The news image could not be saved.');
    }
    return 'uploads/news/' . $filename;
}

function store_profile_avatar(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_string($file['tmp_name'] ?? null)
        || !is_uploaded_file($file['tmp_name'])
        || (int) ($file['size'] ?? 0) < 1
        || (int) $file['size'] > 2 * 1024 * 1024) {
        throw new ApiException(422, 'Choose an image file smaller than 2 MB.');
    }
    $image = getimagesize($file['tmp_name']);
    $mime = is_array($image) ? ($image['mime'] ?? '') : '';
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || (int) ($image[0] ?? 0) > 4000 || (int) ($image[1] ?? 0) > 4000) {
        throw new ApiException(422, 'Use a JPEG, PNG or WebP image no larger than 4,000 by 4,000 pixels.');
    }
    $directory = dirname(__DIR__) . '/uploads/profiles';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The profile image directory could not be created.');
    }
    $filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('The profile image could not be saved.');
    }
    return 'uploads/profiles/' . $filename;
}
