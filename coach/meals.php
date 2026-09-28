<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Coach');

$pageTitle = 'Meal Plans';
$adviser = getAdviserByUser($pdo, currentUser()['id']);
if (!$adviser) {
    die('Coach nutrition profile is incomplete.');
}
$adviserId = (int) $adviser['adviser_id'];
$action = $_GET['action'] ?? 'list';
$viewId = (int) ($_GET['id'] ?? 0);
$editItemId = (int) ($_GET['item'] ?? 0);
$hasSharedAt = columnExists($pdo, 'meal_plan', 'shared_at');


//Checks that the signed-in Coach owns the requested meal plan.

function coachOwnsMealPlan(PDO $pdo, int $planId, int $adviserId): bool
{
    // Student function step: This is the start of coachOwnsMealPlan(). The lines below do the main work of this helper.
    return (int) scalar($pdo, 'SELECT COUNT(*) FROM meal_plan WHERE plan_id = ? AND adviser_id = ?', [$planId, $adviserId]) > 0;
}


// Finds an existing food item or creates a new reusable food record from coach input.

function resolveFoodForMeal(PDO $pdo, array $input): int
{
    // Student function step: This is the start of resolveFoodForMeal(). The lines below do the main work of this helper.
    $name = trim($input['food_name'] ?? '');
    $unit = trim($input['unit'] ?? 'serving');
    $category = trim($input['category'] ?? 'Custom');
    $calories = max(0, (float) ($input['calories_per_unit'] ?? 0));
    $protein = max(0, (float) ($input['protein_g'] ?? 0));
    $carbs = max(0, (float) ($input['carbs_g'] ?? 0));
    $fat = max(0, (float) ($input['fat_g'] ?? 0));

    if ($name === '' || $unit === '') {
        throw new RuntimeException('Food name and unit are required.');
    }

    $existing = one(
        $pdo,
        'SELECT food_id FROM food_items WHERE LOWER(food_name) = LOWER(?) AND LOWER(unit) = LOWER(?) AND calories_per_unit = ? AND protein_g = ? AND carbs_g = ? AND fat_g = ? ORDER BY food_id DESC LIMIT 1',
        [$name, $unit, $calories, $protein, $carbs, $fat],
    );
    if ($existing) {
        return (int) $existing['food_id'];
    }

    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare(
        'INSERT INTO food_items(food_name, category, unit, calories_per_unit, protein_g, carbs_g, fat_g, is_local) VALUES(?, ?, ?, ?, ?, ?, ?, 1)',
    )->execute([$name, $category ?: 'Custom', $unit, $calories, $protein, $carbs, $fat]);

    return (int) $pdo->lastInsertId();
}

// POST handling: only run this block when a form has been submitted.
//  Run this block only when the form is submitted with POST.
if (isPost()) {
    //  reject forged or expired form submissions before processing input.
    //  Check the CSRF token before saving, updating or deleting data.
    verifyCsrf();
    $mode = $_POST['mode'] ?? '';

    if ($mode === 'save_plan') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $memberId = (int) ($_POST['member_id'] ?? 0);
        $planName = trim($_POST['plan_name'] ?? '');
        $calorieTarget = $_POST['daily_calories_target'] !== '' ? (int) $_POST['daily_calories_target'] : null;
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?: null;
        $status = $_POST['status'] ?? 'Active';

        if (!$memberId || !coachCanAccessMember($pdo, currentUser()['id'], $memberId) || $planName === '' || !$startDate) {
            //  Save a one-time message so the next page can tell the user what happened.
            flash('danger', 'Choose an assigned member and complete the meal plan details.');
            redirect('coach/meals.php' . ($planId ? '?action=edit&id=' . $planId : '?action=new'));
        }

        if ($planId) {
            if (!coachOwnsMealPlan($pdo, $planId, $adviserId)) {
                http_response_code(403);
                die('Not allowed.');
            }
            //  Prepare and run this SQL statement using PDO.
            $pdo->prepare(
                'UPDATE meal_plan SET member_id = ?, plan_name = ?, daily_calories_target = ?, start_date = ?, end_date = ?, status = ? WHERE plan_id = ? AND adviser_id = ?',
            )->execute([$memberId, $planName, $calorieTarget, $startDate, $endDate, $status, $planId, $adviserId]);
            //  Save a one-time message so the next page can tell the user what happened.
            flash('success', 'Meal plan updated. Send it to the member when the changes are ready.');
            redirect('coach/meals.php?action=view&id=' . $planId . '&updated=1');
        }

        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
            "INSERT INTO meal_plan(member_id, adviser_id, plan_name, daily_calories_target, start_date, end_date, status) VALUES(?, ?, ?, ?, ?, ?, 'Active')",
        )->execute([$memberId, $adviserId, $planName, $calorieTarget, $startDate, $endDate]);
        $planId = (int) $pdo->lastInsertId();
        //  Save a one-time message so the next page can tell the user what happened.
        flash('success', 'Meal plan created. Add foods, then send the plan to the member when ready.');
        redirect('coach/meals.php?action=view&id=' . $planId . '&created=1');
    }

    if ($mode === 'delete_plan') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        if (!coachOwnsMealPlan($pdo, $planId, $adviserId)) {
            http_response_code(403);
            die('Not allowed.');
        }
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare('DELETE FROM meal_plan WHERE plan_id = ? AND adviser_id = ?')->execute([$planId, $adviserId]);
        flash('success', 'Meal plan deleted.');
        //  Redirect the browser after this action to avoid repeating the same request.
        redirect('coach/meals.php');
    }

    if ($mode === 'save_item') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $itemId = (int) ($_POST['item_id'] ?? 0);
        if (!coachOwnsMealPlan($pdo, $planId, $adviserId)) {
            http_response_code(403);
            die('Not allowed.');
        }

        try {
            $foodId = resolveFoodForMeal($pdo, $_POST);
        } catch (RuntimeException $error) {
            //  Save a one-time message so the next page can tell the user what happened.
            flash('danger', $error->getMessage());
            redirect('coach/meals.php?action=view&id=' . $planId);
        }

        $values = [
            $foodId,
            $_POST['day_of_week'] ?? 'Mon',
            $_POST['meal_time'] ?: null,
            $_POST['meal_type'] ?? 'Breakfast',
            max(0.01, (float) ($_POST['quantity'] ?? 1)),
        ];

        if ($itemId) {
            $ownsItem = (int) scalar(
                $pdo,
                'SELECT COUNT(*) FROM meal_items mi JOIN meal_plan mp ON mp.plan_id = mi.meal_plan_id WHERE mi.meal_item_id = ? AND mi.meal_plan_id = ? AND mp.adviser_id = ?',
                [$itemId, $planId, $adviserId],
            );
            if (!$ownsItem) {
                http_response_code(403);
                die('Not allowed.');
            }
            //  Prepare and run this SQL statement using PDO.
            $pdo->prepare(
                'UPDATE meal_items SET food_id = ?, day_of_week = ?, meal_time = ?, meal_type = ?, quantity = ? WHERE meal_item_id = ? AND meal_plan_id = ?',
            )->execute([...$values, $itemId, $planId]);
            //  Save a one-time message so the next page can tell the user what happened.
            flash('success', 'Meal item updated.');
        } else {
            //  Prepare and run this SQL statement using PDO.
            $pdo->prepare(
                'INSERT INTO meal_items(meal_plan_id, food_id, day_of_week, meal_time, meal_type, quantity) VALUES(?, ?, ?, ?, ?, ?)',
            )->execute([$planId, ...$values]);
            //  Save a one-time message so the next page can tell the user what happened.
            flash('success', 'Meal item added.');
        }
        //  Redirect the browser after this action to avoid repeating the same request.
        redirect('coach/meals.php?action=view&id=' . $planId);
    }

    if ($mode === 'delete_item') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $itemId = (int) ($_POST['item_id'] ?? 0);
        if (!coachOwnsMealPlan($pdo, $planId, $adviserId)) {
            http_response_code(403);
            die('Not allowed.');
        }
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare('DELETE FROM meal_items WHERE meal_item_id = ? AND meal_plan_id = ?')->execute([$itemId, $planId]);
        flash('success', 'Meal item removed.');
        //  Redirect the browser after this action to avoid repeating the same request.
        redirect('coach/meals.php?action=view&id=' . $planId);
    }

    if ($mode === 'share_plan') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        if (!coachOwnsMealPlan($pdo, $planId, $adviserId)) {
            http_response_code(403);
            die('Not allowed.');
        }
        $plan = one($pdo, 'SELECT * FROM meal_plan WHERE plan_id = ?', [$planId]);
        $memberUserId = memberUserId($pdo, (int) $plan['member_id']);
        if ($memberUserId) {
            notifyUser(
                $pdo,
                $memberUserId,
                'Meal plan ready',
                'Your coach has shared or updated your meal plan. Open My Meals to review it and download the PDF.',
                'Nutrition',
            );
            if ($hasSharedAt) {
                //  Prepare and run this SQL statement using PDO.
                $pdo->prepare('UPDATE meal_plan SET shared_at = NOW() WHERE plan_id = ?')->execute([$planId]);
            }
            //  Save a one-time message so the next page can tell the user what happened.
            flash('success', 'Meal plan sent to the member through notifications.');
        }
        //  Redirect the browser after this action to avoid repeating the same request.
        redirect('coach/meals.php?action=view&id=' . $planId);
    }
}

$members = tableExists($pdo, 'nutrition_assignments')
    ? all(
        $pdo,
        "SELECT m.* FROM nutrition_assignments a JOIN members m ON m.member_id = a.member_id
         WHERE a.adviser_id = ? AND a.status = 'Active' AND m.status = 'Active' ORDER BY m.full_name",
        [$adviserId],
    )
    : all($pdo, "SELECT * FROM members WHERE status = 'Active' ORDER BY full_name");

$foodSuggestions = all($pdo, 'SELECT DISTINCT food_name FROM food_items ORDER BY food_name');
$plans = all(
    $pdo,
    "SELECT p.*, m.full_name, (SELECT COUNT(*) FROM meal_items mi WHERE mi.meal_plan_id = p.plan_id) AS item_count
     FROM meal_plan p JOIN members m ON m.member_id = p.member_id
     WHERE p.adviser_id = ? ORDER BY p.start_date DESC, p.plan_id DESC",
    [$adviserId],
);
$view = $viewId
    ? one(
        $pdo,
        'SELECT p.*, m.full_name, m.member_number FROM meal_plan p JOIN members m ON m.member_id = p.member_id WHERE p.plan_id = ? AND p.adviser_id = ?',
        [$viewId, $adviserId],
    )
    : null;
$items = $view
    ? all(
        $pdo,
        "SELECT mi.*, f.food_name, f.category, f.unit, f.calories_per_unit, f.protein_g, f.carbs_g, f.fat_g
         FROM meal_items mi JOIN food_items f ON f.food_id = mi.food_id
         WHERE mi.meal_plan_id = ?
         ORDER BY FIELD(mi.day_of_week, 'Mon','Tue','Wed','Thu','Fri','Sat','Sun'), mi.meal_time, mi.meal_item_id",
        [$viewId],
    )
    : [];
$editItem = $view && $editItemId
    ? one(
        $pdo,
        'SELECT mi.*, f.food_name, f.category, f.unit, f.calories_per_unit, f.protein_g, f.carbs_g, f.fat_g FROM meal_items mi JOIN food_items f ON f.food_id = mi.food_id WHERE mi.meal_item_id = ? AND mi.meal_plan_id = ?',
        [$editItemId, $viewId],
    )
    : null;
$editPlan = ($action === 'edit' && $viewId)
    ? one($pdo, 'SELECT * FROM meal_plan WHERE plan_id = ? AND adviser_id = ?', [$viewId, $adviserId])
    : null;
$selectedMemberId = (int) ($_GET['member'] ?? ($editPlan['member_id'] ?? 0));

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<?php if ($action === 'new' || ($action === 'edit' && $editPlan)): ?>
    <!-- This div groups related page content using the “panel” layout style. -->
    <div class="panel">
        <!-- This div groups related page content using the “panel-title” layout style. -->
        <div class="panel-title"><!-- This div groups related HTML content on this page. -->
            <div><small class="text-muted">Meal plan editor</small>
                <h2><?= $editPlan ? 'Edit meal plan' : 'Create meal plan' ?></h2>
            </div><a href="meals.php" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
        </div>
        <!-- This form collects user input and submits this form using the POST method. -->
        <form method="post" class="row g-3" data-loading-form>
            <?= csrfField() ?><input type="hidden" name="mode" value="save_plan"><input type="hidden" name="plan_id" value="<?= (int) ($editPlan['plan_id'] ?? 0) ?>">
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-6"><label class="form-label">Member</label><select class="form-select" name="member_id" required><?php foreach ($members as $member): ?><option value="<?= (int) $member['member_id'] ?>" <?= $selectedMemberId === (int) $member['member_id'] ? 'selected' : '' ?>><?= e($member['member_number'] . ' — ' . $member['full_name']) ?></option><?php endforeach; ?></select></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-6"><label class="form-label">Plan name</label><input class="form-control" name="plan_name" required value="<?= e($editPlan['plan_name'] ?? '') ?>" placeholder="e.g. Balanced weekly meal routine"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-3"><label class="form-label">Daily calorie target</label><input class="form-control" type="number" min="0" name="daily_calories_target" value="<?= e((string) ($editPlan['daily_calories_target'] ?? '')) ?>"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-3"><label class="form-label">Start date</label><input class="form-control" type="date" name="start_date" value="<?= e($editPlan['start_date'] ?? date('Y-m-d')) ?>" required></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-3"><label class="form-label">End date</label><input class="form-control" type="date" name="end_date" value="<?= e($editPlan['end_date'] ?? '') ?>"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['Active', 'Completed', 'Cancelled'] as $status): ?><option <?= ($editPlan['status'] ?? 'Active') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-12"><button class="btn btn-dark rounded-pill px-4"><?= $editPlan ? 'Update plan' : 'Create plan' ?></button></div>
        </form>
    </div>

<?php elseif ($action === 'view' && $view): ?>
    <!-- This div groups related page content using the “panel mb-4” layout style. -->
    <div class="panel mb-4">
        <!-- This div groups related page content using the “panel-title” layout style. -->
        <div class="panel-title">
            <!-- This div groups related HTML content so the page structure is easier to manage. -->
            <div><small class="text-muted">Meal plan #<?= $viewId ?> · <?= e($view['member_number']) ?></small>
                <h2><?= e($view['full_name']) ?> — <?= e($view['plan_name']) ?></h2>
            </div>
            <!-- This div uses flexbox to align the child elements in this area. -->
            <div class="d-flex flex-wrap gap-2">
                <a href="?action=edit&id=<?= $viewId ?>" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="bi bi-pencil me-1"></i>Edit plan</a>
                <!-- This form collects user input and submits this form using the POST method. -->
                <form method="post"><?= csrfField() ?><input type="hidden" name="mode" value="share_plan"><input type="hidden" name="plan_id" value="<?= $viewId ?>"><button class="btn btn-sm btn-danger rounded-pill"><i class="bi bi-send me-1"></i>Send to member</button></form>
                <!-- This form collects user input and submits this form using the POST method. -->
                <form method="post"><?= csrfField() ?><input type="hidden" name="mode" value="delete_plan"><input type="hidden" name="plan_id" value="<?= $viewId ?>"><button class="btn btn-sm btn-outline-danger rounded-pill" data-confirm="Delete this meal plan and all meal items?">Delete</button></form>
                <a href="meals.php" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
            </div>
        </div>
        <!-- This div uses flexbox to align the child elements in this area. -->
        <div class="d-flex flex-wrap gap-2 mb-3"><?= statusBadge($view['status']) ?><span class="badge text-bg-light rounded-pill"><?= $view['daily_calories_target'] ? e((string) $view['daily_calories_target']) . ' kcal/day' : 'No calorie target' ?></span><?php if ($hasSharedAt && !empty($view['shared_at'])): ?><span class="badge text-bg-light rounded-pill"><i class="bi bi-send-check me-1"></i>Last sent <?= date('d M H:i', strtotime($view['shared_at'])) ?></span><?php endif; ?></div>

        <!-- This div allows this table to scroll safely on smaller screens. -->
        <div class="table-responsive">
            <table class="table table-modern align-middle">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Meal</th>
                        <th>Food</th>
                        <th>Quantity</th>
                        <th>Nutrition / unit</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?><tr>
                            <td><?= e($item['day_of_week']) ?></td>
                            <td><?= $item['meal_time'] ? e(substr((string) $item['meal_time'], 0, 5)) : '—' ?></td>
                            <td><?= e($item['meal_type']) ?></td>
                            <td><strong><?= e($item['food_name']) ?></strong><small class="d-block text-muted"><?= e($item['category']) ?></small></td>
                            <td><?= e($item['quantity']) ?> <?= e($item['unit']) ?></td>
                            <td><small><?= e($item['calories_per_unit']) ?> kcal · P <?= e($item['protein_g']) ?>g · C <?= e($item['carbs_g']) ?>g · F <?= e($item['fat_g']) ?>g</small></td>
                            <td class="text-end"><!-- This div keeps these small action buttons on the same line. -->
                                <div class="d-inline-flex gap-1"><a class="btn btn-sm btn-outline-secondary rounded-pill" href="?action=view&id=<?= $viewId ?>&item=<?= (int) $item['meal_item_id'] ?>#meal-editor">Edit</a>
                                    <form method="post"><?= csrfField() ?><input type="hidden" name="mode" value="delete_item"><input type="hidden" name="plan_id" value="<?= $viewId ?>"><input type="hidden" name="item_id" value="<?= (int) $item['meal_item_id'] ?>"><button class="btn btn-sm btn-outline-danger rounded-pill" data-confirm="Remove this meal item?">Delete</button></form>
                                </div>
                            </td>
                        </tr><?php endforeach; ?>
                    <?php if (!$items): ?><tr>
                            <td colspan="7" class="text-center text-muted py-5">No foods yet. Add any food below.</td>
                        </tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- This div groups the content for the “meal-editor” area of this page. -->
    <div class="panel" id="meal-editor">
        <!-- This div groups related page content using the “panel-title” layout style. -->
        <div class="panel-title"><!-- This div groups related HTML content on this page. -->
            <div><small class="text-muted">No fixed food dropdown</small>
                <h2><?= $editItem ? 'Edit meal item' : 'Add any food' ?></h2>
            </div><?php if ($editItem): ?><a class="btn btn-sm btn-outline-secondary rounded-pill" href="?action=view&id=<?= $viewId ?>#meal-editor">Cancel edit</a><?php endif; ?>
        </div>
        <p class="text-muted">Type any food name and enter its unit and nutrition values. Existing foods appear as suggestions, but you are not restricted to the list.</p>
        <!-- This form collects user input and submits this form using the POST method. -->
        <form method="post" class="row g-3">
            <?= csrfField() ?><input type="hidden" name="mode" value="save_item"><input type="hidden" name="plan_id" value="<?= $viewId ?>"><input type="hidden" name="item_id" value="<?= (int) ($editItem['meal_item_id'] ?? 0) ?>">
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-4"><label class="form-label">Food name</label><input class="form-control" name="food_name" list="foodSuggestions" required value="<?= e($editItem['food_name'] ?? '') ?>" placeholder="e.g. Red rice, dhal curry, banana"><datalist id="foodSuggestions"><?php foreach ($foodSuggestions as $food): ?><option value="<?= e($food['food_name']) ?>"><?php endforeach; ?></datalist></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-2"><label class="form-label">Category</label><input class="form-control" name="category" value="<?= e($editItem['category'] ?? 'Custom') ?>" placeholder="Carb / Protein"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-2"><label class="form-label">Unit</label><input class="form-control" name="unit" required value="<?= e($editItem['unit'] ?? '100g') ?>" placeholder="100g / cup / piece"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-2"><label class="form-label">Day</label><select class="form-select" name="day_of_week"><?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day): ?><option <?= ($editItem['day_of_week'] ?? 'Mon') === $day ? 'selected' : '' ?>><?= $day ?></option><?php endforeach; ?></select></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-2"><label class="form-label">Meal</label><select class="form-select" name="meal_type"><?php foreach (['Breakfast', 'Lunch', 'Dinner', 'Snack'] as $mealType): ?><option <?= ($editItem['meal_type'] ?? 'Breakfast') === $mealType ? 'selected' : '' ?>><?= $mealType ?></option><?php endforeach; ?></select></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-2"><label class="form-label">Time</label><input class="form-control" type="time" name="meal_time" value="<?= e(substr((string) ($editItem['meal_time'] ?? ''), 0, 5)) ?>"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-2"><label class="form-label">Quantity</label><input class="form-control" type="number" step="0.01" min="0.01" name="quantity" required value="<?= e((string) ($editItem['quantity'] ?? 1)) ?>"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-2"><label class="form-label">Calories / unit</label><input class="form-control" type="number" step="0.01" min="0" name="calories_per_unit" value="<?= e((string) ($editItem['calories_per_unit'] ?? 0)) ?>"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-2"><label class="form-label">Protein g</label><input class="form-control" type="number" step="0.01" min="0" name="protein_g" value="<?= e((string) ($editItem['protein_g'] ?? 0)) ?>"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-2"><label class="form-label">Carbs g</label><input class="form-control" type="number" step="0.01" min="0" name="carbs_g" value="<?= e((string) ($editItem['carbs_g'] ?? 0)) ?>"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-2"><label class="form-label">Fat g</label><input class="form-control" type="number" step="0.01" min="0" name="fat_g" value="<?= e((string) ($editItem['fat_g'] ?? 0)) ?>"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-12"><button class="btn btn-dark rounded-pill px-4"><?= $editItem ? 'Update food' : 'Add food to plan' ?></button></div>
        </form>
    </div>

    <?php if (isset($_GET['created']) || isset($_GET['updated'])): ?>
        <!-- This div creates the popup content area shown above the current page. -->
        <div class="modal fade" id="shareMealModal" tabindex="-1"><!-- This div groups related content using the modal-dialog layout class. -->
            <div class="modal-dialog modal-dialog-centered"><!-- This div holds all visible content inside this popup. -->
                <div class="modal-content border-0 rounded-4"><!-- This div holds the popup title and close button. -->
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Send meal plan to member?</h5><button class="btn-close" data-bs-dismiss="modal"></button>
                    </div><!-- This div holds the main message/content inside this popup. -->
                    <div class="modal-body pt-0">
                        <p class="text-muted mb-0">The plan is saved. Send a notification when the member should review the meal plan.</p>
                    </div><!-- This div holds the popup action buttons. -->
                    <div class="modal-footer border-0"><button class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Not yet</button>
                        <form method="post"><?= csrfField() ?><input type="hidden" name="mode" value="share_plan"><input type="hidden" name="plan_id" value="<?= $viewId ?>"><button class="btn btn-danger rounded-pill px-4"><i class="bi bi-send me-2"></i>Send to member</button></form>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('shareMealModal')).show());
        </script>
    <?php endif; ?>

<?php else: ?>
    <!-- This div groups related page content using the “panel” layout style. -->
    <div class="panel"><!-- This div holds the heading for this dashboard panel. -->
        <div class="panel-title"><!-- This div groups related HTML content on this page. -->
            <div><small class="text-muted">Nutrition plans</small>
                <h2>Meal plans</h2>
            </div> <a class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" href="?action=new">
                <span class="btn-ripple-circle"></span>
                <span class="btn-ripple-label"><i class="bi bi-plus-lg me-1"></i>New plan</span>
            </a>
        </div><!-- This div allows the table to scroll on smaller screens. -->
        <div class="table-responsive">
            <table class="table table-modern align-middle">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Plan</th>
                        <th>Target</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Sent</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $plan): ?><tr>
                            <td><strong><?= e($plan['full_name']) ?></strong></td>
                            <td><?= e($plan['plan_name']) ?></td>
                            <td><?= $plan['daily_calories_target'] ? e((string) $plan['daily_calories_target']) . ' kcal' : '—' ?></td>
                            <td><?= (int) $plan['item_count'] ?></td>
                            <td><?= statusBadge($plan['status']) ?></td>
                            <td><?= $hasSharedAt && !empty($plan['shared_at']) ? '<span class="badge text-bg-success rounded-pill">Sent</span>' : '<span class="badge text-bg-light rounded-pill">Draft</span>' ?></td>
                            <td class="text-end"><a href="?action=view&id=<?= (int) $plan['plan_id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill">Open</a></td>
                        </tr><?php endforeach; ?>
                    <?php if (!$plans): ?><tr>
                            <td colspan="7" class="text-center text-muted py-5">No meal plans yet.</td>
                        </tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>