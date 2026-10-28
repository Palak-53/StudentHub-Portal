# Student Hub Portal — Test & Security Report

**Assessment date:** 2026-10-28

**Environment:** Windows/XAMPP, local Apache/MySQL, PHP 8.2.12, Node.js 24.13.0

**Application URL tested:** `http://localhost/StudentHub%20Portal/`

## Executive summary

The local application starts and several key role-based flows and course-material behaviors work. All PHP files passed syntax checking and all JavaScript files passed Node syntax checking.

**This is not a production security approval.** The most important security test failed: unauthenticated requests can download student/contact data files and open a database test page that renders registration records. A form-storage JSON file is also malformed. Resolve these issues before using real student data or making the website reachable outside an isolated local machine.

## Scope and limitations

This report covers source inspection and targeted checks against the local XAMPP instance, including PHP/JavaScript syntax, demo-student sign-in, selected authorization responses, course-material search/PDF responses, public data URLs, and the database test page.

This was **not** a full penetration test, independent code audit, accessibility certification, load test, or verification of every page and business workflow. No external service or production system was tested. The report does not include contents of the exposed files.

## Test results

| Area | Check | Result | Evidence/notes |
|---|---|---|---|
| PHP syntax | `php -l` on all PHP files | **PASS** | PHP 8.2.12 reported no syntax errors. |
| JavaScript syntax | `node --check` on all files in `js/` | **PASS** | All JavaScript syntax checks completed successfully. |
| Student sign-in | Demo Student account | **PASS** | Sign-in reached the Student dashboard and displayed the signed-in student's identity. |
| Role-specific navigation | Student navigation | **PASS** | Student navigation did not include the Admin Student Records link. |
| Admin authorization | Student requests to Admin event management and student records | **PASS** | Both pages returned HTTP 403 to the signed-in Student. |
| JSON API authorization | Student and signed-out requests | **PASS** | Student request returned HTTP 403; signed-out request returned HTTP 401. |
| Sign-out | Student sign-out | **PASS** | Returned to Login with a signed-out confirmation. |
| Course Materials | Search/filter and empty-search result | **PASS** | Searching a topic isolated its subject; an unmatched term showed the empty state. |
| Revision PDFs | Four subject PDFs | **PASS** | All four local PDF requests returned HTTP 200 and `application/pdf`. |
| PHP protected route | Signed-out request to Student Records | **PASS** | Redirected to the Login page. |
| Public sample-data files | Unauthenticated HTTP requests | **FAIL — security finding 1** | `data/students.json`, `data/submissions.json`, and `data/submissions.csv` returned HTTP 200. |
| Database test page | Unauthenticated request | **FAIL — security finding 1** | `test_connection.php` returned HTTP 200, confirmed a database connection, and rendered registration rows. |
| JSON-backed form storage | Parse `data/*.json` | **FAIL** | `data/submissions.json` is invalid JSON. `events.json`, `faqs.json`, and `students.json` parsed successfully. |
| Admin CRUD / event management | Create, update, delete, poster upload, audit verification | **NOT TESTED** | No Admin end-to-end workflow was run for this report. |
| Full-page/browser compatibility | All pages and viewport sizes | **NOT TESTED** | Targeted routes only; not a cross-browser or accessibility test. |

## Security findings

### 1. High — Student and contact information is publicly readable

**Affected resources:** `data/students.json`, `data/submissions.json`, `data/submissions.csv`, and `test_connection.php` (implemented in `php/test_connection.php`).

**Evidence:** Direct unauthenticated HTTP requests to the data files returned HTTP 200. The unauthenticated `test_connection.php` page returned HTTP 200, established a database connection, and rendered registration rows. The exposed records include personal and academic/contact information; their contents are intentionally not reproduced here.

**Impact:** Anyone who can reach the web server may retrieve these files or view the database query output without signing in. This is a privacy and data-protection issue even if current data is intended to be sample data.

**Required remediation:**

1. Remove private/student/contact records from the public document root, or deny web access to the relevant files and directories at the server configuration level.
2. Store contact submissions and student records in protected storage, not publicly downloadable JSON/CSV files.
3. Replace any public Data Explorer input with synthetic, anonymized sample data.
4. Restrict `test_connection.php` to a local/development environment or Admin-only access, and remove it from any production deployment.
5. Re-test every affected URL without a session and confirm it returns 403/404 rather than data.

### 2. Medium — Demo credentials are disclosed in the sign-in page

**Affected resource:** `php/login.php` (the root `login.php` entry point loads it).

**Evidence:** The login page displays the demo student's email and password in clear text, and the database seed/migration creates or resets that demo account.

**Impact:** Anyone who can reach the sign-in page can authenticate to the demo Student account. This is expected for a local practical but is unsafe for a shared or public deployment.

**Required remediation:** Remove the credential display, disable or reset the demo account, and ensure the demo migration is never run against a live database.

### 3. Medium — Database defaults use the MySQL root account with an empty password

**Affected resource:** `php/db.php` (database defaults).

**Evidence:** When environment variables are absent, the connection defaults to database user `root` with an empty password.

**Impact:** This may be convenient for a local XAMPP installation, but is unsafe if reused in a shared or deployed environment. A compromised application connection could inherit excessive database privileges.

**Required remediation:** Configure all database environment variables explicitly in the server environment and use a dedicated database account with only the permissions the portal needs. Do not use MySQL root or an empty password outside isolated local development.

### 4. Medium — No login throttling was identified in the authentication flow

**Affected resource:** `php/authenticate.php`.

**Evidence:** Source inspection found password verification and generic failure handling, but no rate limit, progressive delay, or account lockout in the login flow.

**Impact:** Repeated password guesses are not throttled by the application code reviewed here.

**Required remediation:** Add rate limiting with safe account/IP-based controls, monitor repeated failures, and avoid permanent lockouts that enable denial of service. This was a source review observation; no password-guessing test was performed.

### 5. Medium — `data/submissions.json` is malformed and form persistence will fail

**Affected resource:** `data/submissions.json` and the JSON-backed storage in `php/submission_storage.php`.

**Evidence:** A JSON parser rejected the existing file. The storage helper expects an empty file or a valid JSON array before it can append a new submission.

**Impact:** Contact-form submissions that use this storage path can fail instead of being saved. The same file is also publicly retrievable, as noted in finding 1.

**Required remediation:** Correct the seed file to a valid JSON array or initialize it as an empty array, and add a test that submits a valid inquiry and verifies it was saved. Prefer moving the file outside the web root.

## Positive controls observed

- Passwords are checked with `password_verify`; new registrations use `password_hash`.
- Authentication regenerates the session ID after successful sign-in.
- Session cookies are configured `HttpOnly` and `SameSite=Lax`; the `Secure` flag is conditional on HTTPS.
- Login, logout, Admin student mutations, event mutations, and JSON API mutations use CSRF-token checks.
- Admin pages and API operations enforce role checks; the tested Student requests received 403.
- SQL statements inspected for login and student operations use prepared statements.
- Dynamic PHP-rendered values are escaped in the inspected templates.
- Event poster uploads validate MIME type and image content, enforce a 1 MB limit, and use generated filenames.
- The application has a 30-minute inactivity timeout.

These controls do not mitigate the public-data exposure. HTTPS must be configured for deployment so session cookies receive the `Secure` attribute.

## Recommended remediation order

1. **Immediately:** protect/remove student and submission files and restrict `test_connection.php`; verify anonymous requests cannot read records.
2. Repair the invalid submission JSON and confirm contact form persistence.
3. Remove the demo credentials/account before any shared or public deployment.
4. Replace the root/blank-password database defaults with a dedicated least-privilege database account and explicit server configuration.
5. Add login throttling, enforce HTTPS, and review production security headers and error handling.
6. Run Admin student CRUD, event CRUD/upload, audit-log verification, and the full manual regression checklist after fixes.

## Suggested follow-up regression checklist

- Verify an anonymous visitor is redirected from protected dashboard/profile/Admin pages and receives 401 from the JSON API.
- Verify a Student receives 403 for Admin pages and write operations, while a separately provisioned Admin can use the permitted workflows.
- Test student create, edit, search, course/status filtering, soft-delete, and audit logging with non-sensitive test data.
- Test event create, edit, delete, valid poster upload, invalid file rejection, and audit logging.
- Submit valid and invalid contact/registration forms; confirm validation, persistence, and escaped output.
- Confirm every intended public page and all four course PDFs still work after data storage is moved/protected.
- Repeat anonymous-access checks for all files beneath `data/` and for `test_connection.php`.
