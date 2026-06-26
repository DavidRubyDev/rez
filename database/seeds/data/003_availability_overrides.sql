-- Table 1 is unavailable on this specific Saturday (demo override)
INSERT INTO availability_overrides (resource_id, date, available) VALUES
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa001', '2024-06-08', 0)
ON DUPLICATE KEY UPDATE
    available = VALUES(available)
