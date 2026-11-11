SET NAMES utf8mb4;

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    avatar_path VARCHAR(500) NULL,
    email VARCHAR(191) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'staff') NOT NULL,
    account_status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_verifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    school_email VARCHAR(191) NOT NULL,
    school_id_number VARCHAR(100) NULL,
    id_image_path VARCHAR(500) NULL,
    status ENUM('pending', 'code_requested', 'code_sent', 'code_verified', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
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

CREATE TABLE support_conversations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    subject VARCHAR(150) NOT NULL,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    closed_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
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

CREATE TABLE categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type ENUM('lost', 'found') NOT NULL,
    title VARCHAR(200) NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255) NOT NULL,
    date_event DATE NOT NULL,
    image_path VARCHAR(500) NULL,
    image_alt VARCHAR(255) NULL,
    verification_detail TEXT NULL,
    status ENUM('open', 'claimed', 'returned') NOT NULL DEFAULT 'open',
    review_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    reported_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_items_type (type),
    KEY idx_items_status (status),
    KEY idx_items_review_status (review_status),
    KEY idx_items_category_id (category_id),
    KEY idx_items_reported_by (reported_by),
    CONSTRAINT fk_items_reviewed_by
        FOREIGN KEY (reviewed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_items_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_items_reported_by
        FOREIGN KEY (reported_by) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE claims (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id INT UNSIGNED NOT NULL,
    claimant_id INT UNSIGNED NOT NULL,
    proof_description TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_claims_item_id (item_id),
    KEY idx_claims_claimant_id (claimant_id),
    KEY idx_claims_status (status),
    KEY idx_claims_reviewed_by (reviewed_by),
    CONSTRAINT fk_claims_item
        FOREIGN KEY (item_id) REFERENCES items (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_claims_claimant
        FOREIGN KEY (claimant_id) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_claims_reviewed_by
        FOREIGN KEY (reviewed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
