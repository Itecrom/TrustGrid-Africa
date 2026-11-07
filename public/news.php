<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$navUser = current_user();
$activePage = 'news';
$slug = trim((string) ($_GET['slug'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, min(100000, (int) ($_GET['page'] ?? 1)));
$perPage = $slug === '' ? 9 : 20;
$article = null;
$comments = [];
$pagination = ['page' => $page, 'pages' => 1, 'total' => 0];
$latestStatement = db()->query(
    'SELECT slug, title, featured_image_path, published_at
     FROM news_articles
     WHERE status = "Published" AND published_at <= UTC_TIMESTAMP()
     ORDER BY published_at DESC, id DESC LIMIT 5'
);
$latestArticles = $latestStatement->fetchAll();

if ($slug !== '') {
    $statement = db()->prepare(
        'SELECT id, slug, title, summary, body_html, featured_image_path, published_at
         FROM news_articles
         WHERE slug = :slug AND status = "Published" AND published_at <= UTC_TIMESTAMP()
         LIMIT 1'
    );
    $statement->execute(['slug' => $slug]);
    $article = $statement->fetch();
    if ($article === false) {
        http_response_code(404);
        $article = null;
    } else {
        $count = db()->prepare(
            'SELECT COUNT(*) FROM news_comments WHERE article_id = :article_id AND status = "Approved"'
        );
        $count->execute(['article_id' => (int) $article['id']]);
        $pagination['total'] = (int) $count->fetchColumn();
        $pagination['pages'] = max(1, (int) ceil($pagination['total'] / $perPage));
        $page = min($page, $pagination['pages']);
        $pagination['page'] = $page;
        $commentsQuery = db()->prepare(
            'SELECT display_name, body_text, created_at FROM news_comments
             WHERE article_id = :article_id AND status = "Approved"
             ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
        );
        $commentsQuery->bindValue(':article_id', (int) $article['id'], PDO::PARAM_INT);
        $commentsQuery->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $commentsQuery->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $commentsQuery->execute();
        $comments = $commentsQuery->fetchAll();
        $article['body_html'] = sanitize_news_html((string) $article['body_html']);
    }
} else {
    if (mb_strlen($search) > 100) {
        $search = mb_substr($search, 0, 100);
    }
    $where = 'status = "Published" AND published_at <= UTC_TIMESTAMP()';
    $parameters = [];
    if ($search !== '') {
        $where .= ' AND (title LIKE :title OR summary LIKE :summary)';
        $parameters = ['title' => '%' . $search . '%', 'summary' => '%' . $search . '%'];
    }
    $count = db()->prepare('SELECT COUNT(*) FROM news_articles WHERE ' . $where);
    $count->execute($parameters);
    $pagination['total'] = (int) $count->fetchColumn();
    $pagination['pages'] = max(1, (int) ceil($pagination['total'] / $perPage));
    $page = min($page, $pagination['pages']);
    $pagination['page'] = $page;
    $statement = db()->prepare(
        'SELECT id, slug, title, summary, featured_image_path, published_at
         FROM news_articles WHERE ' . $where . '
         ORDER BY published_at DESC, id DESC LIMIT :limit OFFSET :offset'
    );
    foreach ($parameters as $key => $value) {
        $statement->bindValue(':' . $key, $value);
    }
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
    $statement->execute();
    $articles = $statement->fetchAll();
}
$pageStart = max(1, min($page - 3, $pagination['pages'] - 6));
$pageEnd = min($pagination['pages'], $pageStart + 6);

$pageTitle = $article === null ? 'Technology news' : $article['title'];
$pageDescription = $article === null
    ? 'Technology, cybersecurity and digital trust news for African organisations.'
    : $article['summary'];
include dirname(__DIR__) . '/app/header.php';
?>
<main class="workspace-main news-page">
  <section class="workspace-welcome"><div class="container welcome-row"><div><p class="eyebrow">TRUSTGRID AFRICA · INSIGHTS</p><h1><?= $article === null ? 'Technology &amp; security news' : h($article['title']) ?></h1><p><?= $article === null ? 'Practical updates on cybersecurity, technology and digital trust across Africa.' : h($article['summary']) ?></p></div><span class="badge-pill">NEWS &amp; INSIGHTS</span></div></section>
  <div class="container workspace-content news-layout">
    <section class="news-main-column">
      <?php if ($article !== null): ?>
        <article class="panel news-article">
          <?php if ($article['featured_image_path'] !== ''): ?>
            <img class="news-featured-image" src="<?= h(app_url($article['featured_image_path'])) ?>" alt="" />
          <?php endif; ?>
          <div class="news-article-meta"><time datetime="<?= h(gmdate(DATE_ATOM, strtotime($article['published_at']))) ?>"><?= h(gmdate('F j, Y', strtotime($article['published_at']))) ?></time></div>
          <div class="news-rich-content"><?= $article['body_html'] ?></div>
        </article>
        <section class="panel news-comments-panel">
          <div class="panel-head"><div><h2>Discussion</h2><p><?= (int) $pagination['total'] ?> approved comment(s). New comments are reviewed before appearing.</p></div></div>
          <div class="news-comments-list">
            <?php if ($comments === []): ?><p class="empty-state">No comments yet. Be the first to share a thoughtful response.</p><?php endif; ?>
            <?php foreach ($comments as $comment): ?>
              <article class="news-comment"><div><strong><?= h($comment['display_name']) ?></strong><time><?= h(gmdate('M j, Y', strtotime($comment['created_at']))) ?></time></div><p><?= nl2br(h($comment['body_text'])) ?></p></article>
            <?php endforeach; ?>
          </div>
          <?php if ($pagination['pages'] > 1): ?>
            <nav class="pagination" aria-label="Comment pages">
              <?php for ($index = $pageStart; $index <= $pageEnd; $index++): ?>
                <a class="<?= $index === $page ? 'active' : '' ?>" href="<?= h(app_url('news/' . rawurlencode($article['slug']) . '?page=' . $index)) ?>"><?= $index ?></a>
              <?php endfor; ?>
            </nav>
          <?php endif; ?>
          <form id="newsCommentForm" class="stack-form news-comment-form" data-news-slug="<?= h($article['slug']) ?>">
            <h3>Leave a comment</h3>
            <label>Your name<input name="display_name" maxlength="120" value="<?= h($navUser['full_name'] ?? '') ?>" required /></label>
            <label>Email (not published)<input name="email" type="email" maxlength="254" value="<?= h($navUser['email'] ?? '') ?>" required /></label>
            <label>Comment<textarea name="body_text" rows="4" maxlength="3000" required></textarea></label>
            <label class="honeypot-field" aria-hidden="true">Leave this field empty<input name="website" tabindex="-1" autocomplete="off" /></label>
            <div class="human-challenge" data-human-challenge><span>Loading human check…</span><input type="hidden" name="challenge_id" /><label>Answer<input name="challenge_answer" inputmode="numeric" pattern="[0-9]+" required /></label></div>
            <p class="form-message" data-form-message role="status" aria-live="polite"></p>
            <button class="button button-primary" type="submit">Submit for review</button>
          </form>
        </section>
      <?php elseif ($slug !== ''): ?>
        <section class="panel"><h2>Article not found</h2><p>This article may have moved or is no longer published.</p><a class="button button-secondary" href="<?= h(app_url('news.php')) ?>">Browse all news</a></section>
      <?php else: ?>
        <form class="news-search-form" method="get" action="<?= h(app_url('news.php')) ?>">
          <label for="newsSearch">Search news</label><input id="newsSearch" name="q" value="<?= h($search) ?>" maxlength="100" placeholder="Search cybersecurity and technology news" />
          <button class="button button-primary" type="submit">Search</button>
        </form>
        <div class="news-card-grid">
          <?php if ($articles === []): ?><p class="empty-state"><?= $search === '' ? 'No news has been published yet.' : 'No news articles matched that search.' ?></p><?php endif; ?>
          <?php foreach ($articles as $item): ?>
            <article class="news-card">
              <a class="news-card-image-link" href="<?= h(app_url('news/' . rawurlencode($item['slug']))) ?>" aria-label="<?= h($item['title']) ?>">
                <?php if ($item['featured_image_path'] !== ''): ?><img src="<?= h(app_url($item['featured_image_path'])) ?>" alt="" loading="lazy" /><?php else: ?><span class="news-image-placeholder">TRUSTGRID AFRICA</span><?php endif; ?>
              </a>
              <div class="news-card-content"><time><?= h(gmdate('F j, Y', strtotime($item['published_at']))) ?></time><h2><a href="<?= h(app_url('news/' . rawurlencode($item['slug']))) ?>"><?= h($item['title']) ?></a></h2><p><?= h($item['summary']) ?></p><a class="text-link" href="<?= h(app_url('news/' . rawurlencode($item['slug']))) ?>">Read more <span aria-hidden="true">→</span></a></div>
            </article>
          <?php endforeach; ?>
        </div>
        <?php if ($pagination['pages'] > 1): ?>
          <nav class="pagination" aria-label="News pages">
            <?php for ($index = $pageStart; $index <= $pageEnd; $index++): ?>
              <a class="<?= $index === $page ? 'active' : '' ?>" href="<?= h(app_url('news.php?' . http_build_query(array_filter(['q' => $search, 'page' => $index], static fn ($value) => $value !== '')))) ?>"><?= $index ?></a>
            <?php endfor; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </section>
    <aside class="news-sidebar">
      <form class="news-sidebar-search" method="get" action="<?= h(app_url('news.php')) ?>"><label for="newsSidebarSearch">Find an article</label><div><input id="newsSidebarSearch" name="q" value="<?= h($search) ?>" maxlength="100" placeholder="Search news" /><button type="submit" aria-label="Search news">⌕</button></div></form>
      <section class="panel news-latest-panel"><h2>Latest news</h2>
        <?php if ($latestArticles === []): ?><p class="empty-state">New articles will appear here.</p><?php endif; ?>
        <?php foreach ($latestArticles as $item): ?>
          <a class="news-latest-item" href="<?= h(app_url('news/' . rawurlencode($item['slug']))) ?>">
            <?php if ($item['featured_image_path'] !== ''): ?><img src="<?= h(app_url($item['featured_image_path'])) ?>" alt="" loading="lazy" /><?php endif; ?>
            <span><strong><?= h($item['title']) ?></strong><time><?= h(gmdate('M j, Y', strtotime($item['published_at']))) ?></time></span>
          </a>
        <?php endforeach; ?>
      </section>
    </aside>
  </div>
</main>
<?php include dirname(__DIR__) . '/app/footer.php'; ?>
