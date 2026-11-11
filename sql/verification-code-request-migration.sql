ALTER TABLE student_verifications
    MODIFY status ENUM('pending', 'code_requested', 'code_sent', 'verified', 'rejected') NOT NULL DEFAULT 'pending';
