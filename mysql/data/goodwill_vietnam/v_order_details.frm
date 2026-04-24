TYPE=VIEW
query=select `o`.`order_id` AS `order_id`,`o`.`order_number` AS `order_number`,`o`.`user_id` AS `user_id`,`o`.`shipping_name` AS `shipping_name`,`o`.`shipping_phone` AS `shipping_phone`,`o`.`shipping_address` AS `shipping_address`,`o`.`shipping_place_id` AS `shipping_place_id`,`o`.`shipping_lat` AS `shipping_lat`,`o`.`shipping_lng` AS `shipping_lng`,`o`.`shipping_method` AS `shipping_method`,`o`.`shipping_note` AS `shipping_note`,`o`.`payment_method` AS `payment_method`,`o`.`payment_status` AS `payment_status`,`o`.`total_amount` AS `total_amount`,`o`.`total_items` AS `total_items`,`o`.`status` AS `status`,`o`.`created_at` AS `created_at`,`o`.`updated_at` AS `updated_at`,`u`.`name` AS `customer_name`,`u`.`email` AS `customer_email`,`u`.`phone` AS `customer_phone`,count(`oi`.`order_item_id`) AS `total_items_count` from ((`goodwill_vietnam`.`orders` `o` left join `goodwill_vietnam`.`users` `u` on(`o`.`user_id` = `u`.`user_id`)) left join `goodwill_vietnam`.`order_items` `oi` on(`o`.`order_id` = `oi`.`order_id`)) group by `o`.`order_id`
md5=fb593f7cb007a95d39dd7552f8cb42ef
updatable=0
algorithm=0
definer_user=root
definer_host=localhost
suid=2
with_check_option=0
timestamp=0001775209628019416
create-version=2
source=SELECT \n    o.*,\n    u.name AS customer_name,\n    u.email AS customer_email,\n    u.phone AS customer_phone,\n    COUNT(oi.order_item_id) AS total_items_count\nFROM orders o\nLEFT JOIN users u ON o.user_id = u.user_id\nLEFT JOIN order_items oi ON o.order_id = oi.order_id\nGROUP BY o.order_id
client_cs_name=utf8mb4
connection_cl_name=utf8mb4_general_ci
view_body_utf8=select `o`.`order_id` AS `order_id`,`o`.`order_number` AS `order_number`,`o`.`user_id` AS `user_id`,`o`.`shipping_name` AS `shipping_name`,`o`.`shipping_phone` AS `shipping_phone`,`o`.`shipping_address` AS `shipping_address`,`o`.`shipping_place_id` AS `shipping_place_id`,`o`.`shipping_lat` AS `shipping_lat`,`o`.`shipping_lng` AS `shipping_lng`,`o`.`shipping_method` AS `shipping_method`,`o`.`shipping_note` AS `shipping_note`,`o`.`payment_method` AS `payment_method`,`o`.`payment_status` AS `payment_status`,`o`.`total_amount` AS `total_amount`,`o`.`total_items` AS `total_items`,`o`.`status` AS `status`,`o`.`created_at` AS `created_at`,`o`.`updated_at` AS `updated_at`,`u`.`name` AS `customer_name`,`u`.`email` AS `customer_email`,`u`.`phone` AS `customer_phone`,count(`oi`.`order_item_id`) AS `total_items_count` from ((`goodwill_vietnam`.`orders` `o` left join `goodwill_vietnam`.`users` `u` on(`o`.`user_id` = `u`.`user_id`)) left join `goodwill_vietnam`.`order_items` `oi` on(`o`.`order_id` = `oi`.`order_id`)) group by `o`.`order_id`
mariadb-version=100432
