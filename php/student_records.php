<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
require_user_role('Admin');

function student_records_query(mysqli $conn, string $search, string $course, string $status): array
{
    $statement = $conn->prepare(
        'SELECT s.student_id, s.enrollment_no, s.full_name, s.email, s.mobile, s.course,
                s.study_year, s.gender, s.status, s.created_at, sa.role AS account_role
        FROM students AS s
        LEFT JOIN student_accounts AS sa ON sa.student_id = s.student_id
        WHERE (? = \'\' OR s.full_name LIKE ? OR s.email LIKE ? OR s.enrollment_no LIKE ?)
          AND (? = \'\' OR s.course = ?)
          AND (? = \'all\' OR s.status = ?)
        ORDER BY s.student_id DESC'
    );
    $searchPattern = '%' . $search . '%';
    $statement->bind_param(
        'ssssssss',
        $search,
        $searchPattern,
        $searchPattern,
        $searchPattern,
        $course,
        $course,
        $status,
        $status
    );
    $statement->execute();
    $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();

    return $rows;
}

function student_records_redirect(string $result): never
{
    header('Location: student_records.php?result=' . rawurlencode($result));
    exit;
}

$search = trim((string) ($_GET['search'] ?? ''));
$courseFilter = trim((string) ($_GET['course'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'active', 'inactive'], true)) {
    $statusFilter = 'all';
}

$results = [
    'created' => ['success', 'Student added successfully.'],
    'updated' => ['success', 'Student details updated successfully.'],
    'deactivated' => ['success', 'Student marked inactive.'],
    'activated' => ['success', 'Student marked active.'],
    'duplicate' => ['error', 'That enrollment number or email is already in use.'],
    'invalid' => ['error', 'Please check the required fields and try again.'],
    'csrf' => ['error', 'Your session expired. Refresh the page and try again.'],
    'not_found' => ['error', 'That student record could not be found or was already inactive.'],
    'database' => ['error', 'The student record could not be saved. Check the database connection and try again.'],
];
$notice = $results[(string) ($_GET['result'] ?? '')] ?? null;
$databaseError = null;
$students = [];
$courses = [];
$editingStudent = null;

try {
    require_once __DIR__ . '/db.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!is_string($submittedToken) || !hash_equals(app_csrf_token(), $submittedToken)) {
            $conn->close();
            student_records_redirect('csrf');
        }

        $action = (string) ($_POST['action'] ?? '');
        $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($action === 'deactivate' || $action === 'activate') {
            if ($studentId === false) {
                $conn->close();
                student_records_redirect('not_found');
            }

            $newStatus = $action === 'deactivate' ? 'inactive' : 'active';
            $expectedStatus = $action === 'deactivate' ? 'active' : 'inactive';
            $statement = $conn->prepare('
                UPDATE students AS s
                SET status = ?
                WHERE s.student_id = ? AND s.status = ?
                  AND NOT EXISTS (
                      SELECT 1 FROM student_accounts AS sa
                      WHERE sa.student_id = s.student_id AND sa.role = \'Admin\'
                  )'
            );
            $statement->bind_param('sis', $newStatus, $studentId, $expectedStatus);
            $statement->execute();
            $changed = $statement->affected_rows === 1;
            $statement->close();
            if ($changed) {
                app_audit_log(
                    $conn,
                    $newStatus === 'inactive' ? 'student.deactivated' : 'student.activated',
                    'student',
                    (int) $studentId
                );
            }
            $conn->close();
            student_records_redirect($changed ? ($action === 'deactivate' ? 'deactivated' : 'activated') : 'not_found');
        }

        if ($action !== 'create' && $action !== 'update') {
            $conn->close();
            student_records_redirect('invalid');
        }

        $enrollmentNo = trim((string) ($_POST['enrollment_no'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $mobile = trim((string) ($_POST['mobile'] ?? ''));
        $course = trim((string) ($_POST['course'] ?? ''));
        $studyYearInput = trim((string) ($_POST['study_year'] ?? ''));
        $genderInput = (string) ($_POST['gender'] ?? '');

        $studyYear = $studyYearInput === '' ? null : filter_var(
            $studyYearInput,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 255]]
        );
        $gender = in_array($genderInput, ['male', 'female', 'other'], true) ? $genderInput : null;
        $valid = $enrollmentNo !== ''
            && mb_strlen($enrollmentNo) <= 20
            && $fullName !== ''
            && mb_strlen($fullName) <= 100
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && mb_strlen($email) <= 254
            && mb_strlen($mobile) <= 20
            && $course !== ''
            && mb_strlen($course) <= 100
            && ($studyYearInput === '' || $studyYear !== false)
            && ($genderInput === '' || $gender !== null)
            && ($action !== 'update' || $studentId !== false);

        if (!$valid) {
            $conn->close();
            student_records_redirect('invalid');
        }

        if ($action === 'update') {
            $existsStatement = $conn->prepare('SELECT 1 FROM students WHERE student_id = ?');
            $existsStatement->bind_param('i', $studentId);
            $existsStatement->execute();
            $studentExists = $existsStatement->get_result()->num_rows === 1;
            $existsStatement->close();
            if (!$studentExists) {
                $conn->close();
                student_records_redirect('not_found');
            }
        }

        $mobileValue = $mobile === '' ? null : $mobile;
        if ($action === 'create') {
            $statement = $conn->prepare(
                'INSERT INTO students (enrollment_no, full_name, email, mobile, course, study_year, gender)
                VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->bind_param(
                'sssssis',
                $enrollmentNo,
                $fullName,
                $email,
                $mobileValue,
                $course,
                $studyYear,
                $gender
            );
            $successResult = 'created';
        } else {
            $statement = $conn->prepare(
                'UPDATE students
                SET enrollment_no = ?, full_name = ?, email = ?, mobile = ?, course = ?, study_year = ?, gender = ?
                WHERE student_id = ?'
            );
            $statement->bind_param(
                'sssssisi',
                $enrollmentNo,
                $fullName,
                $email,
                $mobileValue,
                $course,
                $studyYear,
                $gender,
                $studentId
            );
            $successResult = 'updated';
        }

        try {
            $statement->execute();
        } catch (mysqli_sql_exception $exception) {
            $statement->close();
            $conn->close();
            if ($exception->getCode() === 1062) {
                student_records_redirect('duplicate');
            }

            throw $exception;
        }
        $statement->close();
        $savedStudentId = $action === 'create' ? (int) $conn->insert_id : (int) $studentId;
        app_audit_log(
            $conn,
            'student.' . ($action === 'create' ? 'created' : 'updated'),
            'student',
            $savedStudentId
        );
        $conn->close();
        student_records_redirect($successResult);
    }

    if (isset($_GET['edit'])) {
        $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($editId !== false) {
            $statement = $conn->prepare('SELECT * FROM students WHERE student_id = ?');
            $statement->bind_param('i', $editId);
            $statement->execute();
            $editingStudent = $statement->get_result()->fetch_assoc() ?: null;
            $statement->close();
        }
    }

    $courseResult = $conn->query('SELECT DISTINCT course FROM students ORDER BY course');
    $courses = array_column($courseResult->fetch_all(MYSQLI_NUM), 0);
    $students = student_records_query($conn, $search, $courseFilter, $statusFilter);

    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="student-records.csv"');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('Could not open CSV output stream.');
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Student ID', 'Enrollment No.', 'Full Name', 'Email', 'Mobile', 'Course', 'Study Year', 'Gender', 'Status', 'Created At'], ',', '"', '');
        foreach ($students as $student) {
            $values = [
                $student['student_id'],
                $student['enrollment_no'],
                $student['full_name'],
                $student['email'],
                $student['mobile'],
                $student['course'],
                $student['study_year'],
                $student['gender'],
                $student['status'],
                $student['created_at'],
            ];
            foreach ($values as &$value) {
                $value = (string) ($value ?? '');
                if (preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $value) === 1) {
                    $value = "'" . $value;
                }
            }
            unset($value);
            fputcsv($output, $values, ',', '"', '');
        }
        fclose($output);
        $conn->close();
        exit;
    }

    $conn->close();
} catch (mysqli_sql_exception $exception) {
    error_log('[StudentHub] Student records query failed: ' . $exception->getMessage());
    http_response_code(500);
    $databaseError = 'Student records could not be loaded. Check the database connection and apply the student status migration.';
} catch (RuntimeException $exception) {
    http_response_code(500);
    $databaseError = $exception->getMessage();
}

$filterQuery = http_build_query(['search' => $search, 'course' => $courseFilter, 'status' => $statusFilter]);
$exportUrl = 'student_records.php?' . $filterQuery . '&export=csv';
$editingValues = is_array($editingStudent) ? $editingStudent : [
    'student_id' => '',
    'enrollment_no' => '',
    'full_name' => '',
    'email' => '',
    'mobile' => '',
    'course' => '',
    'study_year' => '',
    'gender' => '',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records | Student Hub</title>
    <link rel="stylesheet" href="css/style.css?v=20261028.2">
    <link rel="stylesheet" href="css/interactive.css?v=20261028.2">
    <link rel="stylesheet" href="css/role-dashboard.css?v=20261028.2">
    <link rel="stylesheet" href="css/student-records.css">
</head>
<body>
    <?php app_render_portal_header('Student records'); ?>

    <main class="app-container role-main student-records-main">
        <div class="role-welcome">
            <div>
                <p class="role-eyebrow">ADMINISTRATION</p>
                <h1>Student records</h1>
                <p>Add, update, search, filter, export, or deactivate student records.</p>
            </div>
            <span class="role-welcome-icon" aria-hidden="true">♙</span>
        </div>

        <?php if ($notice !== null): ?>
            <div class="student-notice student-notice-<?= app_escape($notice[0]) ?>" role="<?= $notice[0] === 'success' ? 'status' : 'alert' ?>">
                <?= app_escape($notice[1]) ?>
            </div>
        <?php endif; ?>

        <?php if ($databaseError !== null): ?>
            <div class="role-error" role="alert"><?= app_escape($databaseError) ?></div>
        <?php else: ?>
            <section class="role-content-card student-form-card" aria-labelledby="student-form-title">
                <div class="role-section-heading">
                    <div>
                        <h2 id="student-form-title"><?= $editingStudent !== null ? 'Update student details' : 'Add a student' ?></h2>
                        <p>Fields marked with * are required.</p>
                    </div>
                </div>
                <form method="post" action="student_records.php" class="student-record-form">
                    <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                    <input type="hidden" name="action" value="<?= $editingStudent !== null ? 'update' : 'create' ?>">
                    <?php if ($editingStudent !== null): ?>
                        <input type="hidden" name="student_id" value="<?= (int) $editingValues['student_id'] ?>">
                    <?php endif; ?>
                    <label>Enrollment number *
                        <input name="enrollment_no" maxlength="20" required value="<?= app_escape((string) $editingValues['enrollment_no']) ?>">
                    </label>
                    <label>Full name *
                        <input name="full_name" maxlength="100" required value="<?= app_escape((string) $editingValues['full_name']) ?>">
                    </label>
                    <label>Email *
                        <input type="email" name="email" maxlength="254" required value="<?= app_escape((string) $editingValues['email']) ?>">
                    </label>
                    <label>Mobile
                        <input name="mobile" maxlength="20" value="<?= app_escape((string) ($editingValues['mobile'] ?? '')) ?>">
                    </label>
                    <label>Course *
                        <input name="course" maxlength="100" list="course-options" required value="<?= app_escape((string) $editingValues['course']) ?>">
                        <datalist id="course-options">
                            <?php foreach ($courses as $courseOption): ?>
                                <option value="<?= app_escape((string) $courseOption) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </label>
                    <label>Study year
                        <input type="number" name="study_year" min="1" max="255" value="<?= app_escape((string) ($editingValues['study_year'] ?? '')) ?>">
                    </label>
                    <label>Gender
                        <select name="gender">
                            <option value="">Not specified</option>
                            <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label): ?>
                                <option value="<?= $value ?>" <?= ($editingValues['gender'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="student-form-actions">
                        <button class="student-button" type="submit"><?= $editingStudent !== null ? 'Save changes' : 'Add student' ?></button>
                        <?php if ($editingStudent !== null): ?>
                            <a class="student-button student-button-secondary" href="student_records.php">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="role-content-card">
                <div class="role-section-heading student-list-heading">
                    <div>
                        <h2>Student list</h2>
                        <p>Records shown: <?= count($students) ?></p>
                    </div>
                    <a class="student-button student-button-secondary" href="<?= app_escape($exportUrl) ?>">Export CSV</a>
                </div>
                <form method="get" action="student_records.php" class="student-filters">
                    <label>Search
                        <input type="search" name="search" placeholder="Name, email, or enrollment" value="<?= app_escape($search) ?>">
                    </label>
                    <label>Course
                        <select name="course">
                            <option value="">All courses</option>
                            <?php foreach ($courses as $courseOption): ?>
                                <option value="<?= app_escape((string) $courseOption) ?>" <?= $courseFilter === $courseOption ? 'selected' : '' ?>><?= app_escape((string) $courseOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Status
                        <select name="status">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </label>
                    <div class="student-filter-actions">
                        <button class="student-button" type="submit">Apply filters</button>
                        <a class="student-button student-button-secondary" href="student_records.php">Clear</a>
                    </div>
                </form>

                <?php if ($students === []): ?>
                    <p class="role-empty">No student records match those filters.</p>
                <?php else: ?>
                    <div class="role-table-wrap">
                        <table class="role-table student-record-table">
                            <thead>
                                <tr><th>Student</th><th>Enrollment</th><th>Course</th><th>Year</th><th>Status</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><strong><?= app_escape($student['full_name']) ?></strong><small><?= app_escape($student['email']) ?><?= $student['mobile'] ? ' · ' . app_escape($student['mobile']) : '' ?></small></td>
                                        <td><?= app_escape($student['enrollment_no']) ?></td>
                                        <td><?= app_escape($student['course']) ?></td>
                                        <td><?= app_escape((string) ($student['study_year'] ?? '—')) ?></td>
                                        <td><span class="student-status student-status-<?= app_escape($student['status']) ?>"><?= ucfirst(app_escape($student['status'])) ?></span></td>
                                        <td>
                                            <div class="student-row-actions">
                                                <a class="student-action-link" href="student_records.php?edit=<?= (int) $student['student_id'] ?>&amp;<?= app_escape($filterQuery) ?>">Edit</a>
                                                <?php if ($student['account_role'] !== 'Admin'): ?>
                                                    <form method="post" action="student_records.php" onsubmit="return confirm('<?= $student['status'] === 'active' ? 'Mark this student inactive? They will no longer be able to sign in.' : 'Restore this student to active status?' ?>');">
                                                        <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                                                        <input type="hidden" name="student_id" value="<?= (int) $student['student_id'] ?>">
                                                        <input type="hidden" name="action" value="<?= $student['status'] === 'active' ? 'deactivate' : 'activate' ?>">
                                                        <button class="student-action-link student-action-danger" type="submit"><?= $student['status'] === 'active' ? 'Deactivate' : 'Restore' ?></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
    <footer class="app-footer"><p>© 2026 Student Hub Portal. Administrator access is active.</p></footer>
    <script src="js/interactive.js?v=20261028.2"></script>
    <script src="js/script.js?v=20261028.4"></script>
</body>
</html>
