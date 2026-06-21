INSERT INTO availability_rules (resource_id, day_of_week, open_time, close_time) VALUES
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa001', 'monday',    '09:00', '17:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa001', 'tuesday',   '09:00', '17:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa001', 'wednesday', '09:00', '17:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa001', 'thursday',  '09:00', '17:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa001', 'friday',    '09:00', '17:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa001', 'saturday',  '10:00', '14:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa002', 'monday',    '09:00', '17:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa002', 'tuesday',   '09:00', '17:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa002', 'wednesday', '09:00', '17:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa002', 'thursday',  '09:00', '17:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa002', 'friday',    '09:00', '17:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa002', 'saturday',  '10:00', '14:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa003', 'monday',    '09:00', '17:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa003', 'tuesday',   '09:00', '17:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa003', 'wednesday', '09:00', '17:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa003', 'thursday',  '09:00', '17:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa003', 'friday',    '09:00', '17:00'),
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa003', 'saturday',  '10:00', '14:00')
ON DUPLICATE KEY UPDATE
    open_time  = VALUES(open_time),
    close_time = VALUES(close_time)
