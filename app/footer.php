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
          <a href="<?= h(app_url('news.php')) ?>">Tech news</a>
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
        <div class="footer-newsletter">
          <h3>Technology news from Africa</h3>
          <p>Get occasional TrustGrid updates and practical digital security news.</p>
          <form id="newsletterForm" class="newsletter-form">
            <div class="newsletter-email-field">
              <label for="newsletterEmail">Email address</label>
              <input id="newsletterEmail" name="email" type="email" maxlength="254" autocomplete="email" required />
            </div>
            <label class="honeypot-field" aria-hidden="true">Leave this field empty<input name="website" tabindex="-1" autocomplete="off" /></label>
            <button class="button button-primary button-small" type="submit">Subscribe</button>
            <div class="human-challenge newsletter-challenge" data-human-challenge>
              <span>Loading human check…</span>
              <input type="hidden" name="challenge_id" />
              <label>Answer<input name="challenge_answer" inputmode="numeric" pattern="[0-9]+" required /></label>
            </div>
            <label class="newsletter-consent"><input name="consent" type="checkbox" required /> I agree to receive TrustGrid Africa news.</label>
            <p class="form-message" data-form-message role="status" aria-live="polite"></p>
          </form>
          <small>We only use your email for these updates. Request removal through <a href="https://wa.me/265992919716" target="_blank" rel="noopener noreferrer">WhatsApp</a>. Email campaigns are not sent automatically yet.</small>
        </div>
      </div>
      <div class="container footer-bottom">
        <span>© <?= date('Y') ?> TrustGrid Africa. Open-source digital trust infrastructure.</span>
        <span><a href="<?= h(app_url('sitemap.php')) ?>">Sitemap</a> · Platform-generated risk indicators are not a security certification.</span>
      </div>
    </footer>
    <div class="whatsapp-widget" data-whatsapp-widget>
      <section id="whatsappChatPanel" class="whatsapp-chat-panel" aria-label="WhatsApp chat" hidden>
        <div class="whatsapp-chat-heading">
          <span class="whatsapp-online-dot" aria-hidden="true"></span>
          <div><strong>Chat with TrustGrid</strong><small>WhatsApp · +265 992 919 716</small></div>
          <button type="button" class="whatsapp-close" data-whatsapp-close aria-label="Close chat">×</button>
        </div>
        <div class="whatsapp-chat-body">
          <p class="whatsapp-bubble">Hello! Leave a message and we’ll open WhatsApp so our team can reply to you there.</p>
          <form data-whatsapp-form>
            <label for="whatsappName">Your name</label>
            <input id="whatsappName" name="name" maxlength="100" autocomplete="name" required />
            <label for="whatsappMessage">How can we help?</label>
            <textarea id="whatsappMessage" name="message" rows="3" maxlength="1000" required></textarea>
            <button class="button button-whatsapp" type="submit">Continue to WhatsApp <span aria-hidden="true">↗</span></button>
          </form>
          <small>Replies are handled in WhatsApp, not in this website.</small>
        </div>
      </section>
      <button type="button" class="whatsapp-launcher" aria-expanded="false" aria-controls="whatsappChatPanel" data-whatsapp-toggle aria-label="Chat with us on WhatsApp">
        <span aria-hidden="true">◉</span><span>Chat with us</span>
      </button>
    </div>
    <script src="<?= h(app_url('assets/js/app.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/app.js') ?>" defer></script>
  </body>
</html>
