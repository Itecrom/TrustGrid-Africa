<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/assessment_catalog.php';
require __DIR__ . '/app/pdf_report.php';

$user = require_page_user();
$assessmentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($assessmentId === false || $assessmentId === null) {
    http_response_code(400);
    exit('A valid completed assessment is required.');
}

$statement = db()->prepare(
    'SELECT ga.id, ga.title, ga.scope, ga.frameworks_json, ga.status, ga.overall_score, ga.completed_at,
            a.name AS asset_name, a.type AS asset_type
     FROM guided_assessments ga
     JOIN assets a ON a.id = ga.asset_id AND a.organization_id = ga.organization_id
     WHERE ga.id = :id AND ga.organization_id = :organization_id
     LIMIT 1'
);
$statement->execute(['id' => $assessmentId, 'organization_id' => (int) $user['organization_id']]);
$assessment = $statement->fetch();
if ($assessment === false) {
    http_response_code(404);
    exit('Assessment not found.');
}
if ($assessment['status'] !== 'Completed') {
    http_response_code(409);
    exit('Complete this assessment before downloading its PDF report.');
}

$frameworks = json_decode($assessment['frameworks_json'], true, 16, JSON_THROW_ON_ERROR);
$frameworkNames = [];
foreach (assessment_frameworks() as $framework) {
    if (in_array($framework['id'], $frameworks, true)) {
        $frameworkNames[] = $framework['name'];
    }
}
$controls = array_values(array_filter(
    assessment_controls(),
    static fn (array $control): bool => count(array_intersect($control['frameworks'], $frameworks)) > 0
));
$answerQuery = db()->prepare(
    'SELECT control_id, answer, notes
     FROM guided_assessment_answers
     WHERE organization_id = :organization_id AND guided_assessment_id = :assessment_id'
);
$answerQuery->execute([
    'organization_id' => (int) $user['organization_id'],
    'assessment_id' => (int) $assessment['id'],
]);
$answers = [];
foreach ($answerQuery->fetchAll() as $answer) {
    $answers[$answer['control_id']] = $answer;
}

$findingQuery = db()->prepare(
    'SELECT title, severity, status, description, recommendation
     FROM findings
     WHERE organization_id = :organization_id AND assessment_id = (
        SELECT completed_assessment_id
        FROM guided_assessments
        WHERE id = :assessment_id AND organization_id = :finding_organization_id
     )
     ORDER BY FIELD(severity, "Critical", "High", "Medium", "Low"), title'
);
$findingQuery->execute([
    'organization_id' => (int) $user['organization_id'],
    'assessment_id' => (int) $assessment['id'],
    'finding_organization_id' => (int) $user['organization_id'],
]);
$findings = $findingQuery->fetchAll();

$report = new TrustGridPdf('GUIDED SECURITY ASSESSMENT');
$report->title($assessment['title'], 'Completed ' . gmdate('F j, Y', strtotime($assessment['completed_at'])));
$report->paragraph(
    $user['organization_name'] . ' · ' . $user['country'] . ' · Asset: ' . $assessment['asset_name']
        . ' (' . $assessment['asset_type'] . ')',
    10,
    0,
    true,
    [0.13, 0.31, 0.43]
);
$report->paragraph('Scope: ' . $assessment['scope'], 9);
$report->paragraph('Baselines: ' . implode('; ', $frameworkNames), 9);
$report->metrics([
    ['label' => 'WEIGHTED SELF-ASSESSMENT', 'value' => (int) $assessment['overall_score'] . '% in place'],
    ['label' => 'QUESTIONS REVIEWED', 'value' => count($controls)],
    ['label' => 'FOLLOW-UP ACTIONS', 'value' => count($findings)],
    ['label' => 'REPORT PREPARED', 'value' => gmdate('F j, Y')],
]);
$report->paragraph(
    'This report reflects self-reported answers and evidence notes. Results are not independently verified and do not establish compliance, certification, or the absence of security risk.',
    8.5,
    0,
    false,
    [0.38, 0.31, 0.12]
);

$answerLabels = [
    'implemented' => 'Yes, in place',
    'partial' => 'Partly in place',
    'not_implemented' => 'Not in place',
    'not_sure' => 'Not sure — verification needed',
    'not_applicable' => 'Not applicable',
];
$currentArea = '';
foreach ($controls as $control) {
    if ($control['area'] !== $currentArea) {
        $currentArea = $control['area'];
        $report->section($currentArea);
    }
    $answer = $answers[$control['id']] ?? null;
    $report->question(
        $control['title'],
        $control['question'],
        $answerLabels[$answer['answer'] ?? ''] ?? 'No response recorded',
        $control['guidance'],
        (string) ($answer['notes'] ?? '')
    );
}

$report->section('Follow-up action plan');
if ($findings === []) {
    $report->paragraph('No follow-up actions were identified by the recorded answers.');
} else {
    foreach ($findings as $finding) {
        $report->paragraph($finding['title'] . ' · ' . $finding['severity'] . ' · ' . $finding['status'], 10, 0, true);
        $report->paragraph($finding['description'], 8.5);
        $report->paragraph('Recommended next step: ' . $finding['recommendation'], 9);
    }
}
$report->paragraph(
    'Keep evidence notes free of passwords, access tokens, and other secrets. Use an appropriately authorised security professional to verify important findings.',
    8,
    0,
    false,
    [0.38, 0.31, 0.12]
);

send_pdf_report($report, pdf_filename($assessment['title']));
