<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/includes/functions.php';
requireLogin();
redirect(roleDashboard(currentUser()['role']));
