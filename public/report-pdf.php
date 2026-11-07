<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/pdf_report.php';

$user = require_page_user();
$organizationId = (int) $user['organization_id'];
$pdo = db();

$statement = $pdo->prepare(
    'SELECT COUNT(*) AS total_assets, SUM(status <> "Archived") AS active_assets
     FROM assets WHERE organization_id = :organization_id'
);
$statement->execute(['organization_id' => $organizationId]);
$assetCounts = $statement->fetch();

$statement = $pdo->prepare(
    'SELECT COUNT(*) AS total,
            SUM(status = "Open") AS open_count,
            SUM(status = "In progress") AS in_progress_count,
            SUM(status = "Resolved") AS resolved_count
     FROM findings WHERE organization_id = :organization_id'
);
$statement->execute(['organization_id' => $organizationId]);
$findingCounts = $statement->fetch();

$statement = $pdo->prepare('SELECT COUNT(*) FROM assessments WHERE organization_id = :organization_id');
$statement->execute(['organization_id' => $organizationId]);
$assessmentCount = (int) $statement->fetchColumn();

$statement = $pdo->prepare(
    'SELECT a.name, a.type, a.status,
            CASE
              WHEN SUM(f.status <> "Resolved" AND f.severity = "Critical") > 0 THEN "Critical"
              WHEN SUM(f.status <> "Resolved" AND f.severity = "High") > 0 THEN "High"
              WHEN SUM(f.status <> "Resolved" AND f.severity = "Medium") > 0 THEN "Moderate"
              WHEN SUM(f.status <> "Resolved" AND f.severity = "Low") > 0 THEN "Low"
              ELSE a.risk_level
            END AS risk_level
     FROM assets a
     LEFT JOIN findings f ON f.asset_id = a.id AND f.organization_id = a.organization_id
     WHERE a.organization_id = :organization_id
     GROUP BY a.id
     ORDER BY a.created_at DESC
     LIMIT 501'
);
$statement->execute(['organization_id' => $organizationId]);
$assets = $statement->fetchAll();
$assetsTruncated = count($assets) > 500;
$assets = array_slice($assets, 0, 500);

$statement = $pdo->prepare(
    'SELECT f.title, f.severity, f.status, f.description, f.recommendation, a.name AS asset_name
     FROM findings f
     JOIN assets a ON a.id = f.asset_id AND a.organization_id = f.organization_id
     WHERE f.organization_id = :organization_id AND f.status <> "Resolved"
     ORDER BY FIELD(f.severity, "Critical", "High", "Medium", "Low"), f.created_at DESC
     LIMIT 501'
);
$statement->execute(['organization_id' => $organizationId]);
$findings = $statement->fetchAll();
$findingsTruncated = count($findings) > 500;
$findings = array_slice($findings, 0, 500);

$report = new TrustGridPdf('ORGANISATION SECURITY OVERVIEW');
$report->title($user['organization_name'], $user['country'] . ' · Prepared ' . gmdate('F j, Y'));
$report->paragraph(
    'A snapshot of assets, assessment activity, and open remediation actions recorded in this organisation workspace.',
    10
);
$report->metrics([
    ['label' => 'REGISTERED ASSETS', 'value' => (int) ($assetCounts['total_assets'] ?? 0)],
    ['label' => 'ACTIVE ASSETS', 'value' => (int) ($assetCounts['active_assets'] ?? 0)],
    ['label' => 'RECORDED ASSESSMENTS', 'value' => $assessmentCount],
    ['label' => 'OPEN FOLLOW-UP ITEMS', 'value' => (int) ($findingCounts['open_count'] ?? 0) + (int) ($findingCounts['in_progress_count'] ?? 0)],
    ['label' => 'RESOLVED FINDINGS', 'value' => (int) ($findingCounts['resolved_count'] ?? 0)],
    ['label' => 'TOTAL RECORDED FINDINGS', 'value' => (int) ($findingCounts['total'] ?? 0)],
]);
$report->paragraph(
    'This is a platform-generated overview of recorded information, not a formal security audit or certification. Risk indicators and findings should be reviewed by an appropriately authorised professional.',
    8.5,
    0,
    false,
    [0.38, 0.31, 0.12]
);

$report->section('Asset risk overview');
if ($assets === []) {
    $report->paragraph('No assets are registered in this organisation workspace.');
} else {
    foreach ($assets as $asset) {
        $report->paragraph($asset['name'] . ' · ' . $asset['type'], 10, 0, true);
        $report->paragraph('Risk indicator: ' . $asset['risk_level'] . ' · Status: ' . $asset['status'], 8.5);
    }
}
if ($assetsTruncated) {
    $report->paragraph('This list is limited to the 500 most recently registered assets.', 8, 0, false, [0.38, 0.31, 0.12]);
}

$report->section('Open findings and recommended actions');
if ($findings === []) {
    $report->paragraph('No open or in-progress findings are currently recorded.');
} else {
    foreach ($findings as $finding) {
        $report->paragraph(
            $finding['title'] . ' · ' . $finding['severity'] . ' · ' . $finding['status'],
            10,
            0,
            true
        );
        $report->paragraph('Asset: ' . $finding['asset_name'], 8.5, 0, false, [0.35, 0.40, 0.47]);
        $report->paragraph($finding['description'], 8.5);
        $report->paragraph('Recommended next step: ' . $finding['recommendation'], 9);
    }
}
if ($findingsTruncated) {
    $report->paragraph('This list is limited to the 500 highest-priority open findings.', 8, 0, false, [0.38, 0.31, 0.12]);
}
$report->section('Interpretation');
$report->paragraph(
    'Guided assessment scores are weighted self-reported indicators. Safe website reviews are limited, non-invasive configuration checks. Neither is a penetration test, formal compliance assessment, nor certification.'
);

send_pdf_report($report, pdf_filename($user['organization_name'] . '-security-overview'));
