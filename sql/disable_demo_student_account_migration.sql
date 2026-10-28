-- Local-only cleanup for databases that already contain the former demo student.
-- Select studenthub_practical8 and explicitly opt in in the same session:
-- SET @studenthub_local_demo_cleanup_confirmed = 1;
-- Never run this migration against a shared or production database.

SELECT CASE
    WHEN DATABASE() = 'studenthub_practical8'
        AND COALESCE(@studenthub_local_demo_cleanup_confirmed, 0) = 1
    THEN 'Cleaning up the former local demo account.'
    ELSE 'No changes made. Select studenthub_practical8 and explicitly opt in first.'
END AS migration_status;

DELETE sa
FROM student_accounts AS sa
INNER JOIN students AS s ON s.student_id = sa.student_id
WHERE DATABASE() = 'studenthub_practical8'
  AND COALESCE(@studenthub_local_demo_cleanup_confirmed, 0) = 1
  AND s.enrollment_no = 'DEMO001'
  AND s.email = 'student.demo@example.com';

UPDATE students
SET status = 'inactive'
WHERE DATABASE() = 'studenthub_practical8'
  AND COALESCE(@studenthub_local_demo_cleanup_confirmed, 0) = 1
  AND enrollment_no = 'DEMO001'
  AND email = 'student.demo@example.com';
