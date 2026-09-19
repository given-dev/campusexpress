-- Add Suspended status to vendors
ALTER TABLE `vendors` MODIFY COLUMN `status` enum('Pending','Approved','Rejected','Suspended') DEFAULT 'Pending';

-- Add index on users email for faster lookups
ALTER TABLE `users` ADD INDEX `idx_users_email` (`email`);

-- Add index on orders customer_email
ALTER TABLE `orders` ADD INDEX `idx_orders_customer_email` (`customer_email`);

-- Add index on orders status
ALTER TABLE `orders` ADD INDEX `idx_orders_status` (`status`);
