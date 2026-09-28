<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Contact';

$userProfile = null;
$roleIcon = 'assets/images/member-ic.webp';
if (isLoggedIn()) {
  $currentUserRole = currentUser()['role'] ?? 'Member';
  $roleIcon = match (true) {
    $currentUserRole === 'Admin' => 'assets/images/admin-ic.webp',
    isCoachRole($currentUserRole) => 'assets/images/trainer-ic.webp',
    default => 'assets/images/member-ic.webp',
  };
  $userProfile = one($pdo, 'SELECT user_id, username, email FROM users WHERE user_id = ?', [currentUser()['id']]);
  if ($currentUserRole === 'Member') {
    $memberProfile = getMemberByUser($pdo, currentUser()['id']);
    if ($memberProfile) {
      $userProfile['display_name'] = $memberProfile['full_name'];
    }
  }
  $userProfile['display_name'] ??= coachDisplayName($pdo, currentUser()['id']) ?: $userProfile['username'];
}

// POST handling: only run this block when a form has been submitted.
//  Run this block only when the form is submitted with POST.
if (isPost()) {
  requireLogin();
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();

  $subject = trim($_POST['subject'] ?? '');
  $message = trim($_POST['message'] ?? '');
  if ($message === '') {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('danger', 'Write a message before sending.');
    redirect('contact.php');
  }

  if (!tableExists($pdo, 'contact_messages')) {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('warning', 'Import the final database patch before using contact messages.');
    redirect('contact.php');
  }

  $name = $userProfile['display_name'] ?? currentUser()['username'];
  $email = $userProfile['email'] ?? '';
  if (columnExists($pdo, 'contact_messages', 'user_id')) {
    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare(
      'INSERT INTO contact_messages(user_id, name, email, subject, message) VALUES(?, ?, ?, ?, ?)',
    )->execute([currentUser()['id'], $name, $email, $subject ?: null, $message]);
  } else {
    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare(
      'INSERT INTO contact_messages(name, email, subject, message) VALUES(?, ?, ?, ?)',
    )->execute([$name, $email, $subject ?: null, $message]);
  }
  //  Save a one-time message so the next page can tell the user what happened.
  flash('success', 'Message sent successfully. You will receive the admin reply in Notifications.');
  redirect('contact.php');
}

//  Load the shared file needed before this page continues.
require __DIR__ . '/includes/public_header.php';
?>

<!-- This section starts the main hero section shown at the top of the page. -->
<section class="page-hero">
  <!-- This div keeps this section content aligned inside the main page width. -->
  <div class="container">
    <h1 class="mt-3">We are here when you need us.</h1>
    <p class="fs-5 mt-3">Questions about your membership, account, coaching or plans? Sign in and send us a message — we will get back to you through PowerFit.</p>
  </div>
</section>

<!-- This section starts a new content section and keeps related information together. -->
<section class="section">
  <!-- This div keeps this section content aligned inside the main page width. -->
  <div class="container">
    <!-- This div creates a Bootstrap grid row for responsive columns. -->
    <div class="row g-5 align-items-start">
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-lg-5">
        <h2 class="section-title mt-3">Support that stays personal.</h2>
        <p class="section-lead">Tell us what you need help with and the PowerFit team will respond through your account, keeping your conversation easy to find and follow.</p>
      </div>

      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-lg-7">
        <!-- This div groups related page content using the “panel p-4” layout style. -->
        <div class="panel p-4 p-lg-5">
          <?php if (!isLoggedIn()): ?>
            <!-- This div groups related page content using the “contact-login-gate text-center” layout style. -->
            <div class="contact-login-gate text-center py-4">
              <!-- This div groups related page content using the “feature-icon mx-auto” layout style. -->
              <div class="feature-icon mx-auto"><i class="bi bi-shield-lock"></i></div>
              <h3 class="mt-3">Sign in before sending a message</h3>
              <p class="text-muted">Sign in so we can connect your request to your account and make sure the reply reaches you directly.</p>
              <a class="btn btn-danger rounded-pill px-4" href="<?= url('login.php') ?>">Sign in to contact us</a>
            </div>
          <?php else: ?>
            <!-- This div uses flexbox to align the child elements in this area. -->
            <div class="d-flex align-items-center gap-3 mb-4">
              <!-- This div holds a small status or category label. -->
              <div class="avatar-sm avatar-role-badge">
                <img src="<?= url($roleIcon) ?>" alt="<?= e(roleLabel($currentUserRole ?? currentUser()['role'])) ?>" class="avatar-role-icon">
              </div>
              <!-- This div groups related HTML content so the page structure is easier to manage. -->
              <div><strong><?= e($userProfile['display_name']) ?></strong><small class="d-block text-muted"><?= e($userProfile['email']) ?></small></div>
            </div>
            <!-- This form collects user input and submits this form using the POST method. -->
            <form method="post" class="row g-3" data-loading-form>
              <?= csrfField() ?>
              <!-- This div creates a responsive Bootstrap column inside the current row. -->
              <div class="col-12"><label class="form-label">Subject</label><input class="form-control" name="subject" maxlength="160" placeholder="What can we help you with?"></div>
              <!-- This div creates a responsive Bootstrap column inside the current row. -->
              <div class="col-12"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="7" maxlength="3000" required placeholder="Tell us a little more about your question or concern"></textarea></div>
              <!-- This div creates a responsive Bootstrap column inside the current row. -->
              <div class="col-12"><button class="btn btn-dark rounded-pill px-4 py-3" type="submit"><i class="bi bi-send me-2"></i>Send message</button></div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/public_footer.php'; ?>