<?php
declare(strict_types=1);

require_once __DIR__ . '/safe_web_review.php';

function queue_due_scan_schedules(PDO $pdo, int $maximum = 25): int
{
    $pdo->exec(
        'UPDATE scan_schedules SET status = "Expired"
         WHERE status = "Active" AND expires_at < UTC_TIMESTAMP()'
    );
    $due = $pdo->prepare(
        'SELECT id FROM scan_schedules
         WHERE status = "Active" AND next_run_at <= UTC_TIMESTAMP()
           AND expires_at >= UTC_TIMESTAMP()
         ORDER BY next_run_at, id LIMIT :maximum'
    );
    $due->bindValue(':maximum', max(1, min(100, $maximum)), PDO::PARAM_INT);
    $due->execute();
    $scheduleIds = array_map('intval', $due->fetchAll(PDO::FETCH_COLUMN));
    $queued = 0;

    foreach ($scheduleIds as $scheduleId) {
        $pdo->beginTransaction();
        try {
            $scheduleQuery = $pdo->prepare(
                'SELECT s.*, a.type, a.status AS asset_status, a.authorization_confirmed,
                        u.status AS user_status, o.status AS organization_status
                 FROM scan_schedules s
                 JOIN assets a ON a.id = s.asset_id AND a.organization_id = s.organization_id
                 JOIN users u ON u.id = s.created_by AND u.organization_id = s.organization_id
                 JOIN organizations o ON o.id = s.organization_id
                 WHERE s.id = :id AND s.status = "Active"
                   AND s.next_run_at <= UTC_TIMESTAMP() FOR UPDATE'
            );
            $scheduleQuery->execute(['id' => $scheduleId]);
            $schedule = $scheduleQuery->fetch();
            if ($schedule === false || $schedule['status'] !== 'Active') {
                $pdo->commit();
                continue;
            }

            if (strtotime((string) $schedule['expires_at'] . ' UTC') < time()) {
                $pdo->prepare('UPDATE scan_schedules SET status = "Expired" WHERE id = :id')
                    ->execute(['id' => $scheduleId]);
                $pdo->commit();
                continue;
            }

            if (!in_array($schedule['type'], ['Website', 'API'], true)
                || $schedule['asset_status'] === 'Archived'
                || (int) $schedule['authorization_confirmed'] !== 1
                || $schedule['user_status'] !== 'active'
                || $schedule['organization_status'] !== 'active'
            ) {
                $pdo->prepare('UPDATE scan_schedules SET status = "Paused" WHERE id = :id')
                    ->execute(['id' => $scheduleId]);
                $pdo->commit();
                continue;
            }

            $pdo->prepare(
                'INSERT IGNORE INTO asset_scan_limits (organization_id, asset_id, last_requested_at)
                 VALUES (:organization_id, :asset_id, NULL)'
            )->execute([
                'organization_id' => (int) $schedule['organization_id'],
                'asset_id' => (int) $schedule['asset_id'],
            ]);
            $limitQuery = $pdo->prepare(
                'SELECT last_requested_at,
                        last_requested_at > UTC_TIMESTAMP() - INTERVAL 60 SECOND AS is_limited
                 FROM asset_scan_limits
                 WHERE organization_id = :organization_id AND asset_id = :asset_id FOR UPDATE'
            );
            $limitQuery->execute([
                'organization_id' => (int) $schedule['organization_id'],
                'asset_id' => (int) $schedule['asset_id'],
            ]);
            $limit = $limitQuery->fetch();
            if ((int) $limit['is_limited'] === 1) {
                $pdo->prepare(
                    'UPDATE scan_schedules SET next_run_at = DATE_ADD(last_requested_at, INTERVAL 61 SECOND)
                     WHERE id = :id'
                )->execute(['id' => $scheduleId]);
                $pdo->commit();
                continue;
            }

            $pendingQuery = $pdo->prepare(
                'SELECT id FROM scan_jobs
                 WHERE organization_id = :organization_id AND asset_id = :asset_id
                   AND status IN ("Queued", "Running") LIMIT 1'
            );
            $pendingQuery->execute([
                'organization_id' => (int) $schedule['organization_id'],
                'asset_id' => (int) $schedule['asset_id'],
            ]);
            if ($pendingQuery->fetchColumn() !== false) {
                $pdo->prepare(
                    'UPDATE scan_schedules SET next_run_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 MINUTE)
                     WHERE id = :id'
                )->execute(['id' => $scheduleId]);
                $pdo->commit();
                continue;
            }

            $insert = $pdo->prepare(
                'INSERT INTO scan_jobs (organization_id, asset_id, requested_by, schedule_id)
                 VALUES (:organization_id, :asset_id, :requested_by, :schedule_id)'
            );
            $insert->execute([
                'organization_id' => (int) $schedule['organization_id'],
                'asset_id' => (int) $schedule['asset_id'],
                'requested_by' => (int) $schedule['created_by'],
                'schedule_id' => $scheduleId,
            ]);
            $jobId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'UPDATE asset_scan_limits SET last_requested_at = UTC_TIMESTAMP()
                 WHERE organization_id = :organization_id AND asset_id = :asset_id'
            )->execute([
                'organization_id' => (int) $schedule['organization_id'],
                'asset_id' => (int) $schedule['asset_id'],
            ]);

            $nextRun = $schedule['frequency'] === 'daily'
                ? 'DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY)'
                : 'DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY)';
            $pdo->prepare(
                'UPDATE scan_schedules SET next_run_at = ' . $nextRun . ' WHERE id = :id'
            )->execute(['id' => $scheduleId]);
            record_user_action(
                (int) $schedule['created_by'],
                (int) $schedule['organization_id'],
                'asset.safe_review_scheduled',
                'asset',
                (int) $schedule['asset_id'],
                ['job_id' => $jobId, 'schedule_id' => $scheduleId]
            );
            $pdo->commit();
            $queued++;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    return $queued;
}

function claim_scan_job(PDO $pdo): ?array
{
    $pdo->beginTransaction();
    try {
        $staleQuery = $pdo->query(
            'SELECT id, organization_id, asset_id, requested_by
             FROM scan_jobs
             WHERE status = "Running" AND lease_expires_at < UTC_TIMESTAMP() AND attempts >= 3
             FOR UPDATE'
        );
        $staleJobs = $staleQuery->fetchAll();
        foreach ($staleJobs as $staleJob) {
            $pdo->prepare(
                'UPDATE scan_jobs
                 SET status = "Failed", completed_at = UTC_TIMESTAMP(),
                     lease_expires_at = NULL,
                     error_message = "The worker stopped before this review could finish."
                 WHERE id = :id'
            )->execute(['id' => (int) $staleJob['id']]);
            record_user_action(
                (int) $staleJob['requested_by'],
                (int) $staleJob['organization_id'],
                'asset.safe_review_failed',
                'asset',
                (int) $staleJob['asset_id'],
                ['job_id' => (int) $staleJob['id'], 'error_type' => 'WorkerLeaseExpired']
            );
        }
        $statement = $pdo->query(
            'SELECT id, organization_id, asset_id, requested_by, schedule_id, attempts
             FROM scan_jobs
             WHERE (status = "Queued" AND available_at <= UTC_TIMESTAMP())
                OR (status = "Running" AND lease_expires_at < UTC_TIMESTAMP() AND attempts < 3)
             ORDER BY available_at, id
             LIMIT 1 FOR UPDATE SKIP LOCKED'
        );
        $job = $statement->fetch();
        if ($job === false) {
            $pdo->commit();
            return null;
        }

        $attempts = (int) $job['attempts'] + 1;
        $pdo->prepare(
            'UPDATE scan_jobs
             SET status = "Running", attempts = :attempts,
                 started_at = COALESCE(started_at, UTC_TIMESTAMP()),
                 lease_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 120 SECOND),
                 error_message = ""
             WHERE id = :id'
        )->execute(['attempts' => $attempts, 'id' => (int) $job['id']]);
        $pdo->commit();
        $job['attempts'] = $attempts;
        return $job;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function complete_scan_job(PDO $pdo, array $job): void
{
    $assetQuery = $pdo->prepare(
        'SELECT a.type, a.target, a.status, a.authorization_confirmed,
                u.status AS user_status, o.status AS organization_status,
                s.status AS schedule_status, s.expires_at
         FROM assets a
         JOIN users u ON u.id = :requested_by AND u.organization_id = a.organization_id
         JOIN organizations o ON o.id = a.organization_id
         LEFT JOIN scan_schedules s ON s.id = :schedule_id
                                    AND s.organization_id = a.organization_id
                                    AND s.asset_id = a.id
         WHERE a.id = :asset_id AND a.organization_id = :organization_id'
    );
    $assetQuery->execute([
        'requested_by' => (int) $job['requested_by'],
        'schedule_id' => $job['schedule_id'] === null ? null : (int) $job['schedule_id'],
        'asset_id' => (int) $job['asset_id'],
        'organization_id' => (int) $job['organization_id'],
    ]);
    $asset = $assetQuery->fetch();
    if ($asset === false
        || !in_array($asset['type'], ['Website', 'API'], true)
        || $asset['status'] === 'Archived'
        || (int) $asset['authorization_confirmed'] !== 1
        || $asset['user_status'] !== 'active'
        || $asset['organization_status'] !== 'active'
        || ($job['schedule_id'] !== null && (
            $asset['schedule_status'] === null
            || $asset['schedule_status'] === 'Paused'
            || strtotime((string) $asset['expires_at'] . ' UTC') < time()
        ))
    ) {
        throw new ApiException(403, 'The asset or scan authorization is no longer active.');
    }

    $review = run_safe_asset_review((string) $asset['target'], (string) $asset['type']);
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'INSERT INTO assessments
                (organization_id, asset_id, assessor_id, assessment_type, summary, risk_score)
             VALUES
                (:organization_id, :asset_id, :assessor_id, "safe_web_review", :summary, :risk_score)'
        );
        $statement->execute([
            'organization_id' => (int) $job['organization_id'],
            'asset_id' => (int) $job['asset_id'],
            'assessor_id' => (int) $job['requested_by'],
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
            'organization_id' => (int) $job['organization_id'],
            'asset_id' => (int) $job['asset_id'],
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
                'organization_id' => (int) $job['organization_id'],
                'asset_id' => (int) $job['asset_id'],
                'assessment_id' => $assessmentId,
                'title' => $asset['type'] . ' review: ' . $check['title'],
                'description' => $check['evidence'],
                'evidence' => $check['evidence'],
                'recommendation' => $check['recommendation'],
                'severity' => $check['severity'],
                'created_by' => (int) $job['requested_by'],
            ]);
            $generatedFindings++;
        }

        $pdo->prepare(
            'INSERT INTO web_scans
                (organization_id, asset_id, assessment_id, scanner_id, checks_json)
             VALUES
                (:organization_id, :asset_id, :assessment_id, :scanner_id, :checks_json)'
        )->execute([
            'organization_id' => (int) $job['organization_id'],
            'asset_id' => (int) $job['asset_id'],
            'assessment_id' => $assessmentId,
            'scanner_id' => (int) $job['requested_by'],
            'checks_json' => json_encode($review['checks'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
        $scanId = (int) $pdo->lastInsertId();
        $pdo->prepare(
            'UPDATE assets SET risk_level = :risk_level
             WHERE id = :id AND organization_id = :organization_id'
        )->execute([
            'risk_level' => $review['risk_level'],
            'id' => (int) $job['asset_id'],
            'organization_id' => (int) $job['organization_id'],
        ]);
        $pdo->prepare(
            'UPDATE scan_jobs
             SET status = "Completed", completed_at = UTC_TIMESTAMP(),
                 lease_expires_at = NULL, error_message = ""
             WHERE id = :id AND status = "Running"'
        )->execute(['id' => (int) $job['id']]);
        record_user_action(
            (int) $job['requested_by'],
            (int) $job['organization_id'],
            'asset.safe_review_completed',
            'asset',
            (int) $job['asset_id'],
            [
                'job_id' => (int) $job['id'],
                'scan_id' => $scanId,
                'assessment_id' => $assessmentId,
                'findings_created' => $generatedFindings,
                'risk_level' => $review['risk_level'],
            ]
        );
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function process_scan_queue(PDO $pdo, int $maximum = 5): int
{
    $completed = 0;
    for ($index = 0; $index < max(1, min(25, $maximum)); $index++) {
        $job = claim_scan_job($pdo);
        if ($job === null) {
            break;
        }
        try {
            complete_scan_job($pdo, $job);
            $completed++;
        } catch (Throwable $exception) {
            $retryable = !($exception instanceof ApiException) || $exception->status >= 500;
            $shouldRetry = $retryable && (int) $job['attempts'] < 3;
            $message = $exception instanceof ApiException
                ? $exception->getMessage()
                : 'The review worker encountered an internal error.';
            $delay = max(60, (int) $job['attempts'] * 60);
            error_log('TrustGrid scan job ' . (int) $job['id'] . ' failed: ' . $exception->getMessage());
            $pdo->prepare(
                'UPDATE scan_jobs
                 SET status = :status, available_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . $delay . ' SECOND),
                     completed_at = CASE WHEN :finished = 1 THEN UTC_TIMESTAMP() ELSE NULL END,
                     lease_expires_at = NULL, error_message = :error
                 WHERE id = :id AND status = "Running"'
            )->execute([
                'status' => $shouldRetry ? 'Queued' : 'Failed',
                'finished' => $shouldRetry ? 0 : 1,
                'error' => mb_substr($message, 0, 500),
                'id' => (int) $job['id'],
            ]);
            if (!$shouldRetry) {
                record_user_action(
                    (int) $job['requested_by'],
                    (int) $job['organization_id'],
                    'asset.safe_review_failed',
                    'asset',
                    (int) $job['asset_id'],
                    ['job_id' => (int) $job['id'], 'error_type' => get_class($exception)]
                );
            }
        }
    }

    return $completed;
}
