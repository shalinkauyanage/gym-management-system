<?php

if (file_exists(__DIR__ . '/email_auth.php')) {
  require_once __DIR__ . '/email_auth.php';
} elseif (file_exists(__DIR__ . '/email_aut.php')) {
  require_once __DIR__ . '/email_aut.php';
}

$schemaReady = isset($pdo) && ($pdo instanceof PDO) ? emailAuthSchemaReady($pdo) : false;
$currentUser = currentUser();

// 1. Sign-in state
$loginError = $_SESSION['login_error'] ?? '';
$loginIdentity = $_SESSION['login_identity'] ?? '';
unset($_SESSION['login_error'], $_SESSION['login_identity']);

// 2. Forgot-password state
$forgotError = $_SESSION['forgot_error'] ?? '';
$forgotNotice = $_SESSION['forgot_notice'] ?? '';
unset($_SESSION['forgot_error'], $_SESSION['forgot_notice']);
$pwResetStep = $_SESSION['pw_reset_step'] ?? 'identify';
$pwResetUserId = (int) ($_SESSION['pw_reset_user_id'] ?? 0);
$pwResetUser = ($schemaReady && $pwResetUserId && isset($pdo) && ($pdo instanceof PDO))
  ? one($pdo, 'SELECT u.*, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ?', [$pwResetUserId])
  : null;
$localResetOtp = (defined('MAIL_DRIVER') && MAIL_DRIVER === 'local') ? ($_SESSION['local_email_otp_preview'] ?? null) : null;

// 3. Email-verification state
$verifyError = $_SESSION['verify_error'] ?? '';
$verifyNotice = $_SESSION['verify_notice'] ?? '';
unset($_SESSION['verify_error'], $_SESSION['verify_notice']);
$dbVerifyUser = null;
if ($currentUser && isset($pdo) && ($pdo instanceof PDO)) {
  $dbVerifyUser = one($pdo, 'SELECT user_id, username, email, email_verified_at, must_change_password FROM users WHERE user_id = ?', [(int) $currentUser['id']]);
}
$activeVerifyOtp = ($schemaReady && $dbVerifyUser && isset($pdo) && ($pdo instanceof PDO))
  ? latestEmailOtp($pdo, (int) $dbVerifyUser['user_id'], 'email_verification')
  : null;
$verifyCodeSent = $activeVerifyOtp && strtotime((string) $activeVerifyOtp['expires_at']) >= time();
$localVerifyOtp = (defined('MAIL_DRIVER') && MAIL_DRIVER === 'local') ? ($_SESSION['local_email_otp_preview'] ?? null) : null;

// 4. Change-password state
$changePasswordError = $_SESSION['change_password_error'] ?? '';
unset($_SESSION['change_password_error']);
$firstLogin = $dbVerifyUser ? ((int) ($dbVerifyUser['must_change_password'] ?? 0) === 1) : (!empty($currentUser['must_change_password']));

// Determine initial active pane and auto-open trigger
$activePane = 'signin';
$autoOpen = false;

if (!empty($loginError)) {
  $activePane = 'signin';
  $autoOpen = true;
} elseif (!empty($forgotError) || !empty($forgotNotice) || (isset($_SESSION['pw_reset_step']) && $_SESSION['pw_reset_step'] !== 'identify')) {
  $activePane = 'forgot';
  $autoOpen = true;
} elseif (!empty($verifyError) || !empty($verifyNotice) || ($currentUser && ($currentUser['role'] ?? '') !== 'Admin' && empty($currentUser['email_verified_at']))) {
  $activePane = 'verify_email';
  $autoOpen = true;
} elseif (!empty($changePasswordError) || ($currentUser && !empty($currentUser['must_change_password']))) {
  $activePane = 'change_password';
  $autoOpen = true;
} elseif (isset($_GET['auth'])) {
  $req = (string) $_GET['auth'];
  if (in_array($req, ['signin', 'forgot', 'verify_email', 'change_password'], true)) {
    $activePane = $req;
    $autoOpen = true;
  }
} elseif (isset($_GET['login'])) {
  $activePane = 'signin';
  $autoOpen = true;
}
?>

<!-- Universal Authentication & Account Security Modal -->
<div class="modal fade auth-modal" id="authModal" tabindex="-1" aria-labelledby="authModalLabel" aria-hidden="true" data-initial-pane="<?= e($activePane) ?>" data-auto-open="<?= $autoOpen ? '1' : '0' ?>">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content auth-modal-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="brand-lockup text-decoration-none text-dark" href="<?= url('index.php') ?>">
          <span>POWERFIT</span>
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Top Tab Switcher (Visible in guest / sign-in / forgot modes) -->
      <div class="auth-modal-tabs mb-4" id="authModalTabs" style="<?= in_array($activePane, ['verify_email', 'change_password'], true) ? 'display: none;' : '' ?>">
        <button type="button" class="auth-modal-tab <?= $activePane === 'signin' ? 'active' : '' ?>" data-auth-tab="signin">Sign in</button>
        <button type="button" class="auth-modal-tab <?= $activePane === 'forgot' ? 'active' : '' ?>" data-auth-tab="forgot">Reset password</button>
      </div>

      <!-- ================================================================= -->
      <!-- PANE 1: Sign in -->
      <!-- ================================================================= -->
      <div class="auth-modal-pane" id="authPaneSignin" style="<?= $activePane === 'signin' ? '' : 'display: none;' ?>">
        <small class="text-uppercase fw-bold text-muted">Welcome back</small>
        <h2 class="auth-modal-title mt-1">Sign in to PowerFit</h2>
        <p class="text-muted mb-4">Welcome back. Sign in to continue your PowerFit experience.</p>

        <?php if ($loginError): ?>
          <div class="alert alert-danger py-2 px-3 mb-3 d-flex align-items-center">
            <i class="bi bi-exclamation-circle me-2 flex-shrink-0"></i>
            <span><?= e($loginError) ?></span>
          </div>
        <?php endif; ?>

        <form action="<?= url('login.php') ?>" method="post" data-loading-form>
          <?= csrfField() ?>
          <div class="mb-3">
            <label class="form-label"><?= requiredLabel('Username or email') ?></label>
            <input class="form-control" name="identity" value="<?= e($loginIdentity) ?>" required autocomplete="username" placeholder="Enter username or email">
          </div>
          <div class="mb-3">
            <label class="form-label"><?= requiredLabel('Password') ?></label>
            <div class="password-input-wrap">
              <input class="form-control" type="password" name="password" required autocomplete="current-password" placeholder="Enter password">
              <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>
          <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" type="submit">Sign in <i class="bi bi-arrow-right ms-2"></i></button>
        </form>

        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mt-4 pt-3 border-top">
          <button type="button" class="btn btn-link p-0 text-decoration-none fw-semibold text-dark" data-auth-switch="forgot">
            <i class="bi bi-shield-lock me-1"></i>Forgot password?
          </button>
          <button type="button" class="btn btn-link p-0 text-muted text-decoration-none" data-bs-dismiss="modal">
            Close
          </button>
        </div>
      </div>

      <!-- ================================================================= -->
      <!-- PANE 2: Forgot Password -->
      <!-- ================================================================= -->
      <div class="auth-modal-pane" id="authPaneForgot" style="<?= $activePane === 'forgot' ? '' : 'display: none;' ?>">
        <small class="text-uppercase fw-bold text-muted">Account recovery</small>
        <h2 class="auth-modal-title mt-1">Reset your password</h2>

        <?php if ($forgotError): ?>
          <div class="alert alert-danger py-2 px-3 mb-3 d-flex align-items-center">
            <i class="bi bi-exclamation-circle me-2 flex-shrink-0"></i>
            <span><?= e($forgotError) ?></span>
          </div>
        <?php endif; ?>
        <?php if ($forgotNotice): ?>
          <div class="alert alert-info py-2 px-3 mb-3 d-flex align-items-center">
            <i class="bi bi-info-circle me-2 flex-shrink-0"></i>
            <span><?= e($forgotNotice) ?></span>
          </div>
        <?php endif; ?>

        <?php if (!$schemaReady): ?>
          <div class="alert alert-danger">
            Email password recovery is not installed in the database. Please run the email database patch.
          </div>
        <?php elseif ($pwResetStep === 'identify'): ?>
          <p class="text-muted mb-4">Enter the username or email connected to your Member or Coach account.</p>
          <form action="<?= url('forgot-password.php') ?>" method="post" data-loading-form>
            <?= csrfField() ?>
            <input type="hidden" name="mode" value="identify">
            <div class="mb-3">
              <label class="form-label"><?= requiredLabel('Username or email') ?></label>
              <input class="form-control" name="identity" required autocomplete="username" placeholder="Enter username or email">
            </div>
            <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" type="submit">Continue <i class="bi bi-arrow-right ms-2"></i></button>
          </form>
          <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mt-4 pt-3 border-top">
            <button type="button" class="btn btn-link p-0 text-decoration-none fw-semibold text-dark" data-auth-switch="signin">
              <i class="bi bi-arrow-left me-1"></i>Back to sign in
            </button>
            <button type="button" class="btn btn-link p-0 text-muted text-decoration-none" data-bs-dismiss="modal">Close</button>
          </div>
        <?php elseif ($pwResetStep === 'confirm' && $pwResetUser): ?>
          <p class="text-muted">PowerFit found the registered email address:</p>
          <div class="masked-email-card mb-4">
            <i class="bi bi-envelope-fill"></i>
            <div><small>Send code to</small><strong><?= e(maskEmailAddress($pwResetUser['email'])) ?></strong></div>
          </div>
          <p class="mb-4">Send a 6-digit verification code to this email?</p>
          <form action="<?= url('forgot-password.php') ?>" method="post" data-loading-form>
            <?= csrfField() ?>
            <input type="hidden" name="mode" value="send_otp">
            <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" type="submit">Send verification code <i class="bi bi-send ms-2"></i></button>
          </form>
          <form action="<?= url('forgot-password.php') ?>" method="post" class="mt-3 text-center">
            <?= csrfField() ?>
            <input type="hidden" name="mode" value="restart">
            <button class="btn btn-link p-0 text-muted text-decoration-none" type="submit"><i class="bi bi-arrow-left me-1"></i>Start again</button>
          </form>
        <?php elseif ($pwResetStep === 'verify' && $pwResetUser): ?>
          <p class="text-muted">Enter the 6-digit code sent to <?= e(maskEmailAddress($pwResetUser['email'])) ?>.</p>
          <?php if ($localResetOtp): ?>
            <div class="local-otp-card mb-4">
              <small>LOCAL EMAIL TEST CODE</small>
              <strong><?= e($localResetOtp) ?></strong>
              <span>Visible only when MAIL_DRIVER is local.</span>
            </div>
          <?php endif; ?>
          <form action="<?= url('forgot-password.php') ?>" method="post" data-loading-form>
            <?= csrfField() ?>
            <input type="hidden" name="mode" value="verify_otp">
            <div class="mb-3">
              <label class="form-label"><?= requiredLabel('Verification code') ?></label>
              <input class="form-control otp-input text-center fw-bold fs-4 tracking-wider" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" placeholder="000000">
            </div>
            <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" type="submit">Verify code <i class="bi bi-shield-check ms-2"></i></button>
          </form>
          <form action="<?= url('forgot-password.php') ?>" method="post" class="mt-3 text-center">
            <?= csrfField() ?>
            <input type="hidden" name="mode" value="send_otp">
            <button class="btn btn-link text-decoration-none" type="submit">Send another code</button>
          </form>
          <form action="<?= url('forgot-password.php') ?>" method="post" class="mt-2 text-center">
            <?= csrfField() ?>
            <input type="hidden" name="mode" value="restart">
            <button class="btn btn-link p-0 text-muted text-decoration-none" type="submit"><i class="bi bi-arrow-left me-1"></i>Start again</button>
          </form>
        <?php elseif ($pwResetStep === 'reset'): ?>
          <p class="text-muted mb-4">Email verified. Create your new strong password.</p>
          <form action="<?= url('forgot-password.php') ?>" method="post" data-loading-form>
            <?= csrfField() ?>
            <input type="hidden" name="mode" value="reset_password">
            <div class="mb-3">
              <label class="form-label"><?= requiredLabel('New password') ?></label>
              <div class="password-input-wrap">
                <input class="form-control" id="resetModalNewPassword" type="password" name="new_password" required autocomplete="new-password" placeholder="Enter new strong password">
                <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= requiredLabel('Confirm new password') ?></label>
              <div class="password-input-wrap">
                <input class="form-control" type="password" name="confirm_password" required autocomplete="new-password" placeholder="Confirm new password">
                <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <div class="password-rules mb-4" id="resetPasswordRules">
              <span data-rule="length"><i class="bi bi-circle"></i> 10+ characters</span>
              <span data-rule="upper"><i class="bi bi-circle"></i> Uppercase letter</span>
              <span data-rule="lower"><i class="bi bi-circle"></i> Lowercase letter</span>
              <span data-rule="number"><i class="bi bi-circle"></i> Number</span>
              <span data-rule="special"><i class="bi bi-circle"></i> Special character</span>
            </div>
            <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" type="submit">Create new password <i class="bi bi-check2-circle ms-2"></i></button>
          </form>
          <form action="<?= url('forgot-password.php') ?>" method="post" class="mt-3 text-center">
            <?= csrfField() ?>
            <input type="hidden" name="mode" value="restart">
            <button class="btn btn-link p-0 text-muted text-decoration-none" type="submit"><i class="bi bi-arrow-left me-1"></i>Start again</button>
          </form>
        <?php endif; ?>
      </div>

      <!-- ================================================================= -->
      <!-- PANE 3: Verify Email -->
      <!-- ================================================================= -->
      <div class="auth-modal-pane" id="authPaneVerifyEmail" style="<?= $activePane === 'verify_email' ? '' : 'display: none;' ?>">
        <small class="text-uppercase fw-bold text-muted">Account verification</small>
        <h2 class="auth-modal-title mt-1">Verify your email</h2>

        <?php if ($verifyError): ?>
          <div class="alert alert-danger py-2 px-3 mb-3 d-flex align-items-center">
            <i class="bi bi-exclamation-circle me-2 flex-shrink-0"></i>
            <span><?= e($verifyError) ?></span>
          </div>
        <?php endif; ?>
        <?php if ($verifyNotice): ?>
          <div class="alert alert-info py-2 px-3 mb-3 d-flex align-items-center">
            <i class="bi bi-info-circle me-2 flex-shrink-0"></i>
            <span><?= e($verifyNotice) ?></span>
          </div>
        <?php endif; ?>

        <?php if (!$currentUser || !$dbVerifyUser): ?>
          <p class="text-muted mb-4">Please sign in to verify your account email address.</p>
          <button type="button" class="btn btn-dark w-100 rounded-pill py-3 fw-bold" data-auth-switch="signin">
            Sign in to account <i class="bi bi-arrow-right ms-2"></i>
          </button>
        <?php elseif (!empty($dbVerifyUser['email_verified_at'])): ?>
          <div class="alert alert-success d-flex align-items-center mb-4">
            <i class="bi bi-check-circle-fill fs-4 me-2 text-success"></i>
            <div>Your email address <strong><?= e($dbVerifyUser['email']) ?></strong> is already verified!</div>
          </div>
          <?php if ((int) ($dbVerifyUser['must_change_password'] ?? 0) === 1): ?>
            <button type="button" class="btn btn-dark w-100 rounded-pill py-3 fw-bold" data-auth-switch="change_password">
              Continue to set password <i class="bi bi-arrow-right ms-2"></i>
            </button>
          <?php else: ?>
            <a href="<?= url(roleDashboard($currentUser['role'])) ?>" class="btn btn-dark w-100 rounded-pill py-3 fw-bold">
              Go to dashboard <i class="bi bi-speedometer2 ms-2"></i>
            </a>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-muted mb-3">Confirm the email registered with PowerFit before opening your dashboard.</p>
          <div class="masked-email-card mb-4">
            <i class="bi bi-envelope-fill"></i>
            <div><small>Verification email</small><strong><?= e(maskEmailAddress($dbVerifyUser['email'])) ?></strong></div>
          </div>

          <?php if ($localVerifyOtp): ?>
            <div class="local-otp-card mb-4">
              <small>LOCAL EMAIL TEST CODE</small>
              <strong><?= e($localVerifyOtp) ?></strong>
              <span>Visible only when MAIL_DRIVER is local.</span>
            </div>
          <?php endif; ?>

          <?php if (!$verifyCodeSent): ?>
            <form action="<?= url('verify-email.php') ?>" method="post" data-loading-form>
              <?= csrfField() ?>
              <input type="hidden" name="mode" value="send_code">
              <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" type="submit">Send verification code <i class="bi bi-send ms-2"></i></button>
            </form>
          <?php else: ?>
            <form action="<?= url('verify-email.php') ?>" method="post" data-loading-form>
              <?= csrfField() ?>
              <input type="hidden" name="mode" value="verify_code">
              <div class="mb-3">
                <label class="form-label"><?= requiredLabel('6-digit code') ?></label>
                <input class="form-control otp-input text-center fw-bold fs-4 tracking-wider" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" placeholder="000000">
              </div>
              <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" type="submit">Verify email <i class="bi bi-shield-check ms-2"></i></button>
            </form>
            <form action="<?= url('verify-email.php') ?>" method="post" class="mt-3 text-center">
              <?= csrfField() ?>
              <input type="hidden" name="mode" value="send_code">
              <button class="btn btn-link text-decoration-none" type="submit">Send another code</button>
            </form>
          <?php endif; ?>

          <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mt-4 pt-3 border-top">
            <a href="<?= url('logout.php') ?>" class="btn btn-link p-0 text-muted text-decoration-none">
              <i class="bi bi-box-arrow-left me-1"></i>Sign out
            </a>
            <button type="button" class="btn btn-link p-0 text-muted text-decoration-none" data-bs-dismiss="modal">Close</button>
          </div>
        <?php endif; ?>
      </div>

      <!-- ================================================================= -->
      <!-- PANE 4: Change Password -->
      <!-- ================================================================= -->
      <div class="auth-modal-pane" id="authPaneChangePassword" style="<?= $activePane === 'change_password' ? '' : 'display: none;' ?>">
        <small class="text-uppercase fw-bold text-muted"><?= $firstLogin ? 'First sign-in setup' : 'Account security' ?></small>
        <h2 class="auth-modal-title mt-1"><?= $firstLogin ? 'Create your permanent password' : 'Change your password' ?></h2>
        <p class="text-muted mb-4"><?= $firstLogin ? 'Create a strong permanent password that only you know to secure your PowerFit account.' : 'Confirm your current password, then create a new strong password.' ?></p>

        <?php if ($changePasswordError): ?>
          <div class="alert alert-danger py-2 px-3 mb-3 d-flex align-items-center">
            <i class="bi bi-exclamation-circle me-2 flex-shrink-0"></i>
            <span><?= e($changePasswordError) ?></span>
          </div>
        <?php endif; ?>

        <?php if (!$currentUser): ?>
          <p class="text-muted mb-4">Please sign in to change your password.</p>
          <button type="button" class="btn btn-dark w-100 rounded-pill py-3 fw-bold" data-auth-switch="signin">
            Sign in <i class="bi bi-arrow-right ms-2"></i>
          </button>
        <?php else: ?>
          <form action="<?= url('change-password.php') ?>" method="post" data-loading-form>
            <?= csrfField() ?>
            <?php if (!$firstLogin): ?>
              <div class="mb-3">
                <label class="form-label"><?= requiredLabel('Current password') ?></label>
                <div class="password-input-wrap">
                  <input class="form-control" type="password" name="current_password" required autocomplete="current-password" placeholder="Enter current password">
                  <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </div>
            <?php endif; ?>
            <div class="mb-3">
              <label class="form-label"><?= requiredLabel('New password') ?></label>
              <div class="password-input-wrap">
                <input class="form-control" id="changeModalNewPassword" type="password" name="new_password" required autocomplete="new-password" placeholder="Enter new strong password">
                <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= requiredLabel('Confirm new password') ?></label>
              <div class="password-input-wrap">
                <input class="form-control" type="password" name="confirm_password" required autocomplete="new-password" placeholder="Confirm new password">
                <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <div class="password-rules mb-4" id="changePasswordRules">
              <span data-rule="length"><i class="bi bi-circle"></i> 10+ characters</span>
              <span data-rule="upper"><i class="bi bi-circle"></i> Uppercase letter</span>
              <span data-rule="lower"><i class="bi bi-circle"></i> Lowercase letter</span>
              <span data-rule="number"><i class="bi bi-circle"></i> Number</span>
              <span data-rule="special"><i class="bi bi-circle"></i> Special character</span>
            </div>
            <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold" type="submit">Save new password <i class="bi bi-arrow-right ms-2"></i></button>
          </form>

          <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mt-4 pt-3 border-top">
            <?php if ($firstLogin): ?>
              <a href="<?= url('logout.php') ?>" class="btn btn-link p-0 text-muted text-decoration-none">
                <i class="bi bi-box-arrow-left me-1"></i>Sign out
              </a>
            <?php else: ?>
              <button type="button" class="btn btn-link p-0 text-muted text-decoration-none" data-bs-dismiss="modal">Cancel</button>
            <?php endif; ?>
            <button type="button" class="btn btn-link p-0 text-muted text-decoration-none" data-bs-dismiss="modal">Close</button>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>