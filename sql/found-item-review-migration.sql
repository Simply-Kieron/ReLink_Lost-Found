ALTER TABLE items
    ADD COLUMN review_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved' AFTER status,
    ADD COLUMN reviewed_by INT UNSIGNED NULL AFTER review_status,
    ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by,
    ADD KEY idx_items_review_status (review_status),
    ADD CONSTRAINT fk_items_reviewed_by
        FOREIGN KEY (reviewed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE;
