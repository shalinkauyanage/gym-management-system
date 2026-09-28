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
        "SELECT mp.*, m.full_name, m.member_number, COALESCE(NULLIF(t.display_name, ''), u.username) AS coach_name
         FROM meal_plan mp
         JOIN members m ON m.member_id = mp.member_id
         JOIN nutrition_advisers n ON n.adviser_id = mp.adviser_id
         JOIN users u ON u.user_id = n.user_id
         LEFT JOIN trainers t ON t.user_id = u.user_id
         WHERE mp.plan_id = ? AND mp.member_id = ?",
        [$planId, (int) $member['member_id']],
    )
    : one(
        $pdo,
        "SELECT mp.*, m.full_name, m.member_number, COALESCE(NULLIF(t.display_name, ''), u.username) AS coach_name
         FROM meal_plan mp
         JOIN members m ON m.member_id = mp.member_id
         JOIN nutrition_advisers n ON n.adviser_id = mp.adviser_id
         JOIN users u ON u.user_id = n.user_id
         LEFT JOIN trainers t ON t.user_id = u.user_id
         WHERE mp.member_id = ? AND mp.status = 'Active'
         ORDER BY mp.start_date DESC LIMIT 1",
        [(int) $member['member_id']],
    );

if (!$plan) {
    http_response_code(404);
    die('Meal plan not found.');
}

$items = all(
    $pdo,
    "SELECT mi.*, f.food_name, f.unit, f.calories_per_unit, f.protein_g, f.carbs_g, f.fat_g
     FROM meal_items mi
     JOIN food_items f ON f.food_id = mi.food_id
     WHERE mi.meal_plan_id = ?
     ORDER BY FIELD(mi.day_of_week, 'Mon','Tue','Wed','Thu','Fri','Sat','Sun'), mi.meal_time, mi.meal_item_id",
    [(int) $plan['plan_id']],
);

$mealRows = array_map(
    // Viva note: Transforms each meal item into the table row structure used by the PDF builder.
    static function (array $item): array {
        // Student function step: This anonymous helper runs a small task that is used only inside this current file/function.
        $time = $item['meal_time'] ? substr((string) $item['meal_time'], 0, 5) : '-';
        $nutrition = $item['calories_per_unit'] . ' kcal | P ' . $item['protein_g'] . 'g | C ' . $item['carbs_g'] . 'g | F ' . $item['fat_g'] . 'g';
        return [
            (string) $item['day_of_week'],
            $time,
            (string) $item['meal_type'],
            (string) $item['food_name'],
            (string) $item['quantity'] . ' ' . (string) $item['unit'],
            $nutrition,
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
            ['label' => 'Plan', 'value' => $plan['plan_name']],
            ['label' => 'Daily target', 'value' => $plan['daily_calories_target'] ? $plan['daily_calories_target'] . ' kcal/day' : 'Not specified'],
            ['label' => 'Period', 'value' => shortDate($plan['start_date']) . ' - ' . shortDate($plan['end_date'])],
        ],
    ],
    [
        'heading' => 'Meal schedule',
        'table' => [
            'headers' => ['Day', 'Time', 'Meal', 'Food', 'Quantity', 'Nutrition'],
            'widths' => [40, 45, 60, 125, 70, 159],
            'rows' => $mealRows,
        ],
    ],
    [
        'heading' => 'Coach note',
        'rows' => [[
            'value' => 'This document organizes the meal plan prepared by your coach. It is not a medical diagnosis or an automatic therapeutic diet recommendation.',
        ]],
    ],
];

$pdf = buildPowerFitPdf(
    'Meal Plan',
    $plan['full_name'] . ' - Plan #' . $plan['plan_id'],
    $sections,
);
outputPowerFitPdf($pdf, 'powerfit-meal-plan-' . $plan['plan_id'] . '.pdf');
