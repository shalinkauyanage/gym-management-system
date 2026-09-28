<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Admin');
$pageTitle = 'Admin Analytics';

$selectedYear = (int) ($_GET['year'] ?? date('Y'));
if ($selectedYear < 2020 || $selectedYear > (int) date('Y') + 1) {
  $selectedYear = (int) date('Y');
}

$stats = [
  'members' => (int) scalar($pdo, 'SELECT COUNT(*) FROM members'),
  'active_memberships' => (int) scalar($pdo, "SELECT COUNT(*) FROM memberships WHERE status = 'Active'"),
  'coaches' => (int) scalar($pdo, "SELECT COUNT(*) FROM trainers WHERE status = 'Active'"),
  'pending_payments' => (int) scalar($pdo, "SELECT COUNT(*) FROM payments WHERE status = 'Pending'"),
];

$monthlyIncome = (float) scalar(
  $pdo,
  "SELECT COALESCE(SUM(amount), 0) FROM payments
     WHERE status = 'Completed' AND YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())",
);
$monthlyExpenses = tableExists($pdo, 'financial_transactions')
  ? (float) scalar(
    $pdo,
    "SELECT COALESCE(SUM(amount), 0) FROM financial_transactions
         WHERE transaction_type = 'Expense' AND YEAR(transaction_date) = YEAR(CURDATE()) AND MONTH(transaction_date) = MONTH(CURDATE())",
  )
  : 0.0;
$netProfit = $monthlyIncome - $monthlyExpenses;
$averageRating = (float) scalar($pdo, "SELECT COALESCE(AVG(score), 0) FROM trainer_ratings WHERE status = 'Visible'");

$incomeRows = all(
  $pdo,
  "SELECT MONTH(payment_date) month_no, SUM(amount) total
     FROM payments
     WHERE status = 'Completed' AND YEAR(payment_date) = ?
     GROUP BY MONTH(payment_date)",
  [$selectedYear],
);
$expenseRows = tableExists($pdo, 'financial_transactions')
  ? all(
    $pdo,
    "SELECT MONTH(transaction_date) month_no, SUM(amount) total
         FROM financial_transactions
         WHERE transaction_type = 'Expense' AND YEAR(transaction_date) = ?
         GROUP BY MONTH(transaction_date)",
    [$selectedYear],
  )
  : [];
$memberRows = all(
  $pdo,
  "SELECT MONTH(registration_date) month_no, COUNT(*) total
     FROM members
     WHERE YEAR(registration_date) = ?
     GROUP BY MONTH(registration_date)",
  [$selectedYear],
);

$incomeSeries = array_fill(0, 12, 0.0);
$expenseSeries = array_fill(0, 12, 0.0);
$memberSeries = array_fill(0, 12, 0);
foreach ($incomeRows as $row) {
  $incomeSeries[(int) $row['month_no'] - 1] = (float) $row['total'];
}
foreach ($expenseRows as $row) {
  $expenseSeries[(int) $row['month_no'] - 1] = (float) $row['total'];
}
foreach ($memberRows as $row) {
  $memberSeries[(int) $row['month_no'] - 1] = (int) $row['total'];
}

$packageMix = all(
  $pdo,
  "SELECT p.package_name, COUNT(m.membership_id) total
     FROM packages p
     LEFT JOIN memberships m ON m.package_id = p.package_id
     GROUP BY p.package_id
     HAVING total > 0
     ORDER BY total DESC
     LIMIT 6",
);

$ratingRows = all(
  $pdo,
  "SELECT t.trainer_id,
        " . (columnExists($pdo, 'trainers', 'display_name') ? "COALESCE(NULLIF(t.display_name, ''), u.username)" : 'u.username') . " AS coach_name,
        t.average_rating
     FROM trainers t
     JOIN users u ON u.user_id = t.user_id
     WHERE t.status = 'Active'
     ORDER BY t.average_rating DESC, coach_name
     LIMIT 6",
);

$recent = all(
  $pdo,
  "SELECT p.*, m.full_name FROM payments p
     JOIN members m ON m.member_id = p.member_id
     ORDER BY p.payment_date DESC LIMIT 6",
);

$yearOptions = range(max(2024, (int) date('Y') - 3), (int) date('Y') + 1);
//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div groups dashboard content for the current user role. -->
<div class="dashboard-hero-card admin-analytics-hero mb-4">
  <!-- This div groups related HTML content so the page structure is easier to manage. -->
  <div>
    <span class="dashboard-kicker">Analytics dashboard</span>
    <h2>See the gym business clearly.</h2>
    <p>Membership, payments, expenses, coaching performance and member growth are summarized from the PowerFit database.</p>
  </div>
  <!-- This form collects user input and submits this form using the GET method. -->
  <form class="dashboard-year-filter" method="get">
    <label class="small text-muted" for="yearFilter">Reporting year</label>
    <select class="form-select" id="yearFilter" name="year" onchange="this.form.submit()">
      <?php foreach ($yearOptions as $year): ?><option value="<?= $year ?>" <?= $selectedYear === $year ? 'selected' : '' ?>><?= $year ?></option><?php endforeach; ?>
    </select>
  </form>
</div>

<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4 mb-4">
  <?php
  $summaryCards = [
    ['bi-cash-stack', 'Monthly earnings', money($monthlyIncome), 'Completed member payments'],
    ['bi-wallet2', 'Monthly expenses', money($monthlyExpenses), 'Recorded operating expenses'],
    ['bi-graph-up-arrow', 'Net result', money($netProfit), $netProfit >= 0 ? 'Positive this month' : 'Expenses exceed income'],
    ['bi-hourglass-split', 'Pending payments', $stats['pending_payments'], 'Waiting for admin verification'],
  ];
  ?>
  <?php foreach ($summaryCards as $card): ?>
    <!-- This div creates a responsive Bootstrap column inside the current row. -->
    <div class="col-sm-6 col-xl-3">
      <!-- This div creates a bordered content card for related information. -->
      <div class="stat-card analytics-stat-card">
        <!-- This div uses flexbox to align the child elements in this area. -->
        <div class="d-flex justify-content-between align-items-start"><!-- This div groups related content using the stat-icon layout class. -->
          <div class="stat-icon"><i class="bi <?= e($card[0]) ?>"></i></div><span class="analytics-chip">Live</span>
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
        <div><small class="text-muted">Revenue updates</small>
          <h2>Income vs expense</h2>
        </div>
        <span class="analytics-chip"><?= $selectedYear ?></span>
      </div>
      <!-- This div groups dashboard content for the current user role. -->
      <div class="dashboard-chart-lg"><canvas id="financeChart"></canvas></div>
    </div>
  </div>
  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-4">
    <!-- This div groups related page content using the “panel analytics-panel” layout style. -->
    <div class="panel analytics-panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title"><!-- This div groups related HTML content on this page. -->
        <div><small class="text-muted">Membership mix</small>
          <h2>Popular packages</h2>
        </div>
      </div>
      <!-- This div groups dashboard content for the current user role. -->
      <div class="dashboard-chart-md"><canvas id="packageChart"></canvas></div>
      <!-- This div holds a Chart.js canvas or chart-related information. -->
      <div class="chart-legend-list mt-3">
        <?php foreach ($packageMix as $row): ?><!-- This div groups related HTML content so the page structure is easier to manage. -->
          <div><span><?= e($row['package_name']) ?></span><strong><?= (int) $row['total'] ?></strong></div><?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4 mb-4">
  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-7">
    <!-- This div groups related page content using the “panel analytics-panel” layout style. -->
    <div class="panel analytics-panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title"><!-- This div groups related HTML content on this page. -->
        <div><small class="text-muted">Member growth</small>
          <h2>New registrations</h2>
        </div><strong><?= $stats['members'] ?> total</strong>
      </div>
      <!-- This div groups dashboard content for the current user role. -->
      <div class="dashboard-chart-md"><canvas id="memberGrowthChart"></canvas></div>
    </div>
  </div>
  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-5">
    <!-- This div groups related page content using the “panel analytics-panel” layout style. -->
    <div class="panel analytics-panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title"><!-- This div groups related HTML content on this page. -->
        <div><small class="text-muted">Coaching quality</small>
          <h2>Coach ratings</h2>
        </div><strong><?= number_format($averageRating, 1) ?>/5</strong>
      </div>
      <!-- This div groups dashboard content for the current user role. -->
      <div class="dashboard-chart-md"><canvas id="ratingChart"></canvas></div>
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
      <div class="panel-title"><!-- This div groups related HTML content on this page. -->
        <div><small class="text-muted">Latest activity</small>
          <h2>Recent payments</h2>
        </div><a href="payments.php" class="small text-decoration-none">View all</a>
      </div>
      <!-- This div allows this table to scroll safely on smaller screens. -->
      <div class="table-responsive">
        <!-- This table displays database records in rows and columns for easy reading. -->
        <table class="table table-modern">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Member</th>
              <th>Method</th>
              <th>Status</th>
              <th>Date</th>
              <th class="text-end">Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $payment): ?><tr>
                <td><a href="../receipt.php?id=<?= (int) $payment['payment_id'] ?>" class="fw-bold text-decoration-none"><?= e($payment['reference_number']) ?></a></td>
                <td><?= e($payment['full_name']) ?></td>
                <td><?= e($payment['payment_method']) ?></td>
                <td><?= statusBadge($payment['status']) ?></td>
                <td><?= shortDate($payment['payment_date']) ?></td>
                <td class="text-end fw-bold"><?= money($payment['amount']) ?></td>
              </tr><?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- This div creates a responsive Bootstrap column inside the current row. -->
  <div class="col-xl-4">
    <!-- This div groups related page content using the “panel” layout style. -->
    <div class="panel">
      <!-- This div groups related page content using the “panel-title” layout style. -->
      <div class="panel-title">
        <h2>Operational snapshot</h2>
      </div>
      <!-- This div groups related page content using the “analytics-list” layout style. -->
      <div class="analytics-list">
        <!-- This div groups related HTML content so the page structure is easier to manage. -->
        <div><span><i class="bi bi-people"></i>Total members</span><strong><?= $stats['members'] ?></strong></div>
        <!-- This div groups related HTML content so the page structure is easier to manage. -->
        <div><span><i class="bi bi-person-check"></i>Active memberships</span><strong><?= $stats['active_memberships'] ?></strong></div>
        <!-- This div groups related HTML content so the page structure is easier to manage. -->
        <div><span><i class="bi bi-person-badge"></i>Active coaches</span><strong><?= $stats['coaches'] ?></strong></div>
        <!-- This div groups related HTML content so the page structure is easier to manage. -->
        <div><span><i class="bi bi-star"></i>Average rating</span><strong><?= number_format($averageRating, 1) ?></strong></div>
      </div>
      <!-- This div groups items in a grid-style layout. -->
      <div class="d-grid gap-2 mt-4">
        <a class="quick-card" href="members.php?action=new"><i class="bi bi-person-plus"></i><!-- This div groups related HTML content on this page. -->
          <div><strong>Add member</strong><small class="d-block text-muted">Create member account</small></div>
        </a>
        <a class="quick-card" href="staff.php?action=new"><i class="bi bi-person-badge"></i><!-- This div groups related HTML content on this page. -->
          <div><strong>Add coach</strong><small class="d-block text-muted">Combined training + nutrition role</small></div>
        </a>
        <a class="quick-card" href="blogs.php?action=new"><i class="bi bi-journal-plus"></i><!-- This div groups related HTML content on this page. -->
          <div><strong>Publish article</strong><small class="d-block text-muted">Create gym content</small></div>
        </a>
      </div>
    </div>
  </div>
</div>

<script>
  const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const gridColor = 'rgba(65,65,65,.12)';
  const chartPalette = ['#FF6347', '#14B8A6', '#F4B942', '#3B82F6', '#8B5CF6', '#22C55E'];

  new Chart(document.getElementById('financeChart'), {
    type: 'line',
    data: {
      labels: monthLabels,
      datasets: [{
          label: 'Income',
          data: <?= json_encode($incomeSeries) ?>,
          borderColor: '#14B8A6',
          backgroundColor: 'rgba(20,184,166,.14)',
          fill: true,
          tension: .4,
          pointRadius: 3
        },
        {
          label: 'Expense',
          data: <?= json_encode($expenseSeries) ?>,
          borderColor: '#FF6347',
          backgroundColor: 'rgba(255,99,71,.08)',
          fill: false,
          tension: .4,
          pointRadius: 3
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false
      },
      plugins: {
        legend: {
          position: 'top',
          align: 'end'
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
          grid: {
            color: gridColor
          },
          ticks: {
            callback: value => 'LKR ' + Number(value).toLocaleString()
          }
        }
      }
    }
  });

  new Chart(document.getElementById('packageChart'), {
    type: 'doughnut',
    data: {
      labels: <?= json_encode(array_column($packageMix, 'package_name')) ?>,
      datasets: [{
        data: <?= json_encode(array_map('intval', array_column($packageMix, 'total'))) ?>,
        backgroundColor: chartPalette,
        borderWidth: 0
      }]
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
    }
  });

  new Chart(document.getElementById('memberGrowthChart'), {
    type: 'bar',
    data: {
      labels: monthLabels,
      datasets: [{
        label: 'New members',
        data: <?= json_encode($memberSeries) ?>,
        backgroundColor: '#3B82F6',
        borderRadius: 9
      }]
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
          },
          grid: {
            color: gridColor
          }
        }
      }
    }
  });

  new Chart(document.getElementById('ratingChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($ratingRows, 'coach_name')) ?>,
      datasets: [{
        label: 'Rating',
        data: <?= json_encode(array_map('floatval', array_column($ratingRows, 'average_rating'))) ?>,
        backgroundColor: '#F4B942',
        borderRadius: 8
      }]
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
          min: 0,
          max: 5,
          grid: {
            color: gridColor
          }
        },
        y: {
          grid: {
            display: false
          }
        }
      }
    }
  });
</script>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>