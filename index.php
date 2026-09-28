<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/includes/functions.php';

//  Run this block only when the form is submitted with POST.
if (isPost() && ($_POST['mode'] ?? '') === 'home_rating') {
    requireRole('Member');
    //  Check the CSRF token before saving, updating or deleting data.
    verifyCsrf();
    $member = getMemberByUser($pdo, currentUser()['id']);
    $score = (int) ($_POST['score'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    $trainer = $member && tableExists($pdo, 'trainer_assignments')
        ? one(
            $pdo,
            "SELECT t.trainer_id
             FROM trainer_assignments a
             JOIN trainers t ON t.trainer_id = a.trainer_id
             WHERE a.member_id = ? AND a.status = 'Active'
             ORDER BY a.assignment_id DESC LIMIT 1",
            [$member['member_id']],
        )
        : null;

    if (!$trainer && $member) {
        $trainer = one(
            $pdo,
            "SELECT trainer_id FROM workout_plans WHERE member_id = ? ORDER BY start_date DESC LIMIT 1",
            [$member['member_id']],
        );
    }

    if (!$trainer || $score < 1 || $score > 5) {
        //  Save a one-time message so the next page can tell the user what happened.
        flash('danger', 'A valid coach assignment and rating from 1 to 5 are required.');
        redirect('index.php#coach-ratings');
    }

    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare(
        "INSERT INTO trainer_ratings(trainer_id, member_id, score, comment, status, rating_date)
         VALUES(?, ?, ?, ?, 'Visible', CURDATE())",
    )->execute([$trainer['trainer_id'], $member['member_id'], $score, $comment]);

    $average = (float) scalar(
        $pdo,
        "SELECT COALESCE(AVG(score), 0) FROM trainer_ratings WHERE trainer_id = ? AND status = 'Visible'",
        [$trainer['trainer_id']],
    );
    $total = (float) scalar(
        $pdo,
        "SELECT COALESCE(SUM(score), 0) FROM trainer_ratings WHERE trainer_id = ? AND status = 'Visible'",
        [$trainer['trainer_id']],
    );
    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare('UPDATE trainers SET average_rating = ?, total_rating = ? WHERE trainer_id = ?')->execute([
        $average,
        $total,
        $trainer['trainer_id'],
    ]);

    //  Save a one-time message so the next page can tell the user what happened.
    flash('success', 'Thank you. Your coach rating was submitted successfully.');
    redirect('index.php#coach-ratings');
}

$pageTitle = 'Home';
//  Load the shared file needed before this page continues.
require __DIR__ . '/includes/public_header.php';

$packages = all(
    $pdo,
    "SELECT * FROM packages WHERE status = 'Active' ORDER BY CASE
        WHEN package_name LIKE '%Annual%' THEN 1
        WHEN package_name LIKE '%Personal%' THEN 2
        WHEN package_name LIKE '%Monthly%' THEN 3
        ELSE 4
    END, fee ASC LIMIT 3",
);

$nameExpression = columnExists($pdo, 'trainers', 'display_name')
    ? "COALESCE(NULLIF(t.display_name, ''), u.username)"
    : 'u.username';
$publicRatings = all(
    $pdo,
    "SELECT r.score, r.comment, r.rating_date, {$nameExpression} AS coach_name
     FROM trainer_ratings r
     JOIN trainers t ON t.trainer_id = r.trainer_id
     JOIN users u ON u.user_id = t.user_id
     WHERE r.status = 'Visible' AND r.comment IS NOT NULL AND r.comment <> ''
     ORDER BY r.rating_date DESC, r.rating_id DESC
     LIMIT 10",
);

?>

<!-- This section starts the main hero section shown at the top of the page. -->
<section class="hero hero-video">
    <!-- This video plays the local hero video while keeping it muted for automatic playback. -->
    <video
        class="hero-video-media"
        autoplay
        muted
        loop
        playsinline
        preload="auto"
        aria-hidden="true">
        <source src="<?= url('assets/video/powerfit-hero.mp4') ?>" type="video/mp4">
    </video>

    <!-- This div adds a light dark overlay so hero text stays readable over the video. -->
    <div class="hero-video-overlay"></div>
    <!-- This div adds the subtle grid effect on top of the hero video. -->
    <div class="hero-grid-overlay"></div>

    <!-- This div holds the visible hero text and buttons above the background video. -->
    <div class="container hero-content">
        <!-- This div creates a Bootstrap grid row for responsive columns. -->
        <div class="row align-items-center g-5">
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-xl-8 col-lg-9">

                <h1>
                    Unleash Your<br>
                    <span class="accent">Potential</span>
                </h1>

                <!-- This div groups related page content using the “typing-line” layout style. -->
                <div
                    class="typing-line"
                    data-typing="Flexible memberships|Personal coaching|Nutrition guidance|Progress tracking|Support that stays connected"></div>

                <!-- This div groups the main call-to-action buttons in the hero area. -->
                <div class="hero-actions">
                    <a class="btn-swap" href="<?= url('packages.php') ?>">
                        <span>Explore memberships</span>
                        <span class="round-icon"><i class="bi bi-arrow-up-right"></i></span>
                    </a>
                    <a class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" href="<?= url('login.php') ?>">
                        <span class="btn-ripple-circle"></span>
                        <span class="btn-ripple-label">Enter PowerFit</span>
                    </a>
                </div>

                <!-- This div groups the small hero highlights shown under the main heading. -->
                <div class="hero-statbar">
                    <!-- This div shows one short hero highlight and its supporting label. -->
                    <div class="hero-stat">
                        <strong>Move</strong>
                        <span>With intent</span>
                    </div>
                    <!-- This div shows one short hero highlight and its supporting label. -->
                    <div class="hero-stat">
                        <strong>Build</strong>
                        <span>Real strength</span>
                    </div>
                    <!-- This div shows one short hero highlight and its supporting label. -->
                    <div class="hero-stat">
                        <strong>Track</strong>
                        <span>Real progress</span>
                    </div>
                    <!-- This div shows one short hero highlight and its supporting label. -->
                    <div class="hero-stat">
                        <strong>Become</strong>
                        <span>Your best</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- This section starts a new content section and keeps related information together. -->
<section class="section">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container">
        <!-- This div creates a Bootstrap grid row for responsive columns. -->
        <div class="row align-items-end g-4 mb-5">
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-lg-7">
                <h2 class="section-title mt-3">Everything you need to move forward.</h2>
            </div>
        </div>

        <!-- This div creates a Bootstrap grid row for responsive columns. -->
        <div class="row g-4">
            <?php
            $features = [
                ['bi-people', 'Memberships that fit your routine', 'Choose a membership that matches your schedule, goals and level of support.', 'images/advantage-members.webp'],
                ['bi-credit-card', 'Simple membership payments', 'Keep your membership and payment history easy to review whenever you need it.', 'images/advantage-payments.webp'],
                ['bi-activity', 'Personal coaching', 'Follow clear, goal-focused workout plans created to support your training journey.', 'images/advantage-training.webp'],
                ['bi-egg-fried', 'Practical nutrition guidance', 'Build sustainable meal routines around familiar foods and your personal goals.', 'images/advantage-nutrition.webp'],
                ['bi-graph-up-arrow', 'Progress you can see', 'Review measurements, progress photos and milestones as your consistency adds up.', 'images/advantage-progress.webp'],
                ['bi-star', 'A voice in your experience', 'Share feedback about your coaching experience and help us keep support at a high standard.', 'images/advantage-feedback.webp'],
            ];
            ?>

            <?php foreach ($features as $feature): ?>
                <!-- This div creates a responsive Bootstrap column inside the current row. -->
                <div class="col-md-6 col-xl-4">
                    <!-- This div shows one main PowerFit benefit in a reusable card layout. -->
                    <div class="feature-card feature-card-with-media">
                        <!-- This div holds the visual area used by this feature card. -->
                        <div class="feature-media">
                            <img src="<?= url('assets/' . $feature[3]) ?>" alt="" loading="lazy">
                        </div>
                        <!-- This div holds the text and icon inside one feature card. -->
                        <div class="feature-card-content">
                            <!-- This div groups related page content using the “feature-icon” layout style. -->
                            <div class="feature-icon"><i class="bi <?= e($feature[0]) ?>"></i></div>
                            <h3><?= e($feature[1]) ?></h3>
                            <p><?= e($feature[2]) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- This section starts a new content section and keeps related information together. -->
<section class="section section-dark">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container">
        <!-- This div creates a Bootstrap grid row for responsive columns. -->
        <div class="row g-4 align-items-center mb-5">
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-lg-7">
                <h2 class="section-title mt-3">A better gym experience, from day one to your next milestone.</h2>
            </div>
        </div>

        <!-- This div creates a Bootstrap grid row for responsive columns. -->
        <div class="row g-4">
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-lg-4">
                <!-- This div shows one highlighted service benefit in the dark feature section. -->
                <div class="visual-card visual-card-admin">
                    <!-- This div groups related HTML content so the page structure is easier to manage. -->
                    <div>
                        <h3>Everything important, easy to find.</h3>
                        <!-- This div groups the three short supporting points inside this card. -->
                        <div class="metric-strip">
                            <!-- This div groups related HTML content so the page structure is easier to manage. -->
                            <div><strong>Easy</strong><small>Access</small></div>
                            <!-- This div groups related HTML content so the page structure is easier to manage. -->
                            <div><strong>Clear</strong><small>History</small></div>
                            <!-- This div groups related HTML content so the page structure is easier to manage. -->
                            <div><strong>Ready</strong><small>Updates</small></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-lg-4">
                <!-- This div shows one highlighted service benefit in the dark feature section. -->
                <div class="visual-card visual-card-coach">
                    <!-- This div groups related HTML content so the page structure is easier to manage. -->
                    <div>
                        <h3>Coaching that evolves with you.</h3>
                        <!-- This div groups the three short supporting points inside this card. -->
                        <div class="metric-strip">
                            <!-- This div groups related HTML content so the page structure is easier to manage. -->
                            <div><strong>1:1</strong><small>Support</small></div>
                            <!-- This div groups related HTML content so the page structure is easier to manage. -->
                            <div><strong>Fresh</strong><small>Plans</small></div>
                            <!-- This div groups related HTML content so the page structure is easier to manage. -->
                            <div><strong>Goal</strong><small>Focused</small></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-lg-4">
                <!-- This div shows one highlighted service benefit in the dark feature section. -->
                <div class="visual-card visual-card-member">
                    <!-- This div groups related HTML content so the page structure is easier to manage. -->
                    <div>
                        <h3>See how far you have come.</h3>
                        <!-- This div groups the three short supporting points inside this card. -->
                        <div class="metric-strip">
                            <!-- This div groups related HTML content so the page structure is easier to manage. -->
                            <div><strong>Track</strong><small>Change</small></div>
                            <!-- This div groups related HTML content so the page structure is easier to manage. -->
                            <div><strong>Stay</strong><small>Motivated</small></div>
                            <!-- This div groups related HTML content so the page structure is easier to manage. -->
                            <div><strong>Own</strong><small>Journey</small></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- This section starts a new content section and keeps related information together. -->
<section class="section rating-marquee-section" id="coach-ratings">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container mb-4">
        <!-- This div creates a Bootstrap grid row for responsive columns. -->
        <div class="row align-items-center g-4">
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-lg-7">
                <h2 class="section-title my-0">Member voices. Real coaching experiences.</h2>
            </div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-lg-5 align-self-center d-flex align-items-center">
                <?php if (isLoggedIn() && currentUser()['role'] === 'Member'): ?>
                    <a class="btn btn-interactive-hover btn-interactive-dark rounded-pill" data-bs-toggle="modal" data-bs-target="#homeRatingModal">
                        <span class="btn-interactive-initial">Rate your coach</span>
                        <span class="btn-interactive-hover-content">
                            <span>Rate your coach</span>
                            <i class="bi bi-arrow-right"></i>
                        </span>
                        <span class="btn-interactive-dot"></span>
                    </a>
                <?php else: ?>
                    <a href="<?= url('login.php') ?>" class="btn btn-interactive-hover btn-interactive-dark rounded-pill">
                        <span class="btn-interactive-initial">Rate your coach</span>
                        <span class="btn-interactive-hover-content">
                            <span>Rate your coach</span>
                            <i class="bi bi-arrow-right"></i>
                        </span>
                        <span class="btn-interactive-dot"></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($publicRatings): ?>
        <!-- This div groups related page content using the “rating-marquee” layout style. -->
        <div class="rating-marquee" aria-label="Recent coach ratings">
            <!-- This div groups related page content using the “rating-marquee-track” layout style. -->
            <div class="rating-marquee-track">
                <?php foreach (array_merge($publicRatings, $publicRatings) as $rating): ?>
                    <article class="rating-marquee-card">
                        <!-- This div uses flexbox to align the child elements in this area. -->
                        <div class="d-flex justify-content-between gap-3 align-items-start">
                            <!-- This div groups related HTML content so the page structure is easier to manage. -->
                            <div><small class="text-muted">Coach</small><strong class="d-block"><?= e($rating['coach_name']) ?></strong></div>
                            <!-- This div groups related page content using the “rating-stars” layout style. -->
                            <div class="rating-stars"><?= str_repeat('★', (int) $rating['score']) ?></div>
                        </div>
                        <p>“<?= e($rating['comment']) ?>”</p>
                        <small class="text-muted">Verified PowerFit member · <?= shortDate($rating['rating_date']) ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- This div keeps this section content aligned inside the main page width. -->
        <div class="container">
            <!-- This div groups related page content using the “panel text-center” layout style. -->
            <div class="panel text-center py-5"><i class="bi bi-star fs-2 text-muted"></i>
                <p class="text-muted mb-0 mt-2">Coach ratings will appear here after members submit feedback.</p>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php if (isLoggedIn() && currentUser()['role'] === 'Member'): ?>
    <!-- This div creates the popup content area shown above the current page. -->
    <div class="modal fade" id="homeRatingModal" tabindex="-1" aria-hidden="true">
        <!-- This div creates the popup content area shown above the current page. -->
        <div class="modal-dialog modal-dialog-centered">
            <!-- This div creates the popup content area shown above the current page. -->
            <div class="modal-content rating-modal-content">
                <!-- This div creates the popup content area shown above the current page. -->
                <div class="modal-header border-0">
                    <!-- This div groups related HTML content so the page structure is easier to manage. -->
                    <div><small class="text-muted">Member feedback</small>
                        <h5 class="modal-title">Rate your coach</h5>
                    </div>
                    <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <!-- This form collects user input and submits this form using the POST method. -->
                <form method="post" data-loading-form>
                    <!-- This div creates the popup content area shown above the current page. -->
                    <div class="modal-body pt-0">
                        <?= csrfField() ?>
                        <input type="hidden" name="mode" value="home_rating">
                        <label class="form-label">Rating</label>
                        <select class="form-select mb-3" name="score" required>
                            <option value="">Choose 1 to 5</option>
                            <option value="5">5 — Excellent</option>
                            <option value="4">4 — Good</option>
                            <option value="3">3 — Satisfactory</option>
                            <option value="2">2 — Needs improvement</option>
                            <option value="1">1 — Poor</option>
                        </select>
                        <label class="form-label">Comment</label>
                        <textarea class="form-control" name="comment" rows="4" maxlength="500" placeholder="Share constructive feedback"></textarea>
                    </div>
                    <!-- This div creates the popup content area shown above the current page. -->
                    <div class="modal-footer border-0"><button class="btn btn-dark rounded-pill px-4">Submit rating</button></div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- This section starts a new content section and keeps related information together. -->
<section class="section section-soft">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container">
        <!-- This div creates a Bootstrap grid row for responsive columns. -->
        <div class="row align-items-end g-4 mb-5">
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-lg-7">
                <h2 class="section-title mt-3">Find the membership that fits your goals.</h2>
            </div>
        </div>

        <!-- This div creates a Bootstrap grid row for responsive columns. -->
        <div class="row g-4 justify-content-center">
            <?php if ($packages): ?>
                <?php foreach ($packages as $index => $package): ?>
                    <?php
                    $name = strtolower($package['package_name']);
                    $isAnnual = str_contains($name, 'annual');
                    $isPersonal = str_contains($name, 'personal');
                    $label = $isAnnual ? 'Best value' : ($isPersonal ? 'Coaching' : 'Membership');
                    $packageImageUrl = packageImageUrl($package);
                    ?>
                    <!-- This div creates a responsive Bootstrap column inside the current row. -->
                    <div class="col-md-6 col-lg-4">
                        <!-- This div creates one membership package card and can highlight the annual option. -->
                        <div class="package-card package-card-home <?= $isAnnual ? 'featured' : '' ?>">
                            <!-- This div creates a bordered content card for related information. -->
                            <div class="package-card-media">
                                <img src="<?= e($packageImageUrl) ?>" alt="<?= e($package['package_name']) ?>" loading="lazy">
                            </div>
                            <!-- This div uses flexbox to align the child elements in this area. -->
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <small class="text-uppercase fw-bold opacity-75"><?= e($label) ?></small>
                                <i class="bi <?= $isPersonal ? 'bi-person-arms-up' : 'bi-lightning-charge' ?>"></i>
                            </div>
                            <h3 class="mt-3"><?= e($package['package_name']) ?></h3>
                            <p class="opacity-75"><?= e($package['description']) ?></p>
                            <!-- This div groups related page content using the “price” layout style. -->
                            <div class="price"><?= money($package['fee']) ?></div>
                            <small class="opacity-75">
                                <?= (int) $package['duration_months'] ?> month<?= (int) $package['duration_months'] === 1 ? '' : 's' ?>
                            </small>
                            <ul>
                                <li><i class="bi bi-check-circle-fill"></i>Member portal access</li>
                                <li><i class="bi bi-check-circle-fill"></i>Membership & payment history</li>
                                <li><i class="bi bi-check-circle-fill"></i>Progress tracking</li>
                            </ul>
                            <a class="btn <?= $isAnnual ? 'btn-power' : 'btn-dark' ?> w-100 rounded-pill" href="<?= url('packages.php') ?>">
                                View package
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- This div creates a responsive Bootstrap column inside the current row. -->
                <div class="col-12">
                    <!-- This div groups related page content using the “panel text-center” layout style. -->
                    <div class="panel text-center py-5">
                        <p class="mb-0 text-muted">Membership packages will appear here when active packages are available.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- This div groups related page content using the “text-center mt-4” layout style. -->
        <div class="text-center mt-4">
            <a class="btn btn-outline-dark rounded-pill px-4" href="<?= url('packages.php') ?>">
                View all packages <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>

<!-- This section starts a new content section and keeps related information together. -->
<section class="section progress-showcase-section">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container">
        <!-- This div creates a Bootstrap grid row for responsive columns. -->
        <div class="row align-items-center g-5">
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-lg-5">
                <h2 class="section-title mt-3">Your progress deserves more than a memory.</h2>
                <p class="section-lead">
                    Keep measurements, progress photos, workout plans and nutrition guidance together, so every milestone is easier to review and every next step feels clearer.
                </p>
                <!-- This div uses flexbox to align the child elements in this area. -->
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <span class="feature-pill"><i class="bi bi-shield-lock"></i>Private progress gallery</span>
                    <span class="feature-pill"><i class="bi bi-file-earmark-pdf"></i>Downloadable workout & meal plans</span>
                    <span class="feature-pill"><i class="bi bi-bell"></i>Coach updates</span>
                </div>
                <a href="<?= url('login.php') ?>" class="btn btn-interactive-hover btn-interactive-dark rounded-pill mt-4">
                    <span class="btn-interactive-initial">Open member portal</span>
                    <span class="btn-interactive-hover-content">
                        <span>Open member portal</span>
                        <i class="bi bi-arrow-right"></i>
                    </span>
                    <span class="btn-interactive-dot"></span>
                </a>
            </div>

            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-lg-7">
                <!-- This div groups the private progress showcase content. -->
                <div class="progress-vault-card">
                    <!-- This div groups the private progress showcase content. -->
                    <div class="progress-vault-media">
                        <img src="<?= url('assets/images/progress-vault.webp') ?>" alt="PowerFit progress tracking" loading="lazy">
                        <span class="progress-vault-badge"><i class="bi bi-lock-fill"></i> Your private progress space</span>
                    </div>
                    <!-- This div groups the private progress showcase content. -->
                    <div class="progress-vault-body">
                        <!-- This div groups the private progress showcase content. -->
                        <div class="progress-vault-heading">
                            <!-- This div groups related HTML content so the page structure is easier to manage. -->
                            <div><small>Your progress story</small>
                                <h3>See the journey, not just the latest number</h3>
                            </div>
                            <span class="progress-vault-status"><i class="bi bi-check2-circle"></i>Up to date</span>
                        </div>
                        <!-- This div groups the private progress showcase content. -->
                        <div class="progress-vault-grid">
                            <!-- This div groups related HTML content so the page structure is easier to manage. -->
                            <div><i class="bi bi-camera"></i><strong>Progress photos</strong><span>Compare front, side and back photos across your check-ins and see visible change over time.</span></div>
                            <!-- This div groups related HTML content so the page structure is easier to manage. -->
                            <div><i class="bi bi-activity"></i><strong>Workout plans</strong><span>Keep your latest training plan close and download it whenever you want an offline copy.</span></div>
                            <!-- This div groups related HTML content so the page structure is easier to manage. -->
                            <div><i class="bi bi-egg-fried"></i><strong>Nutrition guidance</strong><span>Review meal guidance, nutrition details and calorie targets alongside the rest of your journey.</span></div>
                            <!-- This div groups related HTML content so the page structure is easier to manage. -->
                            <div><i class="bi bi-person-check"></i><strong>Coach review</strong><span>Your coach can use your latest measurements and progress history to make more informed plan updates.</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/public_footer.php'; ?>