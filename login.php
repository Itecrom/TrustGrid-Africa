<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
if (isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}
$pageTitle = 'Sign in';
$pageDescription = 'Sign in to your TrustGrid Africa workspace.';
include __DIR__ . '/app/header.php';
?>
<main class="auth-page"><div class="auth-art"><img src="<?= h(app_url('assets/images/security-grid.svg')) ?>" alt="" /><p class="eyebrow">Your organisation’s digital trust workspace</p><h2>Security clarity, built around your team.</h2><p>Keep the assets, findings and improvements that matter to your organisation in one place.</p></div><section class="auth-card"><a class="back-home" href="<?= h(app_url('index.php')) ?>">← Back to TrustGrid Africa</a><p class="eyebrow">Welcome back</p><h1>Sign in to your workspace</h1><p class="auth-intro">Use your work email and password to continue.</p><form class="stack-form" data-auth-form data-endpoint="/api/auth/login"><label>Email address<input name="email" type="email" autocomplete="username" maxlength="254" required /></label><label>Password<input name="password" type="password" autocomplete="current-password" maxlength="200" required /></label><p class="form-message" data-form-message role="status" aria-live="polite"></p><button class="button button-primary button-wide" type="submit">Sign in <span>→</span></button></form><p class="auth-switch">New to TrustGrid Africa? <a href="<?= h(app_url('register.php')) ?>">Create a workspace</a></p><p class="auth-safety"><span>◇</span> Your workspace is private to your organisation.</p></section></main>
<?php include __DIR__ . '/app/footer.php'; ?>
