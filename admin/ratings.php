<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Admin');
$pageTitle = 'Coach Ratings';
$nameExpr = columnExists($pdo, 'trainers', 'display_name')
  ? "COALESCE(NULLIF(t.display_name, ''), u.username)"
  : 'u.username';

// POST handling: only run this block when a form has been submitted.
//  Run this block only when the form is submitted with POST.
if (isPost()) {
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();
  $action = $_POST['action'] ?? '';
  $ratingId = (int) ($_POST['rating_id'] ?? 0);

  if ($action === 'delete' && $ratingId > 0) {
    $rating = one($pdo, 'SELECT * FROM trainer_ratings WHERE rating_id = ?', [$ratingId]);
    if ($rating) {
      $trainerId = (int) $rating['trainer_id'];
      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare('DELETE FROM trainer_ratings WHERE rating_id = ?')->execute([$ratingId]);
      syncTrainerRating($pdo, $trainerId);
      //  Save a one-time message so the next page can tell the user what happened.
      flash('success', 'Rating deleted successfully.');
    } else {
      //  Save a one-time message so the next page can tell the user what happened.
      flash('danger', 'Rating not found.');
    }
    //  Redirect the browser after this action to avoid repeating the same request.
    redirect('admin/ratings.php');
  }
}

$rows = all(
  $pdo,
  "SELECT r.*, m.full_name, {$nameExpr} AS coach_name
     FROM trainer_ratings r
     JOIN members m ON m.member_id = r.member_id
     JOIN trainers t ON t.trainer_id = r.trainer_id
     JOIN users u ON u.user_id = t.user_id
     ORDER BY r.rating_date DESC, r.rating_id DESC",
);
$summary = all(
  $pdo,
  "SELECT t.trainer_id, {$nameExpr} AS coach_name,
        COUNT(r.rating_id) rating_count, COALESCE(AVG(r.score), 0) avg_score
     FROM trainers t
     JOIN users u ON u.user_id = t.user_id
     LEFT JOIN trainer_ratings r ON r.trainer_id = t.trainer_id AND r.status = 'Visible'
     GROUP BY t.trainer_id, coach_name
     ORDER BY avg_score DESC",
);
//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4 mb-4">
  <?php foreach ($summary as $item): ?>
    <!-- This div creates a responsive Bootstrap column inside the current row. -->
    <div class="col-md-6 col-xl-4">
      <!-- This div creates a bordered content card for related information. -->
      <div class="stat-card">
        <!-- This div uses flexbox to align the child elements in this area. -->
        <div class="d-flex justify-content-between gap-3">
          <strong><?= e($item['coach_name']) ?></strong>
          <?= (float) $item['avg_score'] < 3 && (int) $item['rating_count'] > 0
            ? '<span class="badge text-bg-danger rounded-pill">Needs Improvement</span>'
            : '<span class="badge text-bg-success rounded-pill">Good</span>' ?>
        </div>
        <!-- This div groups related page content using the “value mt-3” layout style. -->
        <div class="value mt-3"><?= number_format((float) $item['avg_score'], 1) ?>/5</div>
        <!-- This div groups related page content using the “rating-stars” layout style. -->
        <div class="rating-stars"><?= str_repeat('★', (int) round((float) $item['avg_score'])) ?></div>
        <small class="text-muted"><?= (int) $item['rating_count'] ?> ratings</small>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- This div groups related page content using the “panel” layout style. -->
<div class="panel">
  <!-- This div groups related page content using the “panel-title” layout style. -->
  <div class="panel-title">
    <h2>Rating history</h2>
  </div>
  <!-- This div allows this table to scroll safely on smaller screens. -->
  <div class="table-responsive">
    <!-- This table displays database records in rows and columns for easy reading. -->
    <table class="table table-modern">
      <thead>
        <tr>
          <th>Coach</th>
          <th>Member</th>
          <th>Score</th>
          <th>Comment</th>
          <th>Date</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= e($row['coach_name']) ?></td>
            <td><?= e($row['full_name']) ?></td>
            <td><span class="rating-stars"><?= str_repeat('★', (int) $row['score']) ?></span></td>
            <td><?= e($row['comment']) ?></td>
            <td><?= shortDate($row['rating_date']) ?></td>
            <td class="text-end">
              <!-- This form collects user input and submits this form using the POST method. -->
              <form method="post" class="d-inline" data-loading-form>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="rating_id" value="<?= (int) $row['rating_id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" data-confirm="Delete this rating?">
                  <i class="bi bi-trash3 me-1"></i>Delete
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="6" class="text-center py-4 text-muted">No ratings recorded yet.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>