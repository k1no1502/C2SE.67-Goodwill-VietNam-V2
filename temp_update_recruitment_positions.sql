USE goodwill_vietnam;
INSERT INTO recruitment_positions (position_id, position_name, description, is_active, sort_order) VALUES
(1, 'Quản lý kho hàng', 'Quản lý nhập/xuất và theo dõi tồn kho hàng hóa', 1, 1),
(2, 'Quản lý đơn hàng', 'Tiếp nhận, xử lý và theo dõi trạng thái đơn hàng', 1, 2),
(3, 'Quản lý chiến dịch', 'Lập kế hoạch, triển khai và giám sát các chiến dịch', 1, 3),
(4, 'Tư vấn chăm sóc khách hàng', 'Tư vấn, hỗ trợ và chăm sóc khách hàng đa kênh', 1, 4)
ON DUPLICATE KEY UPDATE
position_name = VALUES(position_name),
description = VALUES(description),
is_active = VALUES(is_active),
sort_order = VALUES(sort_order);
UPDATE recruitment_positions
SET is_active = 0
WHERE position_id NOT IN (1,2,3,4);
SELECT position_id, position_name, is_active, sort_order
FROM recruitment_positions
ORDER BY sort_order, position_name;
