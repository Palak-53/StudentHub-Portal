USE studenthub_practical8;

ALTER TABLE students
    ADD COLUMN mobile VARCHAR(20) NULL AFTER email,
    ADD COLUMN study_year TINYINT UNSIGNED NULL AFTER course,
    ADD COLUMN gender ENUM('male', 'female', 'other') NULL AFTER study_year;

CREATE TABLE student_accounts (
    account_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL UNIQUE,
    username VARCHAR(20) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_student_accounts_student
        FOREIGN KEY (student_id) REFERENCES students (student_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;
