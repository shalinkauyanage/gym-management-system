<?php

/**
 * --------------------------------------------------------------------------
 * STUDENT VIVA EXPLANATION (simple English)
 * File: coach/progress_reports.php
 * Main user/area: Coach
 * Simple purpose: This file handles the Progress Reports part of PowerFit.
 * How to explain: PHP prepares/validates the data first, then the HTML shows
 * the result to the user. Database work is done with PDO prepared statements.
 * --------------------------------------------------------------------------
 */


/**
 * ============================================================================
 * POWERFIT VIVA FILE GUIDE
 * File: coach/progress_reports.php
 * Purpose: Lists assigned clients and opens their progress information for Coach review.
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

$pageTitle = 'Client Progress Reports';
$userId = (int) currentUser()['id'];
$memberIds = coachAssignedMemberIds($pdo, $userId);

$clients = [];
if ($memberIds) {
  $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
  $clients = all(
    $pdo,
    "SELECT m.*,
            (SELECT COUNT(*) FROM progress_records pr WHERE pr.member_id = m.member_id) AS progress_count,
            (SELECT pr.weight_kg FROM progress_records pr WHERE pr.member_id = m.member_id ORDER BY pr.record_date DESC, pr.progress_id DESC LIMIT 1) AS latest_weight,
            (SELECT pr.height_cm FROM progress_records pr WHERE pr.member_id = m.member_id ORDER BY pr.record_date DESC, pr.progress_id DESC LIMIT 1) AS latest_height,
            (SELECT pr.bmi FROM progress_records pr WHERE pr.member_id = m.member_id ORDER BY pr.record_date DESC, pr.progress_id DESC LIMIT 1) AS latest_bmi,
            (SELECT pr.record_date FROM progress_records pr WHERE pr.member_id = m.member_id ORDER BY pr.record_date DESC, pr.progress_id DESC LIMIT 1) AS latest_date
         FROM members m
         WHERE m.member_id IN ({$placeholders})
         ORDER BY m.full_name",
    $memberIds,
  );
}

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div groups related page content using the “panel mb-4” layout style. -->
<div class="panel mb-4">
  <!-- This div groups related page content using the “panel-title” layout style. -->
  <div class="panel-title">
    <!-- This div groups related HTML content so the page structure is easier to manage. -->
    <div>
      <small class="text-muted">Assigned client monitoring</small>
      <h2>Progress reports</h2>
    </div>
    <span class="badge text-bg-light rounded-pill"><?= count($clients) ?> clients</span>
  </div>
  <p class="text-muted mb-0">Open an assigned member to review dated measurements and protected front, side and back progress photos. Measurements support coaching review; PowerFit does not make medical or diagnostic decisions.</p>
</div>

<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4">
  <?php foreach ($clients as $client): ?>
    <?php $age = ageFromDateOfBirth($client['date_of_birth'] ?? null); ?>
    <!-- This div creates a responsive Bootstrap column inside the current row. -->
    <div class="col-md-6 col-xl-4">
      <article class="client-report-card h-100">
        <!-- This div uses flexbox to align the child elements in this area. -->
        <div class="d-flex justify-content-between gap-3 mb-3">
          <!-- This div groups related HTML content so the page structure is easier to manage. -->
          <div>
            <small class="text-muted"><?= e($client['member_number']) ?><?= $age !== null ? ' · ' . $age . ' yrs' : '' ?></small>
            <h3 class="h5 mb-0"><?= e($client['full_name']) ?></h3>
          </div>
          <span class="badge text-bg-light rounded-pill align-self-start"><?= (int) $client['progress_count'] ?> records</span>
        </div>

        <!-- This div shows one small summary metric. -->
        <div class="client-report-metrics">
          <!-- This div groups related HTML content so the page structure is easier to manage. -->
          <div><small>Weight</small><strong><?= $client['latest_weight'] !== null ? e((string) $client['latest_weight']) . ' kg' : '—' ?></strong></div>
          <!-- This div groups related HTML content so the page structure is easier to manage. -->
          <div><small>Height</small><strong><?= $client['latest_height'] !== null ? e((string) $client['latest_height']) . ' cm' : '—' ?></strong></div>
          <!-- This div groups related HTML content so the page structure is easier to manage. -->
          <div><small>BMI</small><strong><?= $client['latest_bmi'] !== null ? e((string) $client['latest_bmi']) : '—' ?></strong></div>
        </div>
        <?php $calorieEstimate = estimateDailyCalories($client['gender'] ?? null, $age, $client['latest_weight'], $client['latest_height']); ?>
        <!-- This div groups related page content using the “client-calorie-estimate mt-3” layout style. -->
        <div class="client-calorie-estimate mt-3">
          <i class="bi bi-fire"></i>
          <!-- This div groups related HTML content so the page structure is easier to manage. -->
          <div><small>Estimated daily maintenance</small><strong><?= $calorieEstimate ? number_format($calorieEstimate['maintenance']) . ' kcal/day' : 'Needs adult weight, height & sex data' ?></strong></div>
        </div>

        <small class="d-block text-muted mt-3">Latest check-in: <?= $client['latest_date'] ? shortDate($client['latest_date']) : 'No progress data yet' ?></small>
        <a class="btn btn-dark rounded-pill w-100 mt-3" href="member_progress.php?id=<?= (int) $client['member_id'] ?>">
          <i class="bi bi-clipboard-data me-2"></i>View full progress report
        </a>
      </article>
    </div>
  <?php endforeach; ?>

  <?php if (!$clients): ?>
    <!-- This div creates a responsive Bootstrap column inside the current row. -->
    <div class="col-12"><!-- This div groups related content using the empty-state layout class. -->
      <div class="empty-state"><i class="bi bi-clipboard-data"></i>No assigned clients are available for progress reporting.</div>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>