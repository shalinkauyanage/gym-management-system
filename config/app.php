<?php

// PowerFit application settings. Change APP_URL if your WAMP folder name differs.
define('APP_NAME', 'PowerFit');
define('APP_URL', 'http://localhost/powerfit');
define('APP_TIMEZONE', 'Asia/Colombo');

define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/progress/');
define('PACKAGE_UPLOAD_DIR', dirname(__DIR__) . '/uploads/packages/');
define('BLOG_UPLOAD_DIR', dirname(__DIR__) . '/uploads/blog/');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);

date_default_timezone_set(APP_TIMEZONE);
