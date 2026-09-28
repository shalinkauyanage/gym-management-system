<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Admin');

$pageTitle = 'Packages';
$action = $_GET['action'] ?? 'list';
$id = (int) ($_GET['id'] ?? 0);
$hasImagePath = columnExists($pdo, 'packages', 'image_path');

// POST handling: only run this block when a form has been submitted.
//  Run this block only when the form is submitted with POST.
if (isPost()) {
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();
  $mode = $_POST['mode'] ?? 'save';
  $id = (int) ($_POST['package_id'] ?? 0);

  if ($mode === 'delete') {
    $package = one($pdo, 'SELECT * FROM packages WHERE package_id = ?', [$id]);
    $linkedMemberships = (int) scalar($pdo, 'SELECT COUNT(*) FROM memberships WHERE package_id = ?', [$id]);
    $linkedPayments = columnExists($pdo, 'payments', 'package_id')
      ? (int) scalar($pdo, 'SELECT COUNT(*) FROM payments WHERE package_id = ?', [$id])
      : 0;

    if ($linkedMemberships > 0 || $linkedPayments > 0) {
      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare("UPDATE packages SET status = 'Inactive' WHERE package_id = ?")->execute([$id]);
      flash('warning', 'This package has history, so it was archived instead of permanently deleted.');
    } else {
      //  Prepare and run this SQL statement using PDO.
      $pdo->prepare('DELETE FROM packages WHERE package_id = ?')->execute([$id]);
      if ($package && $hasImagePath) {
        removeUploadFile(PACKAGE_UPLOAD_DIR, $package['image_path'] ?? null);
      }
      //  Save a one-time message so the next page can tell the user what happened.
      flash('success', 'Package removed.');
    }
    //  Redirect the browser after this action to avoid repeating the same request.
    redirect('admin/packages.php');
  }

  $name = trim($_POST['package_name'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $fee = (float) ($_POST['fee'] ?? 0);
  $months = (int) ($_POST['duration_months'] ?? 1);
  $status = $_POST['status'] ?? 'Active';

  if ($name === '' || $fee <= 0 || $months < 1) {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('danger', 'Enter a package name, a valid fee and a duration of at least one month.');
    redirect('admin/packages.php?action=' . ($id ? 'edit&id=' . $id : 'new'));
  }

  $current = $id ? one($pdo, 'SELECT * FROM packages WHERE package_id = ?', [$id]) : null;
  $imagePath = $current['image_path'] ?? null;
  $newImage = null;

  if ($hasImagePath && isset($_FILES['package_image']) && ($_FILES['package_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    try {
      $newImage = saveUploadedImage($_FILES['package_image'], PACKAGE_UPLOAD_DIR, 'package-');
      $imagePath = $newImage;
    } catch (RuntimeException $error) {
      //  Save a one-time message so the next page can tell the user what happened.
      flash('danger', 'Package image: ' . $error->getMessage());
      redirect('admin/packages.php?action=' . ($id ? 'edit&id=' . $id : 'new'));
    }
  }

  if ($hasImagePath && !empty($_POST['remove_image'])) {
    $imagePath = null;
  }

  try {
    if ($id) {
      if ($hasImagePath) {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          'UPDATE packages SET package_name = ?, description = ?, fee = ?, duration_months = ?, status = ?, image_path = ? WHERE package_id = ?',
        )->execute([$name, $description, $fee, $months, $status, $imagePath, $id]);
      } else {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          'UPDATE packages SET package_name = ?, description = ?, fee = ?, duration_months = ?, status = ? WHERE package_id = ?',
        )->execute([$name, $description, $fee, $months, $status, $id]);
      }
      if ($hasImagePath && $current && ($newImage || !empty($_POST['remove_image'])) && !empty($current['image_path']) && $current['image_path'] !== $imagePath) {
        removeUploadFile(PACKAGE_UPLOAD_DIR, $current['image_path']);
      }
      //  Save a one-time message so the next page can tell the user what happened.
      flash('success', 'Package updated successfully.');
    } else {
      if ($hasImagePath) {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          'INSERT INTO packages(package_name, description, fee, duration_months, status, image_path) VALUES(?, ?, ?, ?, ?, ?)',
        )->execute([$name, $description, $fee, $months, $status, $imagePath]);
      } else {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          'INSERT INTO packages(package_name, description, fee, duration_months, status) VALUES(?, ?, ?, ?, ?)',
        )->execute([$name, $description, $fee, $months, $status]);
      }
      //  Save a one-time message so the next page can tell the user what happened.
      flash('success', 'Package created successfully.');
    }
  } catch (Throwable $error) {
    if ($newImage) {
      removeUploadFile(PACKAGE_UPLOAD_DIR, $newImage);
    }
    throw $error;
  }

  //  Redirect the browser after this action to avoid repeating the same request.
  redirect('admin/packages.php');
}

$package = $id ? one($pdo, 'SELECT * FROM packages WHERE package_id = ?', [$id]) : null;
$items = all(
  $pdo,
  "SELECT p.*, COUNT(m.membership_id) AS member_count
     FROM packages p
     LEFT JOIN memberships m ON m.package_id = p.package_id
     GROUP BY p.package_id
     ORDER BY p.status = 'Active' DESC, p.duration_months, p.fee",
);

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<?php if ($action === 'new' || $action === 'edit'): ?>
  <!-- This div groups related page content using the “panel” layout style. -->
  <div class="panel">
    <!-- This div groups related page content using the “panel-title” layout style. -->
    <div class="panel-title">
      <!-- This div groups related HTML content so the page structure is easier to manage. -->
      <div><small class="text-muted">Package editor</small>
        <h2><?= $package ? 'Edit package' : 'Create package' ?></h2>
      </div>
      <a class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" href="<?= url('admin/packages.php') ?>">
        <span class="btn-ripple-circle"></span>
        <span class="btn-ripple-label"><i class="bi bi-arrow-left me-1"></i>Back</span>
      </a>
    </div>

    <!-- This form collects user input and submits this form using the POST method. -->
    <form method="post" enctype="multipart/form-data" class="row g-3" data-loading-form>
      <?= csrfField() ?>
      <input type="hidden" name="mode" value="save">
      <input type="hidden" name="package_id" value="<?= (int) ($package['package_id'] ?? 0) ?>">

      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-lg-8">
        <!-- This div creates a Bootstrap grid row for responsive columns. -->
        <div class="row g-3">
          <!-- This div creates a responsive Bootstrap column inside the current row. -->
          <div class="col-md-6"><label class="form-label">Package name</label><input class="form-control" name="package_name" required maxlength="100" value="<?= e($package['package_name'] ?? '') ?>" placeholder="e.g. Annual Gym Membership"></div>
          <!-- This div creates a responsive Bootstrap column inside the current row. -->
          <div class="col-md-3"><label class="form-label">Fee (LKR)</label><input class="form-control" type="number" step="0.01" min="0.01" name="fee" required value="<?= e($package['fee'] ?? '') ?>"></div>
          <!-- This div creates a responsive Bootstrap column inside the current row. -->
          <div class="col-md-3"><label class="form-label">Duration (months)</label><input class="form-control" type="number" min="1" max="60" name="duration_months" required value="<?= e($package['duration_months'] ?? 1) ?>"></div>
          <!-- This div creates a responsive Bootstrap column inside the current row. -->
          <div class="col-md-9"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="4" maxlength="255" placeholder="Explain what the package includes."><?= e($package['description'] ?? '') ?></textarea></div>
          <!-- This div creates a responsive Bootstrap column inside the current row. -->
          <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['Active', 'Inactive'] as $status): ?><option <?= ($package['status'] ?? 'Active') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
        </div>
      </div>

      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-lg-4">
        <label class="form-label">Package image</label>
        <!-- This div holds an image or image-related control in this layout. -->
        <div class="package-image-editor">
          <?php if (!empty($package['image_path'])): ?>
            <img id="packagePreviewImg" src="<?= e(packageImageUrl($package)) ?>" alt="Package preview">
          <?php else: ?>
            <div class="package-image-empty" id="packageEmptySpace">
              <i class="bi bi-image"></i>
              <span>No image selected</span>
            </div>
            <img id="packagePreviewImg" src="" alt="Package preview" style="display: none;">
          <?php endif; ?>
          <?php if ($hasImagePath): ?>
            <input class="form-control" type="file" name="package_image" id="packageImageInput" accept="image/jpeg,image/png,image/webp">
            <small class="form-text text-muted">WebP, JPG or PNG · maximum 3 MB. WebP is recommended.</small>
            <?php if (!empty($package['image_path'])): ?>
              <label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_image" value="1"><span class="form-check-label">Remove current image and use automatic fallback</span></label>
            <?php endif; ?>
          <?php else: ?>
            <small class="text-muted">Import the final database patch to enable package image uploads.</small>
          <?php endif; ?>
        </div>
      </div>

      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-12"><button class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" type="submit"><span class="btn-ripple-circle"></span><span class="btn-ripple-label">Save package</span></button></div>
    </form>
  </div>
<?php else: ?>
  <!-- This div groups related page content using the “panel” layout style. -->
  <div class="panel">
    <!-- This div groups related page content using the “panel-title” layout style. -->
    <div class="panel-title">
      <!-- This div groups related HTML content so the page structure is easier to manage. -->
      <div><small class="text-muted">Membership catalog</small>
        <h2>Manage packages</h2>
      </div>
      <a class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" href="<?= url('admin/packages.php?action=new') ?>">
        <span class="btn-ripple-circle"></span>
        <span class="btn-ripple-label"><i class="bi bi-plus-lg me-1"></i>Add package</span>
      </a>
    </div>

    <!-- This div creates a Bootstrap grid row for responsive columns. -->
    <div class="row g-3">
      <?php foreach ($items as $item): ?>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-6 col-xl-4">
          <!-- This div shows one main PowerFit benefit in a reusable card layout. -->
          <div class="feature-card package-admin-card p-0">
            <!-- This div holds an image or image-related control in this layout. -->
            <div class="package-admin-image"><img src="<?= e(packageImageUrl($item)) ?>" alt="<?= e($item['package_name']) ?>"></div>
            <!-- This div groups related page content using the “p-4” layout style. -->
            <div class="p-4">
              <!-- This div uses flexbox to align the child elements in this area. -->
              <div class="d-flex justify-content-between align-items-start gap-3">
                <?= statusBadge($item['status']) ?>
                <!-- This div uses flexbox to align the child elements in this area. -->
                <div class="d-flex gap-1">
                  <a href="?action=edit&id=<?= (int) $item['package_id'] ?>" class="icon-btn" title="Edit package"><i class="bi bi-pencil"></i></a>
                  <!-- This form collects user input and submits this form using the POST method. -->
                  <form method="post" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="mode" value="delete">
                    <input type="hidden" name="package_id" value="<?= (int) $item['package_id'] ?>">
                    <button class="icon-btn border-0" type="submit" data-confirm="Remove this package? Linked packages will be archived to keep payment history safe." title="Remove package"><i class="bi bi-trash3"></i></button>
                  </form>
                </div>
              </div>
              <h3><?= e($item['package_name']) ?></h3>
              <p><?= e($item['description']) ?></p>
              <!-- This div groups related page content using the “h3 fw-bold” layout style. -->
              <div class="h3 fw-bold mb-1"><?= money($item['fee']) ?></div>
              <small class="text-muted"><?= (int) $item['duration_months'] ?> month(s) · <?= (int) $item['member_count'] ?> membership records</small>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>