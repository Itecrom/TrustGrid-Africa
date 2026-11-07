<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$navUser = require_page_user();
$pageTitle = 'Digital asset register';
$activePage = 'assets';
include __DIR__ . '/app/header.php';
?>
<main class="workspace-main" data-workspace-page="assets">
  <section class="workspace-welcome"><div class="container welcome-row"><div><p class="eyebrow">YOUR DIGITAL FOOTPRINT</p><h1>Digital asset register</h1><p>Keep track of the services your organisation owns or is authorised to manage.</p></div><span class="badge-pill">PRIVATE TO YOUR ORGANISATION</span></div></section>
  <div class="container workspace-content asset-layout">
    <aside class="panel form-panel"><div class="panel-head"><div><h2>Register an asset</h2><p>Choose a safe endpoint review or a guided evidence-based review.</p></div></div>
      <?php if (in_array($navUser['role'], ['admin', 'analyst', 'contributor'], true)): ?>
      <form id="assetForm" class="asset-form">
        <label>Asset name<input type="text" name="name" maxlength="160" placeholder="e.g. Public website" required /></label>
        <label>Asset type<select name="type" required><option value="">Choose type</option><option>Website</option><option>Web application</option><option>API</option><option>Domain</option><option>Database</option><option>Server</option></select></label>
        <label>URL, domain or hostname<input type="text" name="target" maxlength="2048" placeholder="https://example.org" required /><small class="field-hint">Public Websites and HTTPS API endpoints support bounded, read-only checks. Databases use a guided review; the tool never connects to database hosts.</small></label>
        <label>Owner team<input type="text" name="owner_team" maxlength="120" placeholder="e.g. IT operations" /></label>
        <label>Authorisation details<textarea name="authorization_note" rows="3" maxlength="500" placeholder="Describe the ownership or written permission for this asset." required></textarea></label>
        <label class="checkbox-label"><input type="checkbox" name="authorization_confirmed" required /><span>I confirm our organisation owns this asset or has explicit permission to manage it.</span></label>
        <p class="form-message" data-form-message role="status" aria-live="polite"></p>
        <button class="button button-primary button-wide" type="submit">Add to register <span>＋</span></button>
      </form>
      <?php else: ?><div class="notice-box">Your account is read-only. Ask an organisation administrator to update your role if you need to add assets.</div><?php endif; ?>
    </aside>
    <section class="panel asset-panel"><div class="panel-head"><div><h2>Registered assets</h2><p>Assessment status and risk indicators for this workspace.</p></div><div class="segmented" aria-label="Filter assets"><button class="chip active" type="button" data-filter="all">All</button><button class="chip" type="button" data-filter="Website">Websites</button><button class="chip" type="button" data-filter="API">APIs</button><button class="chip" type="button" data-filter="Database">Databases</button></div></div>
      <div class="table-wrap"><table><thead><tr><th>ASSET</th><th>TYPE</th><th>TARGET</th><th>RISK</th><th>STATUS</th><th>MANAGE</th></tr></thead><tbody id="assetList"><tr><td colspan="6" class="loading-state">Loading assets…</td></tr></tbody></table></div>
      <section id="webReviewPanel" class="web-review-panel" hidden aria-live="polite">
        <div class="web-review-heading"><div><p class="eyebrow">READ-ONLY WEBSITE &amp; API REVIEW</p><h3 id="webReviewTitle">Endpoint review</h3></div><span id="webReviewScore" class="tag unassessed"></span></div>
        <p id="webReviewSummary" class="web-review-summary"></p>
        <div id="webReviewChecks" class="web-review-checks"></div>
      </section>
      <div class="notice-box notice-quiet"><strong>Authorised use only.</strong> Website reviews check the public homepage; API reviews make one bounded GET request to the configured HTTPS endpoint. Both assess TLS and selected response settings without storing response bodies, crawling or sending test payloads. Database assets use a guided team review of access, encryption, updates, backups and recovery; no database connection or credential is requested.</div>
      <?php if (in_array($navUser['role'], ['admin', 'analyst', 'contributor'], true)): ?>
      <div class="manual-review-box"><div class="panel-head"><div><h2>Record a manual review</h2><p>Document a human-led assessment; this does not run an automated scan.</p></div></div>
        <form id="assessmentForm" class="assessment-form">
          <label>Authorised asset<select name="asset_id" required><option value="">Loading assets…</option></select></label>
          <label>Risk score <span class="inline-hint">0 = lower risk · 100 = higher risk</span><input type="number" name="risk_score" min="0" max="100" step="1" value="0" required /></label>
          <label class="assessment-summary">Review summary<textarea name="summary" rows="2" maxlength="500" placeholder="Summarise the scope and outcome of your review." required></textarea></label>
          <p class="form-message" data-form-message role="status" aria-live="polite"></p>
          <button class="button button-secondary" type="submit">Save manual review</button>
        </form>
      </div>
      <?php endif; ?>
    </section>
  </div>
</main>
<?php include __DIR__ . '/app/footer.php'; ?>
