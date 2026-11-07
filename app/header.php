<?php
$pageTitle = $pageTitle ?? 'TrustGrid Africa';
$pageDescription = $pageDescription ?? 'Building digital trust and strengthening African organisations.';
$activePage = $activePage ?? '';
$readOnly = isset($navUser) && !in_array($navUser['role'], ['admin', 'analyst', 'contributor'], true);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="<?= h($pageDescription) ?>" />
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>" />
    <title><?= h($pageTitle) ?> | TrustGrid Africa</title>
    <link rel="icon" href="<?= h(app_url('assets/images/mark.svg')) ?>" type="image/svg+xml" />
    <link rel="stylesheet" href="<?= h(app_url('assets/css/styles.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/styles.css') ?>" />
  </head>
  <body data-base-url="<?= h(app_url()) ?>" data-read-only="<?= $readOnly ? 'true' : 'false' ?>">
    <div class="announcement-bar">
      <div class="container announcement-inner">
        <span class="announcement-dot"></span>
        <span>Shared security infrastructure for African organisations</span>
        <a href="<?= h(app_url('about.php')) ?>">Our approach <span aria-hidden="true">→</span></a>
      </div>
    </div>
    <header class="site-header">
      <div class="container nav-wrap">
        <a class="brand" href="<?= h(app_url('index.php')) ?>" aria-label="TrustGrid Africa home">
          <img src="<?= h(app_url('assets/images/mark.svg')) ?>" width="46" height="46" alt="" />
          <span>
            <strong>TrustGrid <span class="brand-africa">Africa</span></strong>
            <small>BUILDING DIGITAL TRUST</small>
          </span>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primaryNav" aria-label="Open navigation">
          <span></span><span></span><span></span>
        </button>
        <nav class="main-nav" id="primaryNav" aria-label="Main navigation">
          <?php if (isset($navUser) && $navUser !== null): ?>
            <a class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= h(app_url('dashboard.php')) ?>">Overview</a>
            <a class="<?= $activePage === 'assets' ? 'active' : '' ?>" href="<?= h(app_url('assets.php')) ?>">Digital assets</a>
            <a class="<?= $activePage === 'assessments' ? 'active' : '' ?>" href="<?= h(app_url('assessments.php')) ?>">Assessments</a>
            <a class="<?= $activePage === 'findings' ? 'active' : '' ?>" href="<?= h(app_url('findings.php')) ?>">Findings</a>
            <a class="<?= $activePage === 'reports' ? 'active' : '' ?>" href="<?= h(app_url('reports.php')) ?>">Reports</a>
            <?php if ($navUser['role'] === 'super_admin'): ?>
              <a class="<?= $activePage === 'admin' ? 'active' : '' ?>" href="<?= h(app_url('admin.php')) ?>">Platform admin</a>
            <?php endif; ?>
          <?php else: ?>
            <a class="<?= $activePage === 'home' ? 'active' : '' ?>" href="<?= h(app_url('index.php')) ?>" <?= $activePage === 'home' ? 'aria-current="page"' : '' ?>>Home</a>
            <a class="<?= $activePage === 'about' ? 'active' : '' ?>" href="<?= h(app_url('about.php')) ?>" <?= $activePage === 'about' ? 'aria-current="page"' : '' ?>>About</a>
            <a class="<?= $activePage === 'platform' ? 'active' : '' ?>" href="<?= h(app_url('platform.php')) ?>" <?= $activePage === 'platform' ? 'aria-current="page"' : '' ?>>Platform</a>
            <a class="<?= $activePage === 'news' ? 'active' : '' ?>" href="<?= h(app_url('news.php')) ?>" <?= $activePage === 'news' ? 'aria-current="page"' : '' ?>>News</a>
            <a class="<?= $activePage === 'contact' ? 'active' : '' ?>" href="<?= h(app_url('contact.php')) ?>" <?= $activePage === 'contact' ? 'aria-current="page"' : '' ?>>Contact</a>
            <span class="mobile-auth-links">
              <a href="<?= h(app_url('login.php')) ?>">Sign in</a>
              <a href="<?= h(app_url('register.php')) ?>">Create workspace →</a>
            </span>
          <?php endif; ?>
        </nav>
        <div class="nav-actions">
          <?php if (isset($navUser) && $navUser !== null): ?>
            <details class="account-menu">
              <summary aria-label="Account options for <?= h($navUser['full_name']) ?>">
                <span class="avatar">
                  <span class="avatar-initial"><?= h(mb_strtoupper(mb_substr($navUser['full_name'], 0, 1))) ?></span>
                  <?php if (!empty($navUser['avatar_path'])): ?>
                    <img class="nav-avatar-image" src="<?= h(app_url('profile-image.php')) ?>?v=<?= time() ?>" alt="" />
                  <?php endif; ?>
                </span>
                <span class="user-meta"><strong data-account-name><?= h($navUser['full_name']) ?></strong><small><?= h($navUser['organization_name']) ?></small></span>
                <span class="account-chevron" aria-hidden="true">⌄</span>
              </summary>
              <div class="account-menu-panel">
                <a class="<?= $activePage === 'profile' ? 'active' : '' ?>" href="<?= h(app_url('profile.php')) ?>">My profile</a>
                <button type="button" data-logout>Sign out</button>
              </div>
            </details>
          <?php else: ?>
            <a class="nav-login" href="<?= h(app_url('login.php')) ?>">Sign in</a>
            <a class="button button-primary button-small" href="<?= h(app_url('register.php')) ?>">Create workspace <span aria-hidden="true">→</span></a>
          <?php endif; ?>
        </div>
      </div>
    </header>
