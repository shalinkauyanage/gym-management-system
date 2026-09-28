<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/includes/functions.php';
$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}
flash('success', 'You have been signed out.');
redirect('index.php?login=1');
