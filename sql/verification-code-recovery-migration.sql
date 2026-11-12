ALTER TABLE student_verifications
    ADD COLUMN code_ciphertext TEXT NULL AFTER code_hash;
