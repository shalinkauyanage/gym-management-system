<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
  refreshCurrentUserSecurityState();
  enforceAccountSetup();
  //  Redirect the browser after this action to avoid repeating the same request.
  redirect(roleDashboard(currentUser()['role']));
}

$error = '';

// POST handling: only run this block when a form has been submitted.
//  Run this block only when the form is submitted with POST.
if (isPost()) {
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();
  $identity = trim($_POST['identity'] ?? '');
  $password = $_POST['password'] ?? '';

  if (attemptLogin($pdo, $identity, $password)) {
    $user = currentUser();

    if (!empty($user['auth_schema_ready']) && ($user['role'] ?? '') !== 'Admin') {
      if (empty($user['email_verified_at'])) {
        redirect('index.php?auth=verify_email');
      }
      if ((int) ($user['must_change_password'] ?? 0) === 1) {
        redirect('index.php?auth=change_password');
      }
    }

    //  Redirect the browser after this action to avoid repeating the same request.
    redirect(roleDashboard($user['role']));
  }

  //  Save an error message and redirect to index.php so user stays on the home page with the login modal.
  flash('danger', 'Invalid credentials or inactive account.');
  $_SESSION['login_error'] = 'Invalid credentials or inactive account.';
  $_SESSION['login_identity'] = $identity;
  redirect('index.php?auth=signin');
}

//  Redirect GET requests to index.php so authentication is always displayed on the main website.
redirect('index.php?auth=signin');
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sign in | PowerFit</title>
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
        <a class="brand-lockup text-decoration-none text-dark" href="<?= url('index.php') ?>">
          <span>POWERFIT</span>
        </a>
        <a href="<?= url('index.php') ?>" class="btn-close" aria-label="Close" title="Back to website"></a>
      </div>

      <small class="text-uppercase fw-bold text-muted">Welcome back</small>
      <h1 class="mt-2">Sign in to PowerFit</h1>
      <p class="text-muted mb-4">Welcome back. Sign in to continue your PowerFit experience.</p>

      <?php if ($error): ?>
        <!-- This div shows a feedback message such as success, warning or error. -->
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div>
      <?php endif; ?>

      <!-- This form collects user input and submits this form using the POST method. -->
      <form method="post" data-loading-form>
        <?= csrfField() ?>
        <!-- This div groups related page content using the “mb-3” layout style. -->
        <div class="mb-3">
          <label class="form-label">Username or email</label>
          <input class="form-control" name="identity" required autocomplete="username" placeholder="Enter username or email">
        </div>
        <!-- This div groups related page content using the “mb-3” layout style. -->
        <div class="mb-3">
          <label class="form-label">Password</label>
          <!-- This div groups password input controls and related actions. -->
          <div class="password-input-wrap">
            <input class="form-control" type="password" name="password" required autocomplete="current-password" placeholder="Enter password">
            <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
        <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" type="submit">Sign in <i class="bi bi-arrow-right ms-2"></i></button>
      </form>

      <!-- This div uses flexbox to align the child elements in this area. -->
      <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mt-4 pt-3 border-top">
        <a href="<?= url('forgot-password.php') ?>" class="fw-semibold text-decoration-none text-dark"><i class="bi bi-shield-lock me-1"></i>Forgot password?</a>
        <a href="<?= url('index.php') ?>" class="text-muted text-decoration-none"><i class="bi bi-arrow-left me-2"></i>Back to website</a>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= url('assets/js/app.js') ?>"></script>
</body>

</html>