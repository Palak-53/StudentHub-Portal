USE studenthub_practical8;

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
