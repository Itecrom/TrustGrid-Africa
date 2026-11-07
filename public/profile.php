<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$navUser = require_page_user();
$pageTitle = 'My profile';
$activePage = 'profile';
include dirname(__DIR__) . '/app/header.php';
?>
<main class="workspace-main" data-profile-page>
  <section class="workspace-welcome"><div class="container welcome-row"><div><p class="eyebrow">YOUR ACCOUNT</p><h1>My profile</h1><p>Update your name and professional details. Profile information is visible only to signed-in members of your organisation.</p></div><span class="badge-pill">ORGANISATION-ONLY PROFILE</span></div></section>
  <div class="container workspace-content">
    <section class="panel profile-panel">
      <form id="profileForm" class="guided-assessment-form profile-form">
        <div class="profile-avatar-area">
          <img id="profileAvatarPreview" class="profile-avatar-preview" alt="Profile photo" hidden />
          <label>Profile picture<input id="profileAvatarInput" type="file" accept="image/jpeg,image/png,image/webp" /><small>JPEG, PNG or WebP; maximum 2 MB.</small></label>
        </div>
        <label>Full name<input name="full_name" maxlength="120" required /></label>
        <label>School or institution<input name="school" maxlength="180" /></label>
        <label>Workplace or role<input name="work" maxlength="180" /></label>
        <label class="assessment-scope-field">Bio<textarea name="bio" rows="4" maxlength="2000"></textarea></label>
        <label class="assessment-scope-field">Qualifications<textarea name="qualifications" rows="4" maxlength="3000" placeholder="List qualifications, certifications or areas of study."></textarea></label>
        <fieldset class="password-change-fields assessment-scope-field"><legend>Change password <span class="inline-hint">Optional</span></legend>
          <label>Current password<input name="current_password" type="password" autocomplete="current-password" /></label>
          <label>New password<input name="new_password" type="password" minlength="12" maxlength="200" autocomplete="new-password" /><small>Use at least 12 characters. Required to change your password.</small></label>
        </fieldset>
        <p class="form-message assessment-scope-field" data-form-message role="status" aria-live="polite"></p>
        <button class="button button-primary assessment-scope-field" type="submit">Save profile</button>
      </form>
      <dialog id="avatarCropDialog" class="avatar-crop-dialog" aria-labelledby="avatarCropTitle">
        <div class="avatar-crop-content">
          <div class="panel-head"><div><p class="eyebrow">PROFILE PHOTO</p><h2 id="avatarCropTitle">Adjust your photo</h2><p>Drag to reposition, then zoom and save a square crop.</p></div></div>
          <canvas id="avatarCropCanvas" width="320" height="320" aria-label="Profile photo crop preview"></canvas>
          <label for="avatarCropZoom">Zoom</label>
          <input id="avatarCropZoom" type="range" min="1" max="3" step="0.01" value="1" />
          <div class="admin-form-actions">
            <button id="avatarCropCancel" class="button button-secondary" type="button">Cancel</button>
            <button id="avatarCropSave" class="button button-primary" type="button">Use this photo</button>
          </div>
        </div>
      </dialog>
    </section>
    <section class="panel profile-directory-panel">
      <div class="panel-head"><div><h2>Organisation profiles</h2><p>Professional details are visible only to signed-in members of this organisation.</p></div></div>
      <div id="profileDirectory" class="profile-directory-grid"><p class="loading-state">Loading organisation profiles…</p></div>
      <div id="profileDirectoryPagination"></div>
    </section>
  </div>
</main>
<?php include dirname(__DIR__) . '/app/footer.php'; ?>
