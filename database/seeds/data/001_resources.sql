INSERT INTO resources (id, type, name, capacity, attributes) VALUES
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa001', 'table', 'Table 1', 4, '{}'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa002', 'table', 'Table 2', 4, '{}'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa003', 'table', 'Table 3', 4, '{}')
ON DUPLICATE KEY UPDATE
    type       = VALUES(type),
    name       = VALUES(name),
    capacity   = VALUES(capacity),
    attributes = VALUES(attributes)
