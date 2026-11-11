ALTER TABLE student_verifications
    MODIFY school_id_number VARCHAR(100) NULL,
    MODIFY id_image_path VARCHAR(500) NULL,
    MODIFY status ENUM('pending', 'code_requested', 'code_sent', 'code_verified', 'verified', 'rejected') NOT NULL DEFAULT 'pending';
