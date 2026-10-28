USE studenthub_practical8;

ALTER TABLE students
    ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER gender;
