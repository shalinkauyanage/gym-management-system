<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Member');

$pageTitle = 'My Workout';
$member = getMemberByUser($pdo, currentUser()['id']);
$plan = one(
  $pdo,
  "SELECT w.*, COALESCE(NULLIF(t.display_name, ''), u.username) AS coach
     FROM workout_plans w
     JOIN trainers t ON t.trainer_id = w.trainer_id
     JOIN users u ON u.user_id = t.user_id
     WHERE w.member_id = ? AND w.status = 'Active'
     ORDER BY w.start_date DESC LIMIT 1",
  [$member['member_id']],
);
$items = $plan
  ? all(
    $pdo,
    "SELECT * FROM workout_items WHERE plan_id = ?
         ORDER BY FIELD(day_of_week, 'Mon','Tue','Wed','Thu','Fri','Sat','Sun'), item_id",
    [$plan['plan_id']],
  )
  : [];

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div groups related page content using the “panel” layout style. -->
<div class="panel">
  <!-- This div groups related page content using the “panel-title” layout style. -->
  <div class="panel-title">
    <!-- This div groups related HTML content so the page structure is easier to manage. -->
    <div>
      <small class="text-muted">Current training program</small>
      <h2><?= e($plan['fitness_goal'] ?? 'No active workout') ?></h2>
      <?php if ($plan): ?>
        <small class="text-muted">Coach: <?= e($plan['coach']) ?> · <?= shortDate($plan['start_date']) ?> - <?= shortDate($plan['end_date']) ?></small>
      <?php endif; ?>
    </div>
    <?php if ($plan): ?>
      <a class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" href="workout_plan_pdf.php?id=<?= (int) $plan['plan_id'] ?>">
        <span class="btn-ripple-circle"></span>
        <span class="btn-ripple-label"><i class="bi bi-file-earmark-pdf me-2"></i>Download PDF</span>
      </a>
    <?php endif; ?>
  </div>

  <?php if ($items): ?>
    <!-- This div allows this table to scroll safely on smaller screens. -->
    <div class="table-responsive">
      <!-- This table displays database records in rows and columns for easy reading. -->
      <table class="table table-modern align-middle">
        <thead>
          <tr>
            <th>Day</th>
            <th>Exercise</th>
            <th>Sets × Reps</th>
            <th>Duration</th>
            <th>Rest</th>
            <th>Instructions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><span class="badge rounded-pill text-bg-dark"><?= e($item['day_of_week']) ?></span></td>
              <td><strong><?= e($item['exercise_name']) ?></strong></td>
              <td><?= e((string) $item['sets']) ?> × <?= e((string) $item['repetitions']) ?></td>
              <td><?= $item['duration_minutes'] ? e((string) $item['duration_minutes']) . ' min' : '—' ?></td>
              <td><?= $item['rest_seconds'] ? e((string) $item['rest_seconds']) . ' sec' : '—' ?></td>
              <td><?= e($item['instructions']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <!-- This div shows one summary value and its label. -->
    <div class="empty-state"><i class="bi bi-activity"></i>Your coach has not added workout items yet.</div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>