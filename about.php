<?php

$pageTitle = 'About';
//  Load the shared file needed before this page continues.
require __DIR__ . '/includes/public_header.php';
?>
<!-- This section starts the main hero section shown at the top of the page. -->
<section class="page-hero">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container">
        <h1 class="mt-3">Built for people who want more from their gym experience.</h1>
        <p class="fs-5 mt-3">PowerFit brings membership, personal coaching, nutrition guidance and progress tracking together in one focused experience designed around consistent support and measurable growth.</p>
    </div>
</section>
<!-- This section starts a new content section and keeps related information together. -->
<section class="section">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container">
        <!-- This div creates a Bootstrap grid row for responsive columns. -->
        <div class="row g-5 align-items-center">
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-lg-6">
                <h2 class="section-title">One membership experience. More clarity at every step.</h2>
                <p class="section-lead">From joining the gym to following a plan and reviewing your progress, PowerFit keeps the essentials easy to access so members and coaches can stay focused on what matters: steady, sustainable progress.</p>
                <!-- This div creates a Bootstrap grid row for responsive columns. -->
                <div class="row g-3 mt-3">
                    <!-- This div creates a responsive Bootstrap column inside the current row. -->
                    <div class="col-4"><strong class="h2 d-block">1</strong><small class="text-muted">Connected experience</small></div>
                    <!-- This div creates a responsive Bootstrap column inside the current row. -->
                    <div class="col-4"><strong class="h2 d-block">1:1</strong><small class="text-muted">Coach guidance</small></div>
                    <!-- This div creates a responsive Bootstrap column inside the current row. -->
                    <div class="col-4"><strong class="h2 d-block">360°</strong><small class="text-muted">Progress view</small></div>
                </div>
            </div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-lg-6">
                <!-- This div groups related page content using the “about-mosaic” layout style. -->
                <div class="about-mosaic">
                    <!-- This div creates a bordered content card for related information. -->
                    <div class="mosaic-card mosaic-member">
                        <!-- This div groups related page content using the “label” layout style. -->
                        <div class="label"><strong>Your journey</strong><small class="d-block text-white-50">From your first session to your next milestone</small></div>
                    </div>
                    <!-- This div creates a bordered content card for related information. -->
                    <div class="mosaic-card mosaic-training">
                        <!-- This div groups related page content using the “label” layout style. -->
                        <div class="label"><strong>Train with purpose</strong></div>
                    </div>
                    <!-- This div creates a bordered content card for related information. -->
                    <div class="mosaic-card mosaic-nutrition">
                        <!-- This div groups related page content using the “label” layout style. -->
                        <div class="label"><strong>Fuel with confidence</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- This section starts a new content section and keeps related information together. -->
<section class="section section-soft">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container">
        <!-- This div creates a Bootstrap grid row for responsive columns. -->
        <div class="row g-4">
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-6">
                <!-- This div shows one main PowerFit benefit in a reusable card layout. -->
                <div class="feature-card">
                    <!-- This div groups related page content using the “feature-icon” layout style. -->
                    <div class="feature-icon"><i class="bi bi-bullseye"></i></div>
                    <h3>Our promise</h3>
                    <p>Make every interaction feel clear, supportive and purposeful — from choosing a membership to following your plan and celebrating progress.</p>
                </div>
            </div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-6">
                <!-- This div shows one main PowerFit benefit in a reusable card layout. -->
                <div class="feature-card">
                    <!-- This div groups related page content using the “feature-icon” layout style. -->
                    <div class="feature-icon"><i class="bi bi-diagram-3"></i></div>
                    <h3>Built around your journey</h3>
                    <p>Your membership, coaching guidance, nutrition support, progress and important updates stay close together, giving you a more consistent experience every time you return.</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/public_footer.php'; ?>