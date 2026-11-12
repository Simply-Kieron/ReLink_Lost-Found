INSERT IGNORE INTO categories (name) VALUES
    ('Electronics'),
    ('School supplies'),
    ('Bags and accessories'),
    ('Clothing and wearables'),
    ('Drinkware and lunch items'),
    ('Sports and recreation'),
    ('Personal items'),
    ('Other');

UPDATE items AS item
INNER JOIN categories AS old_category ON old_category.id = item.category_id
INNER JOIN categories AS grouped_category
    ON grouped_category.name = CASE LOWER(TRIM(old_category.name))
        WHEN 'usbs' THEN 'Electronics'
        WHEN 'calculators' THEN 'Electronics'
        WHEN 'chargers and cables' THEN 'Electronics'
        WHEN 'headphones and earbuds' THEN 'Electronics'
        WHEN 'books and notebooks' THEN 'School supplies'
        WHEN 'school supplies' THEN 'School supplies'
        WHEN 'bags and backpacks' THEN 'Bags and accessories'
        WHEN 'jewelry and accessories' THEN 'Bags and accessories'
        WHEN 'umbrellas' THEN 'Bags and accessories'
        WHEN 'jackets' THEN 'Clothing and wearables'
        WHEN 'clothing and uniforms' THEN 'Clothing and wearables'
        WHEN 'eyewear' THEN 'Clothing and wearables'
        WHEN 'bottles' THEN 'Drinkware and lunch items'
        WHEN 'food containers' THEN 'Drinkware and lunch items'
        WHEN 'sports equipment' THEN 'Sports and recreation'
        WHEN 'ids' THEN 'Personal items'
        WHEN 'id' THEN 'Personal items'
        WHEN 'wallets' THEN 'Personal items'
        WHEN 'wallet' THEN 'Personal items'
        WHEN 'keys' THEN 'Personal items'
        WHEN 'phones' THEN 'Personal items'
        WHEN 'phone' THEN 'Personal items'
        WHEN 'other' THEN 'Other'
        WHEN 'electronics' THEN 'Electronics'
        WHEN 'bags and accessories' THEN 'Bags and accessories'
        WHEN 'clothing and wearables' THEN 'Clothing and wearables'
        WHEN 'drinkware and lunch items' THEN 'Drinkware and lunch items'
        WHEN 'sports and recreation' THEN 'Sports and recreation'
        WHEN 'personal items' THEN 'Personal items'
        ELSE 'Other'
    END
SET item.category_id = grouped_category.id;

DELETE FROM categories
WHERE name NOT IN (
    'Electronics',
    'School supplies',
    'Bags and accessories',
    'Clothing and wearables',
    'Drinkware and lunch items',
    'Sports and recreation',
    'Personal items',
    'Other'
);
