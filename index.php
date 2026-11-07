<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
if (isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}
$pageTitle = 'Building digital trust';
$pageDescription = 'Shared cybersecurity infrastructure, practical assessments and clear guidance for African organisations.';
$activePage = 'home';
include __DIR__ . '/app/header.php';
?>
<main>
  <section class="hero hero-home">
    <div class="container hero-grid">
      <div class="hero-copy">
        <span class="eyebrow"><span class="eyebrow-line"></span> A shared security network for Africa</span>
        <h1>Digital trust should be within <span>everyone’s reach.</span></h1>
        <p>Cybersecurity support for the organisations powering our communities. Understand your digital risks, take clear steps to improve, and build resilience together.</p>
        <div class="cta-row">
          <a class="button button-primary" href="<?= h(app_url('register.php')) ?>">Create your workspace <span aria-hidden="true">→</span></a>
          <a class="button button-secondary" href="<?= h(app_url('platform.php')) ?>"><span class="play-icon" aria-hidden="true">↘</span> Explore the platform</a>
        </div>
        <div class="hero-proof"><span class="proof-avatars"><i>NG</i><i>SM</i><i>+</i></span><span>Designed for NGOs, schools, clinics &amp; small businesses</span></div>
      </div>
      <div class="hero-visual">
        <img src="<?= h(app_url('assets/images/security-grid.svg')) ?>" alt="Illustration of a protected digital network" />
        <div class="visual-card visual-card-top"><span class="status-pulse"></span><span><strong>Shared protection</strong><small>Stronger, together</small></span><span class="visual-arrow">↗</span></div>
        <div class="visual-card visual-card-bottom"><span class="visual-icon">✓</span><span><strong>Practical security insights</strong><small>Clear priorities. Actionable steps.</small></span></div>
        <span class="visual-label label-one">MALAWI · AFRICA</span><span class="visual-label label-two">BUILT FOR COMMUNITIES</span>
      </div>
    </div>
  </section>

  <section class="trust-strip" aria-label="Our principles">
    <div class="container trust-strip-inner"><span>OPEN-SOURCE BY NATURE</span><span>BUILT FOR AFRICAN CONTEXTS</span><span>AUTHORISED ASSESSMENTS ONLY</span><span>YOUR DATA, YOUR CONTROL</span></div>
  </section>

  <section class="section section-light" aria-labelledby="home-discover-heading">
    <div class="container">
      <div class="section-heading section-heading-split"><div><p class="eyebrow">A practical security partner</p><h2 id="home-discover-heading">Make better security decisions, <span>one step at a time.</span></h2></div><p>Understand your digital risks, take clear steps to improve, and build resilience together—with practical tools designed for real teams.</p></div>
      <div class="value-grid">
        <article class="value-card" data-reveal><span class="card-number">01</span><span class="value-icon">◎</span><h3>Know your digital footprint</h3><p>Keep a clear record of the websites, applications and infrastructure your organisation relies on.</p><a href="<?= h(app_url('platform.php')) ?>">Explore the platform <span>→</span></a></article>
        <article class="value-card" data-reveal><span class="card-number">02</span><span class="value-icon">⌁</span><h3>Understand what matters</h3><p>Turn common security weaknesses into prioritised, plain-language findings and practical guidance.</p><a href="<?= h(app_url('platform.php')) ?>">See how reviews work <span>→</span></a></article>
        <article class="value-card" data-reveal><span class="card-number">03</span><span class="value-icon">↗</span><h3>Build resilience together</h3><p>Help teams turn shared understanding into realistic, trackable improvements.</p><a href="<?= h(app_url('about.php')) ?>">Our approach <span>→</span></a></article>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/app/footer.php'; ?>
