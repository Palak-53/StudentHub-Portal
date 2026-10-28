USE studenthub_practical8;

ALTER TABLE student_accounts
    ADD COLUMN role ENUM('Student', 'Admin') NOT NULL DEFAULT 'Student' AFTER password_hash;

INSERT IGNORE INTO students (enrollment_no, full_name, email, course)
VALUES ('ADMIN001', 'Student Hub Admin', 'admin@studenthub.local', 'Administration');

INSERT IGNORE INTO student_accounts (student_id, username, password_hash, role)
SELECT student_id, 'admin', '$2y$10$WGWGcRD0TIPLLmUtDrWB0uxiiWgqeQ0m/xsuuQgBAFDCkxnqP3636', 'Admin'
FROM students
WHERE email = 'admin@studenthub.local';
