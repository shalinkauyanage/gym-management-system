<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Admin');

$pageTitle = 'Members';
$action = $_GET['action'] ?? 'list';
$editId = (int) ($_GET['id'] ?? 0);
$hasIdNumber = columnExists($pdo, 'members', 'id_number');
$suggestedPassword = generateTemporaryPassword();

// POST handling: only run this block when a form has been submitted.
//  Run this block only when the form is submitted with POST.
if (isPost()) {
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();
  $mode = $_POST['mode'] ?? '';

  if ($mode === 'remove' || $mode === 'delete') {
    $memberId = (int) ($_POST['member_id'] ?? 0);
    if (!$memberId) {
      flash('danger', 'Invalid member record.');
      redirect('admin/members.php');
    }

    $targetMember = one($pdo, 'SELECT * FROM members WHERE member_id = ?', [$memberId]);
    if (!$targetMember) {
      flash('danger', 'Member record was not found.');
      redirect('admin/members.php');
    }

    $userId = (int) ($targetMember['user_id'] ?? 0);

    // Viva note: Protect administrative accounts from accidental removal.
    if ($userId) {
      $targetRole = scalar($pdo, 'SELECT r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ?', [$userId]);
      if ($targetRole === 'Admin') {
        flash('danger', 'Administrator accounts cannot be removed from the members list.');
        redirect('admin/members.php');
      }
    }

    // Viva note: Collect uploaded progress photos so physical files on disk are cleaned up too.
    $photos = all(
      $pdo,
      'SELECT ph.photo_path 
       FROM progress_photos ph 
       JOIN progress_records pr ON pr.progress_id = ph.progress_id 
       WHERE pr.member_id = ?',
      [$memberId]
    );

    // group related SQL changes so they either all succeed or all roll back.
    // Start a database transaction so related changes succeed or fail together.
    $pdo->beginTransaction();
    try {
      // Deleting the user cascades and deletes the member profile and related records
      if ($userId) {
        $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$userId]);
      }
      $pdo->prepare('DELETE FROM members WHERE member_id = ?')->execute([$memberId]);

      // Save all database changes made inside the transaction.
      $pdo->commit();

      // Clean up physical photo files from disk after transaction succeeds
      foreach ($photos as $photo) {
        if (!empty($photo['photo_path'])) {
          removeUploadFile(UPLOAD_DIR, $photo['photo_path']);
        }
      }

      flash('success', 'Member "' . ($targetMember['full_name'] ?? 'Record') . '" was removed.');
    } catch (Throwable $error) {
      // Undo the transaction if an error happens before completion.
      $pdo->rollBack();
      flash('danger', 'Could not remove member. Please try again.');
    }

    // Redirect the browser after this action to avoid repeating the same request.
    redirect('admin/members.php');
  }

  if ($mode === 'save') {
    $id = (int) ($_POST['member_id'] ?? 0);
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phoneInput = trim($_POST['contact_number'] ?? '');
    $phone = normalizeSriLankanMobile($phoneInput);
    $temporaryPassword = $_POST['temporary_password'] ?? '';
    $goal = trim($_POST['fitness_goal'] ?? '');
    $status = $_POST['status'] ?? 'Active';
    $idNumber = trim($_POST['id_number'] ?? '');
    $dateOfBirth = $_POST['date_of_birth'] ?: null;
    $address = trim($_POST['address'] ?? '');

    // Viva note: Validate all required member fields before any database write is attempted.
    // Usernames are intentionally entered by the Admin so the login name can be agreed with the member.
    $validUsername = preg_match('/^[A-Za-z0-9._-]{4,50}$/', $username) === 1;
    if ($fullName === '' || !$validUsername || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$phone || !$dateOfBirth || $address === '' || ($hasIdNumber && $idNumber === '')) {
      //  Save a one-time message so the next page can tell the user what happened.
      flash('danger', 'Complete all required member details. Username must be 4-50 characters using letters, numbers, dot, underscore or hyphen, and the contact number must be a valid Sri Lankan mobile number such as 07XXXXXXXX.');
      redirect('admin/members.php?action=' . ($id ? 'edit&id=' . $id : 'new'));
    }

    if ($id) {
      $member = one($pdo, 'SELECT * FROM members WHERE member_id = ?', [$id]);
      if (!$member) {
        //  Save a one-time message so the next page can tell the user what happened.
        flash('danger', 'Member record was not found.');
        redirect('admin/members.php');
      }

      // Viva note: Check the unique username before updating so the Admin gets a clear validation message.
      $usernameOwner = one($pdo, 'SELECT user_id FROM users WHERE username = ? AND user_id <> ?', [$username, $member['user_id']]);
      if ($usernameOwner) {
        //  Save a one-time message so the next page can tell the user what happened.
        flash('danger', 'That username is already in use. Choose a different username.');
        redirect('admin/members.php?action=edit&id=' . $id);
      }

      //  group related SQL changes so they either all succeed or all roll back.
      //  Start a database transaction so related changes succeed or fail together.
      $pdo->beginTransaction();
      try {
        // Viva note: Keep the login account and member profile synchronized in one transaction.
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          'UPDATE users
                     SET email_verified_at = IF(email = ?, email_verified_at, NULL),
                         username = ?, email = ?, contact_number = ?
                     WHERE user_id = ?',
        )->execute([$email, $username, $email, $phone, $member['user_id']]);

        if ($hasIdNumber) {
          //  Prepare and run this SQL statement using PDO.
          $pdo->prepare(
            'UPDATE members SET full_name = ?, id_number = ?, date_of_birth = ?, address = ?, contact_number = ?, fitness_goal = ?, status = ? WHERE member_id = ?',
          )->execute([$fullName, $idNumber ?: null, $dateOfBirth, $address ?: null, $phone ?: null, $goal ?: null, $status, $id]);
        } else {
          //  Prepare and run this SQL statement using PDO.
          $pdo->prepare(
            'UPDATE members SET full_name = ?, date_of_birth = ?, address = ?, contact_number = ?, fitness_goal = ?, status = ? WHERE member_id = ?',
          )->execute([$fullName, $dateOfBirth, $address ?: null, $phone ?: null, $goal ?: null, $status, $id]);
        }

        //  Save all database changes made inside the transaction.
        $pdo->commit();
        flash('success', 'Member updated.');
      } catch (Throwable $error) {
        //  Undo the transaction if an error happens before completion.
        $pdo->rollBack();
        flash('danger', 'Could not update member. Check the email and ID number for duplicates.');
      }
      //  Redirect the browser after this action to avoid repeating the same request.
      redirect('admin/members.php');
    }

    // Viva note: Prevent duplicate login names before creating the linked user account.
    if (one($pdo, 'SELECT user_id FROM users WHERE username = ?', [$username])) {
      //  Save a one-time message so the next page can tell the user what happened.
      flash('danger', 'That username is already in use. Choose a different username.');
      redirect('admin/members.php?action=new');
    }

    // Viva note: The Admin chooses the username; the system only generates the temporary password.
    $password = $temporaryPassword !== '' ? $temporaryPassword : generateTemporaryPassword();
    if ($passwordError = strongPasswordError($password, ['username' => $username, 'email' => $email])) {
      //  Save a one-time message so the next page can tell the user what happened.
      flash('danger', 'Temporary password error: ' . $passwordError);
      redirect('admin/members.php?action=new');
    }
    $roleId = (int) scalar($pdo, "SELECT role_id FROM roles WHERE role_name = 'Member'");
    $next = (int) scalar($pdo, 'SELECT COALESCE(MAX(member_id), 0) + 1 FROM members');
    $memberNumber = 'MEM-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);

    //  group related SQL changes so they either all succeed or all roll back.
    //  Start a database transaction so related changes succeed or fail together.
    $pdo->beginTransaction();
    try {
      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare(
        "INSERT INTO users(role_id, username, email, contact_number, password_hash, email_verified_at, must_change_password, status)
                 VALUES(?, ?, ?, ?, ?, NULL, 1, 'Active')",
      )->execute([$roleId, $username, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
      $userId = (int) $pdo->lastInsertId();

      if ($hasIdNumber) {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          'INSERT INTO members(user_id, member_number, full_name, id_number, date_of_birth, address, contact_number, registration_date, fitness_goal, status) VALUES(?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)',
        )->execute([$userId, $memberNumber, $fullName, $idNumber ?: null, $dateOfBirth, $address ?: null, $phone ?: null, $goal ?: null, $status]);
      } else {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          'INSERT INTO members(user_id, member_number, full_name, date_of_birth, address, contact_number, registration_date, fitness_goal, status) VALUES(?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)',
        )->execute([$userId, $memberNumber, $fullName, $dateOfBirth, $address ?: null, $phone ?: null, $goal ?: null, $status]);
      }

      //  Save all database changes made inside the transaction.
      $pdo->commit();
      flash('success', "Member created. Temporary login: {$username} / {$password}");
    } catch (Throwable $error) {
      //  Undo the transaction if an error happens before completion.
      $pdo->rollBack();
      flash('danger', 'Could not create member. Check unique email, username and ID number values.');
    }
    //  Redirect the browser after this action to avoid repeating the same request.
    redirect('admin/members.php');
  }
}

$member = $editId
  ? one(
    $pdo,
    'SELECT m.*, u.email, u.username, u.contact_number AS user_contact_number FROM members m JOIN users u ON u.user_id = m.user_id WHERE m.member_id = ?',
    [$editId],
  )
  : null;

$query = trim($_GET['q'] ?? '');
$params = [];
$sql = 'SELECT m.*, u.email, u.username,
        (SELECT COUNT(*) FROM progress_records pr WHERE pr.member_id = m.member_id) AS progress_count
        FROM members m JOIN users u ON u.user_id = m.user_id';
if ($query !== '') {
  $sql .= ' WHERE m.full_name LIKE ? OR m.member_number LIKE ? OR u.username LIKE ? OR u.email LIKE ?';
  if ($hasIdNumber) {
    $sql .= ' OR m.id_number LIKE ?';
  }
  $like = '%' . $query . '%';
  $params = $hasIdNumber ? [$like, $like, $like, $like, $like] : [$like, $like, $like, $like];
}
$sql .= ' ORDER BY m.member_id DESC';
$members = all($pdo, $sql, $params);

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<?php if ($action === 'new' || $action === 'edit'): ?>
  <!-- This div groups related page content using the “panel” layout style. -->
  <div class="panel">
    <!-- This div groups related page content using the “panel-title” layout style. -->
    <div class="panel-title">
      <!-- This div groups related HTML content so the page structure is easier to manage. -->
      <div><small class="text-muted">Member profile editor</small>
        <h2><?= $member ? 'Edit member' : 'Add member' ?></h2>
      </div>
      <div class="d-flex gap-2">
        <?php if ($member): ?>
          <!-- This form collects user input and submits this form using the POST method. -->
          <form method="post" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="mode" value="remove">
            <input type="hidden" name="member_id" value="<?= (int) $member['member_id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" data-confirm="Are you sure you want to remove <?= e($member['full_name']) ?>? This will permanently delete this member account and all associated records."><i class="bi bi-trash3 me-1"></i>Remove</button>
          </form>
        <?php endif; ?>
        <a href="members.php" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
      </div>
    </div>

    <!-- This form collects user input and submits this form using the POST method. -->
    <form method="post" class="row g-3" data-loading-form>
      <?= csrfField() ?>
      <input type="hidden" name="mode" value="save">
      <input type="hidden" name="member_id" value="<?= (int) ($member['member_id'] ?? 0) ?>">

      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-12">
        <!-- This div shows a feedback message such as success, warning or error. -->
        <div class="alert alert-light border py-2 mb-0 small fw-semibold"><span class="text-danger">*</span> Required information</div>
      </div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label"><?= requiredLabel('Full name') ?></label><input class="form-control" name="full_name" required value="<?= e($member['full_name'] ?? '') ?>"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label"><?= requiredLabel('Username') ?></label><input class="form-control" name="username" required minlength="4" maxlength="50" pattern="[A-Za-z0-9._-]{4,50}" autocomplete="off" value="<?= e($member['username'] ?? '') ?>" placeholder="e.g. kasun.perera">
        <!-- This div groups related form controls for easier layout and validation. -->
        <div class="form-text"></div>
      </div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label"><?= requiredLabel('Email') ?></label><input class="form-control" name="email" type="email" required value="<?= e($member['email'] ?? '') ?>"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label"><?= requiredLabel('Contact number') ?></label><input class="form-control" type="tel" name="contact_number" required value="<?= e($member['contact_number'] ?? $member['user_contact_number'] ?? '') ?>" placeholder="07XXXXXXXX">
        <!-- This div groups related form controls for easier layout and validation. -->
        <div class="form-text"></div>
      </div>
      <?php if ($hasIdNumber): ?>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-6"><label class="form-label"><?= requiredLabel('ID / NIC number') ?></label><input class="form-control" name="id_number" maxlength="50" required value="<?= e($member['id_number'] ?? '') ?>" placeholder="NIC or gym-verified ID"></div>
      <?php endif; ?>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label"><?= requiredLabel('Date of birth') ?></label><input class="form-control" type="date" name="date_of_birth" required value="<?= e($member['date_of_birth'] ?? '') ?>"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-12"><label class="form-label"><?= requiredLabel('Address') ?></label><textarea class="form-control" name="address" rows="2" required placeholder="Member address"><?= e($member['address'] ?? '') ?></textarea></div>
      <?php if (!$member): ?>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-6">
          <label class="form-label"><?= requiredLabel('Temporary password') ?></label>
          <input class="form-control font-monospace" id="memberTemporaryPassword" name="temporary_password" required readonly value="<?= e($suggestedPassword) ?>" onclick="this.select()">
          <!-- This div groups related form controls for easier layout and validation. -->
          <div class="form-text">Give this password to the member once. At first sign-in PowerFit requires email verification, then forces a permanent password change.</div>
        </div>
      <?php endif; ?>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-9"><label class="form-label">Fitness goal</label><input class="form-control" name="fitness_goal" value="<?= e($member['fitness_goal'] ?? '') ?>" placeholder="e.g. General fitness, strength, mobility"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-3"><label class="form-label"><?= requiredLabel('Status') ?></label><select class="form-select" name="status" required><?php foreach (['Active', 'Inactive', 'Frozen'] as $status): ?><option <?= ($member['status'] ?? 'Active') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-12"><button class="btn btn-dark rounded-pill px-4"><?= $member ? 'Update member' : 'Create member' ?></button></div>
    </form>
  </div>
<?php else: ?>
  <!-- This div groups related page content using the “panel” layout style. -->
  <div class="panel">
    <!-- This div groups related page content using the “panel-title” layout style. -->
    <div class="panel-title">
      <!-- This div groups related HTML content so the page structure is easier to manage. -->
      <div><small class="text-muted">Member management</small>
        <h2>Members</h2>
      </div>
      <a class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" href="<?= url('admin/members.php?action=new') ?>">
        <span class="btn-ripple-circle"></span>
        <span class="btn-ripple-label"><i class="bi bi-person-plus me-1"></i>Add member</span>
      </a>
    </div>

    <!-- This form collects user input and submits this form using the GET method. -->
    <form class="row g-2 mb-4" method="get">
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-8"><input class="form-control" name="q" value="<?= e($query) ?>" placeholder="Search name, username, member number, email<?= $hasIdNumber ? ' or ID number' : '' ?>"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-4 d-flex gap-2"><button class="btn btn-outline-dark rounded-pill px-4">Search</button><a class="btn btn-outline-secondary rounded-pill px-4" href="members.php">Clear</a></div>
    </form>

    <!-- This div allows this table to scroll safely on smaller screens. -->
    <div class="table-responsive">
      <!-- This table displays database records in rows and columns for easy reading. -->
      <table class="table table-modern align-middle">
        <thead>
          <tr>
            <th>Member</th>
            <th>Contact</th>
            <th>Age</th>
            <th>Goal</th>
            <th>Progress</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $row): ?>
            <?php $age = ageFromDateOfBirth($row['date_of_birth']); ?>
            <tr>
              <td><strong><?= e($row['full_name']) ?></strong><small class="d-block text-muted">@<?= e($row['username']) ?> · <?= e($row['member_number']) ?><?php if ($hasIdNumber && !empty($row['id_number'])): ?> · ID <?= e($row['id_number']) ?><?php endif; ?></small></td>
              <td><?= e($row['email']) ?><small class="d-block text-muted"><?= e($row['contact_number'] ?? '') ?></small></td>
              <td><?= $age !== null ? $age . ' yrs' : '—' ?></td>
              <td><?= e($row['fitness_goal'] ?? '—') ?></td>
              <td><span class="badge text-bg-light rounded-pill"><?= (int) $row['progress_count'] ?> records</span></td>
              <td><?= statusBadge($row['status']) ?></td>
              <td class="text-end">
                <!-- This div groups related page content using the “d-inline-flex gap-1” layout style. -->
                <div class="d-inline-flex gap-1">
                  <a class="btn btn-sm btn-outline-dark rounded-pill" href="member_progress.php?id=<?= (int) $row['member_id'] ?>"><i class="bi bi-graph-up me-1"></i>Progress</a>
                  <a class="btn btn-sm btn-outline-secondary rounded-pill" href="?action=edit&id=<?= (int) $row['member_id'] ?>">Edit</a>
                  <!-- This form collects user input and submits this form using the POST method. -->
                  <form method="post" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="mode" value="remove">
                    <input type="hidden" name="member_id" value="<?= (int) $row['member_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" data-confirm="Are you sure you want to remove <?= e($row['full_name']) ?>? This will permanently delete this member account and all associated records.">Remove</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$members): ?><tr>
              <td colspan="7" class="text-center text-muted py-5">No members found.</td>
            </tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>