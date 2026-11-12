ALTER TABLE support_conversations
    ADD COLUMN reference_code CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER id;

UPDATE support_conversations
SET reference_code = UPPER(SUBSTRING(SHA2(CONCAT(UUID(), ':', id), 256), 1, 16))
WHERE reference_code IS NULL;

ALTER TABLE support_conversations
    MODIFY reference_code CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ADD UNIQUE KEY uq_support_conversations_reference_code (reference_code);
