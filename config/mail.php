<?php

// Gmail SMTP is used by PowerFit for verification and password-reset emails.
if (!defined('MAIL_DRIVER')) {
    define('MAIL_DRIVER', 'gmail');
}

if (!defined('MAIL_HOST')) {
    define('MAIL_HOST', 'smtp.gmail.com');
}

if (!defined('MAIL_PORT')) {
    define('MAIL_PORT', 587);
}

if (!defined('MAIL_ENCRYPTION')) {
    define('MAIL_ENCRYPTION', 'tls');
}


if (!defined('MAIL_USERNAME')) {
    define('MAIL_USERNAME', 'thepowerfit.system@gmail.com');
}

if (!defined('MAIL_APP_PASSWORD')) {
    define('MAIL_APP_PASSWORD', 'dvri krao rseq kiel');
}

if (!defined('MAIL_FROM_EMAIL')) {
    define('MAIL_FROM_EMAIL', MAIL_USERNAME);
}

if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', 'PowerFit');
}

// OTP codes expire after five minutes.
if (!defined('EMAIL_OTP_EXPIRY_MINUTES')) {
    define('EMAIL_OTP_EXPIRY_MINUTES', 5);
}

// Users must wait before requesting another OTP.
if (!defined('EMAIL_OTP_RESEND_SECONDS')) {
    define('EMAIL_OTP_RESEND_SECONDS', 60);
}

// Stops repeated guessing of a verification code.
if (!defined('EMAIL_OTP_MAX_ATTEMPTS')) {
    define('EMAIL_OTP_MAX_ATTEMPTS', 5);
}

if (!defined('MAIL_TIMEOUT_SECONDS')) {
    define('MAIL_TIMEOUT_SECONDS', 20);
}

if (!defined('EMAIL_LOG_FILE')) {
    define('EMAIL_LOG_FILE', dirname(__DIR__) . '/storage/logs/email.log');
}
