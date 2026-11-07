<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}
$pageTitle = 'Contact ITEC ICT SOLUTIONS';
$pageDescription = 'Call, WhatsApp or visit ITEC ICT SOLUTIONS in Zomba, Malawi.';
$activePage = 'contact';
$mapQuery = "ITEC ICT Solutions, Zomba, Malawi, Inside Billys' House, Room Number 5";
include dirname(__DIR__) . '/app/header.php';
?>
<main>
  <section class="interior-hero">
    <div class="container interior-hero-content" data-reveal>
      <p class="eyebrow">GET IN TOUCH</p>
      <h1>Let’s build digital trust <span>together.</span></h1>
      <p>Contact ITEC ICT SOLUTIONS for questions about TrustGrid Africa, support, or working together.</p>
    </div>
  </section>
  <section class="section section-light">
    <div class="container contact-layout">
      <section class="contact-panel" aria-labelledby="contact-team-heading" data-reveal>
        <p class="eyebrow">CALL OR WHATSAPP</p>
        <h2 id="contact-team-heading">ITEC ICT SOLUTIONS</h2>
        <p class="contact-intro">Choose the number that works best for you. Calling and WhatsApp are available on both lines.</p>
        <div class="contact-number-card">
          <strong>+265 992 919 716</strong>
          <div class="contact-actions">
            <a class="button button-dark" href="tel:+265992919716">Call</a>
            <a class="button button-whatsapp" href="https://wa.me/265992919716" target="_blank" rel="noopener noreferrer">WhatsApp <span aria-hidden="true">↗</span></a>
          </div>
        </div>
        <div class="contact-number-card">
          <strong>+265 881 610 633</strong>
          <div class="contact-actions">
            <a class="button button-dark" href="tel:+265881610633">Call</a>
            <a class="button button-whatsapp" href="https://wa.me/265881610633" target="_blank" rel="noopener noreferrer">WhatsApp <span aria-hidden="true">↗</span></a>
          </div>
        </div>
        <div class="contact-address">
          <span class="contact-pin" aria-hidden="true">⌖</span>
          <div><strong>Visit us</strong><p>ITEC ICT SOLUTIONS<br />Zomba, Malawi<br />Inside Billys' House, Room Number #5</p></div>
        </div>
      </section>
      <section class="contact-map-panel" aria-labelledby="contact-map-heading" data-reveal>
        <div class="contact-map-heading"><div><p class="eyebrow">ZOMBA · MALAWI</p><h2 id="contact-map-heading">Find our location</h2></div></div>
        <iframe
          class="contact-map"
          src="https://maps.google.com/maps?q=<?= h(rawurlencode($mapQuery)) ?>&amp;output=embed"
          title="Map search for ITEC ICT SOLUTIONS in Zomba, Malawi"
          loading="lazy"
          referrerpolicy="no-referrer"
          allowfullscreen>
        </iframe>
        <a class="map-directions" href="https://www.google.com/maps/search/?api=1&amp;query=<?= h(rawurlencode($mapQuery)) ?>" target="_blank" rel="noopener noreferrer">Open location in Google Maps <span aria-hidden="true">↗</span></a>
        <p class="map-note">The map shows a location search; please confirm the house and room details with us before visiting.</p>
      </section>
    </div>
  </section>
</main>
<?php include dirname(__DIR__) . '/app/footer.php'; ?>
