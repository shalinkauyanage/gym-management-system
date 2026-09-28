<?php

$pageTitle = 'Packages';
//  Load the shared file needed before this page continues.
require __DIR__ . '/includes/public_header.php';

$packages = all($pdo, "SELECT * FROM packages WHERE status = 'Active' ORDER BY duration_months ASC, fee ASC");

//  Returns the public title, benefits and image presentation for a package.

function packagePresentation(array $package): array
{
    // Student function step: This is the start of packagePresentation(). The lines below do the main work of this helper.
    $name = strtolower($package['package_name']);

    if (str_contains($name, 'annual')) {
        return [
            'type' => 'Annual membership',
            'icon' => 'bi-calendar-check',
            'featured' => true,
            'image' => 'advantage-members.webp',
            'features' => [
                '12 months of gym access',
                'Member portal access',
                'Workout & nutrition plan access',
                'Progress tracking throughout the year',
            ],
        ];
    }

    if (str_contains($name, 'personal')) {
        return [
            'type' => 'Personal coaching',
            'icon' => 'bi-person-arms-up',
            'featured' => false,
            'image' => 'advantage-training.webp',
            'features' => [
                'Dedicated coach support',
                'Personalized workout planning',
                'Regular progress reviews',
                'Accountability & feedback',
            ],
        ];
    }

    if (str_contains($name, 'nutrition')) {
        return [
            'type' => 'Nutrition support',
            'icon' => 'bi-egg-fried',
            'featured' => false,
            'image' => 'advantage-nutrition.webp',
            'features' => [
                'Practical nutrition guidance',
                'Sri Lankan food-friendly planning',
                'Meal plan updates',
                'Progress-aligned support',
            ],
        ];
    }

    if (str_contains($name, 'class')) {
        return [
            'type' => 'Gym + classes',
            'icon' => 'bi-people-fill',
            'featured' => false,
            'image' => 'advantage-members.webp',
            'features' => [
                'Gym access',
                'Group class access',
                'Member portal access',
                'Progress tracking',
            ],
        ];
    }

    return [
        'type' => 'Gym membership',
        'icon' => 'bi-lightning-charge-fill',
        'featured' => false,
        'image' => 'advantage-members.webp',
        'features' => [
            'Gym access',
            'Member portal access',
            'Membership & payment history',
            'Progress tracking',
        ],
    ];
}
?>

<!-- This section starts the main hero section shown at the top of the page. -->
<section class="page-hero package-page-hero">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container">
        <h1 class="mt-3">Find the membership that fits your goals.</h1>
        <p class="fs-5 mt-3">
            From flexible gym access to personal coaching and nutrition support, choose the option that matches your routine, goals and level of commitment.
        </p>
    </div>
</section>

<!-- This section starts a new content section and keeps related information together. -->
<section class="section package-catalog-section">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container">
        <!-- This div groups items in a grid-style layout. -->
        <div class="package-intro-grid mb-5">
            <!-- This div groups related HTML content so the page structure is easier to manage. -->
            <div>
                <h2 class="section-title mt-3">Choose your way to train.</h2>
            </div>
        </div>

        <?php if ($packages): ?>
            <!-- This div creates a Bootstrap grid row for responsive columns. -->
            <div class="row g-4">
                <?php foreach ($packages as $package): ?>
                    <?php $meta = packagePresentation($package); ?>
                    <!-- This div creates a responsive Bootstrap column inside the current row. -->
                    <div class="col-md-6 col-xl-4">
                        <article class="package-card package-card-modern <?= $meta['featured'] ? 'featured' : '' ?>">
                            <!-- This div creates a bordered content card for related information. -->
                            <div class="package-card-media">
                                <img src="<?= e(packageImageUrl($package)) ?>" alt="<?= e($package['package_name']) ?>" loading="lazy">
                            </div>
                            <!-- This div creates a bordered content card for related information. -->
                            <div class="package-card-topline">
                                <span class="package-kind"><?= e($meta['type']) ?></span>
                                <span class="package-icon"><i class="bi <?= e($meta['icon']) ?>"></i></span>
                            </div>

                            <?php if ($meta['featured']): ?>
                                <span class="package-ribbon">Best value</span>
                            <?php endif; ?>

                            <h2 class="mt-4"><?= e($package['package_name']) ?></h2>
                            <p class="opacity-75 package-description"><?= e($package['description']) ?></p>

                            <!-- This div creates a Bootstrap grid row for responsive columns. -->
                            <div class="package-price-row">
                                <!-- This div groups related page content using the “price” layout style. -->
                                <div class="price"><?= money($package['fee']) ?></div>
                                <span>
                                    <?= (int) $package['duration_months'] ?> month<?= (int) $package['duration_months'] === 1 ? '' : 's' ?>
                                </span>
                            </div>

                            <ul>
                                <?php foreach ($meta['features'] as $feature): ?>
                                    <li><i class="bi bi-check-circle-fill"></i><?= e($feature) ?></li>
                                <?php endforeach; ?>
                            </ul>

                            <?php
                            $chooseUrl = isLoggedIn() && currentUser()['role'] === 'Member'
                                ? url('member/pay.php?package=' . (int) $package['package_id'])
                                : url('login.php');
                            ?>
                            <a
                                href="<?= $chooseUrl ?>"
                                class="btn <?= $meta['featured'] ? 'btn-power' : 'btn-dark' ?> rounded-pill w-100">
                                Choose <?= e($package['package_name']) ?>
                            </a>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- This div groups related page content using the “panel text-center” layout style. -->
            <div class="panel text-center py-5">
                <i class="bi bi-box-seam fs-2 text-muted"></i>
                <h3 class="h5 mt-3">No active packages yet</h3>
                <p class="text-muted mb-0">New membership options are coming soon. Please check back again shortly.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- This section starts a new content section and keeps related information together. -->
<section class="section section-dark package-callout-section">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container">
        <!-- This div groups related page content using the “package-callout” layout style. -->
        <div class="package-callout">
            <!-- This div groups related HTML content so the page structure is easier to manage. -->
            <div>
                <h2 class="section-title mt-3 mb-3">More than access. A clearer path forward.</h2>
                <p class="section-lead mb-0">
                    Keep your membership, coaching, nutrition guidance and progress within easy reach, so you always know where you stand and what comes next.
                </p>
            </div>
            <a class="btn-swap flex-shrink-0" href="<?= url('login.php') ?>">
                <span>Sign in</span>
                <span class="round-icon"><i class="bi bi-arrow-up-right"></i></span>
            </a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/public_footer.php'; ?>