<?php

declare(strict_types=1);

$errorMessage = null;
$registrations = [];

try {
    require __DIR__ . '/pdo_db.php';

    $statement = $pdo->prepare(
        'SELECT
            r.registration_id,
            s.enrollment_no,
            s.full_name,
            e.title AS event_title,
            e.event_date,
            r.registered_at,
            r.status
        FROM registrations AS r
        INNER JOIN students AS s ON s.student_id = r.student_id
        INNER JOIN events AS e ON e.event_id = r.event_id
        WHERE r.status = :status
        ORDER BY r.registration_id'
    );
    $statement->execute(['status' => 'registered']);
    $registrations = $statement->fetchAll();
} catch (RuntimeException $exception) {
    $errorMessage = $exception->getMessage();
} catch (PDOException $exception) {
    error_log('[StudentHub] Registration query failed: ' . $exception->getMessage());
    $errorMessage = 'The database query failed. Import sql/schema.sql and confirm the database tables are available.';
}

function escape_output(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical 8 — Database Connection Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px auto; max-width: 1000px; padding: 0 20px; color: #172033; }
        h1 { color: #243b80; }
        .message { padding: 14px 18px; border-radius: 8px; margin: 20px 0; }
        .success { color: #14532d; background: #dcfce7; border: 1px solid #86efac; }
        .error { color: #7f1d1d; background: #fef2f2; border: 1px solid #fca5a5; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { border: 1px solid #cbd5e1; padding: 10px; text-align: left; }
        th { background: #eff4ff; }
        code { background: #f1f5f9; padding: 2px 5px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Practical 8: PDO Database Connection</h1>

    <?php if ($errorMessage !== null): ?>
        <div class="message error" role="alert">
            <strong>Connection or query failed:</strong> <?= escape_output($errorMessage) ?>
        </div>
    <?php else: ?>
        <div class="message success" role="status">
            <strong>Database connection successful.</strong>
            Prepared statement returned <?= count($registrations) ?> registration(s).
        </div>

        <h2>Registered Students and Events</h2>
        <?php if ($registrations === []): ?>
            <p>No registered students found. Import the sample data from <code>sql/schema.sql</code>.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Registration ID</th>
                        <th>Enrollment</th>
                        <th>Student</th>
                        <th>Event</th>
                        <th>Event Date</th>
                        <th>Registered At</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $registration): ?>
                        <tr>
                            <td><?= escape_output((string) $registration['registration_id']) ?></td>
                            <td><?= escape_output($registration['enrollment_no']) ?></td>
                            <td><?= escape_output($registration['full_name']) ?></td>
                            <td><?= escape_output($registration['event_title']) ?></td>
                            <td><?= escape_output($registration['event_date']) ?></td>
                            <td><?= escape_output($registration['registered_at']) ?></td>
                            <td><?= escape_output($registration['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
