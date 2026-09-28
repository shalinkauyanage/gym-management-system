<?php

/**
 * --------------------------------------------------------------------------
 * STUDENT VIVA EXPLANATION (simple English)
 * File: coach/dashboard.php
 * Main user/area: Coach
 * Simple purpose: This file handles the Dashboard part of PowerFit.
 * How to explain: PHP prepares/validates the data first, then the HTML shows
 * the result to the user. Database work is done with PDO prepared statements.
 * --------------------------------------------------------------------------
 */


/**
 * ============================================================================
 * POWERFIT VIVA FILE GUIDE
 * File: coach/dashboard.php
 * Purpose: Builds the Coach overview for assigned members, plans, ratings and progress information.
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

$pageTitle = 'Coach Overview';
$trainer = getTrainerByUser($pdo, currentUser()['id']);
$adviser = getAdviserByUser($pdo, currentUser()['id']);

if (!$trainer || !$adviser) {
  die('Coach profile is incomplete. Ask an administrator to update this staff account.');
}

$trainerId = (int) $trainer['trainer_id'];
$adviserId = (int) $adviser['adviser_id'];

$assignedMembers = (int) scalar(
  $pdo,
  "SELECT COUNT(DISTINCT member_id)
     FROM (
       SELECT member_id FROM trainer_assignments WHERE trainer_id = ? AND status = 'Active'
       UNION
       SELECT member_id FROM nutrition_assignments WHERE adviser_id = ? AND status = 'Active'
     ) assigned",
  [$trainerId, $adviserId],
);

$activeWorkouts = (int) scalar(
  $pdo,
  "SELECT COUNT(*) FROM workout_plans WHERE trainer_id = ? AND status = 'Active'",
  [$trainerId],
);
$activeMeals = (int) scalar(
  $pdo,
  "SELECT COUNT(*) FROM meal_plan WHERE adviser_id = ? AND status = 'Active'",
  [$adviserId],
);
$averageRating = (float) ($trainer['average_rating'] ?? 0);

$ratingDistribution = all(
  $pdo,
  "SELECT score, COUNT(*) total
     FROM trainer_ratings
     WHERE trainer_id = ? AND status = 'Visible'
     GROUP BY score
     ORDER BY score",
  [$trainerId],
);
$ratingMap = array_fill(1, 5, 0);
foreach ($ratingDistribution as $row) {
  $ratingMap[(int) $row['score']] = (int) $row['total'];
}

$planStatus = [
  'Active workout plans' => $activeWorkouts,
  'Active meal plans' => $activeMeals,
  'Completed workouts' => (int) scalar(
    $pdo,
    "SELECT COUNT(*) FROM workout_plans WHERE trainer_id = ? AND status = 'Completed'",
    [$trainerId],
  ),
  'Completed meal plans' => (int) scalar(
    $pdo,
    "SELECT COUNT(*) FROM meal_plan WHERE adviser_id = ? AND status = 'Completed'",
    [$adviserId],
  ),
];

$progressTrend = all(
  $pdo,
  "SELECT DATE_FORMAT(p.record_date, '%b') label, COUNT(*) total
     FROM progress_records p
     WHERE p.member_id IN (
       SELECT member_id FROM trainer_assignments WHERE trainer_id = ? AND status = 'Active'
       UNION
       SELECT member_id FROM nutrition_assignments WHERE adviser_id = ? AND status = 'Active'
     )
       AND p.record_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY YEAR(p.record_date), MONTH(p.record_date)
     ORDER BY MIN(p.record_date)",
  [$trainerId, $adviserId],
);

$recentMembers = all(
  $pdo,
  "SELECT DISTINCT m.member_id, m.member_number, m.full_name, m.fitness_goal, m.status,
        p.record_date, p.weight_kg, p.bmi
     FROM members m
     LEFT JOIN progress_records p
       ON p.progress_id = (
         SELECT p2.progress_id
         FROM progress_records p2
         WHERE p2.member_id = m.member_id
         ORDER BY p2.record_date DESC
         LIMIT 1
       )
     WHERE m.member_id IN (
       SELECT member_id FROM trainer_assignments WHERE trainer_id = ? AND status = 'Active'
       UNION
       SELECT member_id FROM nutrition_assignments WHERE adviser_id = ? AND status = 'Active'
     )
     ORDER BY m.full_name
     LIMIT 6",
  [$trainerId, $adviserId],
);

$ratings = all(
  $pdo,
  "SELECT r.score, r.comment, r.rating_date
     FROM trainer_ratings r
     WHERE r.trainer_id = ? AND r.status = 'Visible'
     ORDER BY r.rating_date DESC, r.rating_id DESC
     LIMIT 4",
  [$trainerId],
);

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div groups dashboard content for the current user role. -->
<div class="dashboard-hero-card mb-4">
  <!-- This div groups related HTML content so the page structure is easier to manage. -->
  <div>
    <span class="dashboard-kicker">Unified coaching workspace</span>
    <h2>Training and nutrition, managed together.</h2>
    <p>Review assigned members, workout plans, meal plans, progress activity and feedback from one dashboard.</p>
  </div>
  <!-- This div groups dashboard content for the current user role. -->
  <div class="dashboard-hero-score">
    <small>Average rating</small>
    <strong><?= number_format($averageRating, 1) ?>/5</strong>
    <span><i class="bi bi-star-fill"></i> Member feedback</span>
  </div>
</div>

<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4 mb-4">
  <?php
  $cards = [
    ['bi-people', 'Assigned members', $assignedMembers, 'Current coach relationships'],
    ['bi-activity', 'Active workouts', $activeWorkouts, 'Workout plans in progress'],
    ['bi-egg-fried', 'Active meal plans', $activeMeals, 'Nutrition plans in progress'],
    ['bi-star', 'Coach rating', number_format($averageRating, 1), 'Average member score'],
  ];
  ?>

  <?php foreach ($cards as $card): ?>
    <!-- This div creates a responsive Bootstrap column inside the current row. -->
    <div class="col-sm-6 col-xl-3">
      <!-- This div creates a bordered content card for related information. -->
      <div class="stat-card analytics-stat-card">
        <!-- This div uses flexbox to align the child elements in this area. -->
        <div class="d-flex justify-content-between align-items-start">
          <!-- This div shows one summary value and its label. -->
          <div class="stat-icon"><i class="bi <?= e($card[0]) ?>"></i></div>
          <span class="analytics-chip">Live</span>
        </div>
        <!-- This div groups related page content using the “value mt-3” layout style. -->
        <div class="value mt-3"><?= e((string) $card[2]) ?></div>
        <!-- This div groups related page content using the “meta” layout style. -->
        <div class="meta"><?= e($card[1]) ?></div>
        <small class="text-muted d-block mt-2"><?= e($card[3]) ?></small>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4 mb-4">
  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-8">
    <!-- This div groups related page content using the “panel analytics-panel” layout style. -->
    <div class="panel analytics-panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title">
        <!-- This div groups related HTML content so the page structure is easier to manage. -->
        <div>
          <small class="text-muted">Member activity</small>
          <h2>Progress records over time</h2>
        </div>
        <span class="analytics-chip">6 months</span>
      </div>
      <!-- This div groups dashboard content for the current user role. -->
      <div class="dashboard-chart-lg"><canvas id="coachProgressChart"></canvas></div>
    </div>
  </div>

  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-4">
    <!-- This div groups related page content using the “panel analytics-panel” layout style. -->
    <div class="panel analytics-panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title">
        <!-- This div groups related HTML content so the page structure is easier to manage. -->
        <div>
          <small class="text-muted">Plan mix</small>
          <h2>Coaching workload</h2>
        </div>
      </div>
      <!-- This div groups dashboard content for the current user role. -->
      <div class="dashboard-chart-md"><canvas id="coachPlanChart"></canvas></div>
      <!-- This div holds a Chart.js canvas or chart-related information. -->
      <div class="chart-legend-list mt-3">
        <?php foreach ($planStatus as $label => $value): ?>
          <!-- This div groups related HTML content so the page structure is easier to manage. -->
          <div><span><?= e($label) ?></span><strong><?= (int) $value ?></strong></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4">
  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-8">
    <!-- This div groups related page content using the “panel” layout style. -->
    <div class="panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title">
        <!-- This div groups related HTML content so the page structure is easier to manage. -->
        <div>
          <small class="text-muted">Assigned members</small>
          <h2>Latest member overview</h2>
        </div>
        <a href="members.php" class="small text-decoration-none">View all</a>
      </div>
      <!-- This div allows this table to scroll safely on smaller screens. -->
      <div class="table-responsive">
        <!-- This table displays database records in rows and columns for easy reading. -->
        <table class="table table-modern">
          <thead>
            <tr>
              <th>Member</th>
              <th>Goal</th>
              <th>Latest weight</th>
              <th>BMI</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentMembers as $member): ?>
              <tr>
                <td><strong><?= e($member['full_name']) ?></strong><small class="d-block text-muted"><?= e($member['member_number']) ?></small></td>
                <td><?= e($member['fitness_goal'] ?? '—') ?></td>
                <td><?= $member['weight_kg'] ? e($member['weight_kg']) . ' kg' : '—' ?></td>
                <td><?= $member['bmi'] ? e($member['bmi']) : '—' ?></td>
                <td><?= statusBadge($member['status']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$recentMembers): ?>
              <tr>
                <td colspan="5" class="text-center text-muted py-4">No members assigned yet.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-4">
    <!-- This div groups related page content using the “panel analytics-panel” layout style. -->
    <div class="panel analytics-panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title">
        <h2>Rating distribution</h2>
      </div>
      <!-- This div groups dashboard content for the current user role. -->
      <div class="dashboard-chart-sm"><canvas id="coachRatingChart"></canvas></div>
      <!-- This div groups related page content using the “mt-4” layout style. -->
      <div class="mt-4">
        <?php foreach ($ratings as $rating): ?>
          <!-- This div groups related page content using the “rating-note border-top” layout style. -->
          <div class="rating-note border-top pt-3 mt-3">
            <!-- This div groups related page content using the “rating-stars” layout style. -->
            <div class="rating-stars"><?= str_repeat('★', (int) $rating['score']) ?></div>
            <p class="small mb-1"><?= e($rating['comment'] ?: 'No comment') ?></p>
            <small class="text-muted"><?= shortDate($rating['rating_date']) ?></small>
          </div>
        <?php endforeach; ?>
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
        labels: <?= json_encode(array_column($progressTrend, 'label')) ?>,
        datasets: [{
          label: 'Progress updates',
          data: <?= json_encode(array_map('intval', array_column($progressTrend, 'total'))) ?>,
          borderColor: '#14B8A6',
          backgroundColor: 'rgba(20,184,166,.14)',
          fill: true,
          tension: .4,
          pointRadius: 4,
          pointBackgroundColor: '#14B8A6',
        }],
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
          },
          y: {
            beginAtZero: true,
            ticks: {
              precision: 0
            }
          }
        },
      },
    });
  }

  const coachPlan = document.getElementById('coachPlanChart');
  if (coachPlan && window.Chart) {
    new Chart(coachPlan, {
      type: 'doughnut',
      data: {
        labels: <?= json_encode(array_keys($planStatus)) ?>,
        datasets: [{
          data: <?= json_encode(array_values($planStatus)) ?>,
          backgroundColor: ['#14B8A6', '#F4B942', '#FF6347', '#8B5CF6'],
          borderWidth: 0
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
          legend: {
            display: false
          }
        }
      },
    });
  }

  const coachRating = document.getElementById('coachRatingChart');
  if (coachRating && window.Chart) {
    new Chart(coachRating, {
      type: 'bar',
      data: {
        labels: ['1★', '2★', '3★', '4★', '5★'],
        datasets: [{
          data: <?= json_encode(array_values($ratingMap)) ?>,
          backgroundColor: ['#FF6347', '#F59E0B', '#F4B942', '#14B8A6', '#3B82F6'],
          borderRadius: 8
        }],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          x: {
            beginAtZero: true,
            ticks: {
              precision: 0
            }
          },
          y: {
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