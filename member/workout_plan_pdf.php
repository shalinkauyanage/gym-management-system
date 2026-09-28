<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/simple_pdf.php';
//  Check the user role before allowing this protected action.
requireRole('Member');

$member = getMemberByUser($pdo, currentUser()['id']);
$planId = (int) ($_GET['id'] ?? 0);

$plan = $planId
    ? one(
        $pdo,
        "SELECT w.*, m.full_name, m.member_number, COALESCE(NULLIF(t.display_name, ''), u.username) AS coach_name
         FROM workout_plans w
         JOIN members m ON m.member_id = w.member_id
         JOIN trainers t ON t.trainer_id = w.trainer_id
         JOIN users u ON u.user_id = t.user_id
         WHERE w.plan_id = ? AND w.member_id = ?",
        [$planId, (int) $member['member_id']],
    )
    : one(
        $pdo,
        "SELECT w.*, m.full_name, m.member_number, COALESCE(NULLIF(t.display_name, ''), u.username) AS coach_name
         FROM workout_plans w
         JOIN members m ON m.member_id = w.member_id
         JOIN trainers t ON t.trainer_id = w.trainer_id
         JOIN users u ON u.user_id = t.user_id
         WHERE w.member_id = ? AND w.status = 'Active'
         ORDER BY w.start_date DESC LIMIT 1",
        [(int) $member['member_id']],
    );

if (!$plan) {
    http_response_code(404);
    die('Workout plan not found.');
}

$items = all(
    $pdo,
    "SELECT * FROM workout_items WHERE plan_id = ?
     ORDER BY FIELD(day_of_week, 'Mon','Tue','Wed','Thu','Fri','Sat','Sun'), item_id",
    [(int) $plan['plan_id']],
);

$exerciseRows = array_map(
    // Viva note: Transforms each workout item into the table row structure used by the PDF builder.
    static function (array $item): array {
        // Student function step: This anonymous helper runs a small task that is used only inside this current file/function.
        $setsReps = ($item['sets'] && $item['repetitions'])
            ? $item['sets'] . ' x ' . $item['repetitions']
            : ($item['sets'] ? $item['sets'] . ' sets' : ($item['repetitions'] ? $item['repetitions'] . ' reps' : '-'));

        return [
            (string) $item['day_of_week'],
            (string) $item['exercise_name'],
            $setsReps,
            $item['duration_minutes'] ? $item['duration_minutes'] . ' min' : '-',
            $item['rest_seconds'] ? $item['rest_seconds'] . ' sec' : '-',
            (string) ($item['instructions'] ?: '-'),
        ];
    },
    $items,
);

$sections = [
    [
        'heading' => 'Plan summary',
        'rows' => [
            ['label' => 'Member', 'value' => $plan['full_name'] . ' (' . $plan['member_number'] . ')'],
            ['label' => 'Coach', 'value' => $plan['coach_name']],
            ['label' => 'Fitness goal', 'value' => $plan['fitness_goal'] ?: 'Not specified'],
            ['label' => 'Period', 'value' => shortDate($plan['start_date']) . ' - ' . shortDate($plan['end_date'])],
        ],
    ],
    [
        'heading' => 'Workout schedule',
        'table' => [
            'headers' => ['Day', 'Exercise', 'Sets x reps', 'Duration', 'Rest', 'Instructions'],
            'widths' => [42, 105, 72, 62, 54, 164],
            'rows' => $exerciseRows,
        ],
    ],
    [
        'heading' => 'Coach note',
        'rows' => [[
            'value' => 'Follow the plan shared by your coach and discuss any change to exercise selection, training volume or intensity before updating the program.',
        ]],
    ],
];

$pdf = buildPowerFitPdf(
    'Workout Plan',
    $plan['full_name'] . ' - Plan #' . $plan['plan_id'],
    $sections,
);
outputPowerFitPdf($pdf, 'powerfit-workout-plan-' . $plan['plan_id'] . '.pdf');
