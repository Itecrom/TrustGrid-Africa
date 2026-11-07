<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$navUser = require_page_user();
$pageTitle = 'Security findings';
$activePage = 'findings';
include dirname(__DIR__) . '/app/header.php';
?>
<main class="workspace-main" data-workspace-page="findings">
  <section class="workspace-welcome"><div class="container welcome-row"><div><p class="eyebrow">REMEDIATION &amp; MONITORING</p><h1>Security findings</h1><p>Review identified issues, assign follow-up with your team and track progress.</p></div><span class="badge-pill">ORGANISATION-ONLY DATA</span></div></section>
  <div class="container workspace-content">
    <div class="findings-summary" id="findingsSummary"><span>Loading your findings…</span></div>
    <?php if (in_array($navUser['role'], ['admin', 'analyst', 'contributor'], true)): ?>
    <section class="panel finding-entry-panel"><div class="panel-head"><div><h2>Record a finding</h2><p>Choose an asset to load its existing findings below, or add a new issue from a manual review.</p></div></div>
      <form id="findingForm" class="finding-form">
        <label>Authorised asset<select name="asset_id" required><option value="">Loading assets…</option></select></label>
        <label>Finding title<input name="title" type="text" maxlength="180" required /></label>
        <label>Severity<select name="severity" required><option>Low</option><option>Medium</option><option>High</option><option>Critical</option></select></label>
        <label class="finding-description-field">Description<textarea name="description" rows="3" maxlength="10000" required></textarea></label>
        <label>Evidence notes <span class="inline-hint">Never include passwords or secrets.</span><textarea name="evidence" rows="2" maxlength="10000"></textarea></label>
        <label>Recommended action<textarea name="recommendation" rows="2" maxlength="10000" required></textarea></label>
        <p class="form-message" data-form-message role="status" aria-live="polite"></p>
        <button class="button button-primary" type="submit">Add finding</button>
      </form>
    </section>
    <?php endif; ?>
    <section class="panel findings-panel"><div class="panel-head"><div><h2>Finding register</h2><p>Choose an asset to see its findings, including issues found automatically during website reviews.</p></div><div class="finding-register-filters"><label for="findingsAssetFilter">Show findings for</label><select id="findingsAssetFilter"><option value="">All assets</option></select><div class="segmented" aria-label="Filter findings by status"><button class="chip active" type="button" data-finding-filter="all">All</button><button class="chip" type="button" data-finding-filter="Open">Open</button><button class="chip" type="button" data-finding-filter="In progress">In progress</button><button class="chip" type="button" data-finding-filter="Resolved">Resolved</button></div></div></div><div id="findingsList" class="findings-list"><p class="loading-state">Loading findings…</p></div><div id="findingsPagination"></div></section>
    <p class="data-boundary-note"><span>◇</span> Website reviews are limited to basic homepage configuration checks and do not replace a professional security assessment.</p>
  </div>
</main>
<?php include dirname(__DIR__) . '/app/footer.php'; ?>
