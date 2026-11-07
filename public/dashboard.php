<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$navUser = require_page_user();
$pageTitle = 'Security overview';
$activePage = 'dashboard';
include dirname(__DIR__) . '/app/header.php';
?>
<main class="workspace-main" data-workspace-page="dashboard">
  <section class="workspace-welcome"><div class="container welcome-row"><div><p class="eyebrow">ORGANISATION OVERVIEW</p><h1>Good to see you, <?= h(explode(' ', $navUser['full_name'])[0]) ?>.</h1><p>A clear view of your digital assets and the security work ahead.</p></div><a class="button button-primary" href="<?= h(app_url('assets.php')) ?>">Add a digital asset <span>＋</span></a></div></section>
  <div class="container workspace-content">
    <div class="dashboard-title-row"><div><h2><?= h($navUser['organization_name']) ?></h2><span class="country-pill"><?= h($navUser['country']) ?> · <?= h(ucfirst($navUser['role'])) ?></span></div><span class="live-label"><i></i> PRIVATE WORKSPACE</span></div>
    <section class="stats-grid workspace-stats" aria-label="Security summary">
      <article class="stat-card"><span>REGISTERED ASSETS</span><strong data-stat="assets">—</strong><small>Digital services in your inventory</small></article>
      <article class="stat-card"><span>ASSESSMENTS</span><strong data-stat="assessments">—</strong><small>Recorded manual reviews</small></article>
      <article class="stat-card"><span>OPEN CRITICAL</span><strong data-stat="critical" class="metric-danger">—</strong><small>Highest priority findings</small></article>
      <article class="stat-card"><span>RESOLVED FINDINGS</span><strong data-stat="resolved" class="metric-good">—</strong><small>Improvements recorded</small></article>
    </section>
    <div class="dashboard-grid">
      <article class="panel panel-large">
        <div class="panel-head"><div><h2>Risk score trend</h2><p>Average manual assessment score · 0–100</p></div><span class="badge-pill">LAST 6 MONTHS</span></div>
        <div class="risk-chart" id="riskChart" role="img" aria-label="Risk score trend from manual assessments"></div><p class="chart-note">Scores are platform-generated monitoring indicators, not a security certification. More assessments improve the trend view.</p>
      </article>
      <article class="panel">
        <div class="panel-head"><div><h2>Priority findings</h2><p>Open issues recorded by your team.</p></div></div>
        <div class="list-stack" id="findingList"><p class="loading-state">Loading your findings…</p></div>
        <a class="text-link" href="<?= h(app_url('findings.php')) ?>">View all findings <span>→</span></a>
      </article>
    </div>
    <section class="panel table-panel">
      <div class="panel-head"><div><h2>Digital asset overview</h2><p>Your organisation’s registered services and current risk indicators.</p></div><a class="button button-outline button-small" href="<?= h(app_url('assets.php')) ?>">Manage assets <span>→</span></a></div>
      <div class="table-wrap"><table><thead><tr><th>ASSET</th><th>TYPE</th><th>OWNER TEAM</th><th>RISK INDICATOR</th><th>STATUS</th></tr></thead><tbody id="assetTableBody"><tr><td colspan="5" class="loading-state">Loading assets…</td></tr></tbody></table></div>
    </section>
    <p class="data-boundary-note"><span>◇</span> Your organisation controls its own asset inventory and security findings. No asset is scanned automatically.</p>
  </div>
</main>
<?php include dirname(__DIR__) . '/app/footer.php'; ?>
