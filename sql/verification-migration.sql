CREATE TABLE student_verifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    school_email VARCHAR(191) NOT NULL,
    school_id_number VARCHAR(100) NULL,
    id_image_path VARCHAR(500) NULL,
    status ENUM('pending', 'code_sent', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    code_hash VARCHAR(255) NULL,
    code_expires_at DATETIME NULL,
    code_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    reviewed_by INT UNSIGNED NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    verified_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_verifications_user (user_id),
    UNIQUE KEY uq_student_verifications_school_email (school_email),
    KEY idx_student_verifications_status (status),
    CONSTRAINT fk_student_verifications_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_student_verifications_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
