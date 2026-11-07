<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$maximum = 5;
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--max-jobs=(\d+)$/', $argument, $matches) === 1) {
        $maximum = (int) $matches[1];
    } else {
        fwrite(STDERR, "Usage: php process-scan-queue.php [--max-jobs=1-25]\n");
        exit(2);
    }
}
if ($maximum < 1 || $maximum > 25) {
    fwrite(STDERR, "The maximum job count must be between 1 and 25.\n");
    exit(2);
}

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/platform.php';
require __DIR__ . '/../app/scan_jobs.php';

try {
    $pdo = db();
    $scheduled = queue_due_scan_schedules($pdo);
    $completed = process_scan_queue($pdo, $maximum);
    fwrite(STDOUT, "Queued scheduled reviews: {$scheduled}; completed reviews: {$completed}.\n");
} catch (Throwable $exception) {
    error_log('TrustGrid scan queue worker failed: ' . $exception->getMessage());
    fwrite(STDERR, "The scan queue worker failed. Check the server error log.\n");
    exit(1);
}
