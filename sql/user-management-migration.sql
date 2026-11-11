ALTER TABLE users
    ADD COLUMN account_status ENUM('active', 'suspended') NOT NULL DEFAULT 'active' AFTER role,
    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER account_status;
