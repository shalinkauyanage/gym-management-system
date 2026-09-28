<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Admin');
$pageTitle = 'Reports';
$packageData = all($pdo, "SELECT p.package_name,COUNT(m.membership_id) total FROM packages p LEFT JOIN memberships m ON m.package_id=p.package_id GROUP BY p.package_id,p.package_name");
$payMethods = all($pdo, "SELECT payment_method,COUNT(*) total FROM payments GROUP BY payment_method");
$totals = ['payments' => (float)scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='Completed'"), 'members' => (int)scalar($pdo, "SELECT COUNT(*) FROM members"), 'ratings' => (float)scalar($pdo, "SELECT COALESCE(AVG(score),0) FROM trainer_ratings WHERE status='Visible'")];
require __DIR__ . '/../includes/dashboard_header.php'; ?>
<!-- This div creates a Bootstrap grid row for responsive columns. -->
<div class="row g-4 mb-4"><!-- This div creates a responsive Bootstrap column for this control or content. -->
    <div class="col-md-4"><!-- This div shows one dashboard summary value. -->
        <div class="stat-card"><!-- This div shows the main value for this summary item. -->
            <div class="value"><?= money($totals['payments']) ?></div><!-- This div shows the small label that explains the value above it. -->
            <div class="meta">Completed payments</div>
        </div>
    </div><!-- This div creates a responsive Bootstrap column for this control or content. -->
    <div class="col-md-4"><!-- This div shows one dashboard summary value. -->
        <div class="stat-card"><!-- This div shows the main value for this summary item. -->
            <div class="value"><?= $totals['members'] ?></div><!-- This div shows the small label that explains the value above it. -->
            <div class="meta">Registered members</div>
        </div>
    </div><!-- This div creates a responsive Bootstrap column for this control or content. -->
    <div class="col-md-4"><!-- This div shows one dashboard summary value. -->
        <div class="stat-card"><!-- This div shows the main value for this summary item. -->
            <div class="value"><?= number_format($totals['ratings'], 1) ?>/5</div><!-- This div shows the small label that explains the value above it. -->
            <div class="meta">Average coach rating</div>
        </div>
    </div>
</div><!-- This div creates a Bootstrap row for responsive layout. -->
<div class="row g-4"><!-- This div creates a responsive Bootstrap column for this control or content. -->
    <div class="col-lg-6"><!-- This div creates a dashboard panel for related information. -->
        <div class="panel"><!-- This div holds the heading for this dashboard panel. -->
            <div class="panel-title">
                <h2>Memberships by package</h2>
            </div><!-- This div groups related HTML content on this page. -->
            <div style="height:320px"><canvas id="pkgChart"></canvas></div>
        </div>
    </div><!-- This div creates a responsive Bootstrap column for this control or content. -->
    <div class="col-lg-6"><!-- This div creates a dashboard panel for related information. -->
        <div class="panel"><!-- This div holds the heading for this dashboard panel. -->
            <div class="panel-title">
                <h2>Payments by method</h2>
            </div><!-- This div groups related HTML content on this page. -->
            <div style="height:320px"><canvas id="methodChart"></canvas></div>
        </div>
    </div>
</div>
<script>
    new Chart(document.getElementById('pkgChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($packageData, 'package_name')) ?>,
            datasets: [{
                data: <?= json_encode(array_map('intval', array_column($packageData, 'total'))) ?>,
                backgroundColor: ['#FF6347', '#14B8A6', '#F4B942', '#3B82F6', '#8B5CF6', '#22C55E']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    new Chart(document.getElementById('methodChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($payMethods, 'payment_method')) ?>,
            datasets: [{
                data: <?= json_encode(array_map('intval', array_column($payMethods, 'total'))) ?>,
                backgroundColor: ['#3B82F6', '#14B8A6', '#F4B942', '#FF6347', '#8B5CF6'],
                borderRadius: 8
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
                }
            }
        }
    });
</script>
<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>