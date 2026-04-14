TYPE=TRIGGERS
triggers='CREATE DEFINER=`root`@`localhost` TRIGGER after_donation_approved\nAFTER UPDATE ON donations\nFOR EACH ROW\nBEGIN\n    IF NEW.status = \'approved\' AND OLD.status <> \'approved\' THEN\n        IF NOT EXISTS (SELECT 1 FROM inventory WHERE donation_id = NEW.donation_id) THEN\n            INSERT INTO inventory (\n                donation_id, name, description, category_id, quantity, unit,\n                condition_status, estimated_value, actual_value, images,\n                status, price_type, sale_price, is_for_sale, created_at\n            ) VALUES (\n                NEW.donation_id,\n                NEW.item_name,\n                NEW.description,\n                NEW.category_id,\n                NEW.quantity,\n                NEW.unit,\n                NEW.condition_status,\n                NEW.estimated_value,\n                NEW.estimated_value,\n                NEW.images,\n                \'available\',\n                \'free\',\n                0,\n                TRUE,\n                NOW()\n            );\n        END IF;\n    END IF;\nEND'
sql_modes=1098907648
definers='root@localhost'
client_cs_names='utf8mb4'
connection_cl_names='utf8mb4_general_ci'
db_cl_names='utf8mb4_unicode_ci'
created=1775209628043398
