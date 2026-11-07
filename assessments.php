<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$navUser = require_page_user();
$pageTitle = 'Guided security assessments';
$activePage = 'assessments';
include __DIR__ . '/app/header.php';
?>
<main class="workspace-main" data-workspace-page="assessments">
  <section class="workspace-welcome">
    <div class="container welcome-row">
      <div>
        <p class="eyebrow">GUIDED · EVIDENCE-BASED · ACTIONABLE</p>
        <h1>Security assessments</h1>
        <p>Review practical safeguards together, record evidence without secrets, and turn gaps into clear actions that strengthen resilience.</p>
      </div>
      <span class="badge-pill">PRIVATE TO YOUR ORGANISATION</span>
    </div>
  </section>
  <div class="container workspace-content">
    <div class="assessment-intro-note">
      <strong>For your team, not just specialists.</strong>
      <span>Record written scope, exclusions, dates and rules before work begins. Use only named, authorised assets. “Not sure” is a useful result, not a failure; scores are indicators, not certification.</span>
    </div>
    <?php if (in_array($navUser['role'], ['admin', 'analyst', 'contributor'], true)): ?>
    <section class="panel engagement-panel">
      <div class="panel-head"><div><h2>Authorised engagement</h2><p>Document who approved the work, what is in and out of scope, the operating rules, and the approved dates. This record supports a safe assessment workflow; it does not authorise activity by itself.</p></div></div>
      <form id="assessmentEngagementForm" class="guided-assessment-form engagement-form">
        <label>Engagement name<input name="title" maxlength="180" placeholder="e.g. Q3 website security review" required /></label>
        <label>Authorised asset<select name="asset_id" required><option value="">Loading authorised assets…</option></select></label>
        <label>Engagement lead<input name="lead_name" maxlength="120" placeholder="Responsible person" required /></label>
        <label>Start date<input name="start_date" type="date" required /></label>
        <label>End date<input name="end_date" type="date" required /></label>
        <label class="assessment-scope-field">In-scope work<textarea name="scope_text" rows="2" maxlength="10000" placeholder="Name the exact systems, permitted activities and boundaries." required></textarea></label>
        <label class="assessment-scope-field">Out-of-scope work<textarea name="exclusions" rows="2" maxlength="10000" placeholder="Name excluded systems, techniques, data and any restrictions." required></textarea></label>
        <label class="assessment-scope-field">Rules of engagement<textarea name="rules_of_engagement" rows="2" maxlength="10000" placeholder="Approved methods, working hours, rate limits, stop conditions and escalation contacts." required></textarea></label>
        <label class="assessment-scope-field">Authorisation reference<input name="authorization_note" maxlength="500" placeholder="Approval record, ticket or agreement reference" required /></label>
        <label class="checkbox-label assessment-scope-field"><input name="authorization_confirmed" type="checkbox" required /><span>I confirm written authorisation is documented for this asset, lead, scope and date window. This must not be used to approve testing outside that permission.</span></label>
        <p class="form-message" data-form-message role="status" aria-live="polite"></p>
        <button class="button button-primary" type="submit">Record authorised engagement <span aria-hidden="true">→</span></button>
      </form>
      <div id="assessmentEngagementList" class="assessment-engagement-list"><p class="loading-state">Loading engagements…</p></div>
    </section>
    <section class="panel assessment-start-panel">
      <div class="panel-head"><div><h2>Start a team review</h2><p>Choose an authorised asset and relevant baselines. Database assets include focused questions on access, encryption, backups and recovery.</p></div></div>
      <form id="guidedAssessmentForm" class="guided-assessment-form">
        <label>Assessment name<input name="title" maxlength="180" placeholder="e.g. Annual website and operations review" required /></label>
        <label>Asset to assess<select name="asset_id" required><option value="">Loading authorised assets…</option></select></label>
        <label class="assessment-scope-field">Authorised engagement<select name="engagement_id"><option value="">No engagement link</option></select><small>Link to an open engagement for this asset and its authorised date window.</small></label>
        <label class="assessment-scope-field">What is included?<textarea name="scope" rows="2" maxlength="10000" placeholder="Briefly describe the service, team or period covered." required></textarea></label>
        <fieldset class="framework-picker">
          <legend>Assessment baselines <span class="inline-hint">Choose one or more</span></legend>
          <div id="assessmentFrameworks" class="framework-options"><p class="loading-state">Loading assessment baselines…</p></div>
          <small>Questions are original, plain-language prompts mapped to these broad themes; selecting them does not establish compliance or certification.</small>
        </fieldset>
        <p class="form-message" data-form-message role="status" aria-live="polite"></p>
        <button class="button button-primary" type="submit">Start guided assessment <span aria-hidden="true">→</span></button>
      </form>
    </section>
    <?php endif; ?>
    <section class="panel assessment-history-panel">
      <div class="panel-head"><div><h2>Your assessment workspace</h2><p>Continue a draft or review a completed assessment and its follow-up actions.</p></div></div>
      <div id="guidedAssessmentList" class="assessment-history-list"><p class="loading-state">Loading assessments…</p></div>
    </section>
    <section id="guidedAssessmentWorkspace" class="panel guided-assessment-workspace" hidden>
      <div class="guided-workspace-heading">
        <div><p class="eyebrow" id="guidedAssessmentAsset"></p><h2 id="guidedAssessmentTitle"></h2><p id="guidedAssessmentScope"></p><p id="guidedAssessmentEngagement"></p></div>
        <span id="guidedAssessmentStatus" class="tag"></span>
      </div>
      <div id="guidedAssessmentFrameworkTags" class="assessment-framework-tags"></div>
      <p class="form-message" id="guidedAssessmentReadOnlyMessage" role="status" hidden></p>
      <div id="guidedAssessmentProgress" class="assessment-progress"></div>
      <div id="guidedAssessmentControls" class="guided-assessment-controls"></div>
      <div class="guided-assessment-actions" id="guidedAssessmentActions">
        <p class="form-message" id="guidedAssessmentMessage" role="status" aria-live="polite"></p>
        <button class="button button-secondary" id="saveAssessmentAnswers" type="button">Save and continue later</button>
        <button class="button button-secondary" id="cancelGuidedAssessment" type="button">Cancel draft</button>
        <button class="button button-primary" id="completeGuidedAssessment" type="button">Complete assessment</button>
      </div>
      <section id="guidedAssessmentResults" class="guided-assessment-results" hidden>
        <div class="panel-head"><div><h3>Assessment summary</h3><p id="guidedAssessmentResultSummary"></p></div><div class="report-download-actions no-print"><a class="button button-primary" id="downloadGuidedAssessmentPdf" href="#" download>Download PDF report</a><button class="button button-secondary" id="printGuidedAssessment" type="button">Print</button></div></div>
        <div id="guidedAssessmentResultStats" class="assessment-result-stats"></div>
        <div id="guidedAssessmentResultFindings" class="findings-list"></div>
        <p class="report-footnote">Assessment answers are self-reported and may not be independently verified. The mapped themes are not a formal audit, legal opinion or certification.</p>
      </section>
    </section>
  </div>
</main>
<?php include __DIR__ . '/app/footer.php'; ?>
