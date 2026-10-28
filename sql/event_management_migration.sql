USE studenthub_practical8;

ALTER TABLE events
    ADD COLUMN poster_path VARCHAR(255) NULL AFTER capacity;
