<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Member');

$pageTitle = 'My Meals';
$member = getMemberByUser($pdo, currentUser()['id']);
$plan = one(
  $pdo,
  "SELECT mp.*, COALESCE(NULLIF(t.display_name, ''), u.username) AS adviser
     FROM meal_plan mp
     JOIN nutrition_advisers n ON n.adviser_id = mp.adviser_id
     JOIN users u ON u.user_id = n.user_id
     LEFT JOIN trainers t ON t.user_id = u.user_id
     WHERE mp.member_id = ? AND mp.status = 'Active'
     ORDER BY mp.start_date DESC LIMIT 1",
  [$member['member_id']],
);
$items = $plan
  ? all(
    $pdo,
    "SELECT mi.*, f.food_name, f.unit, f.calories_per_unit, f.protein_g, f.carbs_g, f.fat_g
         FROM meal_items mi
         JOIN food_items f ON f.food_id = mi.food_id
         WHERE mi.meal_plan_id = ?
         ORDER BY FIELD(mi.day_of_week, 'Mon','Tue','Wed','Thu','Fri','Sat','Sun'), mi.meal_time, mi.meal_item_id",
    [$plan['plan_id']],
  )
  : [];

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<!-- This div groups related page content using the “panel” layout style. -->
<div class="panel">
  <!-- This div groups related page content using the “panel-title” layout style. -->
  <div class="panel-title">
    <!-- This div groups related HTML content so the page structure is easier to manage. -->
    <div>
      <small class="text-muted">Current nutrition plan</small>
      <h2><?= e($plan['plan_name'] ?? 'No active meal plan') ?></h2>
      <?php if ($plan): ?>
        <small class="text-muted">Coach: <?= e($plan['adviser']) ?> · target <?= e((string) $plan['daily_calories_target']) ?> kcal/day</small>
      <?php endif; ?>
    </div>
    <?php if ($plan): ?>
      <a class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" href="meal_plan_pdf.php?id=<?= (int) $plan['plan_id'] ?>">
        <span class="btn-ripple-circle"></span>
        <span class="btn-ripple-label"><i class="bi bi-file-earmark-pdf me-2"></i>Download PDF</span>
      </a>
    <?php endif; ?>
  </div>

  <?php if ($items): ?>
    <!-- This div allows this table to scroll safely on smaller screens. -->
    <div class="table-responsive">
      <!-- This table displays database records in rows and columns for easy reading. -->
      <table class="table table-modern align-middle">
        <thead>
          <tr>
            <th>Day</th>
            <th>Meal</th>
            <th>Food</th>
            <th>Quantity</th>
            <th>Nutrition / unit</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><span class="badge rounded-pill text-bg-dark"><?= e($item['day_of_week']) ?></span></td>
              <td><?= e($item['meal_type']) ?><small class="d-block text-muted"><?= e(substr((string) $item['meal_time'], 0, 5)) ?></small></td>
              <td><strong><?= e($item['food_name']) ?></strong></td>
              <td><?= e($item['quantity']) ?> <?= e($item['unit']) ?></td>
              <td><small><?= e($item['calories_per_unit']) ?> kcal · P <?= e($item['protein_g']) ?>g · C <?= e($item['carbs_g']) ?>g · F <?= e($item['fat_g']) ?>g</small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <!-- This div shows one summary value and its label. -->
    <div class="empty-state"><i class="bi bi-egg-fried"></i>No meal items are available yet.</div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>