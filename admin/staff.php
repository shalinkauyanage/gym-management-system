<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Admin');
$pageTitle = 'Staff';
$action = $_GET['action'] ?? 'list';
$userId = (int) ($_GET['id'] ?? 0);

$hasDisplayName = columnExists($pdo, 'trainers', 'display_name');
$suggestedPassword = generateTemporaryPassword();

// POST handling: only run this block when a form has been submitted.
//  Run this block only when the form is submitted with POST.
if (isPost()) {
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();
  $mode = $_POST['mode'] ?? 'save';
  $userId = (int) ($_POST['user_id'] ?? 0);

  if ($mode === 'remove') {
    if (!$userId) {
      //  Save a one-time message so the next page can tell the user what happened.
      flash('danger', 'Invalid staff account.');
      redirect('admin/staff.php');
    }

    //  group related SQL changes so they either all succeed or all roll back.
    //  Start a database transaction so related changes succeed or fail together.
    $pdo->beginTransaction();
    try {
      $trainerId = (int) scalar($pdo, 'SELECT trainer_id FROM trainers WHERE user_id = ?', [$userId]);
      $adviserId = (int) scalar($pdo, 'SELECT adviser_id FROM nutrition_advisers WHERE user_id = ?', [$userId]);

      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare("UPDATE users SET status = 'Inactive' WHERE user_id = ?")->execute([$userId]);
      $pdo->prepare("UPDATE trainers SET status = 'Inactive' WHERE user_id = ?")->execute([$userId]);
      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare("UPDATE nutrition_advisers SET status = 'Inactive' WHERE user_id = ?")->execute([$userId]);

      if ($trainerId && tableExists($pdo, 'trainer_assignments')) {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare("UPDATE trainer_assignments SET status = 'Inactive' WHERE trainer_id = ? AND status = 'Active'")->execute([$trainerId]);
      }
      if ($adviserId && tableExists($pdo, 'nutrition_assignments')) {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare("UPDATE nutrition_assignments SET status = 'Inactive' WHERE adviser_id = ? AND status = 'Active'")->execute([$adviserId]);
      }

      //  Save all database changes made inside the transaction.
      $pdo->commit();
      flash('success', 'Staff access removed and historical records preserved.');
    } catch (Throwable $error) {
      //  Undo the transaction if an error happens before completion.
      $pdo->rollBack();
      flash('danger', 'The staff account could not be removed.');
    }

    //  Redirect the browser after this action to avoid repeating the same request.
    redirect('admin/staff.php');
  }

  $displayName = trim($_POST['display_name'] ?? '');
  $username = trim($_POST['username'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $contactInput = trim($_POST['contact_number'] ?? '');
  $contactNumber = normalizeSriLankanMobile($contactInput);
  $temporaryPassword = $_POST['temporary_password'] ?? '';
  $trainingSpecialization = trim($_POST['training_specialization'] ?? '');
  $certification = trim($_POST['certification'] ?? '');
  $nutritionSpecialization = trim($_POST['nutrition_specialization'] ?? '');
  $qualification = trim($_POST['qualification'] ?? '');
  $experience = max(0, (int) ($_POST['experience_years'] ?? 0));
  $status = $_POST['status'] ?? 'Active';

  if ($displayName === '' || $username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$contactNumber) {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('danger', 'Enter the required staff details and a valid Sri Lankan mobile number such as 07XXXXXXXX.');
    redirect('admin/staff.php?action=' . ($userId ? 'edit&id=' . $userId : 'new'));
  }

  $coachRole = (int) scalar($pdo, "SELECT role_id FROM roles WHERE role_name = 'Coach' LIMIT 1");
  if (!$coachRole) {
    $coachRole = (int) scalar($pdo, "SELECT role_id FROM roles WHERE role_name = 'Trainer' LIMIT 1");
  }

  //  group related SQL changes so they either all succeed or all roll back.
  //  Start a database transaction so related changes succeed or fail together.
  $pdo->beginTransaction();
  try {
    if ($userId) {
      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare(
        'UPDATE users
                 SET email_verified_at = IF(email = ?, email_verified_at, NULL),
                     username = ?, email = ?, contact_number = ?, status = ?
                 WHERE user_id = ?',
      )->execute([$email, $username, $email, $contactNumber, $status, $userId]);

      if ($hasDisplayName) {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          'UPDATE trainers SET display_name = ?, specialization = ?, certification = ?, experience_years = ?, status = ? WHERE user_id = ?',
        )->execute([$displayName, $trainingSpecialization, $certification, $experience, $status, $userId]);
      } else {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          'UPDATE trainers SET specialization = ?, certification = ?, experience_years = ?, status = ? WHERE user_id = ?',
        )->execute([$trainingSpecialization, $certification, $experience, $status, $userId]);
      }

      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare(
        'UPDATE nutrition_advisers SET specialization = ?, qualification = ?, experience_years = ?, status = ? WHERE user_id = ?',
      )->execute([$nutritionSpecialization, $qualification, $experience, $status, $userId]);

      //  Save a one-time message so the next page can tell the user what happened.
      flash('success', 'Coach profile updated.');
    } else {
      if (!$coachRole) {
        throw new RuntimeException('Coach role is missing.');
      }

      $password = $temporaryPassword !== '' ? $temporaryPassword : generateTemporaryPassword();
      if ($passwordError = strongPasswordError($password, ['username' => $username, 'email' => $email])) {
        throw new RuntimeException('Temporary password: ' . $passwordError);
      }
      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare(
        "INSERT INTO users(role_id, username, email, contact_number, password_hash, email_verified_at, must_change_password, status)
                 VALUES(?, ?, ?, ?, ?, NULL, 1, 'Active')",
      )->execute([$coachRole, $username, $email, $contactNumber, password_hash($password, PASSWORD_DEFAULT)]);
      $userId = (int) $pdo->lastInsertId();

      if ($hasDisplayName) {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          "INSERT INTO trainers(user_id, display_name, specialization, certification, experience_years, status)
                     VALUES(?, ?, ?, ?, ?, 'Active')",
        )->execute([$userId, $displayName, $trainingSpecialization, $certification, $experience]);
      } else {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          "INSERT INTO trainers(user_id, specialization, certification, experience_years, status)
                     VALUES(?, ?, ?, ?, 'Active')",
        )->execute([$userId, $trainingSpecialization, $certification, $experience]);
      }

      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare(
        "INSERT INTO nutrition_advisers(user_id, qualification, specialization, experience_years, status)
                 VALUES(?, ?, ?, ?, 'Active')",
      )->execute([$userId, $qualification, $nutritionSpecialization, $experience]);

      //  Save a one-time message so the next page can tell the user what happened.
      flash('success', "Coach created. Temporary login: {$username} / {$password}");
    }

    //  Save all database changes made inside the transaction.
    $pdo->commit();
  } catch (Throwable $error) {
    //  Undo the transaction if an error happens before completion.
    $pdo->rollBack();
    flash('danger', 'Could not save the coach. Check that the username and email are unique.');
  }

  //  Redirect the browser after this action to avoid repeating the same request.
  redirect('admin/staff.php');
}

$nameExpr = $hasDisplayName ? "COALESCE(NULLIF(t.display_name, ''), u.username)" : 'u.username';
$staff = all(
  $pdo,
  "SELECT u.user_id, u.username, u.email, u.contact_number, u.status AS user_status,
        {$nameExpr} AS display_name,
        t.specialization AS training_specialization, t.certification, t.experience_years,
        t.average_rating, t.status AS trainer_status,
        n.specialization AS nutrition_specialization, n.qualification, n.status AS adviser_status
     FROM users u
     JOIN trainers t ON t.user_id = u.user_id
     JOIN nutrition_advisers n ON n.user_id = u.user_id
     ORDER BY u.status = 'Active' DESC, display_name",
);

$edit = $userId
  ? one(
    $pdo,
    "SELECT u.user_id, u.username, u.email, u.contact_number, u.status,
            {$nameExpr} AS display_name,
            t.specialization AS training_specialization, t.certification, t.experience_years,
            n.specialization AS nutrition_specialization, n.qualification
         FROM users u
         JOIN trainers t ON t.user_id = u.user_id
         JOIN nutrition_advisers n ON n.user_id = u.user_id
         WHERE u.user_id = ?",
    [$userId],
  )
  : null;

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<?php if ($action === 'new' || $action === 'edit'): ?>
  <!-- This div groups related page content using the “panel” layout style. -->
  <div class="panel">
    <!-- This div groups related page content using the “panel-title” layout style. -->
    <div class="panel-title">
      <!-- This div groups related HTML content so the page structure is easier to manage. -->
      <div><small class="text-muted">Combined trainer + nutrition adviser</small>
        <h2><?= $edit ? 'Edit coach' : 'Add coach' ?></h2>
      </div>
      <a href="staff.php" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
    </div>

    <!-- This form collects user input and submits this form using the POST method. -->
    <form method="post" class="row g-3" data-loading-form>
      <?= csrfField() ?>
      <input type="hidden" name="mode" value="save">
      <input type="hidden" name="user_id" value="<?= (int) ($edit['user_id'] ?? 0) ?>">

      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-12">
        <!-- This div shows a feedback message such as success, warning or error. -->
        <div class="alert alert-light border py-2 mb-0 small fw-semibold"><span class="text-danger">*</span> Required information</div>
      </div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label"><?= requiredLabel('Staff name') ?></label><input class="form-control" name="display_name" required value="<?= e($edit['display_name'] ?? '') ?>"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-3"><label class="form-label"><?= requiredLabel('Username') ?></label><input class="form-control" name="username" required value="<?= e($edit['username'] ?? '') ?>" autocomplete="off"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-3"><label class="form-label">Experience years</label><input class="form-control" type="number" min="0" max="60" name="experience_years" value="<?= e((string) ($edit['experience_years'] ?? 0)) ?>"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label"><?= requiredLabel('Email') ?></label><input class="form-control" type="email" name="email" required value="<?= e($edit['email'] ?? '') ?>"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label"><?= requiredLabel('Contact number') ?></label><input class="form-control" type="tel" name="contact_number" required value="<?= e($edit['contact_number'] ?? '') ?>" placeholder="07XXXXXXXX">
        <!-- This div groups related form controls for easier layout and validation. -->
        <div class="form-text"></div>
      </div>
      <?php if (!$edit): ?>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-6">
          <label class="form-label"><?= requiredLabel('Temporary password') ?></label>
          <input class="form-control font-monospace" id="coachTemporaryPassword" name="temporary_password" required readonly value="<?= e($suggestedPassword) ?>" onclick="this.select()">
          <!-- This div groups related form controls for easier layout and validation. -->
          <div class="form-text">Give this password to the coach once. At first sign-in PowerFit requires email verification, then forces a permanent password change.</div>
        </div>
      <?php endif; ?>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label"><?= requiredLabel('Account status') ?></label><select class="form-select" name="status" required><?php foreach (['Active', 'Inactive'] as $status): ?><option <?= ($edit['status'] ?? 'Active') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label">Training specialization</label><input class="form-control" name="training_specialization" value="<?= e($edit['training_specialization'] ?? '') ?>" placeholder="Strength & conditioning"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label">Training certification</label><input class="form-control" name="certification" value="<?= e($edit['certification'] ?? '') ?>"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label">Nutrition specialization</label><input class="form-control" name="nutrition_specialization" value="<?= e($edit['nutrition_specialization'] ?? '') ?>" placeholder="Sports nutrition"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label">Nutrition qualification</label><input class="form-control" name="qualification" value="<?= e($edit['qualification'] ?? '') ?>"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-12"><button class="btn btn-dark rounded-pill px-4">Save coach</button></div>
    </form>
  </div>
<?php else: ?>
  <!-- This div groups related page content using the “panel” layout style. -->
  <div class="panel">
    <!-- This div groups related page content using the “panel-title” layout style. -->
    <div class="panel-title">
      <!-- This div groups related HTML content so the page structure is easier to manage. -->
      <div><small class="text-muted">Unified coaching team</small>
        <h2>Staff management</h2>
      </div>
      <a class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" href="<?= url('admin/staff.php?action=new') ?>">
        <span class="btn-ripple-circle"></span>
        <span class="btn-ripple-label"><i class="bi bi-person-plus me-1"></i>Add coach</span>
      </a>
    </div>

    <!-- This div creates a Bootstrap grid row for responsive columns. -->
    <div class="row g-3">
      <?php foreach ($staff as $person): ?>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-6 col-xl-4">
          <!-- This div shows one main PowerFit benefit in a reusable card layout. -->
          <div class="feature-card staff-admin-card">
            <!-- This div uses flexbox to align the child elements in this area. -->
            <div class="d-flex justify-content-between align-items-start gap-3">
              <!-- This div holds the user or coach profile placeholder/image area. -->
              <div class="avatar-sm"><?= e(strtoupper(substr($person['display_name'], 0, 1))) ?></div>
              <?= statusBadge($person['user_status']) ?>
            </div>
            <h3><?= e($person['display_name']) ?></h3>
            <p class="mb-1"><?= e($person['email']) ?></p>
            <small class="text-muted d-block"><i class="bi bi-phone me-1"></i><?= e($person['contact_number'] ?: 'No mobile number') ?></small>
            <small class="text-muted d-block">Training: <?= e($person['training_specialization'] ?: 'Not set') ?></small>
            <small class="text-muted d-block">Nutrition: <?= e($person['nutrition_specialization'] ?: 'Not set') ?></small>
            <!-- This div uses flexbox to align the child elements in this area. -->
            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
              <span class="rating-stars"><i class="bi bi-star-fill"></i> <?= number_format((float) $person['average_rating'], 1) ?></span>
              <!-- This div uses flexbox to align the child elements in this area. -->
              <div class="d-flex gap-1">
                <a href="?action=edit&id=<?= (int) $person['user_id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill">Edit</a>
                <?php if ($person['user_status'] === 'Active'): ?>
                  <!-- This form collects user input and submits this form using the POST method. -->
                  <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="mode" value="remove">
                    <input type="hidden" name="user_id" value="<?= (int) $person['user_id'] ?>">
                    <button class="btn btn-sm btn-outline-danger rounded-pill" data-confirm="Remove this coach from active staff? Historical plans and ratings will be kept.">Remove</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$staff): ?><!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-12">
          <!-- This div shows one summary value and its label. -->
          <div class="empty-state"><i class="bi bi-person-badge"></i>No coach accounts yet.</div>
        </div><?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>