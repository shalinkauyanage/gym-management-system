<?php

/**
 * --------------------------------------------------------------------------
 * STUDENT VIVA EXPLANATION (simple English)
 * File: coach/member_progress.php
 * Main user/area: Coach
 * Simple purpose: This file handles the Member Progress part of PowerFit.
 * How to explain: PHP prepares/validates the data first, then the HTML shows
 * the result to the user. Database work is done with PDO prepared statements.
 * --------------------------------------------------------------------------
 */


/**
 * ============================================================================
 * POWERFIT VIVA FILE GUIDE
 * File: coach/member_progress.php
 * Purpose: Shows one assigned member’s detailed measurements, calorie estimate and progress history.
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

$pageTitle = 'Client Progress Report';
$memberId = (int) ($_GET['id'] ?? 0);
if (!$memberId || !coachCanAccessMember($pdo, currentUser()['id'], $memberId)) {
  http_response_code(403);
  die('You can only view progress for members assigned to you.');
}

$member = one($pdo, 'SELECT * FROM members WHERE member_id = ?', [$memberId]);
$records = all(
  $pdo,
  "SELECT pr.*, (SELECT COUNT(*) FROM progress_photos ph WHERE ph.progress_id = pr.progress_id AND ph.status = 'Visible') AS photo_count
     FROM progress_records pr WHERE pr.member_id = ? ORDER BY pr.record_date DESC, pr.progress_id DESC",
  [$memberId],
);
$chart = array_reverse($records);
$photos = all(
  $pdo,
  "SELECT ph.*, pr.record_date FROM progress_photos ph
     JOIN progress_records pr ON pr.progress_id = ph.progress_id
     WHERE pr.member_id = ? AND ph.status = 'Visible'
     ORDER BY pr.record_date DESC, FIELD(ph.photo_type, 'Front','Side','Back'), ph.photo_id DESC",
  [$memberId],
);
$latest = $records[0] ?? null;
$age = ageFromDateOfBirth($member['date_of_birth']);
$calorieEstimate = estimateDailyCalories($member['gender'] ?? null, $age, $latest['weight_kg'] ?? null, $latest['height_cm'] ?? null);
$adviser = getAdviserByUser($pdo, currentUser()['id']);
$currentMeal = $adviser
  ? one(
    $pdo,
    "SELECT plan_name, daily_calories_target FROM meal_plan WHERE member_id = ? AND adviser_id = ? AND status = 'Active' ORDER BY start_date DESC LIMIT 1",
    [$memberId, (int) $adviser['adviser_id']],
  )
  : null;

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div uses flexbox to align the child elements in this area. -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <!-- This div groups related HTML content so the page structure is easier to manage. -->
  <div><small class="text-muted">Assigned client report</small>
    <h2 class="mb-1"><?= e($member['full_name']) ?></h2><span class="text-muted"><?= e($member['member_number']) ?><?= $age !== null ? ' · ' . $age . ' years' : '' ?></span>
  </div>
  <a href="progress_reports.php" class="btn btn-outline-secondary rounded-pill px-4"><i class="bi bi-arrow-left me-2"></i>Back to clients</a>
</div>

<!-- This div creates a bordered content card for related information. -->
<div class="coach-analysis-card mb-4">
  <!-- This div groups related HTML content so the page structure is easier to manage. -->
  <div><small>Current weight</small><strong><?= $latest && $latest['weight_kg'] ? e($latest['weight_kg']) . ' kg' : '—' ?></strong></div>
  <!-- This div groups related HTML content so the page structure is easier to manage. -->
  <div><small>Current height</small><strong><?= $latest && $latest['height_cm'] ? e($latest['height_cm']) . ' cm' : '—' ?></strong></div>
  <!-- This div groups related HTML content so the page structure is easier to manage. -->
  <div><small>Current BMI</small><strong><?= $latest && $latest['bmi'] ? e($latest['bmi']) : '—' ?></strong></div>
  <!-- This div groups related HTML content so the page structure is easier to manage. -->
  <div><small>Estimated daily calories</small><strong><?= $calorieEstimate ? number_format($calorieEstimate['maintenance']) . ' kcal/day' : 'Not available' ?></strong></div>
  <!-- This div groups related HTML content so the page structure is easier to manage. -->
  <div><small>Current meal target</small><strong><?= $currentMeal && $currentMeal['daily_calories_target'] ? e((string) $currentMeal['daily_calories_target']) . ' kcal/day' : 'Not set' ?></strong></div>
</div>
<!-- This div shows a feedback message such as success, warning or error. -->
<div class="alert alert-light border rounded-4"><i class="bi bi-info-circle me-2"></i>The calorie figure is an adult maintenance estimate using current weight, height, age and the Mifflin-St Jeor equation with a light-activity factor. It is shown for coach review only and does not automatically create a diet target.</div>

<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4">
  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-7">
    <!-- This div groups related page content using the “panel mb-4” layout style. -->
    <div class="panel mb-4"><!-- This div holds the heading for this dashboard panel. -->
      <div class="panel-title">
        <h2>Weight history</h2>
      </div><!-- This div groups related HTML content on this page. -->
      <div style="height:300px"><canvas id="coachProgressChart"></canvas></div>
    </div>
    <!-- This div groups related page content using the “panel” layout style. -->
    <div class="panel"><!-- This div holds the heading for this dashboard panel. -->
      <div class="panel-title">
        <h2>Progress history</h2>
      </div><!-- This div allows the table to scroll on smaller screens. -->
      <div class="table-responsive">
        <table class="table table-modern align-middle">
          <thead>
            <tr>
              <th>Date</th>
              <th>Weight</th>
              <th>Height</th>
              <th>BMI</th>
              <th>Waist</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $record): ?><tr>
                <td><?= shortDate($record['record_date']) ?></td>
                <td><?= $record['weight_kg'] ? e($record['weight_kg']) . ' kg' : '—' ?></td>
                <td><?= $record['height_cm'] ? e($record['height_cm']) . ' cm' : '—' ?></td>
                <td><?= $record['bmi'] ?: '—' ?></td>
                <td><?= $record['waist_cm'] ? e($record['waist_cm']) . ' cm' : '—' ?></td>
                <td><?= e($record['notes'] ?? '') ?></td>
              </tr><?php endforeach; ?>
            <?php if (!$records): ?><tr>
                <td colspan="6" class="text-center text-muted py-5">No progress history yet.</td>
              </tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-5">
    <!-- This div groups related page content using the “panel” layout style. -->
    <div class="panel"><!-- This div holds the heading for this dashboard panel. -->
      <div class="panel-title">
        <h2>Progress images</h2><span class="analytics-chip"><i class="bi bi-shield-lock me-1"></i>Assigned coach only</span>
      </div><!-- This div groups the three protected progress photo areas. -->
      <div class="progress-report-gallery">
        <?php foreach ($photos as $photo): ?><a class="progress-report-photo" href="<?= url('progress_image.php?photo=' . (int) $photo['photo_id']) ?>" target="_blank"><img src="<?= url('progress_image.php?photo=' . (int) $photo['photo_id']) ?>" alt="<?= e($photo['photo_type']) ?> progress"><span><?= e($photo['photo_type']) ?> · <?= shortDate($photo['record_date']) ?></span></a><?php endforeach; ?>
        <?php if (!$photos): ?><!-- This div shows one summary value and its label. -->
          <div class="empty-state"><i class="bi bi-images"></i>No progress images available.</div><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
  const coachProgress = document.getElementById('coachProgressChart');
  if (coachProgress && window.Chart) {
    new Chart(coachProgress, {
      type: 'line',
      data: {
        labels: <?= json_encode(array_map(fn($r) => date('d M', strtotime($r['record_date'])), $chart)) ?>,
        datasets: [{
          label: 'Weight (kg)',
          data: <?= json_encode(array_map(fn($r) => $r['weight_kg'] !== null ? (float) $r['weight_kg'] : null, $chart)) ?>,
          borderColor: '#14B8A6',
          backgroundColor: 'rgba(20,184,166,.12)',
          pointBackgroundColor: '#F4B942',
          tension: .35,
          fill: true,
          spanGaps: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          x: {
            grid: {
              display: false
            }
          }
        }
      },
    });
  }
</script>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>