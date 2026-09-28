<?php

$pageTitle = 'Blog';
//  Load the shared file needed before this page continues.
require __DIR__ . '/includes/public_header.php';

// Load only blog posts created and published by the administrator.
$posts = [];
$hasImages = tableExists($pdo, 'blog_posts') && columnExists($pdo, 'blog_posts', 'image_path');

try {
    if (tableExists($pdo, 'blog_posts')) {
        $fields = $hasImages
            ? 'post_id, title, slug, category, excerpt, body, published_at, image_path'
            : 'post_id, title, slug, category, excerpt, body, published_at, NULL AS image_path';

        $posts = all(
            $pdo,
            "SELECT {$fields}
             FROM blog_posts
             WHERE status = 'Published'
             ORDER BY COALESCE(published_at, '1970-01-01 00:00:00') DESC, post_id DESC",
        );
    }
} catch (Throwable $error) {
    // A safe empty state is shown if the blog table is unavailable.
    $posts = [];
}

$featuredPost = $posts[0] ?? null;
$remainingPosts = $featuredPost ? array_slice($posts, 1) : [];
?>

<!-- This section starts the main hero section shown at the top of the page. -->
<section class="page-hero blog-page-hero">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container">
        <h1 class="mt-3">Training, nutrition and progress — made practical.</h1>
        <p class="fs-5 mt-3">
            Straightforward ideas from PowerFit to support stronger habits, smarter training and sustainable progress.
        </p>
    </div>
</section>

<?php if ($featuredPost): ?>
    <?php
    $featuredCategory = strtolower((string) ($featuredPost['category'] ?? ''));
    $featuredFallbackImage = str_contains($featuredCategory, 'nutrition')
        ? 'assets/images/blog-nutrition.webp'
        : (str_contains($featuredCategory, 'progress')
            ? 'assets/images/blog-progress.webp'
            : 'assets/images/blog-training.webp');
    $featuredImage = $featuredPost['image_path'] ?: $featuredFallbackImage;
    ?>

    <!-- This section starts a new content section and keeps related information together. -->
    <section class="section blog-featured-section">
        <!-- This div keeps this section content aligned inside the main page width. -->
        <div class="container">
            <article class="blog-featured-card">
                <!-- This div displays the featured blog cover area using the image path saved by Admin. -->
                <div
                    class="blog-featured-visual has-cover"
                    style="background-image:url('<?= e(url($featuredImage)) ?>')"></div>

                <!-- This div groups related page content using the “blog-featured-copy” layout style. -->
                <div class="blog-featured-copy">
                    <!-- This div uses flexbox to align the child elements in this area. -->
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                        <span class="badge rounded-pill text-bg-dark">
                            <?= e($featuredPost['category']) ?>
                        </span>
                        <span class="text-muted small">
                            <?= shortDate($featuredPost['published_at'] ?? null) ?>
                        </span>
                    </div>

                    <h2><?= e($featuredPost['title']) ?></h2>
                    <p><?= e($featuredPost['excerpt']) ?></p>

                    <a
                        class="blog-read-label text-decoration-none"
                        href="<?= url('blog-detail.php?slug=' . urlencode($featuredPost['slug'])) ?>">
                        Read article <i class="bi bi-arrow-up-right"></i>
                    </a>
                </div>
            </article>
        </div>
    </section>

    <?php if ($remainingPosts): ?>
        <!-- This section starts a new content section and keeps related information together. -->
        <section class="section pt-0">
            <!-- This div keeps this section content aligned inside the main page width. -->
            <div class="container">
                <!-- This div creates a Bootstrap grid row for responsive columns. -->
                <div class="row align-items-end g-4 mb-5">
                    <!-- This div creates a responsive Bootstrap column inside the current row. -->
                    <div class="col-lg-7">
                        <span class="eyebrow text-dark border-dark-subtle bg-white">
                            Latest from PowerFit
                        </span>
                        <h2 class="section-title mt-3">Fresh ideas for a stronger routine.</h2>
                    </div>
                </div>

                <!-- This div creates a Bootstrap grid row for responsive columns. -->
                <div class="row g-4">
                    <?php foreach ($remainingPosts as $post): ?>
                        <?php
                        $category = strtolower((string) ($post['category'] ?? ''));
                        $fallbackImage = str_contains($category, 'nutrition')
                            ? 'assets/images/blog-nutrition.webp'
                            : (str_contains($category, 'progress')
                                ? 'assets/images/blog-progress.webp'
                                : 'assets/images/blog-training.webp');
                        $cardImage = $post['image_path'] ?: $fallbackImage;
                        ?>

                        <!-- This div creates a responsive Bootstrap column inside the current row. -->
                        <div class="col-md-6 col-lg-4">
                            <article class="blog-card blog-card-modern h-100">
                                <!-- This div displays the cover area for one published blog card. -->
                                <div
                                    class="blog-cover blog-cover-image"
                                    style="background-image:url('<?= e(url($cardImage)) ?>')">
                                    <i class="bi bi-arrow-up-right"></i>
                                </div>

                                <!-- This div holds the main content inside this Bootstrap card. -->
                                <div class="blog-card-body">
                                    <!-- This div uses flexbox to align the child elements in this area. -->
                                    <div class="d-flex justify-content-between gap-3 align-items-center mb-3">
                                        <span class="badge rounded-pill text-bg-light">
                                            <?= e($post['category']) ?>
                                        </span>
                                        <small class="text-muted">
                                            <?= shortDate($post['published_at'] ?? null) ?>
                                        </small>
                                    </div>

                                    <h3 class="h4"><?= e($post['title']) ?></h3>
                                    <p class="text-muted"><?= e($post['excerpt']) ?></p>

                                    <a
                                        class="small fw-bold text-decoration-none"
                                        href="<?= url('blog-detail.php?slug=' . urlencode($post['slug'])) ?>">
                                        Read article <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php else: ?>
    <!-- This section starts a new content section and keeps related information together. -->
    <section class="section">
        <!-- This div keeps this section content aligned inside the main page width. -->
        <div class="container">
            <!-- This div shows one summary value and its label. -->
            <div class="empty-state py-5 text-center">
                <i class="bi bi-journal-text fs-1 d-block mb-3"></i>
                <h2 class="h3">New PowerFit articles are coming soon.</h2>
                <p class="text-muted mb-0">
                    Training, nutrition and progress articles published by the PowerFit team will appear here.
                </p>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/public_footer.php'; ?>