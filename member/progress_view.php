<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Member');

$pageTitle = 'Progress Details';
$member = getMemberByUser($pdo, currentUser()['id']);
$progressId = (int) ($_GET['id'] ?? $_POST['progress_id'] ?? 0);
$record = one(
  $pdo,
  'SELECT * FROM progress_records WHERE progress_id = ? AND member_id = ?',
  [$progressId, (int) $member['member_id']],
);

if (!$record) {
  http_response_code(404);
  die('Progress record not found.');
}

// POST handling: only run this block when a form has been submitted.
//  Run this block only when the form is submitted with POST.
if (isPost()) {
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();
  $type = $_POST['photo_type'] ?? '';
  if (!in_array($type, ['Front', 'Side', 'Back'], true)) {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('danger', 'Invalid photo type.');
    redirect('member/progress_view.php?id=' . $progressId);
  }

  if (!isset($_FILES['progress_photo'])) {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('danger', 'Choose an image to upload.');
    redirect('member/progress_view.php?id=' . $progressId);
  }

  try {
    $newName = saveUploadedImage($_FILES['progress_photo'], UPLOAD_DIR, strtolower($type) . '-');
    $old = one(
      $pdo,
      "SELECT * FROM progress_photos WHERE progress_id = ? AND photo_type = ? AND status = 'Visible' ORDER BY photo_id DESC LIMIT 1",
      [$progressId, $type],
    );

    //  group related SQL changes so they either all succeed or all roll back.
    //  Start a database transaction so related changes succeed or fail together.
    $pdo->beginTransaction();
    try {
      if ($old) {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare('DELETE FROM progress_photos WHERE photo_id = ?')->execute([$old['photo_id']]);
      }
      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare(
        "INSERT INTO progress_photos(progress_id, photo_path, photo_type, status) VALUES(?, ?, ?, 'Visible')",
      )->execute([$progressId, $newName, $type]);
      //  Save all database changes made inside the transaction.
      $pdo->commit();
      if ($old) {
        removeUploadFile(UPLOAD_DIR, $old['photo_path']);
      }
      //  Save a one-time message so the next page can tell the user what happened.
      flash('success', $type . ' progress photo saved.');
    } catch (Throwable $error) {
      //  Undo the transaction if an error happens before completion.
      $pdo->rollBack();
      removeUploadFile(UPLOAD_DIR, $newName);
      throw $error;
    }
  } catch (Throwable $error) {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('danger', 'Photo upload failed: ' . $error->getMessage());
  }

  //  Redirect the browser after this action to avoid repeating the same request.
  redirect('member/progress_view.php?id=' . $progressId);
}

$photos = all(
  $pdo,
  "SELECT * FROM progress_photos WHERE progress_id = ? AND status = 'Visible' ORDER BY FIELD(photo_type, 'Front', 'Side', 'Back'), photo_id DESC",
  [$progressId],
);
$photoByType = [];
foreach ($photos as $photo) {
  $photoByType[$photo['photo_type']] ??= $photo;
}

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div uses flexbox to align the child elements in this area. -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <!-- This div groups related HTML content so the page structure is easier to manage. -->
  <div>
    <small class="text-muted">Progress check-in</small>
    <h2 class="mb-0"><?= shortDate($record['record_date']) ?></h2>
  </div>
  <a class="btn btn-outline-secondary rounded-pill px-4" href="progress.php"><i class="bi bi-arrow-left me-2"></i>Back to progress</a>
</div>

<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4 mb-4">
  <?php foreach ([['Weight', $record['weight_kg'] ? $record['weight_kg'] . ' kg' : '—', 'bi-speedometer2'], ['Height', $record['height_cm'] ? $record['height_cm'] . ' cm' : '—', 'bi-rulers'], ['BMI', $record['bmi'] ?: '—', 'bi-heart-pulse'], ['Waist', $record['waist_cm'] ? $record['waist_cm'] . ' cm' : '—', 'bi-arrows-collapse']] as $metric): ?>
    <!-- This div creates a responsive Bootstrap column inside the current row. -->
    <div class="col-6 col-lg-3">
      <!-- This div groups progress-related information or controls on this page. -->
      <div class="stat-card progress-detail-stat"><i class="bi <?= e($metric[2]) ?>"></i><!-- This div shows the main value for this summary item. -->
        <div class="value"><?= e((string) $metric[1]) ?></div><!-- This div shows the small label that explains the value above it. -->
        <div class="meta"><?= e($metric[0]) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- This div groups related page content using the “panel mb-4” layout style. -->
<div class="panel mb-4">
  <!-- This div groups related page content using the “panel-title” layout style. -->
  <div class="panel-title">
    <!-- This div groups related HTML content so the page structure is easier to manage. -->
    <div><small class="text-muted">Private image gallery</small>
      <h2>Front, side & back photos</h2>
    </div>
    <span class="analytics-chip"><i class="bi bi-shield-lock me-1"></i>Protected</span>
  </div>

  <!-- This div groups progress-related information or controls on this page. -->
  <div class="progress-private-gallery">
    <?php foreach (['Front', 'Side', 'Back'] as $type): ?>
      <?php $photo = $photoByType[$type] ?? null; ?>
      <article class="progress-photo-card">
        <!-- This div holds one protected member progress photo area. -->
        <div class="progress-photo-frame">
          <?php if ($photo): ?>
            <img src="<?= url('progress_image.php?photo=' . (int) $photo['photo_id']) ?>" alt="<?= e($type) ?> progress photo">
          <?php else: ?>
            <!-- This div holds one protected member progress photo area. -->
            <div class="progress-photo-placeholder"><i class="bi bi-image"></i><span>No <?= e(strtolower($type)) ?> photo</span></div>
          <?php endif; ?>
        </div>
        <!-- This div groups related page content using the “p-3” layout style. -->
        <div class="p-3">
          <!-- This div uses flexbox to align the child elements in this area. -->
          <div class="d-flex justify-content-between align-items-center mb-2"><strong><?= e($type) ?></strong><?php if ($photo): ?><span class="badge text-bg-light rounded-pill">Saved</span><?php endif; ?></div>
          <!-- This form collects user input and submits this form using the POST method. -->
          <form method="post" enctype="multipart/form-data" class="d-grid gap-2">
            <?= csrfField() ?>
            <input type="hidden" name="progress_id" value="<?= $progressId ?>">
            <input type="hidden" name="photo_type" value="<?= e($type) ?>">
            <input class="form-control form-control-sm" type="file" name="progress_photo" accept="image/jpeg,image/png,image/webp" required>
            <button class="btn btn-sm btn-dark rounded-pill"><?= $photo ? 'Replace photo' : 'Add photo' ?></button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!empty($record['notes'])): ?>
  <!-- This div groups related page content using the “panel” layout style. -->
  <div class="panel"><!-- This div holds the heading for this dashboard panel. -->
    <div class="panel-title">
      <h2>Notes</h2>
    </div>
    <p class="mb-0 text-muted"><?= nl2br(e($record['notes'])) ?></p>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>