TYPE=TRIGGERS
triggers='CREATE DEFINER=`root`@`localhost` TRIGGER before_order_insert \nBEFORE INSERT ON orders\nFOR EACH ROW\nBEGIN\n    DECLARE next_id INT;\n    DECLARE order_prefix VARCHAR(10);\n    \n    SELECT setting_value INTO order_prefix \n    FROM system_settings \n    WHERE setting_key = \'order_prefix\' \n    LIMIT 1;\n    \n    IF order_prefix IS NULL THEN\n        SET order_prefix = \'GW\';\n    END IF;\n    \n    SET next_id = COALESCE((SELECT MAX(order_id) + 1 FROM orders), 1);\n    SET NEW.order_number = COALESCE(NEW.order_number, CONCAT(order_prefix, DATE_FORMAT(NOW(), \'%Y%m%d\'), LPAD(next_id, 4, \'0\')));\nEND' 'CREATE DEFINER=`root`@`localhost` TRIGGER update_order_status_history \nAFTER UPDATE ON orders\nFOR EACH ROW\nBEGIN\n    IF OLD.status <> NEW.status THEN\n        INSERT INTO order_status_history (order_id, old_status, new_status, note)\n        VALUES (NEW.order_id, OLD.status, NEW.status, CONCAT(\'Status changed from \', OLD.status, \' to \', NEW.status));\n    END IF;\nEND'
sql_modes=1098907648 1098907648
definers='root@localhost' 'root@localhost'
client_cs_names='utf8mb4' 'utf8mb4'
connection_cl_names='utf8mb4_general_ci' 'utf8mb4_general_ci'
db_cl_names='utf8mb4_unicode_ci' 'utf8mb4_unicode_ci'
created=1775209628056773 1775209628067272
