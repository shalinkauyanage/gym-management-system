<?php

/**
 * --------------------------------------------------------------------------
 * STUDENT VIVA EXPLANATION (simple English)
 * File: coach/members.php
 * Main user/area: Coach
 * Simple purpose: This file handles the Members part of PowerFit.
 * How to explain: PHP prepares/validates the data first, then the HTML shows
 * the result to the user. Database work is done with PDO prepared statements.
 * --------------------------------------------------------------------------
 */


/**
 * ============================================================================
 * POWERFIT VIVA FILE GUIDE
 * File: coach/members.php
 * Purpose: Lists the members currently assigned to the signed-in Coach.
 *
 * Viva flow to explain:
 * 1. Load shared configuration/helpers.
 * 2. Apply login/role/ownership security where the page is protected.
 * 3. Validate submitted data and CSRF tokens before changing records.
 * 4. Use PDO prepared statements for MySQL reads/writes.
 * 5. Pass safe/escaped values to the HTML view.
 * ============================================================================
 */
//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Coach');

$pageTitle = 'My Members';
$trainer = getTrainerByUser($pdo, currentUser()['id']);
$adviser = getAdviserByUser($pdo, currentUser()['id']);

if (!$trainer || !$adviser) {
  die('Coach profile is incomplete.');
}

$rows = all(
  $pdo,
  "SELECT DISTINCT m.*,
        p.record_date,
        p.weight_kg,
        p.height_cm,
        p.bmi,
        (SELECT COUNT(*) FROM workout_plans w WHERE w.member_id = m.member_id AND w.trainer_id = ?) AS workout_count,
        (SELECT COUNT(*) FROM meal_plan mp WHERE mp.member_id = m.member_id AND mp.adviser_id = ?) AS meal_count,
        (SELECT mp.daily_calories_target FROM meal_plan mp WHERE mp.member_id = m.member_id AND mp.adviser_id = ? AND mp.status = 'Active' ORDER BY mp.start_date DESC LIMIT 1) AS calorie_target
     FROM members m
     LEFT JOIN progress_records p
       ON p.progress_id = (
         SELECT p2.progress_id FROM progress_records p2
         WHERE p2.member_id = m.member_id
         ORDER BY p2.record_date DESC, p2.progress_id DESC LIMIT 1
       )
     WHERE m.member_id IN (
       SELECT member_id FROM trainer_assignments WHERE trainer_id = ? AND status = 'Active'
       UNION
       SELECT member_id FROM nutrition_assignments WHERE adviser_id = ? AND status = 'Active'
     )
     ORDER BY m.full_name",
  [
    (int) $trainer['trainer_id'],
    (int) $adviser['adviser_id'],
    (int) $adviser['adviser_id'],
    (int) $trainer['trainer_id'],
    (int) $adviser['adviser_id'],
  ],
);

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div groups related page content using the “panel” layout style. -->
<div class="panel">
  <!-- This div groups related page content using the “panel-title” layout style. -->
  <div class="panel-title">
    <!-- This div groups related HTML content so the page structure is easier to manage. -->
    <div><small class="text-muted">Training + nutrition clients</small>
      <h2>Assigned member directory</h2>
    </div>
    <span class="badge text-bg-light rounded-pill"><?= count($rows) ?> members</span>
  </div>

  <!-- This div shows a feedback message such as success, warning or error. -->
  <div class="alert alert-light border rounded-4 mb-4">
    <i class="bi bi-info-circle me-2"></i>Current weight, height and age are used to show an adult maintenance-calorie estimate for coach review. The estimate does not automatically change a member's meal target.
  </div>

  <!-- This div creates a Bootstrap grid row for responsive columns. -->
  <div class="row g-3">
    <?php foreach ($rows as $member): ?>
      <?php
      $age = ageFromDateOfBirth($member['date_of_birth']);
      $calorieEstimate = estimateDailyCalories($member['gender'] ?? null, $age, $member['weight_kg'], $member['height_cm']);
      ?>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6 col-xxl-4">
        <!-- This div shows one main PowerFit benefit in a reusable card layout. -->
        <div class="feature-card member-card-dashboard">
          <!-- This div uses flexbox to align the child elements in this area. -->
          <div class="d-flex justify-content-between align-items-start gap-3">
            <!-- This div holds the user or coach profile placeholder/image area. -->
            <div class="avatar-sm"><?= e(strtoupper(substr($member['full_name'], 0, 1))) ?></div>
            <?= statusBadge($member['status']) ?>
          </div>
          <h3><?= e($member['full_name']) ?></h3>
          <p><?= e($member['member_number']) ?> · <?= $age !== null ? $age . ' years' : 'Age not recorded' ?></p>
          <small class="d-block text-muted mb-3"><?= e($member['fitness_goal'] ?? 'No goal recorded') ?></small>

          <!-- This div groups items in a grid-style layout. -->
          <div class="coach-client-grid">
            <!-- This div groups related HTML content so the page structure is easier to manage. -->
            <div><small>Weight</small><strong><?= $member['weight_kg'] ? e($member['weight_kg']) . ' kg' : '—' ?></strong></div>
            <!-- This div groups related HTML content so the page structure is easier to manage. -->
            <div><small>Height</small><strong><?= $member['height_cm'] ? e($member['height_cm']) . ' cm' : '—' ?></strong></div>
            <!-- This div groups related HTML content so the page structure is easier to manage. -->
            <div><small>BMI</small><strong><?= $member['bmi'] ? e($member['bmi']) : '—' ?></strong></div>
            <!-- This div groups related HTML content so the page structure is easier to manage. -->
            <div><small>Est. daily calories</small><strong><?= $calorieEstimate ? number_format($calorieEstimate['maintenance']) . ' kcal' : 'Not available' ?></strong></div>
            <!-- This div groups related HTML content so the page structure is easier to manage. -->
            <div><small>Meal target</small><strong><?= $member['calorie_target'] ? e((string) $member['calorie_target']) . ' kcal' : 'Not set' ?></strong></div>
          </div>

          <!-- This div uses flexbox to align the child elements in this area. -->
          <div class="d-flex flex-wrap gap-2 mt-3">
            <a class="btn btn-sm btn-dark rounded-pill" href="workouts.php?action=new&member=<?= (int) $member['member_id'] ?>">Workout</a>
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="meals.php?action=new&member=<?= (int) $member['member_id'] ?>">Meal plan</a>
            <a class="btn btn-sm btn-outline-danger rounded-pill" href="member_progress.php?id=<?= (int) $member['member_id'] ?>"><i class="bi bi-clipboard-data me-1"></i>Progress report</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (!$rows): ?>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-12"><!-- This div groups related content using the empty-state layout class. -->
        <div class="empty-state"><i class="bi bi-people"></i>No members are currently assigned.</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>