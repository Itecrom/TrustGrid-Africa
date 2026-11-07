<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$navUser = require_page_user();
if ($navUser['role'] !== 'super_admin') {
    http_response_code(403);
    exit('Super administrator access is required.');
}
$pageTitle = 'Platform administration';
$activePage = 'admin';
include dirname(__DIR__) . '/app/header.php';
?>
<main class="workspace-main" data-super-admin-page>
  <section class="workspace-welcome admin-welcome">
    <div class="container welcome-row">
      <div><p class="eyebrow">TRUSTGRID AFRICA · PLATFORM OVERSIGHT</p><h1>Administration</h1><p>Manage access, public content, subscribers and platform activity from one place.</p></div>
      <span class="admin-session-badge"><span aria-hidden="true"></span>Super administrator</span>
    </div>
  </section>
  <div class="container workspace-content admin-console">
    <div class="admin-console-heading">
      <div><p class="eyebrow">CONTROL CENTRE</p><h2>Platform overview</h2></div>
      <p>Privileged actions are recorded in the platform audit log.</p>
    </div>
    <div class="admin-dashboard-layout">
      <aside class="admin-sidebar">
        <p class="admin-sidebar-label">ADMINISTRATION</p>
        <nav class="admin-nav" aria-label="Administration sections" role="tablist" aria-orientation="vertical">
          <button class="admin-nav-item is-active" id="adminTabOverview" type="button" role="tab" aria-selected="true" aria-controls="adminPanelOverview" data-admin-tab="overview">Overview</button>
          <button class="admin-nav-item" id="adminTabWorkspaces" type="button" role="tab" aria-selected="false" aria-controls="adminPanelWorkspaces" data-admin-tab="workspaces">Workspaces <span data-admin-count="workspaces"></span></button>
          <button class="admin-nav-item" id="adminTabComments" type="button" role="tab" aria-selected="false" aria-controls="adminPanelComments" data-admin-tab="comments">Comments <span data-admin-count="comments"></span></button>
          <button class="admin-nav-item" id="adminTabSubscribers" type="button" role="tab" aria-selected="false" aria-controls="adminPanelSubscribers" data-admin-tab="subscribers">Subscribers</button>
          <button class="admin-nav-item" id="adminTabNews" type="button" role="tab" aria-selected="false" aria-controls="adminPanelNews" data-admin-tab="news">News</button>
          <button class="admin-nav-item" id="adminTabLogs" type="button" role="tab" aria-selected="false" aria-controls="adminPanelLogs" data-admin-tab="logs">Audit log</button>
        </nav>
        <a class="admin-sidebar-back" href="<?= h(app_url('dashboard.php')) ?>">← Workspace overview</a>
      </aside>
      <div class="admin-dashboard-main">
    <section id="adminPanelOverview" class="admin-panel-view" role="tabpanel" aria-labelledby="adminTabOverview" data-admin-panel="overview">
      <div id="adminStats" class="workspace-stats admin-stats"><article class="stat-card"><span>Loading platform…</span></article></div>
      <div class="admin-overview-grid">
        <article class="admin-overview-card">
          <span class="admin-overview-icon" aria-hidden="true">⌂</span><div><h3>Workspace approvals</h3><p>Review access requests before creators can use the platform.</p></div>
          <button type="button" data-admin-go="workspaces">Review requests <span aria-hidden="true">→</span></button>
        </article>
        <article class="admin-overview-card">
          <span class="admin-overview-icon" aria-hidden="true">◌</span><div><h3>Community moderation</h3><p>Keep public discussion useful by reviewing submitted comments.</p></div>
          <button type="button" data-admin-go="comments">Moderate comments <span aria-hidden="true">→</span></button>
        </article>
        <article class="admin-overview-card">
          <span class="admin-overview-icon" aria-hidden="true">▤</span><div><h3>Platform activity</h3><p>Review the latest recorded administrative and user actions.</p></div>
          <button type="button" data-admin-go="logs">Open audit log <span aria-hidden="true">→</span></button>
        </article>
      </div>
    </section>
    <section id="adminPanelWorkspaces" class="admin-panel-view" role="tabpanel" aria-labelledby="adminTabWorkspaces" data-admin-panel="workspaces" hidden>
      <section class="panel admin-section"><div class="panel-head"><div><p class="eyebrow">ACCESS CONTROL</p><h2>Workspace requests</h2><p>Creators cannot access a workspace until it is approved.</p></div><label>Show<select id="adminWorkspaceStatus"><option value="pending">Pending</option><option value="active">Approved</option><option value="rejected">Rejected</option><option value="suspended">Suspended</option></select></label></div><div id="adminWorkspaces" class="admin-record-list"><p class="loading-state">Loading workspace requests…</p></div><div id="adminWorkspacePagination"></div></section>
    </section>
    <section id="adminPanelComments" class="admin-panel-view" role="tabpanel" aria-labelledby="adminTabComments" data-admin-panel="comments" hidden>
      <section class="panel admin-section"><div class="panel-head"><div><p class="eyebrow">COMMUNITY</p><h2>Comment moderation</h2><p>Only approved comments appear on public articles.</p></div><label>Show<select id="adminCommentStatus"><option>Pending</option><option>Approved</option><option>Rejected</option></select></label></div><div id="adminComments" class="admin-record-list"><p class="loading-state">Loading comments…</p></div><div id="adminCommentPagination"></div></section>
    </section>
    <section id="adminPanelSubscribers" class="admin-panel-view" role="tabpanel" aria-labelledby="adminTabSubscribers" data-admin-panel="subscribers" hidden>
      <section class="panel admin-section"><div class="panel-head"><div><p class="eyebrow">NEWSLETTER</p><h2>Subscribers</h2><p>Manage opt-in records. Email campaigns are not sent by the app yet.</p></div><label>Show<select id="adminSubscriberStatus"><option>Active</option><option>Unsubscribed</option></select></label></div><div id="adminSubscribers" class="admin-record-list"><p class="loading-state">Loading subscribers…</p></div><div id="adminSubscriberPagination"></div></section>
    </section>
    <section id="adminPanelNews" class="admin-panel-view" role="tabpanel" aria-labelledby="adminTabNews" data-admin-panel="news" hidden>
      <section class="panel admin-section"><div class="panel-head"><div><p class="eyebrow">PUBLISHING</p><h2>Technology news</h2><p>Create, edit, publish or remove articles. Article markup is sanitised on the server.</p></div></div>
      <form id="adminNewsForm" class="guided-assessment-form admin-news-form">
        <input type="hidden" name="id" />
        <label>Title<input name="title" maxlength="180" required /></label>
        <label>Publication status<select name="status"><option>Draft</option><option>Published</option></select></label>
        <label class="assessment-scope-field">Short summary<textarea name="summary" rows="2" maxlength="500" required></textarea></label>
        <label class="assessment-scope-field">Featured image<input name="image" type="file" accept="image/jpeg,image/png,image/webp" /><small>JPEG, PNG or WebP; maximum 5 MB. This is used as the article thumbnail.</small></label>
        <img id="adminNewsImagePreview" class="admin-news-image-preview" alt="Featured image preview" hidden />
        <input type="hidden" name="featured_image_path" />
        <fieldset class="rich-editor-fieldset assessment-scope-field"><legend>Article text</legend>
          <div class="rich-editor-toolbar" role="toolbar" aria-label="Article formatting">
            <button type="button" data-editor-command="bold"><strong>B</strong></button><button type="button" data-editor-command="italic"><em>I</em></button>
            <button type="button" data-editor-block="h2">Heading</button><button type="button" data-editor-block="h3">Subheading</button>
            <button type="button" data-editor-command="insertUnorderedList">Bullets</button><button type="button" data-editor-command="insertOrderedList">Numbered list</button><button type="button" data-editor-command="formatBlock" data-editor-value="blockquote">Quote</button>
          </div>
          <div id="adminNewsEditor" class="rich-editor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Article text" data-placeholder="Write the article. Formatting is cleaned and sanitised when saved."></div>
        </fieldset>
        <p class="form-message assessment-scope-field" data-form-message role="status" aria-live="polite"></p>
        <div class="admin-form-actions assessment-scope-field"><button class="button button-primary" type="submit">Save article</button><button id="adminNewsReset" class="button button-secondary" type="button">New article</button></div>
      </form>
      <div id="adminNewsList" class="admin-record-list"><p class="loading-state">Loading articles…</p></div><div id="adminNewsPagination"></div>
      </section>
    </section>
    <section id="adminPanelLogs" class="admin-panel-view" role="tabpanel" aria-labelledby="adminTabLogs" data-admin-panel="logs" hidden>
      <section class="panel admin-section"><div class="panel-head"><div><p class="eyebrow">ACCOUNTABILITY</p><h2>Platform audit log</h2><p>Authentication, workspace, profile, news, subscriber and assessment actions. Client IPs are stored only as keyed hashes.</p></div></div><div id="adminLogs" class="admin-log-list"><p class="loading-state">Loading audit log…</p></div><div id="adminLogPagination"></div></section>
    </section>
      </div>
    </div>
  </div>
</main>
<?php include dirname(__DIR__) . '/app/footer.php'; ?>
