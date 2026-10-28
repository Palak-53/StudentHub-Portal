<?php
/**
 * =========================================================
 * STUDENT HUB — CONTACT FORM (PHP POST)
 * contact.php
 * =========================================================
 */

require_once __DIR__ . '/submission_storage.php';

$errors = [];
$success = false;
$fullName = '';
$email = '';
$mobile = '';
$category = '';
$subject = '';
$message = '';
$terms = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName = sanitize_input($_POST['fullName'] ?? '');
    $email    = sanitize_input($_POST['email'] ?? '');
    $mobile   = sanitize_input($_POST['mobile'] ?? '');
    $category = sanitize_input($_POST['category'] ?? '');
    $subject  = sanitize_input($_POST['subject'] ?? '');
    $message  = sanitize_input($_POST['message'] ?? '');
    $terms    = isset($_POST['terms']);

    // Validation
    if (empty($fullName)) {
        $errors['fullName'] = "Full Name is required.";
    } elseif (!preg_match("/^[\p{L}][\p{L}\s'-]{1,59}$/u", $fullName)) {
        $errors['fullName'] = "Full Name must be 2–60 characters and contain only letters, spaces, apostrophes, or hyphens.";
    }

    if (empty($email)) {
        $errors['email'] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email address format.";
    }

    if (empty($mobile)) {
        $errors['mobile'] = "Mobile number is required.";
    } elseif (!preg_match("/^[6-9]\d{9}$/", $mobile)) {
        $errors['mobile'] = "Must be a valid 10-digit Indian phone number.";
    }

    $allowedCategories = ['General Query', 'Admissions', 'Attendance & Leave', 'Fees & Accounts', 'Technical Support'];
    if (!in_array($category, $allowedCategories, true)) {
        $errors['category'] = "Please select a valid query category.";
    }

    if (empty($subject)) {
        $errors['subject'] = "Subject is required.";
    } elseif (strlen($subject) < 5) {
        $errors['subject'] = "Subject must be at least 5 characters.";
    }

    if (empty($message)) {
        $errors['message'] = "Message is required.";
    } elseif (strlen($message) < 10) {
        $errors['message'] = "Message must be at least 10 characters long.";
    }

    if (!$terms) {
        $errors['terms'] = "You must agree to the Terms and Conditions.";
    }

    // Save only validated data, and report success only after the write completes.
    if (empty($errors)) {
        $timestamp = date("Y-m-d H:i:s");
        $id = "CNT-" . strtoupper(uniqid());

        $record = [
            'id' => $id,
            'timestamp' => $timestamp,
            'formType' => 'Contact Inquiry',
            'fullName' => $fullName,
            'email' => $email,
            'mobile' => $mobile,
            'category' => $category,
            'subject' => $subject,
            'message' => $message
        ];

        try {
            store_submission($record);
            $success = true;
        } catch (RuntimeException $error) {
            error_log('[StudentHub] Contact storage failed: ' . $error->getMessage());
            $errors['storage'] = "Your message is valid, but it could not be saved. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Student Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=20261028.2">
    <link rel="stylesheet" href="css/interactive.css?v=20261028.2">
    <link rel="stylesheet" href="css/role-dashboard.css?v=20261028.2">
    <style>
        .contact-card {
            max-width: 720px;
            margin: 0 auto 40px auto;
            background: var(--bg-surface, #ffffff);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-heading, #1e293b);
            margin-bottom: 6px;
            display: block;
        }
        .form-label .req {
            color: #ef4444;
        }
        .form-control-custom {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: var(--bg-body, #ffffff);
            color: var(--text-color, #1e293b);
        }
        .form-control-custom:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }
        .is-invalid {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }
        .invalid-feedback-text {
            color: #dc2626;
            font-size: 12px;
            margin-top: 4px;
            font-weight: 500;
        }
        .alert-box {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        .alert-box.success {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #14532d;
        }
        .alert-box.error {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #7f1d1d;
        }
        .contact-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        .info-item {
            background: var(--bg-body, #f8fafc);
            border: 1px solid var(--border-color, #e2e8f0);
            padding: 16px;
            border-radius: 10px;
            text-align: center;
        }
        .info-item-icon {
            font-size: 24px;
            margin-bottom: 6px;
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
            <a href="aboutus.html">About Us</a>
            <a href="courses.html">Courses</a>
            <a href="attendance.html">Attendance</a>
            <a href="notice.html">Notifications</a>
            <a href="contact.php" class="active">Contact</a>
            <a href="dataexplorer.html">Data Explorer</a>
            <button class="theme-toggle" id="themeToggle" title="Toggle theme">🌙</button>
        </div>
    </header>

    <main class="app-container">

        <div class="breadcrumb">
            <a href="home.html">Home</a> / <span>Contact Us</span>
        </div>

        <h1 class="page-title">Contact & Inquiry Form</h1>

        <!-- Contact Cards Grid -->
        <div class="contact-info-grid">
            <div class="info-item">
                <div class="info-item-icon">📧</div>
                <strong>Email Us</strong>
                <p class="mb-0 text-muted" style="font-size: 13px;">studenthub@gmail.com</p>
            </div>
            <div class="info-item">
                <div class="info-item-icon">📞</div>
                <strong>Call Us</strong>
                <p class="mb-0 text-muted" style="font-size: 13px;">+91 9876543210</p>
            </div>
            <div class="info-item">
                <div class="info-item-icon">📍</div>
                <strong>Visit Us</strong>
                <p class="mb-0 text-muted" style="font-size: 13px;">CHARUSAT University, Gujarat</p>
            </div>
        </div>

        <!-- Contact Form Card -->
        <div class="contact-card">

            <?php if ($success): ?>
                <div class="alert-box success" role="alert">
                    <h4 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 700;">🎉 Submission Successful!</h4>
                    <p style="margin: 0 0 10px 0;">Thank you, <strong><?= escape_html($fullName) ?></strong>. Your inquiry has been processed and saved to <code>data/submissions.json</code> via PHP POST method.</p>
                    <a href="contact.php" class="btn btn-sm btn-outline-success">Send Another Message</a>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert-box error" role="alert">
                    <strong style="font-size: 15px;">⚠️ Please fix the following errors:</strong>
                    <ul style="margin: 8px 0 0 18px; padding: 0;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= escape_html($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- PHP POST Form -->
            <form action="contact.php" method="POST" id="contactForm" novalidate>

                <input type="hidden" name="form_type" value="Contact Form">

                <div class="row">
                    <!-- Full Name -->
                    <div class="col-md-6 form-group">
                        <label for="fullName" class="form-label">Full Name <span class="req">*</span></label>
                        <input
                            type="text"
                            id="fullName"
                            name="fullName"
                            class="form-control-custom <?= isset($errors['fullName']) ? 'is-invalid' : '' ?>"
                            placeholder="e.g. Palak Kansagara"
                            value="<?= $success ? '' : escape_html($fullName) ?>"
                            required
                        >
                        <?php if (isset($errors['fullName'])): ?>
                            <div class="invalid-feedback-text">⚠️ <?= escape_html($errors['fullName']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Email Address -->
                    <div class="col-md-6 form-group">
                        <label for="email" class="form-label">Email Address <span class="req">*</span></label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control-custom <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                            placeholder="e.g. student@college.edu"
                            value="<?= $success ? '' : escape_html($email) ?>"
                            required
                        >
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback-text">⚠️ <?= escape_html($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row">
                    <!-- Mobile Number -->
                    <div class="col-md-6 form-group">
                        <label for="mobile" class="form-label">Mobile Number <span class="req">*</span></label>
                        <input
                            type="tel"
                            id="mobile"
                            name="mobile"
                            class="form-control-custom <?= isset($errors['mobile']) ? 'is-invalid' : '' ?>"
                            placeholder="10-digit mobile number"
                            value="<?= $success ? '' : escape_html($mobile) ?>"
                            required
                        >
                        <?php if (isset($errors['mobile'])): ?>
                            <div class="invalid-feedback-text">⚠️ <?= escape_html($errors['mobile']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Query Category -->
                    <div class="col-md-6 form-group">
                        <label for="category" class="form-label">Category <span class="req">*</span></label>
                        <select
                            id="category"
                            name="category"
                            class="form-control-custom <?= isset($errors['category']) ? 'is-invalid' : '' ?>"
                            required
                        >
                            <option value="">Select Category</option>
                            <option value="General Query" <?= ($category === 'General Query') ? 'selected' : '' ?>>General Query</option>
                            <option value="Admissions" <?= ($category === 'Admissions') ? 'selected' : '' ?>>Admissions</option>
                            <option value="Attendance &amp; Leave" <?= ($category === 'Attendance & Leave') ? 'selected' : '' ?>>Attendance &amp; Leave</option>
                            <option value="Fees &amp; Accounts" <?= ($category === 'Fees & Accounts') ? 'selected' : '' ?>>Fees &amp; Accounts</option>
                            <option value="Technical Support" <?= ($category === 'Technical Support') ? 'selected' : '' ?>>Technical Support</option>
                        </select>
                        <?php if (isset($errors['category'])): ?>
                            <div class="invalid-feedback-text">⚠️ <?= escape_html($errors['category']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Subject -->
                <div class="form-group">
                    <label for="subject" class="form-label">Subject <span class="req">*</span></label>
                    <input
                        type="text"
                        id="subject"
                        name="subject"
                        class="form-control-custom <?= isset($errors['subject']) ? 'is-invalid' : '' ?>"
                        placeholder="Brief summary of your inquiry"
                        value="<?= $success ? '' : escape_html($subject) ?>"
                        required
                    >
                    <?php if (isset($errors['subject'])): ?>
                        <div class="invalid-feedback-text">⚠️ <?= escape_html($errors['subject']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Message -->
                <div class="form-group">
                    <label for="message" class="form-label">Message Details <span class="req">*</span></label>
                    <textarea
                        id="message"
                        name="message"
                        rows="4"
                        class="form-control-custom <?= isset($errors['message']) ? 'is-invalid' : '' ?>"
                        placeholder="Describe your query in detail..."
                        required
                    ><?= $success ? '' : escape_html($message) ?></textarea>
                    <?php if (isset($errors['message'])): ?>
                        <div class="invalid-feedback-text">⚠️ <?= escape_html($errors['message']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Terms -->
                <div class="form-group mb-4">
                    <div class="form-check">
                        <input
                            type="checkbox"
                            id="terms"
                            name="terms"
                            class="form-check-input <?= isset($errors['terms']) ? 'is-invalid' : '' ?>"
                            <?= ($terms && !$success) ? 'checked' : '' ?>
                            required
                        >
                        <label for="terms" class="form-check-label" style="font-size: 13px;">
                            I confirm that the information provided is accurate and agree to the portal privacy policy. <span class="req">*</span>
                        </label>
                    </div>
                    <?php if (isset($errors['terms'])): ?>
                        <div class="invalid-feedback-text">⚠️ <?= escape_html($errors['terms']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="submit" class="btn-primary w-100 py-2" style="font-size: 15px; font-weight: 600;">
                        ✉️ Submit Inquiry (POST Method)
                    </button>
                </div>

            </form>

        </div>

    </main>

    <footer class="app-footer">
        <p>&copy; 2026 Student Hub Portal. All rights reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js?v=20261028.4"></script>
    <script src="js/interactive.js?v=20261028.2"></script>
</body>
</html>
