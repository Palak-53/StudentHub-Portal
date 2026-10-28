<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
require_user_role('Admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Manage student records with the Student Hub JSON API.">
    <title>Student Records API | Student Hub</title>
    <link rel="stylesheet" href="css/style.css?v=20261028.2">
    <link rel="stylesheet" href="css/interactive.css?v=20261028.2">
    <link rel="stylesheet" href="css/role-dashboard.css?v=20261028.2">
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            color: #172033;
            background: #f3f6fb;
            font-synthesis: none;
        }

        * { box-sizing: border-box; }
        [hidden] { display: none !important; }

        body { margin: 0; min-height: 100vh; }

        main { width: min(1120px, calc(100% - 32px)); margin: 34px auto 60px; }

        .intro, .panel {
            padding: 24px;
            background: #fff;
            border: 1px solid #e1e7f0;
            border-radius: 14px;
            box-shadow: 0 8px 26px rgb(23 32 51 / 5%);
        }

        .intro { margin-bottom: 20px; }
        .eyebrow { margin: 0 0 8px; color: #1d4ed8; font-size: 11px; font-weight: 800; letter-spacing: 1.4px; }
        h1 { margin: 0; font-size: clamp(26px, 4vw, 36px); }
        h2 { margin: 0; font-size: 19px; }
        .intro p:last-child, .panel-heading p { color: #667085; font-size: 14px; line-height: 1.6; }
        .panel { margin-bottom: 20px; }
        .panel-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 18px; }
        .panel-heading p { margin: 5px 0 0; }
        form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 15px; }
        label { display: grid; gap: 7px; color: #344054; font-size: 12px; font-weight: 700; }
        input { width: 100%; min-height: 42px; padding: 10px 12px; border: 1px solid #d0d5dd; border-radius: 8px; color: #172033; background: #fff; font: inherit; }
        input:focus { outline: 3px solid rgb(37 99 235 / 17%); border-color: #2563eb; }
        .form-actions, .filters, .actions { display: flex; align-items: center; gap: 9px; }
        .form-actions { grid-column: 1 / -1; }
        button, .button {
            display: inline-flex;
            min-height: 39px;
            align-items: center;
            justify-content: center;
            padding: 8px 13px;
            border: 1px solid #1d4ed8;
            border-radius: 8px;
            color: #fff;
            background: #1d4ed8;
            font: inherit;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        button:disabled { opacity: .6; cursor: wait; }
        .secondary { color: #344054; background: #fff; border-color: #d0d5dd; }
        .danger { color: #b42318; background: #fff; border-color: #fecdca; }
        .filters input { width: min(320px, 100%); }
        .message { display: none; margin-bottom: 18px; padding: 13px 15px; border: 1px solid; border-radius: 9px; font-size: 13px; }
        .message.visible { display: block; }
        .message.success { color: #166534; background: #f0fdf4; border-color: #bbf7d0; }
        .message.error { color: #991b1b; background: #fef2f2; border-color: #fecaca; }
        .message.loading { color: #1e40af; background: #eff6ff; border-color: #bfdbfe; }
        .table-wrap { overflow-x: auto; border: 1px solid #e4e7ec; border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 12px 14px; border-bottom: 1px solid #eaecf0; font-size: 13px; vertical-align: middle; }
        th { color: #667085; background: #f8fafc; font-size: 10px; letter-spacing: .5px; text-transform: uppercase; }
        tr:last-child td { border-bottom: 0; }
        .actions { flex-wrap: wrap; }
        .empty { padding: 24px; color: #667085; text-align: center; }
        .api-note { margin: 12px 0 0; color: #667085; font-size: 12px; }

        @media (max-width: 650px) {
            main { margin-top: 20px; }
            .intro, .panel { padding: 18px; }
            form { grid-template-columns: 1fr; }
            .form-actions { grid-column: auto; flex-wrap: wrap; }
            .panel-heading { flex-direction: column; }
            .filters { width: 100%; flex-wrap: wrap; }
            .filters input { flex: 1 1 100%; }
        }
    </style>
</head>
<body>
    <?php app_render_portal_header('Records API'); ?>
    <main>
        <section class="intro">
            <p class="eyebrow">FETCH API LAB</p>
            <h1>Student records</h1>
            <p>List, add, update, and deactivate student records using the PHP JSON API.</p>
            <a class="button" href="dashboard.php">Admin dashboard</a>
        </section>

        <div id="api-message" class="message" role="status" aria-live="polite"></div>

        <section class="panel" aria-labelledby="form-title">
            <div class="panel-heading">
                <div>
                    <h2 id="form-title">Add a student</h2>
                    <p>Enter the student's enrollment number, name, email, and course.</p>
                </div>
            </div>
            <form id="student-form">
                <input type="hidden" name="student_id" id="student-id">
                <label>Enrollment number
                    <input name="enrollment_no" id="enrollment-no" maxlength="20" required>
                </label>
                <label>Full name
                    <input name="full_name" id="full-name" maxlength="100" required>
                </label>
                <label>Email
                    <input type="email" name="email" id="email" maxlength="254" required>
                </label>
                <label>Course
                    <input name="course" id="course" maxlength="100" required>
                </label>
                <div class="form-actions">
                    <button id="save-button" type="submit">Add student</button>
                    <button id="cancel-edit" class="secondary" type="button" hidden>Cancel edit</button>
                </div>
            </form>
        </section>

        <section class="panel" aria-labelledby="list-title">
            <div class="panel-heading">
                <div>
                    <h2 id="list-title">Student list</h2>
                    <p id="record-count">Loading student records…</p>
                </div>
                <div class="filters">
                    <label for="student-search">Search records</label>
                    <input id="student-search" type="search" placeholder="Name, enrollment, or email">
                    <button id="refresh-button" class="secondary" type="button">Refresh</button>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Enrollment</th><th>Name</th><th>Email</th><th>Course</th><th>Actions</th></tr>
                    </thead>
                    <tbody id="student-list">
                        <tr><td class="empty" colspan="5">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="api-note">Delete uses a soft delete: the student is marked inactive and their account and registrations are retained.</p>
        </section>
    </main>
    <script src="js/interactive.js?v=20261028.2"></script>
    <script src="js/script.js?v=20261028.4" defer></script>
</body>
</html>
