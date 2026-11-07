<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}
$pageTitle = 'Platform';
$pageDescription = 'Explore TrustGrid Africa asset management, safe reviews, guided assessments and resilience workflows.';
$activePage = 'platform';
include dirname(__DIR__) . '/app/header.php';
?>
<main>
  <section class="interior-hero">
    <div class="container interior-hero-content" data-reveal>
      <p class="eyebrow">ONE CONNECTED PLATFORM</p>
      <h1>From asset inventory to <span>lasting improvement.</span></h1>
      <p>A shared foundation for understanding digital risks, taking clear steps to improve, and building resilience with your team.</p>
      <a class="button button-primary" href="<?= h(app_url('register.php')) ?>">Create your workspace <span aria-hidden="true">→</span></a>
    </div>
  </section>
  <section class="section section-dark" aria-labelledby="platform-modules-heading">
    <div class="container">
      <div class="section-heading">
        <p class="eyebrow">Practical tools for your team</p>
        <h2 id="platform-modules-heading">A clear path from <span>review to action.</span></h2>
        <p class="heading-copy">Record what matters, make risks understandable, and track progress together.</p>
      </div>
      <div class="module-grid">
        <article data-reveal><span>01 / ORGANISE</span><h3>Organisation &amp; asset management</h3><p>Role-based workspaces with a clear inventory of websites, APIs, applications and infrastructure.</p></article>
        <article data-reveal><span>02 / ASSESS</span><h3>Safe security reviews</h3><p>Authorised, bounded endpoint checks and guided evidence-based reviews for websites, APIs and databases.</p></article>
        <article data-reveal><span>03 / PRIORITISE</span><h3>Risk dashboard</h3><p>Track open findings, severity and progress with transparent risk indicators and practical next steps.</p></article>
        <article data-reveal><span>04 / IMPROVE</span><h3>Remediation &amp; reporting</h3><p>Record owners, follow actions through resolution and prepare clear, printable security reports.</p></article>
      </div>
      <div class="platform-note" data-reveal>
        <strong>Designed for responsible use.</strong>
        <p>Automated checks are non-invasive and do not exploit systems. Database reviews are team-led; the platform never connects to a database. This is a defensive assessment workspace, not a penetration-testing suite.</p>
      </div>
    </div>
  </section>
  <section class="section section-light">
    <div class="container platform-next-step" data-reveal>
      <div><p class="eyebrow">Ready to begin?</p><h2>Start with the services <span>your team relies on.</span></h2><p>Register assets you own or are authorised to manage, then choose a safe review or guided assessment.</p></div>
      <a class="button button-dark" href="<?= h(app_url('register.php')) ?>">Build your workspace <span aria-hidden="true">→</span></a>
    </div>
  </section>
</main>
<?php include dirname(__DIR__) . '/app/footer.php'; ?>
