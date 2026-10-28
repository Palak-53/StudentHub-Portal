<?php
/**
 * =========================================================
 * STUDENT HUB — PHP FORM PROCESSOR
 * process.php
 *
 * Handles POST requests for Registration & Contact forms.
 * Features:
 *  - Server-side input sanitization
 *  - Strict regex & type validation
 *  - PDO student registration storage with hashed passwords
 *  - Locked JSON file storage for contact submissions
 *  - Clear success & validation error responses with modern UI
 * =========================================================
 */

require_once __DIR__ . '/submission_storage.php';

$errors = [];
$success = false;
$formData = [];
$formType = "Unknown";
$submissionRecord = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $requestedFormType = sanitize_input($_POST['form_type'] ?? '');
    if ($requestedFormType === 'Contact Form') {
        $formType = "Contact Form";
    } elseif ($requestedFormType === 'Registration Form') {
        $formType = "Registration Form";
    } elseif ($requestedFormType === '' && (isset($_POST['subject']) || isset($_POST['message']))) {
        $formType = "Contact Form";
    } elseif ($requestedFormType === '') {
        $formType = "Registration Form";
    } else {
        $errors['form_type'] = "Invalid form type.";
    }

    // Common Fields
    $fullName = isset($_POST['fullName']) ? sanitize_input($_POST['fullName']) : '';
    $email    = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
    $mobile   = isset($_POST['mobile']) ? sanitize_input($_POST['mobile']) : '';
    $terms    = isset($_POST['terms']) ? true : false;

    // Validation 1: Full Name
    if (empty($fullName)) {
        $errors['fullName'] = "Full Name is required.";
    } else if (!preg_match("/^[\p{L}][\p{L}\s'-]{1,59}$/u", $fullName)) {
        $errors['fullName'] = "Full Name must be 2–60 characters and contain only letters, spaces, apostrophes, or hyphens.";
    }

    // Validation 2: Email
    if (empty($email)) {
        $errors['email'] = "Email address is required.";
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address (e.g. user@domain.com).";
    }

    // Validation 3: Mobile Number
    if (empty($mobile)) {
        $errors['mobile'] = "Mobile number is required.";
    } else if (!preg_match("/^[6-9]\d{9}$/", $mobile)) {
        $errors['mobile'] = "Mobile number must be a valid 10-digit Indian number starting with 6-9.";
    }

    // Form Specific Validation
    if ($formType === "Contact Form") {
        $category = isset($_POST['category']) ? sanitize_input($_POST['category']) : '';
        $subject  = isset($_POST['subject']) ? sanitize_input($_POST['subject']) : '';
        $message  = isset($_POST['message']) ? sanitize_input($_POST['message']) : '';

        $allowedCategories = ['General Query', 'Admissions', 'Attendance & Leave', 'Fees & Accounts', 'Technical Support'];
        if (!in_array($category, $allowedCategories, true)) {
            $errors['category'] = "Please select a valid query category.";
        }
        if (empty($subject)) {
            $errors['subject'] = "Subject is required.";
        } else if (strlen($subject) < 5) {
            $errors['subject'] = "Subject must be at least 5 characters long.";
        }
        if (empty($message)) {
            $errors['message'] = "Message content is required.";
        } else if (strlen($message) < 10) {
            $errors['message'] = "Message must be at least 10 characters long.";
        }

        $formData = [
            'fullName' => $fullName,
            'email' => $email,
            'mobile' => $mobile,
            'category' => $category,
            'subject' => $subject,
            'message' => $message
        ];

    } else { // Registration Form
        $enrollmentNumber = isset($_POST['enrollmentNumber']) ? sanitize_input($_POST['enrollmentNumber']) : '';
        $course           = isset($_POST['course']) ? sanitize_input($_POST['course']) : '';
        $year             = isset($_POST['year']) ? sanitize_input($_POST['year']) : '';
        $gender           = isset($_POST['gender']) ? sanitize_input($_POST['gender']) : '';
        $password         = isset($_POST['password']) ? $_POST['password'] : '';
        $confirmPassword  = isset($_POST['confirmPassword']) ? $_POST['confirmPassword'] : '';

        if (empty($enrollmentNumber)) {
            $errors['enrollmentNumber'] = "Enrollment Number is required.";
        } elseif (!preg_match("/^[A-Za-z0-9\-]{4,20}$/", $enrollmentNumber)) {
            $errors['enrollmentNumber'] = "Enrollment must be 4–20 alphanumeric characters.";
        }

        $allowedCourses = [
            'btech-cse' => 'B.Tech CSE',
            'btech-it' => 'B.Tech IT',
            'btech-ec' => 'B.Tech EC',
            'btech-me' => 'B.Tech ME',
            'bca' => 'BCA',
            'mca' => 'MCA',
            'mtech-cse' => 'M.Tech CSE',
        ];
        if (!isset($allowedCourses[$course])) {
            $errors['course'] = "Please select a valid course.";
        }
        if (!in_array($year, ['1', '2', '3', '4'], true)) {
            $errors['year'] = "Please select your academic year.";
        }
        if (!in_array($gender, ['male', 'female', 'other'], true)) {
            $errors['gender'] = "Please select your gender.";
        }

        if (empty($password)) {
            $errors['password'] = "Password is required.";
        } elseif (strlen($password) < 8) {
            $errors['password'] = "Password must be at least 8 characters long.";
        } elseif (strlen($password) > 64 || !preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[0-9]/", $password) || !preg_match("/[!@#$%^&*()_+\-=\[\]{};':\"\\\\|,.<>\/?`~]/", $password)) {
            $errors['password'] = "Password must be 8–64 characters and include uppercase, lowercase, a number, and a special character.";
        }

        if (empty($confirmPassword)) {
            $errors['confirmPassword'] = "Confirm Password is required.";
        } elseif ($password !== $confirmPassword) {
            $errors['confirmPassword'] = "Passwords do not match.";
        }

        $formData = [
            'enrollmentNumber' => $enrollmentNumber,
            'fullName'         => $fullName,
            'email'            => $email,
            'mobile'           => $mobile,
            'course'           => $allowedCourses[$course] ?? $course,
            'year'             => $year,
            'gender'           => $gender
        ];
    }

    // Validation: Terms Agreement
    if (!$terms) {
        $errors['terms'] = "You must accept the Terms and Conditions to proceed.";
    }

    // Save only validated data, and report success only after storage completes.
    if (empty($errors)) {
        if ($formType === 'Registration Form') {
            try {
                require_once __DIR__ . '/pdo_db.php';

                $duplicateCheck = $pdo->prepare(
                    'SELECT enrollment_no, email
                    FROM students
                    WHERE enrollment_no = :enrollment_no OR email = :email'
                );
                $duplicateCheck->execute([
                    'enrollment_no' => $enrollmentNumber,
                    'email' => $email,
                ]);

                foreach ($duplicateCheck->fetchAll() as $existingStudent) {
                    if (strcasecmp($existingStudent['enrollment_no'], $enrollmentNumber) === 0) {
                        $errors['enrollmentNumber'] = 'This enrollment number/username is already registered.';
                    }
                    if (strcasecmp($existingStudent['email'], $email) === 0) {
                        $errors['email'] = 'This email address is already registered.';
                    }
                }

                if (empty($errors)) {
                    $pdo->beginTransaction();

                    $studentInsert = $pdo->prepare(
                        'INSERT INTO students (enrollment_no, full_name, email, mobile, course, study_year, gender)
                        VALUES (:enrollment_no, :full_name, :email, :mobile, :course, :study_year, :gender)'
                    );
                    $studentInsert->execute([
                        'enrollment_no' => $enrollmentNumber,
                        'full_name' => $fullName,
                        'email' => $email,
                        'mobile' => $mobile,
                        'course' => $allowedCourses[$course],
                        'study_year' => (int) $year,
                        'gender' => $gender,
                    ]);
                    $studentId = (int) $pdo->lastInsertId();

                    $accountInsert = $pdo->prepare(
                        'INSERT INTO student_accounts (student_id, username, password_hash)
                        VALUES (:student_id, :username, :password_hash)'
                    );
                    $accountInsert->execute([
                        'student_id' => $studentId,
                        'username' => $enrollmentNumber,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ]);

                    $pdo->commit();
                    $submissionRecord = array_merge([
                        'id' => 'STU-' . $studentId,
                        'timestamp' => date('Y-m-d H:i:s'),
                        'formType' => $formType,
                    ], $formData);
                    $success = true;
                }
            } catch (PDOException $error) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($error->getCode() === '23000') {
                    $duplicateCheck->execute([
                        'enrollment_no' => $enrollmentNumber,
                        'email' => $email,
                    ]);
                    foreach ($duplicateCheck->fetchAll() as $existingStudent) {
                        if (strcasecmp($existingStudent['enrollment_no'], $enrollmentNumber) === 0) {
                            $errors['enrollmentNumber'] = 'This enrollment number/username is already registered.';
                        }
                        if (strcasecmp($existingStudent['email'], $email) === 0) {
                            $errors['email'] = 'This email address is already registered.';
                        }
                    }
                }

                if (empty($errors)) {
                    error_log('[StudentHub] Registration database insert failed: ' . $error->getMessage());
                    $errors['storage'] = 'Your registration could not be saved. Please try again later.';
                }
            } catch (RuntimeException $error) {
                $errors['storage'] = $error->getMessage();
            }
        } else {
            $timestamp = date("Y-m-d H:i:s");
            $submissionId = "SUB-" . strtoupper(uniqid());

            $submissionRecord = array_merge([
                'id' => $submissionId,
                'timestamp' => $timestamp,
                'formType' => $formType
            ], $formData);

            try {
                store_submission($submissionRecord);
                $success = true;
            } catch (RuntimeException $error) {
                error_log('[StudentHub] Submission storage failed: ' . $error->getMessage());
                $errors['storage'] = "Your form is valid, but it could not be saved. Please try again later.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Submission Result | Student Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .res-container {
            max-width: 760px;
            margin: 40px auto;
            padding: 24px;
        }
        .res-card {
            background: var(--bg-surface, #ffffff);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
        }
        .res-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .res-badge.success {
            background: #dcfce7;
            color: #166534;
        }
        .res-badge.error {
            background: #fee2e2;
            color: #991b1b;
        }
        .res-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }
        .res-table th, .res-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .res-table th {
            width: 35%;
            color: var(--text-muted, #64748b);
            font-weight: 600;
        }
        .error-list {
            background: #fff1f2;
            border-left: 4px solid #f43f5e;
            padding: 16px 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .error-list li {
            margin-bottom: 6px;
            color: #9f1239;
        }
        .storage-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-family: monospace;
            margin-right: 8px;
            margin-top: 8px;
        }
    </style>
</head>
<body>

    <header class="app-header">
        <a href="home.html" class="brand">
            <span class="brand-icon">S</span>
            <span class="brand-text">Student Hub</span>
        </a>
        <div class="header-nav">
            <a href="home.html">Home</a>
            <a href="signup.html">Registration</a>
            <a href="contact.php">Contact</a>
            <a href="dataexplorer.html">Data Explorer</a>
        </div>
    </header>

    <main class="res-container">

        <?php if ($_SERVER["REQUEST_METHOD"] !== "POST"): ?>
            <div class="res-card">
                <span class="res-badge error">⚠️ Invalid Access</span>
                <h2>Direct Access Not Allowed</h2>
                <p>Please submit the form via POST method to view results.</p>
                <div class="mt-4">
                    <a href="contact.php" class="btn-primary">Go to Contact Form</a>
                    <a href="signup.html" class="btn-secondary ms-2">Go to Registration</a>
                </div>
            </div>

        <?php elseif ($success): ?>
            <div class="res-card">
                <span class="res-badge success">✓ Submission Successful</span>
                <h2 style="color: #166534; margin-bottom: 8px;">Thank You, <?= escape_html($fullName) ?>!</h2>
                <p style="color: #475569;">Your <?= escape_html(strtolower($formType)) ?> has been validated and recorded successfully.</p>

                <h4 class="mt-4" style="font-size: 16px; color: var(--primary);">Submitted Data Details:</h4>
                <table class="res-table">
                    <tr>
                        <th>Submission ID</th>
                        <td><strong><?= escape_html($submissionRecord['id']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Form Type</th>
                        <td><?= escape_html($submissionRecord['formType']) ?></td>
                    </tr>
                    <tr>
                        <th>Full Name</th>
                        <td><?= escape_html($submissionRecord['fullName']) ?></td>
                    </tr>
                    <tr>
                        <th>Email Address</th>
                        <td><?= escape_html($submissionRecord['email']) ?></td>
                    </tr>
                    <tr>
                        <th>Mobile Number</th>
                        <td><?= escape_html($submissionRecord['mobile']) ?></td>
                    </tr>
                    <?php foreach ($formData as $key => $val): ?>
                        <?php if (!in_array($key, ['fullName', 'email', 'mobile'])): ?>
                            <tr>
                                <th><?= escape_html(ucfirst($key)) ?></th>
                                <td><?= escape_html($val) ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <tr>
                        <th>Submission Time</th>
                        <td><?= escape_html($submissionRecord['timestamp']) ?></td>
                    </tr>
                </table>

                <div class="mt-4">
                    <p class="mb-1" style="font-weight: 600; font-size: 13px; color: #475569;">
                        <?= $formType === 'Registration Form' ? 'Database Confirmation:' : 'File Storage Confirmation:' ?>
                    </p>
                    <span class="storage-pill">
                        <?= $formType === 'Registration Form' ? 'MySQL: student_accounts and students' : 'JSON: data/submissions.json' ?>
                    </span>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <a href="contact.php" class="btn-primary">Submit Another Message</a>
                    <a href="home.html" class="btn-secondary ms-2">Return to Dashboard</a>
                </div>
            </div>

        <?php else: ?>
            <div class="res-card">
                <span class="res-badge error">✕ Validation Failed</span>
                <h2 style="color: #991b1b; margin-bottom: 8px;">Form Errors Detected</h2>
                <p style="color: #475569;">Your submission could not be processed due to the following validation errors:</p>

                <div class="error-list">
                    <ul class="mb-0">
                        <?php foreach ($errors as $field => $errMsg): ?>
                            <li><strong><?= escape_html(ucfirst($field)) ?>:</strong> <?= escape_html($errMsg) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="mt-4 pt-2">
                    <p style="font-size: 14px; color: #64748b;">Please go back and correct the highlighted fields before re-submitting.</p>
                    <button onclick="window.history.back()" class="btn-primary">← Go Back & Correct Errors</button>
                    <a href="contact.php" class="btn-secondary ms-2">Contact Form</a>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <footer class="app-footer">
        <p>&copy; 2026 Student Hub Portal. All rights reserved.</p>
    </footer>

</body>
</html>
