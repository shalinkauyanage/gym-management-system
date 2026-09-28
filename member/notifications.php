<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireRole('Member');
//  Redirect the browser after this action to avoid repeating the same request.
redirect('notifications.php');
