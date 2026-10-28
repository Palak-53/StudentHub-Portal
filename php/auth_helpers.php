<?php

declare(strict_types=1);

const SESSION_TIMEOUT_SECONDS = 1800;

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('~(?:^|/)php/([^/]+)\.php$~i', $scriptName, $matches) === 1) {
        $rootPage = strtolower($matches[1]) === 'api_lab' ? 'index.php' : $matches[1] . '.php';
        header('Location: ../' . $rootPage);
        exit;
    }

    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

function app_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function app_audit_log(
    mysqli $conn,
    string $action,
    string $entityType,
    ?int $entityId = null,
    array $details = []
): void {
    try {
        $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Could not encode the audit log details.', 0, $exception);
    }

    $actorId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $actorName = isset($_SESSION['name']) ? (string) $_SESSION['name'] : 'Unknown user';
    $actorRole = isset($_SESSION['role']) ? (string) $_SESSION['role'] : 'Unknown';
    $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $statement = $conn->prepare(
        'INSERT INTO audit_logs
            (actor_student_id, actor_name, actor_role, action, entity_type, entity_id, details_json, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $statement->bind_param(
        'issssiss',
        $actorId,
        $actorName,
        $actorRole,
        $action,
        $entityType,
        $entityId,
        $detailsJson,
        $ipAddress
    );
    $statement->execute();
    $statement->close();
}

function app_render_portal_header(string $activeLabel = ''): void
{
    $role = (string) ($_SESSION['role'] ?? '');
    $isAdmin = $role === 'Admin';
    $name = (string) ($_SESSION['name'] ?? 'Student Hub');
    $profileHref = $isAdmin ? 'dashboard.php' : 'profile.php';
    $links = $isAdmin
        ? [
            ['Admin dashboard', 'dashboard.php'],
            ['Student records', 'student_records.php'],
            ['Events', 'event_management.php'],
            ['Records API', 'index.php'],
        ]
        : [
            ['Student home', 'home.php'],
            ['My dashboard', 'dashboard.php'],
            ['Courses', 'courses.html'],
            ['Course materials', 'courseMaterials.html'],
            ['Attendance', 'attendance.html'],
            ['Notifications', 'notice.html'],
            ['Data Explorer', 'dataexplorer.html'],
            ['My profile', 'profile.php'],
        ];

    $renderLink = static function (string $label, string $href) use ($activeLabel): string {
        $active = $activeLabel === $label ? ' class="active" aria-current="page"' : '';

        return '<a href="' . app_escape($href) . '"' . $active . '>' . app_escape($label) . '</a>';
    };
    ?>
    <header class="app-header portal-header <?= $isAdmin ? 'portal-admin-header' : 'portal-student-header' ?>">
        <a href="home.php" class="brand" aria-label="Student Hub home">
            <span class="brand-icon" aria-hidden="true">S</span>
            <span class="brand-text">Student Hub</span>
        </a>
        <nav class="header-nav" id="desktopNav" aria-label="Main navigation">
            <?php foreach ($links as [$label, $href]): ?>
                <?= $renderLink($label, $href) ?>
            <?php endforeach; ?>
            <a href="<?= app_escape($profileHref) ?>" class="nav-profile-box<?= $activeLabel === 'My profile' ? ' active' : '' ?>" title="View profile">
                <span class="nav-profile-avatar" aria-hidden="true">👤</span>
                <span class="nav-profile-name"><?= app_escape($name) ?></span>
                <span class="role-nav-badge"><?= app_escape($role) ?></span>
            </a>
            <form method="post" action="logout.php" class="portal-logout-form">
                <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                <button type="submit" class="portal-logout-button">Log out</button>
            </form>
        </nav>
        <div class="portal-header-actions">
            <button class="theme-toggle" id="themeToggle" type="button" title="Toggle theme" aria-label="Toggle light/dark theme">🌙</button>
            <button class="hamburger" id="hamburgerBtn" type="button" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="mobileNav">
                <span class="ham-bar"></span><span class="ham-bar"></span><span class="ham-bar"></span>
            </button>
        </div>
    </header>
    <nav class="mobile-nav" id="mobileNav" aria-hidden="true" aria-label="Mobile navigation">
        <div class="mobile-nav-account">
            <span class="nav-profile-avatar" aria-hidden="true">👤</span>
            <span>
                <strong><?= app_escape($name) ?></strong>
                <small><?= app_escape($role) ?> portal</small>
            </span>
        </div>
        <?php foreach ($links as [$label, $href]): ?>
            <?= $renderLink($label, $href) ?>
        <?php endforeach; ?>
        <form method="post" action="logout.php" class="portal-mobile-logout">
            <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
            <button type="submit" class="portal-logout-button">Log out</button>
        </form>
    </nav>
    <div class="mobile-nav-overlay" id="navOverlay"></div>
    <?php
}

function require_authenticated_user(): void
{
    start_app_session();

    if (!isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['last_activity'])) {
        header('Location: login.php?reason=login_required');
        exit;
    }

    if (time() - (int) $_SESSION['last_activity'] >= SESSION_TIMEOUT_SECONDS) {
        header('Location: timeout.php');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

function require_user_role(string $requiredRole): void
{
    require_authenticated_user();

    if ($_SESSION['role'] === $requiredRole) {
        return;
    }

    http_response_code(403);
    $currentRole = app_escape((string) $_SESSION['role']);
    $requiredRole = app_escape($requiredRole);
    $dashboard = $_SESSION['role'] === 'Admin' ? 'dashboard.php' : 'student_dashboard.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Unauthorized Access | Student Hub</title>
        <link rel="stylesheet" href="css/style.css?v=20261028.2">
        <link rel="stylesheet" href="css/interactive.css?v=20261028.2">
        <link rel="stylesheet" href="css/role-dashboard.css?v=20261028.2">
    </head>
    <body>
        <?php app_render_portal_header(); ?>
        <main class="app-container role-main">
            <section class="role-content-card access-denied-card">
                <span class="access-denied-icon" aria-hidden="true">403</span>
                <p class="role-eyebrow">ACCESS RESTRICTED</p>
                <h1>Unauthorized Access</h1>
                <p>This page is for <?= $requiredRole ?> users. You are signed in as <?= $currentRole ?>.</p>
                <p>Your current role does not have permission to view this page.</p>
                <a class="auth-button" href="<?= app_escape($dashboard) ?>">Return to your dashboard</a>
                <form method="post" action="logout.php" class="auth-logout-form">
                    <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                    <button class="auth-button auth-button-secondary" type="submit">Log out</button>
                </form>
            </section>
        </main>
        <footer class="app-footer"><p>© 2026 Student Hub Portal. Access is restricted to authorized roles.</p></footer>
        <script src="js/interactive.js?v=20261028.2"></script>
        <script src="js/script.js?v=20261028.4"></script>
    </body>
    </html>
    <?php
    exit;
}
