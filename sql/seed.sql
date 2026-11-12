INSERT INTO categories (id, name) VALUES
    (1, 'Electronics'),
    (2, 'School supplies'),
    (3, 'Bags and accessories'),
    (4, 'Clothing and wearables'),
    (5, 'Drinkware and lunch items'),
    (6, 'Sports and recreation'),
    (7, 'Personal items'),
    (8, 'Other');

INSERT INTO users (id, name, email, password_hash, role) VALUES
    (1, 'Sample Staff', 'staff@example.test', '$2y$10$bYESqAGMPRT33Phc6GJAbup6IF/qJ/mmJZ1vav/8zTRg.7G7DQgNy', 'staff'),
    (2, 'Sample Student', 'student@example.test', '$2y$10$bYESqAGMPRT33Phc6GJAbup6IF/qJ/mmJZ1vav/8zTRg.7G7DQgNy', 'student');

INSERT INTO items (
    id, type, title, category_id, description, location, date_event,
    image_path, image_alt, verification_detail, status, reported_by
) VALUES
    (1, 'lost', '[SAMPLE] Blue student ID card', 7, 'SAMPLE DATA: A fictional blue student ID card was misplaced.', 'Library', CURRENT_DATE - INTERVAL 2 DAY, NULL, NULL, NULL, 'open', 2),
    (2, 'lost', '[SAMPLE] Black wallet', 7, 'SAMPLE DATA: A fictional black wallet with no identifying details.', 'Cafeteria', CURRENT_DATE - INTERVAL 1 DAY, NULL, NULL, NULL, 'open', 2),
    (3, 'lost', '[SAMPLE] Silver key ring', 7, 'SAMPLE DATA: A fictional silver key ring with two keys.', 'Gym entrance', CURRENT_DATE - INTERVAL 3 DAY, NULL, NULL, NULL, 'open', 2),
    (4, 'found', '[SAMPLE] USB flash drive', 1, 'SAMPLE DATA: A fictional USB drive was handed in to staff.', 'Science building office', CURRENT_DATE - INTERVAL 1 DAY, NULL, NULL, 'SAMPLE ONLY: claimant should describe the drive contents or distinguishing mark.', 'open', 1),
    (5, 'found', '[SAMPLE] Graphing calculator', 1, 'SAMPLE DATA: A fictional calculator was found after class.', 'Room 204', CURRENT_DATE - INTERVAL 4 DAY, NULL, NULL, 'SAMPLE ONLY: claimant should identify a distinguishing mark.', 'open', 1),
    (6, 'found', '[SAMPLE] Green water bottle', 5, 'SAMPLE DATA: A fictional green water bottle was left behind.', 'Auditorium', CURRENT_DATE - INTERVAL 2 DAY, NULL, NULL, 'SAMPLE ONLY: claimant should describe a distinguishing feature.', 'open', 1);
