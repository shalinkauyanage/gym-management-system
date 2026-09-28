<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Member');
$pageTitle = 'Member Overview';
$member = getMemberByUser($pdo, currentUser()['id']);

if (!$member) {
  die('Member profile missing.');
}

$memberId = (int) $member['member_id'];
$membership = one(
  $pdo,
  "SELECT ms.*, p.package_name, p.fee
     FROM memberships ms
     JOIN packages p ON p.package_id = ms.package_id
     WHERE ms.member_id = ?
     ORDER BY ms.end_date DESC LIMIT 1",
  [$memberId],
);

$nameExpr = columnExists($pdo, 'trainers', 'display_name')
  ? "COALESCE(NULLIF(t.display_name, ''), u.username)"
  : 'u.username';
$coach = tableExists($pdo, 'trainer_assignments')
  ? one(
    $pdo,
    "SELECT {$nameExpr} AS coach_name, t.average_rating, t.specialization
         FROM trainer_assignments a
         JOIN trainers t ON t.trainer_id = a.trainer_id
         JOIN users u ON u.user_id = t.user_id
         WHERE a.member_id = ? AND a.status = 'Active'
         ORDER BY a.assignment_id DESC LIMIT 1",
    [$memberId],
  )
  : null;

$workout = one(
  $pdo,
  "SELECT w.*, {$nameExpr} AS coach_name
     FROM workout_plans w
     JOIN trainers t ON t.trainer_id = w.trainer_id
     JOIN users u ON u.user_id = t.user_id
     WHERE w.member_id = ? AND w.status = 'Active'
     ORDER BY w.start_date DESC LIMIT 1",
  [$memberId],
);
$meal = one(
  $pdo,
  "SELECT mp.* FROM meal_plan mp
     WHERE mp.member_id = ? AND mp.status = 'Active'
     ORDER BY mp.start_date DESC LIMIT 1",
  [$memberId],
);
$progress = all($pdo, 'SELECT * FROM progress_records WHERE member_id = ? ORDER BY record_date', [$memberId]);
$latest = $progress ? $progress[array_key_last($progress)] : null;
$unread = (int) scalar($pdo, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [currentUser()['id']]);
$completedPayments = (int) scalar($pdo, "SELECT COUNT(*) FROM payments WHERE member_id = ? AND status = 'Completed'", [$memberId]);

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div groups dashboard content for the current user role. -->
<div class="dashboard-hero-card mb-4">
  <!-- This div groups related HTML content so the page structure is easier to manage. -->
  <div>
    <span class="dashboard-kicker">Member workspace</span>
    <h2>Your membership, coaching and progress in one place.</h2>
    <p>Review the plan your coach created, follow progress records, check payments and stay on top of membership updates.</p>
  </div>
  <!-- This div groups dashboard content for the current user role. -->
  <div class="dashboard-hero-score">
    <small>Membership</small>
    <strong><?= e($membership['status'] ?? 'None') ?></strong>
    <span><?= e($membership['package_name'] ?? 'No package') ?></span>
  </div>
</div>

<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4 mb-4">
  <?php
  $cards = [
    ['bi-calendar-check', 'Package', $membership['package_name'] ?? 'Not assigned', $membership ? shortDate($membership['end_date']) . ' expiry' : 'No active record'],
    ['bi-person-workspace', 'Coach', $coach['coach_name'] ?? ($workout['coach_name'] ?? 'Not assigned'), 'Training + nutrition support'],
    ['bi-graph-up-arrow', 'Latest weight', $latest && $latest['weight_kg'] ? $latest['weight_kg'] . ' kg' : '—', $latest ? shortDate($latest['record_date']) : 'No progress yet'],
    ['bi-bell', 'Notifications', $unread, $completedPayments . ' verified payment(s)'],
  ];
  ?>
  <?php foreach ($cards as $card): ?>
    <!-- This div creates a responsive Bootstrap column inside the current row. -->
    <div class="col-sm-6 col-xl-3">
      <!-- This div creates a bordered content card for related information. -->
      <div class="stat-card analytics-stat-card">
        <!-- This div shows one summary value and its label. -->
        <div class="stat-icon"><i class="bi <?= e($card[0]) ?>"></i></div>
        <!-- This div shows one summary value and its label. -->
        <div class="value mt-3 member-stat-value"><?= e((string) $card[2]) ?></div>
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
        <div><small class="text-muted">Your progress</small>
          <h2>Weight history</h2>
        </div>
        <a href="progress.php" class="small text-decoration-none">Add progress</a>
      </div>
      <!-- This div groups dashboard content for the current user role. -->
      <div class="dashboard-chart-lg"><canvas id="memberProgress"></canvas></div>
    </div>
  </div>
  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-4">
    <!-- This div groups related page content using the “panel” layout style. -->
    <div class="panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title">
        <h2>Coaching snapshot</h2>
      </div>
      <!-- This div creates a bordered content card for related information. -->
      <div class="quick-card mb-2"><i class="bi bi-activity"></i><!-- This div groups related HTML content on this page. -->
        <div><strong><?= e($workout['fitness_goal'] ?? 'No active workout') ?></strong><small class="d-block text-muted">Workout plan</small></div>
      </div>
      <!-- This div creates a bordered content card for related information. -->
      <div class="quick-card mb-2"><i class="bi bi-egg-fried"></i><!-- This div groups related HTML content on this page. -->
        <div><strong><?= e($meal['plan_name'] ?? 'No active meal plan') ?></strong><small class="d-block text-muted">Nutrition plan</small></div>
      </div>
      <!-- This div creates a bordered content card for related information. -->
      <div class="quick-card"><i class="bi bi-star"></i><!-- This div groups related HTML content on this page. -->
        <div><strong><?= isset($coach['average_rating']) ? number_format((float) $coach['average_rating'], 1) . '/5' : '—' ?></strong><small class="d-block text-muted">Coach average rating</small></div>
      </div>
      <a class="btn btn-dark rounded-pill w-100 mt-3" href="rating.php">Rate your coach</a>
    </div>
  </div>
</div>

<script>
  const memberProgress = document.getElementById('memberProgress');
  if (memberProgress && window.Chart) {
    new Chart(memberProgress, {
      type: 'line',
      data: {
        labels: <?= json_encode(array_map(static fn($row) => shortDate($row['record_date']), $progress)) ?>,
        datasets: [{
          label: 'Weight (kg)',
          data: <?= json_encode(array_map(static fn($row) => $row['weight_kg'] === null ? null : (float) $row['weight_kg'], $progress)) ?>,
          borderColor: '#8B5CF6',
          backgroundColor: 'rgba(139,92,246,.14)',
          fill: true,
          tension: .4,
          pointRadius: 4,
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
            grid: {
              color: 'rgba(65,65,65,.12)'
            }
          }
        },
      },
    });
  }
</script>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>