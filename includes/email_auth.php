<?php

//  Load the shared file needed before this page continues.
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';

// Checks that the database contains the email-verification columns and OTP table.

function emailAuthSchemaReady(PDO $pdo): bool
{
    // Student function step: This is the start of emailAuthSchemaReady(). The lines below do the main work of this helper.
    return columnExists($pdo, 'users', 'email_verified_at')
        && columnExists($pdo, 'users', 'must_change_password')
        && columnExists($pdo, 'users', 'password_changed_at')
        && columnExists($pdo, 'users', 'contact_number')
        && tableExists($pdo, 'email_otps');
}

//  Masks most of an email address before showing the password-recovery destination.

function maskEmailAddress(?string $email): string
{
    // Student function step: This is the start of maskEmailAddress(). The lines below do the main work of this helper.
    $email = trim((string) $email);
    if ($email === '' || !str_contains($email, '@')) {
        return 'Unknown email';
    }

    [$local, $domain] = explode('@', $email, 2);
    $visible = substr($local, 0, min(2, strlen($local)));
    $hiddenCount = max(4, strlen($local) - strlen($visible));

    return $visible . str_repeat('•', $hiddenCount) . '@' . $domain;
}

// Returns the latest OTP record for a user and verification purpose.

function latestEmailOtp(PDO $pdo, int $userId, string $purpose): ?array
{
    // Student function step: This is the start of latestEmailOtp(). The lines below do the main work of this helper.
    return one(
        $pdo,
        'SELECT * FROM email_otps
         WHERE user_id = ? AND purpose = ? AND used_at IS NULL
         ORDER BY otp_id DESC LIMIT 1',
        [$userId, $purpose],
    );
}


//  Calculates how long the user must wait before requesting another OTP.

function emailOtpResendWaitSeconds(PDO $pdo, int $userId, string $purpose): int
{
    // Student function step: This is the start of emailOtpResendWaitSeconds(). The lines below do the main work of this helper.
    $row = latestEmailOtp($pdo, $userId, $purpose);
    if (!$row || empty($row['sent_at'])) {
        return 0;
    }

    $sent = strtotime((string) $row['sent_at']);
    if ($sent === false) {
        return 0;
    }

    return max(0, (int) EMAIL_OTP_RESEND_SECONDS - (time() - $sent));
}


// Creates a six-digit OTP, stores only its hash, and sends the readable code by email.

function createAndSendEmailOtp(PDO $pdo, array $user, string $purpose): array
{
    // Student function step: This is the start of createAndSendEmailOtp(). The lines below do the main work of this helper.
    if (!in_array($purpose, ['email_verification', 'password_reset'], true)) {
        return ['success' => false, 'message' => 'Invalid verification purpose.'];
    }

    if (!emailAuthSchemaReady($pdo)) {
        return [
            'success' => false,
            'message' => 'Email verification database upgrade is not installed. Import database/patch_email_verification.sql once.',
        ];
    }

    $email = trim((string) ($user['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'This account does not have a valid registered email address.'];
    }

    $wait = emailOtpResendWaitSeconds($pdo, (int) $user['user_id'], $purpose);
    if ($wait > 0) {
        return ['success' => false, 'message' => 'Please wait ' . $wait . ' seconds before requesting another code.'];
    }

    $otp = (string) random_int(100000, 999999);
    $expiresAt = date('Y-m-d H:i:s', time() + ((int) EMAIL_OTP_EXPIRY_MINUTES * 60));

    //  group related SQL changes so they either all succeed or all roll back.
    //  Start a database transaction so related changes succeed or fail together.
    $pdo->beginTransaction();
    try {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
            'UPDATE email_otps SET used_at = NOW()
             WHERE user_id = ? AND purpose = ? AND used_at IS NULL',
        )->execute([(int) $user['user_id'], $purpose]);

        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare(
            'INSERT INTO email_otps(user_id, purpose, otp_hash, expires_at, attempts, provider, sent_at)
             VALUES(?, ?, ?, ?, 0, ?, NOW())',
        )->execute([
            (int) $user['user_id'],
            $purpose,
            password_hash($otp, PASSWORD_DEFAULT),
            $expiresAt,
            (string) MAIL_DRIVER,
        ]);

        $otpId = (int) $pdo->lastInsertId();
        //  Save all database changes made inside the transaction.
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            //  Undo the transaction if an error happens before completion.
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'PowerFit could not create the verification code.'];
    }

    $template = powerFitOtpEmailTemplate($otp, $purpose);
    $sent = sendPowerFitEmail($email, $template['subject'], $template['html'], $template['text']);

    if (!$sent['success']) {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare('UPDATE email_otps SET used_at = NOW() WHERE otp_id = ?')->execute([$otpId]);
        return $sent;
    }

    if (MAIL_DRIVER === 'local') {
        $_SESSION['local_email_otp_preview'] = $otp;
    } else {
        unset($_SESSION['local_email_otp_preview']);
    }

    return ['success' => true, 'message' => 'A 6-digit verification code was sent to ' . maskEmailAddress($email) . '.'];
}


//  Checks an entered OTP, expiry time and attempt limit, then marks a valid code as used.

function verifyEmailOtp(PDO $pdo, int $userId, string $purpose, string $otp): array
{
    // Student function step: This is the start of verifyEmailOtp(). The lines below do the main work of this helper.
    if (!preg_match('/^\d{6}$/', trim($otp))) {
        return ['success' => false, 'message' => 'Enter the 6-digit verification code.'];
    }

    $row = latestEmailOtp($pdo, $userId, $purpose);
    if (!$row) {
        return ['success' => false, 'message' => 'Request a new verification code first.'];
    }

    if (strtotime((string) $row['expires_at']) < time()) {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare('UPDATE email_otps SET used_at = NOW() WHERE otp_id = ?')->execute([(int) $row['otp_id']]);
        return ['success' => false, 'message' => 'This verification code has expired. Request a new code.'];
    }

    if ((int) $row['attempts'] >= (int) EMAIL_OTP_MAX_ATTEMPTS) {
        return ['success' => false, 'message' => 'Too many incorrect attempts. Request a new code.'];
    }

    if (!password_verify(trim($otp), (string) $row['otp_hash'])) {
        //  Prepare and run this SQL statement using PDO.
        $pdo->prepare('UPDATE email_otps SET attempts = attempts + 1 WHERE otp_id = ?')
            ->execute([(int) $row['otp_id']]);
        return ['success' => false, 'message' => 'Incorrect verification code.'];
    }

    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare('UPDATE email_otps SET used_at = NOW() WHERE otp_id = ?')
        ->execute([(int) $row['otp_id']]);

    unset($_SESSION['local_email_otp_preview']);
    return ['success' => true, 'message' => 'Verification code accepted.'];
}
