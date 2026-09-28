<?php

//  Load the shared file needed before this page continues.
if (file_exists(__DIR__ . '/includes/email_auth.php')) {
  require_once __DIR__ . '/includes/email_auth.php';
} else {
  require_once __DIR__ . '/includes/email_aut.php';
}

if (isLoggedIn()) {
  //  Redirect the browser after this action to avoid repeating the same request.
  redirect(roleDashboard(currentUser()['role']));
}

$schemaReady = emailAuthSchemaReady($pdo);
$error = $schemaReady ? '' : 'Email password recovery is not installed in the database. Import database/patch_email_verification.sql once.';
$notice = '';
$step = $_SESSION['pw_reset_step'] ?? 'identify';
$resetUserId = (int) ($_SESSION['pw_reset_user_id'] ?? 0);

if ($schemaReady && isPost()) {
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();
  $mode = $_POST['mode'] ?? '';

  if ($mode === 'restart') {
    unset(
      $_SESSION['pw_reset_step'],
      $_SESSION['pw_reset_user_id'],
      $_SESSION['pw_reset_verified_user_id'],
      $_SESSION['local_email_otp_preview'],
    );
    //  Redirect back to the modal on the website.
    redirect('index.php?auth=forgot');
  }

  if ($mode === 'identify') {
    $identity = trim($_POST['identity'] ?? '');
    $found = one(
      $pdo,
      "SELECT u.*, r.role_name
             FROM users u
             JOIN roles r ON r.role_id = u.role_id
             WHERE (u.username = ? OR u.email = ?) AND u.status = 'Active'
             LIMIT 1",
      [$identity, $identity],
    );

    if (!$found || !in_array($found['role_name'], ['Member', 'Coach', 'Trainer', 'Adviser'], true)) {
      $_SESSION['forgot_error'] = 'We could not find an active Member or Coach account with those details.';
      redirect('index.php?auth=forgot');
    } elseif (!filter_var($found['email'], FILTER_VALIDATE_EMAIL)) {
      $_SESSION['forgot_error'] = 'This account does not have a valid registered email. Please contact the PowerFit administrator.';
      redirect('index.php?auth=forgot');
    } else {
      $_SESSION['pw_reset_user_id'] = (int) $found['user_id'];
      $_SESSION['pw_reset_step'] = 'confirm';
      unset($_SESSION['local_email_otp_preview']);
      //  Redirect back to the modal on the website.
      redirect('index.php?auth=forgot');
    }
  }

  $resetUserId = (int) ($_SESSION['pw_reset_user_id'] ?? 0);
  $resetUser = $resetUserId
    ? one($pdo, 'SELECT u.*, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ?', [$resetUserId])
    : null;

  if ($mode === 'send_otp' && $resetUser) {
    $result = createAndSendEmailOtp($pdo, $resetUser, 'password_reset');
    if ($result['success']) {
      $_SESSION['pw_reset_step'] = 'verify';
      $_SESSION['forgot_notice'] = $result['message'];
    } else {
      $_SESSION['forgot_error'] = $result['message'];
    }
    redirect('index.php?auth=forgot');
  }

  if ($mode === 'verify_otp' && $resetUser) {
    $result = verifyEmailOtp($pdo, $resetUserId, 'password_reset', trim($_POST['otp'] ?? ''));
    if (!$result['success']) {
      $_SESSION['forgot_error'] = $result['message'];
      redirect('index.php?auth=forgot');
    } else {
      $_SESSION['pw_reset_verified_user_id'] = $resetUserId;
      $_SESSION['pw_reset_step'] = 'reset';
      unset($_SESSION['local_email_otp_preview']);
      //  Redirect back to the modal on the website.
      redirect('index.php?auth=forgot');
    }
  }

  if ($mode === 'reset_password') {
    $verifiedUserId = (int) ($_SESSION['pw_reset_verified_user_id'] ?? 0);
    $verifiedUser = $verifiedUserId
      ? one($pdo, 'SELECT user_id, username, email, password_hash FROM users WHERE user_id = ?', [$verifiedUserId])
      : null;

    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$verifiedUser) {
      $_SESSION['forgot_error'] = 'Your password-reset session has expired. Start again.';
      redirect('index.php?auth=forgot');
    } elseif ($newPassword !== $confirmPassword) {
      $_SESSION['forgot_error'] = 'The new passwords do not match.';
      redirect('index.php?auth=forgot');
    } elseif (password_verify($newPassword, (string) $verifiedUser['password_hash'])) {
      $_SESSION['forgot_error'] = 'Your new password must be different from your previous password.';
      redirect('index.php?auth=forgot');
    } elseif ($passwordError = strongPasswordError($newPassword, $verifiedUser)) {
      $_SESSION['forgot_error'] = $passwordError;
      redirect('index.php?auth=forgot');
    } else {
      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare(
        'UPDATE users
                 SET password_hash = ?, must_change_password = 0, password_changed_at = NOW(),
                     email_verified_at = COALESCE(email_verified_at, NOW())
                 WHERE user_id = ?',
      )->execute([password_hash($newPassword, PASSWORD_DEFAULT), $verifiedUserId]);

      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare('UPDATE email_otps SET used_at = COALESCE(used_at, NOW()) WHERE user_id = ?')
        ->execute([$verifiedUserId]);

      unset(
        $_SESSION['pw_reset_step'],
        $_SESSION['pw_reset_user_id'],
        $_SESSION['pw_reset_verified_user_id'],
        $_SESSION['local_email_otp_preview'],
      );

      //  Save a one-time message and open the sign in modal on the website.
      flash('success', 'Password reset successfully. Sign in with your new password.');
      redirect('index.php?auth=signin');
    }
  }
}

// Redirect GET requests so the reset form always opens inside the website modal.
redirect('index.php?auth=forgot');
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Forgot password | PowerFit</title>
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
      <h1 class="mt-2">Reset your password</h1>

      <?php if ($error): ?><!-- This div shows a feedback message such as success, warning or error. -->
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div><?php endif; ?>
      <?php if ($notice): ?><!-- This div shows a feedback message such as success, warning or error. -->
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i><?= e($notice) ?></div><?php endif; ?>

      <?php if ($schemaReady && $step === 'identify'): ?>
        <p class="text-muted mb-4">Enter the username or email connected to your Member or Coach account.</p>
        <!-- This form collects user input and submits this form using the POST method. -->
        <form method="post" data-loading-form>
          <?= csrfField() ?>
          <input type="hidden" name="mode" value="identify">
          <!-- This div groups related page content using the “mb-3” layout style. -->
          <div class="mb-3">
            <label class="form-label"><?= requiredLabel('Username or email') ?></label>
            <input class="form-control" name="identity" required autocomplete="username" placeholder="Enter username or email">
          </div>
          <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" type="submit">Continue <i class="bi bi-arrow-right ms-2"></i></button>
        </form>
      <?php elseif ($schemaReady && $step === 'confirm' && $resetUser): ?>
        <p class="text-muted">PowerFit found the registered email address:</p>
        <!-- This div creates a bordered content card for related information. -->
        <div class="masked-email-card mb-4"><i class="bi bi-envelope-fill"></i>
          <!-- This div groups related HTML content so the page structure is easier to manage. -->
          <div><small>Send code to</small><strong><?= e(maskEmailAddress($resetUser['email'])) ?></strong></div>
        </div>
        <p class="mb-4">Send a 6-digit verification code to this email?</p>
        <!-- This form collects user input and submits this form using the POST method. -->
        <form method="post" data-loading-form>
          <?= csrfField() ?>
          <input type="hidden" name="mode" value="send_otp">
          <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" type="submit">Send verification code <i class="bi bi-send ms-2"></i></button>
        </form>
      <?php elseif ($schemaReady && $step === 'verify' && $resetUser): ?>
        <p class="text-muted">Enter the 6-digit code sent to <?= e(maskEmailAddress($resetUser['email'])) ?>.</p>
        <?php if ($localOtp): ?>
          <!-- This div creates a bordered content card for related information. -->
          <div class="local-otp-card mb-4"><small>LOCAL EMAIL TEST CODE</small><strong><?= e($localOtp) ?></strong><span>Visible only when MAIL_DRIVER is local.</span></div>
        <?php endif; ?>
        <!-- This form collects user input and submits this form using the POST method. -->
        <form method="post" data-loading-form>
          <?= csrfField() ?>
          <input type="hidden" name="mode" value="verify_otp">
          <!-- This div groups related page content using the “mb-3” layout style. -->
          <div class="mb-3">
            <label class="form-label"><?= requiredLabel('Verification code') ?></label>
            <input class="form-control otp-input" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" placeholder="000000">
          </div>
          <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" type="submit">Verify code <i class="bi bi-shield-check ms-2"></i></button>
        </form>
        <!-- This form collects user input and submits this form using the POST method. -->
        <form method="post" class="mt-3 text-center">
          <?= csrfField() ?>
          <input type="hidden" name="mode" value="send_otp">
          <button class="btn btn-link text-decoration-none" type="submit">Send another code</button>
        </form>
      <?php elseif ($schemaReady && $step === 'reset'): ?>
        <p class="text-muted mb-4">Email verified. Create your new strong password.</p>
        <!-- This form collects user input and submits this form using the POST method. -->
        <form method="post" data-loading-form>
          <?= csrfField() ?>
          <input type="hidden" name="mode" value="reset_password">
          <!-- This div groups related page content using the “mb-3” layout style. -->
          <div class="mb-3">
            <label class="form-label"><?= requiredLabel('New password') ?></label>
            <!-- This div groups password input controls and related actions. -->
            <div class="password-input-wrap">
              <input class="form-control" id="resetNewPassword" type="password" name="new_password" required autocomplete="new-password" placeholder="Enter new strong password">
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
          <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" type="submit">Create new password <i class="bi bi-check2-circle ms-2"></i></button>
        </form>
      <?php endif; ?>

      <?php if ($schemaReady && $step !== 'identify'): ?>
        <!-- This form collects user input and submits this form using the POST method. -->
        <form method="post" class="mt-4">
          <?= csrfField() ?>
          <input type="hidden" name="mode" value="restart">
          <button class="btn btn-link p-0 text-muted text-decoration-none" type="submit"><i class="bi bi-arrow-left me-2"></i>Start again</button>
        </form>
      <?php else: ?>
        <a href="<?= url('login.php') ?>" class="d-inline-flex mt-4 text-muted text-decoration-none"><i class="bi bi-arrow-left me-2"></i>Back to sign in</a>
      <?php endif; ?>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= url('assets/js/app.js') ?>"></script>
  <script>
    (() => {
      const input = document.getElementById('resetNewPassword');
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