<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Admin');
$pageTitle = 'Coach Assignments';

if (!tableExists($pdo, 'trainer_assignments') || !tableExists($pdo, 'nutrition_assignments')) {
  //  Load the shared file needed before this page continues.
  require __DIR__ . '/../includes/dashboard_header.php';
  echo '<div class="alert alert-warning">Import <strong>database/powerfit_final_database_v3.sql</strong> or run the V3 patch to enable coach assignments.</div>';
  //  Load the shared file needed before this page continues.
  require __DIR__ . '/../includes/dashboard_footer.php';
  exit;
}

// POST handling: only run this block when a form has been submitted.
//  Run this block only when the form is submitted with POST.
if (isPost()) {
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();
  $memberId = (int) ($_POST['member_id'] ?? 0);
  $userId = (int) ($_POST['coach_user_id'] ?? 0);
  $startDate = $_POST['start_date'] ?? date('Y-m-d');
  $notes = trim($_POST['notes'] ?? '');

  $coach = one(
    $pdo,
    "SELECT t.trainer_id, n.adviser_id, u.user_id
         FROM users u
         JOIN trainers t ON t.user_id = u.user_id
         JOIN nutrition_advisers n ON n.user_id = u.user_id
         WHERE u.user_id = ? AND u.status = 'Active' AND t.status = 'Active' AND n.status = 'Active'",
    [$userId],
  );

  if (!$memberId || !$coach) {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('danger', 'Choose a valid member and active coach.');
    redirect('admin/assignments.php');
  }

  // group related SQL changes so they either all succeed or all roll back.
  //  Start a database transaction so related changes succeed or fail together.
  $pdo->beginTransaction();
  try {
    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare("UPDATE trainer_assignments SET status = 'Inactive' WHERE member_id = ? AND status = 'Active'")->execute([$memberId]);
    $pdo->prepare("UPDATE nutrition_assignments SET status = 'Inactive' WHERE member_id = ? AND status = 'Active'")->execute([$memberId]);

    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare(
      "INSERT INTO trainer_assignments(member_id, trainer_id, start_date, status, notes)
             VALUES(?, ?, ?, 'Active', ?)",
    )->execute([$memberId, $coach['trainer_id'], $startDate, $notes]);

    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare(
      "INSERT INTO nutrition_assignments(member_id, adviser_id, start_date, status, notes)
             VALUES(?, ?, ?, 'Active', ?)",
    )->execute([$memberId, $coach['adviser_id'], $startDate, $notes]);

    //  Save all database changes made inside the transaction.
    $pdo->commit();
    flash('success', 'Coach assignment saved for both workout and nutrition support.');
  } catch (Throwable $error) {
    //  Undo the transaction if an error happens before completion.
    $pdo->rollBack();
    flash('danger', 'The assignment could not be saved.');
  }

  //  Redirect the browser after this action to avoid repeating the same request.
  redirect('admin/assignments.php');
}

$members = all(
  $pdo,
  "SELECT member_id, member_number, full_name FROM members WHERE status = 'Active' ORDER BY full_name",
);

$nameExpr = columnExists($pdo, 'trainers', 'display_name')
  ? "COALESCE(NULLIF(t.display_name, ''), u.username)"
  : 'u.username';

$coaches = all(
  $pdo,
  "SELECT u.user_id, t.trainer_id, n.adviser_id, {$nameExpr} AS coach_name
     FROM users u
     JOIN roles r ON r.role_id = u.role_id
     JOIN trainers t ON t.user_id = u.user_id
     JOIN nutrition_advisers n ON n.user_id = u.user_id
     WHERE r.role_name IN ('Coach', 'Trainer', 'Adviser')
       AND u.status = 'Active' AND t.status = 'Active' AND n.status = 'Active'
     ORDER BY coach_name",
);

$rows = all(
  $pdo,
  "SELECT m.full_name, m.member_number,
        {$nameExpr} AS coach_name,
        ta.start_date, ta.notes
     FROM members m
     LEFT JOIN trainer_assignments ta ON ta.member_id = m.member_id AND ta.status = 'Active'
     LEFT JOIN trainers t ON t.trainer_id = ta.trainer_id
     LEFT JOIN users u ON u.user_id = t.user_id
     ORDER BY m.full_name",
);

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4">
  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-7">
    <!-- This div groups related page content using the “panel” layout style. -->
    <div class="panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title">
        <!-- This div groups related HTML content so the page structure is easier to manage. -->
        <div>
          <h2>Current coach assignments</h2><small class="text-muted">One coach manages training and nutrition for the member.</small>
        </div>
      </div>
      <!-- This div allows this table to scroll safely on smaller screens. -->
      <div class="table-responsive">
        <!-- This table displays database records in rows and columns for easy reading. -->
        <table class="table table-modern">
          <thead>
            <tr>
              <th>Member</th>
              <th>Coach</th>
              <th>Start date</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><strong><?= e($row['full_name']) ?></strong><small class="d-block text-muted"><?= e($row['member_number']) ?></small></td>
                <td><?= e($row['coach_name'] ?? 'Not assigned') ?></td>
                <td><?= shortDate($row['start_date'] ?? null) ?></td>
                <td><?= e($row['notes'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-5">
    <!-- This div groups related page content using the “panel” layout style. -->
    <div class="panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title">
        <h2>Assign coach</h2>
      </div>
      <!-- This form collects user input and submits this form using the POST method. -->
      <form method="post" class="row g-3" data-loading-form>
        <?= csrfField() ?>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-12">
          <label class="form-label">Member</label>
          <select class="form-select" name="member_id" required>
            <option value="">Choose member</option>
            <?php foreach ($members as $member): ?>
              <option value="<?= (int) $member['member_id'] ?>"><?= e($member['member_number'] . ' — ' . $member['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-12">
          <label class="form-label">Coach</label>
          <select class="form-select" name="coach_user_id" required>
            <option value="">Choose coach</option>
            <?php foreach ($coaches as $coach): ?>
              <option value="<?= (int) $coach['user_id'] ?>"><?= e($coach['coach_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="form-text text-muted">The same staff account is connected to workout and meal planning.</small>
        </div>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-12">
          <label class="form-label">Start date</label>
          <input class="form-control" type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-12">
          <label class="form-label">Notes</label>
          <textarea class="form-control" name="notes" rows="3" maxlength="500"></textarea>
        </div>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-12"><button class="btn btn-dark rounded-pill px-4">Save assignment</button></div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>