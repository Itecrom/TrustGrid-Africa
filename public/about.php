<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}
$pageTitle = 'Our approach';
$pageDescription = 'Learn about TrustGrid Africa’s authorised, privacy-minded approach to helping organisations build digital resilience.';
$activePage = 'about';
include dirname(__DIR__) . '/app/header.php';
?>
<main>
  <section class="interior-hero">
    <div class="container interior-hero-content" data-reveal>
      <p class="eyebrow">COMMUNITY-BUILT · COMMUNITY-MINDED</p>
      <h1>Security is stronger when <span>knowledge is shared.</span></h1>
      <p>TrustGrid Africa is an open-source initiative from ITEC ICT SOLUTIONS, Malawi. We’re building practical shared infrastructure alongside African developers, ICT professionals and the organisations they support.</p>
    </div>
  </section>
  <section class="section section-dark">
    <div class="container">
      <div class="approach-callout" data-reveal>
        <div class="approach-symbol" aria-hidden="true">◇</div>
        <div><p class="eyebrow">Trust by design</p><h2>Only assess what you own or are authorised to manage.</h2><p>Asset registration requires explicit authorisation. Registering an asset does not launch a review. Automated checks are bounded and non-exploitative; sensitive assessment information stays within your organisation.</p></div>
        <a href="<?= h(app_url('platform.php')) ?>">How the platform works <span aria-hidden="true">→</span></a>
      </div>
    </div>
  </section>
  <section class="section section-light">
    <div class="container community-layout">
      <div data-reveal>
        <p class="eyebrow">Built for African contexts</p>
        <h2>Practical guidance for <span>real teams.</span></h2>
        <p>Security improvements should be understandable, achievable and shared. TrustGrid helps teams turn evidence into clear next steps and strengthen continuity together.</p>
        <a class="button button-dark" href="<?= h(app_url('contact.php')) ?>">Talk with our team <span aria-hidden="true">→</span></a>
      </div>
      <div class="community-card" data-reveal>
        <div class="community-card-top"><span class="community-orbit">T</span><span class="community-tag">MALAWI · AFRICA</span></div>
        <p>“Building digital trust. Strengthening African organisations.”</p>
        <div class="community-card-rule"></div>
        <span>AN OPEN-SOURCE DIGITAL TRUST INITIATIVE</span>
      </div>
    </div>
  </section>
</main>
<?php include dirname(__DIR__) . '/app/footer.php'; ?>
