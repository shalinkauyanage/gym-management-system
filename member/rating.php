<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Member');
$pageTitle = 'Rate Coach';
$member = getMemberByUser($pdo, currentUser()['id']);

$nameExpr = columnExists($pdo, 'trainers', 'display_name')
  ? "COALESCE(NULLIF(t.display_name, ''), u.username)"
  : 'u.username';

$coach = $member && tableExists($pdo, 'trainer_assignments')
  ? one(
    $pdo,
    "SELECT t.trainer_id, {$nameExpr} AS coach_name, t.average_rating
         FROM trainer_assignments a
         JOIN trainers t ON t.trainer_id = a.trainer_id
         JOIN users u ON u.user_id = t.user_id
         WHERE a.member_id = ? AND a.status = 'Active'
         ORDER BY a.assignment_id DESC LIMIT 1",
    [$member['member_id']],
  )
  : null;

if (!$coach && $member) {
  $coach = one(
    $pdo,
    "SELECT t.trainer_id, {$nameExpr} AS coach_name, t.average_rating
         FROM workout_plans w
         JOIN trainers t ON t.trainer_id = w.trainer_id
         JOIN users u ON u.user_id = t.user_id
         WHERE w.member_id = ?
         ORDER BY w.start_date DESC LIMIT 1",
    [$member['member_id']],
  );
}

// POST handling: only run this block when a form has been submitted.
//  Run this block only when the form is submitted with POST.
if (isPost()) {
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();
  $action = $_POST['action'] ?? '';

  if ($action === 'delete') {
    $ratingId = (int) ($_POST['rating_id'] ?? 0);
    $rating = $member
      ? one($pdo, 'SELECT * FROM trainer_ratings WHERE rating_id = ? AND member_id = ?', [$ratingId, $member['member_id']])
      : null;

    if ($rating) {
      $trainerId = (int) $rating['trainer_id'];
      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare('DELETE FROM trainer_ratings WHERE rating_id = ? AND member_id = ?')
        ->execute([$ratingId, $member['member_id']]);
      syncTrainerRating($pdo, $trainerId);
      //  Save a one-time message so the next page can tell the user what happened.
      flash('success', 'Your rating has been deleted.');
    } else {
      //  Save a one-time message so the next page can tell the user what happened.
      flash('danger', 'Rating not found or access denied.');
    }
    //  Redirect the browser after this action to avoid repeating the same request.
    redirect('member/rating.php');
  }

  if ($coach) {
    $score = (int) ($_POST['score'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($score < 1 || $score > 5) {
      //  Save a one-time message so the next page can tell the user what happened.
      flash('danger', 'Rating must be between 1 and 5.');
      redirect('member/rating.php');
    }

    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare(
      "INSERT INTO trainer_ratings(trainer_id, member_id, score, comment, status, rating_date)
             VALUES(?, ?, ?, ?, 'Visible', CURDATE())",
    )->execute([$coach['trainer_id'], $member['member_id'], $score, $comment]);

    syncTrainerRating($pdo, (int) $coach['trainer_id']);

    $average = (float) scalar(
      $pdo,
      "SELECT COALESCE(AVG(score), 0) FROM trainer_ratings WHERE trainer_id = ? AND status = 'Visible'",
      [$coach['trainer_id']],
    );

    if ($average < 3) {
      $admins = all(
        $pdo,
        "SELECT u.user_id FROM users u JOIN roles r ON r.role_id = u.role_id
                 WHERE r.role_name = 'Admin' AND u.status = 'Active'",
      );
      foreach ($admins as $admin) {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          "INSERT INTO notifications(user_id, title, message, type)
                     VALUES(?, 'Coach rating alert', 'A coach average rating has fallen below 3.0.', 'Rating')",
        )->execute([$admin['user_id']]);
      }
    }

    //  Save a one-time message so the next page can tell the user what happened.
    flash('success', 'Thanks. Your coach rating has been submitted.');
    redirect('member/rating.php');
  }
}

$history = $coach
  ? all(
    $pdo,
    'SELECT * FROM trainer_ratings WHERE trainer_id = ? AND member_id = ? ORDER BY rating_date DESC, rating_id DESC',
    [$coach['trainer_id'], $member['member_id']],
  )
  : [];

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4">
  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-6">
    <!-- This div groups related page content using the “panel” layout style. -->
    <div class="panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title">
        <h2>Your coach</h2>
      </div>
      <?php if ($coach): ?>
        <!-- This div creates a bordered content card for related information. -->
        <div class="quick-card">
          <i class="bi bi-person-workspace"></i>
          <!-- This div creates a Bootstrap grid row for responsive columns. -->
          <div class="flex-grow-1"><strong><?= e($coach['coach_name']) ?></strong><small class="d-block text-muted">Training + nutrition coach · Current average <?= number_format((float) $coach['average_rating'], 1) ?>/5</small></div>
          <span class="rating-stars">★</span>
        </div>
        <!-- This form collects user input and submits this form using the POST method. -->
        <form method="post" class="mt-4" data-loading-form>
          <?= csrfField() ?>
          <label class="form-label">Rating</label>
          <select name="score" class="form-select mb-3" required>
            <option value="">Choose 1 to 5</option>
            <option value="5">5 — Excellent</option>
            <option value="4">4 — Good</option>
            <option value="3">3 — Satisfactory</option>
            <option value="2">2 — Needs improvement</option>
            <option value="1">1 — Poor</option>
          </select>
          <label class="form-label">Comment</label>
          <textarea name="comment" class="form-control mb-3" rows="4" maxlength="500" placeholder="Share constructive feedback"></textarea>
          <button class="btn btn-dark rounded-pill px-4">Submit rating</button>
        </form>
      <?php else: ?>
        <!-- This div shows one summary value and its label. -->
        <div class="empty-state"><i class="bi bi-star"></i>No coach is currently assigned to your member profile.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-6">
    <!-- This div groups related page content using the “panel” layout style. -->
    <div class="panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title">
        <h2>Your rating history</h2>
      </div>
      <?php foreach ($history as $rating): ?>
        <!-- This div groups related page content using the “border-bottom pb-3” layout style. -->
        <div class="border-bottom pb-3 mb-3">
          <!-- This div uses flexbox to align the child elements in this area. -->
          <div class="d-flex justify-content-between align-items-center gap-2">
            <!-- This div groups related page content using the “rating-stars” layout style. -->
            <div class="rating-stars"><?= str_repeat('★', (int) $rating['score']) ?></div>
            <!-- This form collects user input and submits this form using the POST method. -->
            <form method="post" class="d-inline" data-loading-form>
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="rating_id" value="<?= (int) $rating['rating_id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" data-confirm="Delete this rating?">
                <i class="bi bi-trash3 me-1"></i>Delete
              </button>
            </form>
          </div>
          <?php if (!empty($rating['comment'])): ?>
            <p class="mb-1 mt-2"><?= e($rating['comment']) ?></p>
          <?php endif; ?>
          <small class="text-muted d-block mt-1"><?= shortDate($rating['rating_date']) ?></small>
        </div>
      <?php endforeach; ?>
      <?php if (!$history): ?><!-- This div shows one summary value and its label. -->
        <div class="empty-state"><i class="bi bi-chat-left-text"></i>No previous ratings.</div><?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>