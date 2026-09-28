<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$id = (int) ($_GET['id'] ?? 0);
$hasDirectPackage = columnExists($pdo, 'payments', 'package_id');
$directPackageSelect = $hasDirectPackage ? 'COALESCE(pk_direct.package_name, pk_member.package_name)' : 'pk_member.package_name';
$directPackageJoin = $hasDirectPackage ? 'LEFT JOIN packages pk_direct ON pk_direct.package_id = p.package_id' : '';

$payment = one(
  $pdo,
  "SELECT p.*, m.full_name, m.member_number,
        {$directPackageSelect} AS package_name,
        ms.start_date AS membership_start, ms.end_date AS membership_end
     FROM payments p
     JOIN members m ON m.member_id = p.member_id
     LEFT JOIN memberships ms ON ms.membership_id = p.membership_id
     LEFT JOIN packages pk_member ON pk_member.package_id = ms.package_id
     {$directPackageJoin}
     WHERE p.payment_id = ?",
  [$id],
);

if (!$payment) {
  http_response_code(404);
  die('Receipt not found.');
}

if (currentUser()['role'] === 'Member') {
  $member = getMemberByUser($pdo, currentUser()['id']);
  if (!$member || (int) $member['member_id'] !== (int) $payment['member_id']) {
    http_response_code(403);
    die('Not allowed.');
  }
}

$pageTitle = $payment['status'] === 'Completed' ? 'Receipt' : 'Payment Submission';
//  Load the shared file needed before this page continues.
require __DIR__ . '/includes/dashboard_header.php';
?>

<!-- This div groups payment receipt information. -->
<div class="receipt">
  <!-- This div groups payment receipt information. -->
  <div class="receipt-head">
    <!-- This div groups related HTML content so the page structure is easier to manage. -->
    <div>
      <a class="brand-lockup text-decoration-none text-dark" href="<?= url(roleDashboard(currentUser()['role'])) ?>">
        <span class="brand-mark"><i class="bi bi-lightning-charge-fill"></i></span><span>POWERFIT</span>
      </a>
      <small class="d-block text-muted mt-3"><?= $payment['status'] === 'Completed' ? 'Verified gym payment receipt' : 'Offline payment submission record' ?></small>
    </div>
    <!-- This div groups related page content using the “text-end” layout style. -->
    <div class="text-end">
      <span class="badge text-bg-dark rounded-pill mb-2"><?= $payment['status'] === 'Completed' ? 'Receipt' : 'Submission' ?></span>
      <h5 class="mb-0"><?= e($payment['reference_number']) ?></h5>
      <small class="text-muted"><?= date('d M Y, h:i A', strtotime($payment['payment_date'])) ?></small>
    </div>
  </div>

  <?php if ($payment['status'] === 'Pending'): ?>
    <!-- This div shows a feedback message such as success, warning or error. -->
    <div class="alert alert-warning rounded-4">Your payment details were saved successfully and are waiting for admin verification. A downloadable PDF paid receipt will appear after the payment is marked Completed.</div>
  <?php elseif ($payment['status'] === 'Completed'): ?>
    <!-- This div shows a feedback message such as success, warning or error. -->
    <div class="alert alert-success rounded-4"><i class="bi bi-check-circle-fill me-2"></i>Payment verified successfully.</div>
  <?php endif; ?>

  <!-- This div creates a Bootstrap grid row for responsive columns. -->
  <div class="row g-4">
    <!-- This div creates a responsive Bootstrap column inside the current row. -->
    <div class="col-md-6"><small class="text-muted">Member</small>
      <h5><?= e($payment['full_name']) ?></h5><span><?= e($payment['member_number']) ?></span>
    </div>
    <!-- This div creates a responsive Bootstrap column inside the current row. -->
    <div class="col-md-6 text-md-end"><small class="text-muted">Payment status</small><!-- This div groups related content using the mt-1 layout class. -->
      <div class="mt-1"><?= statusBadge($payment['status']) ?></div>
    </div>
  </div>
  <hr>
  <!-- This div creates a Bootstrap grid row for responsive columns. -->
  <div class="receipt-row"><span>Package</span><strong><?= e($payment['package_name'] ?? 'General payment') ?></strong></div>
  <!-- This div creates a Bootstrap grid row for responsive columns. -->
  <div class="receipt-row"><span>Payment method</span><strong><?= e($payment['payment_method']) ?></strong></div>
  <?php if (columnExists($pdo, 'payments', 'card_last4') && !empty($payment['card_last4'])): ?>
    <!-- This div creates a Bootstrap grid row for responsive columns. -->
    <div class="receipt-row"><span>Card</span><strong>•••• <?= e($payment['card_last4']) ?></strong></div>
  <?php endif; ?>
  <?php if (columnExists($pdo, 'payments', 'card_holder_name') && !empty($payment['card_holder_name'])): ?>
    <!-- This div creates a Bootstrap grid row for responsive columns. -->
    <div class="receipt-row"><span>Cardholder</span><strong><?= e($payment['card_holder_name']) ?></strong></div>
  <?php endif; ?>
  <?php if (columnExists($pdo, 'payments', 'payer_name') && !empty($payment['payer_name'])): ?><!-- This div creates a Bootstrap grid row for responsive columns. -->
    <div class="receipt-row"><span>Payer</span><strong><?= e($payment['payer_name']) ?></strong></div><?php endif; ?>
  <?php if (columnExists($pdo, 'payments', 'customer_reference') && !empty($payment['customer_reference'])): ?><!-- This div creates a Bootstrap grid row for responsive columns. -->
    <div class="receipt-row"><span>Customer reference</span><strong><?= e($payment['customer_reference']) ?></strong></div><?php endif; ?>
  <?php if ($payment['membership_start']): ?><!-- This div creates a Bootstrap grid row for responsive columns. -->
    <div class="receipt-row"><span>Membership period</span><strong><?= shortDate($payment['membership_start']) ?> – <?= shortDate($payment['membership_end']) ?></strong></div><?php endif; ?>
  <!-- This div creates a Bootstrap grid row for responsive columns. -->
  <div class="receipt-row receipt-total"><span>Total</span><strong><?= money($payment['amount']) ?></strong></div>

  <!-- This div uses flexbox to align the child elements in this area. -->
  <div class="d-flex flex-wrap gap-2 mt-4 no-print">
    <?php if ($payment['status'] === 'Completed'): ?>
      <a class="btn btn-dark rounded-pill px-4" href="<?= url('receipt_pdf.php?id=' . (int) $payment['payment_id']) ?>"><i class="bi bi-file-earmark-pdf me-2"></i>Download PDF</a>
      <button class="btn btn-outline-secondary rounded-pill px-4" onclick="window.print()"><i class="bi bi-printer me-2"></i>Print</button>
    <?php endif; ?>
    <a class="btn btn-outline-secondary rounded-pill px-4" href="javascript:history.back()">Back</a>
  </div>
  <small class="print-only">Generated by PowerFit on <?= date('d M Y H:i') ?></small>
</div>

<?php require __DIR__ . '/includes/dashboard_footer.php'; ?>