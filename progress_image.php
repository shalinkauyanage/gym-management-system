<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$photoId = (int) ($_GET['photo'] ?? 0);
$photo = one(
    $pdo,
    "SELECT ph.*, pr.member_id
     FROM progress_photos ph
     JOIN progress_records pr ON pr.progress_id = ph.progress_id
     WHERE ph.photo_id = ? AND ph.status = 'Visible'",
    [$photoId],
);

if (!$photo) {
    http_response_code(404);
    die('Photo not found.');
}

$allowed = false;
$role = currentUser()['role'];
if ($role === 'Admin') {
    $allowed = true;
} elseif ($role === 'Member') {
    $member = getMemberByUser($pdo, currentUser()['id']);
    $allowed = $member && (int) $member['member_id'] === (int) $photo['member_id'];
} elseif (isCoachRole($role)) {
    $allowed = coachCanAccessMember($pdo, currentUser()['id'], (int) $photo['member_id']);
}

if (!$allowed) {
    http_response_code(403);
    die('Not allowed.');
}

$path = UPLOAD_DIR . basename($photo['photo_path']);
if (!is_file($path)) {
    http_response_code(404);
    die('Photo file is missing.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
header('Content-Type: ' . $mime);
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
