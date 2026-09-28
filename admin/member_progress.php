<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Admin');

$pageTitle = 'Member Progress';
$memberId = (int) ($_GET['id'] ?? 0);
$member = one(
  $pdo,
  'SELECT m.*, u.email FROM members m JOIN users u ON u.user_id = m.user_id WHERE m.member_id = ?',
  [$memberId],
);
if (!$member) {
  http_response_code(404);
  die('Member not found.');
}

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
$age = ageFromDateOfBirth($member['date_of_birth']);
$latest = $records[0] ?? null;

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div uses flexbox to align the child elements in this area. -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <!-- This div groups related HTML content so the page structure is easier to manage. -->
  <div><small class="text-muted">Member progress report</small>
    <h2 class="mb-1"><?= e($member['full_name']) ?></h2><span class="text-muted"><?= e($member['member_number']) ?><?= $age !== null ? ' · ' . $age . ' years' : '' ?></span>
  </div>
  <a href="members.php" class="btn btn-outline-secondary rounded-pill px-4"><i class="bi bi-arrow-left me-2"></i>Back to members</a>
</div>

<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-3 mb-4">
  <?php foreach ([['Current weight', $latest && $latest['weight_kg'] ? $latest['weight_kg'] . ' kg' : '—', 'bi-speedometer2'], ['Current height', $latest && $latest['height_cm'] ? $latest['height_cm'] . ' cm' : '—', 'bi-rulers'], ['Current BMI', $latest && $latest['bmi'] ? $latest['bmi'] : '—', 'bi-heart-pulse'], ['Progress records', count($records), 'bi-clipboard-data']] as $metric): ?>
    <!-- This div creates a responsive Bootstrap column inside the current row. -->
    <div class="col-6 col-xl-3"><!-- This div shows one dashboard summary value. -->
      <div class="stat-card"><i class="bi <?= e($metric[2]) ?>"></i><!-- This div shows the main value for this summary item. -->
        <div class="value"><?= e((string) $metric[1]) ?></div><!-- This div shows the small label that explains the value above it. -->
        <div class="meta"><?= e($metric[0]) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4">
  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-7">
    <!-- This div groups related page content using the “panel mb-4” layout style. -->
    <div class="panel mb-4"><!-- This div holds the heading for this dashboard panel. -->
      <div class="panel-title"><!-- This div groups related HTML content on this page. -->
        <div><small class="text-muted">Recorded measurements</small>
          <h2>Weight history</h2>
        </div>
      </div><!-- This div groups related HTML content on this page. -->
      <div style="height:300px"><canvas id="adminMemberProgressChart"></canvas></div>
    </div>
    <!-- This div groups related page content using the “panel” layout style. -->
    <div class="panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title">
        <h2>Progress history</h2><span class="badge text-bg-light rounded-pill"><?= count($records) ?> records</span>
      </div>
      <!-- This div allows this table to scroll safely on smaller screens. -->
      <div class="table-responsive">
        <table class="table table-modern align-middle">
          <thead>
            <tr>
              <th>Date</th>
              <th>Weight</th>
              <th>Height</th>
              <th>BMI</th>
              <th>Waist</th>
              <th>Photos</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $record): ?><tr>
                <td><?= shortDate($record['record_date']) ?></td>
                <td><?= $record['weight_kg'] ? e($record['weight_kg']) . ' kg' : '—' ?></td>
                <td><?= $record['height_cm'] ? e($record['height_cm']) . ' cm' : '—' ?></td>
                <td><?= $record['bmi'] ?: '—' ?></td>
                <td><?= $record['waist_cm'] ? e($record['waist_cm']) . ' cm' : '—' ?></td>
                <td><?= (int) $record['photo_count'] ?></td>
              </tr><?php endforeach; ?>
            <?php if (!$records): ?><tr>
                <td colspan="6" class="text-center text-muted py-5">No progress records yet.</td>
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
      <div class="panel-title"><!-- This div groups related HTML content on this page. -->
        <div><small class="text-muted">Protected media</small>
          <h2>Progress images</h2>
        </div>
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
  const adminProgress = document.getElementById('adminMemberProgressChart');
  if (adminProgress && window.Chart) {
    new Chart(adminProgress, {
      type: 'line',
      data: {
        labels: <?= json_encode(array_map(fn($r) => date('d M', strtotime($r['record_date'])), $chart)) ?>,
        datasets: [{
          label: 'Weight (kg)',
          data: <?= json_encode(array_map(fn($r) => $r['weight_kg'] !== null ? (float) $r['weight_kg'] : null, $chart)) ?>,
          borderColor: '#3B82F6',
          backgroundColor: 'rgba(59,130,246,.12)',
          pointBackgroundColor: '#FF6347',
          tension: .35,
          fill: true,
          spanGaps: true
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
          }
        }
      },
    });
  }
</script>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>