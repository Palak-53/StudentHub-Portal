# Student Hub Portal

Student Hub is a student-portal and web-development practical project. It combines public academic information pages with PHP/MySQL sign-in, student and administrator dashboards, student-record and event management, and course revision materials.

> **Local/demo project:** Do not publish this installation as-is. The accompanying [test and security report](TEST_SECURITY_REPORT.md) documents publicly accessible student/contact data, a public database test page, and other deployment risks that need attention first.

## Features

- Student and Admin sign-in, role-specific navigation, protected PHP pages, and a 30-minute inactivity timeout.
- Personalized student dashboard and profile.
- Admin dashboard with database-backed statistics, recent registrations, and audit-log entries.
- Admin student-record search, course/status filtering, add/edit, soft-delete, and the Fetch/JSON API.
- Admin event management, including create/edit/delete and image poster upload.
- Public information pages for courses, faculty, attendance, notices, fees, leave, results, and contact.
- Course Materials library for DSA, DBMS, Operating Systems, and Computer Networks, with original revision-guide PDFs and external references.
- Registration and contact forms, plus educational PDO/API practical pages.

## Project layout

| Path | Purpose |
|---|---|
| `php/` | Main PHP implementations for authentication, dashboards, student records, event management, database access, and form handling. |
| Root `*.php` files | Web entry points that route requests to the matching implementation in `php/`. |
| `sql/schema.sql` | Complete schema and sample data for a fresh local database. |
| `sql/*_migration.sql` | One-time migrations for older database installations; see the database warning below. |
| `css/`, `js/` | Stylesheets and client-side behavior. |
| `data/` | JSON/CSV sample and form data. **Currently web-accessible; do not put real personal data here.** |
| `materials/` | Course revision-guide PDFs. |
| `uploads/event-posters/` | Event poster uploads. |
| `images/`, `assests/` | Static images and project assets. (`assests` is the existing folder spelling.) |
| `webDev/`, `practice/` | Separate practical/demo pages. |

## Local setup (Windows/XAMPP)

1. Install XAMPP with Apache, MySQL/MariaDB, and PHP. The project was syntax-checked with PHP 8.2.12; it requires PHP 8.1 or later and the `mysqli`, `pdo_mysql`, `mbstring`, and `fileinfo` extensions.
2. Place the project folder under XAMPP's web root, for example:

   ```text
   C:\xampp\htdocs\StudentHub Portal
   ```

3. Start **Apache** and **MySQL** from the XAMPP Control Panel.
4. Open [phpMyAdmin](http://localhost/phpmyadmin/), choose **Import**, select `sql/schema.sql`, and run the import. The script creates the `studenthub_practical8` database, its tables, and local sample data.
5. Open the portal:

   - [Login](http://localhost/StudentHub%20Portal/login.php)
   - [Home](http://localhost/StudentHub%20Portal/home.php)
   - [Course Materials](http://localhost/StudentHub%20Portal/courseMaterials.html)

The database connection reads `STUDENTHUB_DB_HOST`, `STUDENTHUB_DB_NAME`, `STUDENTHUB_DB_USER`, and `STUDENTHUB_DB_PASSWORD`. For a typical local XAMPP setup, the defaults are `127.0.0.1`, `studenthub_practical8`, `root`, and an empty password. Those defaults are **only suitable for an isolated local development machine**. For any shared or deployed environment, configure a dedicated database user with a strong password and minimum required privileges; do not use MySQL's `root` account.

### Demo student login

The fresh schema includes this local-only account:

```text
Email:    student.demo@example.com
Password: Student@123
```

The login page also displays these credentials. Remove or disable this account and remove the displayed credentials before exposing the site to anyone outside local testing.

### Creating a local Admin account

There is no documented production-ready Admin password. For local testing, register a separate account using the registration page, then promote that account in phpMyAdmin. Replace the example value with the enrollment number used during registration:

```sql
USE studenthub_practical8;
UPDATE student_accounts
SET role = 'Admin'
WHERE username = 'YOUR_ENROLLMENT_NUMBER';
```

Use a unique, strong password. Do not promote the demo student account, and do not use this SQL workflow as a substitute for a reviewed administrator-provisioning process in production.

## Database setup and migrations

- **Fresh database:** import only `sql/schema.sql`. It includes the current tables and columns, including student status, account roles, event poster paths, and audit logs.
- **Existing older database:** back it up first, inspect its current tables/columns, then apply only the missing one-time migration(s). The migration scripts use `ALTER TABLE` statements and can fail if their columns already exist.
- Do not blindly run all SQL files in sequence after importing `schema.sql`. In particular, the student status, account role, event poster, and audit-log structures are already present in the current schema.
- `sql/demo_student_login_migration.sql` resets the demo student's password to the published demo password. Do not run it on a live or shared database.

## Testing

There is no configured PHPUnit, npm, or other automated test suite in the repository. From PowerShell at the project root, the available syntax checks are:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object {
    & C:\xampp\php\php.exe -l $_.FullName
}

Get-ChildItem .\js -Filter *.js | ForEach-Object {
    node --check $_.FullName
}

git diff --check
```

For functional/manual testing, sign in with the demo Student account and verify the student dashboard, profile, Courses, Course Materials, and sign-out. Verify Admin-only behavior with a separate Admin account. The security report records which of these checks were performed in the local environment and which workflows remain unverified.

## Security and deployment

This project is suitable for local learning/testing only in its current state. Before deployment:

1. Block public HTTP access to student/form data under `data/`, and remove or protect the unguarded `test_connection.php` page.
2. Move private submissions and personal data outside the web document root; expose only deliberately anonymized sample data where the public Data Explorer needs it.
3. Remove the demo login credentials from the page and disable/reset the demo account.
4. Configure HTTPS and a dedicated least-privilege database account. Verify secure session-cookie settings and production error handling.
5. Add login throttling, review public form abuse protections, and set suitable HTTP security headers.
6. Correct and validate `data/submissions.json` before relying on JSON-backed contact submissions.

See [TEST_SECURITY_REPORT.md](TEST_SECURITY_REPORT.md) for the evidence, severity, and validation limitations.
