CREATE TABLE support_conversations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference_code CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    subject VARCHAR(150) NOT NULL,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    closed_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_support_conversations_reference_code (reference_code),
    KEY idx_support_conversations_student (student_id, updated_at),
    KEY idx_support_conversations_status (status, updated_at),
    CONSTRAINT fk_support_conversations_student
        FOREIGN KEY (student_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_support_conversations_closed_by
        FOREIGN KEY (closed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE support_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_support_messages_conversation (conversation_id, created_at),
    CONSTRAINT fk_support_messages_conversation
        FOREIGN KEY (conversation_id) REFERENCES support_conversations (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_support_messages_sender
        FOREIGN KEY (sender_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
