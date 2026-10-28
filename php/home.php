<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
start_app_session();

$isSignedIn = isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['last_activity']);
if ($isSignedIn) {
    require_authenticated_user();

    if ($_SESSION['role'] === 'Admin') {
        header('Location: dashboard.php');
        exit;
    }
}

$homePage = file_get_contents(__DIR__ . '/../home.html');
if ($homePage === false) {
    throw new RuntimeException('The Student Hub home page could not be loaded.');
}

$homePage = str_replace('href="home.html"', 'href="home.php"', $homePage);

if ($isSignedIn) {
    $studentName = app_escape((string) $_SESSION['name']);
    $studentShortName = app_escape(explode(' ', trim((string) $_SESSION['name']))[0]);
    $enrollmentNumber = app_escape((string) ($_SESSION['enrollment_no'] ?? ''));
    $courseName = app_escape((string) ($_SESSION['course'] ?? ''));
    $studyYear = (int) ($_SESSION['study_year'] ?? 0);
    $semester = $studyYear > 0 ? app_escape('Year ' . $studyYear) : 'Student';
    $homePage = str_replace('PALAK KANSAGARA', $studentName, $homePage);
    $homePage = str_replace('25CS030', $enrollmentNumber, $homePage);
    $homePage = str_replace('B.Tech CSE', $courseName, $homePage);
    $homePage = str_replace('3rd Semester', $semester, $homePage);
    $homePage = str_replace(
        'Welcome to Student Hub Portal',
        'Welcome, ' . $studentShortName,
        $homePage
    );
    ob_start();
    app_render_portal_header('Student home');
    $portalHeader = (string) ob_get_clean();
    $homePage = preg_replace(
        '/<header class="app-header">.*?<\/header>\s*<!-- Mobile Nav Drawer -->.*?<div class="mobile-nav-overlay" id="navOverlay"><\/div>/s',
        $portalHeader,
        $homePage,
        1,
        $headerReplaced
    );
    if ($headerReplaced !== 1 || !is_string($homePage)) {
        throw new RuntimeException('The Student Hub navigation could not be updated.');
    }
} else {
    $homePage = preg_replace(
        '/<a href="profile\.html" class="profile-banner"[^>]*>.*?<\/a>/s',
        '<a href="login.php" class="profile-banner" title="Sign in to view your student account">'
            . '<div class="profile-avatar">👤</div>'
            . '<div class="student-name">Sign in to view your student account</div>'
            . '<div class="profile-link-badge">Sign in &rarr;</div>'
            . '</a>',
        $homePage,
        1,
        $guestBannerReplaced
    );
    if ($guestBannerReplaced !== 1 || !is_string($homePage)) {
        throw new RuntimeException('The Student Hub guest profile banner could not be updated.');
    }

    $guestLinks = '<a href="login.php">Login</a><a href="signup.html">Register</a>';
    $homePage = preg_replace(
        '/<a href="profile\.html" class="nav-profile-box"[^>]*>.*?<\/a>/s',
        $guestLinks,
        $homePage,
        1,
        $guestProfileLinksReplaced
    );
    if ($guestProfileLinksReplaced !== 1) {
        throw new RuntimeException('The Student Hub guest navigation could not be updated.');
    }
    $homePage = preg_replace(
        '/<a href="profile\.html">[^<]*<\/a>/',
        '<a href="login.php">Login</a><a href="signup.html">Register</a>',
        $homePage,
        1,
        $guestMobileLinksReplaced
    );
    if ($guestMobileLinksReplaced !== 1) {
        throw new RuntimeException('The Student Hub mobile guest navigation could not be updated.');
    }
    $homePage = str_replace(
        '<a href="home.php" class="active">Home</a>',
        '<a href="home.php" class="active">Home</a><a href="login.php">Login</a>',
        $homePage
    );
}

header('Content-Type: text/html; charset=UTF-8');
echo $homePage;
