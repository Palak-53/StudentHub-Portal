USE studenthub_practical8;

ALTER TABLE student_accounts
    ADD COLUMN role ENUM('Student', 'Admin') NOT NULL DEFAULT 'Student' AFTER password_hash;

INSERT IGNORE INTO students (enrollment_no, full_name, email, course)
VALUES ('ADMIN001', 'Student Hub Admin', 'admin@studenthub.local', 'Administration');

INSERT IGNORE INTO students (enrollment_no, full_name, email, course)
VALUES ('DEMO001', 'Demo Student', 'student.demo@example.com', 'B.Tech CSE');

UPDATE students
SET status = 'active'
WHERE email = 'student.demo@example.com';

INSERT IGNORE INTO student_accounts (student_id, username, password_hash, role)
SELECT student_id, 'admin', '$2y$10$WGWGcRD0TIPLLmUtDrWB0uxiiWgqeQ0m/xsuuQgBAFDCkxnqP3636', 'Admin'
FROM students
WHERE email = 'admin@studenthub.local';

INSERT IGNORE INTO student_accounts (student_id, username, password_hash, role)
SELECT student_id, 'DEMO001', '$2y$10$ORZfEd5RW/nBNVixgwo/j.kLIf56j9ltktWTJ0pDfNAmqDZ1DiJCS', 'Student'
FROM students
WHERE email = 'student.demo@example.com';

UPDATE student_accounts AS sa
INNER JOIN students AS s ON s.student_id = sa.student_id
SET sa.password_hash = '$2y$10$ugujx2zyvYz9p80YvWxLX.hgCDRWUsw9r3qQP51JRTUaJiGA/SYVG',
    sa.role = 'Student'
WHERE s.email = 'student.demo@example.com';
