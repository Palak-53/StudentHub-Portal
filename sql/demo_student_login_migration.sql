USE studenthub_practical8;

INSERT IGNORE INTO students (enrollment_no, full_name, email, course)
VALUES ('DEMO001', 'Demo Student', 'student.demo@example.com', 'B.Tech CSE');

UPDATE students
SET status = 'active'
WHERE email = 'student.demo@example.com';

INSERT IGNORE INTO student_accounts (student_id, username, password_hash, role)
SELECT student_id, 'DEMO001', '$2y$10$ugujx2zyvYz9p80YvWxLX.hgCDRWUsw9r3qQP51JRTUaJiGA/SYVG', 'Student'
FROM students
WHERE email = 'student.demo@example.com';

UPDATE student_accounts AS sa
INNER JOIN students AS s ON s.student_id = sa.student_id
SET sa.password_hash = '$2y$10$ugujx2zyvYz9p80YvWxLX.hgCDRWUsw9r3qQP51JRTUaJiGA/SYVG',
    sa.role = 'Student'
WHERE s.email = 'student.demo@example.com';
