<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Admin');
$pageTitle = 'Payments';
$action = $_GET['action'] ?? 'list';
$editId = (int) ($_GET['id'] ?? 0);

$hasExtendedPaymentFields = columnExists($pdo, 'payments', 'package_id');

// POST handling: only run this block when a form has been submitted.
//  Run this block only when the form is submitted with POST.
if (isPost()) {
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();
  $mode = $_POST['mode'] ?? 'create';

  if ($mode === 'verify') {
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $newStatus = $_POST['status'] ?? 'Pending';
    $adminNote = trim($_POST['admin_note'] ?? '');
    $allowedStatuses = ['Completed', 'Pending', 'Failed', 'Refunded'];

    if (!in_array($newStatus, $allowedStatuses, true)) {
      //  Save a one-time message so the next page can tell the user what happened.
      flash('danger', 'Invalid payment status.');
      redirect('admin/payments.php');
    }

    $payment = one($pdo, 'SELECT * FROM payments WHERE payment_id = ?', [$paymentId]);
    if (!$payment) {
      //  Save a one-time message so the next page can tell the user what happened.
      flash('danger', 'Payment not found.');
      redirect('admin/payments.php');
    }

    //  group related SQL changes so they either all succeed or all roll back.
    //  Start a database transaction so related changes succeed or fail together.
    $pdo->beginTransaction();
    try {
      $membershipId = $payment['membership_id'] ? (int) $payment['membership_id'] : null;
      $periodStart = $payment['period_start'] ?? null;
      $periodEnd = $payment['period_end'] ?? null;

      if ($newStatus === 'Completed' && !$membershipId && $hasExtendedPaymentFields && !empty($payment['package_id'])) {
        $package = one($pdo, 'SELECT * FROM packages WHERE package_id = ?', [(int) $payment['package_id']]);
        if ($package) {
          $periodStart = date('Y-m-d');
          $periodEnd = date('Y-m-d', strtotime('+' . (int) $package['duration_months'] . ' months', strtotime($periodStart)));

          //  Prepare and run this SQL statement using PDO.
          $pdo->prepare("UPDATE memberships SET status = 'Expired' WHERE member_id = ? AND status = 'Active'")->execute([$payment['member_id']]);
          $pdo->prepare(
            "INSERT INTO memberships(member_id, package_id, start_date, end_date, status) VALUES(?, ?, ?, ?, 'Active')",
          )->execute([$payment['member_id'], $package['package_id'], $periodStart, $periodEnd]);
          $membershipId = (int) $pdo->lastInsertId();
        }
      }

      if ($hasExtendedPaymentFields) {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
          'UPDATE payments SET status = ?, membership_id = ?, period_start = ?, period_end = ?, admin_note = ?, verified_by = ?, verified_at = CASE WHEN ? = \'Completed\' THEN NOW() ELSE verified_at END WHERE payment_id = ?',
        )->execute([
          $newStatus,
          $membershipId,
          $periodStart,
          $periodEnd,
          $adminNote ?: null,
          currentUser()['id'],
          $newStatus,
          $paymentId,
        ]);
      } else {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare('UPDATE payments SET status = ? WHERE payment_id = ?')->execute([$newStatus, $paymentId]);
      }

      //  Save all database changes made inside the transaction.
      $pdo->commit();
      flash('success', $newStatus === 'Completed' ? 'Payment verified and membership updated.' : 'Payment status updated.');
    } catch (Throwable $error) {
      //  Undo the transaction if an error happens before completion.
      $pdo->rollBack();
      flash('danger', 'The payment could not be updated.');
    }

    //  Redirect the browser after this action to avoid repeating the same request.
    redirect('admin/payments.php');
  }

  $memberId = (int) ($_POST['member_id'] ?? 0);
  $packageId = (int) ($_POST['package_id'] ?? 0);
  $amount = (float) ($_POST['amount'] ?? 0);
  $method = $_POST['payment_method'] ?? 'Cash';
  $status = $_POST['status'] ?? 'Completed';
  $payerName = trim($_POST['payer_name'] ?? '');
  $payerPhone = trim($_POST['payer_phone'] ?? '');

  if (!$memberId || $amount <= 0) {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('danger', 'Choose a member and enter a valid amount.');
    redirect('admin/payments.php?action=new');
  }

  $reference = 'PMT-' . date('YmdHis') . '-' . random_int(10, 99);

  if ($hasExtendedPaymentFields) {
    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare(
      "INSERT INTO payments(member_id, package_id, reference_number, amount, payment_method, payment_date, status, payer_name, payer_phone, payment_source, verified_by, verified_at)
             VALUES(?, ?, ?, ?, ?, NOW(), ?, ?, ?, 'Admin', ?, CASE WHEN ? = 'Completed' THEN NOW() ELSE NULL END)",
    )->execute([
      $memberId,
      $packageId ?: null,
      $reference,
      $amount,
      $method,
      $status,
      $payerName ?: null,
      $payerPhone ?: null,
      currentUser()['id'],
      $status,
    ]);
  } else {
    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare(
      'INSERT INTO payments(member_id, reference_number, amount, payment_method, payment_date, status) VALUES(?, ?, ?, ?, NOW(), ?)',
    )->execute([$memberId, $reference, $amount, $method, $status]);
  }

  $paymentId = (int) $pdo->lastInsertId();
  //  Save a one-time message so the next page can tell the user what happened.
  flash('success', 'Payment record saved successfully.');
  redirect('receipt.php?id=' . $paymentId);
}

$members = all($pdo, "SELECT member_id, member_number, full_name, contact_number FROM members WHERE status = 'Active' ORDER BY full_name");
$packages = all($pdo, "SELECT * FROM packages WHERE status = 'Active' ORDER BY duration_months, fee");

$packageSelect = $hasExtendedPaymentFields
  ? 'COALESCE(pk_direct.package_name, pk_membership.package_name) AS package_name'
  : 'pk_membership.package_name AS package_name';
$packageJoin = $hasExtendedPaymentFields ? 'LEFT JOIN packages pk_direct ON pk_direct.package_id = p.package_id' : '';

$payments = all(
  $pdo,
  "SELECT p.*, m.full_name, m.member_number, {$packageSelect}
     FROM payments p
     JOIN members m ON m.member_id = p.member_id
     LEFT JOIN memberships ms ON ms.membership_id = p.membership_id
     LEFT JOIN packages pk_membership ON pk_membership.package_id = ms.package_id
     {$packageJoin}
     ORDER BY p.payment_date DESC",
);

$editPayment = $editId ? one($pdo, 'SELECT p.*, m.full_name FROM payments p JOIN members m ON m.member_id = p.member_id WHERE p.payment_id = ?', [$editId]) : null;

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<?php if ($action === 'new'): ?>
  <!-- This div groups related page content using the “panel” layout style. -->
  <div class="panel">
    <!-- This div groups related page content using the “panel-title” layout style. -->
    <div class="panel-title"><!-- This div groups related HTML content on this page. -->
      <div><small class="text-muted">Front-desk record</small>
        <h2>Record payment</h2>
      </div><a href="payments.php" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
    </div>
    <!-- This form collects user input and submits this form using the POST method. -->
    <form method="post" class="row g-3" data-loading-form>
      <?= csrfField() ?><input type="hidden" name="mode" value="create">
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label">Member</label><select class="form-select" name="member_id" required>
          <option value="">Select member</option><?php foreach ($members as $member): ?><option value="<?= (int) $member['member_id'] ?>"><?= e($member['member_number'] . ' — ' . $member['full_name']) ?></option><?php endforeach; ?>
        </select></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label">Package</label><select class="form-select" name="package_id" id="adminPaymentPackage">
          <option value="">General payment</option><?php foreach ($packages as $package): ?><option value="<?= (int) $package['package_id'] ?>" data-fee="<?= e($package['fee']) ?>"><?= e($package['package_name']) ?> — <?= money($package['fee']) ?></option><?php endforeach; ?>
        </select></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-4"><label class="form-label">Amount</label><input class="form-control" id="adminPaymentAmount" type="number" step="0.01" min="0.01" name="amount" required></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-4"><label class="form-label">Method</label><select class="form-select" name="payment_method"><?php foreach (['Cash', 'Credit Card', 'Debit Card', 'Bank Transfer'] as $method): ?><option><?= e($method) ?></option><?php endforeach; ?></select></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['Completed', 'Pending', 'Failed'] as $status): ?><option><?= e($status) ?></option><?php endforeach; ?></select></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label">Payer name</label><input class="form-control" name="payer_name" maxlength="100"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-md-6"><label class="form-label">Payer contact</label><input class="form-control" name="payer_phone" maxlength="30"></div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-12"><button class="btn btn-dark rounded-pill px-4">Save payment</button></div>
    </form>
  </div>
  <script>
    document.getElementById('adminPaymentPackage')?.addEventListener('change', e => {
      const fee = e.target.selectedOptions[0]?.dataset.fee;
      if (fee) document.getElementById('adminPaymentAmount').value = fee;
    });
  </script>
<?php elseif ($action === 'edit' && $editPayment): ?>
  <!-- This div groups related page content using the “panel” layout style. -->
  <div class="panel">
    <!-- This div groups related page content using the “panel-title” layout style. -->
    <div class="panel-title"><!-- This div groups related HTML content on this page. -->
      <div><small class="text-muted"><?= e($editPayment['reference_number']) ?></small>
        <h2>Verify payment</h2>
      </div><a href="payments.php" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
    </div>
    <!-- This div creates a Bootstrap grid row for responsive columns. -->
    <div class="row g-4">
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-lg-5">
        <!-- This div creates a bordered content card for related information. -->
        <div class="payment-review-card">
          <small class="text-muted">Member</small>
          <h4><?= e($editPayment['full_name']) ?></h4>
          <!-- This div creates a Bootstrap grid row for responsive columns. -->
          <div class="receipt-row"><span>Amount</span><strong><?= money($editPayment['amount']) ?></strong></div>
          <!-- This div creates a Bootstrap grid row for responsive columns. -->
          <div class="receipt-row"><span>Method</span><strong><?= e($editPayment['payment_method']) ?></strong></div>
          <?php if ($hasExtendedPaymentFields): ?>
            <!-- This div creates a Bootstrap grid row for responsive columns. -->
            <div class="receipt-row"><span>Payer</span><strong><?= e($editPayment['payer_name'] ?? '—') ?></strong></div>
            <!-- This div creates a Bootstrap grid row for responsive columns. -->
            <div class="receipt-row"><span>Reference</span><strong><?= e($editPayment['customer_reference'] ?? '—') ?></strong></div>
            <?php if (columnExists($pdo, 'payments', 'card_last4') && !empty($editPayment['card_last4'])): ?>
              <!-- This div creates a Bootstrap grid row for responsive columns. -->
              <div class="receipt-row"><span>Card</span><strong><?= e($editPayment['card_holder_name'] ?? 'Cardholder') ?> ·•••• <?= e($editPayment['card_last4']) ?></strong></div>
              <!-- This div creates a Bootstrap grid row for responsive columns. -->
              <div class="receipt-row"><span>Expiry</span><strong><?= e($editPayment['card_expiry'] ?? '—') ?></strong></div>
            <?php endif; ?>
            <p class="small text-muted mt-3 mb-0"><?= e($editPayment['customer_note'] ?? 'No customer note.') ?></p>
          <?php endif; ?>
        </div>
      </div>
      <!-- This div creates a responsive Bootstrap column inside the current row. -->
      <div class="col-lg-7">
        <!-- This form collects user input and submits this form using the POST method. -->
        <form method="post" class="row g-3" data-loading-form>
          <?= csrfField() ?><input type="hidden" name="mode" value="verify"><input type="hidden" name="payment_id" value="<?= (int) $editPayment['payment_id'] ?>">
          <!-- This div creates a responsive Bootstrap column inside the current row. -->
          <div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['Pending', 'Completed', 'Failed', 'Refunded'] as $status): ?><option <?= $editPayment['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
          <!-- This div creates a responsive Bootstrap column inside the current row. -->
          <div class="col-12"><label class="form-label">Admin verification note</label><textarea class="form-control" name="admin_note" rows="4" maxlength="500"><?= e($editPayment['admin_note'] ?? '') ?></textarea></div>
          <!-- This div creates a responsive Bootstrap column inside the current row. -->
          <div class="col-12"><button class="btn btn-dark rounded-pill px-4">Update payment</button></div>
        </form>
      </div>
    </div>
  </div>
<?php else: ?>
  <!-- This div groups related page content using the “panel” layout style. -->
  <div class="panel">
    <!-- This div groups related page content using the “panel-title” layout style. -->
    <div class="panel-title"><!-- This div groups related HTML content on this page. -->
      <div>
        <h2>Payment history</h2><small class="text-muted"><?= count($payments) ?> transactions</small>
      </div>
      <a class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" href="<?= url('admin/payments.php?action=new') ?>">
        <span class="btn-ripple-circle"></span>
        <span class="btn-ripple-label"><i class="bi bi-plus-lg me-1"></i>Record payment</span>
      </a>
    </div>
    <!-- This div allows this table to scroll safely on smaller screens. -->
    <div class="table-responsive">
      <table class="table table-modern">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Member</th>
            <th>Package</th>
            <th>Method</th>
            <th>Status</th>
            <th>Date</th>
            <th class="text-end">Amount</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $payment): ?><tr>
              <td><a class="fw-bold text-decoration-none" href="../receipt.php?id=<?= (int) $payment['payment_id'] ?>"><?= e($payment['reference_number']) ?></a></td>
              <td><?= e($payment['full_name']) ?><small class="d-block text-muted"><?= e($payment['member_number']) ?></small></td>
              <td><?= e($payment['package_name'] ?? 'General') ?></td>
              <td><?= e($payment['payment_method']) ?></td>
              <td><?= statusBadge($payment['status']) ?></td>
              <td><?= shortDate($payment['payment_date']) ?></td>
              <td class="text-end fw-bold"><?= money($payment['amount']) ?></td>
              <td class="text-end"><a class="btn btn-sm btn-outline-secondary rounded-pill" href="?action=edit&id=<?= (int) $payment['payment_id'] ?>">Review</a></td>
            </tr><?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>