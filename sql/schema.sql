CREATE DATABASE IF NOT EXISTS studenthub_practical8
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE studenthub_practical8;

CREATE TABLE IF NOT EXISTS students (
    student_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrollment_no VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    mobile VARCHAR(20) NULL,
    course VARCHAR(100) NOT NULL,
    study_year TINYINT UNSIGNED NULL,
    gender ENUM('male', 'female', 'other') NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_accounts (
    account_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL UNIQUE,
    username VARCHAR(20) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Student', 'Admin') NOT NULL DEFAULT 'Student',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_student_accounts_student
        FOREIGN KEY (student_id) REFERENCES students (student_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS events (
    event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    event_date DATE NOT NULL,
    venue VARCHAR(150) NOT NULL,
    capacity SMALLINT UNSIGNED NOT NULL,
    poster_path VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS registrations (
    registration_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'cancelled', 'attended') NOT NULL DEFAULT 'registered',
    CONSTRAINT uq_student_event UNIQUE (student_id, event_id),
    CONSTRAINT fk_registrations_student
        FOREIGN KEY (student_id) REFERENCES students (student_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_registrations_event
        FOREIGN KEY (event_id) REFERENCES events (event_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
    audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_student_id INT UNSIGNED NULL,
    actor_name VARCHAR(100) NOT NULL,
    actor_role VARCHAR(20) NOT NULL,
    action VARCHAR(64) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NULL,
    details_json JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_logs_created_at (created_at),
    INDEX idx_audit_logs_actor (actor_student_id),
    CONSTRAINT fk_audit_logs_actor
        FOREIGN KEY (actor_student_id) REFERENCES students (student_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO students (enrollment_no, full_name, email, course) VALUES
    ('P8CS001', 'Aarav Shah', 'aarav.p8@example.com', 'B.Tech CSE'),
    ('P8IT002', 'Priya Mehta', 'priya.p8@example.com', 'B.Tech IT'),
    ('P8CS003', 'Rohan Patel', 'rohan.p8@example.com', 'B.Tech CSE');

INSERT IGNORE INTO students (enrollment_no, full_name, email, course)
VALUES ('ADMIN001', 'Student Hub Admin', 'admin@studenthub.local', 'Administration');

INSERT IGNORE INTO student_accounts (student_id, username, password_hash, role)
SELECT student_id, 'admin', '$2y$10$WGWGcRD0TIPLLmUtDrWB0uxiiWgqeQ0m/xsuuQgBAFDCkxnqP3636', 'Admin'
FROM students
WHERE email = 'admin@studenthub.local';

INSERT INTO events (title, description, event_date, venue, capacity)
SELECT 'Practical 8 Database Workshop', 'Introduction to relational databases and PDO.', '2026-11-05', 'Computer Lab 1', 60
WHERE NOT EXISTS (
    SELECT 1 FROM events WHERE title = 'Practical 8 Database Workshop'
);

INSERT INTO events (title, description, event_date, venue, capacity)
SELECT 'Student Project Showcase', 'Students present their semester projects.', '2026-11-12', 'Main Auditorium', 120
WHERE NOT EXISTS (
    SELECT 1 FROM events WHERE title = 'Student Project Showcase'
);

INSERT INTO events (title, description, event_date, venue, capacity)
SELECT 'Career Skills Seminar', 'A seminar about resumes and interview preparation.', '2026-11-19', 'Seminar Hall A', 80
WHERE NOT EXISTS (
    SELECT 1 FROM events WHERE title = 'Career Skills Seminar'
);

INSERT IGNORE INTO registrations (student_id, event_id, status)
SELECT s.student_id, e.event_id, 'registered'
FROM students AS s
CROSS JOIN events AS e
WHERE (s.enrollment_no = 'P8CS001' AND e.title = 'Practical 8 Database Workshop')
   OR (s.enrollment_no = 'P8IT002' AND e.title = 'Student Project Showcase')
   OR (s.enrollment_no = 'P8CS003' AND e.title = 'Career Skills Seminar');
