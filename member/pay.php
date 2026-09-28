<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Member');

$pageTitle = 'Submit Payment';
$member = getMemberByUser($pdo, currentUser()['id']);
if (!$member) {
  die('Member profile missing.');
}

$packageId = (int) ($_GET['package'] ?? $_POST['package_id'] ?? 0);
$packages = all($pdo, "SELECT * FROM packages WHERE status = 'Active' ORDER BY duration_months, fee");
$selectedPackage = $packageId
  ? one($pdo, "SELECT * FROM packages WHERE package_id = ? AND status = 'Active'", [$packageId])
  : null;
$hasCardMetadata = columnExists($pdo, 'payments', 'card_last4');

// POST handling: only run this block when a form has been submitted.
//  Run this block only when the form is submitted with POST.
if (isPost()) {
  //  reject forged or expired form submissions before processing input.
  //  Check the CSRF token before saving, updating or deleting data.
  verifyCsrf();

  $packageId = (int) ($_POST['package_id'] ?? 0);
  $selectedPackage = one($pdo, "SELECT * FROM packages WHERE package_id = ? AND status = 'Active'", [$packageId]);
  $payerName = trim($_POST['payer_name'] ?? '');
  $payerPhone = trim($_POST['payer_phone'] ?? '');
  $method = $_POST['payment_method'] ?? 'Cash';
  $customerReference = trim($_POST['customer_reference'] ?? '');
  $note = trim($_POST['customer_note'] ?? '');
  $cardHolder = trim($_POST['card_holder_name'] ?? '');
  $cardNumber = preg_replace('/\D+/', '', $_POST['card_number'] ?? '') ?? '';
  $cardLast4 = $cardNumber !== '' ? substr($cardNumber, -4) : '';
  $cardExpiry = trim($_POST['card_expiry'] ?? '');

  $allowedMethods = ['Cash', 'Credit Card', 'Debit Card', 'Bank Transfer'];
  $isCard = in_array($method, ['Credit Card', 'Debit Card'], true);

  if (!$selectedPackage || $payerName === '' || $payerPhone === '' || !in_array($method, $allowedMethods, true)) {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('danger', 'Complete all required payment details.');
    redirect('member/pay.php' . ($packageId ? '?package=' . $packageId : ''));
  }

  if ($isCard && ($cardHolder === '' || !isValidCardNumber($cardNumber) || !isValidCardExpiry($cardExpiry))) {
    //  Save a one-time message so the next page can tell the user what happened.
    flash('danger', 'For card payments, enter a valid cardholder name, Luhn-valid card number and a non-expired MM/YY expiry date.');
    redirect('member/pay.php?package=' . $packageId);
  }

  $reference = 'PMT-' . date('YmdHis') . '-' . random_int(10, 99);
  $amount = (float) $selectedPackage['fee'];

  if ($hasCardMetadata) {
    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare(
      "INSERT INTO payments(
                member_id, membership_id, package_id, reference_number, amount, payment_method,
                payment_date, status, payer_name, payer_phone, customer_reference, customer_note,
                payment_source, card_holder_name, card_last4, card_expiry
             ) VALUES(?, NULL, ?, ?, ?, ?, NOW(), 'Pending', ?, ?, ?, ?, 'Member', ?, ?, ?)",
    )->execute([
      $member['member_id'],
      $packageId,
      $reference,
      $amount,
      $method,
      $payerName,
      $payerPhone,
      $customerReference ?: null,
      $note ?: null,
      $isCard ? $cardHolder : null,
      $isCard ? $cardLast4 : null,
      $isCard ? $cardExpiry : null,
    ]);
  } else {
    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare(
      "INSERT INTO payments(
                member_id, membership_id, package_id, reference_number, amount, payment_method,
                payment_date, status, payer_name, payer_phone, customer_reference, customer_note, payment_source
             ) VALUES(?, NULL, ?, ?, ?, ?, NOW(), 'Pending', ?, ?, ?, ?, 'Member')",
    )->execute([
      $member['member_id'],
      $packageId,
      $reference,
      $amount,
      $method,
      $payerName,
      $payerPhone,
      $customerReference ?: null,
      $note ?: null,
    ]);
  }

  $paymentId = (int) $pdo->lastInsertId();
  //  Save a one-time message so the next page can tell the user what happened.
  flash('success', 'Payment details submitted successfully. The record is saved and waiting for admin verification.');
  redirect('receipt.php?id=' . $paymentId);
}

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4 justify-content-center">
  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-9">
    <!-- This div groups related form controls for easier layout and validation. -->
    <div class="panel payment-form-panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title">
        <!-- This div groups related HTML content so the page structure is easier to manage. -->
        <div><small class="text-muted">Payment submission</small>
          <h2>Submit payment details</h2>
        </div>
        <span class="analytics-chip"><i class="bi bi-shield-check me-1"></i>Local academic workflow</span>
      </div>


      <!-- This form collects user input and submits this form using the POST method. -->
      <form method="post" class="row g-3" data-loading-form id="memberPaymentForm">
        <?= csrfField() ?>

        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-6">
          <label class="form-label">Package</label>
          <select class="form-select" name="package_id" id="paymentPackage" required>
            <option value="">Choose package</option>
            <?php foreach ($packages as $package): ?>
              <option value="<?= (int) $package['package_id'] ?>" data-fee="<?= e($package['fee']) ?>" <?= $packageId === (int) $package['package_id'] ? 'selected' : '' ?>><?= e($package['package_name']) ?> — <?= money($package['fee']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-6">
          <label class="form-label">Amount</label>
          <!-- This div groups related form controls for easier layout and validation. -->
          <div class="form-control payment-amount-preview" id="paymentAmount"><?= $selectedPackage ? money($selectedPackage['fee']) : 'Select a package' ?></div>
        </div>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-6"><label class="form-label">Payer name</label><input class="form-control" name="payer_name" required maxlength="100" value="<?= e($member['full_name']) ?>"></div>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-6"><label class="form-label">Contact number</label><input class="form-control" name="payer_phone" required maxlength="30" value="<?= e($member['contact_number'] ?? '') ?>"></div>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-6">
          <label class="form-label">Payment method</label>
          <select class="form-select" name="payment_method" id="paymentMethod" required>
            <option>Cash</option>
            <option>Bank Transfer</option>
            <option>Credit Card</option>
            <option>Debit Card</option>
          </select>
        </div>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-md-6"><label class="form-label">Bank / transaction reference <span class="text-muted">(optional)</span></label><input class="form-control" name="customer_reference" maxlength="100" placeholder="Reference from receipt or transfer"></div>

        <!-- This div creates a bordered content card for related information. -->
        <div class="col-12 d-none" id="cardFields">
          <!-- This div creates a bordered content card for related information. -->
          <div class="card-demo-panel">
            <!-- This div uses flexbox to align the child elements in this area. -->
            <div class="d-flex justify-content-between align-items-center mb-3">
              <!-- This div groups related HTML content so the page structure is easier to manage. -->
              <div><small class="text-muted">Card details</small>
              </div>
            </div>
            <!-- This div creates a Bootstrap grid row for responsive columns. -->
            <div class="row g-3">
              <!-- This div creates a responsive Bootstrap column inside the current row. -->
              <div class="col-md-6"><label class="form-label">Cardholder name</label><input class="form-control" name="card_holder_name" id="cardHolder" autocomplete="cc-name"></div>
              <!-- This div creates a responsive Bootstrap column inside the current row. -->
              <div class="col-md-6"><label class="form-label">Card number</label><input class="form-control" id="cardNumberDemo" name="card_number" inputmode="numeric" autocomplete="cc-number" maxlength="23" placeholder="Use a valid test card number"><small class="text-muted"></small></div>
              <!-- This div creates a responsive Bootstrap column inside the current row. -->
              <div class="col-md-6"><label class="form-label">Expire date</label><input class="form-control" name="card_expiry" id="cardExpiry" inputmode="numeric" autocomplete="cc-exp" maxlength="5" placeholder="MM/YY"></div>
              <!-- This div creates a responsive Bootstrap column inside the current row. -->
              <div class="col-md-6"><label class="form-label">CVV</label><input class="form-control" id="cardCvvDemo" type="password" inputmode="numeric" autocomplete="cc-csc" maxlength="4" placeholder="123"><small class="text-muted"></small></div>
            </div>
          </div>
        </div>

        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-12"><label class="form-label">Notes <span class="text-muted">(optional)</span></label><textarea class="form-control" name="customer_note" rows="3" maxlength="500" placeholder="Any detail the admin should verify"></textarea></div>
        <!-- This div creates a responsive Bootstrap column inside the current row. -->
        <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-dark rounded-pill px-4">Submit payment details</button><a class="btn btn-outline-secondary rounded-pill px-4" href="payments.php">Back to payments</a></div>
      </form>
    </div>
  </div>
</div>

<script>
  const packageSelect = document.getElementById('paymentPackage');
  const amountPreview = document.getElementById('paymentAmount');
  const methodSelect = document.getElementById('paymentMethod');
  const cardFields = document.getElementById('cardFields');
  const paymentForm = document.getElementById('memberPaymentForm');
  const cardNumber = document.getElementById('cardNumberDemo');
  const cardCvv = document.getElementById('cardCvvDemo');
  const cardHolder = document.getElementById('cardHolder');
  const cardExpiry = document.getElementById('cardExpiry');

  const isCardMethod = () => ['Credit Card', 'Debit Card'].includes(methodSelect?.value || '');
  const syncCardFields = () => cardFields?.classList.toggle('d-none', !isCardMethod());
  packageSelect?.addEventListener('change', () => {
    const option = packageSelect.selectedOptions[0];
    const fee = Number(option?.dataset.fee || 0);
    amountPreview.textContent = fee ? `LKR ${fee.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : 'Select a package';
  });
  methodSelect?.addEventListener('change', syncCardFields);
  syncCardFields();

  cardNumber?.addEventListener('input', () => {
    const digits = cardNumber.value.replace(/\D/g, '').slice(0, 19);
    cardNumber.value = digits.replace(/(.{4})/g, '$1 ').trim();
  });
  cardCvv?.addEventListener('input', () => {
    cardCvv.value = cardCvv.value.replace(/\D/g, '').slice(0, 4);
  });
  cardExpiry?.addEventListener('input', () => {
    const digits = cardExpiry.value.replace(/\D/g, '').slice(0, 4);
    cardExpiry.value = digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
  });

  const passesLuhn = (digits) => {
    let sum = 0;
    let doubleDigit = false;
    for (let index = digits.length - 1; index >= 0; index -= 1) {
      let value = Number(digits[index]);
      if (doubleDigit) {
        value *= 2;
        if (value > 9) value -= 9;
      }
      sum += value;
      doubleDigit = !doubleDigit;
    }
    return sum % 10 === 0;
  };

  const isFutureExpiry = (value) => {
    const match = value.match(/^(0[1-9]|1[0-2])\/(\d{2})$/);
    if (!match) return false;
    const month = Number(match[1]);
    const year = 2000 + Number(match[2]);
    const now = new Date();
    const expiry = new Date(year, month, 0, 23, 59, 59);
    return expiry >= now;
  };

  paymentForm?.addEventListener('submit', (event) => {
    if (!isCardMethod()) {
      return;
    }
    const digits = cardNumber.value.replace(/\D/g, '');
    const cvv = cardCvv.value.replace(/\D/g, '');
    if (digits.length < 12 || digits.length > 19 || !passesLuhn(digits) || cvv.length < 3 || !cardHolder.value.trim() || !isFutureExpiry(cardExpiry.value)) {
      event.preventDefault();
      alert('Use a valid test card number, a current/future expiry date and a 3-4 digit CVV.');
      return;
    }
    // The card number is posted only for server-side validation and is never written to the database.
    // CVV has no name attribute, so it is never posted to PHP.
  });
</script>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>