<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
//  Redirect the browser after this action to avoid repeating the same request.
redirect('coach/members.php');
