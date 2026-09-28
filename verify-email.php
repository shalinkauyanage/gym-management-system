<?php

//  Load the shared file needed before this page continues.
if (file_exists(__DIR__ . '/includes/email_auth.php')) {
  require_once __DIR__ . '/includes/email_auth.php';
} else {
  require_once __DIR__ . '/includes/email_aut.php';
}

if (!isLoggedIn()) {
  //  Save a one-time message so the next page can tell the user what happened.
  flash('warning', 'Please sign in to verify your email.');
  redirect('index.php?auth=signin');
}

$user = currentUser();
if (($user['role'] ?? '') === 'Admin') {
  //  Redirect the browser after this action to avoid repeating the same request.
  redirect(roleDashboard($user['role']));
}

if (!emailAuthSchemaReady($pdo)) {
  http_response_code(500);
  $setupError = 'Email verification is not installed in the database. Import database/patch_email_verification.sql once, then reload this page.';
} else {
  $setupError = '';
}

$dbUser = !$setupError
  ? one($pdo, 'SELECT user_id, username, email, email_verified_at, must_change_password FROM users WHERE user_id = ?', [(int) $user['id']])
  : null;

if (!$setupError && !$dbUser) {
  session_destroy();
  //  Redirect the browser after this action to avoid repeating the same request.
  redirect('index.php?auth=signin');
}

if (!$setupError && !empty($dbUser['email_verified_at'])) {
  $_SESSION['user']['email_verified_at'] = $dbUser['email_verified_at'];
  if ((int) ($dbUser['must_change_password'] ?? 0) === 1) {
    //  Redirect the browser after this action to avoid repeating the same request.
    redirect('index.php?auth=change_password');
  }
  //  Redirect the browser after this action to avoid repeating the same request.
  redirect(roleDashboard($user['role']));
}

if (!$setupError && isPost()) {
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();
  $mode = $_POST['mode'] ?? '';

  if ($mode === 'send_code') {
    $result = createAndSendEmailOtp($pdo, $dbUser, 'email_verification');
    if ($result['success']) {
      $_SESSION['verify_notice'] = $result['message'];
    } else {
      $_SESSION['verify_error'] = $result['message'];
    }
    redirect('index.php?auth=verify_email');
  }

  if ($mode === 'verify_code') {
    $result = verifyEmailOtp($pdo, (int) $dbUser['user_id'], 'email_verification', trim($_POST['otp'] ?? ''));
    if (!$result['success']) {
      $_SESSION['verify_error'] = $result['message'];
      redirect('index.php?auth=verify_email');
    } else {
      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE user_id = ?')->execute([(int) $dbUser['user_id']]);
      $_SESSION['user']['email_verified_at'] = date('Y-m-d H:i:s');
      //  Save a one-time message so the next page can tell the user what happened.
      flash('success', 'Email verified successfully.');

      if ((int) ($dbUser['must_change_password'] ?? 0) === 1) {
        //  Redirect to change password in the modal on the same website.
        redirect('index.php?auth=change_password');
      }
      //  Redirect the browser after this action to avoid repeating the same request.
      redirect(roleDashboard($user['role']));
    }
  }
}

// Redirect GET requests so verification always opens inside the website modal.
redirect('index.php?auth=verify_email');
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Verify email | PowerFit</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
  <link href="<?= url('assets/css/auth.css') ?>" rel="stylesheet">
</head>

<body class="auth-page auth-popup-page" data-app-url="<?= e(url()) ?>">
  <!-- This div groups login, verification or password-recovery content. -->
  <div class="auth-shell-simple">
    <!-- This div groups login, verification or password-recovery content. -->
    <div class="auth-security-card position-relative">
      <!-- This div uses flexbox to align the child elements in this area. -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="brand-lockup text-decoration-none text-dark" href="<?= url('index.php') ?>"><span>POWERFIT</span></a>
        <a href="<?= url('logout.php') ?>" class="btn-close" aria-label="Sign out" title="Sign out"></a>
      </div>

      <!-- This div groups login, verification or password-recovery content. -->
      <small class="text-uppercase fw-bold text-muted">First sign-in verification</small>
      <h1 class="mt-2">Verify your email</h1>
      <p class="text-muted mb-4">Confirm the email address registered by the PowerFit administrator before opening your dashboard.</p>

      <?php if ($setupError): ?>
        <!-- This div shows a feedback message such as success, warning or error. -->
        <div class="alert alert-danger"><?= e($setupError) ?></div>
      <?php else: ?>
        <!-- This div creates a bordered content card for related information. -->
        <div class="masked-email-card mb-4">
          <i class="bi bi-envelope-fill"></i>
          <!-- This div groups related HTML content so the page structure is easier to manage. -->
          <div><small>Verification email</small><strong><?= e(maskEmailAddress($dbUser['email'])) ?></strong></div>
        </div>

        <?php if ($error): ?><!-- This div shows a feedback message such as success, warning or error. -->
          <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div><?php endif; ?>
        <?php if ($notice): ?><!-- This div shows a feedback message such as success, warning or error. -->
          <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i><?= e($notice) ?></div><?php endif; ?>

        <?php if ($localOtp): ?>
          <!-- This div creates a bordered content card for related information. -->
          <div class="local-otp-card mb-4">
            <small>LOCAL EMAIL TEST CODE</small>
            <strong><?= e($localOtp) ?></strong>
            <span>Visible only when MAIL_DRIVER is set to local.</span>
          </div>
        <?php endif; ?>

        <?php if (!$codeSent): ?>
          <!-- This form collects user input and submits this form using the POST method. -->
          <form method="post" data-loading-form>
            <?= csrfField() ?>
            <input type="hidden" name="mode" value="send_code">
            <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" type="submit">Send verification code <i class="bi bi-send ms-2"></i></button>
          </form>
        <?php else: ?>
          <!-- This form collects user input and submits this form using the POST method. -->
          <form method="post" data-loading-form>
            <?= csrfField() ?>
            <input type="hidden" name="mode" value="verify_code">
            <!-- This div groups related page content using the “mb-3” layout style. -->
            <div class="mb-3">
              <label class="form-label"><?= requiredLabel('6-digit code') ?></label>
              <input class="form-control otp-input" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" placeholder="000000">
            </div>
            <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" type="submit">Verify email <i class="bi bi-shield-check ms-2"></i></button>
          </form>
          <!-- This form collects user input and submits this form using the POST method. -->
          <form method="post" class="mt-3 text-center">
            <?= csrfField() ?>
            <input type="hidden" name="mode" value="send_code">
            <button class="btn btn-link text-decoration-none" type="submit">Send another code</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>

      <a href="<?= url('logout.php') ?>" class="d-inline-flex mt-4 text-muted text-decoration-none"><i class="bi bi-box-arrow-left me-2"></i>Sign out</a>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= url('assets/js/app.js') ?>"></script>
</body>

</html>