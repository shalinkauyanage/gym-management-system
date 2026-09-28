<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user = currentUser();

if (!columnExists($pdo, 'users', 'must_change_password') || !columnExists($pdo, 'users', 'password_changed_at')) {
  http_response_code(500);
  $setupError = 'Password-security database upgrade is not installed. Import database/patch_email_verification.sql once.';
  $dbUser = null;
} else {
  $setupError = '';
  $dbUser = one($pdo, 'SELECT user_id, username, email, password_hash, must_change_password, email_verified_at FROM users WHERE user_id = ?', [(int) $user['id']]);
}

if (!$setupError && !$dbUser) {
  session_destroy();
  //  Redirect the browser after this action to avoid repeating the same request.
  redirect('index.php?auth=signin');
}

if (!$setupError && ($user['role'] ?? '') !== 'Admin' && empty($dbUser['email_verified_at'])) {
  //  Redirect the browser after this action to avoid repeating the same request.
  redirect('index.php?auth=verify_email');
}

$firstLogin = !$setupError && (int) ($dbUser['must_change_password'] ?? 0) === 1;

if (!$setupError && isPost()) {
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();

  $currentPassword = $_POST['current_password'] ?? '';
  $newPassword = $_POST['new_password'] ?? '';
  $confirmPassword = $_POST['confirm_password'] ?? '';

  $error = '';
  if (!$firstLogin && !password_verify($currentPassword, (string) $dbUser['password_hash'])) {
    $error = 'The current password is incorrect.';
  } elseif ($newPassword !== $confirmPassword) {
    $error = 'The new passwords do not match.';
  } elseif (password_verify($newPassword, (string) $dbUser['password_hash'])) {
    $error = $firstLogin ? 'Your new password must be different from your temporary password.' : 'Your new password must be different from your current password.';
  } elseif ($passwordError = strongPasswordError($newPassword, $dbUser)) {
    $error = $passwordError;
  }

  if ($error) {
    $_SESSION['change_password_error'] = $error;
    redirect('index.php?auth=change_password');
  } else {
    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 0, password_changed_at = NOW() WHERE user_id = ?')
      ->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $user['id']]);

    $_SESSION['user']['must_change_password'] = 0;
    //  Save a one-time message so the next page can tell the user what happened.
    flash('success', 'Password changed successfully. Welcome to PowerFit.');
    redirect(roleDashboard($user['role']));
  }
}

// Redirect GET requests so password change always opens inside the website modal.
redirect('index.php?auth=change_password');
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Change password | PowerFit</title>
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
        <a href="<?= url('index.php') ?>" class="btn-close" aria-label="Close" title="Back to website"></a>
      </div>
      <h1 class="mt-2"><?= $firstLogin ? 'Create your permanent password' : 'Change your password' ?></h1>
      <p class="text-muted mb-4"><?= $firstLogin ? 'Create a strong permanent password that only you know to secure your PowerFit account.' : 'Confirm your current password, then create a new strong password.' ?></p>

      <?php if ($setupError): ?>
        <!-- This div shows a feedback message such as success, warning or error. -->
        <div class="alert alert-danger"><?= e($setupError) ?></div>
      <?php else: ?>
        <?php if ($error): ?><!-- This div shows a feedback message such as success, warning or error. -->
          <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div><?php endif; ?>

        <!-- This form collects user input and submits this form using the POST method. -->
        <form method="post" data-loading-form>
          <?= csrfField() ?>
          <?php if (!$firstLogin): ?>
            <!-- This div groups related page content using the “mb-3” layout style. -->
            <div class="mb-3">
              <label class="form-label"><?= requiredLabel('Current password') ?></label>
              <!-- This div groups password input controls and related actions. -->
              <div class="password-input-wrap">
                <input class="form-control" type="password" name="current_password" required autocomplete="current-password" placeholder="Enter current password">
                <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
          <?php endif; ?>
          <!-- This div groups related page content using the “mb-3” layout style. -->
          <div class="mb-3">
            <label class="form-label"><?= requiredLabel('New password') ?></label>
            <!-- This div groups password input controls and related actions. -->
            <div class="password-input-wrap">
              <input class="form-control" id="newPassword" type="password" name="new_password" required autocomplete="new-password" placeholder="Enter new strong password">
              <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>
          <!-- This div groups related page content using the “mb-3” layout style. -->
          <div class="mb-3">
            <label class="form-label"><?= requiredLabel('Confirm new password') ?></label>
            <!-- This div groups password input controls and related actions. -->
            <div class="password-input-wrap">
              <input class="form-control" type="password" name="confirm_password" required autocomplete="new-password" placeholder="Confirm new password">
              <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>
          <!-- This div groups password input controls and related actions. -->
          <div class="password-rules mb-4" id="passwordRules">
            <span data-rule="length"><i class="bi bi-circle"></i> 10+ characters</span>
            <span data-rule="upper"><i class="bi bi-circle"></i> Uppercase letter</span>
            <span data-rule="lower"><i class="bi bi-circle"></i> Lowercase letter</span>
            <span data-rule="number"><i class="bi bi-circle"></i> Number</span>
            <span data-rule="special"><i class="bi bi-circle"></i> Special character</span>
          </div>
          <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" type="submit">Save new password <i class="bi bi-arrow-right ms-2"></i></button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= url('assets/js/app.js') ?>"></script>
  <script>
    (() => {
      const input = document.getElementById('newPassword');
      const box = document.getElementById('passwordRules');
      if (!input || !box) return;
      const tests = {
        length: value => value.length >= 10,
        upper: value => /[A-Z]/.test(value),
        lower: value => /[a-z]/.test(value),
        number: value => /\d/.test(value),
        special: value => /[^A-Za-z0-9]/.test(value),
      };
      const refresh = () => Object.entries(tests).forEach(([name, test]) => {
        const item = box.querySelector(`[data-rule="${name}"]`);
        if (!item) return;
        const ok = test(input.value);
        item.classList.toggle('is-valid', ok);
        const icon = item.querySelector('i');
        if (icon) icon.className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
      });
      input.addEventListener('input', refresh);
      refresh();
    })();
  </script>
</body>

</html>