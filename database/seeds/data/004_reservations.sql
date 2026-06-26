INSERT INTO reservations (id, status, start_at, end_at, party_name, party_email, party_size, party_phone, created_at) VALUES
    ('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbb001', 'pending', '2024-06-03 10:00:00', '2024-06-03 11:00:00', 'Alice Martin', 'alice@example.com', 2, NULL,          '2024-06-01 09:00:00'),
    ('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbb002', 'pending', '2024-06-03 14:00:00', '2024-06-03 15:00:00', 'Bob Chen',     'bob@example.com',   3, '+1234567890', '2024-06-01 09:05:00'),
    ('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbb003', 'pending', '2024-06-04 11:00:00', '2024-06-04 12:00:00', 'Carol Davis',  'carol@example.com', 4, NULL,          '2024-06-01 09:10:00')
ON DUPLICATE KEY UPDATE
    status      = VALUES(status),
    start_at    = VALUES(start_at),
    end_at      = VALUES(end_at),
    party_name  = VALUES(party_name),
    party_email = VALUES(party_email),
    party_size  = VALUES(party_size),
    party_phone = VALUES(party_phone);

INSERT INTO reservation_resources (reservation_id, resource_id) VALUES
    ('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbb001', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa001'),
    ('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbb002', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa002'),
    ('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbb003', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaa003')
ON DUPLICATE KEY UPDATE
    resource_id = VALUES(resource_id)
