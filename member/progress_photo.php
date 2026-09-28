<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Member');

$member = getMemberByUser($pdo, currentUser()['id']);
$progressId = (int) ($_GET['id'] ?? 0);
$photo = one(
    $pdo,
    "SELECT ph.photo_id
     FROM progress_photos ph
     JOIN progress_records pr ON pr.progress_id = ph.progress_id
     WHERE ph.progress_id = ? AND pr.member_id = ? AND ph.status = 'Visible'
     ORDER BY ph.photo_id DESC LIMIT 1",
    [$progressId, (int) $member['member_id']],
);

if (!$photo) {
    http_response_code(404);
    die('Photo not found.');
}

//  Redirect the browser after this action to avoid repeating the same request.
redirect('progress_image.php?photo=' . (int) $photo['photo_id']);
