<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
start_app_session();

function api_json_response(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function api_read_payload(): array
{
    try {
        $payload = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        api_json_response(400, ['success' => false, 'error' => 'Request body must contain valid JSON.']);
    }

    if (!is_array($payload)) {
        api_json_response(400, ['success' => false, 'error' => 'Request body must be a JSON object.']);
    }

    return $payload;
}

function api_validate_student(array $payload): array
{
    $values = [];
    foreach (['enrollment_no', 'full_name', 'email', 'course'] as $field) {
        if (!isset($payload[$field]) || !is_string($payload[$field])) {
            api_json_response(422, ['success' => false, 'error' => 'All student fields are required.', 'field' => $field]);
        }
        $values[$field] = trim($payload[$field]);
    }

    if (
        $values['enrollment_no'] === ''
        || mb_strlen($values['enrollment_no']) > 20
        || $values['full_name'] === ''
        || mb_strlen($values['full_name']) > 100
        || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false
        || mb_strlen($values['email']) > 254
        || $values['course'] === ''
        || mb_strlen($values['course']) > 100
    ) {
        api_json_response(422, ['success' => false, 'error' => 'Check the enrollment number, name, email, and course values.']);
    }

    return $values;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    header('Allow: GET, POST, PUT, DELETE');
    api_json_response(405, ['success' => false, 'error' => 'This HTTP method is not supported.']);
}

if (!isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['last_activity'])) {
    api_json_response(401, ['success' => false, 'error' => 'Sign in with an Admin account to access student records.']);
}
if (time() - (int) $_SESSION['last_activity'] >= SESSION_TIMEOUT_SECONDS) {
    $_SESSION = [];
    api_json_response(401, ['success' => false, 'error' => 'Your session expired. Sign in again.']);
}
if ($_SESSION['role'] !== 'Admin') {
    api_json_response(403, ['success' => false, 'error' => 'Only Admin accounts may manage student records.']);
}
$_SESSION['last_activity'] = time();

if ($method !== 'GET') {
    $submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (
        !is_string($submittedToken)
        || !isset($_SESSION['csrf_token'])
        || !hash_equals((string) $_SESSION['csrf_token'], $submittedToken)
    ) {
        api_json_response(403, ['success' => false, 'error' => 'The security token is invalid. Refresh the page and try again.']);
    }
}

try {
    require_once __DIR__ . '/db.php';

    if ($method === 'GET') {
        $statement = $conn->prepare(
            "SELECT student_id, enrollment_no, full_name, email, course
            FROM students
            WHERE status = 'active'
            ORDER BY student_id DESC"
        );
        $statement->execute();
        $students = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        $statement->close();
        $conn->close();

        api_json_response(200, [
            'success' => true,
            'data' => $students,
            'csrfToken' => app_csrf_token(),
        ]);
    }

    $payload = api_read_payload();
    if ($method === 'POST') {
        $student = api_validate_student($payload);
        $statement = $conn->prepare(
            'INSERT INTO students (enrollment_no, full_name, email, course)
            VALUES (?, ?, ?, ?)'
        );
        $statement->bind_param(
            'ssss',
            $student['enrollment_no'],
            $student['full_name'],
            $student['email'],
            $student['course']
        );
        $statement->execute();
        $studentId = $conn->insert_id;
        $statement->close();
        app_audit_log($conn, 'student.created', 'student', (int) $studentId);
        $conn->close();
        api_json_response(201, ['success' => true, 'message' => 'Student added successfully.', 'data' => ['student_id' => $studentId] + $student]);
    }

    $studentId = filter_var($payload['student_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($studentId === false) {
        $conn->close();
        api_json_response(422, ['success' => false, 'error' => 'A valid student_id is required.']);
    }

    if ($method === 'PUT') {
        $student = api_validate_student($payload);
        $statement = $conn->prepare(
            "UPDATE students
            SET enrollment_no = ?, full_name = ?, email = ?, course = ?
            WHERE student_id = ? AND status = 'active'"
        );
        $statement->bind_param(
            'ssssi',
            $student['enrollment_no'],
            $student['full_name'],
            $student['email'],
            $student['course'],
            $studentId
        );
        $statement->execute();
        $matched = $statement->affected_rows === 1;
        if (!$matched) {
            $exists = $conn->prepare("SELECT 1 FROM students WHERE student_id = ? AND status = 'active'");
            $exists->bind_param('i', $studentId);
            $exists->execute();
            $matched = $exists->get_result()->num_rows === 1;
            $exists->close();
        }
        $statement->close();
        app_audit_log($conn, 'student.updated', 'student', (int) $studentId);
        $conn->close();
        if (!$matched) {
            api_json_response(404, ['success' => false, 'error' => 'The active student record was not found.']);
        }
        api_json_response(200, ['success' => true, 'message' => 'Student details updated successfully.', 'data' => ['student_id' => $studentId] + $student]);
    }

    $statement = $conn->prepare(
        "UPDATE students AS s
        SET status = 'inactive'
        WHERE s.student_id = ? AND s.status = 'active'
          AND NOT EXISTS (
              SELECT 1 FROM student_accounts AS sa
              WHERE sa.student_id = s.student_id AND sa.role = 'Admin'
          )"
    );
    $statement->bind_param('i', $studentId);
    $statement->execute();
    $deleted = $statement->affected_rows === 1;
    $statement->close();
    if ($deleted) {
        app_audit_log($conn, 'student.deactivated', 'student', (int) $studentId);
    }
    $conn->close();
    if (!$deleted) {
        api_json_response(404, ['success' => false, 'error' => 'The active student record was not found or cannot be deleted.']);
    }
    api_json_response(200, ['success' => true, 'message' => 'Student marked inactive successfully.']);
} catch (mysqli_sql_exception $exception) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }

    error_log('[StudentHub] Student JSON API failed: ' . $exception->getMessage());
    if ($exception->getCode() === 1062) {
        api_json_response(409, ['success' => false, 'error' => 'That enrollment number or email is already in use.']);
    }
    api_json_response(500, ['success' => false, 'error' => 'The student records service encountered a database error.']);
} catch (RuntimeException $exception) {
    api_json_response(500, ['success' => false, 'error' => $exception->getMessage()]);
}
