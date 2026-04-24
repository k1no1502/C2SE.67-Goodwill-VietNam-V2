TYPE=VIEW
query=select (select count(0) from `goodwill_vietnam`.`users` where `goodwill_vietnam`.`users`.`status` = \'active\') AS `total_users`,(select count(0) from `goodwill_vietnam`.`donations` where `goodwill_vietnam`.`donations`.`status` <> \'cancelled\') AS `total_donations`,(select count(0) from `goodwill_vietnam`.`inventory` where `goodwill_vietnam`.`inventory`.`status` = \'available\') AS `total_items`,(select count(0) from `goodwill_vietnam`.`campaigns` where `goodwill_vietnam`.`campaigns`.`status` = \'active\') AS `active_campaigns`,(select count(0) from `goodwill_vietnam`.`transactions` where `goodwill_vietnam`.`transactions`.`type` = \'donation\' and `goodwill_vietnam`.`transactions`.`status` = \'completed\') AS `completed_donations`,(select sum(`goodwill_vietnam`.`transactions`.`amount`) from `goodwill_vietnam`.`transactions` where `goodwill_vietnam`.`transactions`.`type` = \'donation\' and `goodwill_vietnam`.`transactions`.`status` = \'completed\') AS `total_donation_value`
md5=01a7d974d011f783171fb7e4cbf20563
updatable=0
algorithm=0
definer_user=root
definer_host=localhost
suid=2
with_check_option=0
timestamp=0001775209627993359
create-version=2
source=SELECT \n    (SELECT COUNT(*) FROM users WHERE status = \'active\') AS total_users,\n    (SELECT COUNT(*) FROM donations WHERE status != \'cancelled\') AS total_donations,\n    (SELECT COUNT(*) FROM inventory WHERE status = \'available\') AS total_items,\n    (SELECT COUNT(*) FROM campaigns WHERE status = \'active\') AS active_campaigns,\n    (SELECT COUNT(*) FROM transactions WHERE type = \'donation\' AND status = \'completed\') AS completed_donations,\n    (SELECT SUM(amount) FROM transactions WHERE type = \'donation\' AND status = \'completed\') AS total_donation_value
client_cs_name=utf8mb4
connection_cl_name=utf8mb4_general_ci
view_body_utf8=select (select count(0) from `goodwill_vietnam`.`users` where `goodwill_vietnam`.`users`.`status` = \'active\') AS `total_users`,(select count(0) from `goodwill_vietnam`.`donations` where `goodwill_vietnam`.`donations`.`status` <> \'cancelled\') AS `total_donations`,(select count(0) from `goodwill_vietnam`.`inventory` where `goodwill_vietnam`.`inventory`.`status` = \'available\') AS `total_items`,(select count(0) from `goodwill_vietnam`.`campaigns` where `goodwill_vietnam`.`campaigns`.`status` = \'active\') AS `active_campaigns`,(select count(0) from `goodwill_vietnam`.`transactions` where `goodwill_vietnam`.`transactions`.`type` = \'donation\' and `goodwill_vietnam`.`transactions`.`status` = \'completed\') AS `completed_donations`,(select sum(`goodwill_vietnam`.`transactions`.`amount`) from `goodwill_vietnam`.`transactions` where `goodwill_vietnam`.`transactions`.`type` = \'donation\' and `goodwill_vietnam`.`transactions`.`status` = \'completed\') AS `total_donation_value`
mariadb-version=100432
