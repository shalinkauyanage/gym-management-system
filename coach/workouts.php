<?php

/**
 * --------------------------------------------------------------------------
 * STUDENT VIVA EXPLANATION (simple English)
 * File: coach/workouts.php
 * Main user/area: Coach
 * Simple purpose: This file handles the Workouts part of PowerFit.
 * How to explain: PHP prepares/validates the data first, then the HTML shows
 * the result to the user. Database work is done with PDO prepared statements.
 * --------------------------------------------------------------------------
 */


/**
 * ============================================================================
 * POWERFIT VIVA FILE GUIDE
 * File: coach/workouts.php
 * Purpose: Lets a Coach create, edit, delete and send workout plans/items to assigned members.
 *
 * Viva flow to explain:
 * 1. Load shared configuration/helpers.
 * 2. Apply login/role/ownership security where the page is protected.
 * 3. Validate submitted data and CSRF tokens before changing records.
 * 4. Use PDO prepared statements for MySQL reads/writes.
 * 5. Pass safe/escaped values to the HTML view.
 * ============================================================================
 */
//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Coach');

$pageTitle = 'Workout Plans';
$trainer = getTrainerByUser($pdo, currentUser()['id']);
if (!$trainer) {
    die('Coach trainer profile is incomplete.');
}
$trainerId = (int) $trainer['trainer_id'];
$action = $_GET['action'] ?? 'list';
$viewId = (int) ($_GET['id'] ?? 0);
$editItemId = (int) ($_GET['item'] ?? 0);
$hasSharedAt = columnExists($pdo, 'workout_plans', 'shared_at');

// Checks that the signed-in Coach owns the requested workout plan.
 
function coachOwnsWorkoutPlan(PDO $pdo, int $planId, int $trainerId): bool
{
    // Student function step: This is the start of coachOwnsWorkoutPlan(). The lines below do the main work of this helper.
    return (int) scalar($pdo, 'SELECT COUNT(*) FROM workout_plans WHERE plan_id = ? AND trainer_id = ?', [$planId, $trainerId]) > 0;
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
        $goal = trim($_POST['fitness_goal'] ?? '');
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?: null;
        $status = $_POST['status'] ?? 'Active';

        if (!$memberId || !coachCanAccessMember($pdo, currentUser()['id'], $memberId) || $goal === '' || !$startDate) {
            //  Save a one-time message so the next page can tell the user what happened.
            flash('danger', 'Choose an assigned member and complete the workout plan details.');
            redirect('coach/workouts.php' . ($planId ? '?action=edit&id=' . $planId : '?action=new'));
        }

        if ($planId) {
            if (!coachOwnsWorkoutPlan($pdo, $planId, $trainerId)) {
                http_response_code(403);
                die('Not allowed.');
            }
            //  Prepare and run this SQL statement using PDO.
            $pdo->prepare(
                'UPDATE workout_plans SET member_id = ?, fitness_goal = ?, start_date = ?, end_date = ?, status = ? WHERE plan_id = ? AND trainer_id = ?',
            )->execute([$memberId, $goal, $startDate, $endDate, $status, $planId, $trainerId]);
            //  Save a one-time message so the next page can tell the user what happened.
            flash('success', 'Workout plan updated. You can send the updated plan to the member from the plan page.');
            redirect('coach/workouts.php?action=view&id=' . $planId . '&updated=1');
        }

        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
            "INSERT INTO workout_plans(member_id, trainer_id, fitness_goal, start_date, end_date, status) VALUES(?, ?, ?, ?, ?, 'Active')",
        )->execute([$memberId, $trainerId, $goal, $startDate, $endDate]);
        $planId = (int) $pdo->lastInsertId();
        //  Save a one-time message so the next page can tell the user what happened.
        flash('success', 'Workout plan created. Add exercises, then send it to the member when ready.');
        redirect('coach/workouts.php?action=view&id=' . $planId . '&created=1');
    }

    if ($mode === 'delete_plan') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        if (!coachOwnsWorkoutPlan($pdo, $planId, $trainerId)) {
            http_response_code(403);
            die('Not allowed.');
        }
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare('DELETE FROM workout_plans WHERE plan_id = ? AND trainer_id = ?')->execute([$planId, $trainerId]);
        flash('success', 'Workout plan deleted.');
        //  Redirect the browser after this action to avoid repeating the same request.
        redirect('coach/workouts.php');
    }

    if ($mode === 'save_item') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $itemId = (int) ($_POST['item_id'] ?? 0);
        if (!coachOwnsWorkoutPlan($pdo, $planId, $trainerId)) {
            http_response_code(403);
            die('Not allowed.');
        }

        $exercise = trim($_POST['exercise_name'] ?? '');
        if ($exercise === '') {
            //  Save a one-time message so the next page can tell the user what happened.
            flash('danger', 'Exercise name is required.');
            redirect('coach/workouts.php?action=view&id=' . $planId);
        }

        $values = [
            $exercise,
            $_POST['sets'] !== '' ? (int) $_POST['sets'] : null,
            $_POST['repetitions'] !== '' ? (int) $_POST['repetitions'] : null,
            $_POST['duration_minutes'] !== '' ? (int) $_POST['duration_minutes'] : null,
            $_POST['rest_seconds'] !== '' ? (int) $_POST['rest_seconds'] : null,
            $_POST['day_of_week'] ?? 'Mon',
            trim($_POST['instructions'] ?? ''),
        ];

        if ($itemId) {
            $ownsItem = (int) scalar(
                $pdo,
                'SELECT COUNT(*) FROM workout_items wi JOIN workout_plans wp ON wp.plan_id = wi.plan_id WHERE wi.item_id = ? AND wi.plan_id = ? AND wp.trainer_id = ?',
                [$itemId, $planId, $trainerId],
            );
            if (!$ownsItem) {
                http_response_code(403);
                die('Not allowed.');
            }
            //  Prepare and run this SQL statement using PDO.
            $pdo->prepare(
                'UPDATE workout_items SET exercise_name = ?, sets = ?, repetitions = ?, duration_minutes = ?, rest_seconds = ?, day_of_week = ?, instructions = ? WHERE item_id = ? AND plan_id = ?',
            )->execute([...$values, $itemId, $planId]);
            //  Save a one-time message so the next page can tell the user what happened.
            flash('success', 'Exercise updated.');
        } else {
            //  Prepare and run this SQL statement using PDO.
            $pdo->prepare(
                'INSERT INTO workout_items(plan_id, exercise_name, sets, repetitions, duration_minutes, rest_seconds, day_of_week, instructions) VALUES(?, ?, ?, ?, ?, ?, ?, ?)',
            )->execute([$planId, ...$values]);
            //  Save a one-time message so the next page can tell the user what happened.
            flash('success', 'Exercise added.');
        }
        //  Redirect the browser after this action to avoid repeating the same request.
        redirect('coach/workouts.php?action=view&id=' . $planId);
    }

    if ($mode === 'delete_item') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $itemId = (int) ($_POST['item_id'] ?? 0);
        if (!coachOwnsWorkoutPlan($pdo, $planId, $trainerId)) {
            http_response_code(403);
            die('Not allowed.');
        }
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare('DELETE FROM workout_items WHERE item_id = ? AND plan_id = ?')->execute([$itemId, $planId]);
        flash('success', 'Exercise removed.');
        //  Redirect the browser after this action to avoid repeating the same request.
        redirect('coach/workouts.php?action=view&id=' . $planId);
    }

    if ($mode === 'share_plan') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        if (!coachOwnsWorkoutPlan($pdo, $planId, $trainerId)) {
            http_response_code(403);
            die('Not allowed.');
        }
        $plan = one($pdo, 'SELECT * FROM workout_plans WHERE plan_id = ?', [$planId]);
        $memberUserId = memberUserId($pdo, (int) $plan['member_id']);
        if ($memberUserId) {
            notifyUser(
                $pdo,
                $memberUserId,
                'Workout plan ready',
                'Your coach has shared or updated your workout plan. Open My Workout to review it and download the PDF.',
                'Workout',
            );
            if ($hasSharedAt) {
                //  Prepare and run this SQL statement using PDO.
                $pdo->prepare('UPDATE workout_plans SET shared_at = NOW() WHERE plan_id = ?')->execute([$planId]);
            }
            //  Save a one-time message so the next page can tell the user what happened.
            flash('success', 'Workout plan sent to the member through notifications.');
        }
        //  Redirect the browser after this action to avoid repeating the same request.
        redirect('coach/workouts.php?action=view&id=' . $planId);
    }
}

$members = tableExists($pdo, 'trainer_assignments')
    ? all(
        $pdo,
        "SELECT m.* FROM trainer_assignments a JOIN members m ON m.member_id = a.member_id
         WHERE a.trainer_id = ? AND a.status = 'Active' AND m.status = 'Active' ORDER BY m.full_name",
        [$trainerId],
    )
    : all($pdo, "SELECT * FROM members WHERE status = 'Active' ORDER BY full_name");

$plans = all(
    $pdo,
    "SELECT w.*, m.full_name,
        (SELECT COUNT(*) FROM workout_items wi WHERE wi.plan_id = w.plan_id) AS item_count
     FROM workout_plans w JOIN members m ON m.member_id = w.member_id
     WHERE w.trainer_id = ? ORDER BY w.start_date DESC, w.plan_id DESC",
    [$trainerId],
);

$view = $viewId
    ? one(
        $pdo,
        'SELECT w.*, m.full_name, m.member_number FROM workout_plans w JOIN members m ON m.member_id = w.member_id WHERE w.plan_id = ? AND w.trainer_id = ?',
        [$viewId, $trainerId],
    )
    : null;
$items = $view
    ? all(
        $pdo,
        "SELECT * FROM workout_items WHERE plan_id = ? ORDER BY FIELD(day_of_week, 'Mon','Tue','Wed','Thu','Fri','Sat','Sun'), item_id",
        [$viewId],
    )
    : [];
$editItem = $view && $editItemId
    ? one($pdo, 'SELECT * FROM workout_items WHERE item_id = ? AND plan_id = ?', [$editItemId, $viewId])
    : null;
$editPlan = ($action === 'edit' && $viewId)
    ? one($pdo, 'SELECT * FROM workout_plans WHERE plan_id = ? AND trainer_id = ?', [$viewId, $trainerId])
    : null;
$selectedMemberId = (int) ($_GET['member'] ?? ($editPlan['member_id'] ?? 0));

//  Load the shared file needed before this page continues.
require __DIR__ . '/../includes/dashboard_header.php';
?>

<?php if ($action === 'new' || ($action === 'edit' && $editPlan)): ?>
    <!-- This div groups related page content using the “panel” layout style. -->
    <div class="panel">
        <!-- This div groups related page content using the “panel-title” layout style. -->
        <div class="panel-title">
            <!-- This div groups related HTML content so the page structure is easier to manage. -->
            <div><small class="text-muted">Workout plan editor</small>
                <h2><?= $editPlan ? 'Edit workout plan' : 'Create workout plan' ?></h2>
            </div><a href="workouts.php" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
        </div>
        <!-- This form collects user input and submits this form using the POST method. -->
        <form method="post" class="row g-3" data-loading-form>
            <?= csrfField() ?><input type="hidden" name="mode" value="save_plan"><input type="hidden" name="plan_id" value="<?= (int) ($editPlan['plan_id'] ?? 0) ?>">
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-6"><label class="form-label">Member</label><select class="form-select" name="member_id" required><?php foreach ($members as $member): ?><option value="<?= (int) $member['member_id'] ?>" <?= $selectedMemberId === (int) $member['member_id'] ? 'selected' : '' ?>><?= e($member['member_number'] . ' — ' . $member['full_name']) ?></option><?php endforeach; ?></select></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-6"><label class="form-label">Fitness goal / plan title</label><input class="form-control" name="fitness_goal" required value="<?= e($editPlan['fitness_goal'] ?? '') ?>" placeholder="e.g. Strength foundation — 8 weeks"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-4"><label class="form-label">Start date</label><input class="form-control" type="date" name="start_date" value="<?= e($editPlan['start_date'] ?? date('Y-m-d')) ?>" required></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-4"><label class="form-label">End date</label><input class="form-control" type="date" name="end_date" value="<?= e($editPlan['end_date'] ?? '') ?>"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['Active', 'Completed', 'Cancelled'] as $status): ?><option <?= ($editPlan['status'] ?? 'Active') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
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
            <div><small class="text-muted">Workout plan #<?= $viewId ?> · <?= e($view['member_number']) ?></small>
                <h2><?= e($view['full_name']) ?> — <?= e($view['fitness_goal']) ?></h2>
            </div>
            <!-- This div uses flexbox to align the child elements in this area. -->
            <div class="d-flex flex-wrap gap-2">
                <a href="?action=edit&id=<?= $viewId ?>" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="bi bi-pencil me-1"></i>Edit plan</a>
                <!-- This form collects user input and submits this form using the POST method. -->
                <form method="post"><?= csrfField() ?><input type="hidden" name="mode" value="share_plan"><input type="hidden" name="plan_id" value="<?= $viewId ?>"><button class="btn btn-sm btn-danger rounded-pill"><i class="bi bi-send me-1"></i>Send to member</button></form>
                <!-- This form collects user input and submits this form using the POST method. -->
                <form method="post"><?= csrfField() ?><input type="hidden" name="mode" value="delete_plan"><input type="hidden" name="plan_id" value="<?= $viewId ?>"><button class="btn btn-sm btn-outline-danger rounded-pill" data-confirm="Delete this workout plan and all exercises?">Delete</button></form>
                <a href="workouts.php" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
            </div>
        </div>
        <!-- This div uses flexbox to align the child elements in this area. -->
        <div class="d-flex flex-wrap gap-2 mb-3"><?= statusBadge($view['status']) ?><?php if ($hasSharedAt && !empty($view['shared_at'])): ?><span class="badge text-bg-light rounded-pill"><i class="bi bi-send-check me-1"></i>Last sent <?= date('d M H:i', strtotime($view['shared_at'])) ?></span><?php endif; ?></div>

        <!-- This div allows this table to scroll safely on smaller screens. -->
        <div class="table-responsive">
            <!-- This table displays database records in rows and columns for easy reading. -->
            <table class="table table-modern align-middle">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Exercise</th>
                        <th>Sets × Reps</th>
                        <th>Duration</th>
                        <th>Rest</th>
                        <th>Instructions</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['day_of_week']) ?></td>
                            <td><strong><?= e($item['exercise_name']) ?></strong></td>
                            <td><?= e((string) $item['sets']) ?> × <?= e((string) $item['repetitions']) ?></td>
                            <td><?= $item['duration_minutes'] ? e((string) $item['duration_minutes']) . ' min' : '—' ?></td>
                            <td><?= $item['rest_seconds'] ? e((string) $item['rest_seconds']) . ' sec' : '—' ?></td>
                            <td><?= e($item['instructions']) ?></td>
                            <td class="text-end">
                                <!-- This div groups related page content using the “d-inline-flex gap-1” layout style. -->
                                <div class="d-inline-flex gap-1"><a class="btn btn-sm btn-outline-secondary rounded-pill" href="?action=view&id=<?= $viewId ?>&item=<?= (int) $item['item_id'] ?>#exercise-editor">Edit</a>
                                    <!-- This form collects user input and submits this form using the POST method. -->
                                    <form method="post"><?= csrfField() ?><input type="hidden" name="mode" value="delete_item"><input type="hidden" name="plan_id" value="<?= $viewId ?>"><input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>"><button class="btn btn-sm btn-outline-danger rounded-pill" data-confirm="Remove this exercise?">Delete</button></form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$items): ?><tr>
                            <td colspan="7" class="text-center text-muted py-5">No exercises yet. Add the first exercise below.</td>
                        </tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- This div groups the content for the “exercise-editor” area of this page. -->
    <div class="panel" id="exercise-editor">
        <!-- This div groups related page content using the “panel-title” layout style. -->
        <div class="panel-title">
            <!-- This div groups related HTML content so the page structure is easier to manage. -->
            <div><small class="text-muted"><?= $editItem ? 'Update selected exercise' : 'Build the schedule' ?></small>
                <h2><?= $editItem ? 'Edit exercise' : 'Add exercise' ?></h2>
            </div><?php if ($editItem): ?><a class="btn btn-sm btn-outline-secondary rounded-pill" href="?action=view&id=<?= $viewId ?>#exercise-editor">Cancel edit</a><?php endif; ?>
        </div>
        <!-- This form collects user input and submits this form using the POST method. -->
        <form method="post" class="row g-3">
            <?= csrfField() ?><input type="hidden" name="mode" value="save_item"><input type="hidden" name="plan_id" value="<?= $viewId ?>"><input type="hidden" name="item_id" value="<?= (int) ($editItem['item_id'] ?? 0) ?>">
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-4"><label class="form-label">Exercise</label><input class="form-control" name="exercise_name" required value="<?= e($editItem['exercise_name'] ?? '') ?>"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-2"><label class="form-label">Day</label><select class="form-select" name="day_of_week"><?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day): ?><option <?= ($editItem['day_of_week'] ?? 'Mon') === $day ? 'selected' : '' ?>><?= $day ?></option><?php endforeach; ?></select></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-2"><label class="form-label">Sets</label><input class="form-control" type="number" min="0" name="sets" value="<?= e((string) ($editItem['sets'] ?? '')) ?>"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-2"><label class="form-label">Reps</label><input class="form-control" type="number" min="0" name="repetitions" value="<?= e((string) ($editItem['repetitions'] ?? '')) ?>"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-2"><label class="form-label">Rest sec</label><input class="form-control" type="number" min="0" name="rest_seconds" value="<?= e((string) ($editItem['rest_seconds'] ?? '')) ?>"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-3"><label class="form-label">Duration min</label><input class="form-control" type="number" min="0" name="duration_minutes" value="<?= e((string) ($editItem['duration_minutes'] ?? '')) ?>"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-md-9"><label class="form-label">Instructions</label><input class="form-control" name="instructions" value="<?= e($editItem['instructions'] ?? '') ?>"></div>
            <!-- This div creates a responsive Bootstrap column inside the current row. -->
            <div class="col-12"><button class="btn btn-dark rounded-pill px-4"><?= $editItem ? 'Update exercise' : 'Add exercise' ?></button></div>
        </form>
    </div>

    <?php if (isset($_GET['created']) || isset($_GET['updated'])): ?>
        <!-- This div creates the popup content area shown above the current page. -->
        <div class="modal fade" id="sharePlanModal" tabindex="-1">
            <!-- This div creates the popup content area shown above the current page. -->
            <div class="modal-dialog modal-dialog-centered">
                <!-- This div creates the popup content area shown above the current page. -->
                <div class="modal-content border-0 rounded-4">
                    <!-- This div creates the popup content area shown above the current page. -->
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Send plan to member?</h5><button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <!-- This div creates the popup content area shown above the current page. -->
                    <div class="modal-body pt-0">
                        <p class="text-muted mb-0">The plan is saved. You can send a notification now so the member knows the workout is ready to review.</p>
                    </div>
                    <!-- This div creates the popup content area shown above the current page. -->
                    <div class="modal-footer border-0"><button class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Not yet</button>
                        <!-- This form collects user input and submits this form using the POST method. -->
                        <form method="post"><?= csrfField() ?><input type="hidden" name="mode" value="share_plan"><input type="hidden" name="plan_id" value="<?= $viewId ?>"><button class="btn btn-danger rounded-pill px-4"><i class="bi bi-send me-2"></i>Send to member</button></form>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('sharePlanModal')).show());
        </script>
    <?php endif; ?>

<?php else: ?>
    <!-- This div groups related page content using the “panel” layout style. -->
    <div class="panel">
        <!-- This div groups related page content using the “panel-title” layout style. -->
        <div class="panel-title">
            <!-- This div groups related HTML content so the page structure is easier to manage. -->
            <div><small class="text-muted">Training programs</small>
                <h2>Workout plans</h2>
            </div> <a class="btn btn-outline-light rounded-pill px-4 btn-ripple-expand" href="?action=new">
                <span class="btn-ripple-circle"></span>
                <span class="btn-ripple-label"><i class="bi bi-plus-lg me-1"></i>New plan</span>
            </a>
        </div>
        <!-- This div allows this table to scroll safely on smaller screens. -->
        <div class="table-responsive">
            <!-- This table displays database records in rows and columns for easy reading. -->
            <table class="table table-modern align-middle">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Goal</th>
                        <th>Dates</th>
                        <th>Exercises</th>
                        <th>Status</th>
                        <th>Sent</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $plan): ?><tr>
                            <td><strong><?= e($plan['full_name']) ?></strong></td>
                            <td><?= e($plan['fitness_goal']) ?></td>
                            <td><?= shortDate($plan['start_date']) ?> – <?= shortDate($plan['end_date']) ?></td>
                            <td><?= (int) $plan['item_count'] ?></td>
                            <td><?= statusBadge($plan['status']) ?></td>
                            <td><?= $hasSharedAt && !empty($plan['shared_at']) ? '<span class="badge text-bg-success rounded-pill">Sent</span>' : '<span class="badge text-bg-light rounded-pill">Draft</span>' ?></td>
                            <td class="text-end"><a href="?action=view&id=<?= (int) $plan['plan_id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill">Open</a></td>
                        </tr><?php endforeach; ?>
                    <?php if (!$plans): ?><tr>
                            <td colspan="7" class="text-center text-muted py-5">No workout plans yet.</td>
                        </tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>