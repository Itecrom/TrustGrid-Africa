<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$navUser = require_page_user();
$pageTitle = 'Security report';
$activePage = 'reports';
include __DIR__ . '/app/header.php';
?>
<main class="workspace-main" data-workspace-page="reports">
  <section class="workspace-welcome no-print"><div class="container welcome-row"><div><p class="eyebrow">REPORTING &amp; REVIEW</p><h1>Security report</h1><p>A downloadable PDF and printable summary of your organisation’s registered assets and recorded findings.</p></div><div class="report-download-actions"><a class="button button-primary" href="<?= h(app_url('report-pdf.php')) ?>" download>Download PDF report <span aria-hidden="true">↓</span></a><button class="button button-secondary" type="button" data-print-report>Print</button></div></div></section>
  <div class="container workspace-content">
    <article class="report-sheet" id="reportSheet"><header class="report-cover"><div><span class="eyebrow">TRUSTGRID AFRICA · ORGANISATION REPORT</span><h1><?= h($navUser['organization_name']) ?></h1><p><?= h($navUser['country']) ?> · Prepared <?= date('F j, Y') ?></p></div><img src="<?= h(app_url('assets/images/mark.svg')) ?>" width="62" height="62" alt="TrustGrid Africa" /></header>
      <div class="report-disclaimer"><strong>Platform-generated security overview</strong><span>This report summarises data entered into TrustGrid Africa. It is not a formal security audit or certification.</span></div>
      <div class="report-grid"><article class="mini-card"><span>REGISTERED ASSETS</span><strong data-stat="assets">—</strong></article><article class="mini-card"><span>RECORDED ASSESSMENTS</span><strong data-stat="assessments">—</strong></article><article class="mini-card"><span>OPEN CRITICAL FINDINGS</span><strong data-stat="critical">—</strong></article><article class="mini-card"><span>RESOLVED FINDINGS</span><strong data-stat="resolved">—</strong></article></div>
      <section class="report-section"><h2>Open findings</h2><div id="reportFindings" class="findings-list"><p class="loading-state">Loading findings…</p></div></section>
      <section class="report-section"><h2>Asset risk overview</h2><div class="table-wrap"><table><thead><tr><th>ASSET</th><th>TYPE</th><th>RISK INDICATOR</th><th>STATUS</th></tr></thead><tbody id="reportAssets"><tr><td colspan="4" class="loading-state">Loading assets…</td></tr></tbody></table></div></section>
      <footer class="report-footnote">Generated from this workspace’s recorded data. Findings and recommendations should be reviewed by an appropriately authorised security professional.</footer>
    </article>
  </div>
</main>
<?php include __DIR__ . '/app/footer.php'; ?>
