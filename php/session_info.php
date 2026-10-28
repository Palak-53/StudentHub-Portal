<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
start_app_session();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$isAuthenticated = isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['last_activity'])
    && time() - (int) $_SESSION['last_activity'] < SESSION_TIMEOUT_SECONDS;

if (!$isAuthenticated || !in_array($_SESSION['role'], ['Admin', 'Student'], true)) {
    echo json_encode(['authenticated' => false]);
    exit;
}

$_SESSION['last_activity'] = time();
$activeLabels = [
    'courses.html' => 'Courses',
    'coursematerials.html' => 'Course materials',
    'attendance.html' => 'Attendance',
    'notice.html' => 'Notifications',
    'dataexplorer.html' => 'Data Explorer',
    'contact.php' => 'Contact',
];
$requestedPage = $_GET['page'] ?? '';
$activeLabel = is_string($requestedPage) ? ($activeLabels[$requestedPage] ?? '') : '';
ob_start();
app_render_portal_header($activeLabel);
$headerHtml = (string) ob_get_clean();

echo json_encode([
    'authenticated' => true,
    'role' => (string) $_SESSION['role'],
    'name' => (string) ($_SESSION['name'] ?? ''),
    'csrfToken' => app_csrf_token(),
    'headerHtml' => $headerHtml,
], JSON_INVALID_UTF8_SUBSTITUTE);
