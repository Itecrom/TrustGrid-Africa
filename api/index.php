<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/http.php';
require __DIR__ . '/../app/safe_web_review.php';
require __DIR__ . '/../app/assessment_catalog.php';

try {
    $path = rawurldecode((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'));
    $prefix = app_base_path() . '/api';
    $route = '/' . trim(substr($path, strlen($prefix)), '/');
    if ($route === '//') {
        $route = '/';
    }
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'OPTIONS') {
        header('Allow: GET, POST, PATCH, DELETE, OPTIONS');
        http_response_code(204);
        exit;
    }

    if ($method !== 'GET') {
        require_csrf();
    }

    if ($route === '/auth/register' && $method === 'POST') {
        $input = request_json();
        $name = required_string($input, 'full_name', 120, 'Name');
        $email = strtolower(required_string($input, 'email', 254, 'Email'));
        $organization = required_string($input, 'organization_name', 160, 'Organisation name');
        $country = strtoupper(required_string($input, 'country', 2, 'Country'));
        $password = $input['password'] ?? null;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException(422, 'Enter a valid email address.');
        }
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            throw new ApiException(422, 'Choose a valid country.');
        }
        if (!is_string($password) || strlen($password) < 12 || strlen($password) > 200) {
            throw new ApiException(422, 'Password must be between 12 and 200 characters.');
        }

        $pdo = db();
        enforce_registration_rate_limit($pdo);
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'INSERT INTO organizations (name, country) VALUES (:name, :country)'
            );
            $statement->execute(['name' => $organization, 'country' => $country]);
            $organizationId = (int) $pdo->lastInsertId();

            $statement = $pdo->prepare(
                'INSERT INTO users (organization_id, full_name, email, password_hash, role)
                 VALUES (:organization_id, :full_name, :email, :password_hash, "admin")'
            );
            $statement->execute([
                'organization_id' => $organizationId,
                'full_name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() === '23000') {
                throw new ApiException(409, 'An account with that email already exists.');
            }
            throw $exception;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        json_response(['data' => ['user' => ['id' => $userId, 'organization_id' => $organizationId]]], 201);
    }

    if ($route === '/auth/login' && $method === 'POST') {
        $input = request_json();
        $email = strtolower(required_string($input, 'email', 254, 'Email'));
        $password = $input['password'] ?? null;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password)) {
            throw new ApiException(422, 'Enter your email and password.');
        }

        $config = app_config();
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ipHash = hash_hmac('sha256', $ip, $config['app_key']);
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT IGNORE INTO login_attempts (ip_hash, attempts, window_started_at)
             VALUES (:ip_hash, 0, UTC_TIMESTAMP())'
        )->execute(['ip_hash' => $ipHash]);
        $attemptStatement = $pdo->prepare(
            'SELECT attempts, window_started_at FROM login_attempts WHERE ip_hash = :ip_hash FOR UPDATE'
        );
        $attemptStatement->execute(['ip_hash' => $ipHash]);
        $attempt = $attemptStatement->fetch();
        if (strtotime((string) $attempt['window_started_at']) < time() - 900) {
            $pdo->prepare(
                'UPDATE login_attempts SET attempts = 0, window_started_at = UTC_TIMESTAMP()
                 WHERE ip_hash = :ip_hash'
            )->execute(['ip_hash' => $ipHash]);
            $attempt['attempts'] = 0;
        }
        if ((int) $attempt['attempts'] >= 8) {
            $pdo->commit();
            throw new ApiException(429, 'Too many sign-in attempts. Please wait 15 minutes and try again.');
        }

        $statement = $pdo->prepare(
            'SELECT u.id, u.password_hash
             FROM users u JOIN organizations o ON o.id = u.organization_id
             WHERE u.email = :email AND u.status = "active" AND o.status = "active"
             ORDER BY u.id LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $account = $statement->fetch();
        if ($account === false || !password_verify($password, $account['password_hash'])) {
            $pdo->prepare(
                'UPDATE login_attempts
                 SET attempts = attempts + 1, window_started_at = COALESCE(window_started_at, UTC_TIMESTAMP())
                 WHERE ip_hash = :ip_hash'
            )->execute(['ip_hash' => $ipHash]);
            $pdo->commit();
            throw new ApiException(401, 'The email or password is incorrect.');
        }

        $pdo->prepare('DELETE FROM login_attempts WHERE ip_hash = :ip_hash')->execute(['ip_hash' => $ipHash]);
        $pdo->commit();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $account['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        json_response(['data' => ['user' => ['id' => (int) $account['id']]]]);
    }

    if ($route === '/auth/logout' && $method === 'POST') {
        require_api_user();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        json_response(['data' => ['signed_out' => true]]);
    }

    if ($route === '/auth/me' && $method === 'GET') {
        $user = require_api_user();
        json_response(['data' => ['user' => safe_user($user), 'csrf_token' => csrf_token()]]);
    }

    $user = require_api_user();
    $orgId = organization_id($user);

    if ($route === '/dashboard' && $method === 'GET') {
        $pdo = db();
        $statement = $pdo->prepare(
            'SELECT COUNT(*) AS total_assets,
                    SUM(status <> "Archived") AS active_assets
             FROM assets WHERE organization_id = :organization_id'
        );
        $statement->execute(['organization_id' => $orgId]);
        $assetCounts = $statement->fetch();

        $statement = $pdo->prepare(
            'SELECT COUNT(*) AS total,
                    SUM(status = "Open") AS open_count,
                    SUM(status = "In progress") AS in_progress_count,
                    SUM(status = "Resolved") AS resolved_count,
                    SUM(status = "Open" AND severity = "Critical") AS critical_count,
                    SUM(status = "Open" AND severity = "High") AS high_count,
                    SUM(status = "Open" AND severity = "Medium") AS medium_count,
                    SUM(status = "Open" AND severity = "Low") AS low_count
             FROM findings WHERE organization_id = :organization_id'
        );
        $statement->execute(['organization_id' => $orgId]);
        $findingCounts = $statement->fetch();

        $statement = $pdo->prepare(
            'SELECT COUNT(*) AS total FROM assessments WHERE organization_id = :organization_id'
        );
        $statement->execute(['organization_id' => $orgId]);
        $assessmentCount = $statement->fetchColumn();

        $statement = $pdo->prepare(
            'SELECT f.id, f.title, f.severity, f.status, f.created_at, a.name AS asset_name
             FROM findings f JOIN assets a ON a.id = f.asset_id AND a.organization_id = f.organization_id
             WHERE f.organization_id = :organization_id AND f.status <> "Resolved"
             ORDER BY FIELD(f.severity, "Critical", "High", "Medium", "Low"), f.created_at DESC
             LIMIT 8'
        );
        $statement->execute(['organization_id' => $orgId]);
        $recentFindings = $statement->fetchAll();

        $statement = $pdo->prepare(
            'SELECT a.id, a.name, a.type, a.owner_team, a.status,
                    CASE
                      WHEN SUM(f.status <> "Resolved" AND f.severity = "Critical") > 0 THEN "Critical"
                      WHEN SUM(f.status <> "Resolved" AND f.severity = "High") > 0 THEN "High"
                      WHEN SUM(f.status <> "Resolved" AND f.severity = "Medium") > 0 THEN "Moderate"
                      WHEN SUM(f.status <> "Resolved" AND f.severity = "Low") > 0 THEN "Low"
                      ELSE a.risk_level
                    END AS risk_level
             FROM assets a
             LEFT JOIN findings f ON f.asset_id = a.id AND f.organization_id = a.organization_id
             WHERE a.organization_id = :organization_id AND a.status <> "Archived"
             GROUP BY a.id ORDER BY a.updated_at DESC LIMIT 8'
        );
        $statement->execute(['organization_id' => $orgId]);
        $assets = $statement->fetchAll();

        $statement = $pdo->prepare(
            'SELECT DATE_FORMAT(completed_at, "%Y-%m") AS month,
                    ROUND(AVG(risk_score)) AS average_score
             FROM assessments
             WHERE organization_id = :organization_id
               AND completed_at >= DATE_SUB(UTC_DATE(), INTERVAL 5 MONTH)
             GROUP BY DATE_FORMAT(completed_at, "%Y-%m")
             ORDER BY month'
        );
        $statement->execute(['organization_id' => $orgId]);

        json_response(['data' => [
            'assets' => array_map(static fn ($row) => array_map('intval', $row), [$assetCounts])[0],
            'findings' => array_map(static fn ($value) => (int) ($value ?? 0), $findingCounts),
            'assessments' => (int) $assessmentCount,
            'recent_findings' => $recentFindings,
            'recent_assets' => $assets,
            'risk_trend' => $statement->fetchAll(),
        ]]);
    }

    if ($route === '/assets' && $method === 'GET') {
        $statement = db()->prepare(
            'SELECT a.id, a.name, a.type, a.target, a.owner_team, a.status, a.risk_level,
                    a.authorization_confirmed, a.authorized_at, a.created_at,
                    latest_scan.id AS latest_scan_id,
                    latest_scan.completed_at AS latest_scan_at,
                    CASE
                      WHEN SUM(f.status <> "Resolved" AND f.severity = "Critical") > 0 THEN "Critical"
                      WHEN SUM(f.status <> "Resolved" AND f.severity = "High") > 0 THEN "High"
                      WHEN SUM(f.status <> "Resolved" AND f.severity = "Medium") > 0 THEN "Moderate"
                      WHEN SUM(f.status <> "Resolved" AND f.severity = "Low") > 0 THEN "Low"
                      ELSE a.risk_level
                    END AS current_risk
             FROM assets a
             LEFT JOIN findings f ON f.asset_id = a.id AND f.organization_id = a.organization_id
             LEFT JOIN (
                SELECT organization_id, asset_id, MAX(id) AS latest_id
                FROM web_scans
                GROUP BY organization_id, asset_id
             ) scan_index ON scan_index.organization_id = a.organization_id
                         AND scan_index.asset_id = a.id
             LEFT JOIN web_scans latest_scan ON latest_scan.id = scan_index.latest_id
                                             AND latest_scan.organization_id = a.organization_id
             WHERE a.organization_id = :organization_id
             GROUP BY a.id, latest_scan.id, latest_scan.completed_at
             ORDER BY a.created_at DESC'
        );
        $statement->execute(['organization_id' => $orgId]);
        json_response(['data' => ['assets' => $statement->fetchAll()]]);
    }

    if ($route === '/assessments/engagements' && $method === 'GET') {
        $statement = db()->prepare(
            'SELECT e.id, e.asset_id, e.title, e.scope_text, e.exclusions,
                    e.rules_of_engagement, e.lead_name, e.authorization_note,
                    e.authorization_confirmed, e.start_date, e.end_date,
                    CASE
                      WHEN e.status = "Planned" AND e.start_date <= UTC_DATE() AND e.end_date >= UTC_DATE()
                        THEN "Active"
                      WHEN e.status IN ("Planned", "Active") AND e.end_date < UTC_DATE()
                        THEN "Expired"
                      ELSE e.status
                    END AS status,
                    e.created_at, a.name AS asset_name, a.type AS asset_type,
                    (SELECT COUNT(*) FROM guided_assessments ga
                     WHERE ga.organization_id = e.organization_id AND ga.engagement_id = e.id) AS assessment_count
             FROM assessment_engagements e
             JOIN assets a ON a.id = e.asset_id AND a.organization_id = e.organization_id
             WHERE e.organization_id = :organization_id
             ORDER BY e.start_date DESC, e.id DESC LIMIT 100'
        );
        $statement->execute(['organization_id' => $orgId]);
        $engagements = $statement->fetchAll();
        foreach ($engagements as &$engagement) {
            $engagement['id'] = (int) $engagement['id'];
            $engagement['asset_id'] = (int) $engagement['asset_id'];
            $engagement['authorization_confirmed'] = (bool) $engagement['authorization_confirmed'];
            $engagement['assessment_count'] = (int) $engagement['assessment_count'];
        }
        unset($engagement);
        json_response(['data' => ['engagements' => $engagements]]);
    }

    if ($route === '/assessments/engagements' && $method === 'POST') {
        require_write_role($user);
        $input = request_json();
        $title = required_string($input, 'title', 180, 'Engagement name');
        $scope = required_string($input, 'scope_text', 10000, 'In-scope work');
        $exclusions = required_string($input, 'exclusions', 10000, 'Out-of-scope work');
        $rules = required_string($input, 'rules_of_engagement', 10000, 'Rules of engagement');
        $leadName = required_string($input, 'lead_name', 120, 'Engagement lead');
        $authorizationNote = required_string($input, 'authorization_note', 500, 'Authorisation details');
        if (($input['authorization_confirmed'] ?? false) !== true) {
            throw new ApiException(422, 'Confirm that the named lead has documented authorisation for this engagement.');
        }
        $assetId = filter_var($input['asset_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($assetId === false) {
            throw new ApiException(422, 'Choose an authorised asset for this engagement.');
        }
        $parseDate = static function (mixed $value, string $label): DateTimeImmutable {
            if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                throw new ApiException(422, $label . ' must be a valid calendar date.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
            $errors = DateTimeImmutable::getLastErrors();
            if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
                || $date->format('Y-m-d') !== $value) {
                throw new ApiException(422, $label . ' must be a valid calendar date.');
            }
            return $date;
        };
        $startDate = $parseDate($input['start_date'] ?? null, 'Start date');
        $endDate = $parseDate($input['end_date'] ?? null, 'End date');
        $today = new DateTimeImmutable(gmdate('Y-m-d'), new DateTimeZone('UTC'));
        if ($endDate < $startDate || $endDate < $today) {
            throw new ApiException(422, 'The engagement end date must be on or after its start date and not in the past.');
        }
        $assetQuery = db()->prepare(
            'SELECT id FROM assets
             WHERE id = :id AND organization_id = :organization_id
               AND authorization_confirmed = 1 AND status <> "Archived"
             LIMIT 1'
        );
        $assetQuery->execute(['id' => $assetId, 'organization_id' => $orgId]);
        if ($assetQuery->fetchColumn() === false) {
            throw new ApiException(404, 'Authorised, active asset not found.');
        }
        $statement = db()->prepare(
            'INSERT INTO assessment_engagements
                (organization_id, asset_id, title, scope_text, exclusions, rules_of_engagement,
                 lead_name, authorization_note, authorization_confirmed, start_date, end_date, status, created_by)
             VALUES
                (:organization_id, :asset_id, :title, :scope_text, :exclusions, :rules_of_engagement,
                 :lead_name, :authorization_note, 1, :start_date, :end_date, :status, :created_by)'
        );
        $statement->execute([
            'organization_id' => $orgId,
            'asset_id' => (int) $assetId,
            'title' => $title,
            'scope_text' => $scope,
            'exclusions' => $exclusions,
            'rules_of_engagement' => $rules,
            'lead_name' => $leadName,
            'authorization_note' => $authorizationNote,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'status' => $startDate > $today ? 'Planned' : 'Active',
            'created_by' => (int) $user['id'],
        ]);
        json_response(['data' => ['id' => (int) db()->lastInsertId()]], 201);
    }

    if (preg_match('#^/assessments/engagements/([1-9][0-9]*)$#', $route, $matches) === 1 && $method === 'PATCH') {
        require_write_role($user);
        $input = request_json();
        $status = $input['status'] ?? null;
        if (!is_string($status) || !in_array($status, ['Completed', 'Cancelled'], true)) {
            throw new ApiException(422, 'Choose Completed or Cancelled as the engagement status.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'SELECT status, start_date, end_date FROM assessment_engagements
                 WHERE id = :id AND organization_id = :organization_id LIMIT 1 FOR UPDATE'
            );
            $statement->execute(['id' => (int) $matches[1], 'organization_id' => $orgId]);
            $engagement = $statement->fetch();
            if ($engagement === false) {
                throw new ApiException(404, 'Engagement not found.');
            }
            if (!in_array($engagement['status'], ['Planned', 'Active'], true)) {
                throw new ApiException(409, 'This engagement is already closed.');
            }
            if ($status === 'Completed'
                && ($engagement['start_date'] > gmdate('Y-m-d') || $engagement['end_date'] < gmdate('Y-m-d'))) {
                throw new ApiException(409, 'An engagement can be completed only during its authorised date window.');
            }
            if ($status === 'Completed') {
                $draftQuery = $pdo->prepare(
                    'SELECT COUNT(*) FROM guided_assessments
                     WHERE organization_id = :organization_id AND engagement_id = :engagement_id AND status = "Draft"'
                );
                $draftQuery->execute([
                    'organization_id' => $orgId,
                    'engagement_id' => (int) $matches[1],
                ]);
                if ((int) $draftQuery->fetchColumn() > 0) {
                    throw new ApiException(409, 'Complete or cancel linked draft assessments before closing this engagement.');
                }
            }
            $statement = $pdo->prepare(
                'UPDATE assessment_engagements SET status = :status
                 WHERE id = :id AND organization_id = :organization_id AND status IN ("Planned", "Active")'
            );
            $statement->execute([
                'status' => $status,
                'id' => (int) $matches[1],
                'organization_id' => $orgId,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new ApiException(409, 'The engagement status changed. Refresh and try again.');
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        json_response(['data' => ['updated' => true]]);
    }

    if ($route === '/assessments/catalog' && $method === 'GET') {
        json_response(['data' => [
            'frameworks' => assessment_frameworks(),
            'controls' => assessment_controls(),
            'answers' => [
                ['id' => 'implemented', 'label' => 'Yes, in place'],
                ['id' => 'partial', 'label' => 'Partly in place'],
                ['id' => 'not_implemented', 'label' => 'Not in place'],
                ['id' => 'not_sure', 'label' => 'Not sure'],
                ['id' => 'not_applicable', 'label' => 'Not applicable'],
            ],
        ]]);
    }

    if ($route === '/assessments/guided' && $method === 'GET') {
        $statement = db()->prepare(
            'SELECT ga.id, ga.asset_id, ga.engagement_id, ga.title, ga.scope, ga.frameworks_json, ga.status,
                    ga.overall_score, ga.created_at, ga.updated_at, ga.completed_at,
                    a.name AS asset_name, e.title AS engagement_title,
                    COUNT(DISTINCT gaa.control_id) AS answered_count,
                    (SELECT COUNT(*) FROM findings f
                     WHERE f.assessment_id = ga.completed_assessment_id
                       AND f.organization_id = ga.organization_id) AS findings_count
             FROM guided_assessments ga
             JOIN assets a ON a.id = ga.asset_id AND a.organization_id = ga.organization_id
             LEFT JOIN assessment_engagements e
                    ON e.id = ga.engagement_id AND e.organization_id = ga.organization_id
             LEFT JOIN guided_assessment_answers gaa
                    ON gaa.organization_id = ga.organization_id
                   AND gaa.guided_assessment_id = ga.id
             WHERE ga.organization_id = :organization_id
             GROUP BY ga.id, a.name, e.title
             ORDER BY ga.updated_at DESC, ga.id DESC LIMIT 50'
        );
        $statement->execute(['organization_id' => $orgId]);
        $assessments = $statement->fetchAll();
        foreach ($assessments as &$assessment) {
            $assessment['id'] = (int) $assessment['id'];
            $assessment['asset_id'] = (int) $assessment['asset_id'];
            $assessment['engagement_id'] = $assessment['engagement_id'] === null
                ? null
                : (int) $assessment['engagement_id'];
            $assessment['overall_score'] = $assessment['overall_score'] === null
                ? null
                : (int) $assessment['overall_score'];
            $assessment['answered_count'] = (int) $assessment['answered_count'];
            $assessment['findings_count'] = (int) $assessment['findings_count'];
            $assessment['frameworks'] = json_decode($assessment['frameworks_json'], true, 16, JSON_THROW_ON_ERROR);
            unset($assessment['frameworks_json']);
        }
        unset($assessment);
        json_response(['data' => ['assessments' => $assessments]]);
    }

    if ($route === '/assessments/guided' && $method === 'POST') {
        require_write_role($user);
        $input = request_json();
        $title = required_string($input, 'title', 180, 'Assessment name');
        $scope = required_string($input, 'scope', 10000, 'Assessment scope');
        $assetId = filter_var($input['asset_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $engagementId = ($input['engagement_id'] ?? '') === ''
            ? null
            : filter_var($input['engagement_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $frameworks = $input['frameworks'] ?? null;
        $validFrameworks = array_column(assessment_frameworks(), 'id');
        if ($assetId === false || $engagementId === false || !is_array($frameworks) || $frameworks === []) {
            throw new ApiException(422, 'Choose an asset and at least one assessment baseline.');
        }
        $frameworks = array_values(array_unique($frameworks));
        if (count($frameworks) !== count(array_filter(
            $frameworks,
            static fn ($framework): bool => is_string($framework) && in_array($framework, $validFrameworks, true)
        ))) {
            throw new ApiException(422, 'Choose valid assessment baselines.');
        }

        $assetQuery = db()->prepare(
            'SELECT id, type FROM assets
             WHERE id = :id AND organization_id = :organization_id
               AND authorization_confirmed = 1 AND status <> "Archived"
             LIMIT 1'
        );
        $assetQuery->execute(['id' => $assetId, 'organization_id' => $orgId]);
        $asset = $assetQuery->fetch();
        if ($asset === false) {
            throw new ApiException(404, 'Authorised, active asset not found.');
        }
        $matchingControls = array_filter(
            assessment_controls_for_asset_type($asset['type']),
            static fn (array $control): bool => count(array_intersect($control['frameworks'], $frameworks)) > 0
        );
        if ($matchingControls === []) {
            throw new ApiException(422, 'The selected baselines do not include questions for this asset type.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($engagementId !== null) {
                $engagementQuery = $pdo->prepare(
                    'SELECT id FROM assessment_engagements
                     WHERE id = :id AND organization_id = :organization_id AND asset_id = :asset_id
                       AND status IN ("Planned", "Active")
                       AND start_date <= UTC_DATE() AND end_date >= UTC_DATE()
                     LIMIT 1 FOR UPDATE'
                );
                $engagementQuery->execute([
                    'id' => $engagementId,
                    'organization_id' => $orgId,
                    'asset_id' => (int) $assetId,
                ]);
                if ($engagementQuery->fetchColumn() === false) {
                    throw new ApiException(422, 'Choose an open engagement for the same asset that is within its authorised date window.');
                }
            }
            $statement = $pdo->prepare(
                'INSERT INTO guided_assessments
                    (organization_id, asset_id, engagement_id, title, scope, frameworks_json, created_by)
                 VALUES
                    (:organization_id, :asset_id, :engagement_id, :title, :scope, :frameworks_json, :created_by)'
            );
            $statement->execute([
                'organization_id' => $orgId,
                'asset_id' => (int) $assetId,
                'engagement_id' => $engagementId,
                'title' => $title,
                'scope' => $scope,
                'frameworks_json' => json_encode($frameworks, JSON_THROW_ON_ERROR),
                'created_by' => (int) $user['id'],
            ]);
            $assessmentId = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        json_response(['data' => ['id' => $assessmentId]], 201);
    }

    if (preg_match('#^/assessments/guided/([1-9][0-9]*)/cancel$#', $route, $matches) === 1 && $method === 'POST') {
        require_write_role($user);
        $statement = db()->prepare(
            'UPDATE guided_assessments SET status = "Cancelled"
             WHERE id = :id AND organization_id = :organization_id AND status = "Draft"'
        );
        $statement->execute(['id' => (int) $matches[1], 'organization_id' => $orgId]);
        if ($statement->rowCount() !== 1) {
            $check = db()->prepare(
                'SELECT status FROM guided_assessments
                 WHERE id = :id AND organization_id = :organization_id'
            );
            $check->execute(['id' => (int) $matches[1], 'organization_id' => $orgId]);
            $status = $check->fetchColumn();
            if ($status === false) {
                throw new ApiException(404, 'Assessment not found.');
            }
            throw new ApiException(409, 'Only draft assessments can be cancelled.');
        }
        json_response(['data' => ['cancelled' => true]]);
    }

    if (preg_match('#^/assessments/guided/([1-9][0-9]*)$#', $route, $matches) === 1 && $method === 'GET') {
        $statement = db()->prepare(
            'SELECT ga.id, ga.asset_id, ga.engagement_id, ga.title, ga.scope, ga.frameworks_json, ga.status,
                    ga.overall_score, ga.created_at, ga.updated_at, ga.completed_at,
                    a.name AS asset_name, a.type AS asset_type, ga.completed_assessment_id,
                    e.title AS engagement_title, e.scope_text AS engagement_scope,
                    e.exclusions AS engagement_exclusions, e.rules_of_engagement,
                    e.lead_name AS engagement_lead, e.authorization_note AS engagement_authorization_note,
                    e.start_date AS engagement_start_date, e.end_date AS engagement_end_date
             FROM guided_assessments ga
             JOIN assets a ON a.id = ga.asset_id AND a.organization_id = ga.organization_id
             LEFT JOIN assessment_engagements e
                    ON e.id = ga.engagement_id AND e.organization_id = ga.organization_id
             WHERE ga.id = :id AND ga.organization_id = :organization_id LIMIT 1'
        );
        $statement->execute(['id' => (int) $matches[1], 'organization_id' => $orgId]);
        $assessment = $statement->fetch();
        if ($assessment === false) {
            throw new ApiException(404, 'Assessment not found.');
        }
        $assessment['id'] = (int) $assessment['id'];
        $assessment['asset_id'] = (int) $assessment['asset_id'];
        $assessment['engagement_id'] = $assessment['engagement_id'] === null
            ? null
            : (int) $assessment['engagement_id'];
        $assessment['overall_score'] = $assessment['overall_score'] === null
            ? null
            : (int) $assessment['overall_score'];
        $assessment['frameworks'] = json_decode($assessment['frameworks_json'], true, 16, JSON_THROW_ON_ERROR);
        unset($assessment['frameworks_json']);
        $answersQuery = db()->prepare(
            'SELECT control_id, answer, notes, updated_at
             FROM guided_assessment_answers
             WHERE organization_id = :organization_id AND guided_assessment_id = :assessment_id'
        );
        $answersQuery->execute(['organization_id' => $orgId, 'assessment_id' => $assessment['id']]);
        $answers = [];
        foreach ($answersQuery->fetchAll() as $answer) {
            $answers[$answer['control_id']] = [
                'answer' => $answer['answer'],
                'notes' => $answer['notes'],
                'updated_at' => $answer['updated_at'],
            ];
        }
        $frameworkSet = array_flip($assessment['frameworks']);
        $frameworkNames = [];
        foreach (assessment_frameworks() as $framework) {
            $frameworkNames[$framework['id']] = $framework['name'];
        }
        $controls = array_values(array_filter(
            assessment_controls_for_asset_type($assessment['asset_type']),
            static fn (array $control): bool => count(array_intersect($control['frameworks'], array_keys($frameworkSet))) > 0
        ));
        foreach ($controls as &$control) {
            $control['mapped_baselines'] = array_values(array_map(
                static fn (string $frameworkId): string => $frameworkNames[$frameworkId],
                array_values(array_intersect($control['frameworks'], $assessment['frameworks']))
            ));
        }
        unset($control);
        $statement = db()->prepare(
            'SELECT id, title, severity, status, recommendation
             FROM findings
             WHERE organization_id = :organization_id
               AND assessment_id = :assessment_id
             ORDER BY FIELD(severity, "Critical", "High", "Medium", "Low"), id'
        );
        $statement->execute([
            'organization_id' => $orgId,
            'assessment_id' => $assessment['completed_assessment_id'],
        ]);
        $assessment['controls'] = $controls;
        $assessment['answers'] = $answers;
        $assessment['findings'] = $statement->fetchAll();
        json_response(['data' => ['assessment' => $assessment]]);
    }

    if (preg_match('#^/assessments/guided/([1-9][0-9]*)/answers$#', $route, $matches) === 1 && $method === 'POST') {
        require_write_role($user);
        $input = request_json();
        $answers = $input['answers'] ?? null;
        if (!is_array($answers)) {
            throw new ApiException(422, 'Assessment responses must be provided as a list.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            save_guided_assessment_answers((int) $matches[1], $orgId, $answers);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        json_response(['data' => ['saved' => count($answers)]]);
    }

    if (preg_match('#^/assessments/guided/([1-9][0-9]*)/complete$#', $route, $matches) === 1 && $method === 'POST') {
        require_write_role($user);
        $input = request_json();
        $answers = $input['answers'] ?? null;
        if (!is_array($answers)) {
            throw new ApiException(422, 'Save the assessment responses before completing it.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'SELECT ga.id, ga.asset_id, ga.title, ga.scope, ga.frameworks_json, ga.status,
                        a.name AS asset_name, a.type AS asset_type
                 FROM guided_assessments ga
                 JOIN assets a ON a.id = ga.asset_id AND a.organization_id = ga.organization_id
                 WHERE ga.id = :id AND ga.organization_id = :organization_id
                 '
            );
            $statement->execute(['id' => (int) $matches[1], 'organization_id' => $orgId]);
            $assessment = $statement->fetch();
            if ($assessment === false) {
                throw new ApiException(404, 'Assessment not found.');
            }
            if ($assessment['status'] === 'Completed') {
                throw new ApiException(409, 'This assessment has already been completed.');
            }
            $frameworks = json_decode($assessment['frameworks_json'], true, 16, JSON_THROW_ON_ERROR);
            $controls = array_values(array_filter(
                assessment_controls_for_asset_type($assessment['asset_type']),
                static fn (array $control): bool => count(array_intersect($control['frameworks'], $frameworks)) > 0
            ));
            if ($controls === []) {
                throw new ApiException(422, 'The selected baselines do not include any assessment questions.');
            }
            save_guided_assessment_answers((int) $assessment['id'], $orgId, $answers);
            $answerQuery = $pdo->prepare(
                'SELECT control_id, answer, notes FROM guided_assessment_answers
                 WHERE organization_id = :organization_id AND guided_assessment_id = :assessment_id'
            );
            $answerQuery->execute(['organization_id' => $orgId, 'assessment_id' => (int) $assessment['id']]);
            $savedAnswers = [];
            foreach ($answerQuery->fetchAll() as $answer) {
                $savedAnswers[$answer['control_id']] = $answer;
            }
            $missing = array_values(array_filter(
                $controls,
                static fn (array $control): bool => !isset($savedAnswers[$control['id']])
            ));
            if ($missing !== []) {
                throw new ApiException(422, 'Answer every assessment question, including "Not applicable", before completing.');
            }

            $scorePoints = 0;
            $applicableWeight = 0;
            foreach ($controls as $control) {
                $answer = $savedAnswers[$control['id']]['answer'];
                if ($answer === 'not_applicable') {
                    continue;
                }
                $applicableWeight += $control['weight'];
                $scorePoints += $control['weight'] * match ($answer) {
                    'implemented' => 100,
                    'partial' => 50,
                    default => 0,
                };
            }
            if ($applicableWeight === 0) {
                throw new ApiException(422, 'At least one assessment question must apply before completing.');
            }
            $maturityScore = (int) round($scorePoints / $applicableWeight);
            $riskScore = 100 - $maturityScore;
            $riskLevel = match (true) {
                $riskScore >= 80 => 'Critical',
                $riskScore >= 60 => 'High',
                $riskScore >= 35 => 'Moderate',
                default => 'Low',
            };
            $assessmentSummary = 'Guided assessment completed: ' . $maturityScore . '% control coverage across '
                . count($controls) . ' questions and ' . count($frameworks) . ' selected baseline(s). '
                . 'This is a self-assessment indicator, not a certification.';
            $statement = $pdo->prepare(
                'INSERT INTO assessments
                    (organization_id, asset_id, assessor_id, assessment_type, summary, risk_score)
                 VALUES
                    (:organization_id, :asset_id, :assessor_id, "manual_review", :summary, :risk_score)'
            );
            $statement->execute([
                'organization_id' => $orgId,
                'asset_id' => (int) $assessment['asset_id'],
                'assessor_id' => (int) $user['id'],
                'summary' => $assessmentSummary,
                'risk_score' => $riskScore,
            ]);
            $baseAssessmentId = (int) $pdo->lastInsertId();
            $controlTitleParameters = [];
            $controlTitlePlaceholders = [];
            foreach ($controls as $index => $control) {
                $parameter = ':control_title_' . $index;
                $controlTitlePlaceholders[] = $parameter;
                $controlTitleParameters[$parameter] = 'Assessment follow-up: ' . $control['title'];
            }
            $resolvePriorFindings = $pdo->prepare(
                'UPDATE findings f
                 JOIN guided_assessments previous ON previous.organization_id = f.organization_id
                                                   AND previous.completed_assessment_id = f.assessment_id
                 SET f.status = "Resolved", f.resolved_at = UTC_TIMESTAMP()
                 WHERE f.organization_id = :organization_id
                   AND f.asset_id = :asset_id
                   AND previous.status = "Completed"
                   AND f.status <> "Resolved"
                   AND f.title IN (' . implode(', ', $controlTitlePlaceholders) . ')'
            );
            $resolvePriorFindings->execute(array_merge([
                'organization_id' => $orgId,
                'asset_id' => (int) $assessment['asset_id'],
            ], $controlTitleParameters));
            $findingInsert = $pdo->prepare(
                'INSERT INTO findings
                    (organization_id, asset_id, assessment_id, title, description,
                     evidence, recommendation, severity, created_by)
                 VALUES
                    (:organization_id, :asset_id, :assessment_id, :title, :description,
                     :evidence, :recommendation, :severity, :created_by)'
            );
            $findingsCreated = 0;
            foreach ($controls as $control) {
                $answer = $savedAnswers[$control['id']];
                if (in_array($answer['answer'], ['implemented', 'not_applicable'], true)) {
                    continue;
                }
                $mappedNames = [];
                foreach ($control['frameworks'] as $frameworkId) {
                    if (in_array($frameworkId, $frameworks, true)) {
                        foreach (assessment_frameworks() as $framework) {
                            if ($framework['id'] === $frameworkId) {
                                $mappedNames[] = $framework['name'];
                                break;
                            }
                        }
                    }
                }
                $severity = match (true) {
                    $answer['answer'] === 'not_sure' => 'Low',
                    $answer['answer'] === 'not_implemented' && $control['weight'] >= 5 => 'Critical',
                    $answer['answer'] === 'not_implemented' && $control['weight'] >= 4 => 'High',
                    $answer['answer'] === 'not_implemented' => 'Medium',
                    $control['weight'] >= 5 => 'High',
                    default => 'Medium',
                };
                $answerLabel = match ($answer['answer']) {
                    'partial' => 'Partly in place',
                    'not_implemented' => 'Not in place',
                    default => 'Needs verification',
                };
                $findingInsert->execute([
                    'organization_id' => $orgId,
                    'asset_id' => (int) $assessment['asset_id'],
                    'assessment_id' => $baseAssessmentId,
                    'title' => 'Assessment follow-up: ' . $control['title'],
                    'description' => $answerLabel . '. ' . $control['question']
                        . ($answer['answer'] === 'not_sure'
                            ? ' This answer needs verification and does not by itself confirm a security weakness.'
                            : '')
                        . ' Mapped themes: ' . implode(', ', $mappedNames) . '.',
                    'evidence' => $answer['notes'] === '' ? null : $answer['notes'],
                    'recommendation' => $control['recommendation'],
                    'severity' => $severity,
                    'created_by' => (int) $user['id'],
                ]);
                $findingsCreated++;
            }
            $pdo->prepare(
                'UPDATE assets SET risk_level = :risk_level
                 WHERE id = :asset_id AND organization_id = :organization_id'
            )->execute([
                'risk_level' => $riskLevel,
                'asset_id' => (int) $assessment['asset_id'],
                'organization_id' => $orgId,
            ]);
            $pdo->prepare(
                'UPDATE guided_assessments
                 SET status = "Completed", overall_score = :score,
                     completed_assessment_id = :base_assessment_id, completed_at = UTC_TIMESTAMP()
                 WHERE id = :id AND organization_id = :organization_id'
            )->execute([
                'score' => $maturityScore,
                'base_assessment_id' => $baseAssessmentId,
                'id' => (int) $assessment['id'],
                'organization_id' => $orgId,
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        json_response(['data' => [
            'completed' => true,
            'id' => (int) $assessment['id'],
            'maturity_score' => $maturityScore,
            'risk_level' => $riskLevel,
            'findings_created' => $findingsCreated,
            'summary' => $assessmentSummary,
        ]]);
    }

    if (preg_match('#^/assets/([1-9][0-9]*)/scan$#', $route, $matches) === 1 && $method === 'GET') {
        $statement = db()->prepare(
            'SELECT id FROM assets
             WHERE id = :id AND organization_id = :organization_id LIMIT 1'
        );
        $statement->execute(['id' => (int) $matches[1], 'organization_id' => $orgId]);
        if ($statement->fetchColumn() === false) {
            throw new ApiException(404, 'Asset not found.');
        }

        $statement = db()->prepare(
            'SELECT ws.id, ws.completed_at, a.risk_score, a.summary, ws.checks_json,
                    (SELECT COUNT(*) FROM findings f
                     WHERE f.organization_id = ws.organization_id
                       AND f.assessment_id = ws.assessment_id) AS findings_created
             FROM web_scans ws
             JOIN assessments a ON a.id = ws.assessment_id
                               AND a.organization_id = ws.organization_id
             WHERE ws.asset_id = :asset_id AND ws.organization_id = :organization_id
             ORDER BY ws.id DESC LIMIT 1'
        );
        $statement->execute(['asset_id' => (int) $matches[1], 'organization_id' => $orgId]);
        $scan = $statement->fetch();
        if ($scan !== false) {
            $scan['id'] = (int) $scan['id'];
            $scan['risk_score'] = (int) $scan['risk_score'];
            $scan['findings_created'] = (int) $scan['findings_created'];
            $scan['risk_level'] = match (true) {
                $scan['risk_score'] >= 80 => 'Critical',
                $scan['risk_score'] >= 60 => 'High',
                $scan['risk_score'] >= 35 => 'Moderate',
                default => 'Low',
            };
            $scan['checks'] = json_decode($scan['checks_json'], true, 32, JSON_THROW_ON_ERROR);
            unset($scan['checks_json']);
        }
        json_response(['data' => ['scan' => $scan === false ? null : $scan]]);
    }

    if (preg_match('#^/assets/([1-9][0-9]*)/scan$#', $route, $matches) === 1 && $method === 'POST') {
        require_write_role($user);
        $input = request_json();
        if (($input['authorization_confirmed'] ?? false) !== true) {
            throw new ApiException(422, 'Confirm that your organisation is authorised to review this asset.');
        }

        $pdo = db();
        $assetQuery = $pdo->prepare(
            'SELECT id, type, target, status, authorization_confirmed
             FROM assets
             WHERE id = :id AND organization_id = :organization_id
             LIMIT 1'
        );
        $assetQuery->execute(['id' => (int) $matches[1], 'organization_id' => $orgId]);
        $asset = $assetQuery->fetch();
        if ($asset === false) {
            throw new ApiException(404, 'Asset not found.');
        }
        if (!in_array($asset['type'], ['Website', 'API'], true)) {
            throw new ApiException(422, 'Safe automatic reviews are currently available for Website and API assets only.');
        }
        if ((int) $asset['authorization_confirmed'] !== 1) {
            throw new ApiException(403, 'Confirm ownership or explicit permission before reviewing this asset.');
        }
        if ($asset['status'] === 'Archived') {
            throw new ApiException(422, 'Archived assets cannot be reviewed.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT IGNORE INTO asset_scan_limits (organization_id, asset_id, last_requested_at)
                 VALUES (:organization_id, :asset_id, NULL)'
            )->execute(['organization_id' => $orgId, 'asset_id' => (int) $asset['id']]);
            $limitQuery = $pdo->prepare(
                'SELECT last_requested_at,
                        last_requested_at > UTC_TIMESTAMP() - INTERVAL 60 SECOND AS is_limited
                 FROM asset_scan_limits
                 WHERE organization_id = :organization_id AND asset_id = :asset_id
                 FOR UPDATE'
            );
            $limitQuery->execute(['organization_id' => $orgId, 'asset_id' => (int) $asset['id']]);
            $limit = $limitQuery->fetch();
            if ((int) $limit['is_limited'] === 1) {
                $pdo->commit();
                throw new ApiException(429, 'Please wait one minute between reviews of the same asset.');
            }
            $pdo->prepare(
                'UPDATE asset_scan_limits SET last_requested_at = UTC_TIMESTAMP()
                 WHERE organization_id = :organization_id AND asset_id = :asset_id'
            )->execute(['organization_id' => $orgId, 'asset_id' => (int) $asset['id']]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        $review = run_safe_asset_review($asset['target'], $asset['type']);
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'INSERT INTO assessments
                    (organization_id, asset_id, assessor_id, assessment_type, summary, risk_score)
                 VALUES
                    (:organization_id, :asset_id, :assessor_id, "safe_web_review", :summary, :risk_score)'
            );
            $statement->execute([
                'organization_id' => $orgId,
                'asset_id' => (int) $asset['id'],
                'assessor_id' => (int) $user['id'],
                'summary' => $review['summary'],
                'risk_score' => $review['risk_score'],
            ]);
            $assessmentId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'UPDATE findings f
                 JOIN assessments previous ON previous.organization_id = f.organization_id
                                           AND previous.id = f.assessment_id
                 SET f.status = "Resolved", f.resolved_at = UTC_TIMESTAMP()
                 WHERE f.organization_id = :organization_id
                   AND f.asset_id = :asset_id
                   AND previous.assessment_type = "safe_web_review"
                   AND f.status <> "Resolved"'
            )->execute([
                'organization_id' => $orgId,
                'asset_id' => (int) $asset['id'],
            ]);
            $generatedFindings = 0;
            $findingInsert = $pdo->prepare(
                'INSERT INTO findings
                    (organization_id, asset_id, assessment_id, title, description,
                     evidence, recommendation, severity, created_by)
                 VALUES
                    (:organization_id, :asset_id, :assessment_id, :title, :description,
                     :evidence, :recommendation, :severity, :created_by)'
            );
            foreach ($review['checks'] as $check) {
                if ($check['status'] !== 'fail') {
                    continue;
                }
                $findingInsert->execute([
                    'organization_id' => $orgId,
                    'asset_id' => (int) $asset['id'],
                    'assessment_id' => $assessmentId,
                    'title' => $asset['type'] . ' review: ' . $check['title'],
                    'description' => $check['evidence'],
                    'evidence' => $check['evidence'],
                    'recommendation' => $check['recommendation'],
                    'severity' => $check['severity'],
                    'created_by' => (int) $user['id'],
                ]);
                $generatedFindings++;
            }
            $statement = $pdo->prepare(
                'INSERT INTO web_scans
                    (organization_id, asset_id, assessment_id, scanner_id, checks_json)
                 VALUES
                    (:organization_id, :asset_id, :assessment_id, :scanner_id, :checks_json)'
            );
            $statement->execute([
                'organization_id' => $orgId,
                'asset_id' => (int) $asset['id'],
                'assessment_id' => $assessmentId,
                'scanner_id' => (int) $user['id'],
                'checks_json' => json_encode($review['checks'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ]);
            $pdo->prepare(
                'UPDATE assets SET risk_level = :risk_level
                 WHERE id = :id AND organization_id = :organization_id'
            )->execute([
                'risk_level' => $review['risk_level'],
                'id' => (int) $asset['id'],
                'organization_id' => $orgId,
            ]);
            $scanId = (int) $pdo->lastInsertId();
            $completedAtQuery = $pdo->prepare(
                'SELECT completed_at FROM web_scans
                 WHERE id = :id AND organization_id = :organization_id'
            );
            $completedAtQuery->execute(['id' => $scanId, 'organization_id' => $orgId]);
            $completedAt = $completedAtQuery->fetchColumn();
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        json_response(['data' => [
            'scan' => [
                'id' => $scanId,
                'completed_at' => $completedAt,
                'risk_score' => $review['risk_score'],
                'risk_level' => $review['risk_level'],
                'summary' => $review['summary'],
                'findings_created' => $generatedFindings,
                'checks' => $review['checks'],
            ],
        ]], 201);
    }

    if ($route === '/assets' && $method === 'POST') {
        require_write_role($user);
        $input = request_json();
        $name = required_string($input, 'name', 160, 'Asset name');
        $type = required_string($input, 'type', 32, 'Asset type');
        $target = required_string($input, 'target', 2048, 'Asset target');
        $owner = optional_string($input, 'owner_team', 120, 'Owner team');
        $authorizationNote = required_string($input, 'authorization_note', 500, 'Authorisation details');
        if (($input['authorization_confirmed'] ?? false) !== true) {
            throw new ApiException(422, 'Confirm that your organisation owns or is authorised to manage this asset.');
        }

        $types = ['Website', 'Web application', 'API', 'Domain', 'Database', 'Server'];
        if (!in_array($type, $types, true)) {
            throw new ApiException(422, 'Choose a supported asset type.');
        }
        $allowedTarget = match ($type) {
            'Website', 'Web application' => filter_var($target, FILTER_VALIDATE_URL) !== false
                && in_array(strtolower((string) parse_url($target, PHP_URL_SCHEME)), ['http', 'https'], true)
                && filter_var((string) parse_url($target, PHP_URL_HOST), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false,
            'API' => filter_var($target, FILTER_VALIDATE_URL) !== false
                && strtolower((string) parse_url($target, PHP_URL_SCHEME)) === 'https'
                && filter_var((string) parse_url($target, PHP_URL_HOST), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
                && parse_url($target, PHP_URL_USER) === null
                && parse_url($target, PHP_URL_PASS) === null
                && parse_url($target, PHP_URL_QUERY) === null
                && parse_url($target, PHP_URL_FRAGMENT) === null
                && (parse_url($target, PHP_URL_PORT) === null || (int) parse_url($target, PHP_URL_PORT) === 443),
            'Domain' => filter_var($target, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false,
            default => filter_var($target, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
                || filter_var($target, FILTER_VALIDATE_IP) !== false,
        };
        if (!$allowedTarget) {
            throw new ApiException(422, 'Enter a valid URL or hostname for this asset type.');
        }

        $statement = db()->prepare(
            'INSERT INTO assets
               (organization_id, name, type, target, owner_team, authorization_confirmed,
                authorization_note, authorized_by)
             VALUES
               (:organization_id, :name, :type, :target, :owner_team, 1, :authorization_note, :authorized_by)'
        );
        $statement->execute([
            'organization_id' => $orgId,
            'name' => $name,
            'type' => $type,
            'target' => $target,
            'owner_team' => $owner,
            'authorization_note' => $authorizationNote,
            'authorized_by' => (int) $user['id'],
        ]);
        json_response(['data' => ['id' => (int) db()->lastInsertId()]], 201);
    }

    if (preg_match('#^/assets/([1-9][0-9]*)$#', $route, $matches) === 1 && $method === 'PATCH') {
        require_write_role($user);
        $input = request_json();
        $status = required_string($input, 'status', 20, 'Status');
        if (!in_array($status, ['Active', 'Monitoring', 'Needs review', 'Archived'], true)) {
            throw new ApiException(422, 'Choose a valid asset status.');
        }
        $statement = db()->prepare(
            'UPDATE assets SET status = :status
             WHERE id = :id AND organization_id = :organization_id'
        );
        $statement->execute([
            'status' => $status,
            'id' => (int) $matches[1],
            'organization_id' => $orgId,
        ]);
        if ($statement->rowCount() === 0) {
            $check = db()->prepare(
                'SELECT id FROM assets WHERE id = :id AND organization_id = :organization_id'
            );
            $check->execute(['id' => (int) $matches[1], 'organization_id' => $orgId]);
            if ($check->fetchColumn() === false) {
                throw new ApiException(404, 'Asset not found.');
            }
        }
        json_response(['data' => ['updated' => true]]);
    }

    if (preg_match('#^/assets/([1-9][0-9]*)$#', $route, $matches) === 1 && $method === 'DELETE') {
        require_write_role($user);
        $pdo = db();
        $assetId = (int) $matches[1];
        $pdo->beginTransaction();
        try {
            $assetQuery = $pdo->prepare(
                'SELECT id FROM assets
                 WHERE id = :id AND organization_id = :organization_id
                 FOR UPDATE'
            );
            $assetQuery->execute(['id' => $assetId, 'organization_id' => $orgId]);
            if ($assetQuery->fetchColumn() === false) {
                throw new ApiException(404, 'Asset not found.');
            }
            $pdo->prepare(
                'DELETE FROM findings WHERE asset_id = :asset_id AND organization_id = :organization_id'
            )->execute(['asset_id' => $assetId, 'organization_id' => $orgId]);
            $pdo->prepare(
                'DELETE FROM assets WHERE id = :id AND organization_id = :organization_id'
            )->execute(['id' => $assetId, 'organization_id' => $orgId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        json_response(['data' => ['deleted' => true]]);
    }

    if ($route === '/assessments' && $method === 'POST') {
        require_write_role($user);
        $input = request_json();
        $assetId = filter_var($input['asset_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $summary = required_string($input, 'summary', 500, 'Assessment summary');
        $riskScore = filter_var($input['risk_score'] ?? null, FILTER_VALIDATE_INT);
        if ($assetId === false || $riskScore === false || $riskScore < 0 || $riskScore > 100) {
            throw new ApiException(422, 'Choose an asset and enter a risk score from 0 to 100.');
        }

        $pdo = db();
        $assetQuery = $pdo->prepare(
            'SELECT id FROM assets
             WHERE id = :id AND organization_id = :organization_id
               AND authorization_confirmed = 1 AND status <> "Archived"
             LIMIT 1'
        );
        $assetQuery->execute(['id' => $assetId, 'organization_id' => $orgId]);
        if ($assetQuery->fetchColumn() === false) {
            throw new ApiException(404, 'Authorised asset not found.');
        }

        $riskLevel = match (true) {
            $riskScore >= 80 => 'Critical',
            $riskScore >= 60 => 'High',
            $riskScore >= 35 => 'Moderate',
            default => 'Low',
        };
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'INSERT INTO assessments
                    (organization_id, asset_id, assessor_id, summary, risk_score)
                 VALUES
                    (:organization_id, :asset_id, :assessor_id, :summary, :risk_score)'
            );
            $statement->execute([
                'organization_id' => $orgId,
                'asset_id' => $assetId,
                'assessor_id' => (int) $user['id'],
                'summary' => $summary,
                'risk_score' => $riskScore,
            ]);
            $assessmentId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'UPDATE assets SET risk_level = :risk_level
                 WHERE id = :id AND organization_id = :organization_id'
            )->execute([
                'risk_level' => $riskLevel,
                'id' => $assetId,
                'organization_id' => $orgId,
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        json_response(['data' => ['id' => $assessmentId, 'risk_level' => $riskLevel]], 201);
    }

    if ($route === '/findings' && $method === 'POST') {
        require_write_role($user);
        $input = request_json();
        $assetId = filter_var($input['asset_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($assetId === false) {
            throw new ApiException(422, 'Choose an asset for this finding.');
        }
        $title = required_string($input, 'title', 180, 'Finding title');
        $description = required_string($input, 'description', 10000, 'Description');
        $evidence = optional_string($input, 'evidence', 10000, 'Evidence');
        $recommendation = required_string($input, 'recommendation', 10000, 'Recommended action');
        $severity = required_string($input, 'severity', 10, 'Severity');
        if (!in_array($severity, ['Low', 'Medium', 'High', 'Critical'], true)) {
            throw new ApiException(422, 'Choose a valid finding severity.');
        }

        $pdo = db();
        $assetQuery = $pdo->prepare(
            'SELECT id FROM assets
             WHERE id = :id AND organization_id = :organization_id
               AND authorization_confirmed = 1 AND status <> "Archived"
             LIMIT 1'
        );
        $assetQuery->execute(['id' => $assetId, 'organization_id' => $orgId]);
        if ($assetQuery->fetchColumn() === false) {
            throw new ApiException(404, 'Authorised asset not found.');
        }
        $statement = $pdo->prepare(
            'INSERT INTO findings
                (organization_id, asset_id, title, description, evidence, recommendation, severity, created_by)
             VALUES
                (:organization_id, :asset_id, :title, :description, :evidence, :recommendation, :severity, :created_by)'
        );
        $statement->execute([
            'organization_id' => $orgId,
            'asset_id' => $assetId,
            'title' => $title,
            'description' => $description,
            'evidence' => $evidence === '' ? null : $evidence,
            'recommendation' => $recommendation,
            'severity' => $severity,
            'created_by' => (int) $user['id'],
        ]);
        json_response(['data' => ['id' => (int) $pdo->lastInsertId()]], 201);
    }

    if ($route === '/findings' && $method === 'GET') {
        $assetId = null;
        if (isset($_GET['asset_id']) && $_GET['asset_id'] !== '') {
            $assetId = filter_var($_GET['asset_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($assetId === false) {
                throw new ApiException(422, 'Choose a valid asset.');
            }
        }
        $assetCondition = $assetId === null ? '' : ' AND f.asset_id = :asset_id';
        $statement = db()->prepare(
            'SELECT f.id, f.asset_id, f.title, f.description, f.evidence, f.recommendation, f.severity,
                    f.status, f.created_at, f.updated_at, a.name AS asset_name
             FROM findings f JOIN assets a ON a.id = f.asset_id AND a.organization_id = f.organization_id
             WHERE f.organization_id = :organization_id
             ' . $assetCondition . '
             ORDER BY FIELD(f.status, "Open", "In progress", "Resolved"),
                      FIELD(f.severity, "Critical", "High", "Medium", "Low"),
                      f.created_at DESC LIMIT 100'
        );
        $parameters = ['organization_id' => $orgId];
        if ($assetId !== null) {
            $parameters['asset_id'] = (int) $assetId;
        }
        $statement->execute($parameters);
        json_response(['data' => ['findings' => $statement->fetchAll()]]);
    }

    if (preg_match('#^/findings/([1-9][0-9]*)$#', $route, $matches) === 1 && $method === 'PATCH') {
        require_write_role($user);
        $input = request_json();
        $status = required_string($input, 'status', 20, 'Status');
        if (!in_array($status, ['Open', 'In progress', 'Resolved'], true)) {
            throw new ApiException(422, 'Choose a valid finding status.');
        }
        $statement = db()->prepare(
            'UPDATE findings SET status = :status,
                resolved_at = CASE WHEN :resolved = "Resolved" THEN UTC_TIMESTAMP() ELSE NULL END
             WHERE id = :id AND organization_id = :organization_id'
        );
        $statement->execute([
            'status' => $status,
            'resolved' => $status,
            'id' => (int) $matches[1],
            'organization_id' => $orgId,
        ]);
        if ($statement->rowCount() === 0) {
            $check = db()->prepare(
                'SELECT id FROM findings WHERE id = :id AND organization_id = :organization_id'
            );
            $check->execute(['id' => (int) $matches[1], 'organization_id' => $orgId]);
            if ($check->fetchColumn() === false) {
                throw new ApiException(404, 'Finding not found.');
            }
        }
        json_response(['data' => ['updated' => true]]);
    }

    throw new ApiException(404, 'API endpoint not found.');
} catch (ApiException $exception) {
    json_response([
        'error' => [
            'message' => $exception->getMessage(),
            'details' => $exception->details,
        ],
    ], $exception->status);
} catch (Throwable $exception) {
    error_log('TrustGrid API request failed: ' . $exception->getMessage());
    json_response(['error' => ['message' => 'The service could not complete this request.']], 500);
}
