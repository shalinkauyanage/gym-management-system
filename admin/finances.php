<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Admin');
$pageTitle = 'Finances';
if (!tableExists($pdo, 'financial_transactions')) {
    require __DIR__ . '/../includes/dashboard_header.php';
    echo '<div class="alert alert-warning">Run <strong>database/upgrade.sql</strong> to enable income and expense management.</div>';
    require __DIR__ . '/../includes/dashboard_footer.php';
    exit;
}
//  Run this block only when the form is submitted with POST.
if (isPost()) {
    verifyCsrf();
    $type = $_POST['transaction_type'];
    $category = trim($_POST['category']);
    $amount = (float)$_POST['amount'];
    $date = $_POST['transaction_date'];
    $notes = trim($_POST['notes'] ?? '');
    $pdo->prepare("INSERT INTO financial_transactions(transaction_type,category,amount,transaction_date,notes,created_by) VALUES(?,?,?,?,?,?)")->execute([$type, $category, $amount, $date, $notes, currentUser()['id']]);
    flash('success', 'Financial transaction added.');
    redirect('admin/finances.php');
}
$rows = all($pdo, "SELECT f.*,u.username FROM financial_transactions f LEFT JOIN users u ON u.user_id=f.created_by ORDER BY f.transaction_date DESC,f.transaction_id DESC");
$income = (float)scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM financial_transactions WHERE transaction_type='Income'");
$expense = (float)scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM financial_transactions WHERE transaction_type='Expense'");
$monthly = all($pdo, "SELECT DATE_FORMAT(transaction_date,'%Y-%m') ym, DATE_FORMAT(transaction_date,'%b') label, SUM(CASE WHEN transaction_type='Income' THEN amount ELSE 0 END) income, SUM(CASE WHEN transaction_type='Expense' THEN amount ELSE 0 END) expense FROM financial_transactions GROUP BY DATE_FORMAT(transaction_date,'%Y-%m') ORDER BY ym DESC LIMIT 6");
$monthly = array_reverse($monthly);
require __DIR__ . '/../includes/dashboard_header.php'; ?>
<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4 mb-4"><!-- This div creates a responsive Bootstrap column for this control or content. -->
    <div class="col-md-4"><!-- This div shows one dashboard summary value. -->
        <div class="stat-card"><!-- This div shows the main value for this summary item. -->
            <div class="value"><?= money($income) ?></div><!-- This div shows the small label that explains the value above it. -->
            <div class="meta">Total income</div>
        </div>
    </div><!-- This div creates a responsive Bootstrap column for this control or content. -->
    <div class="col-md-4"><!-- This div shows one dashboard summary value. -->
        <div class="stat-card"><!-- This div shows the main value for this summary item. -->
            <div class="value"><?= money($expense) ?></div><!-- This div shows the small label that explains the value above it. -->
            <div class="meta">Total expenses</div>
        </div>
    </div><!-- This div creates a responsive Bootstrap column for this control or content. -->
    <div class="col-md-4"><!-- This div shows one dashboard summary value. -->
        <div class="stat-card"><!-- This div shows the main value for this summary item. -->
            <div class="value"><?= money($income - $expense) ?></div><!-- This div shows the small label that explains the value above it. -->
            <div class="meta">Net profit</div>
        </div>
    </div>
</div>
<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4"><!-- This div creates a responsive Bootstrap column for this control or content. -->
    <div class="col-xl-7"><!-- This div creates a dashboard panel for related information. -->
        <div class="panel mb-4"><!-- This div holds the heading for this dashboard panel. -->
            <div class="panel-title">
                <h2>Income vs expense</h2>
            </div><!-- This div groups related HTML content on this page. -->
            <div style="height:280px"><canvas id="financeChart"></canvas></div>
        </div><!-- This div creates a dashboard panel for related information. -->
        <div class="panel"><!-- This div holds the heading for this dashboard panel. -->
            <div class="panel-title">
                <h2>Transaction history</h2>
            </div><!-- This div allows the table to scroll on smaller screens. -->
            <div class="table-responsive">
                <table class="table table-modern">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Notes</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($rows as $r): ?><tr>
                                <td><?= shortDate($r['transaction_date']) ?></td>
                                <td><?= statusBadge($r['transaction_type'] === 'Income' ? 'Completed' : 'Pending') ?> <?= e($r['transaction_type']) ?></td>
                                <td><?= e($r['category']) ?></td>
                                <td><?= e($r['notes']) ?></td>
                                <td class="text-end fw-bold"><?= money($r['amount']) ?></td>
                            </tr><?php endforeach; ?></tbody>
                </table>
            </div>
        </div>
    </div><!-- This div creates a responsive Bootstrap column for this control or content. -->
    <div class="col-xl-5"><!-- This div creates a dashboard panel for related information. -->
        <div class="panel"><!-- This div holds the heading for this dashboard panel. -->
            <div class="panel-title">
                <h2>Add transaction</h2>
            </div>
            <form method="post" class="row g-3"><?= csrfField() ?><!-- This div creates a responsive Bootstrap column inside the current row. -->
                <div class="col-12"><label class="form-label">Type</label><select class="form-select" name="transaction_type">
                        <option>Income</option>
                        <option>Expense</option>
                    </select></div><!-- This div creates a responsive Bootstrap column for this control or content. -->
                <div class="col-12"><label class="form-label">Category</label><input class="form-control" name="category" placeholder="Membership fee, Rent, Electricity..." required></div><!-- This div creates a responsive Bootstrap column for this control or content. -->
                <div class="col-12"><label class="form-label">Amount</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" required></div><!-- This div creates a responsive Bootstrap column for this control or content. -->
                <div class="col-12"><label class="form-label">Date</label><input class="form-control" type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" required></div><!-- This div creates a responsive Bootstrap column for this control or content. -->
                <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" rows="3" name="notes"></textarea></div><!-- This div creates a responsive Bootstrap column for this control or content. -->
                <div class="col-12"><button class="btn btn-dark rounded-pill px-4">Save transaction</button></div>
            </form>
        </div>
    </div>
</div>
<script>
    new Chart(document.getElementById('financeChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($monthly, 'label')) ?>,
            datasets: [{
                label: 'Income',
                data: <?= json_encode(array_map('floatval', array_column($monthly, 'income'))) ?>,
                backgroundColor: '#14B8A6',
                borderRadius: 7
            }, {
                label: 'Expense',
                data: <?= json_encode(array_map('floatval', array_column($monthly, 'expense'))) ?>,
                backgroundColor: '#FF6347',
                borderRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
</script>
<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>