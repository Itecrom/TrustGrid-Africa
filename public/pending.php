<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$pageTitle = 'Workspace approval pending';
$activePage = '';
include dirname(__DIR__) . '/app/header.php';
?>
<main class="auth-page"><section class="auth-card">
  <p class="eyebrow">REQUEST RECEIVED</p>
  <h1>Your workspace is awaiting approval</h1>
  <p class="auth-intro">A super administrator must approve your organisation before you can sign in. You can try again after receiving confirmation.</p>
  <a class="button button-primary" href="<?= h(app_url('login.php')) ?>">Return to sign in</a>
</section></main>
<?php include dirname(__DIR__) . '/app/footer.php'; ?>
