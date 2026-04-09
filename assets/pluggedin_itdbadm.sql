-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 22, 2025 at 08:04 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

USE `defaultdb`;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pluggedin_itdbadm`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_code` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cart_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`cart_id`, `user_id`, `product_code`, `quantity`, `date_added`) VALUES
(21, 2, 6, 1, '2025-07-22 14:17:34');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_code` int(11) NOT NULL,
  `category_name` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`category_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_code`, `category_name`) VALUES
(1, 'Headphones'),
(2, 'Monitors'),
(3, 'Keyboards'),
(4, 'Mice'),
(5, 'Speakers');

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `currency_code` int(11) NOT NULL,
  `price_php` varchar(45) DEFAULT NULL,
  `currency_name` varchar(45) DEFAULT NULL,
  `symbol` varchar(5) DEFAULT NULL,
  PRIMARY KEY (`currency_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`currency_code`, `price_php`, `currency_name`, `symbol`) VALUES
(1, '0.041', 'KRW', '₩'),
(2, '57.24', 'USD', '$'),
(3, '1', 'PHP', '₱');

-- --------------------------------------------------------

--
-- Table structure for table `customer_deletion_log`
--

CREATE TABLE `customer_deletion_log` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(45) DEFAULT NULL,
  `last_name` varchar(45) DEFAULT NULL,
  `deletion_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_deletion_log`
--

INSERT INTO `customer_deletion_log` (`user_id`, `first_name`, `last_name`, `deletion_date`) VALUES
(6, 'Ella', 'Santos', '2025-07-22 12:38:12'),
(8, 'Delete ', 'This', '2025-07-22 10:53:21'),
(9, 'Delete ', 'This', '2025-07-22 10:53:00');

-- --------------------------------------------------------

--
-- Table structure for table `customer_edit_log`
--

CREATE TABLE `customer_edit_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `old_first_name` varchar(255) DEFAULT NULL,
  `new_first_name` varchar(255) DEFAULT NULL,
  `old_last_name` varchar(255) DEFAULT NULL,
  `new_last_name` varchar(255) DEFAULT NULL,
  `edit_time` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_edit_log`
--

INSERT INTO `customer_edit_log` (`log_id`, `user_id`, `old_first_name`, `new_first_name`, `old_last_name`, `new_last_name`, `edit_time`) VALUES
(1, 2, 'Customer', 'Customer', 'Temp', 'Two', '2025-07-22 20:45:40');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_log`
--

CREATE TABLE `inventory_log` (
  `product_code` int(11) NOT NULL,
  `old_qty` int(11) DEFAULT NULL,
  `new_qty` int(11) DEFAULT NULL,
  `change_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_code`,`change_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_log`
--

INSERT INTO `inventory_log` (`product_code`, `old_qty`, `new_qty`, `change_date`) VALUES
(4, 60, -100, '2025-07-22 10:56:20'),
(4, -100, 100, '2025-07-22 10:59:51'),
(5, 24, -1, '2025-07-22 10:56:01'),
(5, -1, 100, '2025-07-22 11:03:57'),
(5, 100, 99, '2025-07-22 14:36:47'),
(6, 1900, -1, '2025-07-22 10:43:02'),
(6, -1, 100, '2025-07-22 11:04:25'),
(6, 100, 101, '2025-07-22 13:19:42'),
(6, 101, 102, '2025-07-22 18:00:15'),
(8, 1200, 1201, '2025-07-22 11:01:53'),
(10, 800, 799, '2025-07-22 14:36:47'),
(11, 1, 100, '2025-07-21 20:45:23'),
(12, 1, 100, '2025-07-22 11:52:10');

-- --------------------------------------------------------

--
-- Table structure for table `isfavorite`
--

CREATE TABLE `isfavorite` (
  `user_id` int(11) NOT NULL,
  `product_code` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`product_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `isfavorite`
--

INSERT INTO `isfavorite` (`user_id`, `product_code`) VALUES
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 10);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `order_date` date DEFAULT NULL,
  `totalamt_php` float NOT NULL,
  `order_status` varchar(45) DEFAULT NULL,
  `currency_code` int(11) DEFAULT NULL,
  PRIMARY KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `order_date`, `totalamt_php`, `order_status`, `currency_code`) VALUES
(20, 1, '2025-07-21', 12000, 'Delivered', 3),
(24, 1, '2025-07-22', 16000, 'Delivered', 3);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_code` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `srp_php` float DEFAULT NULL,
  `totalprice_php` float DEFAULT NULL,
  PRIMARY KEY (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_code`, `quantity`, `srp_php`, `totalprice_php`) VALUES
(1, 20, 5, 1, 7000, 7000),
(2, 20, 3, 1, 5000, 5000),
(6, 24, 5, 1, 7000, 7000),
(7, 24, 10, 1, 9000, 9000);

-- --------------------------------------------------------

--
-- Table structure for table `order_status_log`
--

CREATE TABLE `order_status_log` (
  `order_id` int(11) NOT NULL,
  `old_status` varchar(45) DEFAULT NULL,
  `new_status` varchar(45) DEFAULT NULL,
  `change_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_id`,`change_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_status_log`
--

INSERT INTO `order_status_log` (`order_id`, `old_status`, `new_status`, `change_date`) VALUES
(20, 'Delivered', 'Shipped', '2025-07-22 10:20:31'),
(20, 'Shipped', 'Processing', '2025-07-22 10:23:45'),
(20, 'Processing', 'Delivered', '2025-07-22 11:32:51'),
(20, 'Delivered', 'Processing', '2025-07-22 11:34:07'),
(20, 'Processing', 'Shipped', '2025-07-22 11:34:27'),
(20, 'Shipped', 'Delivered', '2025-07-22 11:34:43'),
(20, 'Delivered', 'Processing', '2025-07-22 18:01:07'),
(20, 'Processing', 'Delivered', '2025-07-22 18:01:31'),
(24, 'Processing', 'Delivered', '2025-07-22 16:43:45'),
(24, 'Delivered', 'Shipped', '2025-07-22 17:18:04'),
(24, 'Shipped', 'Delivered', '2025-07-22 17:18:26');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `currency_code` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `totalamt_php` float NOT NULL,
  `payment_status` enum('paid','unpaid') NOT NULL,
  `payment_method` enum('card','ewallet','cash') NOT NULL,
  `payment_date` datetime DEFAULT NULL,
  PRIMARY KEY (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `currency_code`, `order_id`, `totalamt_php`, `payment_status`, `payment_method`, `payment_date`) VALUES
(1, 3, 20, 12000, 'unpaid', 'cash', NULL),
(2, 3, 24, 16000, 'unpaid', 'cash', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_code` int(11) NOT NULL AUTO_INCREMENT,
  `category_code` int(11) DEFAULT NULL,
  `product_name` varchar(45) DEFAULT NULL,
  `description` varchar(45) DEFAULT NULL,
  `stock_qty` int(11) DEFAULT NULL,
  `srp_php` float DEFAULT NULL,
  PRIMARY KEY (`product_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_code`, `category_code`, `product_name`, `description`, `stock_qty`, `srp_php`) VALUES
(1, 1, 'Sony WH-1000XM4', 'Noise Cancelling Headphones', 50, 12000),
(2, 2, 'Samsung 27\" Monitor', '4K UHD Display', 30, 15000),
(3, 3, 'Logitech MX Keys', 'Wireless Keyboard', 39, 5000),
(4, 4, 'Razer DeathAdder', 'Gaming Mouse', 100, 3500),
(5, 5, 'JBL Flip 5', 'Portable Bluetooth Speaker', 99, 7000),
(6, 1, 'Airpods Max', 'Wireless Headphones', 102, 35000),
(7, 2, 'LG UltraGear 27GN950', 'Gaming Monitor', 1500, 25000),
(8, 3, 'Corsair K95 RGB Platinum', 'Mechanical Gaming Keyboard', 1201, 8000),
(9, 4, 'Logitech G502 HERO', 'High-Performance Gaming Mouse', 1000, 4000),
(10, 5, 'Bose SoundLink Revolve+', 'Portable Bluetooth Speaker', 799, 9000),
(11, 1, 'test', 'test', 100, 1),
(12, 4, 'test2', 'test2', 100, 1);

-- --------------------------------------------------------

--
-- Table structure for table `product_deletion_log`
--

CREATE TABLE `product_deletion_log` (
  `product_code` int(11) NOT NULL,
  `product_name` varchar(45) DEFAULT NULL,
  `category_code` int(11) DEFAULT NULL,
  `description` varchar(45) DEFAULT NULL,
  `deletion_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_assigned_orders`
--

CREATE TABLE `staff_assigned_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `status` enum('ASSIGNED','COMPLETED') DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_assigned_orders`
--

INSERT INTO `staff_assigned_orders` (`user_id`, `order_id`, `status`) VALUES
(4, 24, 'COMPLETED'),
(4, 20, 'COMPLETED');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_role` varchar(25) NOT NULL,
  `first_name` varchar(45) DEFAULT NULL,
  `last_name` varchar(45) DEFAULT NULL,
  `email` varchar(45) DEFAULT NULL,
  `phone` varchar(45) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `user_role`, `first_name`, `last_name`, `email`, `phone`, `password`, `profile_picture`) VALUES
(1, 'Customer', 'Customer', 'One', 'customer1@gmail.com', '09000000001', '$2y$12$F7AJKgV9z8HlZLX6CE32..TETn57eGZa90mbj2QKJcjyQqvlWFt1u', NULL),
(2, 'Customer', 'Customer', 'Two', 'customer2@gmail.com', '09000000002', '$2y$12$Nt2J86yGNyWcOL6fjK2DE.GOBqq6zDIIM4LO2qmcGgrawPx.CO95K', NULL),
(3, 'Admin', 'Admin', 'One', 'admin1@gmail.com', '09000000004', '$2y$12$6UoJfA5vzf2ILHfAmIeUIuy8L3YBcXSNGW2NRWOxfPht1wTMmy5Sm', NULL),
(4, 'Staff', 'Staff', 'One', 'staff1@gmail.com', '09000000003', '$2y$12$SWtThigB7cfUeACS7RxwBOkzCqxKZpGLK7.ntgYT1fAMJANz3G1sq', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD UNIQUE KEY `unique_user_product` (`user_id`,`product_code`),
  ADD KEY `product_code` (`product_code`);

--
-- Indexes for table `customer_edit_log`
--
ALTER TABLE `customer_edit_log`
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `inventory_log`
--
ALTER TABLE `inventory_log`
  ADD KEY `change_date_idx` (`change_date`);

--
-- Indexes for table `isfavorite`
--
ALTER TABLE `isfavorite`
  ADD KEY `product_code_idx` (`product_code`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD KEY `user_id_idx` (`user_id`),
  ADD KEY `currency_code_idx` (`currency_code`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD KEY `product_code_idx` (`product_code`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_product_code` (`product_code`);

--
-- Indexes for table `order_status_log`
--
ALTER TABLE `order_status_log`
  ADD KEY `change_date_idx` (`change_date`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD KEY `fk_payments_currency_code` (`currency_code`),
  ADD KEY `fk_payments_order_id` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD KEY `category_code_idx` (`category_code`);

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rl_key` varchar(120) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `window_start` int(11) NOT NULL,
  `count` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD UNIQUE KEY `unique_key_ip` (`rl_key`,`ip`);

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD UNIQUE KEY `user_id_UNIQUE` (`user_id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `customer_edit_log`
--
ALTER TABLE `customer_edit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_code` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_code`) REFERENCES `products` (`product_code`);

--
-- Constraints for table `customer_edit_log`
--
ALTER TABLE `customer_edit_log`
  ADD CONSTRAINT `customer_edit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `isfavorite`
--
ALTER TABLE `isfavorite`
  ADD CONSTRAINT `fk_isfavorite_product_code` FOREIGN KEY (`product_code`) REFERENCES `products` (`product_code`),
  ADD CONSTRAINT `fk_isfavorite_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `currency_code` FOREIGN KEY (`currency_code`) REFERENCES `currencies` (`currency_code`),
  ADD CONSTRAINT `user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `fk_order_items_product_code` FOREIGN KEY (`product_code`) REFERENCES `products` (`product_code`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_currency_code` FOREIGN KEY (`currency_code`) REFERENCES `currencies` (`currency_code`),
  ADD CONSTRAINT `fk_payments_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `category_code` FOREIGN KEY (`category_code`) REFERENCES `categories` (`category_code`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
