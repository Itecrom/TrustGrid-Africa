<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$pageTitle = 'Sitemap';
$pageDescription = 'Find public pages and sign-in destinations on TrustGrid Africa.';
$navUser = current_user();
$activePage = 'sitemap';
include dirname(__DIR__) . '/app/header.php';
?>
<main class="workspace-main sitemap-page">
  <section class="workspace-welcome">
    <div class="container welcome-row">
      <div>
        <p class="eyebrow">SITE DIRECTORY</p>
        <h1>TrustGrid Africa sitemap</h1>
        <p>Browse public information, account access and the tools available in your organisation workspace.</p>
      </div>
    </div>
  </section>
  <div class="container workspace-content sitemap-grid">
    <section class="panel sitemap-section">
      <h2>Explore TrustGrid</h2>
      <ul>
        <li><a href="<?= h(app_url('index.php')) ?>">Home</a><span>What the platform does and how to get started.</span></li>
        <li><a href="<?= h(app_url('about.php')) ?>">About</a><span>Our purpose and responsible-use approach.</span></li>
        <li><a href="<?= h(app_url('platform.php')) ?>">Platform</a><span>Asset inventory, reviews, assessments and remediation.</span></li>
        <li><a href="<?= h(app_url('news.php')) ?>">News</a><span>Technology and digital security updates.</span></li>
        <li><a href="<?= h(app_url('contact.php')) ?>">Contact</a><span>Contact ITEC ICT SOLUTIONS and find our location.</span></li>
      </ul>
    </section>
    <section class="panel sitemap-section">
      <h2>Account access</h2>
      <ul>
        <li><a href="<?= h(app_url('login.php')) ?>">Sign in</a><span>Access an approved organisation workspace.</span></li>
        <li><a href="<?= h(app_url('register.php')) ?>">Request a workspace</a><span>New workspaces require super-admin approval.</span></li>
      </ul>
    </section>
    <section class="panel sitemap-section">
      <h2>Workspace tools</h2>
      <p>Sign in to access these organisation-scoped areas.</p>
      <ul>
        <li><a href="<?= h(app_url('dashboard.php')) ?>">Security overview</a></li>
        <li><a href="<?= h(app_url('assets.php')) ?>">Digital asset inventory</a></li>
        <li><a href="<?= h(app_url('assessments.php')) ?>">Assessments and engagements</a></li>
        <li><a href="<?= h(app_url('findings.php')) ?>">Findings and remediation</a></li>
        <li><a href="<?= h(app_url('reports.php')) ?>">Reports</a></li>
        <li><a href="<?= h(app_url('profile.php')) ?>">Profile and organisation directory</a></li>
      </ul>
    </section>
    <?php if ($navUser !== null && $navUser['role'] === 'super_admin'): ?>
      <section class="panel sitemap-section">
        <h2>Platform administration</h2>
        <ul><li><a href="<?= h(app_url('admin.php')) ?>">Super-admin console</a><span>Workspace approvals, news, comments, subscribers and activity logs.</span></li></ul>
      </section>
    <?php endif; ?>
  </div>
</main>
<?php include dirname(__DIR__) . '/app/footer.php'; ?>
