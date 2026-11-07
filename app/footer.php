    <footer class="site-footer">
      <div class="container footer-main">
        <div class="footer-about">
          <a class="brand footer-brand" href="<?= h(app_url('index.php')) ?>">
            <img src="<?= h(app_url('assets/images/mark.svg')) ?>" width="46" height="46" alt="" />
            <span><strong>TrustGrid <span class="brand-africa">Africa</span></strong><small>BUILDING DIGITAL TRUST</small></span>
          </a>
          <p>Practical, shared security infrastructure to help African organisations protect the digital services their communities rely on.</p>
          <span class="footer-origin">An open-source initiative from ITEC ICT SOLUTIONS, Malawi.</span>
        </div>
        <div class="footer-links">
          <h3>Explore</h3>
          <a href="<?= h(app_url('platform.php')) ?>">The platform</a>
          <a href="<?= h(app_url('about.php')) ?>">Our approach</a>
          <a href="<?= h(app_url('contact.php')) ?>">Contact &amp; location</a>
        </div>
        <div class="footer-links">
          <h3>Your workspace</h3>
          <a href="<?= h(app_url('dashboard.php')) ?>">Security overview</a>
          <a href="<?= h(app_url('assets.php')) ?>">Digital assets</a>
          <a href="<?= h(app_url('assessments.php')) ?>">Assessments</a>
          <a href="<?= h(app_url('findings.php')) ?>">Remediation</a>
        </div>
        <div class="footer-note">
          <span class="footer-lock" aria-hidden="true">◇</span>
          <h3>Trust is a shared responsibility.</h3>
          <p>Your organisation retains control of its asset inventory and assessment information.</p>
          <a href="<?= h(app_url('register.php')) ?>">Build your workspace <span aria-hidden="true">→</span></a>
        </div>
        <div class="footer-links footer-contact-links">
          <h3>Call or WhatsApp</h3>
          <a href="tel:+265992919716">+265 992 919 716</a>
          <a href="https://wa.me/265992919716" target="_blank" rel="noopener noreferrer">WhatsApp first number</a>
          <a href="tel:+265881610633">+265 881 610 633</a>
          <a href="https://wa.me/265881610633" target="_blank" rel="noopener noreferrer">WhatsApp second number</a>
        </div>
      </div>
      <div class="container footer-bottom">
        <span>© <?= date('Y') ?> TrustGrid Africa. Open-source digital trust infrastructure.</span>
        <span>Platform-generated risk indicators are not a security certification.</span>
      </div>
    </footer>
    <script src="<?= h(app_url('app.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../app.js') ?>" defer></script>
  </body>
</html>
