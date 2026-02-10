-- ===============================================================================
-- ADMIN PRIVILEGES
-- ===============================================================================
CREATE USER 'admin'@'%' IDENTIFIED BY 'admin_password';

-- Grant all privileges to admin 
GRANT ALL PRIVILEGES ON pluggedin_itdbadm.* TO 'admin'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;

-- ===============================================================================
-- STAFF PRIVILEGES
-- ===============================================================================
CREATE USER 'staff'@'%' IDENTIFIED BY 'staff_password';

-- Update product stock
GRANT SELECT, UPDATE ON pluggedin_itdbadm.products TO 'staff'@'%';

-- Update order status
GRANT SELECT, UPDATE ON pluggedin_itdbadm.orders TO 'staff'@'%';

-- Assign orders to themselves (staff_assigned_orders table)
GRANT SELECT, INSERT, UPDATE ON pluggedin_itdbadm.staff_assigned_orders TO 'staff'@'%';

-- Need to view users table to verify staff identity
GRANT SELECT ON pluggedin_itdbadm.users TO 'staff'@'%';

-- Need to view categories for product information
GRANT SELECT ON pluggedin_itdbadm.categories TO 'staff'@'%';

-- Need to view order items for order management
GRANT SELECT ON pluggedin_itdbadm.order_items TO 'staff'@'%';

-- Need to view payments for order processing
GRANT SELECT ON pluggedin_itdbadm.payments TO 'staff'@'%';

-- Execute stored procedures related to staff functions
GRANT EXECUTE ON PROCEDURE pluggedin_itdbadm.update_order_status TO 'staff'@'%';
GRANT EXECUTE ON PROCEDURE pluggedin_itdbadm.update_product_stock TO 'staff'@'%';

FLUSH PRIVILEGES;

-- ===============================================================================
-- CUSTOMER/USER PRIVILEGES
-- ===============================================================================
CREATE USER 'customer'@'%' IDENTIFIED BY 'customer_password';

-- View products and categories
GRANT SELECT ON pluggedin_itdbadm.products TO 'customer'@'%';
GRANT SELECT ON pluggedin_itdbadm.categories TO 'customer'@'%';

-- Add to cart
GRANT SELECT, INSERT, UPDATE, DELETE ON pluggedin_itdbadm.cart TO 'customer'@'%';

-- Checkout and create orders
GRANT SELECT, INSERT ON pluggedin_itdbadm.orders TO 'customer'@'%';
GRANT SELECT, INSERT ON pluggedin_itdbadm.order_items TO 'customer'@'%';

-- Pay for orders
GRANT SELECT, INSERT, UPDATE ON pluggedin_itdbadm.payments TO 'customer'@'%';

-- Add to favorites
GRANT SELECT, INSERT, DELETE ON pluggedin_itdbadm.isfavorite TO 'customer'@'%';

-- Edit profile 
GRANT SELECT, UPDATE ON pluggedin_itdbadm.users TO 'customer'@'%';

-- Delete their own account
GRANT DELETE ON pluggedin_itdbadm.users TO 'customer'@'%';

-- Execute stored procedure for account deletion
GRANT EXECUTE ON PROCEDURE pluggedin_itdbadm.delete_customer_account TO 'customer'@'%';

-- View their own orders
GRANT SELECT ON pluggedin_itdbadm.orders TO 'customer'@'%';

-- Change currency (view currencies)
GRANT SELECT ON pluggedin_itdbadm.currencies TO 'customer'@'%';

-- Register (insert new user record)
GRANT INSERT ON pluggedin_itdbadm.users TO 'customer'@'%';

FLUSH PRIVILEGES;
