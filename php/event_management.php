<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
require_user_role('Admin');

const EVENT_POSTER_MAX_BYTES = 1024 * 1024;
const EVENT_POSTER_DIRECTORY = __DIR__ . '/../uploads/event-posters';

function event_management_redirect(string $result): never
{
    header('Location: event_management.php?result=' . rawurlencode($result));
    exit;
}

function event_store_poster(array $upload): string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            ($upload['error'] ?? null) === UPLOAD_ERR_INI_SIZE || ($upload['error'] ?? null) === UPLOAD_ERR_FORM_SIZE
                ? 'poster_size'
                : 'poster_upload'
        );
    }

    if (!isset($upload['tmp_name'], $upload['size']) || !is_uploaded_file($upload['tmp_name'])) {
        throw new RuntimeException('poster_upload');
    }
    if ((int) $upload['size'] <= 0 || (int) $upload['size'] > EVENT_POSTER_MAX_BYTES) {
        throw new RuntimeException('poster_size');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!is_string($mime) || !isset($allowedTypes[$mime]) || @getimagesize($upload['tmp_name']) === false) {
        throw new RuntimeException('poster_type');
    }

    if (!is_dir(EVENT_POSTER_DIRECTORY) && !mkdir(EVENT_POSTER_DIRECTORY, 0755, true) && !is_dir(EVENT_POSTER_DIRECTORY)) {
        throw new RuntimeException('poster_storage');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];
    if (!move_uploaded_file($upload['tmp_name'], EVENT_POSTER_DIRECTORY . '/' . $fileName)) {
        throw new RuntimeException('poster_storage');
    }

    return 'uploads/event-posters/' . $fileName;
}

function event_remove_poster(?string $relativePath): void
{
    if (
        $relativePath !== null
        && preg_match('~\Auploads/event-posters/[a-f0-9]{32}\.(?:jpg|png|webp)\z~', $relativePath) === 1
    ) {
        $fullPath = __DIR__ . '/../' . $relativePath;
        if (is_file($fullPath) && !unlink($fullPath)) {
            error_log('[StudentHub] Could not remove event poster: ' . $relativePath);
        }
    }
}

$resultMessages = [
    'created' => ['success', 'Event created successfully.'],
    'updated' => ['success', 'Event updated successfully.'],
    'deleted' => ['success', 'Event deleted successfully. Its registrations were also removed.'],
    'invalid' => ['error', 'Please complete all required fields with valid values.'],
    'poster_type' => ['error', 'Invalid poster file type. Upload a JPEG, PNG, or WebP image.'],
    'poster_size' => ['error', 'Poster file is too large. Maximum allowed size is 1 MB.'],
    'poster_upload' => ['error', 'The poster upload did not complete. Please try again.'],
    'poster_storage' => ['error', 'The poster could not be saved. Check folder permissions and try again.'],
    'csrf' => ['error', 'Your session expired. Refresh the page and try again.'],
    'not_found' => ['error', 'That event was not found.'],
    'database' => ['error', 'The event could not be saved. Check the database connection and apply the event poster migration.'],
];
$notice = $resultMessages[(string) ($_GET['result'] ?? '')] ?? null;
$databaseError = null;
$events = [];
$editingEvent = null;
$formValues = [
    'title' => '',
    'description' => '',
    'event_date' => '',
    'venue' => '',
    'capacity' => '',
    'poster_path' => null,
];

try {
    require_once __DIR__ . '/db.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!is_string($submittedToken) || !hash_equals(app_csrf_token(), $submittedToken)) {
            $conn->close();
            event_management_redirect('csrf');
        }

        $action = (string) ($_POST['action'] ?? '');
        $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($action === 'delete') {
            if ($eventId === false) {
                $conn->close();
                event_management_redirect('not_found');
            }

            $statement = $conn->prepare('SELECT poster_path FROM events WHERE event_id = ?');
            $statement->bind_param('i', $eventId);
            $statement->execute();
            $event = $statement->get_result()->fetch_assoc();
            $statement->close();
            if (!is_array($event)) {
                $conn->close();
                event_management_redirect('not_found');
            }

            $statement = $conn->prepare('DELETE FROM events WHERE event_id = ?');
            $statement->bind_param('i', $eventId);
            $statement->execute();
            $statement->close();
            app_audit_log($conn, 'event.deleted', 'event', $eventId);
            $conn->close();
            event_remove_poster($event['poster_path']);
            event_management_redirect('deleted');
        }

        if (!in_array($action, ['create', 'update'], true) || ($action === 'update' && $eventId === false)) {
            $conn->close();
            event_management_redirect('invalid');
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $eventDate = trim((string) ($_POST['event_date'] ?? ''));
        $venue = trim((string) ($_POST['venue'] ?? ''));
        $capacityInput = trim((string) ($_POST['capacity'] ?? ''));
        $capacity = filter_var($capacityInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        $dateParts = explode('-', $eventDate);
        $dateValid = count($dateParts) === 3
            && checkdate((int) $dateParts[1], (int) $dateParts[2], (int) $dateParts[0])
            && sprintf('%04d-%02d-%02d', (int) $dateParts[0], (int) $dateParts[1], (int) $dateParts[2]) === $eventDate;

        if (
            $title === ''
            || mb_strlen($title) > 150
            || $description === ''
            || $eventDate === ''
            || !$dateValid
            || $venue === ''
            || mb_strlen($venue) > 150
            || $capacity === false
        ) {
            $conn->close();
            event_management_redirect('invalid');
        }

        $posterPath = '';
        try {
            $posterPath = event_store_poster($_FILES['poster'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
        } catch (RuntimeException $exception) {
            $conn->close();
            event_management_redirect($exception->getMessage());
        }

        if ($action === 'create') {
            $statement = $conn->prepare(
                'INSERT INTO events (title, description, event_date, venue, capacity, poster_path)
                VALUES (?, ?, ?, ?, ?, ?)'
            );
            $statement->bind_param('ssssis', $title, $description, $eventDate, $venue, $capacity, $posterPath);
            $successResult = 'created';
            $oldPosterPath = null;
        } else {
            $lookup = $conn->prepare('SELECT poster_path FROM events WHERE event_id = ?');
            $lookup->bind_param('i', $eventId);
            $lookup->execute();
            $existingEvent = $lookup->get_result()->fetch_assoc();
            $lookup->close();
            if (!is_array($existingEvent)) {
                $conn->close();
                if ($posterPath !== '') {
                    event_remove_poster($posterPath);
                }
                event_management_redirect('not_found');
            }

            $oldPosterPath = $existingEvent['poster_path'];
            if ($posterPath === '') {
                $statement = $conn->prepare(
                    'UPDATE events SET title = ?, description = ?, event_date = ?, venue = ?, capacity = ? WHERE event_id = ?'
                );
                $statement->bind_param('ssssii', $title, $description, $eventDate, $venue, $capacity, $eventId);
            } else {
                $statement = $conn->prepare(
                    'UPDATE events SET title = ?, description = ?, event_date = ?, venue = ?, capacity = ?, poster_path = ? WHERE event_id = ?'
                );
                $statement->bind_param('ssssisi', $title, $description, $eventDate, $venue, $capacity, $posterPath, $eventId);
            }
            $successResult = 'updated';
        }

        try {
            $statement->execute();
        } catch (mysqli_sql_exception $exception) {
            $statement->close();
            $conn->close();
            if ($posterPath !== '') {
                event_remove_poster($posterPath);
            }
            throw $exception;
        }
        $statement->close();
        $savedEventId = $action === 'create' ? (int) $conn->insert_id : (int) $eventId;
        app_audit_log($conn, 'event.' . ($action === 'create' ? 'created' : 'updated'), 'event', $savedEventId);
        $conn->close();
        if ($posterPath !== '' && $oldPosterPath !== null) {
            event_remove_poster($oldPosterPath);
        }
        event_management_redirect($successResult);
    }

    if (isset($_GET['edit'])) {
        $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($editId !== false) {
            $statement = $conn->prepare('SELECT * FROM events WHERE event_id = ?');
            $statement->bind_param('i', $editId);
            $statement->execute();
            $editingEvent = $statement->get_result()->fetch_assoc() ?: null;
            $statement->close();
            if (is_array($editingEvent)) {
                $formValues = $editingEvent;
            }
        }
    }

    $eventsResult = $conn->query('SELECT event_id, title, description, event_date, venue, capacity, poster_path FROM events ORDER BY event_date, event_id');
    $events = $eventsResult->fetch_all(MYSQLI_ASSOC);
    $conn->close();
} catch (mysqli_sql_exception $exception) {
    error_log('[StudentHub] Event management query failed: ' . $exception->getMessage());
    http_response_code(500);
    $databaseError = 'Events could not be loaded. Check the database connection and apply the event poster migration.';
} catch (RuntimeException $exception) {
    http_response_code(500);
    $databaseError = $exception->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events | Student Hub</title>
    <link rel="stylesheet" href="css/style.css?v=20261028.2">
    <link rel="stylesheet" href="css/interactive.css?v=20261028.2">
    <link rel="stylesheet" href="css/role-dashboard.css?v=20261028.2">
    <link rel="stylesheet" href="css/event-management.css">
</head>
<body>
    <?php app_render_portal_header('Events'); ?>

    <main class="app-container role-main event-management-main">
        <div class="role-welcome">
            <div>
                <p class="role-eyebrow">ADMINISTRATION</p>
                <h1>Manage events</h1>
                <p>Create, update, and delete campus events and upload event posters.</p>
            </div>
            <span class="role-welcome-icon" aria-hidden="true">▦</span>
        </div>

        <?php if ($notice !== null): ?>
            <div class="event-notice event-notice-<?= app_escape($notice[0]) ?>" role="<?= $notice[0] === 'success' ? 'status' : 'alert' ?>">
                <?= app_escape($notice[1]) ?>
            </div>
        <?php endif; ?>

        <?php if ($databaseError !== null): ?>
            <div class="role-error" role="alert"><?= app_escape($databaseError) ?></div>
        <?php else: ?>
            <section class="role-content-card">
                <div class="role-section-heading">
                    <div>
                        <h2><?= $editingEvent !== null ? 'Update event' : 'Create an event' ?></h2>
                        <p>JPEG, PNG, or WebP posters up to 1 MB are accepted.</p>
                    </div>
                </div>
                <form method="post" action="event_management.php" enctype="multipart/form-data" class="event-form">
                    <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                    <input type="hidden" name="action" value="<?= $editingEvent !== null ? 'update' : 'create' ?>">
                    <?php if ($editingEvent !== null): ?>
                        <input type="hidden" name="event_id" value="<?= (int) $editingEvent['event_id'] ?>">
                    <?php endif; ?>
                    <label>Event title *
                        <input name="title" maxlength="150" required value="<?= app_escape((string) $formValues['title']) ?>">
                    </label>
                    <label>Date *
                        <input type="date" name="event_date" required value="<?= app_escape((string) $formValues['event_date']) ?>">
                    </label>
                    <label>Venue *
                        <input name="venue" maxlength="150" required value="<?= app_escape((string) $formValues['venue']) ?>">
                    </label>
                    <label>Capacity *
                        <input type="number" name="capacity" min="1" max="65535" required value="<?= app_escape((string) $formValues['capacity']) ?>">
                    </label>
                    <label class="event-field-wide">Description *
                        <textarea name="description" rows="4" required><?= app_escape((string) $formValues['description']) ?></textarea>
                    </label>
                    <label class="event-field-wide">Poster
                        <input type="hidden" name="MAX_FILE_SIZE" value="<?= EVENT_POSTER_MAX_BYTES ?>">
                        <input type="file" name="poster" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    </label>
                    <?php if (!empty($formValues['poster_path'])): ?>
                        <div class="event-current-poster event-field-wide">
                            <span>Current poster</span>
                            <img src="<?= app_escape((string) $formValues['poster_path']) ?>" alt="Current event poster">
                        </div>
                    <?php endif; ?>
                    <div class="event-form-actions">
                        <button class="event-button" type="submit"><?= $editingEvent !== null ? 'Save event' : 'Create event' ?></button>
                        <?php if ($editingEvent !== null): ?>
                            <a class="event-button event-button-secondary" href="event_management.php">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="role-content-card">
                <div class="role-section-heading">
                    <div>
                        <h2>Event list</h2>
                        <p><?= count($events) ?> event<?= count($events) === 1 ? '' : 's' ?></p>
                    </div>
                </div>
                <?php if ($events === []): ?>
                    <p class="role-empty">No events have been created yet.</p>
                <?php else: ?>
                    <div class="role-table-wrap">
                        <table class="role-table event-table">
                            <thead><tr><th>Poster</th><th>Event</th><th>Date</th><th>Venue</th><th>Capacity</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($event['poster_path'])): ?>
                                                <img class="event-poster-thumb" src="<?= app_escape((string) $event['poster_path']) ?>" alt="<?= app_escape($event['title']) ?> poster">
                                            <?php else: ?>
                                                <span class="event-no-poster">No poster</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= app_escape($event['title']) ?></strong><small><?= app_escape($event['description']) ?></small></td>
                                        <td><?= app_escape(date('M j, Y', strtotime($event['event_date']))) ?></td>
                                        <td><?= app_escape($event['venue']) ?></td>
                                        <td><?= (int) $event['capacity'] ?></td>
                                        <td>
                                            <div class="event-row-actions">
                                                <a class="student-action-link" href="event_management.php?edit=<?= (int) $event['event_id'] ?>">Edit</a>
                                                <form method="post" action="event_management.php" onsubmit="return confirm('Delete this event? Its registrations will also be permanently deleted.');">
                                                    <input type="hidden" name="csrf_token" value="<?= app_escape(app_csrf_token()) ?>">
                                                    <input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="event-button event-button-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
    <footer class="app-footer"><p>© 2026 Student Hub Portal. Administrator access is active.</p></footer>
    <script src="js/interactive.js?v=20261028.2"></script>
    <script src="js/script.js?v=20261028.4"></script>
</body>
</html>
