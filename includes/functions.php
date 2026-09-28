<?php

//  Load the shared file needed before this page continues.
require_once dirname(__DIR__) . '/config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Escapes text before it is printed into HTML to prevent unsafe output.

function e(?string $value): string
{
    // Student function step: This is the start of e(). The lines below do the main work of this helper.
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Builds an application URL from the configured PowerFit base path.

function url(string $path = ''): string
{
    // This is the start of url(). The lines below do the main work of this helper.
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}


//Redirects the browser to another PowerFit page and stops the current request.

function redirect(string $path)
{
    // Student function step: This is the start of redirect(). The lines below do the main work of this helper.
    header('Location: ' . url($path));
    exit;
}

// Checks whether the current HTTP request was submitted with POST.

function isPost(): bool
{
    // Student function step: This is the start of isPost(). The lines below do the main work of this helper.
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// Stores a one-time status message in the session.

function flash(string $type, string $message): void
{
    // Student function step: This is the start of flash(). The lines below do the main work of this helper.
    $_SESSION['flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

// Returns queued flash messages and removes them from the session.

function pullFlashes(): array
{
    // Student function step: This is the start of pullFlashes(). The lines below do the main work of this helper.
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $items;
}

// Creates or returns the session CSRF token used to protect forms.

function csrfToken(): string
{
    // Student function step: This is the start of csrfToken(). The lines below do the main work of this helper.
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

// Builds the hidden CSRF input used in POST forms.

function csrfField(): string
{
    // Student function step: This is the start of csrfField(). The lines below do the main work of this helper.
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

// Validates the submitted CSRF token before a state-changing action.

function verifyCsrf(): void
{
    // Student function step: This is the start of verifyCsrf(). The lines below do the main work of this helper.
    $token = $_POST['csrf'] ?? '';

    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        die('Invalid or expired form token. Please go back and try again.');
    }
}

// Returns the currently authenticated user from the session.

function currentUser(): ?array
{
    // Student function step: This is the start of currentUser(). The lines below do the main work of this helper.
    return $_SESSION['user'] ?? null;
}

// Checks whether a user session is currently authenticated.

function isLoggedIn(): bool
{
    // Student function step: This is the start of isLoggedIn(). The lines below do the main work of this helper.
    return currentUser() !== null;
}

// Blocks protected pages until a valid user is signed in.

function requireLogin(): void
{
    // Student function step: This is the start of requireLogin(). The lines below do the main work of this helper.
    if (!isLoggedIn()) {
        //  Save a one-time message so the next page can tell the user what happened.
        flash('warning', 'Please sign in to continue.');
        redirect('index.php?auth=signin');
    }

    refreshCurrentUserSecurityState();
    enforceAccountSetup();
}

// Reloads email-verification and password-change flags for the signed-in user.

function refreshCurrentUserSecurityState(): void
{
    // Student function step: This is the start of refreshCurrentUserSecurityState(). The lines below do the main work of this helper.
    global $pdo;

    $user = currentUser();
    if (!$user || !isset($pdo) || !($pdo instanceof PDO)) {
        return;
    }

    $hasVerified = columnExists($pdo, 'users', 'email_verified_at');
    $hasMustChange = columnExists($pdo, 'users', 'must_change_password');
    $hasContact = columnExists($pdo, 'users', 'contact_number');

    if (!$hasVerified && !$hasMustChange && !$hasContact) {
        $_SESSION['user']['auth_schema_ready'] = false;
        return;
    }

    $columns = ['user_id', 'email', 'status'];
    if ($hasVerified) {
        $columns[] = 'email_verified_at';
    }
    if ($hasMustChange) {
        $columns[] = 'must_change_password';
    }
    if ($hasContact) {
        $columns[] = 'contact_number';
    }

    $dbUser = one($pdo, 'SELECT ' . implode(', ', $columns) . ' FROM users WHERE user_id = ?', [(int) $user['id']]);
    if (!$dbUser || ($dbUser['status'] ?? '') !== 'Active') {
        unset($_SESSION['user']);
        //  Save a one-time message so the next page can tell the user what happened.
        flash('warning', 'Your account is inactive. Please contact the administrator.');
        redirect('login.php');
    }

    $_SESSION['user']['email'] = $dbUser['email'] ?? ($_SESSION['user']['email'] ?? '');
    $_SESSION['user']['contact_number'] = $dbUser['contact_number'] ?? null;
    $_SESSION['user']['email_verified_at'] = $dbUser['email_verified_at'] ?? null;
    $_SESSION['user']['must_change_password'] = (int) ($dbUser['must_change_password'] ?? 0);
    $_SESSION['user']['auth_schema_ready'] = $hasVerified && $hasMustChange;
}

// Forces required email verification or first-login password change before dashboard access.
 
function enforceAccountSetup(): void
{
    // Student function step: This is the start of enforceAccountSetup(). The lines below do the main work of this helper.
    $user = currentUser();
    if (!$user || ($user['role'] ?? '') === 'Admin' || empty($user['auth_schema_ready'])) {
        return;
    }

    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($script, ['index.php', 'verify-email.php', 'change-password.php', 'logout.php'], true)) {
        return;
    }

    if (empty($user['email_verified_at'])) {
        //  Redirect to email verification inside the website modal.
        redirect('index.php?auth=verify_email');
    }

    if ((int) ($user['must_change_password'] ?? 0) === 1) {
        //  Redirect to change password inside the website modal.
        redirect('index.php?auth=change_password');
    }
}

// Checks whether a role represents the combined PowerFit Coach account.

function isCoachRole(?string $role = null): bool
{
    // Student function step: This is the start of isCoachRole(). The lines below do the main work of this helper.
    $role = $role ?? (currentUser()['role'] ?? '');

    return in_array($role, ['Coach', 'Trainer', 'Adviser'], true);
}

// Restricts a page to the allowed role or roles.
 
function requireRole(array|string $roles): void
{
    // Student function step: This is the start of requireRole(). The lines below do the main work of this helper.
    //  Protect this page so only a signed-in user can open it.
    requireLogin();
    $roles = (array) $roles;
    $currentRole = currentUser()['role'];

    if (in_array('Coach', $roles, true) && isCoachRole($currentRole)) {
        return;
    }

    if (!in_array($currentRole, $roles, true)) {
        http_response_code(403);
        die('403 - You do not have permission to access this page.');
    }
}

// Returns the correct dashboard path for a user role.
 
function roleDashboard(string $role): string
{
    // Student function step: This is the start of roleDashboard(). The lines below do the main work of this helper.
    return match (true) {
        $role === 'Admin' => 'admin/dashboard.php',
        in_array($role, ['Coach', 'Trainer', 'Adviser'], true) => 'coach/dashboard.php',
        default => 'member/dashboard.php',
    };
}

// Returns the display label used for a role.
 
function roleLabel(string $role): string
{
    // Student function step: This is the start of roleLabel(). The lines below do the main work of this helper.
    return isCoachRole($role) ? 'Coach' : $role;
}

// Formats a numeric value as a PowerFit currency amount.

function money($amount): string
{
    // Student function step: This is the start of money(). The lines below do the main work of this helper.
    return 'LKR ' . number_format((float) $amount, 2);
}

// Formats a database date into a short readable date.
 
function shortDate(?string $date): string
{
    // Student function step: This is the start of shortDate(). The lines below do the main work of this helper.
    if (!$date) {
        return '—';
    }

    return date('d M Y', strtotime($date));
}

// Builds the styled HTML badge used for record statuses.

function statusBadge(string $status): string
{
    // Student function step: This is the start of statusBadge(). The lines below do the main work of this helper.
    $normalized = strtolower($status);
    $class = match (true) {
        in_array($normalized, ['active', 'completed', 'visible', 'good', 'paid', 'published'], true) => 'success',
        in_array($normalized, ['pending', 'frozen', 'satisfactory', 'draft'], true) => 'warning',
        in_array(
            $normalized,
            ['expired', 'inactive', 'cancelled', 'failed', 'suspended', 'flagged', 'needs improvement'],
            true,
        ) => 'danger',
        default => 'secondary',
    };

    return '<span class="badge rounded-pill text-bg-' . $class . '">' . e($status) . '</span>';
}

// Executes a prepared SELECT query and returns one row.
 
function one(PDO $pdo, string $sql, array $params = []): ?array
{
    // Student function step: This is the start of one(). The lines below do the main work of this helper.
    //  Prepare the SQL statement first so user values can be bound safely.
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row ?: null;
}

// Executes a prepared SELECT query and returns all rows.
 
function all(PDO $pdo, string $sql, array $params = []): array
{
    // Student function step: This is the start of all(). The lines below do the main work of this helper.
    //  Prepare the SQL statement first so user values can be bound safely.
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

// Executes a prepared query and returns the first scalar value.

function scalar(PDO $pdo, string $sql, array $params = [])
{
    // Student function step: This is the start of scalar(). The lines below do the main work of this helper.
    //  Prepare the SQL statement first so user values can be bound safely.
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
}

// Checks whether a database table exists before optional features use it.

function tableExists(PDO $pdo, string $table): bool
{
    // Student function step: This is the start of tableExists(). The lines below do the main work of this helper.
    //  Prepare the SQL statement first so user values can be bound safely.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
    );
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() > 0;
}

// Checks whether a database column exists for backward-compatible queries.

function columnExists(PDO $pdo, string $table, string $column): bool
{
    // Student function step: This is the start of columnExists(). The lines below do the main work of this helper.
    //  Prepare the SQL statement first so user values can be bound safely.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

// Trims a text value to a safe maximum length.
 
function safeTextLimit(string $value, int $length): string
{
    // Student function step: This is the start of safeTextLimit(). The lines below do the main work of this helper.
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $length, 'UTF-8');
    }

    return substr($value, 0, $length);
}

// Adds the visible required-field marker used in forms.
 
function requiredLabel(string $label): string
{
    // Student function step: This is the start of requiredLabel(). The lines below do the main work of this helper.
    return e($label) . ' <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> required</span>';
}

// Normalizes a Sri Lankan mobile number to international +94 format.

function normalizeSriLankanMobile(string $phone): ?string
{
    // Student function step: This is the start of normalizeSriLankanMobile(). The lines below do the main work of this helper.
    $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';

    if (preg_match('/^07\d{8}$/', $digits)) {
        return '+94' . substr($digits, 1);
    }
    if (preg_match('/^947\d{8}$/', $digits)) {
        return '+' . $digits;
    }
    if (preg_match('/^7\d{8}$/', $digits)) {
        return '+94' . $digits;
    }

    return null;
}

// Generates a strong temporary password for a new Member or Coach account.

function generateTemporaryPassword(int $length = 12): string
{
    // Student function step: This is the start of generateTemporaryPassword(). The lines below do the main work of this helper.
    $length = max(10, $length);
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $numbers = '23456789';
    $special = '@#$!';
    $all = $upper . $lower . $numbers . $special;

    $password = $upper[random_int(0, strlen($upper) - 1)]
        . $lower[random_int(0, strlen($lower) - 1)]
        . $numbers[random_int(0, strlen($numbers) - 1)]
        . $special[random_int(0, strlen($special) - 1)];

    while (strlen($password) < $length) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }

    $chars = str_split($password);
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }

    return implode('', $chars);
}

// Validates password strength and returns a readable error message when a rule fails.
 
function strongPasswordError(string $password, ?array $user = null): ?string
{
    // Student function step: This is the start of strongPasswordError(). The lines below do the main work of this helper.
    if (strlen($password) < 10) {
        return 'Use at least 10 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Add at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Add at least one lowercase letter.';
    }
    if (!preg_match('/\d/', $password)) {
        return 'Add at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Add at least one special character.';
    }

    $lowerPassword = strtolower($password);
    $username = strtolower(trim((string) ($user['username'] ?? '')));
    $emailLocal = strtolower(trim((string) (($user['email'] ?? '') !== '' ? explode('@', (string) $user['email'], 2)[0] : '')));

    foreach ([$username, $emailLocal] as $piece) {
        if (strlen($piece) >= 4 && str_contains($lowerPassword, $piece)) {
            return 'Do not include your username or email name in the password.';
        }
    }

    return null;
}

// Returns the Member profile linked to a user account.
 
function getMemberByUser(PDO $pdo, int $userId): ?array
{
    // Student function step: This is the start of getMemberByUser(). The lines below do the main work of this helper.
    return one($pdo, 'SELECT * FROM members WHERE user_id = ?', [$userId]);
}

// Returns the training profile linked to a Coach user account.
 
function getTrainerByUser(PDO $pdo, int $userId): ?array
{
    // Student function step: This is the start of getTrainerByUser(). The lines below do the main work of this helper.
    return one($pdo, 'SELECT * FROM trainers WHERE user_id = ?', [$userId]);
}

// Returns the nutrition profile linked to a Coach user account.

function getAdviserByUser(PDO $pdo, int $userId): ?array
{
    // Student function step: This is the start of getAdviserByUser(). The lines below do the main work of this helper.
    return one($pdo, 'SELECT * FROM nutrition_advisers WHERE user_id = ?', [$userId]);
}

// Returns the public coach name stored in the trainer profile.

function coachDisplayName(PDO $pdo, int $userId): string
{
    // Student function step: This is the start of coachDisplayName(). The lines below do the main work of this helper.
    if (columnExists($pdo, 'trainers', 'display_name')) {
        $name = scalar($pdo, 'SELECT display_name FROM trainers WHERE user_id = ?', [$userId]);
        if ($name) {
            return (string) $name;
        }
    }

    return (string) scalar($pdo, 'SELECT username FROM users WHERE user_id = ?', [$userId]);
}


// Calculates the current age from a stored date of birth.

function ageFromDateOfBirth(?string $dateOfBirth): ?int
{
    // Student function step: This is the start of ageFromDateOfBirth(). The lines below do the main work of this helper.
    if (!$dateOfBirth) {
        return null;
    }

    try {
        $dob = new DateTimeImmutable($dateOfBirth);
        $today = new DateTimeImmutable('today');
        return $dob > $today ? null : $dob->diff($today)->y;
    } catch (Throwable $error) {
        return null;
    }
}

// Validates a card number using the Luhn checksum without storing the full number.

function isValidCardNumber(string $cardNumber): bool
{
    // Student function step: This is the start of isValidCardNumber(). The lines below do the main work of this helper.
    $digits = preg_replace('/\D+/', '', $cardNumber) ?? '';
    if (strlen($digits) < 12 || strlen($digits) > 19) {
        return false;
    }

    $sum = 0;
    $double = false;
    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $value = (int) $digits[$i];
        if ($double) {
            $value *= 2;
            if ($value > 9) {
                $value -= 9;
            }
        }
        $sum += $value;
        $double = !$double;
    }

    return $sum % 10 === 0;
}

// Checks that the supplied card expiry month is valid and not expired.

function isValidCardExpiry(string $expiry): bool
{
    // Student function step: This is the start of isValidCardExpiry(). The lines below do the main work of this helper.
    if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', trim($expiry), $matches)) {
        return false;
    }

    $month = (int) $matches[1];
    $year = 2000 + (int) $matches[2];
    try {
        $expiresAt = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $expiresAt = $expiresAt->modify('last day of this month 23:59:59');
        return $expiresAt >= new DateTimeImmutable('now');
    } catch (Throwable $error) {
        return false;
    }
}


// Estimates daily maintenance calories from member age, sex, weight and height for coach review.
 
function estimateDailyCalories(?string $gender, ?int $age, $weightKg, $heightCm): ?array
{
    // Student function step: This is the start of estimateDailyCalories(). The lines below do the main work of this helper.
    $weight = $weightKg !== null ? (float) $weightKg : 0.0;
    $height = $heightCm !== null ? (float) $heightCm : 0.0;
    if ($age === null || $age < 18 || $weight <= 0 || $height <= 0) {
        return null;
    }

    $offset = match ($gender) {
        'Male' => 5,
        'Female' => -161,
        default => null,
    };
    if ($offset === null) {
        return null;
    }

    $bmr = (10 * $weight) + (6.25 * $height) - (5 * $age) + $offset;
    $maintenance = $bmr * 1.375;

    return [
        'bmr' => (int) round($bmr),
        'maintenance' => (int) round($maintenance),
        'activity_label' => 'Light activity estimate',
    ];
}

//  Returns member IDs currently assigned to the signed-in Coach.

function coachAssignedMemberIds(PDO $pdo, int $coachUserId): array
{
    // Student function step: This is the start of coachAssignedMemberIds(). The lines below do the main work of this helper.
    $ids = [];
    $trainer = getTrainerByUser($pdo, $coachUserId);
    $adviser = getAdviserByUser($pdo, $coachUserId);

    if ($trainer) {
        if (tableExists($pdo, 'trainer_assignments')) {
            $ids = array_merge(
                $ids,
                array_column(
                    all($pdo, "SELECT member_id FROM trainer_assignments WHERE trainer_id = ? AND status = 'Active'", [(int) $trainer['trainer_id']]),
                    'member_id',
                ),
            );
        }

        // Legacy databases may have plans even when an assignment row was not migrated.
        if (tableExists($pdo, 'workout_plans')) {
            $ids = array_merge(
                $ids,
                array_column(
                    all($pdo, 'SELECT DISTINCT member_id FROM workout_plans WHERE trainer_id = ?', [(int) $trainer['trainer_id']]),
                    'member_id',
                ),
            );
        }
    }

    if ($adviser) {
        if (tableExists($pdo, 'nutrition_assignments')) {
            $ids = array_merge(
                $ids,
                array_column(
                    all($pdo, "SELECT member_id FROM nutrition_assignments WHERE adviser_id = ? AND status = 'Active'", [(int) $adviser['adviser_id']]),
                    'member_id',
                ),
            );
        }

        if (tableExists($pdo, 'meal_plan')) {
            $ids = array_merge(
                $ids,
                array_column(
                    all($pdo, 'SELECT DISTINCT member_id FROM meal_plan WHERE adviser_id = ?', [(int) $adviser['adviser_id']]),
                    'member_id',
                ),
            );
        }
    }

    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, static fn(int $id): bool => $id > 0);

    return array_values(array_unique($ids));
}

// Checks that a Coach is allowed to view a specific assigned member.
 
function coachCanAccessMember(PDO $pdo, int $coachUserId, int $memberId): bool
{
    // Student function step: This is the start of coachCanAccessMember(). The lines below do the main work of this helper.
    return in_array($memberId, coachAssignedMemberIds($pdo, $coachUserId), true);
}

// Creates an in-app notification for a user.
 
function notifyUser(PDO $pdo, int $userId, string $title, string $message, string $type = 'System'): void
{
    // Student function step: This is the start of notifyUser(). The lines below do the main work of this helper.
    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare(
        'INSERT INTO notifications(user_id, title, message, type, is_read, created_date) VALUES(?, ?, ?, ?, 0, NOW())',
    )->execute([$userId, safeTextLimit($title, 150), safeTextLimit($message, 500), safeTextLimit($type, 50)]);
}

// Returns the user-account ID linked to a Member record.
 
function memberUserId(PDO $pdo, int $memberId): ?int
{
    // Student function step: This is the start of memberUserId(). The lines below do the main work of this helper.
    $value = scalar($pdo, 'SELECT user_id FROM members WHERE member_id = ?', [$memberId]);
    return $value !== false && $value !== null ? (int) $value : null;
}

// Validates and stores an uploaded image using a generated safe filename.
 
function saveUploadedImage(array $file, string $directory, string $prefix = 'img-'): string
{
    // Student function step: This is the start of saveUploadedImage(). The lines below do the main work of this helper.
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('No image was selected.');
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The image upload did not complete successfully.');
    }

    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Use an image smaller than 5 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Use a JPG, PNG or WEBP image.');
    }

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('The upload folder could not be created.');
    }

    $name = $prefix . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $name)) {
        throw new RuntimeException('The uploaded image could not be saved.');
    }

    return $name;
}

// Deletes a previously stored upload when it is no longer needed.
 
function removeUploadFile(string $directory, ?string $fileName): void
{
    // Student function step: This is the start of removeUploadFile(). The lines below do the main work of this helper.
    if (!$fileName) {
        return;
    }

    $path = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . basename($fileName);
    if (is_file($path)) {
        @unlink($path);
    }
}

// Returns the correct package image URL or the matching default image.

function packageImageUrl(array $package): string
{
    // Student function step: This is the start of packageImageUrl(). The lines below do the main work of this helper.
    if (!empty($package['image_path'])) {
        return url('uploads/packages/' . rawurlencode(basename((string) $package['image_path'])));
    }

    $name = strtolower((string) ($package['package_name'] ?? ''));
    if (str_contains($name, 'annual')) {
        return url('assets/images/advantage-members.webp');
    }
    if (str_contains($name, 'personal')) {
        return url('assets/images/advantage-training.webp');
    }
    if (str_contains($name, 'nutrition')) {
        return url('assets/images/advantage-nutrition.webp');
    }

    return url('assets/images/advantage-members.webp');
}

// Returns the CSS class used to highlight the current navigation item.

function navActive(string $needle): string
{
    // Student function step: This is the start of navActive(). The lines below do the main work of this helper.
    return str_contains($_SERVER['PHP_SELF'] ?? '', $needle) ? 'active' : '';
}

// Converts a title into a URL-safe blog slug.

function slugify(string $value): string
{
    // Student function step: This is the start of slugify(). The lines below do the main work of this helper.
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';

    return trim($value, '-') ?: 'post-' . time();
}

// Returns dashboard navigation items allowed for the current role.
 
function dashboardNavItems(string $role): array
{
    // Student function step: This is the start of dashboardNavItems(). The lines below do the main work of this helper.
    if ($role === 'Admin') {
        return [
            ['Overview', 'admin/dashboard.php', 'bi-grid-1x2'],
            ['Members', 'admin/members.php', 'bi-people'],
            ['Packages', 'admin/packages.php', 'bi-box-seam'],
            ['Payments', 'admin/payments.php', 'bi-receipt'],
            ['Staff', 'admin/staff.php', 'bi-person-badge'],
            ['Blogs', 'admin/blogs.php', 'bi-journal-text'],
            ['Reports', 'admin/reports.php', 'bi-bar-chart'],
            ['Messages', 'admin/contact_messages.php', 'bi-chat-left-text'],
        ];
    }

    if (isCoachRole($role)) {
        return [
            ['Overview', 'coach/dashboard.php', 'bi-grid-1x2'],
            ['Members', 'coach/members.php', 'bi-people'],
            ['Workouts', 'coach/workouts.php', 'bi-activity'],
            ['Meal plans', 'coach/meals.php', 'bi-egg-fried'],
            ['Progress reports', 'coach/progress_reports.php', 'bi-clipboard-data'],
        ];
    }

    return [
        ['Overview', 'member/dashboard.php', 'bi-grid-1x2'],
        ['Workout', 'member/workout.php', 'bi-activity'],
        ['Meals', 'member/meals.php', 'bi-egg-fried'],
        ['Progress', 'member/progress.php', 'bi-graph-up-arrow'],
        ['Payments', 'member/payments.php', 'bi-receipt'],
        ['Rate coach', 'member/rating.php', 'bi-star'],
    ];
}

// Recalculates and stores a coach rating total and average.

function syncTrainerRating(PDO $pdo, int $trainerId): void
{
    // Student function step: This is the start of syncTrainerRating(). The lines below do the main work of this helper.
    $average = (float) scalar(
        $pdo,
        "SELECT COALESCE(AVG(score), 0) FROM trainer_ratings WHERE trainer_id = ? AND status = 'Visible'",
        [$trainerId],
    );
    $total = (float) scalar(
        $pdo,
        "SELECT COALESCE(SUM(score), 0) FROM trainer_ratings WHERE trainer_id = ? AND status = 'Visible'",
        [$trainerId],
    );
    //  Prepare and run this SQL statement using PDO.
    $pdo->prepare('UPDATE trainers SET average_rating = ?, total_rating = ? WHERE trainer_id = ?')->execute([
        $average,
        $total,
        $trainerId,
    ]);
}
