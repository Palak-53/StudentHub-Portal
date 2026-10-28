<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
require_authenticated_user();

if ($_SESSION['role'] === 'Admin') {
    header('Location: dashboard.php');
    exit;
}

$name = app_escape((string) $_SESSION['name']);
$enrollmentNumber = app_escape((string) ($_SESSION['enrollment_no'] ?? ''));
$email = app_escape((string) $_SESSION['email']);
$course = app_escape((string) ($_SESSION['course'] ?? ''));
$studyYear = (int) ($_SESSION['study_year'] ?? 0);
$yearLabel = $studyYear > 0 ? $studyYear . ($studyYear === 1 ? 'st' : ($studyYear === 2 ? 'nd' : ($studyYear === 3 ? 'rd' : 'th'))) . ' Year' : 'Not set';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Student Hub</title>
    <link rel="stylesheet" href="css/style.css?v=20261028.2">
    <link rel="stylesheet" href="css/interactive.css?v=20261028.2">
    <link rel="stylesheet" href="css/role-dashboard.css?v=20261028.2">
</head>
<body>
    <?php app_render_portal_header('My profile'); ?>
    <main class="app-container role-main">
        <div class="role-welcome">
            <div><p class="role-eyebrow">STUDENT ACCOUNT</p><h1>My profile</h1><p>Your details from your registered Student Hub account.</p></div>
            <span class="role-welcome-icon" aria-hidden="true">♙</span>
        </div>
        <section class="role-content-card profile-details">
            <div class="profile-details-avatar"><?= app_escape(strtoupper(substr((string) $_SESSION['name'], 0, 1))) ?></div>
            <div class="role-table-wrap">
                <table class="role-table">
                    <tbody>
                        <tr><th>Name</th><td><?= $name ?></td></tr>
                        <tr><th>Enrollment number</th><td><?= $enrollmentNumber ?></td></tr>
                        <tr><th>Email</th><td><?= $email ?></td></tr>
                        <tr><th>Course</th><td><?= $course ?></td></tr>
                        <tr><th>Study year</th><td><?= app_escape($yearLabel) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <footer class="app-footer"><p>© 2026 Student Hub Portal. All rights reserved.</p></footer>
    <script src="js/interactive.js?v=20261028.2"></script>
    <script src="js/script.js?v=20261028.4"></script>
</body>
</html>
