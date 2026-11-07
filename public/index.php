<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}
$pageTitle = 'Building digital trust';
$pageDescription = 'Shared cybersecurity infrastructure, practical assessments and clear guidance for African organisations.';
$activePage = 'home';
include dirname(__DIR__) . '/app/header.php';
?>
<main>
  <section class="hero hero-home">
    <div class="container hero-grid">
      <div class="hero-copy">
        <span class="eyebrow"><span class="eyebrow-line"></span> A security assessment workspace for African organisations</span>
        <h1>Know what needs attention across your <span>digital assets.</span></h1>
        <p>TrustGrid helps your team organise websites, APIs, databases and systems, carry out authorised security reviews, and turn findings into practical actions you can track.</p>
        <ul class="hero-steps" aria-label="What TrustGrid helps your team do">
          <li><span aria-hidden="true">01</span> Keep an inventory of your organisation’s digital assets</li>
          <li><span aria-hidden="true">02</span> Run safe checks or guided team assessments</li>
          <li><span aria-hidden="true">03</span> Prioritise findings and follow remediation progress</li>
        </ul>
        <div class="cta-row">
          <a class="button button-primary" href="<?= h(app_url('register.php')) ?>">Request a workspace <span aria-hidden="true">→</span></a>
          <a class="button button-secondary" href="<?= h(app_url('platform.php')) ?>"><span class="play-icon" aria-hidden="true">↘</span> See how it works</a>
        </div>
        <div class="hero-proof"><span>Designed for NGOs, schools, clinics and small businesses</span></div>
      </div>
      <div class="hero-visual">
        <img src="<?= h(app_url('assets/images/security-grid.svg')) ?>" alt="Illustration of a protected digital network" />
        <div class="visual-card visual-card-top"><span class="status-pulse"></span><span><strong>Authorised reviews only</strong><small>Safe, bounded checks</small></span><span class="visual-arrow">↗</span></div>
        <div class="visual-card visual-card-bottom"><span class="visual-icon">✓</span><span><strong>Findings to action</strong><small>Prioritised. Trackable. Team-owned.</small></span></div>
        <span class="visual-label label-one">WEBSITES · APIS · SYSTEMS</span><span class="visual-label label-two">BUILT FOR AFRICAN TEAMS</span>
      </div>
    </div>
  </section>

  <section class="trust-strip" aria-label="Our principles">
    <div class="container trust-strip-inner"><span>OPEN-SOURCE BY NATURE</span><span>BUILT FOR AFRICAN CONTEXTS</span><span>AUTHORISED ASSESSMENTS ONLY</span><span>YOUR DATA, YOUR CONTROL</span></div>
  </section>

  <section class="section section-light" aria-labelledby="home-discover-heading">
    <div class="container">
      <div class="section-heading section-heading-split"><div><p class="eyebrow">How TrustGrid works</p><h2 id="home-discover-heading">From asset inventory to <span>clear next steps.</span></h2></div><p>TrustGrid is a defensive assessment and tracking workspace. Your team chooses what to review and uses the results to plan improvements—not to launch attacks.</p></div>
      <div class="value-grid">
        <article class="value-card" data-reveal><span class="card-number">01 / RECORD</span><span class="value-icon">◎</span><h3>Inventory your assets</h3><p>Bring your organisation’s websites, web applications, APIs, databases, domains and servers into one private workspace.</p><a href="<?= h(app_url('platform.php')) ?>">Explore asset management <span>→</span></a></article>
        <article class="value-card" data-reveal><span class="card-number">02 / REVIEW</span><span class="value-icon">⌁</span><h3>Assess safely</h3><p>Run bounded, non-invasive website and API checks, or use guided assessments for databases and other systems.</p><a href="<?= h(app_url('platform.php')) ?>">Understand review scope <span>→</span></a></article>
        <article class="value-card" data-reveal><span class="card-number">03 / IMPROVE</span><span class="value-icon">↗</span><h3>Act on what you learn</h3><p>Turn review results into prioritised findings, assign follow-up work and monitor progress with your team.</p><a href="<?= h(app_url('about.php')) ?>">Our responsible-use approach <span>→</span></a></article>
      </div>
      <div class="home-safety-note" data-reveal><strong>Built for authorised defensive work.</strong><span>Automated checks do not exploit systems or test credentials. Database and system reviews are guided by your team; TrustGrid does not connect to database hosts.</span></div>
    </div>
  </section>
</main>
<?php include dirname(__DIR__) . '/app/footer.php'; ?>
