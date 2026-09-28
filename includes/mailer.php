<?php

//  Load the shared file needed before this page continues.
require_once dirname(__DIR__) . '/config/mail.php';

// Reads the complete response returned by the SMTP server.

function smtpReadResponse($socket): array
{
    // Student function step: This is the start of smtpReadResponse(). The lines below do the main work of this helper.
    $lines = [];
    $code = 0;

    while (($line = fgets($socket, 1024)) !== false) {
        $line = rtrim($line, "\r\n");
        $lines[] = $line;

        if (preg_match('/^(\d{3})([ -])/', $line, $matches)) {
            $code = (int) $matches[1];
            if ($matches[2] === ' ') {
                break;
            }
        } else {
            break;
        }
    }

    return ['code' => $code, 'message' => implode("\n", $lines)];
}

 // Sends one SMTP command and verifies that Gmail returned an expected status code.
 
function smtpCommand($socket, string $command, array $expectedCodes, ?string $label = null): array
{
    // Student function step: This is the start of smtpCommand(). The lines below do the main work of this helper.
    if (@fwrite($socket, $command . "\r\n") === false) {
        throw new RuntimeException('Could not write to the Gmail SMTP connection.');
    }

    $response = smtpReadResponse($socket);
    if (!in_array($response['code'], $expectedCodes, true)) {
        $safeLabel = $label ?: strtoupper((string) strtok($command, ' '));
        throw new RuntimeException('Gmail SMTP rejected ' . $safeLabel . ': ' . $response['message']);
    }

    return $response;
}

// Escapes message lines that begin with a dot as required by the SMTP protocol.
 
function smtpDotStuff(string $body): string
{
    // Student function step: This is the start of smtpDotStuff(). The lines below do the main work of this helper.
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);

    foreach ($lines as &$line) {
        if (isset($line[0]) && $line[0] === '.') {
            $line = '.' . $line;
        }
    }
    unset($line);

    return implode("\r\n", $lines);
}

// Checks that Gmail SMTP configuration values have been filled in.
 
function mailConfigLooksValid(): bool
{
    // Student function step: This is the start of mailConfigLooksValid(). The lines below do the main work of this helper.
    $username = trim((string) MAIL_USERNAME);
    $password = str_replace(' ', '', trim((string) MAIL_APP_PASSWORD));

    return filter_var($username, FILTER_VALIDATE_EMAIL) !== false
        && $password !== ''
        && !str_contains($username, 'YOUR_POWERFIT_GMAIL')
        && !str_contains($password, 'PASTE_YOUR_16_CHARACTER');
}


// Sends a PowerFit email through Gmail SMTP using the configured App Password.
 
function sendPowerFitEmail(string $to, string $subject, string $htmlBody, string $textBody = ''): array
{
    // Student function step: This is the start of sendPowerFitEmail(). The lines below do the main work of this helper.
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'The registered email address is invalid.'];
    }

    if (MAIL_DRIVER === 'local') {
        $directory = dirname(EMAIL_LOG_FILE);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return ['success' => false, 'message' => 'PowerFit could not create the local email log folder.'];
        }

        $plain = $textBody !== '' ? $textBody : trim(strip_tags($htmlBody));
        $written = @file_put_contents(
            EMAIL_LOG_FILE,
            '[' . date('Y-m-d H:i:s') . '] TO: ' . $to . ' | SUBJECT: ' . $subject . "\n" . $plain . "\n\n",
            FILE_APPEND,
        );

        return $written === false
            ? ['success' => false, 'message' => 'PowerFit could not write the local email log.']
            : ['success' => true, 'message' => 'Local test email created successfully.'];
    }

    if (MAIL_DRIVER !== 'gmail') {
        return ['success' => false, 'message' => 'Unsupported email driver in config/mail.php.'];
    }

    if (!mailConfigLooksValid()) {
        return [
            'success' => false,
            'message' => 'Gmail is not configured. Open config/mail.php and add MAIL_USERNAME and MAIL_APP_PASSWORD.',
        ];
    }

    if (!extension_loaded('openssl')) {
        return ['success' => false, 'message' => 'PHP OpenSSL is disabled. Enable php_openssl in WAMP and restart WAMP.'];
    }

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'peer_name' => MAIL_HOST,
        ],
    ]);

    $scheme = MAIL_ENCRYPTION === 'ssl' ? 'ssl' : 'tcp';
    $remote = $scheme . '://' . MAIL_HOST . ':' . (int) MAIL_PORT;
    $errno = 0;
    $errstr = '';

    $socket = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        MAIL_TIMEOUT_SECONDS,
        STREAM_CLIENT_CONNECT,
        $context,
    );

    if (!$socket) {
        return [
            'success' => false,
            'message' => 'Could not connect to Gmail SMTP. Check internet access, WAMP OpenSSL, firewall, and config/mail.php.',
        ];
    }

    stream_set_timeout($socket, MAIL_TIMEOUT_SECONDS);

    try {
        $welcome = smtpReadResponse($socket);
        if ($welcome['code'] !== 220) {
            throw new RuntimeException('Gmail SMTP connection was rejected: ' . $welcome['message']);
        }

        $helo = preg_replace('/[^A-Za-z0-9.\-]/', '', (string) ($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
        smtpCommand($socket, 'EHLO ' . $helo, [250]);

        if (MAIL_ENCRYPTION === 'tls') {
            smtpCommand($socket, 'STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not enable TLS for Gmail SMTP. Check WAMP OpenSSL/CA configuration.');
            }
            smtpCommand($socket, 'EHLO ' . $helo, [250]);
        }

        smtpCommand($socket, 'AUTH LOGIN', [334]);
        smtpCommand($socket, base64_encode((string) MAIL_USERNAME), [334], 'AUTH username');
        smtpCommand($socket, base64_encode(str_replace(' ', '', (string) MAIL_APP_PASSWORD)), [235], 'AUTH password');
        smtpCommand($socket, 'MAIL FROM:<' . MAIL_FROM_EMAIL . '>', [250]);
        smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtpCommand($socket, 'DATA', [354]);

        $safeFromName = str_replace(["\r", "\n"], '', (string) MAIL_FROM_NAME);
        $safeSubject = str_replace(["\r", "\n"], '', $subject);
        $boundary = '=_PowerFit_' . bin2hex(random_bytes(12));
        $plain = $textBody !== '' ? $textBody : trim(strip_tags($htmlBody));

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $safeFromName . ' <' . MAIL_FROM_EMAIL . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($safeSubject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $message = implode("\r\n", $headers) . "\r\n\r\n";
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($plain), 76, "\r\n") . "\r\n";
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($htmlBody), 76, "\r\n") . "\r\n";
        $message .= '--' . $boundary . "--\r\n";

        if (@fwrite($socket, smtpDotStuff($message) . "\r\n.\r\n") === false) {
            throw new RuntimeException('Could not submit the email to Gmail SMTP.');
        }

        $sent = smtpReadResponse($socket);
        if ($sent['code'] !== 250) {
            throw new RuntimeException('Gmail did not accept the email: ' . $sent['message']);
        }

        @fwrite($socket, "QUIT\r\n");
        @fclose($socket);

        return ['success' => true, 'message' => 'Verification email sent successfully.'];
    } catch (Throwable $error) {
        if (is_resource($socket)) {
            @fclose($socket);
        }

        return ['success' => false, 'message' => $error->getMessage()];
    }
}

// Builds the HTML and text content for email verification or password-reset OTP messages.

function powerFitOtpEmailTemplate(string $otp, string $purpose): array
{
    // Student function step: This is the start of powerFitOtpEmailTemplate(). The lines below do the main work of this helper.
    $isVerification = $purpose === 'email_verification';
    $title = $isVerification ? 'Verify your PowerFit email' : 'Reset your PowerFit password';
    $intro = $isVerification
        ? 'Use the verification code below to confirm your registered email address and continue setting up your PowerFit account.'
        : 'We received a request to reset your PowerFit password. Use the verification code below to continue.';

    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeIntro = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');

    $html = '<!doctype html><html><body style="margin:0;background:#ededed;font-family:Arial,sans-serif;color:#0e0e0e">'
        . '<div style="max-width:560px;margin:32px auto;background:#fff;border:1px solid #d2d2d2;border-radius:24px;overflow:hidden">'
        . '<div style="background:#0e0e0e;color:#fff;padding:26px 30px;font-size:22px;font-weight:800;letter-spacing:.04em">POWERFIT</div>'
        . '<div style="padding:30px">'
        . '<h1 style="font-size:28px;margin:0 0 12px">' . $safeTitle . '</h1>'
        . '<p style="color:#565656;line-height:1.6">' . $safeIntro . '</p>'
        . '<div style="margin:26px 0;padding:22px;text-align:center;border-radius:18px;background:#f3f3f3;font-size:34px;font-weight:900;letter-spacing:.24em">' . $safeOtp . '</div>'
        . '<p style="color:#565656;line-height:1.6">This code expires in ' . (int) EMAIL_OTP_EXPIRY_MINUTES . ' minutes. Never share this code with another person.</p>'
        . '<p style="margin-top:24px;color:#8a8a8a;font-size:13px">If you did not request this, you can ignore this email.</p>'
        . '</div></div></body></html>';

    $text = "POWERFIT\n\n{$title}\n\n{$intro}\n\nVerification code: {$otp}\n\n"
        . 'This code expires in ' . (int) EMAIL_OTP_EXPIRY_MINUTES . " minutes.\n";

    return ['subject' => $title, 'html' => $html, 'text' => $text];
}
