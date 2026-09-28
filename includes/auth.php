<?php


//  Load the shared file needed before this page continues.
require_once __DIR__ . '/functions.php';


// Validates login credentials and creates the authenticated session.

function attemptLogin(PDO $pdo, string $identity, string $password): bool
{
    // Student function step: This is the start of attemptLogin(). The lines below do the main work of this helper.
    $user = one(
        $pdo,
        'SELECT u.*, r.role_name
         FROM users u
         JOIN roles r ON r.role_id = u.role_id
         WHERE (u.username = ? OR u.email = ?)
         LIMIT 1',
        [$identity, $identity],
    );

    if (!$user || ($user['status'] ?? '') !== 'Active' || !password_verify($password, (string) $user['password_hash'])) {
        return false;
    }

    $hasVerified = columnExists($pdo, 'users', 'email_verified_at');
    $hasMustChange = columnExists($pdo, 'users', 'must_change_password');
    $hasContact = columnExists($pdo, 'users', 'contact_number');

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['user_id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role_name'],
        'contact_number' => $hasContact ? ($user['contact_number'] ?? null) : null,
        'email_verified_at' => $hasVerified ? ($user['email_verified_at'] ?? null) : null,
        'must_change_password' => $hasMustChange ? (int) ($user['must_change_password'] ?? 0) : 0,
        'auth_schema_ready' => $hasVerified && $hasMustChange,
    ];

    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?')->execute([$user['user_id']]);

    return true;
}
