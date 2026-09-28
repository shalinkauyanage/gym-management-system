<?php

$pageTitle = 'Privacy Policy';
//  Load the shared file needed before this page continues.
require __DIR__ . '/includes/public_header.php'; ?>
<!-- This section starts the main hero section shown at the top of the page. -->
<section class="page-hero">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container">
        <h1 class="mt-3">Privacy Policy</h1>
        <p>How PowerFit handles member and staff information in the local academic system.</p>
    </div>
</section>
<!-- This section starts a new content section and keeps related information together. -->
<section class="section">
    <!-- This div keeps this section content aligned inside the main page width. -->
    <div class="container legal-content">
        <p><strong>Last updated:</strong> <?= date('d F Y') ?></p>
        <h2>1. Information stored</h2>
        <p>The system may store account details, member profile details, membership history, payments, workout plans, meal plans, progress measurements, progress photographs, coach ratings and system notifications.</p>
        <h2>2. Why information is used</h2>
        <p>Information is used only for authorized gym administration, training, nutrition advisory, member self-service, reporting and system security.</p>
        <h2>3. Role-based access</h2>
        <p>Members should only access their own records. Coaches should access only the members assigned to them for training and nutrition responsibilities. Financial and administrative information is restricted to authorized administration users.</p>
        <h2>4. Passwords and database security</h2>
        <p>Passwords are stored as hashes. The application uses PHP sessions, role checks, server-side validation and PDO prepared statements.</p>
        <h2>5. Progress photographs</h2>
        <p>Uploaded photographs are treated as private member records and should not be published through public links.</p>
        <h2>6. Retention</h2>
        <p>Historical membership, plan, payment and assignment records may be kept when required for accountability and reporting.</p>
        <h2>7. Contact</h2>
        <p>For this academic version, privacy questions should be directed to the project team or authorized gym administrator.</p>
    </div>
</section><?php require __DIR__ . '/includes/public_footer.php'; ?>