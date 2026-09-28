<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Member');
$pageTitle = 'My Payments';
$member = getMemberByUser($pdo, currentUser()['id']);

$packageExpr = columnExists($pdo, 'payments', 'package_id')
  ? 'COALESCE(pk_direct.package_name, pk_membership.package_name)'
  : 'pk_membership.package_name';
$directJoin = columnExists($pdo, 'payments', 'package_id')
  ? 'LEFT JOIN packages pk_direct ON pk_direct.package_id = p.package_id'
  : '';

$rows = all(
  $pdo,
  "SELECT p.*, {$packageExpr} AS package_name
     FROM payments p
     LEFT JOIN memberships ms ON ms.membership_id = p.membership_id
     LEFT JOIN packages pk_membership ON pk_membership.package_id = ms.package_id
     {$directJoin}
     WHERE p.member_id = ?
     ORDER BY p.payment_date DESC",
  [$member['member_id']],
);

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div groups related page content using the “panel” layout style. -->
<div class="panel">
  <!-- This div groups related page content using the “panel-title” layout style. -->
  <div class="panel-title">
    <!-- This div groups related HTML content so the page structure is easier to manage. -->
    <div>
      <h2>Payment history</h2><small class="text-muted">Offline records and verified receipts</small>
    </div>

    <a class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" href="<?= url('pay.php') ?>">
      <span class="btn-ripple-circle"></span>
      <span class="btn-ripple-label"> <i class="bi bi-plus-lg me-1"></i>Submit payment</span>
    </a>
  </div>

  <!-- This div allows this table to scroll safely on smaller screens. -->
  <div class="table-responsive">
    <!-- This table displays database records in rows and columns for easy reading. -->
    <table class="table table-modern">
      <thead>
        <tr>
          <th>Reference</th>
          <th>Package</th>
          <th>Method</th>
          <th>Date</th>
          <th>Status</th>
          <th class="text-end">Amount</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><a class="fw-bold text-decoration-none" href="../receipt.php?id=<?= (int) $row['payment_id'] ?>"><?= e($row['reference_number']) ?></a></td>
            <td><?= e($row['package_name'] ?? 'General payment') ?></td>
            <td><?= e($row['payment_method']) ?></td>
            <td><?= shortDate($row['payment_date']) ?></td>
            <td><?= statusBadge($row['status']) ?></td>
            <td class="text-end fw-bold"><?= money($row['amount']) ?></td>
            <td class="text-end">
              <?php if ($row['status'] === 'Completed'): ?>
                <a class="btn btn-sm btn-outline-secondary rounded-pill" href="../receipt_pdf.php?id=<?= (int) $row['payment_id'] ?>"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
              <?php else: ?>
                <small class="text-muted">Awaiting verification</small>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr>
            <td colspan="7" class="text-center text-muted py-5">No payment records yet.</td>
          </tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>