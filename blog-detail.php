<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
$hasImage = tableExists($pdo, 'blog_posts') && columnExists($pdo, 'blog_posts', 'image_path');
$fields = $hasImage ? '*, image_path' : '*, NULL AS image_path';
$post = $slug && tableExists($pdo, 'blog_posts')
  ? one($pdo, "SELECT {$fields} FROM blog_posts WHERE slug = ? AND status = 'Published' LIMIT 1", [$slug])
  : null;

$pageTitle = $post ? $post['title'] : 'Article not found';
//  Load the shared file needed before this page continues.
require __DIR__ . '/includes/public_header.php';

if (!$post) {
  http_response_code(404);
?>
  <!-- This section starts the main hero section shown at the top of the page. -->
  <section class="page-hero">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container">
      <span class="eyebrow">PowerFit Journal</span>
      <h1 class="mt-3">Article not found.</h1>
      <a class="btn btn-power rounded-pill mt-3" href="<?= url('blog.php') ?>">Back to blog</a>
    </div>
  </section>
<?php
  //  Load the shared file needed before this page continues.
  require __DIR__ . '/includes/public_footer.php';
  exit;
}
?>

<!-- This section starts the main hero section shown at the top of the page. -->
<section class="page-hero blog-detail-hero">
  <!-- This div keeps this section content aligned inside the main page width. -->
  <div class="container">
    <a class="text-white-50 text-decoration-none small" href="<?= url('blog.php') ?>">
      <i class="bi bi-arrow-left me-2"></i>PowerFit Journal
    </a>
    <!-- This div groups related page content using the “mt-4” layout style. -->
    <div class="mt-4"><span class="badge rounded-pill text-bg-light"><?= e($post['category']) ?></span></div>
    <h1 class="mt-3"><?= e($post['title']) ?></h1>
    <p class="fs-5 mt-3"><?= e($post['excerpt']) ?></p>
    <small class="text-white-50"><?= shortDate($post['published_at']) ?></small>
  </div>
</section>

<!-- This section starts a new content section and keeps related information together. -->
<section class="section">
  <!-- This div keeps this section content aligned inside the main page width. -->
  <div class="container blog-detail-container">
    <?php if ($post['image_path']): ?>
      <img class="blog-detail-image" src="<?= url($post['image_path']) ?>" alt="<?= e($post['title']) ?>">
    <?php endif; ?>
    <article class="blog-detail-copy"><?= nl2br(e($post['body'])) ?></article>
  </div>
</section>

<?php require __DIR__ . '/includes/public_footer.php'; ?>