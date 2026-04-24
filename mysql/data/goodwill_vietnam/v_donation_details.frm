TYPE=VIEW
query=select `d`.`donation_id` AS `donation_id`,`d`.`user_id` AS `user_id`,`d`.`item_name` AS `item_name`,`d`.`description` AS `description`,`d`.`category_id` AS `category_id`,`d`.`quantity` AS `quantity`,`d`.`unit` AS `unit`,`d`.`condition_status` AS `condition_status`,`d`.`estimated_value` AS `estimated_value`,`d`.`images` AS `images`,`d`.`status` AS `status`,`d`.`admin_notes` AS `admin_notes`,`d`.`pickup_address` AS `pickup_address`,`d`.`pickup_date` AS `pickup_date`,`d`.`pickup_time` AS `pickup_time`,`d`.`contact_phone` AS `contact_phone`,`d`.`created_at` AS `created_at`,`d`.`updated_at` AS `updated_at`,`u`.`name` AS `donor_name`,`u`.`email` AS `donor_email`,`u`.`phone` AS `donor_phone`,`c`.`name` AS `category_name`,case when `d`.`status` = \'pending\' then \'Cho duyet\' when `d`.`status` = \'approved\' then \'Da duyet\' when `d`.`status` = \'rejected\' then \'Tu choi\' when `d`.`status` = \'cancelled\' then \'Da huy\' end AS `status_text` from ((`goodwill_vietnam`.`donations` `d` left join `goodwill_vietnam`.`users` `u` on(`d`.`user_id` = `u`.`user_id`)) left join `goodwill_vietnam`.`categories` `c` on(`d`.`category_id` = `c`.`category_id`))
md5=e26ca42a07774b76f8832d89d68fa79b
updatable=0
algorithm=0
definer_user=root
definer_host=localhost
suid=2
with_check_option=0
timestamp=0001775209627998740
create-version=2
source=SELECT \n    d.*,\n    u.name AS donor_name,\n    u.email AS donor_email,\n    u.phone AS donor_phone,\n    c.name AS category_name,\n    CASE \n        WHEN d.status = \'pending\' THEN \'Cho duyet\'\n        WHEN d.status = \'approved\' THEN \'Da duyet\'\n        WHEN d.status = \'rejected\' THEN \'Tu choi\'\n        WHEN d.status = \'cancelled\' THEN \'Da huy\'\n    END AS status_text\nFROM donations d\nLEFT JOIN users u ON d.user_id = u.user_id\nLEFT JOIN categories c ON d.category_id = c.category_id
client_cs_name=utf8mb4
connection_cl_name=utf8mb4_general_ci
view_body_utf8=select `d`.`donation_id` AS `donation_id`,`d`.`user_id` AS `user_id`,`d`.`item_name` AS `item_name`,`d`.`description` AS `description`,`d`.`category_id` AS `category_id`,`d`.`quantity` AS `quantity`,`d`.`unit` AS `unit`,`d`.`condition_status` AS `condition_status`,`d`.`estimated_value` AS `estimated_value`,`d`.`images` AS `images`,`d`.`status` AS `status`,`d`.`admin_notes` AS `admin_notes`,`d`.`pickup_address` AS `pickup_address`,`d`.`pickup_date` AS `pickup_date`,`d`.`pickup_time` AS `pickup_time`,`d`.`contact_phone` AS `contact_phone`,`d`.`created_at` AS `created_at`,`d`.`updated_at` AS `updated_at`,`u`.`name` AS `donor_name`,`u`.`email` AS `donor_email`,`u`.`phone` AS `donor_phone`,`c`.`name` AS `category_name`,case when `d`.`status` = \'pending\' then \'Cho duyet\' when `d`.`status` = \'approved\' then \'Da duyet\' when `d`.`status` = \'rejected\' then \'Tu choi\' when `d`.`status` = \'cancelled\' then \'Da huy\' end AS `status_text` from ((`goodwill_vietnam`.`donations` `d` left join `goodwill_vietnam`.`users` `u` on(`d`.`user_id` = `u`.`user_id`)) left join `goodwill_vietnam`.`categories` `c` on(`d`.`category_id` = `c`.`category_id`))
mariadb-version=100432
