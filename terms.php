<?php

$pageTitle = 'Terms & Conditions';
//  Load the shared file needed before this page continues.
require __DIR__ . '/includes/public_header.php'; ?>
<!-- This section starts the main hero section shown at the top of the page. -->
<section class="page-hero">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container">
        <h1 class="mt-3">Terms & Conditions</h1>
        <p>Academic project terms for the PowerFit demonstration system.</p>
    </div>
</section>
<!-- This section starts a new content section and keeps related information together. -->
<section class="section">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container legal-content">
        <p><strong>Last updated:</strong> <?= date('d F Y') ?></p>
        <h2>1. Purpose of the platform</h2>
        <p>PowerFit is designed to support gym administration, memberships, payments, workout planning, nutrition planning, progress tracking, coach ratings, notifications and reporting.</p>
        <h2>2. Account responsibilities</h2>
        <p>Users must keep their login details private and use only the functions permitted for their assigned role. Access to another user’s records without authorization is prohibited.</p>
        <h2>3. Fitness and nutrition information</h2>
        <p>Workout and meal information in the system is intended to organize guidance created by authorized gym staff. The platform does not replace professional medical advice, diagnosis or treatment.</p>
        <h2>4. Payments</h2>
        <p>The current academic version records payments made to the gym. It does not process bank-card payments directly.</p>
        <h2>5. Uploaded content</h2>
        <p>Progress photographs must follow allowed file type and size rules. Users must not upload unlawful or unrelated material.</p>
        <h2>6. Availability</h2>
        <p>Because this is an academic/local deployment, uninterrupted availability is not guaranteed. Regular backups are recommended.</p>
        <h2>7. Changes</h2>
        <p>These terms may be updated when project functionality changes.</p>
    </div>
</section><?php require __DIR__ . '/includes/public_footer.php'; ?>