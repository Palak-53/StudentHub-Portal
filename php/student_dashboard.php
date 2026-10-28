<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
require_user_role('Student');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Student Hub</title>
    <link rel="stylesheet" href="css/style.css?v=20261028.2">
    <link rel="stylesheet" href="css/interactive.css?v=20261028.2">
    <link rel="stylesheet" href="css/role-dashboard.css?v=20261028.2">
</head>
<body>
    <?php app_render_portal_header('My dashboard'); ?>
    <main class="app-container role-main">
        <div class="role-welcome">
            <div>
                <p class="role-eyebrow">STUDENT ACCOUNT</p>
                <h1>Student dashboard</h1>
                <p>Welcome, <?= app_escape((string) $_SESSION['name']) ?>. Your Student Hub home is ready.</p>
            </div>
            <span class="role-welcome-icon" aria-hidden="true">♙</span>
        </div>
        <section class="role-content-card student-dashboard-summary">
            <span class="auth-role-badge">Student access</span>
            <h2>Your student portal</h2>
            <p>This area is available to student accounts. Use your personalized home page to access your courses, attendance, notices, and campus resources.</p>
            <a class="auth-button" href="home.php">Go to my Student Hub home</a>
        </section>
    </main>
    <footer class="app-footer"><p>© 2026 Student Hub Portal. All rights reserved.</p></footer>
    <script src="js/interactive.js?v=20261028.2"></script>
    <script src="js/script.js?v=20261028.4"></script>
</body>
</html>
