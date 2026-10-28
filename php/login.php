<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
start_app_session();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$messages = [
    'invalid' => ['error', 'Invalid email or password. Please try again.'],
    'unauthorized_login' => ['error', 'Unauthorized login: this account does not have access to the selected role.'],
    'csrf' => ['error', 'This login form expired. Please try again.'],
    'logout' => ['success', 'You have been logged out successfully.'],
    'login_required' => ['error', 'Please log in to access that page.'],
    'timeout' => ['error', 'Your session expired due to inactivity. Please log in again.'],
];
$reason = $_GET['reason'] ?? '';
$message = $messages[$reason] ?? null;
$csrfToken = app_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Student Hub</title>
    <meta name="description" content="Sign in to Student Hub to access your courses, attendance, and student resources.">
    <link rel="stylesheet" href="css/style.css?v=20261028.2">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <header class="auth-simple-header">
        <a href="home.php" class="brand" aria-label="Student Hub home">
            <span class="brand-icon" aria-hidden="true">S</span>
            <span class="brand-text">Student Hub</span>
        </a>
        <div class="auth-header-actions">
            <a class="auth-header-link" href="contact.php">Need help?</a>
            <button class="theme-toggle" id="themeToggle" title="Toggle theme" type="button" aria-label="Toggle light/dark theme">🌙</button>
        </div>
    </header>

    <main class="login-main">
        <section class="login-card" aria-labelledby="loginTitle">
            <div class="login-heading">
                <span class="login-panel-icon" aria-hidden="true">S</span>
                <p class="login-panel-kicker">STUDENT HUB PORTAL</p>
                <h1 id="loginTitle">Welcome back</h1>
                <p>Sign in to continue to your student account.</p>
            </div>

            <?php if ($message !== null): ?>
                <div class="login-alert login-alert-<?= app_escape($message[0]) ?>" role="<?= $message[0] === 'success' ? 'status' : 'alert' ?>">
                    <span class="login-alert-icon" aria-hidden="true"><?= $message[0] === 'success' ? '✓' : '!' ?></span>
                    <span><?= app_escape($message[1]) ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'service'): ?>
                <div class="login-alert login-alert-error" role="alert">
                    <span class="login-alert-icon" aria-hidden="true">!</span>
                    <span>Login is temporarily unavailable. Check that the database is set up and try again.</span>
                </div>
            <?php endif; ?>

            <form method="post" action="authenticate.php" class="login-form">
                <input type="hidden" name="csrf_token" value="<?= app_escape($csrfToken) ?>">
                <div class="login-field">
                    <label for="role">Sign in as</label>
                    <div class="login-input-wrap">
                        <span class="login-input-icon" aria-hidden="true">♙</span>
                        <select id="role" name="role" autocomplete="organization-title" required>
                            <option value="Student">Student</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                </div>

                <div class="login-field">
                    <label for="email">Email address</label>
                    <div class="login-input-wrap">
                        <span class="login-input-icon" aria-hidden="true">✉</span>
                        <input id="email" name="email" type="email" autocomplete="username" maxlength="254" placeholder="you@example.com" required autofocus>
                    </div>
                </div>

                <div class="login-field">
                    <div class="login-label-row">
                        <label for="password">Password</label>
                        <span>Keep your password private</span>
                    </div>
                    <div class="login-input-wrap">
                        <span class="login-input-icon" aria-hidden="true">▣</span>
                        <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
                        <button class="login-password-toggle" id="togglePassword" type="button" aria-label="Show password" aria-pressed="false">Show</button>
                    </div>
                </div>

                <button class="login-submit" type="submit">
                    <span>Sign in</span><span aria-hidden="true">→</span>
                </button>
            </form>

            <p class="login-demo-note">
                <strong>Demo student account (local testing only):</strong><br>
                Email: <code>student.demo@example.com</code><br>
                Password: <code>Student@123</code><br>
                Change or remove these demo credentials before deployment.
            </p>

            <div class="login-divider"><span>NEW TO STUDENT HUB?</span></div>
            <a class="login-create-account" href="signup.html">Create a student account <span aria-hidden="true">→</span></a>
            <p class="login-security-note"><span aria-hidden="true">●</span> Your account is protected with secure sign-in.</p>
        </section>
    </main>

    <footer class="app-footer login-footer">
        <span>© 2026 Student Hub Portal. All rights reserved.</span>
        <span>Need help? <a href="contact.php">Contact us</a></span>
    </footer>

    <script src="js/script.js?v=20261028.4"></script>
    <script src="js/login.js"></script>
</body>
</html>
