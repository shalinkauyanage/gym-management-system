<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Member');

$pageTitle = 'My Progress';
$member = getMemberByUser($pdo, currentUser()['id']);
$memberId = (int) $member['member_id'];

// POST handling: only run this block when a form has been submitted.
//  Run this block only when the form is submitted with POST.
if (isPost()) {
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();
  $mode = $_POST['mode'] ?? 'create';

  if ($mode === 'delete') {
    $progressId = (int) ($_POST['progress_id'] ?? 0);
    $record = one($pdo, 'SELECT * FROM progress_records WHERE progress_id = ? AND member_id = ?', [$progressId, $memberId]);
    if (!$record) {
      //  Save a one-time message so the next page can tell the user what happened.
      flash('danger', 'Progress record not found.');
      redirect('member/progress.php');
    }

    $photos = all($pdo, 'SELECT photo_path FROM progress_photos WHERE progress_id = ?', [$progressId]);
    //  group related SQL changes so they either all succeed or all roll back.
    //  Start a database transaction so related changes succeed or fail together.
    $pdo->beginTransaction();
    try {
      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare('DELETE FROM progress_records WHERE progress_id = ? AND member_id = ?')->execute([$progressId, $memberId]);
      $pdo->commit();
      foreach ($photos as $photo) {
        removeUploadFile(UPLOAD_DIR, $photo['photo_path']);
      }
      //  Save a one-time message so the next page can tell the user what happened.
      flash('success', 'Progress record and its photos were deleted.');
    } catch (Throwable $error) {
      //  Undo the transaction if an error happens before completion.
      $pdo->rollBack();
      flash('danger', 'The progress record could not be deleted.');
    }
    //  Redirect the browser after this action to avoid repeating the same request.
    redirect('member/progress.php');
  }

  $weight = $_POST['weight_kg'] !== '' ? (float) $_POST['weight_kg'] : null;
  $height = $_POST['height_cm'] !== '' ? (float) $_POST['height_cm'] : null;
  $waist = $_POST['waist_cm'] !== '' ? (float) $_POST['waist_cm'] : null;
  $notes = trim($_POST['notes'] ?? '');
  $date = $_POST['record_date'] ?: date('Y-m-d');
  $bmi = ($weight && $height) ? round($weight / (($height / 100) ** 2), 2) : null;

  //  Prepare and run this SQL statement using PDO.
  $pdo->prepare(
    'INSERT INTO progress_records(member_id, record_date, weight_kg, height_cm, bmi, waist_cm, notes) VALUES(?, ?, ?, ?, ?, ?, ?)',
  )->execute([$memberId, $date, $weight, $height, $bmi, $waist, $notes]);
  $progressId = (int) $pdo->lastInsertId();

  $photoInputs = [
    'photo_front' => 'Front',
    'photo_side' => 'Side',
    'photo_back' => 'Back',
  ];
  $photoWarnings = [];

  foreach ($photoInputs as $input => $type) {
    if (!isset($_FILES[$input]) || ($_FILES[$input]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      continue;
    }

    try {
      $name = saveUploadedImage($_FILES[$input], UPLOAD_DIR, strtolower($type) . '-');
      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare(
        "INSERT INTO progress_photos(progress_id, photo_path, photo_type, status) VALUES(?, ?, ?, 'Visible')",
      )->execute([$progressId, $name, $type]);
    } catch (RuntimeException $error) {
      $photoWarnings[] = $type . ': ' . $error->getMessage();
    }
  }

  if ($photoWarnings) {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('warning', 'Progress saved. Some photos were skipped: ' . implode(' ', $photoWarnings));
  } else {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('success', 'Progress record added successfully.');
  }
  //  Redirect the browser after this action to avoid repeating the same request.
  redirect('member/progress.php');
}

$rows = all(
  $pdo,
  "SELECT p.*,
        (SELECT COUNT(*) FROM progress_photos ph WHERE ph.progress_id = p.progress_id AND ph.status = 'Visible') AS photo_count
     FROM progress_records p
     WHERE p.member_id = ?
     ORDER BY p.record_date DESC, p.progress_id DESC",
  [$memberId],
);
$chart = array_reverse($rows);

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4">
  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-5">
    <!-- This div groups progress-related information or controls on this page. -->
    <div class="panel progress-entry-panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title">
        <!-- This div groups related HTML content so the page structure is easier to manage. -->
        <div>
          <small class="text-muted">Personal progress log</small>
          <h2>Add progress record</h2>
        </div>
        <span class="analytics-chip"><i class="bi bi-images me-1"></i>3 photo angles</span>
      </div>

      <!-- This form collects user input and submits this form using the POST method. -->
      <form method="post" enctype="multipart/form-data" class="row g-3" data-loading-form>
        <?= csrfField() ?>
        <input type="hidden" name="mode" value="create">

        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-12">
          <label class="form-label">Record date</label>
          <input type="date" class="form-control" name="record_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-6">
          <label class="form-label">Weight (kg)</label>
          <input class="form-control" type="number" step="0.01" min="1" name="weight_kg" placeholder="e.g. 72.5">
        </div>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-6">
          <label class="form-label">Height (cm)</label>
          <input class="form-control" type="number" step="0.01" min="50" name="height_cm" placeholder="e.g. 175">
        </div>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-6">
          <label class="form-label">Waist (cm)</label>
          <input class="form-control" type="number" step="0.01" min="1" name="waist_cm">
        </div>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-6 d-flex align-items-end">
          <!-- This div holds one protected member progress photo area. -->
          <div class="progress-photo-note w-100">
            <i class="bi bi-shield-lock me-2"></i>Photos stay behind login and role checks.
          </div>
        </div>

        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-12">
          <label class="form-label">Progress photos <small class="text-muted">(JPG/PNG/WEBP, max 5 MB each)</small></label>
          <!-- This div groups progress-related information or controls on this page. -->
          <div class="progress-upload-stack">
            <?php foreach ([['photo_front', 'Front photo', 'Face the camera directly', 'bi-person-standing'], ['photo_side', 'Side photo', 'Stand side-on to the camera', 'bi-person-standing-dress'], ['photo_back', 'Back photo', 'Face away from the camera', 'bi-person-standing']] as $photoField): ?>
              <label class="progress-upload-box progress-upload-row">
                <span class="progress-upload-icon"><i class="bi <?= e($photoField[3]) ?>"></i></span>
                <span class="progress-upload-copy"><strong><?= e($photoField[1]) ?></strong><small><?= e($photoField[2]) ?></small></span>
                <span class="progress-upload-action">Choose image <i class="bi bi-upload ms-1"></i></span>
                <input type="file" name="<?= e($photoField[0]) ?>" accept="image/jpeg,image/png,image/webp">
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-12">
          <label class="form-label">Notes</label>
          <textarea class="form-control" name="notes" rows="3" placeholder="Add a short note about this check-in"></textarea>
        </div>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-12">
          <button class="btn btn-dark rounded-pill px-4"><i class="bi bi-plus-circle me-2"></i>Save progress</button>
        </div>
      </form>
    </div>
  </div>

  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-7">
    <!-- This div groups related page content using the “panel mb-4” layout style. -->
    <div class="panel mb-4">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title">
        <!-- This div groups related HTML content so the page structure is easier to manage. -->
        <div><small class="text-muted">Measurement history</small>
          <h2>Weight trend</h2>
        </div>
      </div>
      <!-- This div groups related HTML content so the page structure is easier to manage. -->
      <div style="height: 290px"><canvas id="progressChart"></canvas></div>
    </div>

    <!-- This div groups related page content using the “panel” layout style. -->
    <div class="panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title">
        <!-- This div groups related HTML content so the page structure is easier to manage. -->
        <div><small class="text-muted">Your saved check-ins</small>
          <h2>Progress history</h2>
        </div>
        <span class="badge text-bg-light rounded-pill"><?= count($rows) ?> records</span>
      </div>

      <?php if ($rows): ?>
        <!-- This div allows this table to scroll safely on smaller screens. -->
        <div class="table-responsive">
          <!-- This table displays database records in rows and columns for easy reading. -->
          <table class="table table-modern align-middle progress-history-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Weight</th>
                <th>Height</th>
                <th>BMI</th>
                <th>Waist</th>
                <th>Photos</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td><strong><?= shortDate($row['record_date']) ?></strong></td>
                  <td><?= $row['weight_kg'] ? e($row['weight_kg']) . ' kg' : '—' ?></td>
                  <td><?= $row['height_cm'] ? e($row['height_cm']) . ' cm' : '—' ?></td>
                  <td><?= $row['bmi'] ? e($row['bmi']) : '—' ?></td>
                  <td><?= $row['waist_cm'] ? e($row['waist_cm']) . ' cm' : '—' ?></td>
                  <td><span class="badge text-bg-light rounded-pill"><?= (int) $row['photo_count'] ?>/3</span></td>
                  <td class="text-end">
                    <!-- This div groups the main content for this part of the page. -->
                    <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                      <a class="btn btn-sm btn-outline-dark rounded-pill" href="progress_view.php?id=<?= (int) $row['progress_id'] ?>"><i class="bi bi-images me-1"></i>View</a>
                      <!-- This form collects user input and submits this form using the POST method. -->
                      <form method="post" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="mode" value="delete">
                        <input type="hidden" name="progress_id" value="<?= (int) $row['progress_id'] ?>">
                        <button class="btn btn-sm btn-outline-danger rounded-pill" data-confirm="Delete this progress record and all of its photos?"><i class="bi bi-trash3 me-1"></i>Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <!-- This div shows one summary value and its label. -->
        <div class="empty-state"><i class="bi bi-graph-up-arrow"></i>No progress records yet.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  const progressCanvas = document.getElementById('progressChart');
  if (progressCanvas && window.Chart) {
    new Chart(progressCanvas, {
      type: 'line',
      data: {
        labels: <?= json_encode(array_map(fn($row) => date('d M', strtotime($row['record_date'])), $chart)) ?>,
        datasets: [{
          label: 'Weight (kg)',
          data: <?= json_encode(array_map(fn($row) => $row['weight_kg'] !== null ? (float) $row['weight_kg'] : null, $chart)) ?>,
          borderColor: '#8B5CF6',
          backgroundColor: 'rgba(139, 92, 246, .14)',
          pointBackgroundColor: '#FF6347',
          pointRadius: 4,
          fill: true,
          tension: .35,
          spanGaps: true,
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
        },
      },
    });
  }
</script>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>