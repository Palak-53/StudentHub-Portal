<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
require_authenticated_user();

$role = (string) $_SESSION['role'];
if (!in_array($role, ['Admin', 'Student'], true)) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Access denied | Student Hub</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <main class="container py-5">
            <div class="alert alert-danger" role="alert">
                <h1 class="h4">403 — Access denied</h1>
                <p class="mb-0">Your account role is not permitted to access this dashboard.</p>
            </div>
            <a class="btn btn-primary" href="home.php">Return to Student Hub</a>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$dashboardError = null;
$stats = [];
$registrations = [];
$auditLogs = [];

try {
    require_once __DIR__ . '/db.php';

    if ($role === 'Admin') {
        $statsResult = $conn->query(
            "SELECT
                (SELECT COUNT(*) FROM student_accounts sa
                 INNER JOIN students s ON s.student_id = sa.student_id
                 WHERE s.status = 'active') AS user_count,
                (SELECT COUNT(*) FROM students WHERE status = 'active') AS student_count,
                (SELECT COUNT(*) FROM events) AS event_count,
                (SELECT COUNT(*) FROM registrations WHERE status = 'registered') AS registration_count"
        );
        $statsRow = $statsResult->fetch_assoc();
        $stats = [
            ['label' => 'Active users', 'value' => (int) $statsRow['user_count'], 'icon' => 'bi-people', 'tone' => 'primary'],
            ['label' => 'Active students', 'value' => (int) $statsRow['student_count'], 'icon' => 'bi-mortarboard', 'tone' => 'success'],
            ['label' => 'Campus events', 'value' => (int) $statsRow['event_count'], 'icon' => 'bi-calendar-event', 'tone' => 'warning'],
            ['label' => 'Active registrations', 'value' => (int) $statsRow['registration_count'], 'icon' => 'bi-card-checklist', 'tone' => 'info'],
        ];

        $recentStatement = $conn->prepare(
            "SELECT r.registration_id, r.registered_at, r.status,
                    s.full_name, s.enrollment_no, e.title AS event_title, e.event_date
            FROM registrations r
            INNER JOIN students s ON s.student_id = r.student_id
            INNER JOIN events e ON e.event_id = r.event_id
            ORDER BY r.registered_at DESC, r.registration_id DESC
            LIMIT 10"
        );
        $recentStatement->execute();
        $registrations = $recentStatement->get_result()->fetch_all(MYSQLI_ASSOC);
        $recentStatement->close();

        $auditStatement = $conn->prepare(
            'SELECT actor_name, actor_role, action, entity_type, entity_id, created_at
            FROM audit_logs
            ORDER BY audit_id DESC
            LIMIT 10'
        );
        $auditStatement->execute();
        $auditLogs = $auditStatement->get_result()->fetch_all(MYSQLI_ASSOC);
        $auditStatement->close();
    } else {
        $studentId = (int) $_SESSION['user_id'];
        $studentStats = $conn->prepare(
            "SELECT
                (SELECT COUNT(*) FROM registrations WHERE student_id = ? AND status = 'registered') AS registration_count,
                (SELECT COUNT(*) FROM events WHERE event_date >= CURRENT_DATE()) AS upcoming_event_count"
        );
        $studentStats->bind_param('i', $studentId);
        $studentStats->execute();
        $studentStatsRow = $studentStats->get_result()->fetch_assoc();
        $studentStats->close();
        $stats = [
            ['label' => 'My registrations', 'value' => (int) $studentStatsRow['registration_count'], 'icon' => 'bi-card-checklist', 'tone' => 'primary'],
            ['label' => 'Upcoming events', 'value' => (int) $studentStatsRow['upcoming_event_count'], 'icon' => 'bi-calendar-event', 'tone' => 'success'],
        ];

        $registrationStatement = $conn->prepare(
            "SELECT r.registration_id, r.registered_at, r.status, e.title AS event_title, e.event_date
            FROM registrations r
            INNER JOIN events e ON e.event_id = r.event_id
            WHERE r.student_id = ?
            ORDER BY r.registered_at DESC
            LIMIT 10"
        );
        $registrationStatement->bind_param('i', $studentId);
        $registrationStatement->execute();
        $registrations = $registrationStatement->get_result()->fetch_all(MYSQLI_ASSOC);
        $registrationStatement->close();
    }

    $conn->close();
} catch (mysqli_sql_exception $exception) {
    error_log('[StudentHub] Role dashboard query failed: ' . $exception->getMessage());
    http_response_code(500);
    $dashboardError = 'Dashboard data could not be loaded. Confirm the database connection and apply the audit log migration.';
} catch (RuntimeException $exception) {
    http_response_code(500);
    $dashboardError = $exception->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Role-based Student Hub dashboard">
    <title><?= $role === 'Admin' ? 'Admin dashboard' : 'Student dashboard' ?> | Student Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=20261028.2">
    <link rel="stylesheet" href="css/interactive.css?v=20261028.2">
    <link rel="stylesheet" href="css/role-dashboard.css?v=20261028.2">
    <style>
        body { min-height: 100vh; color: var(--text-main); }
        .dashboard-content { max-width: 1240px; }
        .stat-card { min-height: 132px; border: 1px solid #e4eaf2; }
        .stat-icon { width: 46px; height: 46px; display: inline-grid; place-items: center; border-radius: 13px; font-size: 21px; }
        .stat-value { font-size: 30px; font-weight: 800; line-height: 1; }
        .table > :not(caption) > * > * { padding: .8rem .9rem; }
        .role-pill { font-size: .73rem; }
    </style>
</head>
<body class="body-dashboard">
    <?php app_render_portal_header($role === 'Admin' ? 'Admin dashboard' : 'My dashboard'); ?>

    <main class="container dashboard-content app-container py-4 py-lg-5">
        <section class="welcome-card rounded-4 p-4 p-lg-5 mb-4">
            <p class="text-primary fw-bold small text-uppercase mb-2"><?= $role === 'Admin' ? 'Administration' : 'Student account' ?></p>
            <h1 class="display-6 fw-bold mb-2"><?= $role === 'Admin' ? 'Admin dashboard' : 'Welcome to your dashboard' ?></h1>
            <p class="text-secondary mb-0"><?= $role === 'Admin' ? 'Live portal statistics, recent registrations, and an audit trail of management actions.' : 'Your personal event registrations and upcoming campus activity.' ?></p>
        </section>

        <?php if ($dashboardError !== null): ?>
            <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle me-2"></i><?= app_escape($dashboardError) ?></div>
        <?php else: ?>
            <section class="row g-3 mb-4" aria-label="Dashboard statistics">
                <?php foreach ($stats as $stat): ?>
                    <div class="col-12 col-sm-6 <?= $role === 'Admin' ? 'col-xl-3' : 'col-xl-6' ?>">
                        <article class="card stat-card shadow-sm rounded-4">
                            <div class="card-body d-flex align-items-center gap-3">
                                <span class="stat-icon bg-<?= app_escape($stat['tone']) ?>-subtle text-<?= app_escape($stat['tone']) ?>"><i class="bi <?= app_escape($stat['icon']) ?>"></i></span>
                                <div><p class="text-secondary small mb-2"><?= app_escape($stat['label']) ?></p><div class="stat-value"><?= (int) $stat['value'] ?></div></div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="card shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="h5 fw-bold mb-1"><?= $role === 'Admin' ? 'Latest registrations' : 'My recent registrations' ?></h2>
                        <p class="small text-secondary mb-0"><?= $role === 'Admin' ? 'Most recent event sign-ups across the portal.' : 'Events you have signed up for.' ?></p>
                    </div>
                    <span class="badge text-bg-light"><?= count($registrations) ?> shown</span>
                </div>
                <div class="card-body px-4">
                    <?php if ($registrations === []): ?>
                        <div class="alert alert-light border mb-0">There are no registrations to show yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light"><tr>
                                    <?php if ($role === 'Admin'): ?><th>Student</th><th>Enrollment</th><?php endif; ?>
                                    <th>Event</th><th>Event date</th><th>Registered</th><th>Status</th>
                                </tr></thead>
                                <tbody>
                                    <?php foreach ($registrations as $registration): ?>
                                        <tr>
                                            <?php if ($role === 'Admin'): ?>
                                                <td class="fw-semibold"><?= app_escape($registration['full_name']) ?></td>
                                                <td><?= app_escape($registration['enrollment_no']) ?></td>
                                            <?php endif; ?>
                                            <td><?= app_escape($registration['event_title']) ?></td>
                                            <td><?= app_escape(date('M j, Y', strtotime($registration['event_date']))) ?></td>
                                            <td><?= app_escape(date('M j, Y g:i A', strtotime($registration['registered_at']))) ?></td>
                                            <td><span class="badge text-bg-<?= $registration['status'] === 'registered' ? 'success' : 'secondary' ?>"><?= app_escape(ucfirst($registration['status'])) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($role === 'Admin'): ?>
                <section class="card shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h2 class="h5 fw-bold mb-1">Recent audit activity</h2>
                        <p class="small text-secondary mb-0">Successful student and event changes are recorded with the acting account.</p>
                    </div>
                    <div class="card-body px-4">
                        <?php if ($auditLogs === []): ?>
                            <div class="alert alert-light border mb-0">No administrative actions have been logged yet. Create or update a student or event to record activity.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light"><tr><th>When</th><th>Actor</th><th>Role</th><th>Action</th><th>Record</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($auditLogs as $audit): ?>
                                            <tr>
                                                <td><?= app_escape(date('M j, Y g:i A', strtotime($audit['created_at']))) ?></td>
                                                <td><?= app_escape($audit['actor_name']) ?></td>
                                                <td><span class="badge text-bg-primary"><?= app_escape($audit['actor_role']) ?></span></td>
                                                <td><code><?= app_escape($audit['action']) ?></code></td>
                                                <td><?= app_escape(ucfirst($audit['entity_type'])) ?> #<?= (int) $audit['entity_id'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="small text-secondary mt-3 mb-0">For the complete audit log, open phpMyAdmin and browse the <code>audit_logs</code> table.</p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/interactive.js?v=20261028.2"></script>
    <script src="js/script.js?v=20261028.4"></script>
</body>
</html>
