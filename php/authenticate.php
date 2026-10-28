<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
start_app_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (!is_string($submittedToken) || !hash_equals(app_csrf_token(), $submittedToken)) {
    header('Location: login.php?reason=csrf');
    exit;
}

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$selectedRole = $_POST['role'] ?? '';
if (
    !filter_var($email, FILTER_VALIDATE_EMAIL)
    || $password === ''
    || !is_string($selectedRole)
    || !in_array($selectedRole, ['Admin', 'Student'], true)
) {
    header('Location: login.php?reason=invalid');
    exit;
}

try {
    require __DIR__ . '/db.php';

    $statement = $conn->prepare(
        'SELECT sa.account_id, sa.student_id, sa.username, sa.password_hash, sa.role,
                s.full_name, s.email, s.enrollment_no, s.course, s.study_year
        FROM student_accounts AS sa
        INNER JOIN students AS s ON s.student_id = sa.student_id
        WHERE s.email = ? AND s.status = \'active\'
        LIMIT 1'
    );
    $statement->bind_param('s', $email);
    $statement->execute();
    $result = $statement->get_result();
    $user = $result->fetch_assoc();
    $statement->close();
    $conn->close();
} catch (RuntimeException $exception) {
    header('Location: login.php?error=service');
    exit;
} catch (mysqli_sql_exception $exception) {
    error_log('[StudentHub] Login query failed: ' . $exception->getMessage());
    header('Location: login.php?error=service');
    exit;
}

if (!is_array($user) || !in_array($user['role'], ['Admin', 'Student'], true)) {
    error_log('[StudentHub] Login rejected: no matching account or invalid role.');
    header('Location: login.php?reason=invalid');
    exit;
}

if (!password_verify($password, $user['password_hash'])) {
    error_log('[StudentHub] Login rejected: password verification failed.');
    header('Location: login.php?reason=invalid');
    exit;
}

if ($selectedRole !== $user['role']) {
    header('Location: login.php?reason=unauthorized_login');
    exit;
}

session_regenerate_id(true);
$_SESSION = [
    'user_id' => (int) $user['student_id'],
    'account_id' => (int) $user['account_id'],
    'email' => $user['email'],
    'name' => $user['full_name'],
    'enrollment_no' => $user['enrollment_no'],
    'course' => $user['course'],
    'study_year' => $user['study_year'],
    'role' => $user['role'],
    'last_activity' => time(),
    'login_regenerated' => true,
    'csrf_token' => bin2hex(random_bytes(32)),
];

header('Location: dashboard.php');
exit;
