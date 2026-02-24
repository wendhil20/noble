-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 24, 2026 at 01:21 AM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u318146187_nh`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`u318146187_nh`@`127.0.0.1` PROCEDURE `record_order_as_sold` (IN `p_order_id` INT)   BEGIN
    DECLARE v_sold_order_id INT;
    DECLARE v_order_exists INT;
    DECLARE v_error_msg VARCHAR(255);
    
    -- NO transaction control here — let caller handle it
    -- or rely on the trigger's implicit transaction
    
    -- Check if order exists and has valid status
    SELECT COUNT(*) INTO v_order_exists 
    FROM orders 
    WHERE id = p_order_id 
    AND status IN ('Delivered', 'Picked Up', 'delivered', 'picked_up');
    
    IF v_order_exists = 0 THEN
        SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Order not found or not in delivered/picked up status';
    END IF;
    
    -- Check if already recorded as sold
    SELECT COUNT(*) INTO v_order_exists 
    FROM sold_orders 
    WHERE order_id = p_order_id;
    
    IF v_order_exists > 0 THEN
        SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Order already recorded as sold';
    END IF;
    
    -- Insert into sold_orders
    INSERT INTO sold_orders (
        order_id, user_id, emp_id, warehouse_employee_id,
        customer_name, email, mobile,
        subtotal, discount, shipping_fee, delivery_fee, vat_amount, total, final_total,
        address, zipcode, latitude, longitude, delivery_distance, delivery_type,
        assigned_vehicle_id, assigned_vehicle_type,
        total_cubic_meters, total_weight_kg, total_width, total_height, total_length,
        mode_payment, payment_status,
        reference_no, reference_number, verified_by,
        paymongo_session_id, billing_address_id,
        order_date, confirmed_at, completed_at, delivered_at
    )
    SELECT 
        id, user_id, emp_id, warehouse_employee_id,
        customer_name, email, mobile,
        subtotal, discount, shipping_fee, delivery_fee, vat_amount, total, final_total,
        address, zipcode, latitude, longitude, delivery_distance, delivery_type,
        assigned_vehicle_id, assigned_vehicle_type,
        total_cubic_meters, total_weight_kg, total_width, total_height, total_length,
        mode_payment, payment_status,
        reference_no, reference_number, verified_by,
        paymongo_session_id, billing_address_id,
        created_at, confirmed_at, completed_at, NOW()
    FROM orders
    WHERE id = p_order_id;
    
    SET v_sold_order_id = LAST_INSERT_ID();
    
    -- Insert into sold_items
    INSERT INTO sold_items (
        sold_order_id, original_order_item_id, order_id,
        product_id, product_name, codename, type_name, variant_color, size,
        price, quantity, subtotal, delivery_fee_per_item, item_total_delivery,
        descrip6, descrip7, origin,
        supplier_id, manual_supplier_name, po_number,
        qr_code, warehouse_location,
        lt_from, lt_to
    )
    SELECT 
        v_sold_order_id, id, order_id,
        product_id, product_name, codename, type_name, variant_color, size,
        price, quantity, subtotal, delivery_fee_per_item, item_total_delivery,
        descrip6, descrip7, origin,
        supplier_id, manual_supplier_name, po_number,
        qr_code, warehouse_location,
        lt_from, lt_to
    FROM order_items
    WHERE order_id = p_order_id;

END$$

CREATE DEFINER=`u318146187_nh`@`127.0.0.1` PROCEDURE `update_expired_timer_discounts` ()   BEGIN
    UPDATE product_variants 
    SET timer_discount_active = 0 
    WHERE timer_discount_active = 1 
    AND timer_discount_end < NOW();
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `accountantrecord`
--

CREATE TABLE `accountantrecord` (
  `id` int(11) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `particular` text NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `forms` enum('Expense','Sale') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `adminsuppliers`
--

CREATE TABLE `adminsuppliers` (
  `id` int(11) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_inspiration`
--

CREATE TABLE `admin_inspiration` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(100) NOT NULL,
  `main_image` varchar(255) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `image_1` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`image_1`)),
  `description_image_1` text DEFAULT NULL,
  `image_2` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`image_2`)),
  `description_image_2` text DEFAULT NULL,
  `image_3` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`image_3`)),
  `description_image_3` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('on','off') DEFAULT 'on'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `icon_class` varchar(100) NOT NULL,
  `color_class` varchar(100) NOT NULL,
  `target_admin_id` int(11) DEFAULT NULL,
  `target_role` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_notification_actions_log`
--

CREATE TABLE `admin_notification_actions_log` (
  `id` int(11) NOT NULL,
  `notification_history_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `performed_by_name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_notification_history`
--

CREATE TABLE `admin_notification_history` (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `admin_name` varchar(255) DEFAULT NULL,
  `notification_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `action` varchar(50) DEFAULT NULL,
  `action_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`action_details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `user_level` varchar(50) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `order_item_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_trail`
--

INSERT INTO `audit_trail` (`id`, `user_id`, `user_name`, `user_level`, `action_type`, `table_name`, `record_id`, `order_id`, `order_item_id`, `old_value`, `new_value`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 12, 'receiver_warehouse', 'warehouse', 'UPDATE_TRACKING_STATUS', 'order_items', 46, 44, 46, 'processing', 'In Warehouse', 'Updated tracking status from \'processing\' to \'In Warehouse\'', '124.104.82.28', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-21 05:38:48'),
(2, 12, 'receiver_warehouse', 'warehouse', 'UPDATE_WAREHOUSE_LOCATION', 'order_items', 46, 44, 46, 'Not Set', 'nasa conference', 'Updated warehouse location from \'Not Set\' to \'nasa conference\' for order item', '124.104.82.28', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-21 05:38:53'),
(3, 12, 'receiver_warehouse', 'warehouse', 'UPDATE_TRACKING_STATUS', 'order_items', 47, 44, 47, 'processing', 'In Warehouse', 'Updated tracking status from \'processing\' to \'In Warehouse\'', '124.104.82.28', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-21 05:39:12'),
(4, 12, 'receiver_warehouse', 'warehouse', 'UPDATE_WAREHOUSE_LOCATION', 'order_items', 47, 44, 47, 'Not Set', 'nasa conference', 'Updated warehouse location from \'Not Set\' to \'nasa conference\' for order item', '124.104.82.28', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-21 05:39:16'),
(5, 8, 'logistic', 'logistic', 'CREATE_DELIVERY_BOOKING', 'delivery_bookings', 7, 44, NULL, NULL, '{\"booking_type\":\"delivery\",\"tracking_number\":\"fwqr\",\"courier_name\":\"Default Courier\",\"pickup_person\":null,\"pickup_contact\":null,\"driver_name\":\"wrtwet\",\"vehicle_plate\":\"WTWT\"}', 'Created Delivery booking with tracking #fwqr', '124.104.82.28', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-21 05:41:55'),
(6, 8, 'logistic', 'logistic', 'ASSIGN_DISPATCHER', 'delivery_bookings', 7, 44, NULL, 'Unassigned', 'Dispatcher_logistic', 'Assigned dispatcher: Dispatcher_logistic (was: Unassigned)', '124.104.82.28', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-21 05:42:03'),
(7, 8, 'logistic', 'logistic', 'UPLOAD_DELIVERY_PROOF', 'delivery_bookings', 7, 44, NULL, '{\"old_status\":\"in_transit\",\"proof_image\":null}', '{\"new_status\":\"delivered\",\"proof_image\":\"proof_7_1763703854.webp\"}', 'Delivery proof uploaded and status updated from \'in_transit\' to \'delivered\'', '124.104.82.28', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-21 05:44:14'),
(8, 12, 'receiver_warehouse', 'warehouse', 'UPDATE_TRACKING_STATUS', 'order_items', 56, 50, 56, 'processing', 'In Warehouse', 'Updated tracking status from \'processing\' to \'In Warehouse\'', '124.104.82.28', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-22 03:03:00'),
(9, 12, 'receiver_warehouse', 'warehouse', 'UPDATE_WAREHOUSE_LOCATION', 'order_items', 56, 50, 56, 'Not Set', 'confksjf', 'Updated warehouse location from \'Not Set\' to \'confksjf\' for order item', '124.104.82.28', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-22 03:03:08'),
(10, 8, 'logistic', 'logistic', 'CREATE_DELIVERY_BOOKING', 'delivery_bookings', 8, 50, NULL, NULL, '{\"booking_type\":\"delivery\",\"tracking_number\":\"fsdfs\",\"courier_name\":\"Default Courier\",\"pickup_person\":null,\"pickup_contact\":null,\"driver_name\":\"efaefea\",\"vehicle_plate\":\"FASE\"}', 'Created Delivery booking with tracking #fsdfs', '124.104.82.28', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-22 03:04:52'),
(11, 8, 'logistic', 'logistic', 'ASSIGN_DISPATCHER', 'delivery_bookings', 8, 50, NULL, 'Unassigned', 'Dispatcher_logistic', 'Assigned dispatcher: Dispatcher_logistic (was: Unassigned)', '124.104.82.28', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-22 03:04:58'),
(12, 8, 'logistic', 'logistic', 'UPLOAD_DELIVERY_PROOF', 'delivery_bookings', 8, 50, NULL, '{\"old_status\":\"in_transit\",\"proof_image\":null}', '{\"new_status\":\"delivered\",\"proof_image\":\"proof_8_1763780756.webp\"}', 'Delivery proof uploaded and status updated from \'in_transit\' to \'delivered\'', '124.104.82.28', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-22 03:05:56'),
(13, 12, 'receiver_warehouse', 'warehouse', 'UPDATE_WAREHOUSE_LOCATION', 'order_items', 62, 56, 62, 'Not Set', 'shelter1', 'Updated warehouse location from \'Not Set\' to \'shelter1\' for order item', '2001:4451:138c:d00:fc32:a8a7:ce86:d5aa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-09 00:00:13'),
(14, 12, 'receiver_warehouse', 'warehouse', 'UPDATE_TRACKING_STATUS', 'order_items', 62, 56, 62, 'processing', 'In Warehouse', 'Updated tracking status from \'processing\' to \'In Warehouse\' and marked as received', '2001:4451:138c:d00:fc32:a8a7:ce86:d5aa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-09 00:04:03'),
(15, 12, 'receiver_warehouse', 'warehouse', 'MARK_ITEM_RECEIVED', 'order_items', 62, 56, 62, 'pending', 'received', 'Item marked as received in warehouse by user ID: 12', '2001:4451:138c:d00:fc32:a8a7:ce86:d5aa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-09 00:04:03'),
(16, 8, 'logistic', 'logistic', 'CREATE_DELIVERY_BOOKING', 'delivery_bookings', 9, 56, NULL, NULL, '{\"booking_type\":\"delivery\",\"tracking_number\":\"09128390234901236\",\"courier_name\":\"Default Courier\",\"pickup_person\":null,\"pickup_contact\":null,\"driver_name\":\"mark jameasmakeikm akioer\",\"vehicle_plate\":\"12132\"}', 'Created Delivery booking with tracking #09128390234901236', '2001:4451:138c:d00:fc32:a8a7:ce86:d5aa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-09 00:11:56'),
(17, 8, 'logistic', 'logistic', 'ASSIGN_DISPATCHER', 'delivery_bookings', 9, 56, NULL, 'Unassigned', 'Dispatcher_logistic', 'Assigned dispatcher: Dispatcher_logistic (was: Unassigned)', '2001:4451:138c:d00:fc32:a8a7:ce86:d5aa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-09 00:12:11'),
(18, 8, 'logistic', 'logistic', 'UPLOAD_DELIVERY_PROOF', 'delivery_bookings', 9, 56, NULL, '{\"old_status\":\"in_transit\",\"proof_image\":null}', '{\"new_status\":\"delivered\",\"proof_image\":\"proof_9_1767917765.webp\"}', 'Delivery proof uploaded and status updated from \'in_transit\' to \'delivered\'', '2001:4451:138c:d00:fc32:a8a7:ce86:d5aa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-09 00:16:05'),
(19, 12, 'receiver_warehouse', 'warehouse', 'UPDATE_WAREHOUSE_LOCATION', 'order_items', 6, 6, 6, 'Not Set', 'ware 1', 'Updated warehouse location from \'Not Set\' to \'ware 1\' for order item', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 06:14:54'),
(20, 12, 'receiver_warehouse', 'warehouse', 'UPDATE_TRACKING_STATUS', 'order_items', 6, 6, 6, 'processing', 'In Warehouse', 'Updated tracking status from \'processing\' to \'In Warehouse\' and marked as received', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 06:18:26'),
(21, 12, 'receiver_warehouse', 'warehouse', 'MARK_ITEM_RECEIVED', 'order_items', 6, 6, 6, 'pending', 'received', 'Item marked as received in warehouse by user ID: 12', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 06:18:26'),
(22, 8, 'logistic', 'logistic', 'CREATE_DELIVERY_BOOKING', 'delivery_bookings', 10, 6, NULL, NULL, '{\"booking_type\":\"pickup\",\"tracking_number\":\"09128390234901236\",\"courier_name\":\"Customer Pickup\",\"pickup_person\":\"markjamesulo\",\"pickup_contact\":\"123\",\"driver_name\":null,\"vehicle_plate\":\"12132\"}', 'Created Pickup booking with tracking #09128390234901236', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 06:20:32'),
(23, 8, 'logistic', 'logistic', 'ASSIGN_DISPATCHER', 'delivery_bookings', 10, 6, NULL, 'Unassigned', 'Dispatcher_logistic', 'Assigned dispatcher: Dispatcher_logistic (was: Unassigned)', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 06:20:39'),
(24, 8, 'logistic', 'logistic', 'UPLOAD_DELIVERY_PROOF', 'delivery_bookings', 10, 6, NULL, '{\"old_status\":\"in_transit\",\"proof_image\":null}', '{\"new_status\":\"picked_up\",\"proof_image\":\"proof_10_1771827793.webp\"}', 'Pickup proof uploaded and status updated from \'in_transit\' to \'picked_up\'', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 06:23:14'),
(25, 12, 'receiver_warehouse', 'warehouse', 'UPDATE_WAREHOUSE_LOCATION', 'order_items', 7, 7, 7, 'Not Set', 'hello', 'Updated warehouse location from \'Not Set\' to \'hello\' for order item', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 07:10:46'),
(26, 12, 'receiver_warehouse', 'warehouse', 'UPDATE_TRACKING_STATUS', 'order_items', 7, 7, 7, 'processing', 'In Warehouse', 'Updated tracking status from \'processing\' to \'In Warehouse\' and marked as received', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 07:12:12'),
(27, 12, 'receiver_warehouse', 'warehouse', 'MARK_ITEM_RECEIVED', 'order_items', 7, 7, 7, 'pending', 'received', 'Item marked as received in warehouse by user ID: 12', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 07:12:12'),
(28, 8, 'logistic', 'logistic', 'CREATE_DELIVERY_BOOKING', 'delivery_bookings', 11, 7, NULL, NULL, '{\"booking_type\":\"delivery\",\"tracking_number\":\"jhghguygyib\",\"courier_name\":\"Default Courier\",\"pickup_person\":null,\"pickup_contact\":null,\"driver_name\":\"fasfsf\",\"vehicle_plate\":\"FSAFASF\"}', 'Created Delivery booking with tracking #jhghguygyib', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 07:14:27'),
(29, 8, 'logistic', 'logistic', 'ASSIGN_DISPATCHER', 'delivery_bookings', 11, 7, NULL, 'Unassigned', 'Dispatcher_logistic', 'Assigned dispatcher: Dispatcher_logistic (was: Unassigned)', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 07:15:06'),
(30, 8, 'logistic', 'logistic', 'UPLOAD_DELIVERY_PROOF', 'delivery_bookings', 11, 7, NULL, '{\"old_status\":\"in_transit\",\"proof_image\":null}', '{\"new_status\":\"delivered\",\"proof_image\":\"proof_11_1771830951.webp\"}', 'Delivery proof uploaded and status updated from \'in_transit\' to \'delivered\'', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 07:15:52'),
(31, 8, 'logistic', 'logistic', 'UPLOAD_DELIVERY_PROOF', 'delivery_bookings', 11, 7, NULL, '{\"old_status\":\"delivered\",\"proof_image\":null}', '{\"new_status\":\"delivered\",\"proof_image\":\"proof_11_1771830958.webp\"}', 'Delivery proof uploaded and status updated from \'delivered\' to \'delivered\'', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 07:15:58'),
(32, 8, 'logistic', 'logistic', 'UPLOAD_DELIVERY_PROOF', 'delivery_bookings', 11, 7, NULL, '{\"old_status\":\"delivered\",\"proof_image\":null}', '{\"new_status\":\"delivered\",\"proof_image\":\"proof_11_1771831181.webp\"}', 'Delivery proof uploaded and status updated from \'delivered\' to \'delivered\'', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 07:19:41'),
(33, 8, 'logistic', 'logistic', 'UPLOAD_DELIVERY_PROOF', 'delivery_bookings', 11, 7, NULL, '{\"old_status\":\"delivered\",\"proof_image\":null}', '{\"new_status\":\"delivered\",\"proof_image\":\"proof_11_1771831536.webp\"}', 'Delivery proof uploaded and status updated from \'delivered\' to \'delivered\'', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 07:25:36'),
(34, 8, 'logistic', 'logistic', 'UPLOAD_DELIVERY_PROOF', 'delivery_bookings', 11, 7, NULL, '{\"old_status\":\"delivered\",\"proof_image\":null}', '{\"new_status\":\"delivered\",\"proof_image\":\"proof_11_1771831681.webp\"}', 'Delivery proof uploaded and status updated from \'delivered\' to \'delivered\'', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 07:28:02'),
(35, 8, 'logistic', 'logistic', 'UPLOAD_DELIVERY_PROOF', 'delivery_bookings', 11, 7, NULL, '{\"old_status\":\"delivered\",\"proof_image\":null}', '{\"new_status\":\"delivered\",\"proof_image\":\"proof_11_1771832977.webp\"}', 'Delivery proof uploaded and status updated from \'delivered\' to \'delivered\'', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 07:49:37'),
(36, 8, 'logistic', 'logistic', 'UPLOAD_DELIVERY_PROOF', 'delivery_bookings', 11, 7, NULL, '{\"old_status\":\"delivered\",\"proof_image\":null}', '{\"new_status\":\"delivered\",\"proof_image\":\"proof_11_1771890921.webp\"}', 'Delivery proof uploaded and status updated from \'delivered\' to \'delivered\'', '2001:4451:137a:3200:39f0:fec8:6e3c:f0a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 23:55:22'),
(37, 8, 'logistic', 'logistic', 'UPLOAD_DELIVERY_PROOF', 'delivery_bookings', 11, 7, NULL, '{\"old_status\":\"delivered\",\"proof_image\":null}', '{\"new_status\":\"delivered\",\"proof_image\":\"proof_11_1771891193.webp\"}', 'Delivery proof uploaded and status updated from \'delivered\' to \'delivered\'', '2001:4451:137a:3200:39f0:fec8:6e3c:f0a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 23:59:55'),
(38, 8, 'logistic', 'logistic', 'UPLOAD_DELIVERY_PROOF', 'delivery_bookings', 11, 7, NULL, '{\"old_status\":\"delivered\",\"proof_image\":null}', '{\"new_status\":\"delivered\",\"proof_image\":\"proof_11_1771892024.webp\"}', 'Delivery proof uploaded and status updated from \'delivered\' to \'delivered\'', '2001:4451:137a:3200:39f0:fec8:6e3c:f0a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:13:45'),
(39, 8, 'logistic', 'logistic', 'UPLOAD_DELIVERY_PROOF', 'delivery_bookings', 11, 7, NULL, '{\"old_status\":\"delivered\",\"proof_image\":null}', '{\"new_status\":\"delivered\",\"proof_image\":\"proof_11_1771892379.webp\"}', 'Delivery proof uploaded and status updated from \'delivered\' to \'delivered\'', '2001:4451:137a:3200:39f0:fec8:6e3c:f0a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:19:41'),
(40, 8, 'logistic', 'logistic', 'UPLOAD_DELIVERY_PROOF', 'delivery_bookings', 11, 7, NULL, '{\"old_status\":\"delivered\",\"proof_image\":null}', '{\"new_status\":\"delivered\",\"proof_image\":\"proof_11_1771892858.webp\"}', 'Delivery proof uploaded and status updated from \'delivered\' to \'delivered\'', '2001:4451:137a:3200:39f0:fec8:6e3c:f0a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 00:27:39');

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `banner_title` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `banner_link` varchar(500) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bestseller`
--

CREATE TABLE `bestseller` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bestseller`
--

INSERT INTO `bestseller` (`id`, `title`, `slug`, `description`, `image`, `product_id`, `created_at`) VALUES
(1, 'aac block', 'aac-block', 'AAC Blocks (Autoclaved Aerated Concrete Blocks) are lightweight, durable, and eco-friendly building materials designed for modern construction. Made from a mixture of cement, lime, sand, water, and a small amount of aluminum powder, these blocks are cured under high-pressure steam in an autoclave, which gives them their unique cellular structure.', '../../uploads/1759478965_Untitled design.png', 23, '2025-10-03 08:09:25'),
(2, 'FIBER CEMENT BOARD', 'fiber-cement-board', 'Durable, versatile, and eco-friendly — the Eco-Flex Fiber Cement Board is designed to meet modern construction needs with superior strength and sustainability. Made from a blend of high-quality cement, cellulose fibers, and natural minerals, it offers excellent resistance to moisture, fire, and termites, making it ideal for both interior and exterior applications.', '../../uploads/1760602343_sdahusdsduhhusaduhhudwwawdaywadydaygsda.webp', 24, '2025-10-16 08:12:23'),
(3, 'MATTE TILES', 'matte-tiles', 'Matte tiles feature a smooth, non-reflective surface that brings a natural and elegant look to any space. Their subtle finish offers a refined texture that’s easy on the eyes and ideal for creating a modern, sophisticated atmosphere. Perfect for floors and walls, matte tiles provide excellent slip resistance, making them a practical choice for bathrooms, kitchens, and high-traffic areas. Durable, low-maintenance, and timeless in design, these tiles effortlessly blend functionality with understated beauty—perfect for achieving a warm, contemporary feel in your home or business.', '../../uploads/1760657650_yeyeeyeyeyeysad.webp', 26, '2025-10-16 23:34:10'),
(4, 'POLISHED TILES', 'polished-tiles', 'Polished tiles are high-quality ceramic or porcelain tiles characterized by their smooth, reflective surface achieved through a specialized polishing process. These tiles are widely used in both residential and commercial applications for their elegant appearance and long-lasting durability.\r\n\r\nThey feature a high-gloss finish that enhances the brightness and spaciousness of any interior space. Polished tiles are resistant to stains, scratches, and moisture, making them ideal for areas that require both aesthetic appeal and easy maintenance.', '../../uploads/1760659131_police.webp', 25, '2025-10-16 23:58:51');

-- --------------------------------------------------------

--
-- Table structure for table `bestsellertwo`
--

CREATE TABLE `bestsellertwo` (
  `id` int(11) NOT NULL,
  `bestseller_id` int(11) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `extra` longtext DEFAULT NULL CHECK (json_valid(`extra`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bestsellertwo`
--

INSERT INTO `bestsellertwo` (`id`, `bestseller_id`, `subtitle`, `content`, `image`, `extra`, `created_at`) VALUES
(1, 1, 'AAC Block', 'AAC Blocks are lightweight, durable, and eco-friendly building materials made from autoclaved aerated concrete. They provide excellent insulation, fire resistance, and easy workability, making construction faster, stronger, and more cost-efficient compared to traditional bricks.', '[\"..\\/..\\/uploads\\/1759479036_image (1).png\",\"..\\/..\\/uploads\\/1759479042_image.png\",\"..\\/..\\/uploads\\/1759479049_eyeysi - Edited.png\"]', NULL, '2025-10-03 08:10:36'),
(2, 2, 'FIBER CEMENT BOARD', 'Eco Flex Fiber Cement Board is a high-quality, durable building material engineered for superior performance and reliability. Manufactured using a precise blend of cement, cellulose fibers, and other reinforcing materials, it offers exceptional strength, dimensional stability, and resistance to fire, moisture, and termites.\r\n\r\nDesigned to meet modern construction standards, Eco Flex Fiber Cement Board provides a smooth and stable surface suitable for various finishing applications such as painting, tiling, or wallpaper installation. It is widely used for wall partitions, ceilings, eaves, and exterior cladding, both in residential and commercial projects.\r\n\r\nEco Flex stands out for its eco-friendly composition, long service life, and minimal maintenance requirements—making it a sustainable and cost-efficient alternative to traditional walling and ceiling materials. Its versatility and dependable performance make it an ideal choice for architects, contractors, and developers seeking quality and sustainability in every build.', '[]', NULL, '2025-10-17 00:56:19'),
(3, 4, 'POLISHED TILES', 'Polished Tiles are premium-grade ceramic or porcelain tiles manufactured through an advanced polishing process that creates a smooth, reflective surface with a high-gloss finish. These tiles combine aesthetic elegance with functional durability, making them suitable for a wide range of interior applications.\r\n\r\nTheir refined surface enhances light reflection, giving spaces a bright and sophisticated appearance. Polished tiles are resistant to stains, scratches, and moisture, ensuring long-lasting quality and easy maintenance.\r\n\r\nCommonly used for floors and walls in residential, commercial, and institutional projects, Polished Tiles are available in various sizes, colors, and patterns to complement both classic and contemporary designs. Their combination of strength, elegance, and versatility makes them an excellent choice for projects that demand both style and performance.', '[]', NULL, '2025-10-17 00:57:34'),
(4, 3, 'MATTE TILES', 'Matte Tiles are high-quality ceramic or porcelain tiles distinguished by their non-reflective, smooth surface finish that provides a natural and understated appearance. Designed for both aesthetic appeal and practicality, matte tiles offer excellent slip resistance and are well-suited for areas that require a subtle, elegant look with functional durability.\r\n\r\nTheir surface texture minimizes the appearance of water spots, smudges, and dirt, making them ideal for high-traffic and moisture-prone areas such as bathrooms, kitchens, and outdoor spaces. Matte Tiles are also favored for their low maintenance requirements and ability to blend seamlessly with modern and minimalist interior designs.\r\n\r\nAvailable in a wide range of sizes, textures, and color options, Matte Tiles provide versatility for both residential and commercial applications—offering a perfect balance of style, safety, and long-term performance.', '[]', NULL, '2025-10-17 00:57:56');

-- --------------------------------------------------------

--
-- Table structure for table `billing_addresses`
--

CREATE TABLE `billing_addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'Philippines',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_addresses`
--

INSERT INTO `billing_addresses` (`id`, `user_id`, `full_name`, `phone`, `address`, `city`, `state`, `postal_code`, `country`, `latitude`, `longitude`, `is_active`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'BSIT4107_HIMARANGAN Wendhil', '+63 908 103 1241', '128 sitio pajo', 'quezon city', 'Metro Manila', '1115', 'Philippines', 14.64263300, 120.99523710, 0, 'test', '2025-07-24 07:42:20', '2025-07-24 07:42:20'),
(2, 2, 'Wendhil himarangan', '+63 908 103 1241', '128 sitio pajo', 'quezon city', 'Metro Manila', '1109', 'Philippines', 14.62089090, 121.06139360, 0, 'hwg whgw ', '2025-08-02 04:04:20', '2025-08-02 04:04:20'),
(3, 3, 'Mark James', '0967 167 7760', 'Camarin', 'Caloocan', 'Metro Manila', '1423', 'Philippines', 14.75621790, 121.06443220, 0, 'malapit sa bahay', '2025-08-05 02:05:06', '2025-08-05 02:05:06'),
(4, 6, 'NobleHome', '+63 910 834 6508', '1181 MC Premier Balintawak', 'Quezon City', 'Metro Manila', '1106', 'Philippines', 14.66418740, 121.01036620, 0, '', '2025-08-09 02:52:00', '2025-08-09 02:52:00'),
(5, 4, 'BSIT 4107-Salvadora, Mark James F.', '+63 961 716 7776', 'Camarin', 'Caloocan', 'Metro Manila', '1427', 'Philippines', 14.75733670, 121.06647930, 0, 'malapit sa bahay', '2025-08-18 02:53:22', '2025-08-18 02:53:22'),
(6, 5, 'Lion King', '+63 967 167 7760', 'Quezon Boulevard Quiapo', 'Manila', 'Metro Manila', '1001', 'Philippines', 14.59925770, 120.98411700, 0, 'rwfga', '2025-08-20 03:30:37', '2025-08-20 03:30:37'),
(7, 6, 'blue dragon', '+63 967 136 7760', 'Santol', 'Silang', 'Cavite', '4118', 'Philippines', 14.16098870, 120.95448770, 0, 'namo', '2025-08-26 05:29:15', '2025-08-26 05:29:15'),
(10, 7, 'Lion King', '967 167 7760', 'Camarin Purok 7 Sitio 4 Pechayan Dulo', 'Caloocan', 'Metro Manila', '1427', 'Philippines', 14.75737180, 121.06655540, 0, 'basta malapit toh samin', '2025-09-01 01:13:21', '2025-09-01 01:13:21'),
(11, 7, 'Lion King', '967 167 7760', '432 A. Bautista Street Quiapo', 'Manila', 'Metro Manila', '1001', 'Philippines', 14.59843420, 120.98472930, 0, 'sfasfsa', '2025-09-01 01:15:45', '2025-09-01 01:15:45'),
(12, 7, 'Lion King', '967 167 7760', 'Old Samson Road Balintawak', 'Quezon City', 'Metro Manila', '1106', 'Philippines', 14.65672200, 121.00339690, 0, 'jdzfvjgdjv', '2025-09-01 01:24:28', '2025-09-01 01:24:28'),
(13, 7, 'Lion King', '967 167 7760', 'Porvenir Street Quiapo', 'Manila', 'Metro Manila', '1001', 'Philippines', 14.60169490, 120.98404300, 0, 'fasfda', '2025-09-01 01:25:24', '2025-09-01 01:25:24'),
(14, 7, 'Lion King', '+63 967 167 7760', 'Carlos Palanca Street Quiapo', 'Manila', 'Metro Manila', '1001', 'Philippines', 14.59850450, 120.98190840, 0, 'faasfjsakf', '2025-09-01 03:52:12', '2025-09-01 03:52:12'),
(15, 4, 'BSIT 4107-Salvadora, Mark James F.', '+63 967 167 7760', '916 F. R. Hidalgo Street Quiapo', 'Manila', 'Metro Manila', '1001', 'Philippines', 14.59921060, 120.98631560, 0, 'asfafsadf', '2025-09-01 04:24:23', '2025-09-01 04:24:23'),
(16, 8, 'Jb Sy', '+63 9851 245 929', 'A. Palon Street Grace Park West', 'Caloocan', 'Metro Manila', '1406', 'Philippines', 14.65532050, 120.97922190, 0, '', '2025-09-02 23:33:36', '2025-09-02 23:33:36'),
(17, 8, 'Jb Sy', '+63 9475 195 096', 'Cavite', 'silang', 'Cavite', '2100', 'Philippines', 14.25540730, 120.86715030, 0, '', '2025-09-03 07:38:42', '2025-09-03 07:38:42'),
(18, 9, 'Froilan Linga Bawag', '+63 9155 919 182', 'MALVAR ST.', 'QUEZON CITY', 'metro manila', '1117', 'Philippines', 14.70552280, 121.07428210, 0, 'aaaaa', '2025-09-03 23:38:02', '2025-09-03 23:38:02'),
(19, 9, 'Froilan Linga Bawag', '+63 9155 919 182', 'San Roque Street 11', 'Catbalogan', 'Samar', '6700', 'Philippines', 11.77743310, 124.88318130, 0, 'aaaa', '2025-09-03 23:39:42', '2025-09-03 23:39:42'),
(20, 10, 'Christine', '+63 9935 487 799', 'EDSA Balintawak', 'Quezon City', 'Metro Manila', '1106', 'Philippines', 14.65742210, 121.00389590, 0, '', '2025-09-06 01:50:03', '2025-09-06 01:50:03'),
(21, 11, 'Mary Grace Rivera', '+63 9382 041 746', '918 Alvarado Street Binondo', 'Manila', 'Metro Manila', '1006', 'Philippines', 14.60366550, 120.97514010, 1, '', '2025-09-08 08:13:46', '2026-02-19 05:05:52'),
(22, 4, 'BSIT 4107-Salvadora, Mark James F.', '+63 9671 677 760', 'Ricardo Gawan Village', 'General Santos', 'Soccsksargen', '9500', 'Philippines', 6.07488160, 125.08981300, 0, 'fhfs', '2025-09-16 03:08:19', '2025-09-16 03:08:19'),
(23, 1, 'BSIT4107_HIMARANGAN Wendhil', '+63 9081 031 241', 'Purok 2', 'Paracale', 'Camarines Norte', '4605', 'Philippines', 14.24373330, 122.76354210, 0, '', '2025-09-16 03:19:25', '2025-09-16 03:19:25'),
(24, 3, 'NobleHome', '+63 9565 466 445', 'Mahilum', 'Hindang', 'Leyte', '6523', 'Philippines', 10.48229490, 124.83609930, 0, '', '2025-09-16 03:45:21', '2025-09-16 03:45:21'),
(25, 16, 'BSIT 4107-Salvadora, Mark James F.', '+63 9671 677 760', 'Malapatan', 'Malapatan', 'Sarangani', '9516', 'Philippines', 5.97075120, 125.28827170, 0, 'bhjg', '2025-09-16 06:14:15', '2025-09-16 06:14:15'),
(26, 1, 'BSIT4107_HIMARANGAN Wendhil', '+63 9546 464 564', 'Port Mangingisda Mangingisda', 'Puerto Princesa', 'Mimaropa', '5300', 'Philippines', 9.68673070, 118.75106890, 0, '', '2025-09-16 06:27:23', '2025-09-16 06:27:23'),
(28, 2, 'NHCC Marketing', '+63 9281 985 985', 'Nueva Ecija', 'Ormoc', 'Nueva Ecija', '1111', 'Philippines', 15.58333300, 121.00000000, 0, 'NYANYANYANYA', '2025-09-30 06:01:37', '2025-09-30 06:01:37'),
(29, 2, 'NHCC Marketing', '+63 9281 985 985', 'Davao City', 'Davao City', 'Davao Region', '1111', 'Philippines', 7.06483060, 125.60806230, 0, 'sadsadsaasdas', '2025-09-30 06:03:18', '2025-09-30 06:03:18'),
(30, 2, 'NHCC Marketing', '+63 9281 985 985', 'San Pedro Street Poblacion District', 'Davao City', 'Davao Region', '8000', 'Philippines', 7.06574990, 125.60814100, 0, '', '2025-09-30 06:04:04', '2025-09-30 06:04:04'),
(31, 16, 'BSIT 4107-Salvadora, Mark James F.', '+63 9671 677 760', 'Camarin Road Camarin', 'Caloocan', 'Metro Manila', '1422', 'Philippines', 14.75673800, 121.05800930, 0, 'fafaf', '2025-10-07 07:35:38', '2025-10-07 07:35:38'),
(32, 16, 'BSIT 4107-Salvadora, Mark James F.', '+63 9671 677 760', 'Sitio Pajo', 'Quezon City', 'Metro Manila', '1106', 'Philippines', 14.66360500, 121.01469510, 0, 'eqeqe', '2025-10-10 08:46:19', '2025-10-10 08:46:19'),
(33, 31, 'Wenswens Himars', '+63 9671 677 760', 'Camarin Road Camarin', 'Caloocan', 'Metro Manila', '1422', 'Philippines', 14.75673800, 121.05800930, 0, 'dxgdg', '2025-10-11 03:27:51', '2025-10-11 03:27:51'),
(34, 3, 'NobleHome', '+63 9686 920 810', 'Santana Village', 'Antipolo', 'Rizal', '1879', 'Philippines', 14.59265970, 121.19566940, 0, '', '2025-10-12 23:54:33', '2025-10-12 23:54:33'),
(35, 17, 'BSIT4107_HIMARANGAN Wendhil', '+63 9081 031 241', 'Zamboanga Street Nayong Kanluran', 'Quezon City', 'Metro Manila', '1104', 'Philippines', 14.63968250, 121.02318510, 0, '', '2025-10-16 06:16:24', '2026-02-18 02:19:43'),
(36, 17, 'BSIT4107_HIMARANGAN Wendhil', '+63 9081 031 241', '128 sitio pajo', 'quezon city', 'Metro Manila', '1106', 'Philippines', 14.66313120, 121.01449210, 1, '', '2025-10-16 06:32:59', '2026-02-18 02:19:43'),
(37, 38, 'kelly llaneta', '+63 9535 375 146', 'Zapanta Street San Andres Bukid', 'Manila', 'Metro Manila', '1017', 'Philippines', 14.56787810, 120.99778680, 1, '', '2026-02-19 05:01:16', '2026-02-19 05:01:35'),
(38, 16, 'BSIT 4107-Salvadora, Mark James F.', '+63 9562 604 446', 'Old Samson Road Balintawak', 'Quezon City', 'Metro Manila', '1106', 'Philippines', 14.65700370, 121.00337600, 1, 'nasa office namin', '2026-02-23 06:45:20', '2026-02-23 06:45:32');

-- --------------------------------------------------------

--
-- Table structure for table `blocks`
--

CREATE TABLE `blocks` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `side_right` longblob DEFAULT NULL,
  `side_left` longblob DEFAULT NULL,
  `side_top` longblob DEFAULT NULL,
  `side_bottom` longblob DEFAULT NULL,
  `side_front` longblob DEFAULT NULL,
  `side_back` longblob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'other',
  `image_path` varchar(255) DEFAULT NULL,
  `image_pathtwo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `type`, `image_path`, `image_pathtwo`) VALUES
(1, 'furniture', 'other', 'furniture_1759281200.png', 'category_1_two_1766124919.png'),
(2, 'Tiles', 'other', 'tiles_1759277420.webp', 'category_2_two_1766124991.png'),
(3, 'buildingmaterials', 'other', 'buildingmaterials_1759214354.webp', 'category_3_two_1766124906.png'),
(5, 'BathroomFixtures', 'other', 'bathroomfixtures_1759280321.webp', 'category_5_two_1767659133.png'),
(6, 'AacBlock', 'other', 'aacblock_1759214251.webp', 'category_6_two_1766124792.png'),
(7, 'aircon', 'other', 'aircon_1759277920.webp', 'category_7_two_1766124802.png'),
(8, 'KitchenFixtures', 'other', 'kitchenfixtures_1759280441.webp', 'category_8_two_1766124975.png'),
(11, 'windows', 'other', 'windows_1759281360.png', 'category_11_two_1766125019.png'),
(12, 'lightingfixture', 'other', 'lightingfixture_1759277049.webp', 'category_12_two_1766125005.png');

-- --------------------------------------------------------

--
-- Table structure for table `categorysub`
--

CREATE TABLE `categorysub` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categorysub`
--

INSERT INTO `categorysub` (`id`, `category_id`, `name`) VALUES
(1, 0, 'table'),
(2, 0, 'Bed');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `sender_user_id` int(11) DEFAULT NULL,
  `sender_noble_id` int(11) DEFAULT NULL,
  `receiver_user_id` int(11) DEFAULT NULL,
  `receiver_noble_id` int(11) DEFAULT NULL,
  `sales_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_info`
--

CREATE TABLE `client_info` (
  `id` int(11) NOT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `country` varchar(100) NOT NULL,
  `client_type` varchar(100) DEFAULT NULL,
  `sex` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commission_claims`
--

CREATE TABLE `commission_claims` (
  `id` int(11) NOT NULL,
  `sales_user_id` int(11) NOT NULL,
  `referral_code` varchar(20) NOT NULL,
  `commission_amount` decimal(10,2) NOT NULL,
  `claim_date` datetime DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected','released') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `released_by` int(11) DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `order_ids` text DEFAULT NULL COMMENT 'Comma-separated list of order IDs included in this claim',
  `order_count` int(11) DEFAULT 0 COMMENT 'Number of orders in this claim'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `sales_user_id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `company_address` text NOT NULL,
  `logo_path` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `sales_user_id`, `company_name`, `company_address`, `logo_path`, `created_at`) VALUES
(1, 2, 'Megawide', 'fdsfasgge', '../../uploads/client_logos/logo_1764225801_6927f309f0ec7.jpg', '2025-11-27 14:43:21');

-- --------------------------------------------------------

--
-- Table structure for table `company_logos`
--

CREATE TABLE `company_logos` (
  `id` int(11) NOT NULL,
  `logo_blob` longblob NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `company_logos`
--

INSERT INTO `company_logos` (`id`, `logo_blob`, `created_at`) VALUES
(1, 0x89504e470d0a1a0a0000000d49484452000002590000021808060000005efcf98e000000017352474200aece1ce90000000467414d410000b18f0bfc6105000000097048597300000ec300000ec301c76fa8640000ffa549444154785eecfd07b865c759260ad74a3b9c1c3b9dceea562b59962c5b728eb28d234e32188361001b4c18b8ffc03f33cc0c3f97fb0ff3007780993ba4210cd8c08013ced9968d6d59b6acd4925ab1933a9e73ba4f3e67efbde27ddfef5bb5f7ee56cb96e53e1da47acfa95db56a555a55b5beef5d55b56a79455118070707070707070787b30bbfb41d1c1c1c1c1c1c1c1cce221cc9727070707070707058053892e5e0e0e0e0e0e0e0b00a7024cbc1c1c1c1c1c1c16115e0489683838383838383c32ac0912c0707070707070787558023590e0e0e0e0e0e0e0eab0047b21c1c1c1c1c1c1c1c56018e643938383838383838ac021cc9727070707070707058053892e5e0e0e0e0e0e0e0b00a7024cbc1c1e1a24191e77e96a5215c951ceed2dbc1c1c1e18284fb40b48383c3050f122adff3aaf9cae4b0692e0e65e94a4fd0b3ee98dfb7663ecbb266100499f13c27cc1c1c1c2e283892e5e0e07081a3a8e48b47d6a793f75e171fbfefbabc31bbaec89af5b07fe2d17064ebfdd19a2b6ff70736ef2bc26ac37344cbc1c1e1028223590e0e0e1724209bc0994c259bbae7eae6239f7f5b76ec9e5767b3fbaf2cb222ccf2c404d581dcef193d515977c5a7ab3b5ff5be6074d7dd59d4b71004615a26e1e0e0e0705ee148968383c305072158e94a5f76e2feabe3bd9f7f477cf4eeb7858de9b5c5ca8231796672182fac1a53ed37a66fdd72b8f68a4f8513cffa58307ae9ddfec0c4bec2af35dda8968383c3f98623590e0e0e1714b22c0bbc7471303bf2ad17c7076f7e473e75df6bbc9513fd516bc578596a3c10ac82442b884cee558ce91d37a63e9215c35b6ff77bd7dd176d79fedf876baefc4e1ed6977c3fc8ca641d1c1c1cce391cc9727070b82020a357c654f3b97d5b9223df7a69f3c82d6fcb661f7845d45af2fcc68ca9640505168816655601821598dc04a6a80c1aafde67d2eaa8c96a439937b4e90bd5f5577fd85ffbcc7f0987d61f44922dcdc1c1c1c1e1dcc2912c070787f38e3ccf023f5dee4f8fde71c3f2be2fdc949cb8e7b57e3cb3de6f2d983059367ed63261ee191fc6f37ce3915c159e212d2bfc9a2938aa55a99b56d46ff2eaa0492a7d2bd5e1ad9fe9ddf1ea3f0927aeba352f6a4ddff7f3323b07070787730247b21c1c1cce1b387a952471256c4e6e880f7de365cd435f7f5b3cb5fb3541ba68a26cd178590c629599284f61872600c9325e00a255922c1efb9ec941b6387d18879129c29a49aa43a6a80c99707cd7977ab6bef84f83b1cb6ff7fb374c42dcb9b55a0e0e0ee70c8e643938389c17c8de5769a32f3d7ef7b3e3835ffba1c6d16fbf216f9cbc2cc8e641a8964da56819bf484d50e4b07310ae0076a89149b060f21ce918dfc8c2ab007e510853316958375ed867b29e3193d7d61dac8e6cfb6c65fdb59f8ad65dfbf5a232300fa2e546b51c1c1c561d8e643938389c5370d7f638892b516b663c3d72fb0b5afb6ffed1e6d49ed714c9c98a9f2e1bdf6b99c82426040fa2f14d01a2053295faf809ca343cc33f53f826850ccb608a00e72b205a0188981f98ccaf9b24e8336965d098eaa809fad6dcddb7f1797f1c6d7efea7f3ead07414556249ccc1c1c16195e048968383c3394396a66110cf8ca553f73e2b3e74c7cb5a530fde58cc1fb8a6c8568c97ce83503541b25213727ccacb74040b6e0f642acf48b240a2b80e0b76e0870813981c322ce6db86e05c88a0240beedcaf98d4af8197c18403268ffa4c581b3bdeb3e68a0fd5b7bdf09f82f1cb6f2f02b7d5838383c3eac1912c0707875507d75e7163d17ce6914be2bd37bf3e3e7ad7ebd393fb5ee025cbbe9fcc8334c5c6f763c3593cdf07b9e2e815481622daf820595c8705a2c5a942f8057e64c2b0a2242b4b4d56203cf852ce85f1b4fdc078206219d76fe5dcee0171abe3c64463a6bef6d2cff5ecbcf1bf85eb9e796b1ef5cebb45f10e0e0eab0147b21c1c1c561542b0d2465f367ddfd5adbd37bf2d79f4b6b782586df292a6f132be39b802d294421a6562fb5ccb6e72c4d32daeec38536122fc9264e99421df322c10b8003d02ef428c9264c1ceed88178cf142902dc405e12abc3ac8d7a0097bc64c3038f19dcac4b3df176d79c127f29eb587c3d0ed14efe0e07076e148968383c3aa21cbd230c8960664edd5235ffcf1e4f89ed77b8bc77a7c2f36619ec9d420a705856015a98c4a71d6af2061ca41b478009050196e3c4a9265028401c14258aec5228f2233a39dfb4803f1b28c440be77cc60b4c109068214e1ee15c60f2eab031b511e3f54f1cafafbbfcfd956d2ffbfb6074d7c348b389706e54cbc1c1e1acc0912c070787b30e19bd4a1af57ce9c8d6ecf0adaf681eb9e38df1d483aff0d3a6173466409112e3e78909c967e445bf1c2409a48a7f45ce712c19c15292054ac5293f8f6f168638a37e245122be7cf5f139aa0592459696a5246df04722dceec197bdb57c9371d42b873bea317ed46fb2fa9849ab83ad6874c7276a13cff95065ddb55fcb6aa39341e0768a777070f8c1e148968383c359053f8b13642b7de9f1bbae4ff67df147e3a97b5e9f2f2dac295a73b21d43c8f55759836c4746b17c21428828648ba3583a4a45643821d3821cc9f2039ce448162c9c16c2d42652c6047cbb9064cde728164e9284918081a9953c4c49190dd2f2a3aac9c21e13073593d6c68cdf3771b067fc19ff50dbf99abfcd7a26f6875194b845f10e0e0e3f081cc9727070382b802ce1479d7bf2c5639bd3e3b73f3f3972db1bf313f7bfdab466aba6d93245da00d9e1760c99094c8208190410885620c355204a9e4c171242b78aa02459f097202051e5a277ca2de15b9c122cc3c0d7f87cbb1069726516bf6fc8d12d9ee31a2f0981f0fcb8b40922e385a1c9fdaa490398b0df64952163c2be959eb5d7fc5375cb4bfe3e5c73f9ed4565907b6a3921e9e0e0f0a4e048968383c30f0c19bd8a178792a3df7c5172e89bafcd661e7e99b77c6c8789578c1f2f20004341d680007134cbf340b0488a207f384855ce0b2a79120f9ee5b709dba764148ad22ae35a2d1c0441603c19bd02d7e2c815d2557ac6b1309d2e24c90a7cdff81ef3c4d98c6bb69037d76a71213cdf3e8c22939aaa69e5a0625e9f097ad7986affe67baa5b5ef4e795ed2ffd485a193911862158a1838383c3f70747b21c1c1c7e4014d57ceee0a6e4d0ad2f6f1dfafa5bb3d9fd2ff1d3956ad09a33266d41c82430320ec5b0c60721d20dd741b0688454f1906c0a3e14493eb76b80178d44461839c865548b724b885237e8c72460e7245c245338061733011c9e5f940be25324c7355e20554823c5b9d4ab809e05286305573364fcca9829fad61fa9aebbea1faa5b5efc017ff8d23d451835dca8968383c3f70347b21c1c1c9e14203bb835433d9bde736d6bef176f6a4d3df043dee2c15d26e1d4600b648a1baa672628621329fbc17f5eae8f22d1a21fd3519b34cc2ba703b9585dc2cb241f09199d2aabb8ce4a7c21bbacfc625a62409488344d11202dfd719e7160cba2fad2df84ba5b7ccab55b881754eb28420db9452875d564d5115304bda636beeb0bb54b5efae7d1c475376741dfbc5b14efe0e0f044e148968383c3f70d6ecde0c57323f9e4ddd727fbbefca3e9d4ee3715cd855ebf312be446f814f7b982cdc5edfc3c0e975e71b594902c7993d05743b29473713a09919224215c2448229ef46d435920cf0465ea8febb7ca3708b938cbe7ba2b92ad107e9c52cc64348bb44bd2e14018df6444d90a8e66a1303e47b1905e9ee018b1a34ad578010816ca9231d9a06ed2a0cf44fde3a63270c91e7fe3b3fe2adc78fda793fa8603d56ab545f1c9d2393838383c1e1cc972707078c280bcf0bcac59cb178e6c4d1ffdeaabd343b7dc94cfed7f9e9f2e7ba6d50487591152e3fb21088dee57c5f551244821879f4a70db512297512ca1471046dc662190f844c051a9924891b079429240dc4a92a53377246afc93b2213e372ce59423a70c8d09b96e0b048c7c28473c8e621559025205af723d174520e918e3918d9170c9d4253fcf13548c5fed315e65c814bdeb57c2e1ed9f0a36bde8fde1c66bbf5a04fd8b28875e88838383c319e048968383c313827c773059184ea7efbb2679f46b6f48a776bfce5f38bc5dde1a4c978d4939724542c5912310144ecdc18ff485a3594a8c4080e0c1f9365222922c95404ab20213228c929c80b249e41317ba331d12ae4c3eb723692947821f479e901a132e0249b7f048d67c19ad226792b7131197a35f2947dab87544b9a68bc48e348d8be7b9533cd396f29084497c12b59ac9eae3b2816930bcedb670e286ff196d7efea7b2cac874e0768a777070781c3892e5e0e0f03d91247125583c7849f2e8d75f951cbdfdf5c9c9075fe8678d5a102f1a8f6baf8a1436e80d0dc78248728423e187a34fb2268ae405048bdca6086564c94e1772dc8a148b537f3c2d6e8e2f497a485b88560a7fb81140c78f72381847f3615a39481a53e22276aeb3e23422d763c9aaae72240bbfc85b891afdfc9ca35f00d766d14b43c3a1e59595f3c8340dfb4c0e13f48e81708d4c56d65cfbbeca25af7c9f3fbae321844a10484ae5e0e0e060e148968383c3e322cfb3c0cf967bb3130f5d153ff285b7e5c76e7f73b6746c6bd69a3191ac7b22f1019129c3fbb20e4a389580b4c38e5e596470cb1b82c249e820ad421a205e829268f13427033d19c522a14a247d9ee0fe5a1d303420a3584c83a123c66ce72b844edc8c8f7297fe3cf6257fcd8f2045a3bf86e1481bd2833b07a1e31b891ec9567dc814b5755965fdb5efab6e7be9dff8e397de9b7a3d8b6eab070707876e3892e5e0e07046a4691af92bc7268a47bff6eaf4d16fbd313971cf4b4dbcdc93272bc6cb571042680c8448497284b028c5515b2124ab2442242b245944518e642938f205370c4fcb2278c8269e95912cca297ee750261a41e428b6105f50e62f1f9016a2c6fdb57424cb122b25752579f29906a1f1bb4996cd8fe7da040dbe324a26235f24945593572ac68b864c5e1b317eefbabbfd75577fb4b2f9059ff0d75c792f02b534a68383c3d31d8e643938389c02c804d2895a7ef2fe5dd9239fbb293bf2ed1f33f347b6a68d5993274b86df4fd63daa485294a05858726547b49460c919f915b2237687c490aaf1bc2c7c2f090fd30924a21239b52dc9e2b1254a84e484d270eab082749909d784811cc1c9bc08c90f442db743565d24cb122cfac9a859798e5082d5712b0293f99129823e93ca3710878beae8ce4fd59f71d3efa623577ca752a9b670ed3623070787a7291cc972707068833bb77bcdd9d16272f7739283fff2e6e2f8dd6fce564e8ef8f1a2f1738e6071bb8392e0d891a4d3a084a53385480244437f129d8c6f092215214070d1d651ac72340b90912c521a8a2744e4f4204916a718957475932cc6617c9ea9c08d048560695a1c2f631a8cc532179e256a6a84dcf11cc01de8097bac69215d1237c09ee5a8164be4f9dce621325965c078d541e3afbdfae3f54b5efa97e1ba6bbe995706677c3252070787a72d1cc972707090d1ab3449a260e5c8d674df975f1f1fb9ed2df9ccbe1778ad799334174da5484d3584c000d7c8b80d82c88d53f903c912c1d12b4b962c02094ae20347399a2429d086e1392fe762775f899521e152d964d74829a9a31f8c10255872c878cc5c491629936e0da105d251282980e685fc3b6527c92a0b5e42a70f75748b36219ba4229878954878aa880c8b9d7107f9a06e8a812d261ada746775c3757f176d7dc50793eac8645489dca2780787a7291cc97270789a23cf73df8f9706d2e93dd724076f7e5372f4ce1f2e5a335ba3d61cf8484b3e8d23d37f20191ce9d1293ffc159dd1241210d208b119b02459700969e9508c1ca748b2342e49979020091f28c16178390977395aa60bdeb50c926fe96ff351809871c1bb254db0ed341f0e3a715886327f42f36a070448f4986ea7dc9688b593c639bb805f20c50f4d5e1f35a63268bc9eb1597fed357f1f4d3cf723e1ba2befccfd9e0537aae5e0f0f48323590e0e4f5770f42a4d43bf35b326e3c6a2fbbefc8e78f68197e72b27fd1044a692815c7127758e4ce58549b90f96dde6806ca7448130568e586962cff3b743b2f00372a30bd0f9869f720e3dd635548cd11905b376871c3121fe95099656194e18904dc7827e1a564956692422dddd712dc29264294e19e912b78e6a69f9910e8272a3545e73ee816871548b9fe4a98f18afff923bc289e7bc3fdcfa928f65d5f123611425085716c8c1c1e1a90e47b21c1c9e8690d1aba4d197cd3c7c59f3f02dafc98edffd8674ee91ebbc78d9148d1326008188404242f001998c03abc8f8ad990c044356a4779110900bd9c11dc484e4a39b80914e30a4d20afc08c9e24894eedfa98bd035ae12248dcbb12c59c82e44c842c991245512b4530915c33308fc4a32d4860d2fe9311dda4ca90c57e6abc73aa2c6105c1b667dbba1539cbaff16091677b42f40ae52ee728f3845d0638ab0df98beb526ef5d3b5d1bbbecc3d1a6e77d285873e5ed45d4b7803a6254070787a7381cc97270789a41766e8f4fac890fdc72e3f2c15bdfd298d9fb0a3f6bf405ad59e3e7cb08d034511ecbceeadc759d5384dcb94a4774483e5466d837ed648b0361215e17c1d2e938129ed34916cfe9da2ccb337812f1843071fb05a2439edafca73cc3d04a724882c402ca4076afad12eda8655c25588c648f2d212bdd72dcc9dba2edc3b0657c3b1247f0654b2eb45792053762e445d5981a88567dc4f8b5316ef5707b65cb0d7f55d9f2927fce6aa3278220703bc53b383cc5e1489683c3d304b8d749816af9cc4397c47bbffcc6c691dbdf962e4e5f9b2773e03d2df09f26044213f420315191c8b70649b2f846216948e085b07d9008d02c900df91c0d8805a946876e40a808d152b9c2f12842c9107f60849c7034ab943da7901cc6b094466d09d526346adb6d18345d1b1ff8be4856998fe4df95e71948968587d2d0683a364d3871cd2c370968066f11ab7cbbd1ab1853a9c31a344575d8e43deb272b9b9ff327952d2ffc883fb2e31104686a0a0e0e0e4f453892e5e0f034802e6e5fee4f8fdef6c2f8e12ffe483abde7d54532bf868ca02856606253a42dd8fc844d66221088081cc107c1926d0db8f05dc8874fa7d8c68b849f702b03e129384f7922244b08080949171191631de152a8ec11d22209d005a24227d32fd19650a711ad53c1f0f03f8d64b581b8cacb10c6923be274824594244b4e9d06923af5663a9d81285eb79043ae6193eb2001a59b0be4590b205b410ff7d332797d4d1c8eedfc446dc72bfe3c5c77cdadb95f5d768be21d1c9e9a7024cbc1e1290c8e5e25491285add9f1fce8375f163f72f3bbf2a93d377af1bc5764dc981cc407dc2037892932aeac020902b10ac026a2102481df0ec431e584c80a10108e6179f2cd404e132232d808c9d1a9a48472a534428e68f4b8a4328046b00bdd79d6fa59c2d35e1c2fbfc0f7225a655a674237e17b6c7c1b0ff9cb9610b0baae874492c0558a2de356ca3605526f808eeec106b1621d71055906d6a844343019b779a88f81688d996864fbbf449b9efbd7d18667df9c55478f47954a2c893838383c65e0489683c35314327a952ef6a753f75f1b1ffcc60fa553f7bc2e589ebcca6fce99a2b10052d5345ee0cb1b8424031c8b29642a3015bf08e79458e18c102d3a394683381e478c484c486c48401e4b6eecdb779df5579c4c43481139dc6e017f884bc32438ced51e64a243fc59ae128f2158b4bb9890b82d0db2e8948b57d901e3f2b8333929f1e19583380a69640c8ecac18fb6964dd328cfcab16c6fa117057e9a9b9cc71cc52211a59f4c1b82609180719778bf6a92ca80f17b38853838555b7bed07a22d2ffc40b0f68adb4dd0c3ef153938383c45e0489683c3530cb8a7bd24892b61737a7d7ae89b2f6f3e7acbdb92e9fb5f5d4957fc285d317eb2648ab8c1dd188c2f248b3483fb5f817808c9821d8024c09ff2c11abe5d48ce11803078240ea01aba3d0389881e778074901ed74ec90eef5d536b4490f34d42c657624490ee2845a2210952d8f55702215a36cc99a02974d05da66e94e9c8e27ef5b1603974dab2a4522c22af534f97644bc919042843a21e753a324d13925b1dc9f241b2104942b22e038e6c2134cee726345e583559a5d778bd1b4d30baeb33b54b5ef93fc20dcffc46e6f72d0541d03dafeae0e07091c2912c0787a710387a65e2c5a17cea9e6b93835f7b5372ecaed79ae6ccf6305d3226691a3f6b99206b803421709151f70b99e218136581aca7623afc016bc8e197938df1d00b65cf2c5df48e782456749744cbeedcaefb5e11bad128b76c100257facba04f511152d24db24e854d0350565382fe5de7bad0d97894e56239cac3123c5f0e3809747d18ea400fdb509215c2d6043825ca68722489f2fa198ae528491643a03e559ee23c4916086ccafdc532e403621a4420963e62826491c8caea2dd47d565f2b6f20e6035bee8ad63ff7fdd1c6eb3f1b8eeed88ba462a4d37df10e0e0e17191cc97270788a204dd3306cceac89f77ff9b58d7d5ff9897cf6c08bfde6ac098b15137a31f8446a0a28f8a0800d050f0ea06400bc81648acc4b46a8cacd45ed7604245924576158810d5240d245bf92c528c9ea909a4246ad485d2cc94278f8c974248e65f48b235902463a9de610202e5634b5798692ab53d75675c06b6a6f8c5a96c7122501d2b1696a1a9a47777a1a272ca993964dae0be975e37492c5756c5c9715a07e7c1ff149468390db6598568cbaf703135543d42fc86cc69441eeb20445421d87755344fd26aeaf31cdca9ab4367ee5877a2e7de55f446b9f7147eed7dc4ef10e0e17311cc97270780a80d383fedcbe5de9fe9b7f383efced9bf2b98357fb69c304ad25908004848a244047506873b5bba51134a2c5210b48a6088e4ce97e4fe28d30240f9c3293311b79c350e28af80001210929c950f74896f89058c8fe58f4a71b6173a42779e9c818c1a9394914e0341cced843713384849260f4c131c2d1976451dee8c381e6229e7a5d748a8fa6c9f2c83a2a3d653a1446afcdaec992f12929237d9564691c1c23be902b490b358a6b66dd9264b12c1c1fe3e6a41cb5e24816c9a91f04b0515e8e2072dd5b9e82f066260f225447d524d19049c22193f5ac2982e1ad5faf4f3cfbefab5b5ef0a9ac367ecc4d1f3a385c9c7024cbc1e12206a707fd34e9c94edefbccf8e1cfbe2b3e7cd74d41e3c4b0d798363e947bc08f398319f8b2f0a81c5962442a7bd288f6edaff481244068457beecd8ee094764988daa483e4ca8ef29068c869cb5a4a1b696ade7a4c0263c99c8e3ad10dfad3263465148029db1933c985e52bcf312e691c498d8e5c710f2f9238a425e71857f351fa449018e1ba73e52c4ccb073913147c3390340ce4ac8b68d9694ec6d5d13b44829f50294956cf77a62be90382c53458b452c692a22ad9d3d1442e850fcbf6c9bc8a490d0cc856561d37597dc404435bf756d75df577956d2ffb803fb4ed006236703d9a988383c3450147b21c1c2e52706b86a83939911ef9f68be343df7a4b7ae281d7058db9308a178c9736c003706fd39004900b4199930e7021ba700540480683903474898253bfdda764a11d4960cfd3afdb5f899c42c987121cba2523255b7e278e12ad4e7e02f8713b04afc8842871648b05641979ac2969914886143e888a9ee1afb8ca53bc468e20d9693ee669aff7bb932c64d0ae18c6a5db122cf5679a5a3f088f7839d2d090746b08962690d133f8ca281817dc2bd9627a5c0396c095792059519f4c219afe0dc6ab0c34c2b1cb3e1eaebff663950dcffe4a5e1b9ef27d37aae5e070b1c0912c07878b0cb8673d2f4dead9c93d57258f7ce647d363df794bb1787c4b94ac189922a48a174e4322413ac0e92ca87218ae8f129ba761930e10e411ba3e49c10d16184ffc401eec360b4487d4108c6fc904a1e97560434b81c46839c4f3b1c009a139420e11536c7ae39a48b610b940a128b7e4ad48f8b1b424461cc5cae1c51842cc684ab7e18811d2e2f592b811ca9d386daa85b125e5b4a3922c442bcb698928c7ff48bd48e7f8d663d075b92468194916ce3254566ed62a0452486c6e4259132767918b4e19324d8297ca69c62c8c408423e385fda6a8f699bcbac664f5b1a3d5f5cf7c5fcf656ffc8ba4bef650188629ae4b233a38385cb07024cbc1e12242966541d09a1b4d8fddfebc64ff977f243f7ef70f9b64b1c73467a1c7a1deb9d607e1f8011cbdb7a1e561911e5872237447c8034907c340e9976240bc81ee8d36654a10e810233d2634548780312d35f4689fc56f9901d009ab60a84e8acc8fd7011f949f3489c119472805fc49b248ae746b072535fcd40f21b9e0bc122cae20635094a0fcd60dd36a5f9b2623f96999488e48c37444ab0ca57997e5d7b55ccc9167115abd659fac1c01e5ac102d2e9e275963b01001784d8cafedc374388a45b265d06ecc83e36ca47f39d772c1147ec598a81764b26e5ad19029a2a156cf961bfeacb2f5851f0e06b7de9f550767dd5a2d07870b1b8e6439385c24e0f460b07c644bfac867de9a1cfed6dbd38523cff25bf3c64f57a09963286e9225a87928f980ec01e0a272dee39684e45dd37464082409d6872a5e8fe903ddcd03611f655a34e2b431d48fc72427ba85831a59bbd475dc0d217be510918c32014286108c47b22e8cf151643996b435154e2172aa314304be1129c4043649a44400580e8ec4051ce90281919dd74b92a5a361481fb6a4abb4a6bc2ea6433f1dc9529f1252268e402184d89a9f25a7bad85f47b08482214f216b488f236d166d92863474fa50a730993faf23457ab9af5b3d70db072fe05612a16965555354064dd0335e44a35bbe55597bf53f479b9ff7717f64d37e632adcbadfc1c1e1028423590e0e1738708ffa5eb2dc939d7ce8aa64df17de9a1ebee55d79637e0db767f0b386287d8ec650ef5bd2222339a59bf778ca2d06a0b8497eacbf05e373a485a4c1121d3d3e15f45132a2d0511a2523a743477a4e05258d8d2f3445640fa7eb78c472300e8f4142e00cbac809f39272739408961035c44f90a0122cd2421dd42149133245c3ebe1698d80b4b9cecb922c2e92676ceee3c5b82c0527ef748f2c258d04fd951c69fd58824428d192ba200a6edf40dfc064dc151f07fae921ad27a641f0ad427b9d85ac91cbe54dc48c71b9892942922ccb1e66483c4d707d618f09ea23c6ef19e5e2f8b97064eb47ea3b6ffc5fc19acbee2a829e655c932db08383c3050247b21c1c2e6064591a06cdd9d1e4d0375e91ecfbdc3bb3137b5ee9278b51917053d15814b5d5ef9caee2fddc2617b4c100c85d328ee440cfeb4eed360605007f75fc85d4a09b5c313ec1343935d79114a00a65128f273d3a3910dda1ca349123cb227b70c14faea30c46d262094d37485658262929790bcac5cf59db601cdd22bad3219823fd388245f245f2463e4232065a83b3a4594ad084ec806431164ba6a0cd7ad6bae98c4295c7b0c1839901e274f6d812b226044b89964ebb2a29635c923c21968c2771711685d5c5fbf0f6f80123b4213cc1bf70cd551370417c5033b1df6792a06a2aebaef87c7dc78dff33da7cc397f3b06fdeeda9e5e07061c1912c07870b10b82f41071af57cf6c8b674df975f9f1cbded6df9c9fb9e2d6f0d260bd0b81c098182e7de5508c9e9374e0d723444c8951828711966019980bf2877286e210d0c5b32212a7b2aff533f7d4362553a019d0e53901e1048e2547479906274705a40aeab2a099380e4876563199036a7d428977466b3938e102c212c9d3718b9aa095729e7983ddd363fa621538365da0cc971324e254addc08fa355245842a8ca4d543524d7654932525e86d5f26979640b06716a6d300f3d2241d3327111bca45b1e4beea78c68697acc51b7b7808de6e17e5ba45ccc55d7a681b8b15d91b61f44d286ad3c342d2f32c1e026138e6cfb7665cb0d7f136e7ef127b3dac86414b90f4d3b385c287024cbc1e102439e658169ce8ee647bef592f8d02d6f488eddfe062f8b8782d609e3e7cb264b63211554ba6da242550d454c652c4a1fde01984b00edcf5b5c3ee3c22d0ae42c15b78d070843809207c9a0534f95eb93e0a184c68ef4f057890271ea949a42bcbad30734491b0665677642f8bac0b7fee4ba901a12917559520a922106d034997c2afe5c36ce84900e8c9030d852225e74a6644d8b489ac32948d6895c1962f31a094e17220403a08c428edaf989a34c83f15036a4d9594f8574e41c81b3a863d685e450922c216d6c90f6f5c30f87767d16cb263be2a35c3275c8f139b4234beb71d13ce2914007700701d76b4526c16959365fe747a6478cdf3b7a345873e5476adb5ff24fc1f8d5b71741d8447db44be6e0e0707ee0489683c30502dc8b5edc6a55a3c6f1cde9be2fbe31defb2f3f512c1fb9dac420570588952c6e4f4d2a7b47f92022ba45808ce2406b93609168953443485614c01fe1794e953c874a68d39032685819a93a7d244b8882255954fa0c27561b7a6cc903054ae9e80a28b409c7f45192478281149925c94717784c6ec06464140e2e9ba67c7059ca445a04ff9c0bc5499e60704e16b8a35e84ba20712e76671285bcc5c7744b92057ac2742da9232cc9d2512ce6515e539b18316725454c93db30782443703347498fd726d7cd6942faf047a70c79d6122dad4f8e52c15746b068739290ebe65836922c0642a985640508cb2b08d0a6b84e9814d1b8503ee3c2f8a06af2daa849a2c1a2b6eeb24fd72eb9f14fc23557dd964603b3dcea81b93938389c1f3892e5e070010024c8f7e2c5a1ecf0ad2fcef67fe56de9b1dd6f308de981a06841e1ae802840ab1609d4b9ae2222c9e2485639a726ca9bf77291ab4ee50816557b084620a3390cc2854c42044abb0d8e1451d977d0de9d1de92801b1c7141add714b08b95088eb94309c262b9d365f129c328c90c492ecd0cdd129094ea2c36bc2351601ae37ec85a9226c080e1499acb5e2e5c9b2ec3545a2c53c84c7303eeb8b53859a529b78caa2f8f23c091273927db7104faf90b5a6236c4a08bbaf83044b7db970dd8e4241889a4099531b96ec758f66b1662c91eb866ce3200497d43111375b97e48a1fe5f664011a0ce71261b1edf59347485b8e439356b856ab6eb2de7113f4aebb275a7bf547a3ad2ffb903f76e983289efbd0b483c3798223590e0ee7112457dc9aa192cc8c27fbbef23a6fff177f3a9fd97f7dd13c694c63def8015432079f709f92105071a7d0bf3202246faf411d93a4f03ee68815157ea94f852e88ee27a1c011ce71ca907138326279814c53b549146989920e4b3b64d17b19b61bcc46e99bc2ba48762c91b06b90841d0056d54b5978cceb0094c8a18c3ce41a32f8e7b8bec20bc02d2ac68ba2c2d44757824a7fd3f851ec87b53c6dcef6142b735593346b266bf87e9ec867843cbe49e9810481485972276bb0ca025a92d50dbed5c7bc8542b1ae18586c9ed5882c3b5d245aa4827c0791d56e8fedb511a7932c9e129bd72575d195bf44e4a81847b3121041c444388e5c9140916cc9a81f2a89f5c674b4fd393aa72625510419cb2b7da6a80c18d3bbb615aebbf61f2b5b5ef4f7c1e8aedd71d83f174591235b0e0ee7188e6439389c27c8daabc68935c5f47dd7a68f7ef3d5f9916fbfa5d29adb68e205e8dc2563e296f1f8b21874321536d572ce57faa9bc9900147837740b044b670004d29050cb243bd4cb50c4aa666d28122c92023dd291ab8e4cd01449b23a7e8c7b26556d53e43921023c40befabd3ffa49113400af42088226241b78229cec989e715d14773daf8034f470cb82c4ab0fcc54c72ffb4c65ecb2af9b6aef82ef8769ba70746b3a77f09aece4fe1727cd93137ee34414b4668d9fa2de64617a5a8e5c21579212016b44c68eba80eb2be5a04c39ca9594e3548fa9639de623a92275e22896d026894fb7a6a321f08b7cdba3592882d83867c1aad138f04579390549b7ec732653a07c5b1139a0309c129669504462ba046dbd3e0640da8853f835e3d5064c511f2e8afe4d7ba2d1cb3e134c3ceb73c1da677ebb887a171dd1727038777024cbc1e11c03f79c97a66918ac1cdb943cf8f177a4076ffd917ce1d033c2d64953cd9a08a15347a2c0a150a98833287f8e73703d0e477908a85bf9b520a120743d0fc0b8f412a2a164c17e06a65bcd9240d9e93aa6cdb1129e66be8412ac4e043b5d684789ba51465152472011f12b8f3962c6632f20014161ca82642cab89708d200a7964e2a06ed2a0b7303d238d7068d303f575977facbafda51ff106b63eec0581cc891669aba75838b4353d72db2b1a87bffd63d9fca12b82e563bd7e6bce847913d79a80ac6430cc5b7255124732d2fdd2801014d6356b53c3b6895619864447af83a486472829c22b15d31133baf598e15997e244148dc12a905de1591e5e2fced35882a60be15903ac1b789130d181f0dc8143a608e1e85ecbc66b031513376539e36608c84d4cb3a866f2da3008d798f10736de51d9f9aa3f0ab7bce85359d837ef768a77703837087eebb77eab743a3838ac36f23cf7fd7479c09cb8ef99f1439ffa89e6c1afff8cd798da11a68b26ca97a028c11fb244d4a6ec69050755afaa511e5339974a953f32c4210efe88f2b5844ac2c39014d19753870c2ab1f1439b4abe7d005852215442dccc5bcb41c2d72e0912d2b36ab89d01f395d0128efea5cde3765c182135190887ea79eedc2e5383a6022252375965d098de75b93fb07ea6beeeca4fd4b7bfe8afaa5b5ef805af7f62bf175638bc0716815c822831b5a199a06fedb1a032b83f0cc36691c597059e17c908561e232fb912b91672198e0691b0928e78e5b70f65b0b02c1f6b8c61b5acea96b0388fab543f181b96d0b036065da4545afb128dec0d46a66a71ccf324aea7c6437ac88b3b636924cd85adaa648d114b0a877e21ed4c8225b69645491a6cd42fdd0147dbb214040df43c6baccf964f5c51c4f351501b98f6eac34b4848c8aa8383c3eac18d6439389c23c867711ac727d283df7879f3c0bfbc3d9f79f0463f590cc26cd984508861d604bf6a816441598a22e54267b5652a8dd3817e68bc00b401042595e9c19cebbaa861a15c431322bcfd640dbd453903f6333bed8f2397a44ce900475754ad2ba9d0b0aaec4b9028002457040f2dc920cad36d9b690a6404ab3b24caeb6728630c17cac2f222cf3ca823df5e63aa8326a98f272059c76b13d7fe5565ebf33e6a06b73cec47f50622b7533f0d5e91346bc5ecbecb9a0f7ee2d7cdf47daf33cbc7fac3e5a35e5034c1e952e3816cc8276b4074b8b09da34bac4796374402ede94258965871548be194bad8baa0ad67b98d03af8746eb45fdad1f21f55a1ad6853d9673522f9dbae188144a84328080e6765b091050eed42ff564e4ad42b2c5006dcd3c1947ea1c8ded65ba779a27bbd723187260d963be3810d64d5a5f678aea58124d5cf70fb51daffceb60cd336e2f82ea8a9b3e7470583db8912c07875586bc3998aef498937bae4ef67cf4a7d243dff8496f61eff3bc64d9f79245c305db1cdda19ee7a80eb4240c0996aa5d8e5c10a2822d59925fbee18660f0a372a64de8d888a85b648e33aa79c5574641486e389a049755e49658b5d72075132c81e6616d9643a6a9a89e5166aae9320751fe242accdb4e61f125488e7451f117b2309da7a1fcfdc87861cd14955193d7d7987c7063cb1fdab4bbbaf9d97f51d9f2e27ff68640b0c22ae750bf2bbc204c41d066c3bea1233898cdd3d68ec2aff682ac8263f1fa505a144cb6444039da752bbf2c234bafe5e708578e6b90e9577a814cf132e128eb1ff48875c6ebc3910461505e533b15822356b04a23f455f88ca64b6f4d972e960a3622300d96571a8ec7ac27c98094c9d63f63e1af4c4eda1e444bea9e1e1ccda2cdd3527e122ff4172f0bf278f99a6c6566d4f7bd85a03e7222f32a2d90380dece0e07056e1489683c32a224d9328689d5c931dbae515c9831f7b4f7ee8ab3f651a336bbde69c29b8fe0a048b4a51df76a372844225c1828255d24485aac447152b8902f521c32aede109521e9ee59b6854d2e2965fda08d78e03854b82c050626b38255b4cc342fd3b605ecc1b2e9a4eca88088f42091fa724594efa0b49c139b936eed785e33ce736a2f093c0c84f08568fc97ad71645dfda956070f3bfd4b6bee88fbdcd2ffa4438b0eeb0e7ebfaab270284cdbcdeb5478a81cd7b7ccf9fc992c6a5456afa8aa419f0da489e48fe9835af5cb6b9607971ccb2f2c5015ea3f019d80cc503d61d4997c4c4bf5033691386e5b5200c1362785852bfa824da3ca6bf58041d0ccbe45196ce19da653be62ca71240a94cf1d7632d3d5c8c8fd89ce6647bc8fa3d3943ae852a63545c2f93d3f6861faf238fe5fa8ad6f2ae64f9d865b0c3b03e3495067c9900a9497e0e0e0e670b8e643938ac02a07c3d2f5bee33271e7c46f2c8e76fcaf67ff93dfecc83aff05b27fc20593645de821e84c2c31fdfe6a36ae3af8c00c1e67a1b2a58dae273baf2c3a110153854f1964a59001fd1eff68f10ad0c4fda6ae497e9e29f6339ea60688dd149538f2d25b067cba242c997539070936c3065f903b71212023f99fe9473cc02e986552558b551e3f58e67a677c34cb4e68a0fd6b6bdf42fbc0dd77d0d8aff24229585fe7ee0157ea577a9e85d77200ca2a379ba3282fcc78c1f559098c705f639778297a0fce195e3882779283e3c46e1cbeb633158ff32b8481b7e1c9113e040af966e3984c5c4e823be6d687b595f06d478763d9810297a94044bc9113cca78ead090b4359ef41a291783720a5608170e7d4e2b3349b22a39c974cbb622e1ca92758d85996bccca89fe4ab567c68f7a170bdf6df3e0e07036e1489683c35946966581df5a184e0e7cf5875a0f7cf497cce16fbe2b5c3eb43968ce9a205b82a6e7826cbee04fa5572a79a36f0dca5a212a51d83aea82e352697643d52c0d63978a5702c1708a50d264fa54e2f0b27ab3adfca1be1154c76ee404c2697845b75bd31085ce211d12274e4df93e975449f970d11a90a761713772f94399e4fb8a32c41598d4e70ee51563a25e5354074cde37d1cafab71ea86c7aeeffa85cf686ffe98decdced47f5154decc9c3aff42c7b835b1e0cfa27ee4529e22c69edc84d18255911a49c9e6521595d328248426547f8584e78733a15c7ac4921564100d2c25a568226ebe3181e61a5c5789d1215676d1dd3661ed69410277f4892d866125e7b01cb20c185403198dad2c60cca9065b28486d170b2c68d81989214866e0e65d18bc781c91059da837d2a6b99a2b5d29b2ecd3d3b9b3fb8cbcf1a26ec5b7b38f5ab4d842959a48383c30f0247b21c1cce12f8e62054594f3eb77f5b76e0e637c47b3ff773def403aff093b9c88f2765ffa6f6f420553395b4283f129a529993b0c8110d55ab1e0b18b4748a6ae7760ee20345aa74a03454c454bb4ae0b8064b966f8b025762a0a44bf3555b43f21cbd6c6cf94550aa708217489245cd4d8225b971a1b6900e10108e9ec81ffc1180a35839a705610a90aba43a608acaa0c9fa27626f60e3bc3fb2e5d6cac61bfe3cdaf6f28ffabd6b1ff57cbf646b3f383c3f4cbddef16379effa0783203c8e1aa8147e389499a06a821045970b05a4b4ca436c9510bc709c230922495482c57ac02fae9723443e2ba41d87448d86c75ad78f05e333ad32576907892cbe9d7643ddf1b4f52fcf7546bdb4d535a69448cb6b47e0001238a6415e8c6b2ed30be1d2d571264d8d4956102df68a5663b35999bea668cd4761545df4aab5e5cc4409a71035350707872703c874770f3938fca0c8d234f456a6366447bef5a2f8d0b75e5b4cdef36a2f5918f5e26513e52bb8d196a100493ea864951cd937d7e46d374baea8b0e123bb958b0f6f52ea4f6a4e2857b865ba4d469d984e3b144c8710c9c6a450f8baed26f34419190d7e3c92e47056e36b1a54faa2f305a59267de929ef8c819a6cdf286241e50e45996826072513522080bd3ebe2c79ae5fa40bc3835e8857593f78c1b535bb35c0c6cf94e75ed151f0e375cf755d3bb76af1ff570f4aa9dfbd9469134ebc9e2e4063375e78dcd035fffe562e5c4ce60e578a568cee01c8806da2800290c71a105ae472e981b53b156705d1cfdd1b62a4c06d692b1fe71ad01c8a39019d407370ab5f5a775665ba7fbb258fbeacb96226c28a1521294248a7d82c55072249036174fd84c47db98144a6d948e240f2ee621e5424adcdc95645eca8fbec3b7507deec18580b2928efdd0af18af3a80b6e9cbfdc1ad775426aeffdfe1e6e77d3aefdbb4378ca24492747070f8bee146b21c1c7e0040b94295b5ea66eec0cef8a14ffc586bffcdef2ea6eebdd16bccf404f1bc09b2159373ef2b5182d496aaf84890ecfa2bd1a1700b4929b5b49e2154c92b789e0a9dca9694c72a6b194f82e11f53c719a4239b5cca56484c530d15b6e48f7454b933b4c6b5bf622320c32ab5505a2021f023c7485f46e2f8877cf827694a801097e79b94d353384eb9feaad26f8ada7051f44dccfb63977fa4beed257fe26f7ac1a783beb587bc208a91e4aa826f1f06f5a15933b0e9a1a8523952c48beb50ecf1b4c82b79c6c5e0dc1d1e57c9f20bcafac29f1d01d4639218fcb19d1054a6de60b749b2fcd2965a2edda7e3d43336a6c629c9123c759d15fdd91e6c63ebe62f0916cbc0d86af3e505890b5b2e03d7c33ec276d6d294239e4c8b51a46d33d94fcccf96d155689a5e9ab436e42b333798e5a991b067f0a857e99d2ffc28e9f443070787270a47b21c1c9e248460c5cbfdd9e16fbd3079e8e33f9d1efee64f782bd3db83d63c14574346457cea35c3efd249142521a2f068f40d424edec879788932179b7faa6c798a4a924a8ea1859ce19f6a974a5447aff4c82a4f1ee94887cebeb5f767a28db3d4c262c3837f44f72f136a87409a54d3ea0dc52c19e63265265b4e487a01dc50d94837e3c79b83aa49bc082634713864bcdeb5b9e95d3b579bb8e14faa3b5fffa7fee8a5bbb96e4a123d87f0834acc4d4d83fac8fd596bb91e27cd4bb23c05d14ac13db87d27eb8957ce511ed41fae4f461fe1417ac5aad74a410dc32dc702c660bd68bdd15b53617a88d20ed88e00c00dc2a353b7dade4a87980efea5c1d837d89af8c3b1ae49677db32c25d14278f9141293c38fb4356c969c2358fafd43c6d1b4b8679a7c201be7b97129e83e3831fa4c005bda13b590c7b564e9e4b5d9d2f4362f4f93a077fc681e541be8830ce0e0e0f004e1489683c39340cacfe2a4cb43c9e16fbea4f5e03fff4276fc3b6f8f9a27fafc26bf9dd7908d2aa930a95ca99c75517b497f3862424509c886a325a852edc2674294a4d86a2cc112d50b0faa3bc9038a917e4c5d27e8a07c691040465ea8744b23a52a6d85b5154c9d3e4c4ff3eca44d9fee4fe1c80816af094a9c5383a9ef0ba9ca829a29c2aa49c35e9384fd45d13bbeec0fac3b5cdf70cdfbaa3b5ef357fee0e6bd5e109eb72928bb562bec1b3958c4711ab716b61481e7e31a2a45112184b4184823c90daf1dd72875cceb868deb4445c87a2ce124e2cf902524928e00f2476da4a2d556c28667eaf82bc3777c354d7b2c794802f4611b94ed5006d0f6d503b5e9c1d152f637ddbe827e9d51394e14667810c840b00a13c2c8e8243feb93a1699a2ba64863986c7bba72e2195ed688fdde91a35ea5bf85d8e8cea75e8dc393802cc86443a9295bf30919fc9cea57b6bdc38507b726cbc1e1fb808c5ea58d7a3eb7ffd2ecd0d75f991dbdfdf5c5f4bd2f2eb2451334f9d65a829b8a14867487230950d750601c5de04814c52a4713489288d3a760328e2200227e6977dd9f54f9f2f91a4a6304d0910e28fb761075302f92bc0c765ebe24a6534965ba1cd5388d5c75500694b44aa2206e4d9b799d9a164855392d95f8fc348e6fe27050d660999e9134acad99aeadddf9d1da96e77cd20c5efa9db03e7c021a5f0b75de51f8e9d2c9f162fedee7340fddf5fae689fbdf9aad2c0d7bcde9205899c2b5a7a652c4b8661092ac0512c2f682e19b92005b99ebb0f2bc245eed3aedd49b2c54479d9db1b6a51dbaa104a8435fd85f90926ca00a8034b1cd75aa92bd4ba1a3587a2cfd8d51b400c897fad76ebc5a02652ecadde139caca2573b2801fe0fa407ed7910da49f38ea376975c478fd6be6aa6baef850b0fe599ff0c7affc76511b4125b9ef1f9e0eca07be00130472338426594603b0866d8bb481863acdaffba3a3a784eff408058eb9111bd3c0815841f955d2a0a66193657983b70b1a1e9d1afd15cf43eeedd1730547b21c1c9e20b22c0d836469303df69de7c5fb3eff63c9f1db7f388c177a82c69cc9b3a609c06a744a0ff295ca0e322d834233018ea198a9d4283a293dcf4cb2f812be9eb022f6f149164d97eaa4a22fd3d2b8c81b36099eaa6e0bba18ab3bdfc783250b5df218e5610a7c63cda71ef1f9e16a1dc9cabdaac940ae5a954153548656a281750f0d6d7dc19f56b6bde4935e7de438f2bc40053b146363665deb912fbe63e1f077de932e1ddbec2d1cae578a1513e50d943a914f1e79b26e8bd7cd1f1890a4244d65bb0aaecd22c1d436e8d4b86dab763b75434816c36b1c311cd1d2a825ca30a784c56157205ba9b6cf707b097b96e561d974ed980d807ec62d37481e73902d9e477a3ccf6b609bb2679170b57292e78a317d6b4dd83b6accc0c63ba2b1cb3e52d9fad20ff9c35b0fe2d25b88774a899fcec85b0ba3862fc02ccf8e17cd931b4c9e56bd3cade1be098b22f73d3e60b0b67cd9c3a56c10b9abe0b6639170e559e879dc95966fdbb27e2157d04814075adf5e8e764ac1e8245d3fac2f4b23c04d4b479e912edb149164535f3f6afa7d6b1e35f5f123fec038eec7b0c5400eab0b47b21c1cbe1738ac9fa5d57c7eff25c9a35f7f6572ec5b6f2966f7bec0b416fc285e305eda82bccc8d88434849d9b59bc2cdf3858c90aa90f0a89253254a1546b4155fa92a75240b67ad37204212e1188f242b10790c3f49076968526df07406592e232d70e728872575dde88ed79d9f42cb530aeb36246b7871b22980f2a5428e4d05442b327e0f9470dfda24ab0c2e5407273e5fdb78dd07fd75cffa5ad83372925135850b165ed6981dc98fdffdfce547bff9aee4e423af36ad99fe6065da70f3d8205d466dc63020256c0b902c5e10c90a5b8cb484bbcadb7a94ba0561faee24abfcc48f80e4953d85113a448be9a99b141bfd02edde9e0e14635baa04cb2561e05fca76f61d0eb4c9881612e3e81b89966cbd017728538a7c38601960e358e31993169c068e4c51eb939191ac366ed24aff4c347ad9476a9b9fffc1cadaab6e2b2a0373885f96f8698c3c8b9a0f7df2a71bc777ff68be7c727bd19a5b17795e14b1c3b059118435cc8ab2363d4507a3ca79cc5a64554acbf21e87257d480ec5b70deb274b0e70a393144b78690a7038b42fc938fb47e685191e8056bcdaf0a17068eb976adb5efcd7c1d8aebb70ca6195e1489683c3770137160d92465f367df7758d873ff3e3f9f4ee37facd93a37e6b0ee46a11c44a459b08409192f8f7a13c61a8a4a8105330ad3427035321a8d3897adf29c9523569efc59c533fa5a2eb06270448b2184e3ef8cc74ba6fdf327f123a51b248836e4af0323796b4adf84f075fff3f05e5b186ed94898a57320a6b48b9625a5e15a6662a439b968391ad7baa6b2efb5065f3f33ee5f54f3ce205958beb6939cf82fce403d7aeecfbeabbe26377bdd32c4d0d048d936190cc8293b44cc40f4e2358107045131498b419a96867a48875cf3a636dd9bae3194e139f0a2e48e79a36baa1741158b4b1f407a6f83868a72339a8917474448d1d8f2a9c0430856d17ddb3ccc285385d48052e4ee6c5c96d52388463df2bfb217f65d778980c65e5b4701af49938aa1b535d6b82fe8db7f56e7fd1ffac6e79e9c7b2eae0ccd37efab0393f3a79f37ffe4ab178f8aaa03169fc78c95451a55cefa61fefd6b6e52c21fb08a77fedd46e216f01b3e9f0f0221d82035e6c23dee3784842dbf0b1495b85405b4a3b91a8237c1ec16228fe119437fc8415fe40bc73dc9fb18c48ae3349ef86a277cb737fad7ed5dbff10016d820eab04b7f0ddc1e17110c77125583cb823ddfbb937351ff9c24fc5876fbf2988177b82d692f1f3d4841072aac0483a74b3032e70e7120c0ace9c230520611ccda2285311c809198a451598aaff18000e921a683e3da2c2e59f8e6ef00d303dc63946928854ccf82d0d270a289f653f2c1ac694cc3445c6e81e59e1b135120227345f353c2151795e0b8ad2904cc8fb6848bbc76455d9b53df70637cdd6d65df5a19e1daffe237fc3b33f1f0cac3f04b279f1edafe4f985d7333a697ad73f1805c1b4c98a0d85571df69266202f1840596a3be89f6d55a21cb0288fd45637eb936e7b8660cbb29f208e9cd4b8fa82040f69d38f276d9f2851b6a7b65a09693b89a8714b6f8d6941374c49a26c7adc338b57a11e4847889af64fbe619973d3529e44bca0484d90a1ef233f3f89278a9513579864d10baa03335ecff03cc23d6d8956de9c1d6becfbf2bf31ad19c8885913268b26cc964d183770df716d1f4c9e8044a13e33ba5b623c1ad4a94fc36f7be218958e268df11c95c08fd3ba3cc6ed84638f5b6ec83728790c1acd2d62f810474377c1f8dcfbad01bb892edc443a48174d938720c83d435ed8337a77b4eeea2fa3b374771087558023590e0ea7014ff29e97adf49ac9bb6e68ddf977bf9adcffe95fcc668f5ce93517a0609a5034244a9e0903ae45a2a224c1e21262000a0b2785ec70bd4e0aa5cc5102a5611c2da0ba825ca36c2b15993c750a4ae2250661251f490e47b0451b13f44118e4cb2d1354512bc1cbf8d4ca2923eac42e65cba768592c0fb735363f9be7e9235936379badbcec2f4fdf110856d5e4952153d446d26060d3b1de2dcffbc3eae56ffcb36074e75d7ea56709b16dd6171f40b482fae0ac37b4edbea8deb7375b99b9c424f11a28b890248315227fd64614fdb8b6b683dd959dc76a5b976d49356c5d1a215652ffa79da33faa5147b8143c2361c44ffd6d0c42be758978926fd902b474c40d69c1d2725b72889c4a92c5785c6fa6241ee40a16379acda8e0b5f5111e61a0cc4914f805033f5b19c94f1e7869be3c39e1d7fa0f79d5c193b917a648a3ccfd6984e6ec7863ff57dfebc70bb5285dd0b78c517f24e67c9da03dcddfbeede4ae848386c7f094766238049230680fd83e1ac3b6210d472db9850af7cbcdd9507c9a121ba7851a838cc170244ba60d5902f6d1a8cf98faa0097ad77ea7b2ee995f445f3ff5a67738eb7024cbc1a10b5cdcee37e7c6d243b7bcbcf5c0277f3e3f7af7dbbd78a10a6502e589a750082c1d55a270cba190e0a0b0842d532f54529077a2a8320ef54389e1244739ac5ae3a9f6d450a98ba864e51c0fe110c18a78243eba268367cb93a59149a876fc52c6f214c1293dc94343db1f8da5a9a967c730aecd5f81d02490a27479be620a3c09fb14d4b551e30f4c34fc818d0fd6b63cffbf05db5ff10f41df9a2308a7057a0ac00f2b2daf6fdda1300c268bd6e298ef876b40622a9c772b0abedcc03662cb9555c66a422ba32578d4a946715963d1215cda66f8613ddb631ed2c281846466657b12a7d6b2968061198041f5f4a969e92fdb9e448ca922bd362923184b63b22f5937fb6ae885863be2b31c5cf343a2c551138ec070817616372fcb1a2736992269f9b5a11379506bf84fb337d8f29513eb1a07bffede309e8fa2640904b405320a99c10f92f33e667da3feb4b63b6df35850aee889539603006c0b8924a751bd65382159f4a6e5716a92235f7cec437e390816a7b77932ea3745cf28bf51795765fd359f359e7b4374b5e14896834389386e558385833b93873ff58ef491cffda239f1d04bb86b7b088255e143200914c2513dc9a755f818492127aff473dd95922e8e065842c5b55332ba00b75565b4545896f16197fab1fc514ba7eff4a81d57cf884b52407c9d62a21fd39353b07996a09ea39b29687a36357bc61ac691339216d3c4b16802a887203229f7bfaa0e98a23e927b3d6b9682f1cb3e5bdff5dadff537bff093617d6886493cd5207b6a0d6cdc178cacbf1b87cb45ab754911d6aa5ec61d3e13101a2e2cb675c65ae391d6b392e38e61c7913372a06d260416e0c895d812982315120207d4816895b23d75529a09976d25a0ad7d88e01a40a17e4c5bc8146c4e01d2861f17edf361407c6cfeb24e0b364750080d8a687cf3d037a11f485f16b2c7f96f3c6cf87ca89069aac46450e869bcbc3d5d387a8d89e72b616d68d2eb195940313249e86980a27172ddf2c1affc52902efa51be2c04cba4e82329f723d33ab0f7139b876d20bea8537ee3531a40c2f1a482cd4ee9c153e25f0a0a6943fac3086166dbd0882f5fce60d8b22d11ca7e43d4af0e1a531f3541cfdadd95f5cffc0c9e246400de61f5e04896c3d31e799e055eba38608eddf1fce4fe0fbf277bf49bef09968f6cf1130ef92f98802358506e329502c155906051f951f041b05109895283d169410a3a359c2a14c15a8615f05094261ce245858b14ec2102535caa9b7e74311fba28509903bde08f64f4ac4d935e882f91f558a16972dd96d88cca405d86e5943fa60fb72cd0f543938591292a7da6a8ad3145ffc686df3f71285a7bf53f5477bceacffc75cfbcd50f6b8d3293a7243c3fc8bcfad864d6bbeee120aa1e0531aa675e348ebaa9705c8774d59266d9ccb36c132a39e917d090426658c752b71cf9649ba3b720ba7e2649aa5ce2c9e8673982c9333ed221c9e2282ad5aae4a35a17603ab4e44728194b214a5b5203d86fe53c83c15db633cb203d97e593626ada1a4e699fac39e40816fd1807a7f99d4a0987327a321505c3e9c3ac8573ade17c79e6fa627972c40f8239bf3a7222f7437e9247137e2aa331b3b671f01bef0de3652fe4f730ed07e1d97ef8d3fb57eb5da788b5b665d490150b9bbe5ad7a5296bcd4e416b1f41cbf024c276425b2ff84863a2ff705e92ed87f0f2e97a4ef347bda6e819317ecfdafb2aebaffda42359ab0f47b21c9ed648d324f2968e6d8a1ffcf4dbe33dfffcabc5e1db5eefb766ab781a355eaa538422003982408542278525a41d15ab6ee248c1c6f314672a1069448242f4f18f71ec741ebd85e0c05ffce8534a53fa4a1c498cf9a8a7c84828688d263f129646200583010114a1cd9109c9890a5a95b82a4bd2459ee6080ccb8dc0348824d783b01cbd609814d1393d68aa3dc6f4ae2dcce0e6a570e4d2cfd577bcea77836daffc876070d37e121049fb290fd45c6d60de1bb9e4debc7fe3b7a1c096d177b6a1fd7b33bf08589b7ed112d2221f990611a1a1b2cb60cbe866182219d42dea95d484750f2282b44974d84ef0433b320d6e80ca565037fda8a841b2d081d8bcec47ec1c62c38767a94849c384848b6138a650aa6ea6afc1714cb081991e091cec726ecada0af88399b35cd2c5e883724bbf91be0f379341cefc46a79735d1719a51b17ce2ea64e1e8155edeccfc9ed16379585f79aa4f1f168dd935cd83b7fc9c1f2ffb5ec2378fb9401d95836a926d3248a67d795c93871d9e907b15b5a7eb21e580be30da6a52b970b1ba79bab418833fe531c392c4c18d7e44b027b0d720439d2644fba67c048cf8b0346e82deb1072b1b9ef55147b2561f8e64393c2d81a77608c2b8b798d9f38ce4fe4ffc64eb912ffcaabf7868a7b732658264c9047913828d2aab337aa0b4857ff011e9a6228ebf14a614693cd2631844537d45e927ff0aebe68f08514a4b0928e9b6c309343d7de34b85a9cd4314a49401a7a8f8249df29cf8319ec4966315dbe5799c64893bd30ef895f45430e75cd44f82551f35a67f5dd3ef5f371dad7de6fb6a3b5efdc7feda6b6e91c5ed4f87d189d3405219f68e9c280626ee0f7c6f324b56c68d170e177e510d406c597b32c5076527448b6dc078812f3bab93a870ed1e49b69066d4bf4cd19571f95285aa61b694f61f69c7f2987d40fa9ea06c2bb6250c553389b46cb5413fe6c798f09672b07d25156d36bad490c8d17eec5f57664099b73504ca2344810f1939f4354956d63245dac075651b92c5a967fbf2f6e1e0092fe86fa0ac29faf253b2dfe48d99f1f8d16fbddb8f97420f32c44b75bb0fde62b26440da2828abd4122c5bc5fc413f603d4afba8611f9107b6d3c0b691d1d392f94a1f9160944fe83f726b6a3c7ebb92fd809f8932152e7c1f337eef1a90ac6bff1924ebe27b03f8228323590e4f3be45916f8cd99f1d6de2fbdbeb9fbc3bf9c1efdce3b8364b12f8ce765ca83a30601849428ba020a53880741a1a5cbdea9d428fb64f407c72a18452d95e14a4b9cf8e9f6c28f8846f1a6e2d2bcdab254fc2d4a57294c2942dbd259dc088104a50c8c07892eca9b7f786a961295029e2948320c2f7e38ce72e8770e46311cae8da35814cab501e3f542180f6e690463977fb9bafdc6dff3b7dc6847af9ef64fbf41a567d91fdafc40d8b761371aa19925cd4ba1ccaa5e91fb4516a35944ad815879268c22a96b922b7e01806dc8b36d80a02b49965680911e26a72454e7077d448955bbd7f1981d0736fd188c648a1932864d4bd2433fb3e9127a5ec374688f248034994f2704ff195bce7541c74d34ed024f169297f4e7147c81236f495fb678f4b9d9dca14b4c3257f3ab0327bddae032223cf54640399275e0abbf0052e907f182ecab564a0b5c2e2b9023585a97edb5786a95d07aecf65282053f6953f5b390f6b76ec693f3f441fd4b3bd103fd04f9b2c77144cbab0e18d383e7829eb187ab13d77dd891acd58723590e4f2b24495cf1978f6e6e3efcf99b9a0f7df197d3e93dcfe742d54a3a0782d584504c41b0a01c4462510f4040b5052209883dee12701466f0b36a4d942be5258e5488f2bc1ecb217ecb24e184f893b81cdd28fd80b64cb579cb0f7e65fa817eb0cb401a0469402f52f54a59244dbae90351cfb062342ae3c87a1b2a787af9a11c6795ba29c201e3f78d65c1c0fa057f6cd7a72b3b5ffbbbfefa677f35a8f6cf2322537000bc204afcbef1a3fee0c6074d96ce17496b0215da57e431bf328d4aa5e243ed7374098720f7e2c70a2785299b42da491695c3706a50d65b954a92bdccf637358cc936653f5412cd16b47f650f10880fd269c766ba125a8de425b63d6f6dfcb233e19ced87326a22e0088c4d41fde4ea10c8d23279e392d39f696c4cbc4cb215e4cdf91dade593cf2a96a606d18f8efbbdfdb3850933bdcf9e1ac81b336b5b07bff68b416bc1f3b325888f54eb06607b49e594d72b24ebf44ba7dc29abd9b689ed21369e34881ceba18d20c94918d25e925cbb3a94324ba365fcc1c393a98e71847a5f75e3751f347ee448d62ac3912c87a70564efabb4d1634edc77edcafd1fffe9c6c15b7ec66b1cdf5189674c685a26f4b85920df98e25a062a090a2f8848519054280184159f4b659cab1492a501f84b19264a0982afed2ecfd2b6868251dcb230b5b499a79c643c9e479e48dbfa311f96498fa584e2c7e985b69b6764fa893e2c37ca8aa47929921e141f3f1cad5744818e1088c66541455035795037a67fadf10637358bc14d7bc389e7fd4965d3cbffd21bbbf45e100a684c87c7008ded55fae6cdc0c40361efd89e3ccf2aa8de2d99092b264b3cbeb567b87124a7d2d87338d2880ad737f568b4adb90e4ea60d017eaecef69d53091615674779aa02b56e9e578245c2ce36663fd3f6a6e11a2f5de72521255ff671cd9f7f42aca44f3144094d507eb83713cb257d500c63b1afe140c274c01211b2460879f1e1252ff2fe74f9e435f9f2f4985f788da067f844ee579b20072cc8c58fc6ec78ebd16ffc7c206bb248b22053a476b48e0565dd6abb9e06992a2c9da5ade0d169674acb925f6d367aea31f788d740b695f9c8083f92acda1847a9f755373ceb83c6ddd7ab0e47b21c9ef248d3340cb2c5e1f8d15b5eb6f8c0c7dfdb3cfced9f34cde38341326f2a7903442336916c3209c242a5485068e18f848adfe7d305c44ab0688bf012a1a60a4a9eeef51046684f5b57f13c9f4c699440d113f9c0210b5ec5431598400388c574e8d0e9273dc53444d9891f04b37a0ac122240eca58e0119665e5673944c4e284a589320d0a0411147e543179a5dfa495c12218dcb8188eecf87265f34bffd0dffaf20ffab273bbdb4be77bc18fea2bfec0c43ebf77ed834516a7696b654751241510a72097b7cc4074409ee4c582d007c922594283c8d4af502285f4071cb17da539714efa16db525bafdbe879902bb43183b753433ab4b57f5079ebda42494592d7fec45ffd635a1d685fb6292ac152b796ad0db8f5501d365d8e90725b0721f9c83f00d1e4b434ea26485bcb57260b277614e9921ff68c1d49fdaaeca975b18f6a154d90ac835fff793f5e9285ef1738c9da5b9db8ee038e64ad3e1cc97278ca4246af4ca3273ff9c095cd073f7dd3f2de2ffe6c3cf3e02bfdace145d9a289b2064410440f4719403a845f411ac9560d4149ae44b1c18800b32a0436ad12429c4431aa40d551022be23a863f2afa541052f9883f14211d4a9ce854df6e5080ca561165380d25b94999583ccd151085abee520603f4a172870b6c8beb330abf62bc5a8ff1abe3261bda92f8039b8f4613d7fdaf62eb0ffdf768dd35b7fad55e2e6e2fe33b7c4fa013f8f5a19379dffafbc36afd8097e5c37e501d47cd573852c537cb4870b96b3e4778b88337fe6534913b736beb6bfbaa8b6dab36db4fda42fef103c3b6a74b7f6123621904867fda2f6d18fb27fd4d40f2dfee35005d5d641fd9ca3869a7133145fcc2d08fe45d0e35aff2408cf457e6ca3e4e12282375488b37190b99c71b9295b9abcdca747f10854b413430570495f862265a4563761c0f723fe7b71683f343b2c4478e1dc9ba70e04896c35312b2b8bdb538141ff8ea8df3f7fcc3bf5edaf7b9f79ae5e9cd7e7cd284f9b2890a2e4ae577c43848c3276cda1048145650087e10c2d65dbd45b14082c9d8009c2a20ad528411e1483783713d57e79c283906911f2b70790cf5258ed3042b7e24a69ca312838386652a05a78c6489cd23f991381a496d965bcec2dd56b4884e65cf05f1f2699ca8c714f561e3d7d72e9be11dbb2bdb6efc1d7ffb0ffd6d6568e3012f70af763f29a0b2b9d5833fb4f5816060e36e93ad445ebcb81d152f6c5da6cf324b36d0546813255bda873cb0606d7fed6b5497b4b5e7208a8c4ab25d753d96fed10767d9ceb4e418a6bbbfc110eaee8e49d848ec93920bca05c372c9f933a12b45c994a9695e5a42fea0c7b20c7280eb95f4707f807c88c91bfd2b4b279e9b2e1cdf1a79d94ad0333c957ad5d645bbd5436b7eb475e06b3fefc78be1854eb282beb1bd15992eac3892b5ca7024cbe129058e5e25495cf5578e6c891ffad8db5afb3ef3ee74f6815786f9921fa6dc85b969c22235b2b123141b4511a7d1385568a74c641b0390114e01f2b5670ac45294a9dc2a8fda106546bd40c5c93499962a514b70daa20ec73932cc458fe8398d2faef2985e6559901a472828344530b32cb074034b1afaf12c470d900ac33000ca4150c8eb9b84c89b7b08906055faf0243b6ecce0b6d41bda74c25f73f53f875b5ef547dec6e77c31a8f62f4844871f08dc29deef1d3b9ed6d73ee8557b8e9aa2d25b043da3b91f54659357d9a49221d1f6681b1260019b0f3ff68f6d5ff612807d807d8bed5bc6937ea7fd8a4dcc3e28c41a2e35687b18c6159b7d5b60432898969dbad697579998240874c22998868dcf30b0cb432d7509498e65a4cdbc514679d122c1b9d4a4a8033fcd4d1e2f6d4b1ad3bbbce5e9feb0d233e35786e70bdfe7f70f359d8b05cdb9b1d6014e175ef8240b7dd391ac730447b21c9e322847affa8be93b6e68ddffe17765fb3ff56eb374f80a6ecdc0afe147456c02881aee67247b1141cbc974091519a4904ee9501994a356f0d7691c551df25c4fa1d636d42214a0b42120655f2dda149619fcf88f700092e2af102c896bfdc45f7e70968ad0e6455f0a4a15965654d3c8d27c1227fc495cba3942c5b0e24f3035181cf0ad410ea470857b1ef4f0a3cec61fdeb2920f6cdd136d7ee1ef7997bceecf83d11df77961a525511dce0ec05cc29ee193fee8ae3bfd810dbbb33429d234d9618aac0605ecc97e59201c9c9e0e42b41bda897d8ceda7d4847d84ed4b5797aff439f610ed4bd2df84b169bf92e002bb485e6d5d9bd76d9836fb2bf9104772350ded979a96d892602755bbfe90a6dd07919ebad9eb188d2779efe819d97d1c67245979c12485951892918c8bc4e3a535e9fcc117142b27c7fdc05f08eaa353ba53bc3c025d1c00c96aeee74816f7c97224cb41e14896c35302711c57fca5c3db5afbbff49ae6c39ffae9f4f8b77fcc6bcd0e1a7e093f6f9a805fc2e7c8158915540a4537171fcba855495844f0411151d4e9140e151f855507aa584a430527e24b62f0acd84abae866789ca743dcf8116948bbed519a6e254557e9df258cc5851f390f7f19b100b9e24895862dcf53e14ab61203c42e324558357965d814bd6b4c31b0792118bbe263d1f697fc8137f1fccf06f5e11372310eab02be38e0f78c4de63d6b1f029f9a8132be34f3c33e361e378e943603e1921e44b76d4831e8af6c6739d263db52eac758244a6c70354270041a423e162e4ad71a40fa150cc2aba2d63eab0bdc69104e92e10fd3a5ad71bb0942c74537e3a98b6ee997eca7f4cf90be8c62d19fab1c190ad72c4433c53dcaed0eb8c758eb8a7c65f2122f59c9c2dac0c934ec5dc203022ff2c247736e5417be5f0c2359a3fb2a1bb826cb91acd58623590e173520a47d6ecd904ddefedc85bbdeff0b8d7d5ffed96261dfb3c36419cfc14b2057b14c0df2e959361885b4e1880f859e0792c5111e1901e2904f39454789ce0f40cbe00060a72de4d70ab252b8898293185698d2bfd40908a3c215beb0da82b66dab2094f2306fe4236591b4cb32c26575a65d23c3430611418a031dc592906d8225e29a6fb0c1e420584505c2b5775d92f7af9989363ceb2fc2cbdffa07fee865777861adc9b41d56191cd5e247b4fb373c0cf74cd15adae407fe605164113fbb9366ba044eda96ed2a2dadadad6d4bc0452f407b4ea7df694cc529244b0896922c3e5e884d8b90c490066d287821583024009a67274d75330d3a3bfea784d04eae2149b0d02fb947589e66a64838fe8a54115746eeb8e691f9c9a8163fa0cc38e8b559cb98d6c244bc78ecd9d9d2e470d4337a348b864e5e146f1f36e7c6e283dff839afb5185db024abda6f4c7dbc1cc97224eb5cc0912c878b13b2f62aa9f82b531b9223df7cd9cafd1ffbc5e6c15bdf1e64cb8351ba68c21ce44a46af20e020fc95869050855c7e85e8f08102a00212218463d9138bca01e12916e583ae243014600c841f2a36f9782f42a8a160843ffd9897b869ab88d3884c52d3a41e5201cb3716ad202419624a2c8bc6d45ffdb1c2d3fa691e2c975e958686ba4456e45814a64ccb443dfa198dde35c60c6c5af286b7de136e7ec1ffe36d7bd5fbc281f5079167c9061dce15fc4acfb2e95bbfdfaff61c46e583791413b91755d23c032f613bb2f5f8f20443a30f0a61921e8a23d8ec4fd2bfe8a77d41cf6b1f100605a712353d9450f41612445225a7e851da2458e2d0c37600ebee18298eb8011c749fb7bfeae28815543d491e3b253aa74e5bf3be423878b13c2c3fcb2af71289669a1813f37be3795fb1327b4dde981d0a6a7dd341b57f2ef723be7d5816fac243419275e06b3fe7c5172ec9f28464ad315ecfe8deeac4b3ffc9bd5db8fa7024cbe1a20317b77bc9726f31bdfbd9f1c31ffbc9e6de4ffd623ebfff06af31ef558aa6e16e905c601b4288cbc261681015e891b8731a922c213550692458229be0c7c5e19056f2c44d12240a4d9ff649d83a04abdb663e4800613aa24d5d224cf12f540c6ef9302c144fe1e1495ec230151c97533afcd3f8341a82b12d0553a1ad798a7c454029ba9ca17a0e914705871104eab029fad6a641ffc46cb0e1597f57b9fc0dbf174c3cff330147549e8660bf8125e67c8e8af851bde10d6e7ea4e8df74b75fed3f90c6cb5b0b530ce154c869434e6b872c9fb4af55d2685df431b1cb631a45792dcaa80084809321087eca85ca580c2728c1adf51827693325e4a7248c9e4c474ec2d85ff8b12c721ff14fd3d77380dc4f0c023ff1449a285ec12942d8be8c1af3ab02245d08c09162122ff46a6e2b22741fd79e816415dc293ee3b924c89b73cf68ce1db8d24b56bca87ffc48ea5fb81f9a5692f5f58b806471246bf4e1ca866b3f6c02b70e73b5e14896c345032a49be3918c627c7d323b7beacf9f047df1d1ffbd64f852b53635e73c10409f7bde2b810e8930876c4117e44254221a44486a3565ca22e5289a35792b6aa0efdf69f1a79ea0728a6642d17051a6df1a3b14291e155988a871026c667be1a8267f59736352029134331352ab032aaf811e5112c5d47a6f9738133d7f1c868808c66714d0b881542640148a4df6bf2dab0313d6b137f68d37c38bcf9de68e373ff24baf4757fe50f6dbbdf7b1a7fab2c49929e46a331b8b4b43482f60de19506c1f959efc3755a9c3ef406363de2079563201623c60f46d01f2a3887a6d685e8ba23bcf638eda974a1df89bff627f6944e9f412c51bc088543e13362e387fdb954ca16ba881dfd48fa3fa1bfb4bb89a826c9e38ee12f2b4f4a21fd5f4f6989e9402489571230c4a791696ff84bf939da0597a4cf6b02d1a23b2852d8b1c9b3c4a4f1c286b43573b5692d7841b567deaff4ad8030a4171ad9129275f01beff65a8b152f5d00ab49a4cd6c3b09ca3a3dbf248b6bb2c6f7461b9ef541cf91ac558723590e1705b8f6ca4f1687f2c93b6e683ef2b17735f77ffae7b299075f1c65cb7e902ce149184f8d229c6940396053b0897083f411016fd75fc14dda1284113816df33a4dae2933dc3ca3fe4bd551f4a72aca8e2b11a1588eaaf24893647c728e0948a591ba1382a26a111b074b7cb461be1d4e62fd324ca7ca9c0249c945242f29c86421e70e4245851bf296ae3c61bdabc120d6fde5ddbfac2ffe16d7fed1f441bafff9257e3e85529919f8680620f0e1e3c70d5b76ffdd6dbbefad5affec291438776f5f4d4e606078766a0accfdb9e607e5869f9039bf67a43db6fa944d56345126ff5c268c8a4cb1c3845c3a2edd905a4b5d9e7b47fb00774608f4a1b963634428a9bfeecdf347adc1ec92aa1e9d2438de607170e792f318e9e17dfb6ada14a1ffcd8333c1104b813d0d749aa322e7cc759de83b4b50fe3981910084f3fbee11b208c7c57d3a4889b22ff04d5d0ea4fe7f7bf309ddb7f45be32d31755fbe7bc7adfbc3121d9e8050125595f7f8f922c8e645da8240b324216be3b92752ee04896c3058f2c4b436f657a7d6bff175fbffcf0477f293ef6ed77f84bc7c6fd78d644c9b2acbdf2451971e20d82454811850e058d42dc9042baee0a4e0a7b2801aeb992d042aa088d4bc5c0d122256b366dbaad29f380afa427a357d630df109e3ac127a4ca4e9394044b8c4845a444275caa4d6dba7206bf14bcb0513ea62c6fa39144222e472874e7f6c0a4519ff1eba385dfbb6e211cdbf5c9face57ffbebff9059f8a06d61d711b8bb20f65d1fdf7dffffc3befbafbddf7ef79f8a553d3279ebfb4b03052ab55a7068786a6822048da0aff1c037d31e5560fdec09607824afd60b132b7d5cb9231b47ac82934f608214454c2ec0aec033c945e62a13d8668132851c08c8b34a4239f66ba5250177e1151c796902bc2d810b4bb8fd4302ffe4a0f559ff2943c14d0e65425eecd2ce53a33255152cfbc474b9b89309aacd90a7007e35ee1715e204e9ea04ce001d99229e2153f4996b7b4168e3f3f5f981c0ba3be29bf6764a6f0b8d503639c6734e7f9762117be5fd024cbeb91355924591f70246bf5e14896c3058d2449227ff1e0cee6031ffc8995435ffc9962fe911b8278c90b656b063ce54209f9601b24593aad063788870a792a232818d8322d8223ee642d1331104e62e40fe1441e51146a3a74312df9e69bc82dfa5950d9e95946f48a90b1602bc9221dcb65360ac7520e5d7f45b1a725d494a8d0e8c35133c902872c13a705edd4a4916913940fd727b161ab02255984092ac654eb3282e50f6e3c59d9f4bc3fad5ff6863ff5c72ebbc38f6a5c41ec008034fb870e1fdaf5d0c37b5ff5e8c147c79bad8637373f7ff9ecccc9ad5114cc0e0d0f1d8fa24a0bfd469ae27cc00fab4dbf7fc37ebfd27330693587b322d81817060d4c92c4eec19ecab5f2ec561de5cdb54e84f623f67f35ec7bd2bf791ff0b2f4890106c79a080fa41fb23fcb9439a3b183e93f8ce6c17f7b07c8318ed4a73ce2793d2dc7fc610885e66bdf829570ecbbb4da81b43fcb3d83004a247152a6efe984bb00e14a5b3049255f99bdda3467d67a451207bd6b0f17817c689a099d3fe8160e42b24cbc2804513e63455306916b86c7f923597d2a2be4b33a6ee1fbb98023590e1724b8b1a8972ef79993f73f337ef89f7fb271e0b33f9f2f1fd91c34670c48960933eeda0ea1915339409440308b81a4916f0f42bec8da0fd87c7a5652255208028722a7143e8c5ca2bd033cd3a05d8a271b4bd2874b7f09b57313e11731b8ce0a4fe139df60148541c3a7722d97806b4e708ec7eac5f451229483f9d14d6f8ea2c129262fcbc5753a720d54564164b210e40a4fa66975bc09c179a2baf5a57f54ddf9fabff40637ee8342bb60a6512e04e4791e1e3e7478d703f73ff086fd070f0ece2fcc9b9546c36bc5ad2d4b4bcb5bf2346df6f50f9ce8eded4d0a0446fd753ac63904778af7fad63f1ad406f7a3481ec8d626902874bd22a26a2ed00772920d86454f63df46c7600f823ffa0e4e48bf8211058ccb1092c51054e2a294791296f6aace1f1f12d0c7b969a986635fe41fddf093d00c42bf0ef800d30693a611376296f79796b3138ebe72443ff1675988320cf39753bc6a8e68d1e081030f571eeeff801b99a6f14eaf31bbc5849599a03634652a3d69819b057134d3738d2e926517becba27e5e124aa47581ba936b2aafb31be78464f58360ad355eefc8c3950dcffea02359ab0f47b21c2e28401079491c57c3e5435b5b07bef8c6d6de0fbd37397eeb8f4280f5905c05fc140794000557507084884a01bf94ec3c126143c1028e510a1deeff6385910a1d9ec0b1f8a9e17420c1b3aa5ef9b4af493098122e1e4ba2e252f00c491c851a6da6c0912ccd47d293702803bf93887ce48d2bf1c335e04274e44d95988059b13cb49139491f6320a63151c5984aaf313d6326eb9bc88b812d0bc19a677ca97ee96b7f3fd876e387fcded16924a685bdc0c1b6665b9d0b202ffff09123973ef8e0436f383a39d91fb79a9c8616d36834268e4f4ede303d35b9697169bededbd33b57abd717410cce4b3d7adcbcb477cd516f70f3ddf581b57798b0de42f7dee485953afa3a1a97536fda2fa5d3c066df90ef21d2832760f847822e36fea44b7124140ef64c25fbec777ca8505b465ea4101cada5cd1fdaf4d5cce4b70c2767db0521caf0409b0094bf128a07e28163eb2e8fd5adb6c655a3de7a5ff25e89bcd08076e2de8147bab2215f38f29c6c796ad4cb93dc8f7a96b2a0da40dbb132ce298ac6cc3848d67bbce682902c19c962f9e59258df74f06a787d6a9f827344b274ba70e41147b2ce0d1cc972b86090c96771e6078ba9db9fd77ce0433fd73cf0b95f3227ef7986b732ebfbf1a2f149b0a0240208da007f424e720a6b551084bc404885c2a7774eb341085931a36f52315069ca38b44588d385a0729a8a086eeef323d38ea27470a68cc71429ca8458d1a00cdd048b90bc0494f71c8140b94aa2c504f874cf45be14beb6fca200b948988712061704cd97737d1733a590ac0d8260ad6d157d1b8e8413cffff3ca95eff8fd60dd336ff12b3d2ba5a4bda0c135767e96d68b7869d08baa5a39e5f5af1658954740b2ee7be081374d4e1eef5969364c9ac6a6d96899b98525b3b830df3b3b3bfbcca3478fbdb4d56af8c323c387fafb0716d136e76744d043cfab0dcc07c35b1f32435bef083c6fc6c40b3bd087fafcac150a8f667f92bec47ea5a48acdcf5ec8ae225f2d80cd30128e350d4bd6274a7d2bc1a74d9ac55f19ed4240a5970c4c47d93b256d6d27fe723b122a79f67bbd232492406e95f250e36a5a965ce8ada46e8116bc9d8ede733a3a27d3f7f0267108515e6e5c2a13f069d37871d324c9ca40bc78f4867461f2da6265ba2fec199ef4ea230b88969d2b124f14add9b1e6fe7f79af1f2fc9160e2459bac9aa5e6bbb2cb8f88e6ce882d47de92c6d058f4e3b535adf2fc9e2c8b7ef48d6398523590e170464edd5f2a3db92473ef3d678ffe77fba98bcf3ad5e6bb127682d805871aa802357bad78e6c280a8141c560a54d2973449a5049f09742a75433a5505701a422870121bce1a2a15251414ea8b0a3a0a7ade1796c5d0881032a0cb12145b9089d01322424029461453fe7e0484c983bcec3e2c8158c2557b628dcbf4b3821fe289465ca1346a687380519d64c0e72950f6c34f9d0b62533bcf36b954b5ef57bdee6977f28e85b730489d9a25de0486a66fed19df1f1dd2f482777bf100abfe5f78c4ee162d968ab863ccf83c3870f5fb667cffd6f9d9c9cac369b4d56ba7ced25c952102eeeba2e233fb58585c56b674e9e1ca9d52ad34343c35320c3e754599f02b4ab7cb4bb6fe3c37ea56f6f162fadcd82683dfa7fc4decb46cf398d868eab5372f847bfe1e82d0994cf37fce04fb2ce4713ee53c597359454696f97becfee83fab06fd2ead53275265876ad335401ab858f198c63ef35318c0323feb04f354c8a361d20888c51fad3d6ab227837e09823d7525e949f5399384dcec8f2a65c1c9f256010dceea1399ead4c5f972f4f8fa30e9682cae05c1e54b856cb26b8aa289ab363ad035ffbb96e92258f6072bd0aede4b82aebd18d7340b2f890e648d6b98523590ee715fa599c951e73e2be6b933dfff49ef8e14ffea2b7f0e855feca09cf4f1a268002e46815891547af3854255b1c51ab948a8f02848a824fbd7284a77a550c642d6a78c4540811dce2d35110046571c75d12adf2982e1a4baed4d08b53313e1f9945e0d164a2ebe00fde201f8d465a5c22259ff5c139517e082f2a048a9d09f939fc68c3e05499abcf8d9c4cecd54c511f345effba38ed9b980fc69ff18fd59daffb437fc3f53707d5be45097a8143d7d8a53dd9e4ee1b9a0f7dfebdcdc97bdede9c7ef8c630f04e46a397deb9da9b22165cf87ee8f0e5f7eed9f3b6a9a9e361c2e942f417f69918243e497313279969345ba6d56c4633b333cf04d95adbdfdf77b8b7b76f260cc30464c57686730ebec4e00d6cda1b44038fa6ad95be3c8d371428509e676191c725c962484e26b2efa22f41bbcbf73985d093642951919ec77ec674e597dd9823c4ecafec8fdd4487d0be2f86e74bb0ffab2ff343dfb74f2a128636ef3d4d4bce11729e71358cbd6798166d090b5bcba55490650ef2500916d390a870f37e07c9ca4032f364c564e992295a0b952c5e7c46ba74f40a3f6be47ecfd8f1d4abca06a6ab4e941b2059ba85437b33529d7e6539f1cff24a19e073a6a29432479ca5add0388ad22e2dad471ce2582f4f8f1dc9ba70e04896c37942e1c5715cf51b53ebb347bff6caf4e18ffd5c72fcf61ff31bd3bd41bc6cfc34856885b005f9e05b497c0a97b940d524c29f283954d64038e358dea8124f1cd329825d8f29be251c7ebb158c1cc3c8e77718568e340cdd2461ddb0c2d1da1cc122a9d2a772f5e729122ce6c7e9179f0bd6993ec320ace6a3429023589c92b4230f055f610f42f9a0b357a99bb436ca8d038d3fbc6dd11bddf1edda86ebffdcdbfacabf094777ece1abff48e48206ae9b975d2b168f6c4d8fde7a636bef57fe553a75ff4d45bcb0a1c8e281a867e4c170c335377b417555bfa12824ebf0a1cbf6dcbfe76d93939341b3054e873621d1e26816c71cd92a6906a51dc7205a2d93b4e29db3b3331b1b2b2bd55aadbe58abd51ae779ab87cceb5d73341ade747f18560f794105cc295c63fca05ac86eea283b2e825b1f509febc816fa388916fb3c8889de0116ea2659e1acb4f469c495af24d0dd364ca7bc0fda361f5c24821cda7e6f957e99b4c2ba69b7fd110e61f50e2c8f6184689569f01c17e3eb9bbcbcc9e08f7fe62cd788701ab25ca3c6071a96df4b705fc5ebd3e6c9abccd2f1a1308c96fddad06cae5b3d68945540d19a1b8d0fdef26e0f448f9b9172a1be5d93c5b20bc9520180b2b72ba20347b29e927024cbe19c4346afb2a5fee2f81dcf4befffe0bbd3fd9ffc656ff6c1e7f88d19dfb4e68dcf6f0e229c9020793d1d2e087a790a27ab112500b12142ab142a942770b6850dcf8b00a2413a1460a209e4240ce2e0b87364a1476d3fa4c168ddc69e2c8b821c342f3de60816958765813039d763594550d236fcf095790a5ea6c1913ab9d690a6620aee7b55e93779ffc6048a7536da70cddf54af78fbeff81b9ef385f02259dcce76f6d3958174f2eeebe3073efe0bf1de2fbd379bd9fb9c6279d2f7f396aead19da7467b8fe595ff2c2d52759478e1ebdf4fe3df7bf6d7a4a4916eb3e431948b4d895489e68b770aeb1bc6c92b86516e6e6b61d3f72e44593c78f5f52147963746cec1888567cbe46b564517c6d78ca1fdd7567387af9378aa499e5f1e21526f02a268f7df63359fb27537f644ee88b2029ec9cec953cc629e97f728e7fb86ede6f3222c58703762dc611208e98f2726db7836d1f56d8afe5bea0bbfdd3650372de1afeb4dd488f699686dd5a88969c02b582c337110c4a886be0dafe94d720e1350d2f403872171adee779c3700b05132f0cb4e68f5c5f2c4eee00e169853da393a8bc555b145f3467c7b919a91f2f567cee9355922c5ea35c0fa14200e56ffb74704e49d62849d60740b29eb65f8038577024cbe19c2249e28abf726c22dd7ff3eb92873ff99ee2f877de619ab3037e631a32897b5f5158403888ac28b746a050c2b10fedc03f9ee42fc5082184061296d323a55ce918fc88f01129af6357faa7e71e8bd3ce74097326d12656f658c240a9899463580a54aee3563742692480b9d38711a12624d99ce491d3390147b1229307352158a66f5d6106372e9aa12d775726aefb33b3fdd57f150c6edacb57fc99c4850ebec410344eae4b0eddf2da74df577e2639befb47cce2f181209e355eb28c6b66bb042618dabc3b047104c95aed3dbdbc471f7df4ca3d0f3cf8d6e3935341abd5449fa2b2662bb0edb437499ba42988486eb23831cdc68a29d2acd25859d935333bb3637979d9ebebeb9d1918185c42d0f3b6564b46b5aafdb3c1c0c45ed4e4c92c4d37e0061828a035b98e8ffd4e17b8c345c2c46e4876059bf7925c35fb1e1c72dbc865b0573200ef3906e209ba195823953d588fe544c7ea3ee4bd2187f8a1fb14f0b8343a625ca29d4779883f8eee065c612ff790e62951f88dd1b2e0857573440b61f8391e2f47fba6fc483c22a6e9e6ac31bb2b5f9aea0f7b86a6bdfae812523aeb239245736e3439f8f577fbf152d54b16c006913fcb2386455597d867cafb9c92acb1872b1baee348962359ab0c47b21cce09a0d03c2f5be935fca8f39e0fff4ceb91cffe6230b7ff593272d55c80404cf48d21d9b83330391e4d453c517842428842d47440b6344d8a104e1172b58915242269204b84c4b4253d41078c3ca15b0104252361e8666cfa972883f3bc1564255752210f9343285231f02c9fa0e92ef584c4a12debace0e093b8523b2bf4f04725c7eb8492c883d06441d56451bf49eb6b32bf6fc354b0e1dabfacec7cf5ffe34fdcf0a9a067f824a25d1448f912c3d2d16df1becfbf33dbfbf9ff233ff1c073fde5639ed798036d6ec9b615dcac951fb20e0637df176e78f617bca8be52465f15a0df700b875df7de77df4d5353537e1cb7a4fea5f9f9316df601b6358848843609d92e596e9266d3b410b6b5d2308da5e5f5470e1f7afe89e9e90d61259a191a1a9e0ec3303e5f448bf02a7df3fef0f6dd416df8812c690d1679b6d9e4494516b0b34fa2bf71dd99122cf439fb762e8cf44bf6415e3aba3e9732ca349cf454f6d7aefb417a6dc7d6beacaef63d501e5a4bea560fe53e3a93c18f06b201013a99b31014dc74dc6cb80d9c2882522ea08de4e3d7cc9d65c0f5e10a24015e7a081370fa30475b3717c692f923d7e78b939bfc6c250bfa6503d3b3baf96cfbb33af1621506ac4649168b8aabd53e66fb19acc7e09c922c8e643992752ee04896c3aa43a78d1687d283df7845eb818ffc427ee45bef0a9a2707a3d69cf1920624009ebc2130652a0d72814f795cab24d289808450010a2fb55484c02f10e9c2030dcf312e0d427fba28cc19ba23743aa0e8130bd0b08f01bc74b13b4951790c4bf2b0f219369d725afcb8145e49962a132ab572025412817290346170dd59583579d46392faa8c9fb3734bcbef57bab5baeffc3e8d2d7fe2f7f70f303de4522084964bcb4d957cc3c7075fac8e7de951eb9f5e7fc85fd1bcdca31106990e9b461422812bee196736a14e4c61fd8f460b8f1facf7a51cf7299ccaa4048d6e123bbeebbfffe9ba641b25a31aa14fd47c804d72ce12fcf4842729411be689714c43fcb60d2d4a4205a2466711a571b8de5ab6666e7d6a7691a8f8e8e1da954cedd1b6c67821754629f1b98d687f6e5f14a6f96b62e15e688be26bd9023c042b254f58a29ef2d55fc38a53d5a20d3d8f42bef47f1c3e5a99ff6eb53c073d659fef17ee41ffbb81c9f62bad1e5cfff3221fae06e917b479e5f384f48e24b62252357a54140b9e7c9107967e23ae5e3f038e21d17f0cd64b4233f73e3e569e825f1ae7479721764c7b2d7377e24f7cfe29e5add242b59c483231e28784d7a6982522ab4ebf5149c4392e5bb91ac730647b21c5615505491b77c7c53fcc8e7dfd27ae863ffda9b79f0e561b2e405c90a9e3053556841007909e109414a594a65c0e9332b494a79021101514161c2032800952310c4307cfe262f9309451232f8514833b40a7aa293a6daa5bffc500ce939a6a30a85be54347a4efc61dbf4ac80e301a72c9558215f78e36a247f9912e3a6a954e4327a8032c070b17ce68526f5a00b8341938160815cad54c62efd5c7de7ab7ec7dff2a24ff9b5218e5e95995cd810221dcf8fa6fb6f7e53f2d0677f257ef4ebef30cb937d3e77e84f9ad01fb1b41309162b969f03f2a21a48d6c67de1a61b3ee545bd9c7e5b4d78870f1fbaec81fb1f7cfbf4d4b41f375b6821b622a1e4aac840f6a1a4b59770ea89e56573a1bcf0247d6f926825b1995d98db7e7266e6725c78737ccdda83205a54d6e7adad3c1005bf77fc78581f7b245f39399ae5e95690a510466e1be993b816de6fbcd7d816ec8afaeca2235dd2b7a55678d1ecbbac0940aa492f8d21785f889705ee032143653c392b36fd98bdda1dd3f5c7a01a430cd17990c279a4cb43b90cb60302c97dce402025fce335700492e0c7e159c6100171f15a0cf870a901a4119a17922269acc996a79f5dacccf5847da387b26860f66c10ada231b3a675e01b5cf85e2d5af33292a53241e582105a815ec763704e47b21cc93a577024cb6155201b8b7a717f71e28167b41efaf44f340edffa33fedcbeab3c7973905b33c4bac70de480084f102d3b92c04101ddcf0722020a41c4067f1058de2014438b21201b458fa8705511a3e7f8ca37038ab0a67297142d28c0ad0b0e3945a2a6e16d3a568851b0f3d9b82d1cad02623af853c5c0340b7992565f55185414f26dc580a337dcee819308a1c9a23e980163fad6344cff9a99cae8651febd9f9437fe4af7bd62dfe2a4f9f9d2d14d0e271ab558b9ad313c981afbda975e0cbff9ffcc481ebcdca31df6bcc9a1064da98f2333065bb711d9af12bc60f6106371df027aeff9457e95b9004570fdee12347b84fd64d9393933ec912db9bea4fd4abcc95c181f693ef464abfc2bff43ff407f4d5344f4c2b4d4d2b8e4da3d934499e8e35569a97371a2ba6b7a73e53aff770fa293d6f64cbf30baf3e7cc2af0e3feae5c57c91c563be1f54f1185305c9c0a570d25aef39f671b97e8e2203a4583c96fbc4b61102b2bfd3b4ef08d48dc4143f85f674055dbc83d4558ede9677147df4fea13fc183ce7dc47f4eafcb116ce6a8474ab0f8062ef7a1e334bd0e1cc2903c914cc11df01e933fc6c31fca2f72c0b627896486764f1293155e5fbabcf0ec22598ca29ec1a35e38d848c1c0505fec0d4f0a45e3e4bad6a3dff859af3957f1123c3394d3852c9b6cd9c2fa9490b0f5c24ec5392459a667d491ac730447b21cce3af4a3ce0776b4eefbd88fc47b3ff7b3c9d13bdfc93d64bc7801423495db9e02330773e113b48a79004292c29353359c06e493378544294fe084fc2b658755061432a2203c8e65a940a3112103454101cf23350807e926ca5e12613c55a6f2542cfe5a363d4737cad896578ccf03f5d05490833cc5a3cc485b3ef9c310e2a6c101f708e03fcac34d218bb06692b0d798fe4d8519d8bc505973e5677a76bcf2f7832daff8fb7078fb835e70712c6e97d1abd6e2a8397edbcb571efac2bb5bfbbef60bc5c2a14d61e3a489b2652818cf446c06b495acb1c3f54b3b906c8655489faaf106371df4375eff71afd28f47ff558577f8d0e1cbefdbb3e7ed535327400c9bd2369c86e2f61d2c19a77622763af6094608d0aee8661c2d01a132297b9a34baa77b6c216e9ac443c78e1d7fd1c10307af993d39d33f383434d9dbdb3b7ffe8816287deff8717f74d777f281addfa8567a66b2d6f2159e5fa9a123fa7ed694fe2df720eebc1cf7230b4a1fb613fb27fb3caf5bee9732ac5cb7268f1ff917c37b41a6c4db7f7a2ff0c142ceb5ff4aff53fcec1fd352dba6aabfa87b19bd427de3c9456406fed822749134311c1f6a749d1972e43dc69ec76b601958701af9f7409411bb1cd5e2f461da587c563a7f6857b17c7c388c2a0daf363807f6ffa48847b172627d7ce85b3fed35e73b1f8866cd6af61a465cbc263d3e05e7806479b501e3d5654d962359e7088e64399c3514dc9a21497abc93f75d13dff90fbfd2baffe3bfea2f1ed9e5b5e67cafc57d63620412d1dd7e6d3e2b9f4429062812b856844f7d4ab054c08b133243c4b328411a15fcb2560bc2c9be51a586e254853cba7829f049ca18b62374e42d400105b8da14e804c5b0842b8fe9265415e82f6d757b10f4281d950d8cac09118363d1052c1fc246201651bfc92a8326a9ad6d99de0d53f54dcff9d3fa156ffeefe1da67de1250c07be749397f1fe0e815bf2fe937a73625076e7e73e3a1cffe7a766cf70f794bc7ebfed231534996b5ad48527039763432471d704d4dc1971bc20a4ccd78fd1b0f051b6ff8e77342b20e1fbe7ccf9e0740b2a6bcb819a3ad502a9888440b0168d8ff5856f652ee1dc5b7d838fd4c9225641ee567dfe0a017d769ad2caf98a5a5c560697169f3e123475ece3db5c6d7ac79646060a009120656731eda93442bacb42a036b8f98e1ed77a1c473596b690bfa724f91a7151f9d9257418245d5abfd186dc57b83f79fd4812d366cf8733a9cf70f7da5efe347af8c2d6def35bd07e49e933fda7ade1a3ea4f07e642839b689a903bffc23f4fe26b16299b2800497f9e38fe44a0cda086140d9c5d64f60b11c9a026d36311fb6a45800af96fbd67959cb70b36393aef8696b6e5b3a77ec65ad85a39b83b0361df40e4d652664dbb1a99f3048b292c3b7fe2bafc535591cc9e27756499c58ceb20062c3c716b11be78064995abf2359e7188e64399c15707ad0344faecb0edcfcea74cfc77ec93f76fb4d61b218f2c9d91414362a0879db67b90a760a4f8e58e9c8123a23dd1007a5dca03217816a255ddb467006a1f2136145a22629232e9f646567451e8af89533e261139693f8653a14dc62e021025aa1a959654043b72a001a82d724c7d6eefe9304991ed419d72087555340b815fd6b4d3eb075d91fdbf9b59eed2ff86fc196977e3018d87010797f5f02fd7c01bc21f05b7323c5f4eee7b71efed4bf8af77fe5bddef2d1ed616bce04e9b20941a4519ba83c52920ceda735435b46059906bc7ca9939a31031347c289eb3fe255cf01c93ac2cfeaecb969721224ab8572a2e1646a979a58ca4b322ca56770693e169ffd8c9b7c723fa620e0a69ff0e40978e639ce25a989635920efcfcf2d5cf7e8a307af9c5f98aff4f5f5cdf6f7f79fbfef1f023ef71f1bda727f6574db6d5e9e66a9f1b799b05ac37de5cbcb07b8365923cf364278d6827cf7535a4a7d085c6d1b1d37ef09549278b0d7ab1fa1b79afa59c39fb69b900306541fb967e4986d001b8752cdb89db55c7a46b82f4e337719bdc209a175b06d6ab4e9b25721d38df4c16dc6db5c450e47bfd13439a7b3633f8f1777c68b87aff6e246e1577ae6fdfa30fae4136fbba271724d76ecf677f9ada57a817b816b4e499c38cac6fa95ba1248e6f8a52fcba2a5d4d2f37ac4ea82bd2218165ca30b1e9f6451f6c915b753e686b55e65c0989e71b7f0fd1cc2912c871f189c1ef4e60fee68ddfb919f4ceefff4af06271fbc216c9df4fcf2fb5dbcd945c44008d87557bce9fd2030f20d3f2ab952108858c63fdd1411225ced394811da94269a02c3c1c6bf4c0fc81f4816474a64f40ac7622388154014687443388997a4437fbad547ecf2699b3fb4a55c5448e5b18c52f13c40a16f217e728ef972817b8447ed8a29aa3d26eb9bc84defdab970ecb20fd476bdf6f783891bbe14d406661983d12e7cc49562f1f8c6f8c0cd6f5e79e433ff3a3d7ec79bcde2a1deb0352304cb2f6288765e0aea98e458dab504db87a340242c3824c9f2c31ee3f76f3c1a4c3c07246b604e03ae1abc43870e5d7edf7d7b6e9a9ee674a1aec9a28e62af1232cfe12996996dcc9d52d9df609178b1bff9245921d579d98fb93b3c4c2a242b368d46c3ac2c37bcd999d96d93c7275f353b3b333ad03ff0e8e0e0e054103cf9b53e3f28fca0127bbde387cdd0b63b518e49d39cdb9ee5793fae2d440f15f22b779a949077141d3a4aa460bf67bda8d19e8fdf8293617411f67ee81ce99ffaa86f69e347ce303da66dfd89d28f76fb61aa2b80d2bad2c08fe9c8bd28d7a0ed45482a70eb48375b18d7049b6da82419c7a09c8549d08e20dc7818f44094937879cdd2e4c1579895a9f14a7de0b05f1b9c2d74a7784df8bba1353f9a1eb9ed5d5ebcd46b703fa063a04425e880613dda322a583efe6bf7907152d6813512d1da16d68d362b9d2c9ead031a4be7e8660dd3708b673ccc8064ad31a63efa7065c291ac730147b21c9e34386d845bbac74ced7e56e3de8ffc7c7ce0d677fbad85f5413c032ed390273986a0d856a1c8273ab82914f1c7fd9284f350864018ca0695f8a3a8a111f18bc73a59d3032783893401383dc734f9f4cae75859972142bf8ccbf3302260240a02c81363e9b686e7a400841cc05bf3204113418ed3f28a3847e0289c616411315414cb2d5e08cf6b935100c4a152ceb9b9685437597d8dc9fbc63333b8f54865e2d97f1cee7cf5df78c3dbefbd58b666c035420734ebe9d483d7c4fb3ef7638dc3df7e9799df7f2dc9952f4fec312e3997b7b9e48fa305f863fbf0495b9a4caa8a1589ea413fc0b5837cd68ddfb7fe78b0f1fa0f9d2b92b587fb644d9723596c38b42b3fe4cdf6d337d6505834bab4279c6233320e3822c10b913e8a6bd12920edb1845c1ed2c34307fb873fbfb070f5c2fcfce8c060ffa3030383b3e7f3933c845fe95d32031b1f0aaafd7b8b786518e46103ca5ce14b18bc3e2a782558fa8625477a6c0dc8149dd415c3b131796fa02e5827e2a775c6b0bceb78d3300dfad2f04f42b03fb4ed0e4920f4f354cca38c539ea32523e1a5917b5d4f956995394978d86c08b89927cb4662af4d85b64250199594b37088dbb629efef1445cb83bcd5b8ca5b995eef65491cf48d1f2bfcefbd4d47d198198f0f7fe727f295b9be823bbe8364e9d2075e2985846406f047eb498ac59288bfba4f8164a9e1d526e8d6703aea88d2e35477dfe215a9bc2b65205c7911816471246b8c23590f56269ef52147b2561f8e64393c2964591afa31f7befaeaabe2073efd0bc9c15bdf1135a66a413a67c27c05f73717b8f335788a00b9e5f14b50e0a9b0539105e12dc2410504850d5dfaf48a0008c805d3a29819a94c514353b830756e47ca1131d84840e817a26a1a54f84c870247440dfc29d460830ca850d738541c14df4adce8071e8068329197e15ab8e78e943f3741a0248b4a29cda89c7006f971544037178d4c5ae9375975a0c8fa3734f2fe8903d5ad2ff8a368e7ebfed6ef5bf728aee5094f439c4f70713bf7be4a27775fdf7cf093ef4e1efde64f16cb4727fcc69409d205d40d9ed6a55e54416bebc0e047da86368fa9e868a40d50777ed51451c5f803eba6838d377cc0ab0e72446f35c1355997de7fff036f9f947db29a327225aa1645923e208526d5605bc22d9a8ffd80fd8cfd8fd38bb806900de96ff093be065bd280e154789a66a6d96cca7aad38492e9f9b9bdb9667e9f21047b4c2303eafa35a61b5251f9aee9fb82f6d2d8771b27249e147615670522ec3fdc3ab477bf2bee1da25ad0dde36727dac0b6965de8bfcce66d9c03825f71c5d5a57a833469033daee4a9e9090d8307ca8c269bd0769db6ad1b0167277f29e84418e62d348c66240926991811174230dfbc20a8bcab69332305f9836cab6e31a2f922c2f4d457e4568c7a235b7b3689cdc85b37361efd8f1c4ab34b9c64ee5d563c11ddf5b876efb89b4393f6092152497826cb35fe1e18fe9f13e61bdc070049165a4619d5092d03065a91b26d8cec7da1a5e8dfa493f056c9deb395485566c2903d5972b0f75246bd4783de30f80647dd891acd58723590edf176471bb69d4cdec819de9fe2fbf393df095f706738fbc3c8ae7bc20e1db83205846d72208da4240459c1520b4db420512806247042520bef8b721bbc1232b4b79a4b21876fbb703bbd6413380d095630837096c057a2989e597e9a95b7c114d9e98517651087c33d0e3932e4e2080ac098370d665c430107479503539c895e95d6bf2a16db1e9df381dadb9e2e3b56d2ffe136fc3f33f1bf40c9f408a52ea0b1d5c67e73567d66647bef9f278df977e2a99bce3ed5eeb64cd6f9148374a7205452297a36dcbeaa32d6fa8893feb93752d67c51632837a2aa2aaf1fa264e8413d77fc0ab0dcee0e46ac23b76ecd825f7dcb3fb47a6a74ff8fc3e21a1a31e340c515e05dd25c4891f3dcdfe617ba57ad2dd0dab80331038ae80225aad78dbf1e393cf5f589caf54ab95a5d1d1b1130877dede208542cebd9ed129d3bbeefe20ac1e2b8a6cd4f32b239917445c0cefc1b0ff13211f267c259232a22c67d896daea02b6b5d48bd68dd62243f15eb3f5cbd6678c4ea5f14846a8712ff2c1a783320ec0a07c6b9736effb32aab491f63540c26a6fd35c34be12356b34fce950c286ab91287cc394654f6513d3c034391ab5266f9cbc1c64ab0682dcf42bf5e5dc3ff34ef15c93951cbeedc77390acbcb50459d992fea03d9ff7006a4f185f495cad2d784c72386f0bacb6d6951aad91ce35493ee2d674c87d194a491873416b70bfbe1a49d618491647b21cc93a077024cbe109234dd3d06bccac490f7ee3c6e6bd1ffea5f4d16fbcc75f3ebe356c9d3461b60421d582e84d8578880c921b5f6f72b1e994a7572b2200716818fbf4aa82a31d424152042f7bca92204dab14f0a745e984a7c0457c91a46520f8e95b4efc53c521a52aa596fc5201739a10075e84a7c00a281749559e405c52703214fc48be82483617f5f8ddc1da70e1f56d6a44c35b6fab5c72e37ff62e79d55f87e357dee157577dc3cdb3029906f64ccd2c3cba35ddf7f9b7c6fb3ef7cbdef47d2f378d93bed79a85026a81644111a12e3a4419b5d76e3fdaf08572c6115cf863bd96dedc1f802f02701b07af6ffdc960c3733ee0d565e3d55503af8924ebde7beffdd1a9a9c9809fca916d18704e8a24a1f0ab8e5360cfb7c3947677505572e849b8661ad9de21d5b55a4b4b4bfce874ffd4d4f48ba7a6a62eab542a474647fb273d2f3c8f7b6a7945501f9a0d862fd91d0d6db83b6f2ef4e669baa5c8e38ac9525c8ad2a9d00f41b2d0af714df20d443e48a02d5968aeade43d22d7ce246d5fc00ffb02e32bc196d07244486cf61118196901cadb4efb8eb86dbfe221ea34d77b95bf963888c13f53a69f74b12ed38dd38f09bdef61e39c6d3f06e3278928c7fcac658214fd24591a4de71e7d51b670f4d2226916d1c0ba43995f6dd8386d3467c693c3b7beab688064a58b264ff50d568e5af19af9c02679499d41464ac1590e9ee04f3718109614dc5e33cbcb3c510b8c0bd8ebd2b2d0a5278464c194b528b62359e7078e64397c4fc8945112f79af9472e6f3ef8a977341efdfa4f99930fbcd28b972b4132678ab409814be24181aa43f72214ec4d0eb750120a6884e06daf67487ef488a08b4296b618fcb4dd6578c693efb2f1d886f528c48852628964ee188ace8ef0862579e2b8f450d14a1b428cde744bf03202bc596ed9d38b4ffa0c4402c1cd84a28a29a21e934683a6a88d18af7f53d31b9838118d5ffeb1cab697fd0f6fc3f55f0ef9ddc18b606b0682df1d0c5ab363c9913b6f881ffafc3b9323b7fd44b170e43213cf193f5952c5532a4e69034af3b606b3b62a030bad576d2b2a55bed5e605152159a66ffd6c34f1ec7fe2269a1a7a75a024ebe825bb77dff3a3d3d3d325c9d2736c67ed5b4f149d6beb46b7c225c9e2a2781a122daed34af9799e34db7cfcf8e4b3969656bcfebebe9351a5b21204013f347d5efa07f764f37bc68f15f5b5f7857e3e9f272b5b4d54af8158457ceb5008718eebc0c3062e455a9d235a3ab2c504f42e61e9658d1b1fb0f820057fdef3bc285b2b9676d9ee22d37925da648131e096be25c7ea2bd3b27449badde8cee1bbe08c41780d5a565e0fd1ce93fd15a630b1b4214b9525e996d6f2cc257e91ae84b59e79dfab26e8cbfc76a514a958995e971cfdd63bbce6dc609e704d5653ef15f405ae3fd50712353a8a05e0476d9bb3bd776848cbf4984b26ba21718076bdc935d0a5b523240bb0b548db91acf30347b21cbe2bb8f62a689e589b1cfcf26b9b0f7de23df1c1affeb4694c6e81223645b60c1343aec6102410c41028fa44097122b2016e3e01f358fc5520cb9a25384440403ed1576402040f45840a238a061a1d525742a54f84345660f10d6b3b2d61d77be8a89712012b74b8185b46b204cc8feb3c6050368a6f0f125f9e34255d712881a0419a714605a98ba5bd80a35721ee1e5dd85e54fa4ddeb3b6f0fb2696a3919d5fa9ed7ce57f09b6bffaaffd91edf7cb2bf4170164f42a6bd68b9987af6c3ef8c99f5879e88bef691dbefd4d66e5c48869ce99205e32a1494c88bae0ba9db62a60758bad6dac47b6056d28740ffec0c9361765eb9364714dd686995048d6c8aa93ace3c78f6d2f49564892c5414a822594bef403804ace0279896296355f7013749368adac705fada5f143870ebff2c4f489ed88b6dcd7d7335ba99cc56fe87ddff00a3e0878c3db7747036bef36691681586de11304fa3b9eaf74dd6192f03e0b753da2cf8d4b71dfe0b2e5dec475eaf5721d92de36e51d2a903b562a5afb89ac5d943a53a36e862e477c24b682a3a092163c1edb4a4fb0ddcac43aa44a658075b399689f0ef6ef9c7d9bf73fe45c9236c7e285c9ebf2b943db82a295f3d3575ed49b20b22996a7d6e7476ff951af353b58c4fcac4e4bee15f67e3fd0a948c948caccfc991f8ce45b1abebc23848a0fa4b2028db1cbf3ea4f63e3dad17fbd0ebaf4d891ac0b078e64399c11500eb84fb9f6eae0cef891cfbe3ddef7f99fcf670fbcc46bce84f25659b664026ecf0072c5fb5ae951293c705ff3e617a1580a107d6aa51f6e779103a51420e851fa5bf52c62817ee2d6a73e889f52dc480200cf33ae12b10eb1529b71f82b236c2807e3cab3354ea89822c9c22f8428c3122a7c3b6e59674505c2785022dc7682d383f251e7a00272b5c164bd9b62bf6fc374347ef93fd5b6bdec4f8389eb6f0eaafdf3783a3e4f4af3fb03472afdd6fc487cfccee7c60f7df6c79b07bffd53c5e2f19d61f3841fe0893ccc564c00e549e527df86631503524f52c9d2b8529fdade84fa09248c3ad90e541a329215d58c0792156c1092355d86581590641d3b767cfbdd77dffd8ed52259342455d658483f2a4916d7829168a569eaa559b6736666f6dad99999be7aad3e37323230eb79c1795babc50702bf7fc381a067fdbdb84f17b23cdf9815512f186358e481900c2e768fd0ffe5e6464bb2deb4c9b9b09b7789de6bbc667614547b29071047fccabb53e274c05ea17e7adfd2b6d03bf2b4086d3cc1762ba34bb9dae8b8d5bb2c771b2c1573c055f1cd43c83bd9eaa1c87ab3e6dc95269e5be7274b9e4957200832dfccdc7f4d7ef4f69bf8599d82dfeb4c5a920209287704e1343247fa2cda39f1fee14d250f2a2457ac55de3f288ff831a4524f1a19c917228692953723cbad45d7e3ef49b27a41b2363892752ee04896c36390cbc6a23363e9c15b5e113ff0a99f4d8edcf293fef2d12d7e3c6b8264095c86a35799080f1105b88ff5e10b37b3cf1b1f69c0ad7f65005af24bdbbae0864ce0118d8a955270977e7a0c5b08543728cc35acba99904d8c62917f0aa62679f29c0d564a79bba64b4f96407929d8e8cb1de9391ae7877cfd99eb8822934261e4611da6dff883132d6f68c777ea5b5efcbbc1b657be2f1cb9e4818b666b068023955e637a7db2ff2b6f683cf8999f4f8fddfba6a039d3477215a52b26f23228d55c940409b5aeb7b3edca1fb61a4ea1034895b69582a24d36e8557af33c777be7ba2cbf7ffd4cb0fe39e784641d3f7e7ceb5d77ddf563274e9c38eb248b0a94f5620916ddf4b38624cb4e1fd2a4fc062208d7f2f2f2e8c99999171e3972e49a7a4fefdef1f1350711fef4ce7eee20df3f1c9af14677de110daebbb3589e5d9716c504dabdc27bdef74369714eb5728900df6253f5cfeb061101e49a71fd3202c33a81cd7b90352ca6d33d4ae8e54a3b203dbd8d3b7f8ac7442af104dbad8c7e3a893a158f3d96d4e12d1229c075d1c587cbac0112b5b4295f3c7483bf78f0b262e1e0f6627af7abf2a523bb4cbc8073cbb80e5dc6c08743e90f94615dc5ede406176596948d79da33cc8db5cb7b8ab69e95fb87f715823d5992e58364458e649d133892e5d006159117b76a050855bcf7736f6bedfbeabb93130fbe3668cdd6bd0647af5a3010ac786ae5ed4f012b86377679b3eb4d6f6f6ca54d84fce287b68c66f150a3b4a122cdc6274a5df3188245205c998e0585b71e5b5f2500cc47b21282c063197381e10896e667e99a5e0de3d38f25e0e25ca41342b9c0f0edc1829fc6a98de75eefda856064d7bff4ec7cd5ef0713cffddc45b5f62a4d223f6b0e98f9033bd2035f7d63836d7d72eff3c2783e08a024426e2c5ab4647a90eb4a6844e1812c68f5b022f9cf3aa612906461aba3fd2694f869140b990ef1edc2f77533e13918c98212f28e1f3bb675f73df7fcd86a4e179e8964d14dbfee29c414248bdb3c8064f1a1c68b936462657965cde8e8c8437dfdfdf36118c692e0f900cb1e54626e3512d407f616ad252e869f80a2af702857be3fca69419103e81be57d882bd7f880b63bee2192eed22dfd01466b0a364ec80270ba910e7fd90ef6fca938b3afe6f4bda179680e9da44e4ff3b1c796c4c81bb1e0be22d1706ff04b165e0e93366a2659da9aaf4c5f572c1fbf842f86784953642549a7fd3835bf6621e9c8afa2931bf3e1918e62296c489eb36ea665eb8895c7fad4f2b18f6916b6bc6249484903c68d649d1f3892e5209029a37469289bbcf3b9cdfb3ffa33f1c15b7ec62c4f5ee6c54bc6c4cb101a7c53864f6622a64c243b27f38915373487c0714e46c24568f2930e140e628920a58387724c4bc25150a8c0284531dc56e85af140c33f3dd61f3d2f0722a969e84b02a067446089bfe66be3738c80d48a6e257b146008c70b41812dc9924fc050415039c23fe70260bf66b2cab029062692a07fd391eae617fc59b0fdf5ff3d5af78cdbfcb07611adbd8a7bcccc435725fbbef0d6d6235ffaa9e4d1db7e3c5f3ab635689e3411144708e509adaa3541620a852a23973c2669903f513730686ba973841629af466b9b752ad5aa4ed4b71ac42f3723f5fad79f0c37c848d66a6f6de11d3b7e6ceb5d77ddfdced52059963cd110245741c0754b4ab22cd1a20d0225fe8c93826ce5596eb8396aabd5dab17ffffe1717453eb779cb96fb10468786ce1bf038d2bbe6a837bcfdf6a8d6b7177d601c5262ad495b61c4b74b39a2837a0bb9650bfa09ef72fefa01fa02d75d720c0795aba39cac6540fa4159e790017c24a15b3728a6bbf3c7260978dfd1fdb878e2edc6ba17fbd49fb2cdbae98f05651cc368efd0a972ba506e943de03dc20d9741b8826401bc0a7292df2c4cd1b7e06f2913f3e5438a253e8f81ad9b321f7e8351ee191915567f855eab8ebe53d6c187328e7e484393d1e32740b21e7224ebdce04c3dcbe1e904285d7e16c75f99dad07ae853372deffebbff981ebcf9e7cde2c17566e598f1a078fd641932945b339437b02c7ad52901eebeccc5e74591c04011f3556b09c6ae853010141416564c88d2a680e83242c2ac11d0d6bc3ad0f414141a74332515e662731a4f043b09117354056751caa353c06be29e46b4a908f81e11e9440e41977ba01b5e64f240a70693caa849fb372df943dbefecd9fecaffab72f9dbffa8b676d73dde93fc6affb94626fb5ecd8ea5476e7d4963f7dffffbc67d1ffccfe9b1dbdf18ccef1dae2c1f33513c67c2ac612a1e14a8182a11ae452181463db21d65da42eb9ec702d50440a765bf0f9ca155560b67ea016707d28f32de074aa4ac21acbb9b78110ccf29c3f9c505337df2843974f890d9bb6fefa5b77eeb9bbf74e2c4f4060974bee1f97934b0ee70b8e355ff58bfea0dbf190d5ff2b9bc67c3625e1f374565c078510ddd21c4bdc2d6d76b2733644567f0a391374a61d84d6c03c8b1d8da637423d26ea3f566ebf36c40eef1d25874bb4f87e6ab8f137ce090a748c8b8408c6ef1e0250b26684e1bb3346982c6b4c9e30682b4847ce9038a1a42f2e6b5b5e5dc99a17582f0a7c8c32ef7f788ffc4c01670381728a5a4c3d3111cbd32f162bf3f73cf75f19ebfffc5f8e17ffe7533bbe7257eca8ffd2e803b35409262113601bfdb4662c52ec3378de2c4a43079cac5a0c684202451c03d753a4a984fa7b4b97853147429cfa88a9526758d604100a9d130f4eb0816a0add0356d4d856ea6406255dae24630182b9c6975685e07edbc40c84822b86f92bcb28e34b8e71517b77bb5216306d61b33ba2bf5462f3bd933f19cbfebdbf9d65f0bb6dff84f416dd53f0573d6206baf968f6e693df8c97736eefde06fa447bef356af315be12790c2a261423f35512857ae5b7264fac628053a47af2c31e0e88b25b65ae76807d637dbc2b6d169b035cffa3e9352433b3dd6731550e6b32a79e93d129c327a451245c305efb4e94723750863c13a49d3d42cafac989373b334974e4f4d6d86ff633bed79821746ad70dd33bfde7bf99b7eb3b6f5797f998f5c76321fd999e6bdeb4d8c079014f70b8d7e0791d7c4fb5f9c8fc1e9de56ddd3ea18ca88d2e4ec9794230cf58343d2b1c3985d10127486fec912ab74e143251e3cf0401970242f839d814ca16d7947f081c4ee1da792a9631e03dc57bc6eb977207fc40d6fdbe2a712acd5409991c3aae3cc52d1e1298f248e2b7ee3f844b2ff0b6f4aee7dffbf8f0f7cf1578bc54777788d495334164c91f0d3389c018300e18846a93884904010f103b919c8168d0785e141c072d342fc0ab99269397038daf676a6c09421f4b61ca30051414aa143c8141ece5b537a967629b238ca847c744929a726e9071bfe921b6d317433aec6b74ace42a6ae2014a910348b9234802ce6019ed02b7d26ef192ba2bef54be1e0e67b7ab7bde8b7a3cbdef69f838dd77d5dbe037711204dd3d0242b03defcfe5df1c39f79e7cac39ffff5746acf0bb3653c752f4f99a005320da511fa5ce0cefae1ba2118b4ab7caf51da1cea855340ac3fb481ad2fd6553754599e2652ca46d4cfb540f948bb967d49cf68c3af3ed06db5955703b66ff13ea1917b04f705c915dae0145265fd19c6864da0ac979b0d33b7b8601aadb8e7f8e4d4168489ca281706bc20f3d75c7a4f74e55b7eb776c98bfe9337b4e9ee1cf746561929477b2b68637e221cf725da995443da5aba00fa14ef37f61b1afe89dddd5fb4af9c091c61d6a0a78739adbf3d01d832489a6d9ca91bea796d5bba21bf60cbc85439c24be3a33dc520bc902d89459c5ed6338332c882d9d829401ee83dc5f32a27797896567de2a24a97c3aae2fbefa10e173544d1248bfdfef4edcf5bb9fd2f7ead71ff07ff433a79d71bcdca6ccd40e172617b0085db56827ce2c2d31b3fb7a18404373acee9533b7400372c24d18134283212300eaf836a81e4e81f9ffe687857ab1021da8242c85569ba88960a42744f21565a165d004a31a6848ac25c091748516948bcba0d77bf6a5f0b60d3c69518be21a54fdea59169c6d07851dd98fa20f7702afcb19d93d1a6ebffbaf7ea1ffd9570c76bfe865327a5c4bdf091c5756ff6a167341ffae43b56ee7affbf6b1ef8ca2f048da90d41b6646a263611da103502859f8ad2d7913c9084289245fef2840d3ffaab9243fd848843a28dfa92a76f18aa96efab4238f4d98634b0384a7bd5807e80cb599d6c98ae351674b3ef59e2d56dba47bda4af233c37fce46eea1e48fef2caf2589224154de90202fa7ed43b36195ef2caf79b5d6fff9568e30d7feb8d5e3ae30fef28829e7113547a714db88770edba305e8d4ce89775a3778f506e39e63dad75801f40a7d74e35222f443c6898b381ef87ac9091c8762f947db4e907fbbb99ef06b914f96521ca8ba3fceb968730dc79def0535eb4ade11799a41219c6e14287ede50e4f03e009daf79ab3a3f1c39f7dcbca9d7ff9ff6fedfbc42fe727efbb349b3f64b2952979ed980b9c09c87e3104d75a5111eb2bdab8f1a924f0d44a92e57b11046488f3203fa271a13872289082440b34073ad4922c7e169749da4e47314459618da0245a2a783afef2861fd291112c2158245a25b12aa08b64242b8238577f2fe7981a491772210144ae24584a0c60b3145006dc5c916bafb89ecbf0d338fcf66034604cef78eaf56d385adffa92df8eae7ac76f076baefc7a50ed5bd0d25c1408b2e9fbae5ebef3ef7e6dfeae7ff8cf8dfd37ff783ef3f03a8e5406f98a09a161c8b1400bda242b45db73c17200826540a632280a9a1cca3f43b3c8f42022e5687f4bae32f8895b08f099a1a3581d8388ec8ca5d26a77878b1edd44ab9b6c75932b6ba884699368115c17a85f14e0fa24631acdc608da841df8820447727b365dfb0dfff21ff9ed68ebcb7e271fd876c4f46e484d6508b75fafdc4bfc9836a7cf640d13db9bfda0532de837ec5f24e87c70e21a48dea3e81f08a471d5489f1150069ca9bbd0cf9a27069603d57caa41791e4b8e3872859429c3e0d6694bb4a1ac3db5869dd8fed1ad4677c5d732f30a38124cdb5e0ddb591ffa68d8ee6a946c51d6c2c096556e3cb686c7ed541c2e743cf15ee97051234dd3d05f3eb62979e863ef4c1ef9e4bf31737b5f18c44b5ed85a82006c98308fa195714353a0e0b60e201c64da0f6ea25b7928512985444002c3d10d882039811814424ce71441c45454e0101dc159a603630587ca39f911854e05ce343af169a89c383549638f351c55b92cba459efcde1a0539212a1e19c9b7d79809c918c9955f81b366f2ca88f1fb371a7f785b231ab964776dcbf37fc7dffce20f06fa5d3db9828b016823cf2c4dae4d0eddfaca78fa81d785adb9512e6af7e32553a40d7dfb89758d3ae045b10eda1727ca5e47afe40502d46981b6cd6158a77c9656f24572a5f5aa60dbd963b483fc22b6102cb89181bc158534e575765116cc5f4a705eeaf66c676a15b42558964cd1d04f460bcb69420b4e1db68f199f53b67c332f2f423e14e9890b1761efe894b7fd557f5bbde4c6df348397ecce87b6374d2feea1ca304fa25770fab0737f2a584f242beaa6d8503f0bf6253556ce3c713cf92a3bbd17b25de867db47a6c9db6bb99430b2a7832aebb9d2e8b71d510eca25b8d9fe9451dded0eef2e74d2d46b2eaf9f040bf78ff5d77b86b6f50344f67d2f302c3294780ee71a4fbe473a5c14c08dedb55acd9a37fbf095ad7bfff7bfcef77ef2ff1bcd3ff28cb031254a97fbbd44b80923dccc2230723c754228a8d1093f0a110a89525db6850815ad1828052a60f9dc0cc259a1a28a550d6f722bc44470213e0fc546780a1debc77cc44f42233cdc8f0185cb2946f3d62edd11e8146c347c1ee41b83245614fc1cf1ca7cbe3d4882d5638a68c014bd6b53d3bf712e1cb9e433f51daff90fe18e1f7a5fd8b3badfd45b0d7879524d8edefebcecd85dafad348e0dd6e29326ca9641a29ba6485a264b53d42d05b684860b750412c5e9524eee245024292a3f459fe0a6b21cbda2e29769439082ac400cb61784b6da6ada82bfec29822ec5020dc5219bd3ce210db8d463b5815ed5d5973aae1f1ca78c4a814811d6cffaa7a8771a0bbb368b36c1b24978dc5328a946ba08c07b24d876e33f562e7dfd7f32c3bbbe50f46e9ccf7bd66445d4673cbf8a7a8e50f51c94e33890c5996a9ff560fb84ed9f3f3831e8263716acebd3cd9960e3dab6caf0e4a67e942f88876be2c3e5290637038d844133f26144c922db55e5dd77834eab72040fb615a0a5f054a9466898ef87387d8f6c1d56099d367378ca01023dcc9b27d7f847befeead67dfff8ebf1a15bdf5b2c9ddc9037168dc78f97e266a6241771813b901487e4ca93059db8812119744f16fc89cce7c806ef5412168a413baaa5ca566df88a92a14168a423314446e80887457b248491852011e5d3bfd8aa674e1792525644a1114104a31450dd48b9b4bb5c488b4482539bc68b20fcb9e338bf39386ef2818dc68c5eba188eedbcabbee5b9bfef5ffac3bfe56fbcfe4b17cbe2f6d351ac9c1ccba6ee7d91b774f299014815df800a3965837a0c51e76c8b5cd6cf2130eb9a04ab44775dcb7727398ac5e661ed222c95872a242bdc695b43581b492329120d21db38e6be6a24106583f04770ae3e3f844b63a69d8ccf326cdd752b6cfa59126571a6feac367eca9112c489106ed5ca7ab6e185b546b0f1b99ff72f7bdb7f8cb63cff0f82b12b1f302397aee47d9b4c511b0351af32109a5e258e8ffb918638bd3e08f635590b5adec74f14223b4eabffee638bd3f3ec3eb69c46655637445222b0ca2a9198128fc7d69c9a962dbf4a28bd3bba1e331e175d57d0364a51713d3c941f4ddbe1c286f60a87a7147093531bd6bcd987ae6cdcf177bf3a7fdbdffed7e6c16fbd335f9caae72b733265240b53a90061f834c6dbd70a2899dea36695b4284ab8be298241389ca310d145ba6aa8bb64c403373e7e61e3a98f02a0e0024d8815084b7d3a3b55d930577dcaeb324c818a98a327a7094b1a1d61a3e044486b180b79d87c789e465414a75f20cc7939729d01047d5093cfba98ea90c97b27526f60e244b4e1babfa95df323bf125ef1b63f94bdaf828b63efab33a158995e9fcf1f7fa697253d1e08164996bc88807a9035226873defa54fe6d8580cad2fa0f4c8636962956182bca45dd51b0830fb15ed5a8bb0359e92646173ec38db4d8c3b87f9a2f8c1cfe920c47489908fbcfb94151e4e0999d12b37f2901d47ec9cb7bb2b0fd93b0fdd6fa5963ef377b8eb0f79c3edce0980f25245ab90cfd9c52bb173a3c3f4c6b6b2fdb1d5d71d37fad5cfdce9ff5d75ef721bf7ff37cd1bba1f0a221343a1e6e204b44e2805ff1534d94177ca8e3231b7b9fec902eb20455c0ba2ae58d10fdf21c1f98e4a10931b497296c5d5ab785adebd361dba57ddeca12740a31a7a5a7ed57ca25e60f5b47a658361ade0f3c86bc41b938bacf9469786f29989e5ca998ef0e8440c2d66839b56e9e182467c46304b851970ee71eaed69f62c8d234f4e2b9b1f8c0575ebe7cd7fffef5c6be9b7fd52c1cb9c46f4c1bbf350b1ed2c07dc7912addd345809b57040a6e4659fb24ca50648e2821998a6357296d0a07deb73419bd45b84840d8481b062246ce4b42fc153743d0876978ba664ac56cc7886287c1b9d385907d8d5acf681e24571d37dfc2e17986c32f080147d73212039207af82e3ba29a24193f5ac35c5e0e6157f74fb9d95cd2ffc9dca156ffd037fecaa5bbdb08a0abaa8e115cdf951d35ad8e6e72db41feb8544872307aa346484504832eb1c75cc632a36a97fd49b6dabd27dbae906c9147ca5f5d87274ab51b0c9347f398239b54dcf213c904a76e08e5205ac125d0d9caec4b5ee4fcd8fc7423601a919255830798878ab57b855845fe959f6d73ce3dbdeaeb7fcb6bfe5457f6806b63e6a06b6b48a9e0db2a9aff17b718991dce3ec50d21fcb3aeaf4a25216a00644deb01ecb7a226c9d9e8ec7f33f5b38538b74fc28d714eac716b586e83eeef63b15b2909efd424891ca3b356719ab5b550e251edbc20e172db873bb37f7c8e5cb77feedaf2cdef9beffd23cfced777aad999a49e77143e93707b99052d7d6e048c8140c6e6659b47e0a20fcf8db7da30bc9510122c2501406fdf5bc4c1772e607b65db0c9b0f2799b2ea8f054a384eb3483e034149856318991c83a1dc9b720e54d4819c122b9028d0251d06b946ca0a770ca8ecc946f0e661508f89e35c6ebdbb21cacbde6233d57ffc82f87bbdef8e77edfda83e5855cf428d2468fc91a23a26f70edac0f19b14163b03db57e54b55b73ba285025f1789084d5b0add16a6c6b1e336d31383a0592afb6e32979e1f0bb667516a1dde95ce5f6fd83fdddde05393fc87c91922c81e7e79591cd7bc32bdef287d52bdff82bc5f8955f33c3db970bdc7b5934605290ac348fd0207cf9047d035d829faf6aaf7bf2e11770b448fb0a6b866dc73aea2652f6f84ce66cc32e4867ab741bfae9196d2ef563f88e51f05ada0780bd0fbaee078bb624e23935ed7bebac5cda2a5490c3197186d675b8d8802774dfc44b7dc1cc3dd73577bfffd71bfb6ffeb7f9fc81ab4d7c120a771137253ff30032c2b01057ed27424803de6ad6c80ddc7e23900a99e128f4ade0d0617b8296bde1f9065f47d152084150cab41273a38d3c118186c3e68822a9724a4a87dcad614e1ad08a80d2c2931d35a4c62cf88e9b8cc6f1d3159c92d4d1332ee1660c4e21a204f0830f88571ed640b0fa4c5e1b04c15a9f79fd1327fdf15dff58bbec877edf1fbbe2363faaaf48c4a702c096bd3cab78795ad7fa90ea14837f215b323a897ab4ed27d52aa4581c0807958663993a298daeb153c374ade986acc7639b97c44bd26d83a9b2045a1639669e322bb6fa6fd1a9d2959c2f38a2c5b2b1ae940897ed911755b9af2f7270db137fd30b3e5dbdf275ff291cbfeafd6660e354de3f1167d5319373617c50418304b8e6b26f481fc16543a6b08fc8f4a0100c540a9fbcba703a91127255babbf1f8edcd7e7d263cd69f3e7adf74831eecefe5111aceba1ff3ccfa1868d3f24e51f058652f47b0b4cc6a3ab2b82b51a91f7194f699c0f036dce9e84acb6155f1782de07091004f7e81694ead8b1ffecc4d2b77bfff375bc7bef9137e3c1306d9a209fc96e137e8fc00ca10469ec420b47803eb1a01ce9ed0e8cd7d6a77e0cd6b6f608a98c79af6ba1b185112b0350e048f8ffc4aa3c207c407f7b59c15a2650dd75670ba80465262226da8b256236bbc8a446c122d215b24571ccd2a09008fb9609632290f41e448b0c21ef93c4e30b4b9150e6d7ca4b6ed05bf1d5df9b6ffcb1fbee45e44e0c53c75c03acee37a06494d41afd3b93eea9c2407a7d11624ac76dd5adbb0154a62d569dfb2dee9ee2256a798f23c475fa4e584603d162c0bdb44c8743b040a471e710e466c566364e36c024da0766950604e17ea2d759183dff6f4c7afba2db8eaa6ffb37ee50fff4a657cd73783e1ad0dbf0ea215f6a14fe81bbf224d40b864eb10d40747dad9355835fa1026a9f1e731b0ed7b7a3b7f6f42dde9ef1d94d54ed1d065283fcf64b47c1a85f8de04cb82f9b0ff77191c937416725f74fc1e6b08da4f38b353002dd07dc10eab08db5a0e17258a9ab7706047ebde8ffc6cf3be0ffd6671ecaed7f023a5416bca04f982f1f3180a3515c52ac240840245026e31343d851917a27a856edca98b94d1294e95534097b0116124620571f5099c466e77083451d2655e9a9f1a8e6cb5953e63c3cd63bbe33a85a8d8621e0b799711e4899f7ca1cd69413bb562cb26232938e69f6c341af51aaf3a6420ccd3a26fdd9c3fb4fdcbb51daffaff053b5ef3b7d1d0c453667af014b002f32c647d2aa9b53640628dc622316a7f0aa434426611c4d6298d7e87cd9ed396d1d6e932a8426983d248f712b7b5d92e08562a3b51825d1aa96ced8ec72a02599f937cbe5f689dd1460173500d3de66f79e629002fc8c2bef1e3e1f61b3f58b9f28dbfe18fedfa583e303167eaa37911f5eb362a013736e68632245de8b719ee64192cb56a4a6df6257961a7ec53faf24da7b2ce1ea17e7cf1c02e6c8d42fb3a659df6f953e39eb13125320dafab3490c1facca10fbfd6ef31e607ecca67ab861cbe37d85a0e1719e4093759ec4f1ffdfa0b5a77fcedbfcdf67ef1d7bde689ad41b20472a5538332ca0432e2658918ae5fe2088f00adee07a5a00a40acf8aa0f5fd5c7091a0b59cc0ee2422d200a8069e2f614118053fad60b87b71904f1488020f8943091ee300d356d7769ebd39efcb4417f666917682b07226542d9e50f798b3f84aa8f7c50f622004904a192ad06e04e838a98ac366ad2de0dc61bded90cd63c734fcff61b7f2fbce21dff26d8f6f27f0eaafdf39ae3531090d0459e46856cbfcf7a26c1a6e19112543b42c511406b845009f92a47b4d00ed2fea873beb1a96dcd363fdde0a78404ef02db1321da6e3da6adfd8cc17d1d423d27c07dc39e45bbf4b930c03ecdaad1471ffc5e58c53bbbf0f9fdc3abbe155cfee6df8ab6bef0bfe6c3bb0e25c3bbe2bc67bdc9c36193fabd2601c9ca388dccb711f12048c85bb1f6fea7abbbdff16111fef41353f633c2867f62389518d9beabe83ea772c922839c92875899da54681b76c268fb02e58d20f793dc5bf43c159d58ccfff18cd6cbf70b1d1c6519b4380eab0fd6b8c345022a097ed8d95b9e5c9f3cf4b9b736effafbdf8c0fdef2afb295e37d4173d278c92294245fd7cf8c9f91608120a5347c8d1f8a144614290539053b480aa70f851fe16ecf41bc384a9dcab13e8dd1c85a28121eba2102ecb492122f255c1622e4704e464f705247ab984e1900b95b8225d198b998ce3dafc995bf921ec9818a1e09c68d31b9d33c4862068298e238e5c2f6b062f2a8c764954193d7c673af77622e1adef1d9de4b5ffbefc3cbdef4c7b5f11df77b41144b424f69a4d04ea8fdf61a29da6c8b926497edd89ef293faa5d4b5d407558c7a6655778c4a658d47638f6110454837dc967c9d0ea62fb6fc7620defae8beea28333927793d19900f706cf94cf5f794821764d5b16d0f86bb5eff27d54b5efcdb6668eb378bbe8d0b69cfba3c8b06d01bab26f37b707fe36149a612397d88fe5612a66ee2f478ee6e3c9efff70bf6526bce04ede39453d610dfad319950c774d65de91dd6c9ec4c86b0f693c5e35d89c3d904e5a2c345803c03ad88e786fcc9db5fd0bcf37dbfd6daf3b1dfc84fee7db1d79a377e6bd1e4dcc51b3739d7ca721043845269a838498cecba1b9f8449146b8627c6cca4456ab21c36fc3268cc0c525e745ff9c76faaa95255052b7b51210955aa7ad3ab62809b1a171071236e4d8bb06a997feab6ddcf86b0502165153385a45fae2583037608f1c02d1902948d760dc4b01f046bcce4bd1b4d30b2330e4776eeedd9f2c2dff32f7dcb6ffa13cffde245f6ddc1270f129622e7c71b5147f6099b6d81f6967a461b96fda21b6c110b369bf41fbadbc6b69afdb37e7c22ffee505d819e23368ce40503b7aaa27328eccf5d4e4f185d352fbf652d5b2dfd9445501f9a09b6bfe29ffaaef9d17f176db9fecf82d11d87cdc8d6b4e85b634cb5df98b08efbbd227d8775c46941a25b2e74db168fe7ff8383f9765acb1e8b1ca479cc3dc3631a959962caf0a7c3122cdaf66e90fb459d40e9790a18e04cfe0e1712be977c74b800902471c52c1ededabcefa3ef5afecefffa2fad87befc2bfec98776fa4bc78d69ce18afb522db1ac87a25d9fb80421ab720a77e708387b8b9f563cdfaa42c235db87d658488c48a440b4a58a6e5688b613208531a3b6aa586c280b60a11b9d9057ad38baf784929e800d8d5d4dd9d861c6b0c49af3ca9862ab8ad6b105e46afb8c648ae0aa50d51ee08064fbe7c02aeaec9f144bce80deef86675fbab7e33b8f42d7f5c5bc38d459f0ea3571d70d34d99e6450db1fed8ae7aacf569dbf494bab6cdd4855383b07d5409744cd966d207ce0c0493b3c8556d1cb3a749fe04777d3c6738c3455e0060cd4825a02e39ae4852013ce54916e1473dcbfed865df0a2f7fdbff5ddbfeb2df32039bee31bdeb1a456dd4984aafc9820a42f1618ad5837aea32c4e9848ab69cc7a1f439d83467074c88c6d22a7b5c027ddafaa8e1fd21de007f3ac6de3b6a3ae755d6f258c1a9754bcd3aa60bdd811d2e483892750103c2c23349dce34fdf735df3eef7ffebc6439ffdb57c6edf0d613eef55f2651379b189f89518aeab2a6f3dcf871b86fb6947f00f4148421c7377658e64c97a2d482c994acc21867093cbf7e8189f53870847a35b303045cafa92ec5000d0ab04efef6ef3f8ea52bb19c3589b616948fcda69b07ce554a718c91b7952800ab9e2c4566812089e18e4aae9f798663868e2dab8c9062e89cdf0aefdd5f5cff99f3d97bff93f865b5efcf1a7cde855375055a847b438eb974fd03068733d874a967ea2156e8f50c1fc2ddbdbc22a126b78d29ad3f1583f8ea09150d1963134e467f74b637b7694625998730055b9172e7877c99b65ac6fcf4b711f3f2d881650843d23d3c1b6977da8efb2d7fea7daba677ea218de3197f56c2e4c7dadc9c25e7441922d483912281889d4e526ba89d66a80bdd49a6e74fb3fc6f07c97d156b6f2d4362f027e17f03e5a057cf74c1dce1a283d1d2e40706b06af353f921efaca8daddd7ff7ebc9812ffca23f7f7063b178c804c9ac09fcd454830cc6981a6c0e0870891537e4a48982c054fc087664f4732aaad84428513809c9cac0b34e95e39664f11eb46ba93a9d846aaa3bbcdefd142662c3c81e2f38d66944c650db42473e34ac9ed1a9487d46d3f541b2e12847e5648a93d383104b248f12822357304505369e74a3219357d7acf8039bbfd5bbf395bf8127e2dff3d75d7b8ba77b5f3dfdc0ea2dd077d806704a3da3fe2c01961706e0591e02ec13386640b49d6d09dd52a3dbd04f7bc26347b4901775066c6d53db63d87fd0be38a123a354184a758478b1109ed0fb738682dbdc5fa0607db07872af067eeb6944b2047ea57731dcf8bccf8657fdc87fa86fb8f68f8a9eb5878bfa784bde120e7ad057f8d8a5ebb34e975b16acbbb30b9b0ffbb41abd3fd474b6be39dd10d6eee0f14bc7bbc09a6e307f3eb0e83dd4294f89f29efc7e817a7a5af5adf38927d7420eab075953d3ac790bfb77b6eefdd04f35eff9d0bfcba6ee797398ac04613acf0932e8d0d824698cdb0d8484371ffeb829679e27e04d311409f792224951a2a24f7baa4cad2dbb2c4360918005be7ef95f946469188e108508c33b52bc593c045055253e0289a39e80da1d25acc288be5629ebdb6d105770d3c8ba0564c20e891c18406c112ef0c35979eb284179337ed8b98f6f0e6e4dc2b14ba67b363eeb030357bce63783cd2ffc64d83b3a0561fcf41520a86454991fa09edb758bbaf765df1d9c8491676928236e062ba383545e2052ba9f961a25c7dd06448badc370b045d194ad45a3535cf427b4fa991d616994fc4a3829949822cffd7325f0711f30aff2e8c282f47619ade528b278a14e58494f337051fcc89647c21daffe8bde5d37fe46b466d797c3b19d8bc5d084f16a83c60fcb1de2d18edda358ab014a2cfbe62265a53c38c25086c959121c916dea2706e1247cd783ad350a96598db42ebb7e792fb44d571835046f91f236913004ec277beb500c7412725845e8edec704140776e6ff4a7076f7d697cfbdffefbe6ee7ffcedfcf89ee7792b5c77b5cc311c53f539d2939a344b60743d1517ad171977412f0ddc05cee7dcb4538816951914a0285a28c9d2a620e0074f439ffb64916871bd96de7a7aafabd224c1b1ca97ca5947234e9b4e1450c8a0539502c7471ebac5038dfab3c3750ca7b3a8aef58d45da42bc90beac5528d3d71c429373ab067ecd3fea3761dffa467d64ebeefe9daffc8dda35effa8d60fdb3bff6b41dbdea06dbce434db371f0e84bba19a0bd6d3b48ad976dc9b56d6ad0ceb06d3b0be9a2d1163acd94648b86e909e9b2466147adc40d43052323a056a65bbb8332f4398076d10b0edac7594f6c42d6ef632be9e984b07fedd170fb8d1fa83ff3c7fe6db4f1babf0cfbd6cdfaf5e1dc8b7a71ff576d1dad1ad1622711c3a515e8d03a024ff9a567e4fee231ec53fe28b7185ee2759bf2cd6ecab7530c5b9ed3f94c5f8dca566b782fd9875ade5bddd7fb035c3b97ff399c13b0a73a5c00e0d60c66e9d8e674ef67df9cddf3c17f571cbbfd5d9578aea7d29a32513c67c26c05342333216e32990b8248b6048b532e5c84c3d7ee79e372948823583c85438077289ffe4a050962a59fb051a8b88029ef5939234a11e9946e59470023ca922e51cc140072d805cd4b463ea080cbd4804e40e643a3affd77d2e6c315dd323247c5ccb87c9330ec3559a5cf14f5f5c61bd89e79c35b67ab6baff8687dd70ffd9fc1b61bff29ec1b3fc627604dfde98ec283502f57e55109805c9567d8066c2f922512296d1b18b4133fd62d2dc03683b161d806d6681bc2946118b7bb1f75da5adb97501f6d5f1aca76ba48ba45b968bb95a1571f7205cade2f284899f04f82ca91bfc287aae62df034065f58f187b6deebedfce13f08b7bee0f7cdc88e03fef025cb79df66e3f58c8b5cd0fdfdd89eec699026ec8ff230d18d4eff7bacb1382d0e2b5f852750862b055ee7e151bdad7f0736dd33e5d301bf8a41683a9db0dd29758364ab03484966db2e23d15566103b42e42be321694d177d0bbe94127ac261b5717a6f7438c7c04dc13ba3ee4fdff1dcd66d7ff69badddeffb3defe4dd2fa934a64c255f32a1cf7daf12dc88ba5e89c25816b607018e71b3c84de59b108c8586db330841e17d2577151524478140ac028e0471fb03558efc202be3f3a3ac5ce3d0b961f9c4a52e264127d3d2f478b3f3b7140a245d25f11258610321a44efe947e8f0b1243ebfa7fd9fb0a00398e2bed6a1a5a46edae76c54c06992132c476ec384e1cc77138970bdfe50fa3edd8a10bdde5920b5c72b95cd071626619651962b665816531e3ae56cb3bd8f0bfef55d7ceec6a258b766656ea4f7adb3dddd5d5d555afdefb0aba9a5452b3484c1859619b21225855c2893509a76a62c2a89dbea664f29bbf6fcd7ecf7566f3590b8d5049afbc3280047292329f8c2c7ab0e49c911c5021a26c711c59ce42c5a3643070ad9283033bb937c0807362f06b8f7982476d907d9fb2d8a048206d07e5d4710acf2a6fdcae4f7fe7afac791ff9b8187bfa6d5ec5e44ea7acc9b64b6a856d950a176be4e9689ec97c53760a18e8c9a763c30b1a8e727b70c8d5ef9cfd81a252f10c896fc04ee614e940ddc436572828ff05700d445613a4958f64030c027b05ba052707448b02e2f9e50b157491af5bb4cd63bd3bbea14a39409e218dbd17d6fadbc7381b1f794b6ac5dfbe9ed9f2fc47446f6bbd48760acdeea28a22c9952a24ee594685a1ba83a13d061d8351e6b900e8bdf09d1c867f50bc3cc4837d222ddc2bc45bfc060922c344c9407d8491919513a68a8fd06f544b72d6b455c2f7e6840c0745ac280dbed1931f8f966963a382df48af9f7e0e45f12262dc8d5776a6f461683063950937dc28dcd2f18e289fb0d7aa99717fc9d44bbe614d7debef4295cd9bfc5e90a2068680f112030f05e709547218a0a072a77ca572d817322938c322b39f0557b352ed236f0ca9737c7fde0e07dc234be6f30bd7f5a87a0c4e5ba1d2721070a58d0800e8a1d21ebde9e427ac59effabe35fecc1f6b9593577865e3fb9c924661476a846360b907d83699650e952bbfd70a1bc78a9d159ee7e98bb44383f55bd9a67da17407db9cfd81f8118fda1214b162723514b0d9f40ff6902e51c3f94ad84e623be85e88478e26e02eb9903e00b14ac1f48fdcde6c40da5644bdbfe70b301250a517208f80f1d432fde5ceb667cfe95ff67f5f8d2ffbd3bf655a5fbb54d81d44ae7ac942248950c9f9540e111ab97a3a572fd50bcc9590e751e9e8ba0699413f170c0bed5365922d1739df064c04bd562c749ee7dd200eaa98a870aa55243f262cf799fc500c266c843cc45bd9ff812dce8320f9155f97959f491dc5e9a062b3f590f7e36960a8dcc4e44000e897bc8e8c21afe6ee223e8bced31d74b44c6342842a0591ab945e35614dc9e40baf0fcd7eefd78c71672d34c2655d48433103654c12edecec6cdcb367cfb8f6f6f6263a9c97fa062a8cb2c6a2b4522750ae289fac5301a11e103ac243cd14568a3c8632564e0265ada01c1416aef5509e54ae2c52abb8743906d201e803e6a8f0bc305fd1d82140d1a0027984e7baa62627d330f04c7883b62840e940f590b59644d36ccaa73ce7509143234d2a6f5e6fcd78c72fa3277fe4d356d3897ff5ca277610d1723174e81a511269dbe4c221b846ea3c5952fe87f246b6b20ef271e832e9866f6b711d78b8cc7ad8270a3140c495aec0eec93d158e7b9ad039c422ebccb0e0fa07a0d1894609d73eda472272c4af45597555b51251d0310c93729d93c0bc2f760eec17708d2b178c861dc67de81a7c7a8c1b42fc3c749027e706186904999c67f06771fa5a9bd26b1fb8ba77e95fbe9ddcb0e8f376fbba995a7fabd0d35dc273e2e41c51e14930a96a0840a6b881eb3b2c5df51ea07ee19c5fa4a8461856446505a981a18011e1ad6f58fc28b8d272c585f1e02b61202826ae8c72523a4f4ca7dfb0294a14941d502d249c427cd25dd031dec7413864dac2b3cb2374ad24863c47c820c34186d20b550b37d6e079a52d3da26ad213a149177ccb9c7cc99fadcae64d9a6eda7c6111c3b66db3afafaff2d5575f3de7a1871efaf8534f3df581575e79e5d2542a15f6838c2cb8dc0894f5ec2006197de4b6dc4a014074fd6bb85c068b9cf49b2b3e509e744ec97040514bddca62f0afe1af1b09b89e4bdc85e11f292e502efa7b84fc65cb6883a7874afaf4da192f46665ffde3e8b8337e6295d66ff1620d093d52253031dec5b74bc90e3a443aa44d920d0cd9d02041247418a27401faab9685907693ae42981cbd4648d83d089d6181f5ca42fecaed29533218327e09797fd9a395edbd621339704edd93b603e99569cb4dbfe770938665f03de475dc8b4b5b268e8e1d2562289d478011c5e092083062a04aa0894c4fb9befbc573e3affce686d4eadbbea377af3fdb723a35d3eda26a9120fd4f5381642834c8156a165f4a7bd93e0626517e4584b011c06f16bfc281b0f039e948a571419c54b978cbd4671f7005a738649735ea1f2a3d5a3e83e3cf050e417899074a0b4c986c114a0380371c33765238369697a0962409de8c04896492a745f8f3199a55ca6b5e65628d22553dc3766b67efd2272cf86f63cefbbf608e5f70f7287a73d0dab66ddbcc471e79e4e3f7de7bef775e78e1854fae5bb7eebd9b376f3e27994c96fa6146167e0f08fff18db004112cee0a95444b0aedd3217ea3938f0f238aa42901d4f600c8de430a6b2527ca070c7f4ecfd248423a23d598285650e650bd409e077843787a69e3266bfadbff3b36e73d9f359b4f7f40d4ccea754a9af1e50761e3535b9a29b01ca0c386861a7c64cbf8b363741084440d0b805429619b45e5c076cd2731109c436865ef067aacb8ac48dea03ea89030d70c8a04358ee384adf6e385c8daab88965f6f48f0a210db7bc25035e63accb54c42c5a1e6dd2ad15c5be84e526876bcdcb36dacf01a608481b20b30c2900b8beead4bad59784defcbbffb717af3e39ff07ab6361af19dc24876b0d2eb5e0a55824253eda01ac23d4db8165b9f34a9e292955155ea6c11821c0d7edb0b71c151c220d82c9a9002c285f3b2cae25eb2a2cbab5159c9b8e0c0b09057c974f02e43522b5999a501a334ba30681981c545e1fb3d6a2db291a3ad4386cfc54acee1a8f0c235c28ed50b37da1c37ca5a9685275f788335ebdd3f0ed74e59a5e926986751839e594ba7d3a12d5bb64c59b468d1a79e78e2896b57ae5c79c6860d1b1a76ecd831b3a3a363566f6f6fa51f7c8441194d5698cb07c635a75455efa52a77c61b388841618137087f70403f277496939527486d65fd2c32c0294ac7a8b0dfca17200bcf889475992d673d189b7df58dd69859ffe7459bf68a6843c6b36a846bc448cb2cca49d950e4f5b5fc06235420570d78b8db672eac1f7c8e0819aea1ebd13f247bb8a0fbb073c36390f9e5b03eb870734e0e2a700c5bab1e2faaaf5cbf708c4f12f01bf61ab65ca60fd33440c2727539ab31b88facff809c1e423f88c063891fcf4e0837d55f27d23d9574fdc055014606835422c048c00b6b3d9ba72457dcfec9f4fac7beac25da4e36337161a4e342b7d3029f3cc1d8b95c9fca6f8b40ef07741f959cf6b9db1bc5952b380691bd4e6c101007c70362c59de62419126ac10c123aee0f0162ae8c412217ac347de19921be98242061d4caf3058b58327de37ddc9b6ea79331320c619a26192449a830d7274c7fc278fb91aeb0c89084e8189b2d4cc23723226d560a3b5aef399593ba8dc679774767bef5ebe6a40b6f3122159df460450f4c6a8fc7e3e5ab57af3e9dc8d5c768fb6e225515dddddd22994c62f810614a485050230f7cbb70b44079b63c00f95fdc3d59010e0b9aeee85513561b53aef85964eac5d75b75d39799d5935356498bd04215c2d04b48a2647f4264672d1273608ea05cd3ca174558c8f6f2d40bb2cb20314c7188a0d864bb306f55ce5d956468407d7d7b0d7ec4eb01b2c8f9b24a4092b001997228208b2ec5d5e52883435b977f5344880c42d6975243421617369bae1faac59c0eb2c3d8b26d26628886b39c7585e782ad2771a8319fee115e7ccf64bb75f9395a3a5e1610ad9105174180a30f385ee1f5953adb9f3f2bb5f4a6afc4d73df235d1bd759a99ec10860d8295240e94a1ca0e12441486ea81922c64f1a87904a86e8345121dae4c08ebb7d2245011d17e41fc39c44a09190d79156814190112fce54992109f6061f1491610295cc1848a62a04a2c3fd40c8109906410ad3d2c2f219f499e0d11c9b288245a14bd495bdda4f82d8b09961d2a17766c4cc28ed4ee349b4efab93ef35d37ea4da72dc6bc0bf91cc50b182792c8debd7bc7bef4d24b972c5ebcf8b34b962cf9d8f6eddb6b77efde2d7a7a7a442a95e22532285cc8ce57f77c9e86e046135056542789d5073826a169ae5539768b35e5d23fc5a65dfcad50cde4c54659738f17a973e904d925225a5a98ec123500896469649e35b025b40e7d51bd5c14998c1316917e93de90c5c45cd66c8f93b2c9e82796769aaea1b0038ddc014175445c0af22af96d4f08a64da8ad14902d35bd03c204cb8f0729838f502954e0657930a449369885ec2cb799699f93c7569ae22292a5a5c9b4a6fbc3e9ad2f7e2ab3e3f9376be9de2ac7b6a9251dd88d9100677f80a30b0c1b89de9de3d2af3ff8eef8f25baf4dee5cf6cf7aa2b3c4c3d20c896ea9e84472b0a8e8c0983ff6b9324a3d57afdda288b8f203541987072e94d765ab0985e51fb8061408c48a68128b2cf841a40e61f93eb806fb205932323e4574897bace8f7d054c8de2e3a4701c999b151e2c9fb1826a46bd1bb8565196cbab7a385b0f08df0620d42544d72f4da695d91a6f9f7c44eb8fa8bd6b42b7e894f6a8c96a51930fc47e4eabcbbefbefbf38f3cf2c8d7962e5d7ad58e1d3bca3b3a3ab807cb27572a4f2c5ce35f3ea2c851a3003ea88e513140c1c911a91e8b00c71c742b9ad0c79ef16874debbbe698d3ff3e77acd8c0d5ae5e414e669b95a19d9d908d9a43099988830f410592eb25da40eb059ac17ac1b6c1d393eb69e744cce6f9540087ee35bfe2480c0e4d8d24100f1f177096c4bf11b240de46aa0170bc7e91e4cb8949595c284ce3f862635d2a8081780dbca34ca5e37a6537412fb83d24e3ec725df23d2bdc2eb6f1599f60da7f5adbcf7fba955f77c5ceb5a3f5b6412a5145e451be028212f46ff78013bd1444fb9defacad9c9577effcdc4b2bffd486f7df5cd46ff0ecd88ef122241242b93103a7ab0b82749564c4cc2f4d09a1aa836e82d42e5417f90dafa82e3c308fd65e14a4822cd04531f16746e708b888ecbbe2f6c711e2d389c03640f16642006b484089c3c0ea58efbf7e6df125cc9d1ea2372c182f958388e1e2f1d242d2c5c22584eb85638a52d71b77cc27a73dcb93f0acff9c0d7cd8917dec1df1d1c05c01c3b22520d4f3ffdf4bb172e5cf8cdc71e7bec8bcb972f3f79c3860ddab66ddb445757178836e7050834f285ae89e8f9fae02f938900c341f5b62ae077912228c3c384a65b69bd66ce2be6ec6b7e129e71e597bdaae94f64220dfd19b392ed8f6646280c112ddd223b87a91194d5b98238a0173c4f8b7fd21664056488aa30131e1c23e1bdecb5dc439623001ac9b0a772beacb4d0d22ee2acb4ad2056d2a64a3b2e7bcb68dfffcd42fbfc9362405ad060f6a31848ab12be076c0f0942c106e1a8ee66848ee92af15621f6ae1162f7b2e9c995f77d2fb1ece6ef67b63cf9362dd15107fbc67106382a905a10e028c00b8b9e1d13d21bee7f6f72c5dfaf4b6e79fe9fb5fef65a23d92d3007cb24c2818f3b83f86844aa34b49c780b9a4215926b1c6a08ed7365c416bd5c6fa4efaa026262bbec5e46959690f1a1570a6f09b2b81093ee8db757d08ac37d0908c7d7f9c25dd65c557d82c577c982e3dd577d38cdb4d50d6a231a21810f0f6728696983c855e958e1544eb6bd9aa97bf58613ee0acfbcfcebc6d4cbfe57ab68da8a79153286e20619abc88e1d3ba63efae8a31f59b468d157376fde7c567f7fbfe8edede5deab4c26c373b0403261d8e0d021942ff91daaf2cb2c4016c3915ce97c8a0fa42fa3a23e142fc8da853129fecc8763b32efd6e68dcc97fd16aa7b63a1593d376ac911a7a9522a3472998456161c760b7fc4a83a51f48a4fd25fda09630f6416400b6b8b47b309aa38896425601fdb8fc5b622b8955f6d850704f994fb424559340da2451f3858ee50ae243186e747b193a600bc3490ad3eb17a6d32174bbd3141d6b2e4baf7be49ba935f77d42ebd9329502056f1e1e25eceb25031c12c8485333a1bfc4d9f6dc99a9a57ff87a6af96ddfcfec7ced42b767a7f0127bf94d0ecb2385363c1106f1a01ac23d4be8e5400f1619f901434f84848b04e4475689c1956790f85508b698edb1ecb192040b155109089c8c8b27b4a3a70a5bf2f9b2b78a2203fcd619e25171cb89a0b4a5ab394e9cf621db47f2eaacc8e7c064777c124733885652f6246c0a6d950aadb439a9578d5f654dbcf05bc69c0f5c678c7bd37d46b46aafb464c50df45212992a5db264c9d977de79e7758f3ffef8d7d6ac593389089700c9b28958016cf0a86c2139040bbf4ddacf8fe30cbafc0f0059e718b4450d50b9552cb936c826043822686628a58f39e17973de7bbf1d9e7dd99745ed84e7455953bf1ba916ba19a2762248165ed431c96e61be96acbf98f58ede22b68b6432b9ddeb63a89ac01e03c3e90f1f23fb8be91f72da8712d5f095bfa5051c4e01a52d868dcfda7c5f37e877d674e25c8ecee0b82f14bb7f0d6c38dee97684450dfe906e8bb0971666a24d685ddb85ddb16e4662f5a3df4cadbcf3cb6eeb6b27894c3c467a385ca2021c02725427c0a102ddaa5ab2b336b3f6b1b72596df7c83b3f3c58f9b893dd5a6bd5784bc3ea1bb496e31c8e1417c83901c2f29bbeca982ee92900eab0f37736bc947eefebe408522a84ac7150f5b49b6b2401cb817bac441dc40b068ebdf1f554e563d29329eec2f45bc24d9ca393e80dc7b01b22d28c9219138bc421dad1622d620ccb2e65eb36efa43a1c9177d4b9ffc969b429563b78c86b957806ddb667b7bfbd8a79e7aea5d0b172efcdaabafbefa81b6b6b6b2cecece811e2c0ac3c639d7392a67e94bde7ab2a80c0ea43cc72da80c8a3a5fa0276a4bf57f68650b70b8203b6395d6ed36265e704778c615df321a66ddaa9536768a689deb842a84ad8585a345c96659c48788f8a05144564c9587b2c583ec339d7a236552eb15eecfcd72b39509187ef961a0a287a0a6b816a1651c98938511084a3bff2220cd44d0e4d022b6649d798e167ab35c92b408390961dadd428fef1622de1a4aee5efa91f8aa7bbe9adefec2c55aa2634c307c786418bef403bc2132998ca5756f9e9a7eede64fc597fff5bbeeee65e71bf1bd22ecf48988961125d4408a80a4c0f13a36f11ff474d06f4313ba91ede1906bb080fcc8ca981d1e44d519aec22962832d445527bfd5c5fbd88240a1074b922b2ca080f825d1f27bb68682ed3a5d8b787c82a5b6a8c48384c2c97d9cf7c312a8ea8a0cb5933264b46cab5c88d22657af99d4ad359ffe47eb847ffa9a3efebc7bb1b68d1fbca801a7ecaf7d35f3965b6ef926c97f2e5bb6eca2eddbb78bbd7bf70e0c0d62fe15f62118260450b630d239c4cba06d50df02bc217cdd0974e5284333230963ecc94f1a73df73833971c1f7936513b7dbb1c64c8aec54c6227b65489285c59401d94305ba256d2b132b5f00de97bb078434e1549cbe8dcfeefba000d296aa63d8ee1b73ee48067aa7b8874a9ea1e0721483bf2bab7ae07c617288f39c76ff2a22588e9b22c9f0e47b93dabb06f92e11df2344f7363db5edc577f6adbcf3dfe3abeffd94e8d93625205a878f9c920e703070a16c99ee0a7dcfb2d3322bfff6597bd3a22fea3d3ba678c93621527b856b27489933c2009122b5d4781a08b52c5caaaa70b85443b08e09693e899c60ce959002639b9dac88abf60759a1005cca97a382e6546249a2a4c8813d9f6c51eb4ab6b0007feb5b0e74472bc33200bf52ee23aab212b0c6172a309ec73122c209950ba7a4c9732b26f77a1593d686c79ffd736bd6153fd7ca9bd78d86cfe200449eccaeaeae9a152b569cfbc0030f7cf195575ef9786b6b6b15094f6c4f24124ca840a294a8e141b94e18e533912b2504bcdea6327e4441e5a28a26c010147bc6e4e84b809100967a286fdcae4f79f39fa3d32efc8ea899f8825339a1db2b1deb7ad12a91b6cac8e499443ca4a6f08ae964145978b2b9b475bcae2187f06d1f016654da706963e5be3c07605f5d8fc3b0d9520c5ea35009e291848b43b1c09eb3cd65818fa0c3046cd04c97265cd96d10c2ac708358a516c44b01bb3c2fd826bf96a6dfe4b79c1e21c897b9fddb84d3b37d4aeff6573e935ab7f0935acfa6199401183ecc8b0d3b961064d8c1c2f3347c7750f46e9f905879f787fb97fee53bc96dcf7cca4974540a274e958e5a0fae43ce19eb2261e8082b9c6b44b64caa05722d29d2673a871604080da93e881591315448901b45ae72aac13e18a8e0bea84a2b8b1295912a20047152f563124722170d85501a94100174e862f55b42dd1d2946dc68652963902b08833ba1a5477f39bea8d0ad52a1c7ea5ca372c2ded8f833fe5036ff239f3467bffb3ff5b2711bf88222071911eebddab469d36c22579ff9dbdffef65fcf3efbec3fa1e72a1e8f73cf95eaa5ca7588581b0ce42a140af13e845b903e681f242b7b6004215394975b1d1de469680c454005e6ff0a703c036f325bd32ef96bd9291ffa52d9d405bf306b266cd52b1b337aac4cb8a1b070a92e63dd290635a020fced56585452a15c51f3aa64e355b954aa7f54dd614595f05bdcbee42e522ae3814dcdda5a198ee22202a66c3837c0113f463be83844d918d59406a9c29c2b49aca4289b9e1d3591fe01d762ea8a63931f486784468d46c3d00466eb863cb2739853dcbbb3a67ffb8b9fe95b7ecbb7331b1eb9dc4bec0dde3e3c44288d087000f0426da9ae4a7dcf9233e32bfefef9de350f5c6fb7adba40243a34418a88795700e84686481408163b603ac26b4451650589c179aa4f0428ba54725901e91cefcbea202beb1b63a03acb4825b8228204f81593ab9e225c740f4e07fde6ad0222507707e496e3cd8d3b07881786804209cfb484162e155aac4668e52d7151deb43936fecc7fb7665df52363ccdca78d4879d7905416253004dcdbdb5bbd72e5cab388607df999679ef9ca860d1b66efdcb9938707d5c2a2b94039a31cd173c58bb0fa865991af1ce004326ce4919fbb1c25e4512de85645af8401f206cd0c25f59ae92f5933aef86564e29bfe4daf9af0bc5ed6d8adc52a851e2aa3f35132d516290dd96b32982059a029b0a6b0664a720193c8f6d7ffbd3fe45e2f451130f43949bb0ab225cd86b4e5d26e43703ec79cf8bd53eae88100a286904cb6d85651dc1865418f169d33740891343721b44cb7d0fa7708d1d76aa55a5fbfaa7feda3d7a7d73df261ad7bf30c91ee2b253f1790ad83c01b95c9710dee79f0bca8d6b97e6662e5ed1fef5bfac7efa5b73df52f46babd4e64ba849b8e9382da14905a00504eaa003ac608b93210392355b6319404474c0aad1319c11b2dba81ee685dd854b9988cb1e26729cea08a4b3f644b87aa18ba7f3914aa9b0225912e67c2c3ddce78430669f0e3f42ba55c4558d23cb9b81d82c82142f986a2ba3bee85d6141281fb6413238f2005bac8e89670746af119a5c20dd708a772ba27eae6f584c69e7a77c9a91ffea439e38adf98a5f53bfdcb8a1a286792c8ba75eb4ebce3b6db3e7febadb7fec792254b3eb06bd7ae18860641aed4fc2b0c0b02b9a40a6508f205c99d9b85e3aaa549c85b4f9682a2f4458fbc25339b23289b0001089e5952db6a4cbee4afdaac0ffd3f73dc79bf742ba7edf5cac6bb4eac8e3f5cef1961aabd64533d2c8a4e7a032234a03ed212b3f955b55bb226f601b9c8edfd82d0156c5f7daae36fe9284c0cdd83af67a205d28610f2052688f219322d323d324d6473308a82ad2f4cdbe003780814f69fe282a0cf8ab61adeac2432095bc68d447a462c2761b849613a7d424b77083dbe4b38dd9be6f66c7cecc6be57fef483d48645578bde9d13c826e2f5cc0007004a30c030a0caa46b99bef2cc96a716f4bdfafbebe3afdf75a3dbb6f21ca37fa76ec4db8461f71237c1a771d0b30165969501ddaf506bac0f059fea90e2439131a1929818d555397c889e2d5e019d2e439dc26f55519500b232226688efe0f9afac545224b163a17b0d2e56102a102c9f646175615e49c0ff4d22e762216e6e2b511c103c93029da7bfb2ca1a4410d1ba2383634499608958b3eb95b6ecb11a4efca535effdd7e963ce58a4874a7be4b5c50d109f743a5dfae28b2f9e7bd75d775dbbf0a187ae7fedb5d7e66fddba55e0d338589e411128882259aaf74a912818dfdc70905cb091cbd37c065209fe2f5bc4c509956f9c9b794a26aa8aba6fb122377db44f490e900fe85624111933639935e31dbf0a8d3befc76e49f31637d694c944aa8513223b67e26d69494a186c2f2594adc611d90895920b78056553a5902545c319c3902ce435b036170593cbfcd035beb980cde5d10f5cc5fa21e396231e24036605d55eda1d5e7e42d62ebe2f83c2618a0a1af8102c12cd1fcf26928539c19887063fc0cd68226a58b8d47013c24c770933b947e8dddb85d1be3e96deb9f46dbd2beffd7e62f9dfbea875ac9b4311476047e54d020c852a9d00392047aa6ba9bdb5e9750f5e155f7dcf17ec3daf5dad39bd312dd343a40a2bb6a749b0de08e92f94de05a1c2e020eb311d230586be83b2d03ecfbd82d06fa236242035149848177ab9d42abf52005274dacd164eb642d3157c0ecb2af016a9a0c8a84ed03de156e1f80d22732076888f8814556649a4b0d68a3c268d8414a41d0996f683f6a9d2237e194e9105b484284e3234e8c172cc98c8c41a85573931e3554fd9121e77d6778da997fd522b19b305a9f32f2a6aa09c7b7b7bab9e7efae9b7dc7befbd5f5fb366cddbfbfafaf89b83eab33820559c3f04e500f19bcb3b8770a973d82af235e4781e7bb2a00730e3070795c67c02ee867b58fddff9003d2755b7ec1db1c7bdccfea122d35aa95c01f20a2d5ab3db987ce91fa3532eb9c1ac9afab2573e29a547eb856755b2cd433b09b69bac82b401fe96948beb1104b51cc7f873362052284a22502cb44f6a2874b2abcca1b18ff3b0b36c6bf11bf55115bf6f437c22c5f1236e2c728a201445ae4ec388230c0f6bd261082703d770bae43e26f4f367d2c82f394cba8864e1189d77285d8803cb0d31bdc3480df93cd3ed1396d32b42e90e61f5ef6eb0b7bdf489e46b777ed5defaec02cd8e079fe4d90fb27e3c00944f4ba75361bd7f574b6af503ef8bafbdef2b4efbca8bb5449b2652eda47dfdd450c05b186034e8924506e22f2a94fa85b94f52ff9948a18210500d986091c6e32d43801599147c30105241eecb18648591f3a072c0b5466ed41c299de245dc100938928154516503e1ca1a080893360e2b81630c8a0357e1691d0c115a44b0c265c28954bb6e6c4c8f5636f685f0b833be634cbae826b3b46e1705f52f2c6ed8b66db6b5b5352f5ebcf8ea471f7df4cb2b57aebc60e7ce9d1ad6be02d1c2b20c8a6401b9f9a9f24ce5913a972bb9e5aa7e53f821853792c0addeb82890b682a240b71f78ee423fff7e40e9b349a4f205c8273c3356d3664e38ffcec88437fdc4ac18b758943476bbd17ac7b14a846384c816a2c18c4682ac617242ba84b409ca3ec8dfd24c608be294e719390ddd5c28bba220e3202162e5f21b4aa03e1232248ec1de60bb3f7d0639c39db89f8ad3acdc3fa788fea8fb30f7a33f148aee84a7c5447aacf5981226568a4fede5e143a37fb7996a5df69efe550f5c6b6f7bfe222ddd574936339bb8008c8064f98072606151a3f5a5372597ffedf3e98d8f7dc9ebdd35434b770a833f8b932105c33a24a4742418de364821e5dca55c0cfdbd2f58a97d1984019b4a67a0e91c421e53c386a82c5c097c5287df00be2e0f82073f2e2b0c28145d9b130f1736dd832bd0c056b67a00fe86222a1a472ddf44c487806c6ae5640c2257a14ae195340a513d3519aa9fb1313ae1f45f474f7ad797cd496fbe6534ad7d455282b707efbdf7de4f3ff8e083d76edebcf934f45ee1edc1e186f98e1210d1518bec8d31a030458d1cff943740c7818213cc61a0d246a4dca1f48d8e423c06a159d1b839ee9c852573afbcd11affa6ffb2ea66bcaed74c4d88f266e1862b4482088f83a1360c211a6477690be2c26f6b934af3572f74b2b91444a763721f5b2a52bfc74a952e37c67d3b9ff5015964b500d40e3fe481ecde503d86a557a2a0c28003d1719e6202c830aaf9877b29410f1b7c9cf479584c9bc44d530ce40bb1d0b6ddc384cbebd9faa6f8bac7be915c73ff8744f7a669145b98147968a28e5be496c27109385df45ee17b4da915b77d22f1ca5f7e90597ddfe7b53d6b9bcdde9dc24a12c97213c2c0aaed60f3503cca35832a0a320f4a8f251578ad289fc8c87d1030dc0195262be845e28a349c0003fb108e60806065b556bee6cba02d8607755478fc2472c56f373261929519f1c9ca8d770c41ac648498a80f3fa313a142dc08cf461e438d3c2c484fac9b2454674c6ac595d4ba6e597397593de991f0ecab3e6fcd7ddf0ff5da135e248394e0088b182867bc3dd8d5d555b778f1e2cbeebefbee6f3cf9e4935f58b366cd387cd4b9bbbb9b27b8e3f9e17c9503564eef70a1aec71669e01f2389d1e898f3669065de1423b95240dafce1669764f495e53104cd0c27f49ab92f99b3aefe6968f6555f30ea66dfe7958deb76a3b59e6da0570b4b3d58642ff1b1690cbba1814bf683ec2f16f804b9c2b0204815ef93100d237d970d6039bac0776292c342bf646d201de0d2e723140fc54707944a4862862b875ee79f60609ff449fea000393e03fbfc1b8d766c293d7c525e85f962037ecc17265bdca3650bcb8d934f8c8b7062af88766d125eebca53ba57def7ddc48a3bbf62ef7ce57491ee2f0f7ab52406f2ff7804f75ea57baaf45d2f2d482ffbfb17331b9ffa9cd7b573be48c685862f953b0ef75e99a4bd26193ff9fa2e044a872d481509292404ac5f0ebb41a0b4fef91c41c53a1841582647d8e6822a04ee8e10aa9200205110eec1920e9dc919571822536a3d16aea39864c9b5526e1116c09020cf9321828537213d2b2abc5099702ac609af6e464aaf99b13e32fe9cff88cebdf206a3f98c47f101563fc6a206cab9afafaff2f5d75f3ffdfefbeffff4a38f3e7aed8a152bae21621546ef951a1a84d8b6cdf9a19c5deeb0df9100f151bcca8ee50179bcd59100da9327328122f0778b16d03d35441da018a079e8a5d71be63f1d9e72f98fadf1e7fcdcab9eb2dba89b9e71cb9b45caaa1419c312292a3735d71673613d41768484ad2ad9713e4be4845f40a2a390eccb4e504cd875806c3b575dd920577e03b37985e67f3584e3f5ef85a06c56e87a0f5d00be6f40fda7e3ea37e207f8b33a7c447612f06ff639d857a2f44fa608e786d650cc3b669fe252fbdae91356ba4384929d65ceeed73e945c7dcfb599f50bdfa7f56e9942898ae6d7ee151f8e8e07196540a1636151ad67dba4f46bb77f2cf9ea5f7e9cdcf8f4c7eccead75766f1b294d9a5b2306e9864144c6209d63214d93828cc3902148105313523849b0f88d115ff6479ea4822b51c7060b3054b125a0af5429513149d165fdc2be4fb0e85a1e26f4438218424cfacbcfe20b2a13482183b67297aed04ca1616810f30ff05daf48b5302ac6f785eaa62f2e99fed62f5a33dff933bd6ac672cdb0d27c6d91c3b66db3b5b5751c11ab0fdc7efb6d3f7ce8a187beb96ad5aa13f1f6604747073b34884f829868e137c8159668381220be21409104d807fc5984bc609832292a400f7d904939be9d533101f64eaf9bf1aa39fdedff159df6966f846aa7be2ccac6f5d9e14a91d463447b4ce1e08d72c95cc806635400444bf664c951026ac8619401a48b4a16361c4449922dec93e5f74b5c963c7415220996f439722b8fd319ba1fae91631464af781890c72b383e782a3e0eb04381a0ba91dfa2f428ff25a7bef85b3aab28dff03e88d2cf0c8f6276c94e3a9e3033711149770a2bb9db705b575c92587bfff712cb6ebededef2c49bb54c7f19d9d4e3926b00c7dd83cbdeabee6abdede573f0599cccc6c7bee4f66c3ec14875697aa65798feeaed26290ee65df93aef03440a1b5276101a264e8aac605f9e87d2e23aa9ac39994c27d166c9057e2b51f14bd9d7ef48f5473815fb50e01a293c27802aae32da702e48263b19f5a68b6468141082a5252ce19a11619b252213ae115a598bad554e6c8f8c99f3e7e8ccab6e34c69ef1a86ec5fa913a8eb488010745125bbf7efd9cfbeebbefd34f3ffdf417376dda7c7657579781854531b91deb59217f546f55eebeccaf7d1f33c7091e1472e3182ebe91839f4e32e8c3e1d09e6204c02a44a9e084e4894c70f6a3c59fcf72387440c7c82991590948565141d35c235ab5571f77de5de1d997df6035ccfd8b56d1d2264a1b1c17d329cc10595e93ed349acf283d9bf41c3d5c9258a12e0e2e52846582e5132329649e392cf6e01b2411e2a146da4a50483a8dc6ff605f20e3473cccf824eb93c79120c44b82f8e4dc30798fc1c7060331ca58e55f869f3e8788a4e324856727859126df9968e349f15a5f5bb5ddb6ec03c9750bbf6c6f5efc0ed1b7bb5964d22179f1f185dcd239e661db194bebdd3229fdda2d1f4b2ef9df1f64363df2af7aefa67aa37fb7d01d2258186f36889d13f1c7e7050c385d52ac01c7ca0aab144d8e99cbaed5a1bd5059517484559dc3cb1894e4221bf3e0f0b9403d61c9d9776535e67d9c91e91d7a21fd8610b98293e1355630170bafee12b1c01a300e0856a85ca44335221d1d9bd22a26ad8c4d7ecb0de68c6bbeabd74c7b69b4f45ec139251289d2c54f2cbee4965b6ef9f6e2c58b3fbf61c38609bb76ed1af4dd41402d28aa0896fa1c8e1a3e1cce210fe84380d185a12d9c2204d74d122259c7a5431a0d30c2a53d46e3998f5bb3dff3ddd2a9975c6f94356f7463b5b61b8af147a6339a41b6d4a27db2a9a47458901a9f34e32570c8e69255914b25505c6cb3a5e11e0cf63504901e128d05b69d2ef48912f7609140a907081aee93bbcf51fb6aef13237406c82b94cf929d061c04d1b360ae162e467cbe707cd847fc14034e232ec7169a9d119e13176ea64fe8c97661f46d1746ef16a1ed79ed4de935f7fe5b7ad56d5f743bd7ce1399c471377ce897e4b10d2ed474aa546b7ffd84d4ca5bff25bdfe912f8ace75a71989764dc76771ec1e6a11100bd7d224e8ddb0897480ba10792196038592f1d01fe83755004dcd6b624842a524aba8389755eae1a088948aeb900a446a393b7d39ad25773f275ebabf32de107517b49eb8f3598f09375425dc9226e1964d72bcf2299d66f58cc722932efea635f992bf98a5b5bb110b5f54e4c0e4f6cececeda679f7df6ad8f3cf2e857d6ae5d7b454767a7b567cf1e5eff2a9760018a30c97c91bf871e3b3ac863f68d8a92ca3fe0368a3d7394ee1182e1c2a286ee5aa5f5bbcc096fbeb574f205df37aba62c1365937b9d92f1ae1b1d235cab8cec6a8448904504cb645b8b613b977c872240bc380215310f1392762a51f65992a25c9d80f3c131501efa4bea8cb71925708d1f8e870cb1afae036438d95ba53a0360072589c3313e3708321d9c5634ca112fc7adee45448faee71e3a7c8a8704b4d224d2a5db44b6e2e45bfb7609ad7b6bb3bbeda54fa457ddf32567c78b1768c98e5af7389a14ef97e6b10b8c05a350331b1eb832b9e2afd73a9b9ffc8c966cabd7923dc4bee3e4487d16cf8a0682458c5ca449793244b01c2221105226d83b6a36681007bd58b4a5f8a59002c37efb0292c35c1fbffd10aa650051e467a07ef8613091907f0d5c2a03f81bae6c680971f5a092936b5de137dd0d639bb20b8ec249fd0549604281ef523129f42b11df9c2a3c557edbc49b8331814f48b81593927ad594b5b1890bbe1799fb81cf99e3172cd443257df2a2e2061c523c1e8f11a93ae9ee7beef9ec830f3f7cfd864d9bcfece8ea14f1449c27b4abde2940192e750cf3b080dcef0f1e5d50d92bf63bd2e04793cf07400f8e2e611c9da0fcc7daa3450be8a492a0bc46078c70697768d245b794cf7fdfe763e3cefead553d79ab573921e585a9d14a7615222c902db2b554a6b2f709a4459215e9295057a5cdc9b53cb04843a90f2c08eb076c3fc812fd96ba8250bece3011cb85b405b2b12fcd3f8747383f2ce285fb6119489714102bddc36af748fb5021db49e2199496103e8caf0b03230274d2b01da1e125b29e7621ba77469ded2fbe27bdf2966f67d63ef061d1b763021ac47cf3631c23e14d8a0698dc2efab64f48adbaeb2389d7efbcc1695d71a59ee8308d4487d03309224bdc714b1a26bb62c1ea315911bfe53fe6e944b07c27a5c6b7599fa18050481252b65ce0181470ffc049299278d11e2e1a043acf11a342c923480b3b4cff37b60382dff230df1be1401cd41667d12221064131628830240956a84a38254d422b1ddb6b554d7e2c36e9826f5a53dffadb5055cb062262929114396cdbb63a3a3a1a9e7df6d92beebce7de1b9f78fa1f5fdbb465ebecf6b63da2abb353a49329ce03080c94027e2b82c5f98a32f6911bee2892ae7d4a7964a0d29e7d86005ca6d2a31431d4d0f568486b0009cd8c248cfaf9cf8466bcf3a7b189e77fcfaa9af2a45edad2e346eb85085793ad2d21526551635d2eedc9df20240c3606aab8738f92dd21c740da20f7096cc3292c592bfe0df0f23c0704e2264147c2c0beba5f2e28ee815e30dc4f6d7dfba77ae158b86b81d243f170a31dbf10a74c0b7b190744cb167aba5be8f156a1756f17ee9e35f3539b177f36b3e6ae4feb1d2be70bbbb78cecae7f836313c7e4c351a169a96432a2b7bd7a6662c9ff7e33b5e1e16fe8f1b6297aaa8b9766f01c2252e45875a802f7ee903231d3c12e9c2d9418ce17a44bb53e1033291729b9fcee137e67a1c230c1610554a294524d6c2425e66e57ca7a45da5808f45bae5902c10da4c0e8ca8f7b226e09a45add4f524529eab339ea5b8410d44b9d3f021ae637075d23442dac32e19875c28d8db3bd8a89eda1c6f9ff6bcd7ccf97ad090bee1e2dbd57402a95c2879de7fdfd965bbe7ccbadb7fefb6bcb575cd6b66bb7d5deda26fafa7a453a25a791a187cab22c619a26e7a7225daa672b772e962263cae1e592afc3c5d188e3e021ef85720f9005e50a15435e0be290a1083fe91e567c2feab406c885e659650d3b4293df7273d9bc6bbe1e6d39fb7fcd8a69eda2649c93b1aa85a3970adbb1c8469be43fc8fe6bd2deb0a8521ed881ddf6418e061cc4e34fb7f91f8866ff80b0cac61360efe97a44b18f50581eef60d547385c83fd2ce09b10378635a564676ca9bb647d1ee240e7049d25bf0481cfb4ed0c3da3ff717c7a52b6a9167ab7f0763bd961bb5fb8f13dc2edd8dc92d8f0c4e7132bfe4ebe79d13bb57847ddb13c7ce87bf763078e6d9b5aa2a35adffed45bfa97df745d62f3b3ff247a77541ac4a48d543711ac24e9469a975fe0a142ba068a0e558440f560e4a42d568a48a1a852e0255456460a8fd76e314ecd5b751d6f4929a1744caa5485c036bb0f2516dc9a91044c922d5248a401fba8541c1669cae9c1e25fa4e29c54f56b3070675044342c3818898e4a4de2816098a5c2b3ca8513aef3bcd2b13d5a79f3f292716ffa9635e3aa7f0fd74c5c335a7aafa8221bf178bce2e5975fbee0b6db6ebbe1c9a79efce2ba751b9ab76edd22dadada447777974825e5fc2b459a50e9210ac8539c03546f957272ca001e4d507cc317da5107516cd26d8fdf2095cf75bc03654ace6054e8b68f3ce94a80a309dd8a26f4aa594bf5c96ffb6574dcb93fd44b9a571bb1b17d4ea4c6f3ac2af20d1184a2d2253b443e0833b378fd2b263e64ab5845738b1e61f1c622ec5648922d125876b9203564b0aac88e2dfc19a2422045438ef3b0236d318489b38817be072f46614813bd6eeccb066c210896b499d8c7647ef5ad5e87ec0d3e19c77695aee20559297e83b698e76c7871a1910f76314fab67a391dabde2b2f8aa07ae4badbbef23a267db64914e97b8eeb147b690abc7063049d44b44b5ae75b312affdfd538995775ce7746eb8c8ccf40bc1bd5769e17a986f85e2974a85de217c851c2a832134166e299033a67fdce5c9c25790d2919006db24f8a826e645c9ad544849ba6497b0fc2d45b606a0c48a60f95beed142ecd97d2e1290a22150d58209165d0da8d7822955fc4d4490c6413d59944e267c241a088619166eb44638a5135dbd7a5a5b6cd279bf0ccff9e02743d3defa7bb3b40e93db47053096bf6bd7ae89f7dd7fff87eeb8e3ce6f2f5bb6ec8a9e8e2e914e5039db2981d5f84394859150884995e3d8bce0a8ed2f340a42a5081724678886050039831c29703f257901ea81eb9a685906d807540cf92a884387d21348fe087980a30fcd0b55366f36a75cf6fb9259effe4264dcb9bf37ebe6b45935d3337ac918e1185126276cc7c95691d7204991e035249b3c059aeda8bfb2b1ed0a8b2442361e13e8b1af7c058070e45d4867786151ba42d224e9dcb92f8a6c81ec8f922a051bc72320244cf394dfa2e3591fa67acd208a60610ff752021703db495e5233c96d61f57bacb34877465cf4cf269feba0c147d71aa62142215d44a81d6fb909114a7508a367fbd4d486c55f4a2cfbeb37539b1e7dbbe8db319e6e7c4cbd598b7218f520a3a48b74bc2cb3e1a98b7a9fff9fef24563e709db367ed29a2af5d88742f351492e430d314502a248f233341a19fa45492d4d00fbf65402e981448512c3ec38a09b2c2dfa6a20383058aa91495d419c2eac82acec7b835c022f7712f54165e4a818f9183970922a51c6c5fd52f45b0108c9f81f695c8c5ee10327b2d5e13e6054b756a8d98a5425815c28b36a544e998cd91496fba3134fbdd3f8e36cd7959b32245ff591c809e4f23b254f2faead5a7de74f3dfaebdfd8ebb7eb8f2f555a76cd9b24d74eedd2b9c5492878291cb165574d3a41234c039e44aee437bb514d9c236976801aa474be6e9910171a81eb3bc4036758f4ada8f25508b7b683741d102baeeef0618a53022159d46cba98b22f3def3fdd289e7df10aa9eb0dc286d4a7b66199d241ee19730e84856e031a0a2ca5e907fe006387a942c12bf518ee380efcb94c791d35fb0258fe213a1a18ac4fe83b6f02710f611144a6e959f92be8963e66d0e385229f095ec373152c223261407d95e97ecae9cd74c76977c13d2085b1c227b6bd27516a5cd48760bbd77a770bbb6d6a6b6bcf081fed7f1599edbbfe0b42e3f89220dfb771bf5f04b6af402c3465a7cef98e4da85efea7bed9eafd9ed1bde2e9c64547312c220168dcfe180d903703a4c96701d15323b3f1e562185405727fdce754c504efe45d74865944257b2483b989b853981e8b8ce2d0190275529d4562ab43c471b867f0fda9331fae1d00bc5bf01a9eab92d097905ed831cc87e628a5bf6a0397a48648c12615b95c28ed57976c5c43ead76e2d3d1e96ff9a63ef1cdb7e8e1d26ebe601400e5dcd1d151f7f8e2c56fbfed8ebbae5bbafcf57feaedeb8fc5e349ca10e410e5013d3fbeb745054364cb1536e60610b901815273b1b8cce91808972a6b6ced9ccfe928391a50f7c496ee2b8b7624011dc2eda0176848f8cf584c28542f1b116a9bcaa0f832240739ba777414304061a169ae16a96ad3c72fb8233cf9c2ef1b55939e332a5ad24eac467856291192109318ee7bca1dcd26e30e4545c35b91209019d97e92be4142d5256c659d97f0d5dcff2d7d857f0d5701f996bb2470d22c710700744ffea703f88b34910da57d90b8ecfd280cac8c6adc93bd756db237d9d3944c5821ff2a0a83a1448c2a387686242d742725b036a5e9748950a6531889bd93d2bb967f34b9ea817f75762f9b4f816214b99fe8d18b9137fa23042a582d9d4a85b5ee4dd3922bfefea9f4eb0bbfe1756c3ccb48b40b3dddc31faf345c902c5200101214371b30b9855eb8a43f2058b2d7421e938a0c859203856a390405e5b4b045f10f121c6795e03f12a4c46815e40e0faaf3082bdb20682d4892c542e794a8fb41555158b81a5b29f2b9b08f7603c78b5607910ac78cf2caed76a85264226312e958d32eaffea43f86e7bef7ebc6a48b6e93df1d2c7ea09c31b97dc78e1d931f7ae8e18fdcb7f0a11b56bebeeab2d6ddbb35acdc8e8f3a233751cc301a9c5ff838360ff7c9c9ed6a58d0775e03240bdbdcdfb856ea881490b3a305dc83e2948539e2f0f401223360748b01c87fa9adfc0be591a71ca1b2f4481c9401eeabea553101e95242e93b7aca17a0d0f078a5f896731686265ef013bd62d2f35e4953af1b2af760a7f19169c7f0473ca8dec20700fe866a8cd45707be09be64c087482f203d0590d569581a25124a9d705deeb510f95b86c716bf7d7ac4c399d8ca8890021e7e84ad24b2864ff390b1259f498217caf09b0352bcf44ce8d54274205868f4bab0b344b2dc0c912c748210c90a93af36331dc2e8df26f4de2dd174ebd2f725d73cf0797bc7f30b446af47f685ae5fca80232dd4d76d5e83b9fb930b5f4cf5fcd6c7afa3322b9738a617750c1f5922485eee2239a44848868a9f54178ce121536faa1a05859a3261db0cebd4bc3c1574628208503f83a5f890704c7f95674cebf067fd517cd4188204897829a5725e756c91e32257413ff7eb21241b999b241c949a971c6a4bff2e3d5a81ca88016297685109146e156cfb2cdbab97bc2e3cebcaf64d6bb3e6fce7ddff7f4ea69af6aba99e1088b1c28e7cececeba975e7ae9cd7ffffb2dd72e7ee2a9afb7eedc3dbdbbb35be0c3ce2046106e2551460f9054120ce3ee0fb2cc6500e56cf7f71b3854873cf45a49e6780b751859102925c36730c93ac474e713c8173f5f0f50524717542ea42a39e55b84f9a3f20584d03f14e0188166869346f3198f45e7bdeffa50cb59bff36a67edf2aaa7a5bdd26621b0ae9611e31108b564109318126ea0d1f5ec4760ffe11b646b3e8b218d299c56227baaf0c36fe4b3d03125eca500f810d409dc8fd40fdf49e42a42bfa193b02903c009902cd94dc0e922e7473148fda574fb032b04f84df02410496c304d83eec97590eee3908f765342b77b8499ee147aaac3b0db5ebbba6fd53dd725d7ddff41af67eb94d1bca696cadd51012a3c2d9d4e8545cfa629eeca5b3e9a79f9b7dff7b63ef54f46dfb66a3dd92a74a78f14110c19f3af48dc24091404cc1b025d24fae3ca69ede8a950048b8d2efefbfb030a9a03fc04d3872241f81a02e25502320531491fb952d05655169906991e082b2f5f843d125252a20c2454b150692463e3fa035285be352e308a0bcf65e23eb89c7c37a747c7987d543856b5d0cac6c68dcac9cba3d32ffbba35ffe39f35a75c74a7595adb8aab1145314396733abc63c78e2977de79e7fffbfb2db7fde41fcf3cfbe1cd9b3755eedab543f4f5740ac74ed193a0f749b5a4c82c5081f1cc06f4e681307399a275282bbe02ca4d96bd3c3ef4776ed8fd4185cb0dabf401c0e16c18907b26f8b034230e2258a455d0229936ec719e48edf151083590e941934002f5ec4074f8e842166fb6c0647ec896368037ad0a09a52f3e5cd2a7c22628c051876e461266fdec7f8466bcfb4791c96fbfdea89ef58c5b39a9d72d6970b04abc8b8ff3eb6a123a9400b5c4f71b784b8f6ab69c530cd540c39c63655d669f45bf78ab64e038ea9c147cc85aedb327a38be41c64c4427ec6f753f886afa626e283f33391a3340c0859166e0bc8f306a5cda030f06d6a189127c6e35e2059ec6bc9f3628b91050a8fb7291d4c8e7713641fe3746dbf3092bb84dbbd49d87b569eddb77ae177e2cb6ffebabef7f55328c2685e1aaa4719a326c1fcddc1f89e7a63d7730b32cbfff699f486459fd57bb69e20525d4264ba89602584e18079cb8266b5534486b4080e500920b7fb3ebe52cea1001702704a09e2c67da4f2d106caca5b795f3e460a284db9544cb5bec8c0968e4987881e19eccb7f80dcd27514814a3729dac0fdd141c29f6920a575f17a6fb85668152dae5135bed36a9c777be98c4bbf614e7af3dfcd122257a3646906b458babbbbeb962c59b2e0f6db6fffea73cf3df7d95d3bb74fc3920cbd3ddd22994cf07a2ce87606384f0903e536a87c95bc3106f2f7a8c12f439040de526982ede501d4eaa4fbecef56521b87d3fd8280b229cb2b46169aa6db54067c377f53b4207d948a1de05884675183d79c70fe1dd129977fdb6c38f3377af5f4355ad5f43e51d64c8de40a61635402c484e7e4421588c87898379aa1dfd887fecac6b5028e8094c9bd2c9868914980e06528b60d08080244c7e49746b2fe1222ef892bc97fd1711e161c8817e1a5201d9270d1f57e9b40a6c9b731748fdcf038c6f38c7d7b4b348b0ed37d98a081d0a5c835268496e9145aa25568a93d5599b6951f8cafbceb2be90d8fbd55f4ed1a4757611d8c518322b1b4fb071c13e65ee97b57cf49befaa7ff977ee54fdfb5372cfe57ad6f47937c73304ee40ac3831816cc50015221515963ed0ebcf940e549428a44828255927d7459d80782af3b4cb4386e5f72338f958c54865499efc5c74801a9a5ce6c5f327e440476ef2b3248150b583f9d6781c2e23a0a41bf41bb5cbcada1cbae568ccb4b6a07626551653485ad85856b950baf64aca3574eda119a74fe0fcc791ffe86d172d6639a158d232dc50e94334964fdfaf5f36ebbedb6cfdf74d34d3f7bfef9e7ff79d7ce1de5f8ee603281e1418cf9538eb892b84843405b3fbf65ee12e8376f07cab6506a2ed307f86954091a51b8a3693e0f543d2fb98265e2cc0c958387e117902cb52d4650ba8a3361018e1af843d34da73e1d9efdfeef45667fe49356d3597f352a26763b913ab2e925e433f006225565d833e82ae633910dd4586fe13f281226480a4a6506772ac8e928a0339238a1575d122919363bef4a9225653da511c15f0ac9260542c42f67d851cee1524221293d9abf8e57f61adc490a681bb6eaee1408a387c2d00d12f2d318b1213f6eb82961ba7d42cf74083db9c7e86f5d7ee59e65b7ff7befb2bf7f3dbdedf973849d2ca12aa222296ae0898b16e4544d2db9b746dff5fc79c915b77cc15ef7d017bccef5a769a976cd48b652807e222c692a263060c9a43174268b502acd1b4329e6fe71704589098b448a40aa88fd633c1b044bc2bf07ff46383f6df41bad124309fd4687936650fa5129e8be2056783a9b5a35e8e6c53e77fde25b5846a9704395c22e192fecf2a909af7cfc2a6bf285dfd1275ff607b3b47e2755d083c98082035dc07d7d7d1544aacebbebce3bbff6dc73cf7d61dbb66d3331b1bdb3ab9b3fea6cdbf6805364e381f225603fbb55955a8942a1b2216be8301c991f405b7d8dcd556d3678c7372ccb4a81bc289d5143c459e4ee07089007908d06d9d2c7cc7bde9a7ee5cfb5b1e7fc4294b5743825633d275a4b2d83329230997bb2f9066c09fc0b9ad9b8d6f7730390facb7e83fec9e9276404d81cc0184891a608d7b167216244f1f822213b0cb2c00514874facd402a5388ead244e59fbc2c3ee3e0193c0fd7304f1d075487d9688d119dc9f041d0ee8b5339cb4309d5e225a9dc2c474a0aead13e2db977dac6fe5bd5f4d6f7efa2d5ab2ab66344c8acfe64c3101bd57e97448ebde3c35f5fa1dff9c5a7ed3b732db9ff9a04876c4bc44077113ccb74230f40041196477a87c1cbfc07ce7962b033d5a38cf457c84328c7242bd15d9c207a6795d2de23a2c204ee8d16272a5faa3b012ae234c3a6f51c22caa48cce8e931b0a81be6d2d894e6345d9120499282a68d3011ac1221a215429436baa26202b5884eba2f3af75d9f37265d7cb319ab6e97e9297ea092b4b7b7373df6d863efb9e79e7bbef5ca922557efdebd3bd2d1d13130b9fd607b1c50c6c50899f6fc0d1706181e2059b4e1960fdb039f6415abde04387ea0e9a66d548c5b654e7eebff84c79df733bd7a6a9b281b9771a295c20b458516d2856e61c2387b3bf639ba8351113ac6d394e003655c0afcf20b7c12f91b259a4ea4ca17b9d27cf65c56c8def2fc18f47da951132c842aa7a540045eb002c1e2ba8386bf6cfc8370211c1fc339266694be5ca173722bc301982bcd359304cbefe00d7132fe3c426578497ac6242ff5a0a5761be9b63517f5aebaff86c4aa7b3fa6f56e9f84afbc7024450a3c6951819969aabfc2d8f3ca59e955b7ff4b7af3639ff3da579fa1a7885c65ba28403f1548920a126f0f66916b2847d6684271e576e02e449ea062d84254f72c7ab2d06b8516056f4971b3936bd12323f7b8c2500de1172ef8342a86d249a263748c2742eaa670cc887043a5c289d509afb439e596b76cb3c69ef4dfd1b9577c5f6f9affe4681a1e4c2412b16ddbb64d59b870e1c7172f5efc8d0d1b369c8e4fe274767672ef15566907c9024951325a318a937ecc804815554c8ca6a2be5215a33a95bf1ec683c768d6f3004704cf2a6fdc1e9d76e95f621317fc50ab1cff8256dedce59536786ea84a08b2fd44c6b8478bfd0c7c0e3907a88bd419d66a19d1800f943d43204f0393d707048d7d294cae101ffb37d9a8951e4e09ea093b2412395f8c7bb3b8574a9d9761f157769ea96308a3241b0ee0e5221cf271780e9e2706e248f592cf520a886c69b62d0c9b8816966542274ba255b83ddbe7f56d79ee0bc935f77d46ebde305b64d2453b7c289fa548c0af69f66e9994587bf7fb132b6fb936bdf3854fbb89b62697c815e65d19068808c8098c2494615f0c25585096a172b4803ba9bba9e2cdc60e62a594160aec0f23d25f5e630421f92d41daf7b7e8b181c8de1b84a5e2d1a8056184499763d4142f115e845a36a58d9e5635a9cfa89fb53832fb8aaf1ab3aefaa95e3563395a437451d10344babbbbbbfab9e79ebbf4d65b6fbd9eb69fdbb163c738902bac7b457a30504e87525e47b36c47023a3e98990fc859b1a306f94a2de907d96c9efb07a6c5fa52ec3a13e0f8835e3a664b78da65bf8f9df0c1af98132ff8895bd6bc3313ad7132568548eb21e224e851d2c9e7982439a4857d1f7a66f1161f3c4f56b7a59e4bdfc30d7afa25fd90a452dc7145e7a51fe33ffb6e9934f9f7a37df45a813849b245fb3ee152570158ff515eaf0477c116e131b5075bfa8ff4216d44220d4abf458d1f2c4d847968ae9de6b96802438844060d3d2344ba4b78893df589ddcb3fd6f7da9dd7a7b73e75a996ee2ccae143e452c1c15fe0cef496ebedafcdef5bfeb7cff4acbdf76be98e1517e9f1ed86c01b06192c4c9e20264ec533405cb250c46a3882351892a51f990c06ab8b7f9becdd14c122a5f527bdf3c4776e2d501cec6b7342b382faddbd0e3d23891c812425d42d611a21a15965428fd60ab3ac39a195356f331be7fd293cfdf2ef1ae3cfbfc72ca969a348b311162930f7aabfbfbf64ebd6add31e7ae8a10fdf7ffffd37bcf2ca2b1fd8b66d5b456b6babe8eded6582a586070fc5011e4ad8631f83eb415123ef49a59ae9eb56a033018a141ee6699975335ed0275ff6bfe684f37eec968d5b63c7c6f6ca0f4d97f342d38e6152b39d0464848809bb73ee616237c2fe10440b7d52729e137aad889ef99d14f26dc0dc3a80ca88e6bf244e5240a6100aa48e03d19e1c4484df93240f5ba4419d9501d131c5db4182f0324e793ff95bf8439efcb21a4f82c7324b44d8e00831d9dfc15b8749ba27bee4121786dd298cf876e1f4ed8c25db56beab7fed63d7a6d63cfc01af7bf33491ee2f85afc11d8a01054d081939ca652f2c7ab74dc8acbdff9afe157fbc2eb9e5894f69bd5b5bbcc45ee1f1caed94b9a40c187ae34fe17059d065540892b11f0c71954a966b5c31967df0e247330894103a2781c780c290ea92b261614c7e32d57181de2a353e4eeaa5944f5e428a8c8176aa30ba16924cdeb03ac877030000f3bf494441542876fa4d7aa26b6121ac2a61948e75b5aae9dd56e3490fc5665ef9656bf67bbfa78f99fb82668430cfa4e861dbb6b963c78e898f3ffef8d57ffad39fbe4b24eb3abc49d8dedecee48ace73b9e4aecc9e0b1c1b8e4cabf2ccc5d0dfc5024ad6be0f3602a0dbf88a370a205b4d7901e90513ac5c5d2a56147bfa02e40572a987296ff973ecd40f7fda98b8e0776eedec76a772a28dcff238205a9842423e4436dfe17fc0576043d1cb256d2984f6d80f491704c2850b6090949e614b7e888702655cf063729167224e7e302ce92023f1ef287b03e83cd96172949816939d1203d07998232539806f851394ab00a0074b7efa0c403d453f975cc81bfb78392c437c2023745e4da09fc99699da23acd44ea1f7ac3d29b9eec11bd24bfefc9dccc645ef14bd3b2760d9278eacc0502c21ef40b79e96e9ab7076bc7846f2b5bf7ebe7fe5cd37dabb9ebbdc88ef0c99f13661243a856b2728b333543854a0a410ec3c218788e19d2e0afc6065782865448841f760f604c8ec1dce8f28a52556c50bb3618b49599a4604cb03e10a5365c1d20c65c28bd6274449c3b668c3bcdfc466bfe3bbe684f3ef364beb768f86b5af285ff4442211254235f7965b6ef9da9d77dcf1e3175f7cf1aa0d1b36546fdfbe5d600e567f7fffc0dcab37722e6f745e95c3f0655e38bc51ba8f3e8aebf98b01a413540cd2e9a8f228363dc9c1fe0d4f80e30af8049a39e694a7a3b3affa6968e279df772ac7adf462637a9d68adeb864b856b4611480e23122182fb91fa0d19eae2a54f93535724c9190af460c93957389f7b3daea5ebe08fe1d3983465d574dfbaa47eab70244388964c239129ee640015c432e1b4a574213e3c075692e7f71931b7cc458f962f6e425876a73013ad42efde2444c7aaaaf48e67dfd5bfe28e1f275fbbe58bdade552752242585eed52ad0cdd321ad77cba4f4bafbaf4eaefcdbd7323b9effa8d6bf67ac9e890b4ba484857157ca6e743cf20706c1b891d97e81ec0ffb3398caa002d8cffd7d6450e9419c7e77ad52ca01e5554ab56fda40b470d4a51dc735846debc2c6da57468c171615a5e3845e39bd37da70c283a5332efdba39f5f25fea55d39669c6e8f82c8e6ddb667b7b7bc333cf3cf3f6bbeebaebba65cb967da4b3abab0e6f0d625810e5800f37abb7bc507e8a6c0d878329b7a357b64717782692e24cdc7102d20dcf300c08eb1c1df0cf040850ecd03cbda46ebb35f92d7f8acdb8f26be684737fafd5ced8e2554d4e3bb10691b14a854d0d741bc387e44bd069c557918e83b6706f1109f75641064d5887a705a982b7a26320374cc220f2a824579224f1b0230f3de23cced1b161eb12ae1d2a327c967aa02f8dee4da7601e3d8c7362ca0cf9449966902c439844c242a6254206e66ae14b27c411dc8cf0305f8b788397e915ba8d9ead1e6126f78c7176bdfaa9f4cadb6fb4d73df04e91686bc27250fe0df30ef5a4790133ca4c7f89b373c5fce4b29b3f935c7df7d7dd5d2f5f6af46d8d7af15dc248f6080b1f75a6f23774bf9b10194f1ac3c376c4d26561ca64fb8e8bf715d4b1ec7114a854b6e1152117073e2f5da48c4f02f7a0f4b182322564c80f6c1206f954ecfbe966c1755053102cca16d2576c5d7c16c72c136e49435a948c6d33ebe7fec19a7bf58dc68437df81374f289edc04142550ce44a462ebd6ad3be1eebbeffec23df7dcf343225857b5b6b69a58581424cbb66d2e0f45b200102c08e6640dc51b979dc4507d2826e4ab45453955bc993014794c29912b9bf4c8e1563388bd7fbc4851e4c90b50007846b4a2d36a39fb516bd6bb7f149d7ac98d5ee594e7b5b2313d9970a5e718619121a2c5d4887d8f5421e5fb948060499f453e887f4b1fa6fc137b26b2a3a03fd84725c52c2a393408b2e50b3c9eef8ee47211cabca970742d9d576fdb239eacffc45bf7724fd96c6c5c2258fca621f97b102dacb5851e2e430f09cb08f15c2d93ea2e8816be4fac6339a74c925af4fd42b37b8491e91056629bb0fa77185edb8ab7a657dffbfdccea073ea1756f9c25bc78ac10bd5a79bb21395553c4db9a329b1ebf3cb5e2e62f64363df531d1bd639291e81622d94f9995a162661ecd24056582a96fcc62757c0619054005845e2d59265c64ac34fb0005a944629f7955508e5c610c0d9405ae97f75559862dabdec03e1341de8372527808f76c290106ce90203cf175221a86592244a85c68e52d42af9dd7678c99fb5c64daa5d71bb3aefa77bd72d2aad1f25167bc214a44aaf989279e78d7cd37dffcdd679f7df60bbb76ed1aaf966518da53054285638a74c1f929d2a5307c190f0f65480ee59a7c20af69c25205a309f99d97453a3778edb52225e64599a80045004d7743a5f5bb8cf10bee2e9df79e6b43e3def40bb37aea2e5131c1151801f142a43ce81d223b0ab2827d4c50c73c2d1e78cb0aaf7de54f5e07e0d1f08bfbb63c223158c791048b96caf958c380fd27d4155bdf9ff23145c47208190b0818cefb42fba8832c642379123f9129d9ab4629b2e9b84d7e83842a2e912b49002d0a675138f526227f6bd1c59b8871a127f60ad1bf4b88ae6dcd89cd4f7f29b1f4e6ef64d62f7ea7e8db399e5731c823067bb31100651c7fd459eb5c3f2bb3e26f9fc82cfdf38dced6a7af767bb6c5bcbe56e1a4c04033e41724bbe66e422a2fd9c54904042c960444997b38a8201461c21a5207638978e81185405b25fb800bfdc0c81235996d5975ccc6273f2f8067a0d4b3f85db3dc7ae0773a7c910a2e8c08af7fe2852b8488d479a26c6c8fd678c24da139577fd9987ad99f65ef155e4d2c6ec8724e87d6af5f7fc21d77dcf195db6fbffd27cf3ffffca59b366d3276eedc29bababa787906102a05942704044b1d1f3af1fd708849b1112c057ad611af6f01f60f22ef0eb5b8073eaba32440804305e98d0e9b07f10fe51d46a8a4d7ac9ff56c64f6bbfe2b36fedc1f69a54ddbdc68bd9da1063be6f37a025c02024ae2132b265a20308670fc7d4ccec9f5612047728c051d1fe49b99608168811c11d9a13691922c7cc2942b8a5cf9d749d2953da6e2202fc07e0039c99fc3232e20c5643e80a9580e112c274361681f438a061d3799602901c922bf4b0d288d7c8963f70b27be4738dd5b85e85c177377bef8f6d46bb7ff24b3e2e66bf5bdafcf0727c957d98da8d1a716a3a1a5fb2a8d5d2fbd29fdfa1dff92def6ec27dcde1d33b54c5c985e928447902501f2f076019401848ad82b3dbf1456683a4e6728d3c16cd1cdc8dd88746edf5c42010e06c2bfa129a5f8f60f5c2d254bb4900608d22eb7dc6bc5e5a6f641a600c40d912d0a7c87d0d32ce19921615b5421c2f542ab1c97d6aa276e379ae7ff223ceb6d3fd36b67bcaa99a3e3cd41286b3c1e2f7fedb5d7ce7ef0c107bff8ca2baf7cbabbbbbb16c382e8bd4a26933c076b682f160042046285614360e8f943852258c546b458870fae4d708480e5f2ef73307a7f1c01248b328588969f3db03745a627014605cceecece869e9e9e5ab26d95f4bb906fb1797aa4a23d34f1c23b4a265ff8bd7055cb72a36a72bf5b365668a14ae19aa5e4ff30295e36ea993ec107e142aa0783bd25ea05e816965b445780ea468029a1908348d560204c16cafcd096c815b6f27a7937b94037f68934e1007c674e3544f5e43aaad247fe52ae2420ef2279019de761458e81cfebfce2189249f7246e81ade725849eee124ea25368fd6df5eeeea51f4dafb9ff2b7aebabe7e2c53bba7e70d247002372034ab896c9a4437adf8ef1e935f7bd2ff3da6dd73a9b9ffd84d6b77b8c1eef12a693162143136174e4205360ecf8424a0e672efd077bc5f82ccc22912e5ea0cc2022c619beff6423d39528e41e1b7aeee080d4293910fc303ec142f6f26baa7c1cc7646f1dd7493d223cab8c0856b548c7c6f6bbd5939f0bcd7be7178dd9effa4fbdbc65ad5c9eb7f841c4090b8bd62d5ebcf89a7b1fb8efbbaf2e5bfaded6d656033d57c867d55b355400943b860641b0540f56eef943857298c5e83859c7f3f52dc93c0ebf8d265019205f482da50d9065529c448bd28495e98b2f610144fb9e3d8d8b1e7ffc934f2c5efc2f8b1f7fec23dbb76d99468dc85001cbcbd34b6a7686a65cf2d7d8ec2bbf1a6a3af95651dad06197d4671c225a8e1e1519112231854349845f85fe4b63c45582148e7e3121924741b014e49b8800b6fb3761ca2bcb4ca0fac55120bc7f0f08df2757282d74213e2107106f120efde1f7ddd03b828e090a809e2d7e0b9ffd29cec1f7485f81670174f8108a878310813029ee10ed1b5e5a18996ee1c55b85d7b75d73f7ac7e676acd422cf5708d16df3b66a4977a90293e8ac0c2a25abaa78a98e299c9e5b77d36bdeef12f6a7bd79f67a67b343da5d6bdc238afcc1818b8817d1699919e4bcc9444ce690231a1c36c18f1fb307539b7708f3224bb86f86a455b1e36a4b443b087ee4f1dcb3284ca84573a4678e513d36ef9a456a3f1c4bf4766bced067ddc9bee37a2951db89c232d726069864d9b36cdbaefbefbfee589279ffcc6c60d1bcfc6479d41b0d08b859eabfd1126553114d43c2c59c6b27c0fd7f90d8dbb9050cf9fef34f9cd96c3cec36311941758c3e1e857fe1102d2ebef062822747675366cdbb1eb92d56b377eec99675ffae2238f2cfae2bad5abceeae9e9aaa79a5ea85e2d4fb72209b371fed3d1d96fffafc8e48bbfa3d5ce78ce2d6be9b563f5c2b1cae59a5a1832e4062d5d81a13f0c037a58a310c3821e11158f3b3ed88ba127688060c12ee33a7832ba149a094634048a50701454d5f01b73b9d077e6f79f0d885c538b889d3f3ca4c10750fcb807fab894cdc46ff4a9b1ef27b285ce0a660de45fc10f100cfd6fc41d85cb9fa143dcb88eae22e16f033b44b4ec84d0129d42f4ef164ec78633531b9ffc467acd3dffaa75ac3e596412e564ab55f28f2a8e5aa49421dc7b25fa778e4fafb8e563a997fffc7d7bdda2cf99edeb27697d9dc248f509d3ce902610e9a08c4126a00855d7213212901690c268161d93e404f0d00bc44219cee37f384ec2db2c10cf00ada14c6741ac436c2bc2a97b2aa0509530a747b72a0ba5cb17c486380d8a4ea600c54fe249468d37041190e34170028641c1b23d232a84151322522fbc8a09fd66cdcc1763b3dff1a5d0491fbc566f9cff0fcd0827fd4b8a1a50c6542a55faf292972fbae9e6bffef891471ef9e68675eb26ecd8ba4d7463ee553ac5f3ac542b43e5f5d0fc56403835274b91adfd853d1054d9a9fdc202cf9ed56b7f7be80f7518a09c96e6cfbfdbe0bc1f5c0f0a0f7e2d292f20ddc2bd5c951f4a5f149529264a43e9a3a491b10b507448a7d2d1bedede31bb76ec6c5ebd7acdd8e79e7bf19f6fbdfdf6df3ef4e0c22f6cddba793a955ba45065a71956daa89ab43c34f3edbf8d9df6c97f355b4eff5da66c7c971bab731db3543866c8f745f05d649f8860b95e9afc975c8f12c404ed107840f66cb0a3fc2892d8b067259f88e36c62c9d270bd4118e58bf77974f850e9e7a5fd91555e4dbd21ebcf47704739f11dfa8f3492e05efefde027d071411599841e0061283d98288ff3b83e8d74d1799eafed27031ba4d175887f38b67012edc2edd946446bedf8e4ba47af4bbefae79f65363c7c8d1edf33224b3df8b97264c090919644efd5923332cb6ffe8cb3f6deafe8edabcf32e8618c04be9cdd2b44264d4f496cd6cf58a9831024012c95f6a9909019921821a3610ce9187e5178ce6875bd9f81870ba9247e643ef6ff5ba61122891614231b5685437a31ec05e5742981206a3c6550b7846b96082f5625bcb2968c51d6bcd7ac9b716b78fae5371813cebf833f8b93afa1a423003d273eea1cddb56bd784471f7bf49a07173e78c3da55abdfd2d6ba4b6f6ddd25baba3a44229e90931431f9d0274eb21c65f97139e700c720a840800a7b2450e5514cc85f9ad05b33cade2ecc1348aff041867df2e6f8d69700870adb71ac647fb2a2bbab53ec696d153b766e15eb37ad9bfafc8b2f7e96ece2e7962d5bb2a0b7b7b790dfd1f374339c302ac7af8ccc7cdbafc313cefc9957debcce2d6b8c6b915ae1864a8446640b4405f3a1d9d77a7e6f133960f9a512e81f6d99e4903de68a43c7680bdd64bf8ce37c35048f4a5bd5f94190a3504330d0d901d2e5df83ffe2beb4c35190ef54268cf6711f9c834fc515aae345dd07401a6d6aaf391427fb68ff1aa455f209d911e212c9323209e2251d42efdd2eb4de6dc2eb58774662c3a2eb536beefb94d6858f4df796d17588e5a860985c38045042f875c8dead93d26beff850f2955fff38b3e9e1cf69f1f65ac3ee16a64e3403bd7b5480c41031e5944914c00f4ecf21b7c87b5093a1c011f9acc83866b9b47f341e1f999f2b00ba2b9901fb053be0ecb155428aa33a4199f1f94c1e61d5046e265aa4c0e8c2cc50f8b4161576a88248567352af99f16a68ea5bae33675d73a3de78f2539a111a15bd5750bafefefef2679f7ffeb23ffef9cf3fb8edf6db7fb276eddaf91d7b3be4d0a0ff591cce0b999dfb40f552051871eca7048e6fa0270bd5ddff59d4207b129461b10225e3b906f7023969d1dfd72df6ec69155b766c89fee385a73f76e7bdb7fdd7134f3cfa313a36a180440bf0f4f2b1ebc3b3aefa59c909d77c2e3c76febd5ae5f83e275a2d3c3342442b2c3c033d5b78ebd0e0e136db21a2829118bc79483e2cdb2b247db1c3a313cadfc9cfc051007937f6d5321c7c3b1f3920d1f2fd3a132d088816449e57154046a5e2911d1df0adf22aa64fb447fe1a67736a0def52dae5b0244248c28511060c21623153c3ee27c2b547185d5bc6a5373ef18df8ab37fd28bd61f1d55ebabb0a3e8f233a420c930307074e402651a277bc7e6266ed5dff1cdff8c097327b5f3b5defdba9eb58e6dee9e1096720594c46a864d02d282fe6ec90fb8390ede992992ebb1825c1f28f1c548a0f9c3720034a72c1a9a2438a6041e88f3ca990a320b9714097c021601b3d838a939e5b33a2c28d540811ad76bc5843b75e39e1d1f0b4b75e6f4cb9f4cf5679d33662756ac0bba861dbb6d9d5d555b378f11357df7dd73ddf5ebaf4d5776fdfb2bd62f78e5da2afa74bd88994f032a4b4286655c601f601e9ca811533c08882eab34b2df76c0526e41ae500010e06a4479e6b3b215e6fcd498944a25f7475b58bb6dddbc5c68debc59a756ba63ff3d2f35f5afce4e39fd8bd6bc7542c6d53c0ba8f8f4d775bcd672c8a4e7bdb4fcd86b97f3622b5dd2252e77aa172a19b21f2a9169c95c0447339d91cbd54f23b82b21f41f66239e4eb786a0746f8c9d9c9e93c7e0f16b6707ab4cf040bfb708abcaf5e00f33188788154f9bb830062a4fc7f960770f206b843f6424ce807d9ca6d9ab0ff2670127082053d761863a22d959f9e490891ec11a27787f0fa76ebf69e359724563ffc6d7be393efd2929db536d6f73c42e43eed21414bf756a4b63d7951ffea5b3fd9bff3d9f77bfd6dcd46a697722143241f139e6de16192bb9de60743a671065066213b069e198758e4799c43588864a054b86409d1b5c81fa1c4996cde1e1c06152addc32750aa10a0288853b176065dc3dd9324b248a4a02059e1703d5e196531b89706c363a93416dcb485a05682563e469875d35246c3acf5a189e7ff549ffdbeebb4a6d39ed0ad2895ece8007a2a31b9fdeebbeff9ecc30f3d72ddd64d9b67f7eced144e0a1fe9a4160115157fc493ca86074a5140546e01f645de0c6d305c382c0cc3a06a3b6a9413352940118288069939cf408f888b49e302bdf83635465322934a888e8e76b16ddb96dae75f7ae1330f3efce057d7ac597d763c1ec77201f9a9ffc340d34ddba89ff54a64c695ffadb72cf8a55b3a7187136dccd86635a5be44643c8bc816f1090c036218d1309960a56c47a4b9778b14520d1dc2d7d1391b3e9ab6dc93c404cb275a6a9f05c407e741b4285ede2aa1b3be0c2653205e74d0e701c30b902568f0f074271efec4a5f0ed1c434ebc1e9945547f76fbb4c5f57c25864ac9771be93e61a6f60a33d1d69cd9fce4f5a935f7fc3fbd67e36c9e6b3e70a743c760f671d0708cccee25a7f7aebbfbe3c99dcfbcc7ee58dfc2af477a494a8a435b7a2866f918264cd14d5cbf9787d289522170460c882258728b3f72bc165bc9680784330699c79b8380ff887e41e70ac89564e2744b62e544fd684f763fe24e004fc6f37bac20f84da9f4cf5141a21b95481694136311dcdd6a458408977b7af9985eb366ca13912997de68cd7ad72f226366ac182ddf1d4437774f4f4ff99ad56b4eb9ffbefbbfbae8d1c7afddbc69f384dd3b768aee8e4ee1a631fc4b3980d600e503044586377191abd98a10a00090ca1b601098606968f19192060870f8c0fa1ad4c6446702a9934b261d53626c5ba49349d1dbd5059225b66dd9145bb27cd9471e7ef491eb5e7ee9c5cbbbbaf636a257cb8f23ffd00dc7a89af0ba31f5f2ff35c62ff80fb774fc2b76a4b1cb0ed7655ca392485639f9d5188945ee11cb3d1099a286345c36f7ffd0169d10fc32184597f587e4ff54cf15d72d127012265bf84d1e817fe3387ec33f481f3a00aa9ad26728411420514afc2807441e53c02edf99d23870d8bf562687e264911d3e748483e0a48b74bae4c188ab98894e61f4edc64af12d99cdfff84666f5fd5fd3db5f3b5364faaa0e77523cd275c870e37beb92bb5f3e37d3b3e56cd74996084a1ca918251be3b35428e811a22705a9624609728587c57812c677a970006694fcc032192039aa2709473863f83c32c6ef0de3df3e193b2820fcbe60c247505d909c261249a6e43900f4811f2647e43341e9308ead89341510d6217162e5c2ac1e2ff49a69b65637bb4d6b3af50fe1d9575e678c5b70afbf34c3a84032998c6cddba75ea430f3ef489db6fbbe37b4b972d7f5f77779791eeef976f4a5256d999144986bb8f91675c1e283bae44286e525a0a9b2bc73b289f824c28204807a1a5641086b70901021c0ca047d20dc0d691af230782b5a7b06e136ca19371849d4c89eece2ed1b673a758bdf2f50b1f7cf8e16fdf79e7dddf58bd7ad5d9a9542a56405be0852bc76eb1a65e7653f4948f7e353ce3f21f9a4d27bca4d54def734a9a846d94902f2b11691bf3b1c2226445856586f803cd18b101d1e275ac4065305cc8a21e05bed2275cb4cf73ae791926794e6e731f1bfbf2379f637f8f1d74ae109fa08359f13b5906441e979d3100c5433e07d98a5e32ac144f01060469e45b709949502cd212d079ee1c72a8dc1c9bfc5a8ff0fa770badbfd5b4772f7d6f7ac5dfff33bdf2ce7fe1ef1f661251bef81090bde3c143737b76b424bb37ce71537bcabd742f252e4129f619223d9306678b87c1165d58b44517a4041e556694eca59249405ef096cf4b33c8c7284c56140b557d49870f647a5639b2504506a8de32bf9c7c91048b1715a567b369df2145734821b558adb0aa26f49b95135784275ef42d63c6353fd4abe72cd1ccf0a8181e44ef15bab5972c5972feedb7dff9cd071f7ae486254b965eb063cb36ad634fbb48c4e3c24459523ed8fea770d45b8143315cde06085048904ea27ae756f1a2859fd6004508f26f2e3e540cfb0fa78d110de5fe3cdae223c7762a2dfa7afac4ceed3bc4faf5ebc49a55ab263df9e4539f79f0c187ae5fb674e985582d9e6ca85928b265c62a3bac31f3fe119af3ee5f46665dfd4dabe1949bbde898bdb655e960f15297c8118512ba151206094f8e27bac0bd5be890609fa70bd77fc10bafedb28364cf2c4996dcc75fec4be1c6f780f069c64026283f0fd27310ff72ab33082e37f2fdf87507c393725cc5bf3b1da7d40cf54d9410d02d88e3a48583293f8976e1746f116ee75ae1ee5e7e7272dd23df8eafb8fdbbf6ae572e109978c5a1f46ae1de870a4f8fd6ecb542953dba1125120f76eb131f1e60e5413f4a38ba14e9417db64b07f86200e1988dd23e0464c681f8a9516e1b31214a7e13800531420e1f2888a10400ba21d382e344a0e8192008877392adfbcfa184c7af2dd2c37221a805202a27db6ed5e43da271fe4da119eff8863ee992bf5ba5f5bb2810a22e6aa0a29344b66cd932e3befbeeffe7fbef5f78fd8a152bdedbd1de51d6d5d549e4aa5ff65a11a9e2fce3dca227c31efd86a81ead00058674ce83153c8002b59102f212e08841ae004b768260902ff0e06f318a13a213161d914b50c34ca2219a4e25445767bb686f6fc3a4f80beebbffbeef3eb8f0817fddbc69d3bc6432594276b350f5d5d3ad58bf39f6d4a7c373aefa4574ca453f326b67ae1565e3324ea842648c88b05d9d1ad49ec8f8b69fdf9a27df67d3be8dde3b0c2772547094d94e9041a0c733904f9427837c293c484e6d1c34d407b23410d790f88605c22bf1efc5448bee4d64cb2431f8ad397907e600cc039000a45ba69d3ff14769e3454c419fdd84d0329d424f75e899f6d557f4afbaf7fbc9d5f77e54746f9e49d786e9a23704ee7ac8d04aea5aad8a09afbb6645bf6b584c9038c1ccec8929524180e18345b2fe805972a622bf25b902635513d240aef03d2574330eca4e5c474714c9c2dc2d8983cdf87da108965a6894b380581ed2b90f47a0f31c1ea4907ecad398bb05164fcf6d95092d522dbcb2e684573d695568e2c5dfd466bee7dbfad8331e3322655d1cbcc8e1f75e95bff0c20b0bfefeb75b6f78e8a187bfbd6ad5aab3b66fdfaeb5b6eea0d65887489291e0653848b0453ee03b518a507317329497ca8b5b3594a705331b4582dce757bbbeeae501431539400e0ecf70e411aae112a038410d4a437a2e8286c636912b2f42f5db243f1522274dbf05912d32027839c8b36d6ea876b4b78aad9b368815cb979df0c4938baf7b60e1fd37befaea928bb0340eec30475e00609eb0513571a535e38adf8567bdfd465139e1653b3c26e99825c2a667b15d4b122a7a2a3929de1219da4fd3b3c1f6b38b675f4eb69fc8891cc253d54cfa58b86e358c3720c83bf2a5aa670bc0a5205be0078384e393223b5aa4e0373a6958284d3caa4409c2e2edfc393e87ca043d5afe1764c049b2439ae4f375f267e8350341544b4e190e0fc0c15e1b5857cb890b3dbe5b681d6b84bb77d5bcbe750b7f9078edd66fdbbb969e46a1a3787a8a6cbf904f7688d0cc48225c37f78568cdb4278c70655a33b0de06bec7878c035941af8614fc9385800b5108523db168983a8eace22dffa6eb7dc125cc6e21140a8935c8f870191e01940153c64c45a70a0b89e1fe1a3e47454985c5afb1922210dd238215124eb846888a26d7ab99d06d8c99714f6cdaa5d71a132ebcc52caddb459a085a5cd4a067d352a95464f7eedd131e7becf1f73ef2f0a22fbdbeeaf5abdbdbdbcbb0c85e3cde472d98343d3bde9cf15b2894f1a0584cb3b01618112d74178320a38583ee635976f21e01060379eeef062800c8361da1e5c81f28adc1b70b8b14542e3afc1cbf7d4ebe81bf40427e015fd4d1d051e09083076772e83cb110f4f2b3388e88f7c7456767a768dfd36eae5cb9f28ac7163dfa8dc54f2cfae08e9ddba7dab67d503d232304cf8856745ae3ce5e5832fdd21f849be63d62554cea16256385404702112ef4d671cf1d3dbb4ebe1eed6c59a51cf29b202ae42b7cc2227d290817bc3b408199dcd0095f40b4e40af2203f702f205b7ee8213555912a08fba201191eb91587ee40f1d1fd7cc1ef2c280e45e44898672011e84c70d3b44911494ed22326886c75092dd92ecc547bc8d9bbe6caf8ba07be96def2f405c2491d709ed661912ca4c6a89bb3ac64ecb9b7864a9a966b56896798f80c0e328f948e1228df2cc492fdc8744a3c09329c9513c128165908324600792f217764c6639f0a0759e393233eab4ae3b0801872af976952c02df959e83888964dcf8071689b2e71b1489b45791aab4d7b6563b7865b4effcfe8bcf75f6f8c3beb41233c3a7aafa8c2ebf178bc8c2af999f7dd77ff171e7f7cf1f5ab56adbea8adad4debe8d8c3040b63d320c906d524082a15fa206d902e8a432d5d81652ff8ed4a08112d10ed000102043856c1e497fd03ef93d0961c087a6b987011f11a9808efd279129e0b44ff307c984824446b6babd8b0618358b56ad5a98f3cfad80fefbdefbeebd7ad5b7332c58749f187e9978f1c7a28d66f8e3fe7a19253fff96bd684b37f659435b66b25b519cd2a235b1f25f36e3269c00b501831d50c6a80a317084d6f6c99ace037bc84f2b16a0b8f8a7fd8fac029ba441e91227dbef4ff4af607a465200c73024426a7b4b0c7d25dbf6300de8b82f8e5868d6a73a9637c1d1522732c17fc25c37ed0c1325444b84cf27da61d1746a25378f1dd22b5e7f5b7f6ae79f0abcedef5b3fd0886c56117a64684c26a3aed1f25634e79c08a8ded36ad6aa11383e7be1f62ed7800f85c1e05a48ce787a4637854e421e9216ff1984cc2a0a19c41740d74989f9f8b93f62922129e6cced927c31c3e409ee4bd6436cbbf0c3f5e7a042288f493136b904245841eae105ae91861548eefb56a263e1b9b7ad137f5a997ff8f5ed6b87134f45e015481c3bb76ed1afff4d34fbff3d147177d79e9b2e51fddb973c7d8f6bd6da2b7af5ba45209611373f748a150081816f44883b15619de2005e1b4e938e6ac61ad30741163abf661740270d60d82cc97a147470a4752378e5d90310d9433c011c3f53c8c04628f7d1a9cb49c224307d9e9196c0bd1f3a361f2b827b77c9c9c0a13ad6452f4f5f589b63d7bc4aeddbb4b5e7b7dd5fb1f7af8d1eb9e7efaa9b77574b437147af850af6859139971f9efa293dffc3dbd7af28b5af9b8b888d60961959331a3672507a9db98c263d3b3c157a0178bb6d8677f9a4bb464b5e3da97ebb7fd7d49bbd09b852d7c7e4e9883867f0fde92f3c6cd9879295e41fbc433c0407838136490f6998c916dd6c141f83af92c989e847e3bd97787f12b9ca7289d9410e9b87012fd42247a69b7739ed3d7d64ca7f68bc3265904572f6ddc1a1e77de03a563cfb85544ea928e16139e03c68e2e39bf8383871041ac40b4e81f6d7929373c30520d25a50d12823cc17020b63c7e8b004cb030a9508edf66e54880ccdf3fe01011020d0a64b53089e085cb845656675b152d9d56cb197f889df4de2f9b932ebad52ca96d4550beb0c8818abb7af5eab977df7befe71f7d6cd175af2e5b7ad9f6eddb236dedada22fde4de753f42092f743f045760c9bc36e70ef24f7e8611c9e840e62a90e6cd36450b8aa512e401da904fd3b06188c5c0b3392c8d77d028c14024258bcd0e191e19009926041c82b91c33374225424e8f1e10f17fb6b4ec190e237fb2eda62880c6fa9271229b1bb158b97ee12af2e5ff9d6fb173efaf3871f7eec733b76ec98467ea8601f9a2678e4df375b33affc6d6cdefbbf6ad6cfbd472f6bee31ad324fd72342e337d532b40529410fd67022f34822671f1d75ecdfa58fcf76aac0ef535e0e9c3b18a1db300122cf43f764c1dc2a224aae96a1db64c84f91ef2271e89c63c89135d5b305c0d70997ae23bfc6f3bfc99b59142e4cfe2f44c56591806419448eb12a2b967ae06d46a77dd3d18c1031affde3c8bca16e385af5f4d722132ebad9aa98bec80bd53aba594ac7f14d23ca00560f3fc329f10a728e4f16fcb07ea621bb9071f898a3047ac7e0e521d97de5c8a182839d3af6730b775f60fc180099c23df91a2e68797fcc3102b9f28c12a145ab845bd6e26a1593baccaa694bac496ffa8131edca9fea357396e08be71cd128417f7f7fc54b2fbffce6575f5d7ef596addba6e0a3ce7bf7eea51655af48a5923c6f804105276d3cfd66324c8a494a0ae54456718f38e6182004949c7690a3b842da84dcf238be011d734997e5bc0c56b691070f67f8fb018682cd4d8000870bb88de12a32de4ce3ce03fc405527f28539aa205598348e452ff93701a1f0665e2aed905d4e082c91b373db76b17ddbb6fa175e79e5cb8f3cf2e8d75f7ef9e50bf7ee6d6bc6dc59be28fff0b0fc903166ce0bd1a997ff87d57cea6fbc8af15b44f9a4b8281d43eca39a1ead443a00199c7d033f9d6f7f543ef14f84f385ddcbc075124c98d4854330f830aecbadc6f80d822405848bd71ca608d5bc307c70da25912f6ec15b29415ae47c6fb92586812def6309566c657ab1f036de1ec562ad1aa60c95d4a7add2da154669c376ba7cbf3862a3af5991843ee6a4174213ceffa35e367ea388d5c90f4f22699893858471e228a1f8da07e7aedc48d9d71f80e1f39b7f837c12f621b2170c99830c1cc87cecf892b34b37c03da5c8c5cb709cb212e1e8ce52e921749cb7c8546a7984f0ddc14a212a9bd356ed8c8d9129e7ffa739ff5ffec99af5ee9f5b95cd9b9172be681461e7ce9de3d76fd8706aeb9ed6c6ceae4e2c3a2a327692c79e07562fa6e79779051245ccdf95e7f0b0baa1f107b0f91342a4843cef80b6c831981d7ea3144dba0083801e406817e523b510461e542b60defd5f014623d08351c05e8c000705f821aa6d3cf2e10adbc16296b099b29f84bff58750548a686461985036b6e83726c4d36ff48a60e4c6ce806cc5c5eeddad62c7b61dfa4b2f2ff9d0edb7df79d3adb7defec315cb965d60dba9c22df5a01b8ede38fbd5d0dcf7fe3076d2073f69359f76a75e3bb34bab9ee07ab16ae11851b238e8a9935687a78dc0b7627fc0b70e01f9972c07f08f911f91fe987c0877a46445e3454db162013a63b02f7ff3f574050b5d8bcb79d896b7140d05400701eea7e680a34c8682cb9005440cb69ad2e750482a1f7cd9049d44f07f06f9bf70b85c444a1b44ac76c2cb15d32ff8a956397eb51fcdb0382a1e914855526f3ce9b950c389b7695679afb0ca883512e343e651e2d09d073608866892187442a70781d033d396324c66137e0d93092043d84299654648c68c5f1272255719877c1310e754c66605f1f024451428fd901316b18feba9d028dd9a5922f47095d04b1ae266f9f817a2932ffa8e3eed8aff09d74d5ef5465d83c58c8c9db1e2f17815b58c44922493c182a2a8f4c853ca17e40d844353beb0b2e11cf2124459768d732f17149014114a6ff82207898f5f209f72b70a32ff50795941471e5068ae73b246142d86e4d348833437bf373c02909137d85904283a900d24af4bf68f8b072a4536947499fe72a393f786141dfb2316f91b5b49b87ca74e8e3c95488adeee1eb16be72eb171c306b171e3c6ca575f5dfabe458b17dff8d24baf5cdcd7d75d4de18f8acf3e6490d1c7db877af3998bac13defb1dabe5acffd4cac76ff24a9b32c2aa204e1421dfe10f91fab399e46ff42bc1fec99c9250bfe0e995e01632d3f04b2e529e15b50038ac9a7a3f70a864efc031d035d9bba25424d436172a4bb1f547cac89fe163d92827940fa262e268103f88960aa3a4341d1e3befcfe6d8d31e7da3112d15fb11438bd6b49a1317dc2dea67df6f47aa1ccf2a21861be6c4f1878449890c72cc26912e88c1c7a838f0ba2b582a3b68ca2a7a38901f64a65c3f8b32c8a02cc2b82ae936b517e4182b8a4ee62c132cc9a2a59397ca2c951e59aae9749e277053186a36a002a0e0710fc9c029e3047adf24c132229489e513facdc6b9f74466bde35bc684f3ee3663356d1cf928464b4bcbc671cdcdab2a2bcbed9258093db75f2958e4143fe4097a1131911df98b9e2995cf9ca7d44a43cbcba4305c6aa4cf105eec8d0a0b9308a52b3b6aaa5554e00a4779a3441d830c853aa6ce53e3203f9942f7e1fb511d03505e7473decf2d97423a71f460cb2499486841940556c6a17fc506940b95dfb159818e11c8463d552bf624b2a7847b67a84a71ef09781805e17d0ac7f3e1a922e23c1aa9bcd825c5c11f34263d444c107420e0936589449c3f348db710b76cd972da638f3dfadd279f7cea7ddddddd75859c14cf5384ca9bd7ebd3dffedfd68cb75deb564f7bd92d1b97b643b522433ed41118d9c4a2ac9264c1a72023403ed9dc509e70231d73a37439670a8235aa30a407ff8c6156f643ec8f0cba967c1071045e32832221da40f924f30d51223ab9f200c8adb479dce9478e9ea7037155a238d806a33ce8debc2fc3e2ba019b4ee41069c73a679e07318543e689d343f1b8c40fbc68b510250dcb8cfab94fea56e40dbfe62235e528005ff9d6aaa7ad34c79d7dbb1baddee486ca4893f02d4c3c283203e39b727219f25173e18ce5c3678d3db28d1e1ef9832d1ff3ffaa0ca21c855a230c90eb28e41e1b286e21c89faae0e81aba1dba70e5b5f48332136f80209d9e19a14da9d04bead294819d46fdf4bf47665efe53bdf194a7f550492fae18ed282d29ed9a3973e6d38d0d0d6bcb4b4b5d35f4a763b226112d3078e41f460d917d4c7c49e1074079479ac6ce1bf98e3605ceb2a04cfdedb108a57f4adf72f56e40377dc945ee6f17564266d3c8821c34ead781909bfe4260205fb82f3f0030b44c288f0a5b48018607a92e9663605b37a49e3191920e6eb0b02347d86c7894b73c861f741e3e8abc1b861593c984e8e9e9117bf6ec115bb76ec5720fb35f7ae9c52f3ffbcc3fdebb7be7cea989442256b05e2d7a08235ab5d718bfe09ec8e44b7ea0554efd875bd2dce359d5c44b2a846b9490c4e8992c0a4afec45763f86de483cc8fe141b9c75be48d1a2ae41e2c3a2cdf5ee7d30c696f156182e0bc1476f24a284eb54e16e2cf29017f4bd9a88818fc200822ee49dc00df2706c1c36afeae4104122fc085abfab5f2f18bf537988ba570540b49b7a271a3f1d467cc716ffa931da9efb1cd72e15082b10a2b69103b74387383120e81ff86a356af6ee6e49f84f2dc281816fa49198d3716656822509cb9d8a5809451702e981604912d0749dc20182bb7315e8ecc3640acc24caebc488df0aaa6bafa9879dd1aa5df98f6b6ef6a33dffd7dbd76d62b441e89661f1b2052659f72ca294f5c78c1f93f6f6868682f89c5846585799e95618070816481f15356d21f540ad06349c490a14a29a9c2902100d9422f166c0aab288a0b22831cd340fe1c2cb24600b57584411686b45bda79ff5051828d1aeaacfc1920c0a801b915d58897c458fa27499894af826f42fb41fd9690c44a4af678f61a88bc0ef642122e90adddbb778bcd9bb78c7be4d1477e74e7dd77fee885e79f7b575b5bdb380a07265310688695325bce7e2834fb435f894e5cf06baf6676bb5d31d5b6a30dc20991ef3763444cc2020b56cbe939f299e4f3a967c4334b23802155de925d4078951fb2e78ff802c523b7f047b9f9887c957e2b4bacd823b1643b7330ad85c2fabf698f047797a3379c468a8f872a4110f9ad508b9e13f3b3cb8557d22044f9f88c5637eb6fe6f8736e125624ce11bc0170a7a30a2d5abd2734e1bc7bccfad9773b565906df3ff2fb4a29bb8821d23eaff04a4c11f3793809be72ca4ca33d0c0b2237fcac962c940a84339d7ee7642eae43414134141ec7952d00e6ade87de1b9472e912c8a858e63f154839440844b85535a9736cbc76c34c69dfe5fd6dcabbf6ace7ce76f4255e336523cf286c710aaaaaa5a179cbbe08e19d3a7de5b515a96298dc644381c6592257bb3a0ac946b3c1e0de1dc64051fc87280f29109150988321fe24d6ea0000ce839e72bd7fe91052c1286df6803b391ad274506bf6a799a4d49c41b31230fca9851a39c94293064018a10f840b4bfcb60bbe943d5b783ab77fb77bf9290684cb2e2f1b8d8bbb71373b4c49a356b424b972ebde2f1c717fd74d1a38f7c69c386f573fbfbfb4b0bd5ab854e8848e3ec25e6f477feac74ca85df33cbc7ae7063f57d4ea8ca13564c68e467f9cb20941fdcdde11329ce1d7e46dee00c1f47c7a02263a8b1c846085ec44295901d271c94f3589ea72de5158427c6e7720b5f40ac54674e36a3d439be0118883c8c6354a618eed479948bfc63b88abf516cc46a575913cffbb55e3d6525053c28bb95bddfd182a6b95ad594d5a1c917fe55af1abf4c9435ba76b84cd8482c481635e659e8d618e7cc85ec3bc9d0510c2b62789142c159f8cf8e3940dc53458fa6a334306c85df542806150256a0059f83ca2366cfa62036c52a976a67a2100a47841121866d950a375623b4ca7171b376eaf3a16917dfa04d7bdbaff5da59afe2b555bee1b109afbcbcbce38c33cffcfbcc5933968e1f3f3e5d59564e442b2c4c03392c9514d500444b36d8a824fc2da02a0a4f2c0419e656058e21903c7790fa17e068039699df6294064d9555b10255bbb8531820c0609053cfab7143e700c8563a9dc6323ca2adad0d4388d54b5e7de55f1f5cf8c0f79f7de61fefebe8686f2ae0f0a1b04aeb769b932fb9a974f6dbae8db69cf227a3a2798f286bb2dd488dd04df2b9267aa1c8bb40e065e08f89c41824b2d3857c082282cd82ef80f3f632e477d2bcc54a8c3a163ac55c16ca7e6eb72238f92ae61268c4621f248ba7bd20408e3040b0c8c7d16f9030f4f60f05ec25e737b6149a3fa167960a27d620bcb2962e6dccbc3f88ca09ab88ed1db40ea8bb1f55f007271b4e7c393cf9fcff734bc76ecb84aa445a8b52a2c3a42c263d0409f9019ec10f62c5ce40f646c9b71141b290a9729fbb0b510444a878788a891609299e4e998e05c8349dc2d349342a8838f3a442eebd22828539882837438b08d32c1346a842b8e12ac72dadefd6eb67df1d9d71f9b7f48917dc6d92a25004087d4c034662ceecd9cfbde5928b7e3861fcf825d5d5d5367ab42c034a6f901a12832745e5d6814fb498d9fb2d012a24fa876ca2b2631dc64b09d0392a430ac26bc5708d095008503da252912dc1d100b441fddd11070f7b07087004207fb58f753bb89eab83836a1c2951b06d9b97ddc13cad8d1bd78b0d1bd66b2b562cbfe4e9a79ff8fe934f3cf1f1d65dbb26614dade1d2970f18d1ca0e73ec198f86665df583c8e40b6ff0ca9a57b8a563fab01492c7bd5a219003846462c493cc699f3fda8cdfe89102b50117806f11190a8fcfb811d962910b9faa21478c9c3043a0ebd5db88884ff64cc9fb48c16f902f49aee0e1b86f00ff38abe4b552a4d59429200662940807f3cca2b529bd6ee6ff19e317dc41a4f1903a6146cce268a1d22e6bc2f9f75b13cef9bd17ab4c3a912a913462e4b02d7e734daea5410f4499eef2e42c52289e48828746d72065f83e5413e70844b060964d8ccdaa1e143ae6d8292256f8a0b17caf9087b80c8be75d09bc1510a2c28ed40ab77c6252af99b63a32ee9c9f84a7befd875ae3c9ff38c67baff641241289cf9f3fffd1b3cf3efd1793268e7bad714c835342440b63de183ac4cac56869a0ddc10a8b9607fde372e1316b94196da1415406acf828072e553e14a040a09291e3bca3057eb50e1060348088fa8836c4f747d8e4f021510d225b894452747676895dbb7689eddb77d63cf7fcf3d7def7c07d372e5fbef4e29e9e9e7a0a8eb7baf20fcd70ccb2313bcc4917fe2d36ef8aaf849b4efeb35639a9552b1b9f71a26384437ed81196b0a91d88b70415408fd8fdd33e04dd2f06391139de05ba031f9f2558b253066e5f4682a13e08f208570d362aeab73c06df84fb490602c80e0288e40e78d3d1a4b446855352239cca16d72b6f7ad29a7ae1ffe825b50735d93d17b8fb48c1d34bea7786275e78aba898fa8463563a58b4cc26c72dd7d000c1a2c7f4f302ac546ee94149b86704990e85f3491532863bbbfcbc318844a1658a7360fce8527532369505c541d500e3a98605890aacdde585aa3d3b3ab65f544c5e1c9d75d597ccd9effea95e3fed35f4bcc9188f2f949494f49e76da690bdff4a6737fd2d8346633fd169669f913e12d26a9dceaa0fce5f5b2e81708969c7808c28558fcf2a2b3f2dfe8e9413936e10d2c6299db0a2e6ae43199180a0810e048a0ea976c70fa8eeb2843c53d5cfc72f8d0e50f4defd9d3ce6f1f6edcb8d17cf9e5973ff0d8638ffee2f1458f7d7ae7ceed1333994cc126c51be1d21eb3e18cc7ad791fbe3e3cf31d5f11d5539679254d49c72a17b61e15192259e452247d42639d1e53fa786c8958d10f9020254caad8cb60288ffcbc2f806fed783f0bbacecf3b9ecb45fb982caf806bf00b73bd0751323a81ee020773c9c231e1c56a3dafb471b339e1ac5fe965633720840c78f018499285a773b5b2b15b22e3cfbd592f6bdaaa45aa856b8684878f1a920307c9528f9755262449a74cf51f16cc920e21533863d8b91b1c9e9d089c3fb6605ffcfc540c140ebd63b6416cd48809275a2fdcf2096951d1dc6a35ccbb393aeb1d3fd01b4f79520f95f45144879c69c712cacacaba4e3df5d44766ce9cf1f7ca8aaa4c79798508133195bd58fe647894073275484e41f1996471cb02a5257fef133040bec1956938035d74002f0f10601481ea555eb516f5385750bdd1d845af562a95163ddd3da2bdbd9ddf40dcbe7dc7b857972efbcae38b1efff2fa75eb4ecf64e231194b21e02f603a7ec1dde1e9977e5bd44e7fd02d9fd8ed96b7b822562be73ab17f811ba7e7a2c63a0f1792f8cdfb0161d2856c47181dfbc817f87a8c7a5161f8044ae60fef495ec0052565b806166672f19424740e602812f73175a187cbc8393608ada47a6f64dcc93fd1c79cf81427f03030b2248ba0999184d57cc6e3919653ffe486cae32254223c265a94193c619a328314869db9efd0399b29d355963bb4cf248bb384c232f35561e93865263800c745e4cd352c16c78a8a9455219c92a67ebd66da739169977f3334f783df36ea4f7806abd4237d0184a8a8a8d873f2c927dd57df50ff7a4d4db51b8bc91e2dcccf929f33901501440b63de505935942b3fc743a5c013df021409c81aa16a2b8313204080a305f233dcaf2f9d78fe00ff06e1a50cb0e40ed967d86449b632a2bbbb576cddba5d6cd9b225b67ce5ca4f2c7c68e1af962f7bed3cba541af002c10895f46a2de73e143ae9bd375813cffba95635698b51d194d6a29564a62c61636413dd5a2cf0f8f033f0fed2cfc8d929d9bc667e4027d9e7d379b50f8ec04d7dfe8df0204e98d3c5dd354ca00084432c10f91d638a91b6e067f8bea1669ac22ba9165a65633cd474d22fcc496ffeab1129efc2558783112759805652bfcb9af8a6bbcc9a498fb9b11ac70d45e991b1c0982449b9905f5d236205a64eb9a1c66d39eb7d0593c9061183aa5338ba86c918912f8ca37a16d6b4a8174ed9b8b4573ebedd6c98774b78c65bbfaf4fb8e8368c17538a0e8b911ec3f0264d9af4da19a7cfffdd9886866d7575b55e345ac273b328af64002a0b26b1be61c1df816142eeb6458b22205a45025968010601ce11f369a0c701021c2e4887ec7c122ce8ebfe7416c7d1c8b5fd49f158530b6f1f6edfbe5d6cdeb275de9257976295f82a4a6f41951e8b95eb95d3561a33defe1b6bf205dff36aa73de754b6f4ba2563848d2144cda2e7a024f29b5472fd457e6e4c58f79f5f4916b20c728fc127492002ec0f16f5821600e2060692fb4d651dc38456a91025b569b372fc9dd6c4736f35c2a5dd7cf23091179285a7d02a27af8e4c38fb6651d6b8d50b57f1c43270551e0e242581b0e2329ba56b381f90e1d89519208915f153265f1083173be5795e7a48b86644b8212a302a38af7c62c2ac9dfb726cc63b6eb066bdf7dfcca63316e9479859c732a2d168ff05175e70f39bde74d67fd4d6d67655575763723c9789b227d8cadf20b7b2bc986091e2ca2e5c540204ce2a7280824055980043403a3a5a5a02411916290cc370d8578d2014a1c825100ab837e61fc367e23ce6c7621fc740b6b0d443474707cfd5dab479f39bd7af5f3bdf759d82cdcfca42f3ccd2da5673eaa57f0d9dfaf1cf1acd67fcaf5b35696fa6b4c5c95865c23643b2a344e0533a06b9103f0fb8575ee609837c0d7aa5401b510a20562c140c6124d152e5a37c51d627492e47e494f21151625086ef6398c20b11c18ad608a3b4f9e5c88cb7fd402f1fb7962f3a02e487641134c34a9b8da73c151e7bd24df42071cf8cd1831239a29ce2fe109f5c211b39f3b043c05b88c83dd9bb253b11fd0e3e225578038032c6207245f1d9e15ae19634d844e4f61ab533ee8b4cbee807e694b7fc3554d5b2116f3dc81803ec0f1515151de79e7bf66db3e7ccfa537d7d4d6f5565a58884c2c21a98048f561395130c8c3f8408b03a2b6d1d40de542bc03ef0ad52804120430a05251f95aba70102141706c8c47e00fd559d124c0efcf0f8020748167ab4babbbbc5debd7b45775757fde6cd9b4f4a2412251ca808a099a1945e39698531ededbf084f3effbb6645c3eb5a5953dc89d611c929e32fb160b5757e7310bc88b6fc85182205988b865acccb34f029aed2241c70a0a74a112f006128a7e83746c964ddc77c620c430298ef85550e34e2103a112cada4a1df6a3cf98f7af9c4d5742719c91120afc6582ba96d0d4dbce016ab76caa34ea4da73cd527a5822500e6501f788c80c508a835fd82ae6e931cb25924507b04818afbb6185851b2a114e8c0856c5c4b4563d7d4d64e2453f0ccffbf0b7f4e63316e12d078e34c041a1aaaaaaede28bdffc9b93e79ff8bf4d8d4df1baba3abc85284c031ffd34a0ef3c0f400e1112fc320b504480d10d8a657804f43340918349c10104187821c9271bec27a9019c4ca67885f8542ac53d5b38e3385e84c857619674d83f3cabb279b339fdedbf8b9df0fe2f99e34ebf4bab99dc23ca9b3c87880ebf20875508a4f317c2f17bb4b0c531051024ff052c69f47c19205bb44f9761e4c5a5700e6fe91a5cc76129143ea163c5841bad175ef9f8b45137e70e73ec190fd085474cb080fc9a1cbc6d58317ebdd174cadd7ab4ba5d5878bb00bd9870de1e3155f9d08a99e76ed19385b33cf70add89983047d7db56b560065c3636ae574d7d263ce9821fe9932ffdb35e316e3526dd7304a300d432d15111b0f50f150494d75e4b4bf3ba8b2ebaf03753a64ebcbf714c7db2b2b2528448e9f121e9dc8a3e408a29c54a02ec1fd40a3b2a95f6a09063870264412de0fc9541800079010caf245c205a98044fbe847bbb804c3a5d99c9d861fe5164d0ad58bfd17cfae3a159effa81d972e6afdcb2961d6e6c6cc6b62ae86498c810f91c2256586200cfc79fcd6141431f648948932f60072cfe3e26bf838fa9c9f06ae2bb3c2ff779444cc73a9aa5c28bd60aad64ecaba1296ffd991eabddcd018e02f2ee16d155688e39e159abf1a45bdd586dd2b1f0b6a1450e08cb3210ddd279c94b265672e5569fad532ef157b129e31d232c6ca34424cd32e1c49a1db77c5297553fefaed2e997fd9b397ec1bd66aca68d6ee5e768718314050f1916893d0d5ac7dad9a27f570b1d8cd1f1825116ca7bb7a9a969e382f317fcf794e9531735d4d5a7cb4a4a44d832b96c380cfe50d249edf937132fe86e80fd42d7f348b2020c8b42d6ab000100d57970203081d88f00ec1f7d51d0748dd738c427d20c2c938463743e954ed7dab65d0473b2f6034d77f4eac9abf4a96fff6f6bea65df16d59396d965e3d2e9488d48938f4feb51124b38547579757774cab02792e4923bab50ad893c61223b1c11bc2ac0de553a2b291406c3865824024384e82dc387ac33a13ae1846b9366c3497fd0aaa6be46693a6ab6ba2006472b6fde129af0a65bccca714f68d16adb0997cb4967ba21f0391ce80d324e291432d4c5047711a66d88c2c50416357342f57111a95b1f6b3ae917d16957fc87de38ff2923343a26b7d3b369d4c2086989ce5a67e7925333abeffd7072f94d9f4fbe76dba7ecad4f2ff092857d2304933be7cc99fddcf9179ef79f631aea979497973998088fde2c26c4a4a4ace0282c4a267a6cd53c81e301b9c62dc0284240b1021c051c6efd57d71de8fa03d950750ed70f159d7c2748965c4c5a8e0e018ee3d4673299a2ecc91a00911aabbc71bb39f92d3747a75df62d5131fe3937d6d06387aa3d8727c547f9fbc72e966862df234577b0c4906cfac32b715ed063639151facbbe49b22c25043a253905be4b88ef1897092f5c9bd263f58bcc7167dd878f5ecb8047078521594628a5d7cf79253ce9fc3f98d593d61825b5ae6e61ce8f266cc7111e84c759292c39741ea6d223c2c38af1e12a7e7b502f6fe9296b3ef9f6f2596fbd3634ed6dbf316aa6ae38da993352f05c57d7523dd57afbf2d3d3afdff5d1d4d29baf7336fde353f6ce251fc86c7ff1a37dabeefb7fcece17ced39c54d4bfa420b02c2b0da275da99a7fd766ccbd88db558da2116e50aacbaa2b16619de6e4185e657708f512843a60c57ae2154e714702ef77c3161685a0304087068a0ba4d55c877e843ead21bd57b75fe40e10e543f0f748e62e578958074c136132ac85ecbaead22876645e37af3998b4ae7fff3d72353ceff1fad6a52ab533e2e8337fe306f0a2fcbf12476a62e1022949e45040beb86c951b05c0cdb4d41798835c6348a4f84ab85281de798b5539e8ecebde6462d5ab7cb0f75d450b0769d4e9969369efa6464dc197f346275ed589bc2a507c7d7af1d72e010ca0dca245368589a013d575685e744eb935ea4614f64ec09bf8fceb9eadfcc090bee33cbc6ec147af1bf3d488aafd976c6d2921df5994d8bae482ebfe5eb9955f75fab6d7be12d5afbda71d1fe36d3ead95197d9bde2d2e4a6273eec746c98455785fccb0b8248249238f3ac33179e7ee619bfa9adaedd1bc3a7772cd9f30c95e66f1c32c9a2161397197ab7b2f539b7d22b19cd80913b986719fe1c1f2b700648725c4c18dd1a31623890370d50400c376f76a83d3874db30188ac00d2740360e453608744e8e2638dc9b433b2a7c6cb4902c000b85eb75b35e30e67ee047e1996fbdc1ad9abc2453d6d46787cb3d1ec132c2c22562e5b9160f13a213060b67e32b25f0459c1bf4ec78d95dbe91885e3d39d2a291e0bc4171786695d0a24dc28b356e0acd7cfb757acdd42574f7372e9c43c43eca924f6891ca766bdc39f7e9f527de918ed63ae8a5f2cc12ca8190d074393488ef1cd94685702bc7db46ddacb692e6931656cf79f3b762d32eff955edeb261b47c77902a85ae65e2657ae7ea13d26beffe277bc383ffe2b62dbdcc48ec2e33529dc2caf409d3c98850ba4fc4ec3e213a365f985effd0079dbdeba6d3e505ad2078e3f09c73cebe73f6dc59b736d48f49d754d7082c560ae5266d27e52545a27d93e7d60df60d6ff4fb58c3c118d0914731a4e1e0413a91bf04171fc7dc1f4657211e47c0540a7fb768906b5787da20fa5dc04feb1c363c235ab5d79c7cf12d6527bdfb5a6bec697f746b66ecf0aa26a6b4d2266afdd7d033c7a89244896c911fb20d611313b06d396505f3b941aa1cff379620c21c2e265bc42bb0a6a628ad156e79537768dc693fd7aaa72fc53de5ad8f2e0a4ab2d0fb444469bdd57ceadd76a46eb313ae7544b89c88564c6821ca3cb394c7621dab2a65948f5d533a65c1774a4fbce61ba11957fc9f5ed5b20113e6fc988a1a8e639b5aa2a32eb3e589b72697ffe5facc9adbbee5b52f3fc5e8db26f4e41e61d809613849528cb430dd8488a47b8595e888a577bcf4a1ccfa47dee3f6efa92345296459790d8d0d5bcf39f79c9b5ac6b5bc54575b972a2bab109645ca4a4e4b922c838711fdeee94180015072ac23fb8cc887023d2ffad303ec0f544441f60438320c2532f9866a96f03763f96db95c0cb6c1a4efc53d1feb00d043f8d0f4fc27c2b3dffdc3d8d4b7dc68564d7946ab18d72d62c415b032bb81811e4bd8c404321922551987dafd72b441fa269937ea537c186df1e81acfaa1422d690098d99f33b63d29b6fc23a9e1c70045048c72d4144cba89bb92c32ee9cffd3ca5ab6d8b126db89350aa7b4c515d553925af5943d46e3dc272313cefe776ddc39b7ebe5cd1b30a78b34a7b05a7e10a082d644261d13dd1ba7a7d7dcfbe1c4fa07be62b72e7b7ba6bf23eca47a85e6a05c5149e01725e3c67c34a2e3c248740a33de5199deb9ec3df696a7dfaa25f6d6727c0502912777ead4a94b2fbef8a29f4c9e32f91f63c736a56a6aaa442814628f3ed471058e2c40b101f58764b40c9b041528c07ea1ecebbe76166e71b06b1c453abf1f68ae55d6b0c39870c11d9139577f27d472d6cf8deac9abbdaac909af84b88255c69fd3d3b16e263e05a791601a0b35faf11200f2c3269fca3023428bd50aad7aa2ad978e79c99870ce9f8c4879a73c3932283cc9226891aa3de16997fe3532feec5fbbb1a6f55e49538fa89cb0c3ac9ffd68c9ccb7de183bf5639f37275e709b5982a51946c7673160d0313c686f7bfac2d4b29bae4bad5bf84da77df5494e5fab7013ed427792a40444aa74aa2406d6eac067828868f1c4ff8c706de291f12ea1f5b54e8aaf5bf4a5e48645576999fe323ffa8200f3b3ce3afbac8597bce5921f4c9a34f1b98686864c494999300d8b870d21f4dc7e682a293200432517089b1bfed847f1370c8e65a04e8ea2b92981ae041816b0a3e42964e3dc37a9d28ee6ca201874cdd0eeae5107235cd66d8c99f79439fbddff1199f3ceaf980db3177a65cdbd5eb8c213e152e15a254ca21cdd902b1510c1b24254ddc9af8264e1cd443d5c22b4b2b1c2a81cbfd29c78debf69951356f9d18f188a8264115cbda4617b68fc39f744c69dfa5babf9945f85272cf85178e6a53f3526bef9ef7a45cb6a4c94f7c3163dc890eb5aaaa336b3f9f1b7a656dff17977e70befd5e3db4bcd44ab30335dc2f26caa148eb0a92e40f3b1d69aac30f27be158fddec8a48469c785196f175af7e699894dcf7cdcdef9ca39c24917fa8dc3d49c39739e3be79c737ed9d8d8b8bcaaaaca8bc54ab8c57030a46928d13a9690fb6cfe175ce40f1f9437a86fc76e06143f82bc0f708c009e63a8ecdff6eafae8985a7310f0f01517bde9d4c5e16917ffd4689af707bbbc79af5ddaecb8d16a9131314fcb1019ca0e1051be00738769dfc5d4230aa347cb537ac3493fd7c69cf8143e5ccd814610c542b2089aab978fdd189a72c9dfc233dff1dfa16997fdd1a04c302215e8ca3bb0e72e228060e9893d6332eb1f7cb7bdf69e2f6a7b965fa0c7db8495d82b226e52842c5d584c4888643919d97b45a61f5504c03e132d3a621061d1eda430e85ad1bbe3a4c49a073f63ef5e760a0583d6140cd168347eeaa9a73e72caa9a7feaea6a6a6131f93c602788a64601cfc6008d7f180c1a432c88f42e35868d107087088700dc3187132914f684628a98f99fb7c78e6db7e1e9978e6cfb5caf19bec928674c6aa1271ad44c45d53c46d43a46c9dbf0ea347ca8588d5e04d42e1948e7d516f3ce5413d14ebf3a31b511411c92268866395d6eeb6ca1bb6eb56b49f0e8c1af60d7225bcfe12d1b76d6266c343d724373efc79a76bf3295a823862aa47083b213cdbe1af21c9371d306a81252a34eec9c24bc1f8183677ff6af87490cbff34374d442bce442bb367e35b926b1efc84dbb3bd99084caef7ce3b4a4a4a7a4f993fffe159b366dc5153531b2f2f2f67a23574e2fb50a2a57e1fcb044c11abc1048b9fb9b8eadbf18951a378a43f012b0f70c420bbe31c6b248b8195e2cbc76eb2a65efcd7f0f853fecba818ff9c573ab6db8d36386ea84ed87a4ca43553384689f022b5422382254a1b7a8cda39b768b19a563f96114760f48f02ec4cc6127d3bc765d63d7665f2d5fffd56ffba07bee1746f9fe211b932c8cf128f169e6b081773daf935538ff40393f242bc281a0807c896c02abd864e5c93c2d2850efef9438b9a9310bad3a765f6acbbd2def2ec655abab7824e1494688d193366ebf9e79fff7fb3664dbfb7a6a6c6c687a4d51a5ab9e0e7f3e558c5504225abd61092e56f03141405ad330102e40fb041ece233c7d070e160689aab97366e32275f7c53e9bc6bae2d997cfecfac3133d619d5135322562f326695b0c3b5c28d350927d6d01baa3ff1d756d3e9f79103ce5b8f7640b28e00441a749189c7b4ced527a456ddf9c9fe3577dd98defad407bcee4df55aef7661a4ba04d126cc3a643e643b9e701c97c816d6abc527840c76ce03e443a7e3606506744710c172e81c112ceed14a092dd529f4c49e92f496e73f6a6f7de6126117764578ac173363c68c25679e79e61febeaead65456567ad1a85c11fe4038d6c8d6be04abc02095f2f7020c01d7d900018e10c555e70fcc175cd7b561abfd9fc7223c235cde658c99fd4264e615ff5332f94d3fb1eaa63de1968debb5634d69a7ac256e978ded0ad5ceb82932f1a2dfeaa563b6fbd7e50581c1394cc0586bc98e1a7bdbb317a556def2657beb539f35fb774d09dbfdc272fa85e16584ae616e15e936564427e201f2a12a27e62d39789390ceeb7801020384aecdc40abf50321879c3d7c59968b98e301d4c86ef177acfb69333eb17fdabb3e7b5f93c4c594098a699993b77eef3a79d76daef1b1a1adaabaaaab837cbc4abb43e40aa720579902b018e32c0e1030408700c03c44ac9814136f6d8edc9ca85a6397ab47ab739ee4d77974fbbf487252da7fd2e84795b634eb8a7b4f9b47f8f4d38ef777a59e3160a97b75e2c2030c68701d7710c2dd15e97deb4f8f2c4ea7bbf6cef7ae51ad1bf2366f4ed1646a69fc8509a4896230c224e4c2ce81f3e7ccd0b766a243cbb9d8e12d1c2ac0b9c1314de75e4d0a0a7c9de2b784bf482a122699e2d74122c5cea255a85bb67f5b9f6e627dfafc77737233d9cb002a1a4a4a467c18205779c78f249bf2dafa8e8c7b0612854bc1f7d2f1864ffd2c8f7328dbe7eacbca598ea63c0ea038c72488e00df3218f83df81c1ab1b4ef1c17244bc233a355ed66e3494f97ce7cdbafaba65dfcc3ca1997fe3834e5d2dfea35d396615d4e3f5cde1090ac43041be9be1de3e2abeffd4062ed439f75f7ae3b474f740b3dd12b74ac6de5a0d789149c82b90ed6e7c8f05b841e1124302a5dd7846518c234746112b932e9187f571c3d3c7e8f9550f3b0d013866b9868519c38e7668466f70b4192697bfd9af4da073e22fa778d2b708f96575f5fbfedc2f32ff8cbbc79736f1f3f7e7cb2aaba9a9775087aab0a80d1472402050910e0300032e5792056b2519e0b6577697bec4d7a7f23e06b32654d1bccb1a72e32aaa6ac00f1c24479ff6c5e1190ac4384e6a643f1350f5cd3bd7ae197327bd79de8f5ee125ea253e819bc3d0872448a8da2247d771cac349ba16d46b8449c34cd1106912b299a140a08d1a9a268102252589014840beb944a015141948e70886409a75f68994ee1c67755c6b7fee333e90d8f5c23921df8f48eac558581dbdcdcbcfebc05e7fd7ec2c409ffa8acac74316ca8e6670d47b414015302a816d8b10822d8b299192007f95559d2af5161f3a83e1cbb1521c01103969ead3d1cc41ba88adf8b753cea93cb9fcbd10a3b1f2d205987082fd55b95e9da705ac8e96b0cb9711172e5d0a0491aafc361b87268105ffe0671c09206e00fb09920108e6b0b9b04fb4ca8d01271f065704d98f81400930dd407d9b385168a4661f04571b0374d50a384aed7e8de44ac84d3b7bb36be6bc907d25b9fb9544b177645784cae9c3265cab2d34e3ded4fd5d5d53bb1ac0326c2230f14149952db630dd9e79365c8a491e7d515c2c6a97be672bb22a9f28aeb683a35c4f3d70b4be51310dd00478a021b2f5417f20b646330679776fc63436d2b549d6d8f47c70a618002108ac4e28e1eb889ce6a2bde3a2decc585c9042b2d42aa878ad458652888057a719440f13104885e2ddbb679d23b26bfab09f01a11330c23a2770b9f0160f2e50bc74775043d5d4cb0281e8fb622dd2d44bc5588deadb3fbb7fce3a3f69e15a7d0c5f86266c1505a5ada3d7ffefcc7886cdd53535313c7fc2c0c1b0e47b480dcfdd10ef58c439f4995a13282238e7d6e33a095feb608c05c87f3a5a00a702ce95f80fc80ec7541e7c0021a35523070a1b9d05fd4eb81fa14a0c8504456779420d35feaa6fa1a3447921d65a2f125747c1647a979d6b10e068c7aaec816bd8a05bf0d2254d9b710197e4f08da22dc5b467fd1b3859e307c03d14b10d9eada784e72fd639f7676af3889d8574189566565e59ef3ce3bef2f279c70c29d63c78eedafadadc5e478265b80229780ca276c73f78b1d83cac7877aa6a1c0f3c86766e3bcef85471332f6ac222af0d842f180f30f498297c863d254b9297d2b565da37415578105c8057a86fcdd2c863b3692507d5343757898df414f56011190ac43846727639e9d29e349e8e88ae5a354b930d91dbe8d081177e1e2171b71f460e1971c42045465747958917c8c6ed0e5f203d17c25fd261a45e764f178e48730b991c392c8a521b09c962d4cba229cee16a144bbc8b4adbb2ab9ea9e2fba7bd6ce25a75eb0b2c5b0e18c19335eb9f2ca2b7f3c67ce9c3b1a1a1a9244bc0626c28370c8bc91b997bbafa08e0d954262e8fd55790c972e1c575069a7e786028cec43b0737675bca12a27c442cfe80f694361736f30d0209139e450939c77f206555e18a447c6a8152f025a13e06000fb36d4860d456efd1f09c8e92824d448874857eeebf39034f9362ad0ee02415a9700070b62089990e63916de2224cda523bcc215d32d383355f906239bcd8a680d07ac0a8f9e2c080c3fd12a3a4ad782b429c981413f4d4a8791890b2dde21b4649b96695d756566e3e3ef15c9ceda42ba0d2254f6f8f1e3579f7df6d97f9a3a75eaa2bababa34e6678168a9e153607f46aad8f046e91caecc65594b27cedfa894bc221f6532708fdc748db4e13f64707a48f298ac42363e021cfbc8771d43fd5675dc2753bc0fe49c73e9dc500315204f080ccea1026f270d1025901ea9bb205850e87d2a19b732006cf7cd6ef4666545f68249c1d9035758386f4cb727cf21b44c3f7fdfd04cefb59cf6d5ef7077be749e70ec82ae084f842a3363c68c972eb8e0829f353535bd8a21437cdf30140a31d1029451902f08f843a8c71094a1933d9a856c4d0eaf7f850695f8e096c30823776e60800001028c34028b7318d0357c6910e400bd146fe0370fc9872847a86498b873e2e3cfedf082a7e847a37f76d25fdaa16d726aeb731fb45b979d4c6e3ebbf47a0140c4aa77d6ac59cf9f74d2497fa9acac8c63d83097640d87638168814e71a9d0d62758792359b80f5357d5762d24b73b0868055abfa6c8b5acc89377fca2b08da5c382b204010a8080641d22341d1f962c9e1e17a67a7893d1c46af2e4abb02657aa5f385d9b2e496f7cfc236ed7d689851e2289c5627df3e7cf7fe4c4134fbca7b1b131851e2d90ac5ca20512920bd5ab3554462bfcce7a6de8730608301ca027a3d09907284e0413df0b8880641d2ad8f0eda79729cf50b58687da40b47418e6a470d39dc24bb55b89aef557a6372fbe5ac4f73415da603735356d3cfffcf3ffa7a5a5e5e9aaaa2a8788177fe3f0b818be71d961fa3ff200d9cd1a6074232058018e0a886015a4a738804440b20e159e8b8950d8e19f8584b2c2587f0bc0c7a55dacb99589633d2f61f6eeac4a6e7feea3e9f50f5ea3a57bab385081601886336bd6ac17cf3df7dc5f8d1d3b765d6363a35b5a5a3a30111ef05beff92524230c7e1e2a28963c115d4d436f2beff026408000470f548ff5d1d4ab4ee90d1a5d054440b20e111ebf02587c90f5c8e3cff77876bf30d21dc2ebdf23bc9e2d9352dbfef1517bfbf30b84932ee844f870389c38e9a4939e38f3cc337f595f5fbfa3a2a2c28b4422dca3a58816dece3c96a08c713eed1cdd0b83abfeaf00a30d4a57508ebc1320c091212059054440b20e15fc026171cd118251e67f9e2b4055b00084e6668495e91566aa5de87ddb67da9b1efea4b37be9a944b40aba5069595959d782050b6e3be79c73fea3b1b1b1b3a1a101abc433c9427ef2b3e491908c14300302daa19ec5d797bc3048dc51ea03ed057e7a10284f464b8660fe5e5078018e18f9b23b01864740b20e119257159fed73316448240b0b95eab46f38b630ec7ea1253a85166f136ec7ea4b52ebeffb84dbb3754aa127c2575757b711c9ba6deedcb937d5d5d5f513f1e261c363717e9622e26a9b0f64ef04ba557cba5a48503948d65be4208255943de601462588648d0ab53f261190ac630470e2dc8345f5c9203f62f13e1dc7e77f527dc24b760ab77df995e9f50fbd57c4f73416ba954c44abf5ecb3cffecbf4e9d36fafa9a9c98068a9c54a8fe5c9f0234eb678a810652bfbb3f2bc0c5580a387c02b1629465b0f23d91ce718181c18b50848d6a1822a1856c7a28ae61f281c0652408e14df9536a834754a1766e61bba214c225b26912c3d9d900b95f6ef88a576bdf4c1f4a647dfae398912ffea828088943b6ddab457cf3ffffc5f8f1933e65522592ede3804c9ca67af4f3e80e7f1f565e41f0cf40a2f67e0b34fc7583e1e25143deb84ae90ce04ecb848e1388e39ca1a82c1120e054440b20e1545a4acec427d5bac614152470a568097865a27f26592e842b7d1a315175a7ffbf8ccb6673e64b72e3fa5d05f93c71b8753a64c597ed65967fdaaa5a565537575b58749f0c72ad1ca8be3442bdb73827a1d204080004580c0188f6ab8fe22971ef95587c9952258285a0d044b27d26258148e7c2f112d23d921bcce8da767363cf241d1b7737ca1e767e18dc3b3cf3efbde0b2eb8e007555555dd183254eb67f9c464908c6650b9e4e9013c3d68b68e6e90ae07df9b2b5e5055e6de46ff67d123e8152d20029275c8283ebba75185d77d12a26b54a49e1c2d726c9c25b242bf758843e1927d424b740967cfeab767d63ff83e3db1a7217fce7f78e08dc3d34e3b6de1ac59b36e6968684812d9e2deac63ad472b1f4e93ca32fbada7636c398ca3848132287206130cf11429d003efef8e160486a0800848d621a2d08464388088183a91281243276242c71cc761c1f7f3987cd131dd259b9dc90837de87ef1bd6385b9efa646addc26bb444674da19f0b6f1c2e58b0e08f3367ce5c5c5f5f9fc6a777d0a3758c212f4e1373b2d0d20eb02f3017d0df2d76040558a4201d724653fd0ac87a611190ac43840632e2d39162ab6898f08e8e1fbc67885f0ee669511af119694eaa67089788968e55e1e39d42f4ef684e6e7bfae3a9cd8bdea665facb38920201866bdab4694bcf3befbc5f8e1d3b76495d5d9da356843f1040209528e0998f739231402486e6cb718ea2ce80dcf2a1720b860b031c1504f5beb00848d62182288b81453fd14324414ecc2b4c366a9a2174e27c180a54dfc7e3faa411ad226ea2198670c8c9a203cbc5b23b24065d63ea749d93115eaa4768fd6d33131b1ff9a4bd7bc91942d8076634238c5028943cf1c4139f3af3cc337f336edcb88d44b43c3a36b050a9827c4e99ff38aece43705cad1a7f7c1a174f733d8f7434fbec2a5f8e77503e60088e9503798221764c5ec3b2277cac08b208e9f211d8e62205f448d5279457ae0c8742d73d4ad7681bde3ca61054e44385e7a186f93f8a03480f3ad80646fc50e18978807c00208458d8016bc18398f1db86ae23343b2e44bc4dd89d1b4f4f6d78e8c36ecfae96424f848fc5627da79d76da83a79f7efa2f6a6a6a76e7ae9f359c11c3313c7feeb9fd19bb22407e1246d991eb040248505e2053060d17165bfee4949beda737c0284031d7b3408f0a8b80641d328a674ec770644f1dc139265754f771ccc5d65f4100bfb1e4030f1bda2961da3d22d3b1f66de9750fbe47c4db0b3e111ef3b3ce3befbc5b4f39e594df343636c66b6b6bd1cbc5862c5700f45aa9f9670088a5fa1622e6a81d8f40cea8fc0930181896f6778b12a8b710d2e144b1a7f57805d5ad01c3abca0bb23f14ba2ed2fd033d2a2002927588d08a68e2ac21dda9fc3104b2cafb7fd92610e9c2960fa9e111977bb3f454b7f0e2ed65c96dfff87866d323efd452859d9f05545555b59d75d659b74d9f3efdfebababa14162a1d6e6907902c65e0d4be0a7320c3772c23a057a317d05b5f7f65a50d507420bb326c152b567b03bbe8eb538002202059870aae48c5a1af0ea563a0778ab6bc3f48d08081d0393d43e7e90addf6af81c87e39c3f18491ea135aefae89c9cdcffc93ddf6ea99c271221ca070f05a5a5ad69f77de79ff337efcf8a731115e7de310c60c86c3371eec94d431f468a5d3e9819ead0001461ba0cba4bf26743b405162541116d22369ec03140401c93a5414558b000382f477501d52249048081dcf0a7ab206ffa6872105208245bbf8f48ed6d72ebcaecdf3536b1ffeb4b367e53c8aa7a06b2810a1cacc9933e7b9d34e3bed7744b236575656bae8cd522d466ce18820b9244b11b0e3161a068703ec0752798a1ca4cf78792128c7e2c468f39b01c92a2002927538282a339d4d0c6a92fc256d33de9cd2078eca9e2eeecda2eda087f074225aaeb0444684ec84b0db565d915c79c7179c3d6b663a4e61df388c442289934f3ef989b3ce3aeb974d4d4d3baaabab074d8407b98200f88db95838a726fd07083004454f5c7c7dcea9a0018a09d4903386da9efd6d8b019456aceb1510f602212059c71aa8f28342a1a36aa0cf4d112bde62180dc38c0327e9bfcb2b6b5974ce4a768a507cb766ef5c7a75eaf53b3ea3f56e9be415f88dc3cacacab60b2eb8e06f44b67e57515191c4b02126c2e70e1302f89d4bb260088b09c8757f37408140fa523cde2f074896d265a5cff43bd0972204d999418b91aafddc63c5029526b28545a9f7c7030292758828764d2553edef65c14786122dda6753eee1b33bf4d3a3e398c7e464849be91756a6d3705b5fbb3ab3eea1f77ae99e4a042d203c4c843ffdf4d3ef993265ca134d4d4d697c7a271c0e3399ca357210dbb6d95115a3d10b3004018d18001a053922995680a203d99551a5b5a44bc104d502222059878ce26c110c67913d0d83855807de07932b302a55e754f1d316d3781c22294e5ae899b81058a834b5b732b3f3a5f7b8bb979e4eb185fcc085823761c284d5e79e7bee6f69fb424d4d8d8d370ed1a3a5c8150473b240b28eef89efa3cb07f8dbbc0004a618919b2eec932e07df2e2c5250d1047e33c041235096510d1866484e310e34b2862b5ad8ec5c3a96dd0741a13f2444cbb050a9972292d521bcfeed33121b1efda4d3bafc443a59d089f09665a54e3ef9e4c5e79c73ceaf9a9b9b573636360e7c7a4709860c013c0f3f531141cb1b412f4e22311c3c2fbf93f48b9db8e4902d2c283b7a0af23882ebba83267caa32cb29bba202a52be8152d20029235aa7108959aedb5fcb48ef4f5fbd63b97c9952b0cd20ac34b0b3ddd23b4788748b72dbda27fe94d5f71dbd74e2bf4fcacb2b2b2aed34e3beda1d34f3ffdd763c78edd8289f01836448f56ee27788ad5e08d34f8a9bdc29651b18274a2a80996826a1c0424ab380192a56ccc503b53a4762720590544608c473d5084fb11eed5c6160d2faafcfc9b0c838b1706691f3e871b390e9d92df3b14a627e76c11e172dcb4d0529d424bb46989dd2fbf33b5e1810f68a98eda421bffd2d2d2eeb3ce3a6be149279df4dbdadadaee9a9a1a515252c2044bf56401853478c8a151e1d18f238c16d202bda5b4ea01c92a4e10c9e235cca87cf837f687da9a42da9e611098a20222eb91021c148ad1eaa142e323d14a34926cad428ad18365119ff2051f8a7675129cc53c2d2258ba233483e221e175b4e8b8e6d9742a258c4cbf309da49edefdeabb539b1ebf5cb30bbf223c11abed44b4ee983d7bf6df9a9a9ae2151515c566d802043868c06143f0c2060487f84480a203168af57707ca2d4080fd212059870cb42e8bd199a328a560895109a493841bc452f04ffec64fd98bc5a2e3733438462d34fac786de8378142c41417a8593689d14dff4c827d2db9e3f4fd8fd2588a280f01a1b1b379d73ce397f9e3e7dfabd44ba52914884870c3137ab180897cc6bff07017d84feee88a3f04f1fe07090ebb4691bd8e72204912c4bd99751d2b00b5860011154e2430438471e7de561c33701fc17c3823c3288aa86ad3c4a8f0192e51f74fd15d3b1f401ed33e839f1cfa0870e095b68890e6177ae3dbd6fcddd9f4b6f7be13cb23605fdf48e61180e11ac572ebae8cd3f6f6969f9477575b58b35b4f803d105265a9cdfd89228c74982c9cc7c7ec491affb04382a50baaa86bb696b537d0cec731102f538d7b6a8fdfdd99bbcd5f9fd80d2e5920406a140082af1216334e92a4894ebaffcae8459221fc72477059f040c1804e6913af824fac5886839b63078227c9bb03b565fd0bbfaaeff67b7bf36a7d08e8008557adab4e9af9e75d659bf1d3366cca6aaea1a27122b11a615c6c7bcfd5085c1405efa5bd7e5c971794276ce4831834c7fc112596cf9a39c34d205094856514243b90cd59dfd11ac2201257714f40c1ca3082af1a142d35d4f379878c8b601fde11ea1424312aaac0098ab85ba8584fac3829a4d4789609148d2950599753c15132c4dc35b7af49c1a566d90216158a87d2d8c545c38edeb2fce6c5874b54876d514ba028742a1e4fcf9f31fbbe8a28b7edcd4d4b4a9a2aa5a8068198645cfa4f19a5990a18611c0b1e164286044f767480f740e809e780e95499e867f2829be7796444b23c9ea6b71813427afbaa36a862a63d20afa2773ab505a9cab3b481774d5b66d8bb6fe1b2b018a0544b034bf6cfc23b2cc726d06ca531da3f083ce1502949e22acf9c70ff262f48f3d8c0e9dc5a4f6ac486b2dab1bc8548e51f0b7f0326412844e24928983c87e9a06d7e90efd8171219225527d5aaa75e555cef6672ed2527de564490aea0c2a2a2a3a162c5870e749279ef8fbca8a8a4eac9f8561c303ad9b359cf1cb3d96ebfc383f727e0f87dcf38847fd568696240f79e49149a57f9cdefd3fcf710b7292fe5e5102e5454e5cf4f5f5d5b5b5b535f7f6f6a2115341dbf29e9e9e5ca9e8eeee86542a213dabda9f7475750d9581eb482a109f8a1bf77a23399cb024ea3e2aedfb3c03d205f1d35d9d2bfb49bb9281f80ef61e39f1ed732f2543cfb7b7b7377474748c4ba7d31eca49c1afdffeafc1a0ebf67b2e4f28e8cd8f77e46f8ec8b101cddef4f8e5c9e7ffeb5eb36f3bfd4809cf49121951ede3c2801daa3f8f8a7b4d06dc48365dae06b2217fcb8f46ef0b1c050fe0ebf196223b6a83178347ec50151babc21b6161eb116196358b70e5d825d6d4b7fcd49c74f19dc28a10fb2a28b4eddbb74f79f0e1873ffef292a59fdebe6573696767a748a792dc3b702824636858f5fb40f50561d47965583111bfa1a1418c1d3b56bce31deff8e78b2fbef88f146eff911c2932f1d2f493dff97366eb3fae14e95ee166e2c2a4d23354fad19b59000ce40b2997ab8584168e0a3d5223b48639af874fffe2155ac5b88d08c6814608445ccabffded6f2f5ab264c929ddbd3dc281ae53ba309c5e147598042f6d34353589fafafa6eda2e6d6e6e5e460d863dbefe0d2825e997456947a5568b96f23ecef9bf07c2aabccf057490e05223040a01528e793b368efbfbfb6408851f6894fbf7e078fceda0f0388f7304c30f8b61361ce37d3a3628bd740e6b4fa973383c280c21f73e889c4efb0135cdc16fec03f88d8d7f3d7ee31e26898a6b608b30fe710ac691aae31c86f28792465a2befa72712899a55ab56bd8dc85605916001a1f3dc98530d3a00f1280172cf1d4da8f8f18c10d5c386fba19179da69a789534e39e57fdef9ce777e0d4bdff0c900798552aa0007871c92b5837e2425c942161670c8902b989aacceb648d99061d2748074c2e964414601364a9795179fe8c1a478dc0677703c4b58e11261c4aa845e39e95573fec73e6734cd7f9e6e9091d71706a4cffad6ad5ba7de79d7ddd7bef0e2f3efd9dbbe37d4d7df27ec746680f840f899720c9442ee7e2ed4f103d597dc38d5bd40b21a1b1bd9715e79e595ff74d14517fd99c2ed3f92238447242b1390ac6151cc240b80fec03962cd37257863567dc900e7958e41bf20ea3a752da0f27a28f6771cc0f5c504f59c406eba0ff40cb9507902e09aa1d7edef7973af0372af45fedab62dbabbbb59fafbfb453c1ee7f038a7f21fbf55d9a86b55bc43d371a4181aff7e48d6af89647d232059854140b20e0d454cb26018c8b1d326ebc28749d361922c9ec5853710e90ca2a706aa304c72965654682563843ef6f43f85e65efd63bd7ada1a3a5d184fee0373595e79e595f3eebee79eefeed8befdd4ddbb779b3d3d3d22954ab191e4b70f0d638008e1f90065a8703cb7e74b1d070e545f10469d57718364d5d7d733d17ac73bdef189cb2ebbec77146eff911c210292b57f8c069205817e2a819eaaaf1828a8bc545b751d1c2b8ea9e343911b7e28f677cd9140dde770e3c6f52391aec305d203bb003b924c26452693617b827cc7b9dc7c45ba73d3aece1fede751f1a9f8f747b2aebaeaaaaf1369efe19301f28a80641d1a7c92f5737fb8b038481643f68c13c9021da24ac6c5ba1f92e5871d0a4c02ce42922cf02e545e9ec545a73d180a3aeb10c932898009b8f068ad7062f5c9d0b8d3ff3b76e207fe9d7eb7d235b991e51d894422f6e4934f5ef5d4534f7d6dfdfaf5b35a5b5b3510ad743acdbd033040d07d90213c1f802d8c130c270c28ce2b07a7c21ca8be208c3a9f4bb2b0223d48d6e5975ffe79225abfa07b8c98b204246bfff049d66344b24e2de69e2c00e9523a37142a2f7381eb203837dcf903e150c31f8f5079a4f2570930b4dcd471b50510667fe57924c84d0364389275eaa9a7fe377ab2029255180cef6d038c5a6860450318a678f743b0807d8c804f1c5191898d836631a1442843c3db7a52841d1722d31b49ee5cf9fee4a6c5efd0d2bd157c6101118d46e3679c71c683279d74d2ff555757f7e01b87b1588c875ef03c205210902125388e2d80bc5086f348803855bc24795cc221c050503950b11e7d4777b4003d81935402fd04d95782060224f758ee39f4b0eceffcfee450c30792e1b251760236023aa5eab942ee7ea1758eee5ff816c4718c80641d73a00a3d88481d5a11c3200c360a59a225e11b170a63683af790e86e5278894ee1c4dbc7f4af7feaff25373d791959a210072c202a2b2bdbcf3aebac7b66cc9871576d6d6d827e73cf120c24bafbe160729d9a726c78d6e186690e1739795a586b7b9c83ca002cab681d0ef44ee921f6214a7720caa10f27eadadc46c2c108e23c50bc814851f9a424d73e40009401f21f5b05750ec83d1ee0f84140b28e59a06855f11e7c312ba380d1bea1237eca5cf082a608a7c2b8b6d09cb830d29d42f4ed9a95dcbcf8636ed7c66964540aae5f63c68cd98a4fef4c9e3cf911225a99aaaa2a1e2e5486125b20d7402a63986b408f148887e23d3a91bd018266eb0151b49e4ee99dc270baa77432577231f4f7c162689c47530e17c3c5554ca2a06cc681ca6fe8357946d1eafcf180823bc1002303385a4fa78a8f55db698b79f16f24000c853206a0046a5f6ebc01ea466d5f169ead4544cbf06c9284d0325d4274ac3f3fb5e5a9776872a1d282ea98699af6dcb9739f3ffffcf37fd5d2d2f26a5d5d9d838f4947a351110e870726172b0339f0ecf47b68abf47091cd437f674451dc43624580a2cd1c941b44f596a8dffb2b4f75fc8dc2151aea790e558a1143f358d987a17622b74cf02cc55a3601461e01c93a86818aafe45080f0b94661b0815064046178970eb944b01c626a2961da3d42b7fb4572f7f20fa4363f7ea597e828f88af044a61273e6cc79fef4d34fff5d6363e3c6dada5a0fafc763e87068977f2e0e35df0e043ffea317e17ec105431cbb789d6ea140f981fccf43191c1e505eca212b7923a8708a981cec75f9c291a4a5989e63289036651fb05502a832c89502a36875fe784040b2021c10c3190cfa4f848afcb8eb08accf8549f11e3e20eda485e7a484b0bb85ddb36d7acfdac73e9fc144f854771559a0825a1a2255bde79e7bee5d0b162cf8494d4d4d3b3e248d9e2c18463cd370adcda1bf8f02f293078339f268403e535bd40e6738c73d547221eba31445b48a09c3a5ff50a458a1e6bea9341e88e422ac92627ea600238380641d3a468ffb1a18a9c356c9fe31d4b091c9e009ee6426c870c89e2b6940b25980250178215497c27a14ce490a918e0bb76fbbc8746e9ed9b7e9a97f496f7dfa2dc24e44fd4b0a064c843ffbecb3ef99356bd64d44b47ad19ba530d4381e6d6338d4f08e28d0c8cee3ed021c3d404f543d543a38541787d3cda1fa1b6064305cde2b0c2d035586b95228d0bd03e528100292758c0155e970ab933212430d03a203d9a23324e8c1ca8aecc5c2c29d64403c47b81e9646c0b0615c586ea730fbb79c686f78f8d34edb6ba7504baee0fa565555d576c92597fce1f4d34fff636d6d6d5aada63d1478eea3d1f2447eaa3791e83eb2f91b20c07e007d51bd22aa6724f777ee7140d5d162ed2951e93b5c2976a01c940c45313d07a5aff833f3184540b28e1128727524242b17ca30e41a885c43926b4040b4f0c6211d15c22143efda427712c2ca740b2bbe5b88f6d5e7b81b1efc9048b43552f8a390bac3073d833b61c284d72fb8e082df3734342c510b93c271016a9bfb7c470ae49bef18f343b2242f1e2d184d691d5128f2942b38369c28e4eae95029348e461a8ae139145479603b14b9650651e956c41732dc75018e7d0424eb104115a5786afd30c062a4030b927252b19f5bb951e4fb167bae8150c6401987a1c28b94e6f205ffd33b06456b1a86b06807fbbab08593ee1376bc53a4f7ac7a9bbde9c92bb4745fc1172aa5b43a2d2d2debe6cd9b77676363631ccb3aa8b70cfdf3b9bd4f7cec480043ebaf6134f28b91fa24b6785783da07a4508525dec582e1ea5a6e0f55ae0c87dcba0bec2f5cbea0d273a4526c78a37ccd3daf9e4191e3423c0fddb3b08a709ce3c83dc8f1060d5fad1f253a0bdfa5e420a00c828232e8b93208e4c9f91a3a8c2d8c88617844b2b05029de38b485c8f40927be57b8bd7bea335b9efd88bdf3a573844885fd180a864824123ff5d45317ce9831e3e1bababa0426c163e850e5812259b9f97138409ee590ac238bec604006d523964da5b56f791523463e4786c59196eb48409597d299e108562e727f2bbd1d8afd5d9b0fa8341da91423f697af437f2b72950f925588320ef0c6f8ffedbd098024477de61b79d4d1d5dd73df97469a43b784002190b824218104ac6181355ee3b579eb7d0f96f52ebb2c7eacd7bbb6176cef624e636330c6d8cbc398c51cc6087399d39c421c968424745f33d268eee9b32a8ff77dffc8a8caaeee9ee99ee9aacaaafeff66fe9d999191919199717c151119a9224b69d25e68b40a39368bd05a4ddefc7124eb145afcf81ba7708839464b76cb6cf0dcef9bd8cea1357dd87847efb9bc71cf175f13efbffd2938a0d733c2a7dbb76fbfe7c61b6f7cd79e3d7bbe84f506bf31e88416c95772245f48d2cdcdcc2dd7992b44dd7d7446b2e33a9edf7076fc8f9b2d662ebe4a138fcfa95f687f7e2e3d9d8ca23cf385c4f5542c45184bc5a9e292dfcf67d08de7b090f3607fffb46b0f202ab20604694d1243a5bfa082898fde992d209c1197799b628a6eb4c47e50d74f212a6449b72c93e3581125105b1465018e95cfeff014d1096326f699e889db5e387ddbc75f1f1fb8ed62b8863cac57944aa5e98b2ebae83b575f7df57b779e7df60fd7af5f1fb1458b9530ef83135934de83bc9072eeee7ee5f791fc3e921ddff1c20ee764e41089044f16e7e79b9fca0cf2cf2540aa761f87763f107a898b1bd34bdef2cce587b4a7b776eb058cd39958d170716abfafa78a2bf7b797094b41fb73cdc74929062ab24e87e2e5fd3384c9e04c3226448854e6a8d8b1904415c52685f15ec99000142e5e326982c61884d6ed2f69dcf9e95f4d8e3fb21d054f4fd36018868df3cf3ffffbcf7ce6b3fe68f3e6cd3fe3f82cf721698a26372e8b8563947d509aeb2cc8b88fb0458bfb5c01eaf63b3f3c9e74eb5af124ed7f6506782e7253dcb3a1294abfa3e9b8d8f4b482eb5b06294d37e7d23ad38b82d0e2824203828bc22386c8a228e18ec08f4d60eac6af1f35def4e152e3f19fbca271f7dfbfd24c3db1c9557ebd62c58a15479ef1f42b3e7bc92597fcc5aa55ab26b0dd9c0dde89245e078514af8bd08d428cc8b566e6849613584e8865ee5dc96f99d493bf8507d1ece6f3ef755a53941ed02785c160a2226bb1a4a9cfaeb2a2b2e81aa4d95d7f263d59adee16199ec5961c385157506cf822343c13e3be25f1b8f11a878c997c7cedd423dffb57d3777fe9e55e34312201f490d1d1d1a3975f7ef9df5f74d1457fbb79f3e60862abf921e97ceb15c9af733f8ddb74a7393727cce846e8c71dd73938ea3d0db353f605b8276792f8160ccec3bba2659eb2ac40f9d3f9b79a9579d102677120bd2661ea97b2cd62c08a1b55ba18c79770292a276f5d44c6b9f89e093cbe9d17981416a71059315bb61ac68feb269c3e61bc130f9d377ecf17dfdc78f49667478d46cf6feace9d3bef78ee739ffbfe6ddbb6ddba71e34633323222f796f1e692ad525c52345140d1b8cd162deecb8b29c2e31a8d86f8a3e8c2fa30f675f46178a5a189a431b1164935732938691c78d55587b9661d3a07bf61393e3ebe21db549465c1e4e4e4460e8bc836952ea322eb74401d5af42a8c4287ad4adda1958c282f685660f9105bf811054b213cd8aa934078982432269a347efda0091bc7564cddfdb9ffd73b74c76510233d4d8f1042f1a5975efa8fcf7ef6b37f7fc3860d4729b4f8e91d8a28279cb874eb4e5451401127bef238bf199dff4599b2218bd68a5fe1e952171eef0b17764b5196072cd7b255a507a8c85a3c5da910ce1456b06d157c87b149c989101f228b1393526c416a213e76ae2819b7c4d6b62036a1894c181d37f1b1879f357eebc7febb9938b05902e9214110c44f7ef2933fff94a73ce5ed5bb66c390eb1d5145a4e40b1d58ac28ad7c9fbecae8bfb9d397fce2fe98ae88190e88b04da03ba9b1f14455154642d1ad6abc5fc2dcc8a7da6a569f64dc159fb961a978cb0e478354eefe05b81c58ecca600a349458738216e415a374174ccf8638f98fae3fff4c2c67d5ffe05531febf9f82c88aa13d75c73cdfb2ebae8a2f76fdcb8716addba75f2c6615e5839b84e51d5ee264213fe29b2b89e55f05daae5793e77af154551945ee16a47a56fe160ebf95b83ddbc5633994b6c9d49526885957a101ca8db457870068734137b8843289fdbe1ec4d9e492044e278da98e913269d3e6c4ad11133f5c0d7ded4b8ff2baf30d154cf85d6ca952b0f5e77dd75efd9bd7bf797ce3aebac68fdfaf54da1c57156aee58a3831e5c6663921e65ab572026cd693e81c2ab0dac173c0a3d1fba2284af75091a5e470ad4e27b35361fd71f84b0c81254224b6f36749f7a11fc804a574e7140f7154375e63ccf853874c3af6e8da893b3ffbd6e8c16fdc589feefda777d6ae5dfbc8b5d75efb96ad5bb7decad62c761bf21b874e3cc9b50127b25cd7208d505ce50416d7bbf2960fceaa5a623eba287315455116526b2a0585c2c50d70679dda6e79e8c7592769bde3c87e5540a14131e22510587cebd04680f1139192726c16ad61fce9a326997862ddd8dd5ffc6df3d8cd57c771ebf3303d22ddb367cf2d575c71c51f70fe2c4e543a3a3a6a8686869a83e19d514cb1854b06f767e2cbed73407cd91d1d06678d67a70005b011555114a56ba8c8ea63f8f1947c4b89edb66b6d9f497db2183126c3be0426a7d65959a3b1458b71f42107659c96db4ff12546f1c5efebc426a81f35e989474de3d8e3e71fbffd33bfe58d3f7c0efcf4b45a0cec40f89b76eedcf937ebd6ad9be4fc596cd1e2e777da45567b0b577e3f8d4eb243e90978066ce2eb8ad0551445212ab2060e2a9e93d7230b1150ee738433809b7c152f33879541323b57939cb090a58c1b4b38488b5d87a909e016fab06c0c132d4e22e3c593c69b7acca4c71f7efaf49d9f7d8dd7185f2181f41088aae3d75f7ffdffbcf0c20b3fb57dfbf649f7c621bb0529ae383e8bb0758bdd89cedd5957b16f3a288aa23872a5b5d26d54642d12d4995004d946a1995f6831fa73092d3ae58de4fde50503d75a42cb092c8829ac3b33325716bb337d1b4e0281154360711d61859e0fb1057f018e97b0204ae27113340e9a52e30913effbd16b1bf77de59f1b3355e5de5ec2894a5ffad297febfbb76edfa3b88ac297e7ac70d72772d571459343726cbb56ae5ee9b1676b3e9ea3de1f3521445e9162ab21609149654a9338aea1ef540e42b8c96fc71f0d1dad151f9f851ccb4fcd2ddeea31b43cb1b49e4d876cbfc64e767db09cf262d5a10141c83e58e27cdc615f98834e114075cb7612531e7d04a20be121342a004690342eb9849a68f0c9db8fb8bffb5fed02dcf4e929e8fcf3210571c08fffb7bf7eefdfcd6ad5beb6bd7ae95f1599c7475ae962b777f9c3b045757ae8173c066abc5a775bbba051e45f74faa28caf24445d6a229ce6be05279fb9051cde8e41fa715307341ff22b66489304448cdae7828d0e6ee5acc870d81c1b0b8da1451366eec1acc23fe6026a0d6f0e1dd7eb226e2649e11bbdc28b28c091388ac68d298c983263df1d0ae89bb6e7aa319dbbf0361f2e89eb27bf7ee9fbce4252ff9f5eddbb77f7ffdfa0d666474a5f18310d7eff3254a315e3bd348be158b4b6c773ebf719a82ec31b8731716c48fb72adbea163313a5a20c38852f07061c15598b0409b650f78cf34d517a246c4da18367058c081e182bfb7cc306339c133bac6dd8c147372ead4bf66e20049254d61060145a34f9564bbe8eca02e2110cc19dc99d8d63afeccdb2c788f0c0ce9871a02be340c12ab714c763c973fa8c77dc30def431134e1c32c9f1879f377de7a75fe74d1d5d2301f5105c43ba6ddbb67baeb8e28af76edab4e504a77618e2fc59108e31441545a3bb6a761bbaaec30c776b3a07e20769874785a7827b4b7314b4b045d26242ea0eedf9b7a0f764c673cb937fa6929733236edf7cc72e67dc7d99eb79cf77bff2c7cc751c71fb4eb5bf97e0fc33d2bcd25df4e62f9634f16d5301320f0408fe5af71e20b1903f566025fc46201ea938b1dc8051f6486181aa4c3e68831dccf422b0fc445ab2288238309d428ae3a58218e2400e6f092d1aaf3b9f60786e7bbc2540d82dc331f0c0dd7cb330e5f9119f1871714b0a33c69d1f91a689908be13f415ce2ba294513a6d2386ecad3874cbcef07afaddff7c5579a38eaf9fc59b89fe965975df6a54b2fbde4cf376edc5c1f1e5d618252d98a4dde5f7697e2fa7c4e59811b60d30bc4aa8f47d01578e3f96c29b7acc0ed7541df8ec7fb242b48713d6aa164fab6dde14bc352dc638661d38e15e8dc7646dc3eb6928aa06f73e7d26d9f890d12f96be2b2fd7e3a9c1b99ef98bc9ffc3a39d9b60bcf8579a6b49f4b292e2ab2164b9647962ab39c092ea371292d59a8abd83294f8219e2c2c84b8e1fc084180ea2460a4b16d0596482e5cc242aab79c8e6a833b6c001cdc4eaca8426182a53d0e055356914917a5b888ac833b8e11214283d0421814793e2a8f00c66ec3309e34c1e441134e1daa450f7ded0dc9c19f3ea9d1689424c01e323a3a7ae839cf79d61f6fd9bae9db1b366c48d7ac5963aad56a7330bcb422c21f8df79a15629274e717659a26814b1b4b59b02f25123f490cfaf1dab9e0fd69b7bc3b9fa91361cedd754f2f850d0aedd733d7b6a37d1f3959de69dfe7f25abbfb5ce12acb0715598b667ec9d14b9889a5fb8dd53a04965fae99201cc67a15553ca715088d07411304289ca1b7d8bd85a3a44090aabf2d253891e4ae76f655b75a009cc062402e28b64a317c1ae3e5a0a8b3dd84f68d42db368638897f14505c267c1b11ee142a496482c68449279e30c9f8feb3276ffbebffe11ffee993bb32bee9e4a45bb76ebde739cf7ed65bb76cd9fcc4962d5b0c272be54078567e5654d9b70bedb8b33a272bedd25b924bd83cd32924cd30012015d8a6bebea7bd723d5d5c9ac9a721e7ee8ce7725f1970fba2286a1ee32af6e56e844b77dfdaf79f6a9f33f76cb9cc1bf3bab3bcfb5cb8b094e545af2baabec3f3fcc40a02cbd214ab670adb8e00140cf3b04715551a31a6ba16b626f6abab4d52aaa020c80a0114085433322e1dfe298dd8a567450f6c9e89c967558599435344a16ee791ce9fbb4f5c4aab99ddc47e6ee09ca2936058b7ed3e16a97ee1d9e7f5d021aacb370ecd8983a671f89eeb276effe47ff1270e6c4181d53aa807e05ea6175e70c137ae7afad37e7fcbe68d87366edc6046578e9852899f0fe23de5b5d842dc569ad150afe35c189c0ec41229d2258d6e80c756cc4770b20ad8ede392c66bc857ecce9d692def4fad75dfda69f7e7ccd1be9dc7ddf7b92cbfdf315f38dd00f1d032a787b01e50fa14e61c31e421ce37c5de3766e684ad57431b5233b2e544ba66cf7dde8ab30e79b5f5260e2af04c4ff8d516a7f8e5cb8934d960c46e46366f71897d993916530552a0d963118f2c9c938d7b7161b3abb0d99a85642963b42016b96458269e36e9f471938e3d66260fdef973e33ffdd4ebbce9235091bda55aad8e5f73f5d57ff6ac2baffa6f5bb76c19dbbc71931919193161a9648252683c7e143b6b71c0b309b3c3941e80fbefc10a5de6b9cad8a5199a13530ee787709d029ee4fd9fa9f17c45b1b9e2b7189b2bcc3cdccefb27bcafcef2dbaed56bbe96afbc3b71e76a3fa7b27c5091d5c7b86c8b62049938b52d460932b85f3149797422d9f8a48f9a73ae7f477acef3dee50fad99f42ba3d0586511365250c42c2c700c5bb1104a9c35b2e407b3e7c75ae5a1573acdd72ed36cdd3a25887b33105c89ac3bcb2a983486286c18333d66bcc903b08366fc91effda7c9bb6efa6553af8ff0c85e52abd5c69ef9cc2b3f7ae1f9e7fec5e64d1b8fb2db106e32fbbbabf408df04050bbe33a7c58074bd7508defbc29779aed277a2c9a5a1b92a6a57e1e78fe1bada6c23f9edf67bdabe9d174feddbb4f984162d0fc355962ffaf4fb986656f6209158384060251024b18f427968fdad66e3c5ffe06f7ddad7fc73aefb78bae9c97f6756ee6cc49595a6117b102d2c90aba875d8c28230a4ef906ffd214940e888886290ad32a789b873d9d40b28e8b3562b1267fd84a297f264f53f9d5d5722e1b6f5cace35c6075b72300b43db9a25d33bf08c2c1ce349e3d78f968fdff795374dddffc59798faf8b01cde4320aa8edd78c30dbffbb42b2e7feb8e1d3bc6376cda682ad5219937ab5eaf9b6918446d57f29bbd97ca7cb45782bda0bd5276717295bc1b93d5becf09292708b874eb34e7d71d77ba5624e68adf628c62c88d5523bc5feeeb0cf97be9fc3bdcbd75d62e78f3c73873a2d785e3fc3953961f2ab206049f5d807c6390991c42c51b59779fbf7acfedde8ab3eef556ecb827d8f5c27762fd4749794d54f7ca26094ad295c52e395b30b090c802cb55d3562831995000f1f3396efc14965268e0b8ccbb8ce9ca8494135c4e2fcdd7b265bdd313cf414fd6a3155ad8c960e44d3d5e150cce61326dbc89c3269d38b2eef05d5ffaeda907bef16214a2760470efe01b868f5df7bcebde77fef9e77f6ccb962d63ec3624bcbf5114a190359138741aaa516516a8e4785f6cc2ec21ae029e0b5719d38f5bcf57f24e1ccc65cedf7c961709ed6ef359deef426cae306873f93d95b963f3f7a0dddc3d5a88d13fc3630b338df7cc2ddbe3e8ce9d37e7d79dd7d12eae9c39f27ebb4d2fcfad5882dffeeddfce569585901ebdfffc78df0f7ede9b3e82e2ba018b3359d07d5a19886204f18030e1275efce175c65f75d65dfec64bbee2d5d61d80cfc454d73ce19557dc1f1f7fe829264a3670967299f413c28c858384c4715d2c8c244416f270927fd8ce4e650593155cce5d76654bbb9e850728ba9c89bbb448f19f3d5ec662b92d0a2d54813e5665e253442011f1c87f1058d891a2b063f1c5aece08d79c7ae19afaf1fdbb46365ff279afbae2a89cb48794cbe5e96ddbb6dd323e3e3e7ce4c8a1cb272727bd72a56caa4355b37bf7deef3ef9c94ff96c670bbed48feefeecaf26638f6df5b2f4e9e4ab60556dcf608a4bd93249911fd6f06360fda1d239d7fc9909ab9399978e4121fe852f7ce1d70e1e3cb88a2d8bae32e4fd21728f3af9684e81e43d54e0acc82b958a4c09c20f91b3eb99eb34bab71bdf6875fb9d9ffcf642ac3dccb9fc2cc4dac371d61ec74e19cf3397f11ed21817e4d1a671db1dc7fd5ce6c3ca87c9751ec3e7e4f2b01353dc3e95b0caef5f0af2f16887ee14865bb76e359b366dbafd924b2ef95ba4abeefcc85366c081a0d9aab210e2fbbff2d2facdeffba47ffc3e934613c644d3ac36b2bddd06a20542c970cc129fa357826c0e8d59b3cba49b2eff7ae5b25f79adb77af71d99678e6b2a47f77efe5f4efcf4d36ff7a69e58e38ded33a6316e4cbd017d65c775c8d82e868be0b8245ca7ab18f2b4f868cbdbecfecb1f236a897f21ac5ceb96b474492de60cd22ac1af44989f665d820c07c24f06e7738970648c5880f020eee2d0330d139a69442436651357571b7f68ad5979c14bdf3874c9cbde6bbc1a1e4aef3974e8d09677bce36d37df77df7d5ba22832e54a689ef7bce7ffe1affcf2ab5f8ffb6c6f4827489360f273aff94ef4d88f2ff7b3f4c9b9c7ec6cfd206b61ec36ae9cb12f59a0a22a0d19afbad6781b2ff8d9d0356f7e86a9ac3c2c1e3a089e43f8fad7bffeee3befbc73e7f1b11376867e18ef0fe15361faee34f395b9ae15a5bd726765c963684c4b5cba0a96fb788cdbeff6cd05f7b543bfeeb83c2efcb98e39157385753acc1737c75ce1cee5e6ee2be1fda33978ff286add71fcc1e9963c2fddddb1ae0b776262c2e047941d06303ddd8c9f8bab3b2e6fcecf52913f87dbce2fe9ce96b7a73ded69e6d24b2ffdd8ab5ef5aa57435076fc878c321b15598ba448228b5d84c855f81f5bb1458115944dba768f89365c7a4bf5d257ff8abf76ef6d9977219e3ab63abeeb33ff76fac16ffcd7e4e05d43a67ec2048d29a48486b4204998d2e244dfec1ce45fc235b62265851197e2a955695368716c984d5338168bbcc8b2b092c542ba0751f8b1252b412197135972bc47e1c856182ce5d4b6458be3bd22f8ad332e10955e79d424105adeca1d47462f78e9bf2def7ac1dfa284e97961c256938f7ce4c37f7afbedb7bffaf8f1e35ebd316dae7eeeb5effac55f7cd57f420198bf214b8b8aac7941e55a7891c54a7fc3860d6c7d68ac5ab5ea68ad569b42651923cd206b21e1b48ee5268f11773ab87dd976fe4a9aeb999f7c20b29c2f4e19dc498fb38ecb232de273b8d38de1b7ef3bc9399d47e721bf3dfb0460aef302717422897e289210cfc49d3b13a9d8658fe72e2c52eb25e5876ac508d20fcdc30fa88d8f3ffe78e5e8d1a3e6f0e1c3cd6be3f5136e13bab973d3cdf95b0a5c582e3c77cefcb929b2aeb8e20a73c92597a8c8ea212ab21649114516db985889a610595e5031d1eab34d63ed25b70e5df6ea57f9ebcebb155e6744309d3ab461fc7b7ff281c907bef3e252fd90578ac651f7d6219028d6dc04dcede2088545268c5876718fdd4fb9650b1766770a257b3a6bd60f2d0f7dda70d89245f126dd86105b02af0bc7582dd2b0de21b262882e1473905db862148e2228fd2a821a36d1d0066c8f1e5a79f9bf7a4db2fdb99f2997cb7506d54b7ef0831fbce49bdffcfa7b8f1c39b20e057278c5d39ffe9e7ff90bbff81f5100764ee9a8c89a977e1059b4f3ce3bcfecdcb9f3d6a73ffde9efb8e8a28bbebb62c58a43d3d3d3d57abd5ec5b12cb37d8a782c034e70cb7d74cbc248709d25ec2fd3cd999ca0453302488b4e4848c6c771212b6884d36ceee17e80452b3367eb7341bf542d49765cfbb99b30cc6c350f0f1277773e1706c36c0f8f7e812cb98fcb6c17f1207c789f189f18824ac4551886d3eefab88f7e6021dd60f4d780201987489986df3aef29d6ebd56a751ccfa0fce10f7ff8af1f78e081ab1e79e411436398f0234b5a1ec68d4601c67d5c5f0a5c582e3c775eb7a4bb8aac62c08499ad2a0ba1b0220b998a9fa949bc9289579f63e20d4ffa51edc9bffa2a6f8d7417b64530f5a287bf7dfd899b3ff4417fead09670ea80e7d5c750224d9a30ae4b4b13851397ad562714bcd9d21a49b15f244fb6cd04e5ca405799b78b3507c360cb15fca721c28668c2bab466c9b13c06d70671c57f6cd96aa40dfb4b1487f8212a6abf2c6f432669d944e555a8b8d79870d58e7b862e7de5ebcce6cbbf8a42060aad778c8f8fafbce5965bfef9edb7dff66f0e1e7ce29c673ef359bff9dce75efd413cabce25983e1359fe86f3efaa5efb962b978bc83a5979cb8a9a5d574f7ad293cc539ef294f73efbd9cffed3bd7bf7de8af4d29b87a6ccc9a73ffde9dff8ce77bef3bbf7df7fbfb9f7de7be599b2458ccbfcf3750288cb5e892ced2eec3dae4654fa18662816d01429918c1b400634d0247e48913147a9eea5c1e6a77e7de4a297bf291cddf26850590131534138b625891f776e16050893e24da656900c8d8ccd0c2e99d90a3c2ba8ac5957b787c90bfee56f9bc193931af8f989bfae1ec9c2c04ece436adf80b467717376f1af8ffd816f3f24edc713a61c1d31438dc3261ddbb7fbf8f73ef85effd06d4f4581c353f58ce1e1e163575d75d5ff7ee94bfff9bfbeeaaa67feb7f3ce3bffeb7856d955779279a6ec577a8aab00e7c2e5611a2bc76ab53ab972e5cac370d7675930366cd8701f9f119f95134ff33d5b3ed7bc29cb0f15597d8d9338d95803912f54266cd1f242b66d89b739f0c2ca5478ceb51fafec7dfeef2423db0ea7c39b4d1ad6a0a9d87d87fd5266309c9658ca492f812d785ed6b5672d8f4b5a38664634b23a434e60c76de114b22ee3b09c77ce3e8f3059884136a280a23fb66cf9b6b0c27993b821e70fd2d884580fa263269c78ccf8e38fef9abced53bf9e4c1edcd86ba1855fb8f1a64d9bee7cea532fffe4c68d1befcb9c3b07af9715b3b40ad2a195c57b7b279453c174cd8a3b1b2794d66ab5f16c975220f05c8e65cfe8a402eb64fb94e5838aac018002abd1b05d6992f9d9749d24c3495c3fe90789bdb0341d9c73dd5f95773deff7d2914d63a6bc0a1531dfb461ab153c48456d0596f897bf16c82f11592d66ee6d2d67d7ec32409e265bfc250811251b31bc731d614321d0bd114526869082fc32295bb5706dbcc62482f4e25b911458d01401a72ba84f98a07ec294eb878d77e89e9f6bdcfe89d77bd347d6c9697a4bca71352894dd80b7ced1959632a5135064316d7389fcbcbad73f109479f128b0f89ce407df1c3881453b556b9732d8b8da50e977e4a518ea22b63c21e3a7a6845ccd12e0a405b5571a9af077dff0c170e345ff5f5c1d4dd3b06212b622213886c879b72c5630b1e548ce8050ad0b71a7a08bf37fd2d33661eb14cf94175a29dc386d0385154d0a290427f1c1f5092cb4e02e2d598c0b965e12c9b8b2523466bca9a3def403dffff5e9bb3ef7cb5063657bd0f261bec27fb983db2242b788155ebed246fc2a105c9d17e5caa2c1739931d6b33daf75336d9dec5cf9f4a4f40e57232a05e25419278fcb485e298410f14c9440a6b047cef3277dbfc437ec4e99e3fdea8aa3e1ae6b3f602aab8e98a135260a87a49b10a122ec008984e744a01035fc6c4f9a42cc3058b6364190b1ed84d2cb7dfb103bc48d93a3ca140d621442d6ec3e1cc3e3218e24ecac45cbbab1eb13f50b52275baf28fa62dc13f890562d3943d6adc20f63538871ae3036e204f0e7a57513348e9860ea98173ff4fdd724471fdc03a1b67cd23adfc0c22d1165ca095b794f9846e8b68cc13d90fe6dde0fb927700b989f3877096826df0ed1ccab6de668c6cbbaa76128632a9582e1fbad318f733d3f87dbe7dcf27ecf94f6f39e82659ef37b8b8aac823257c6993b93a24086e0e0e771f0c7c450589910894c28226b4178abf7dc56defe8c3f4d6a5bc6e370352a9c2a045409b99349c48eef69bea126f01c76bb5539417a35bdd8e35a384f70e3585e19cf9b955559edcf39b5e84e31c5eb97b71ce9dd155670a03bcfc1e67a8a2c22738471898517436cc1ccf4b80926f699747cffaec65d37bdce9c78e4ec6523b45aaf78ce60290bf97e84d7cf7ff9bc55b47bc2565b8278c55de95e56168d2b47f2e9a8bdbc96b4d685b465cbc3acfc9be77cda22da5b9647a5b3a474faf7eec2a108b16ffcd9c1e96c49627e6396c33234499c4d3c756abca054f7cf7dc97bfcad4ffb8859b1b51ed7d69b24acb1f14a5ac6a4ec47a0321641baf8b899ca9b8c49c2315c6cefb2e3493896ab858d9f65ae5b672b1527baec370f21b4dc36fef28e337ca119b638da25ffda2865e1209ef211e913105a074cfcd0b75e3bf5e30fbfd98cef3b0b711efc349ff28349337f551715ce728488ce9530969c388e7dfc08a9b8f134a4a8f708f1e304a4c57f80cb9052a9344531ecd250d1411acf0a59a5170c7e8533e0b0148e2134ecdcec105b22767ce8abb8962e4264917064c3beca85ffe2f7fc953b6f31c36b71f408c40ec242169542057e20a3103aea4594ff092a28d651118512059e9439fcd34a56cddc3d87b6e1e079f9cbb09af589135819b9f556558c18cca87edcb9ada3bcf1c8d6ac78dcc413874c72e251d3d8ff4fbf3075db27dfe84d1cd8289e0699e67d2a30921ef0bcbaa87929b06165690565bab66e45155a123dbbaa14090ae06c55514e49f74a38a533b0926263007f55a1e2b0735ab10bd12b9f4e5deb8d6c7aa4b4ebfa77fa23dbf699da06934268796159deea936915102827948ca5ad04e7f6f905fb50c49654562d2594c1ed99c98c2d5e8254b0791195af53d8aa95ad66709b26b50fae93e15a7149b8b407301ea847e583d2611a193f9a34def43133f5f02daf9db8fd93ffce44e3c3e2717069ddd482e3b966d1eec014e4124c9105164920060b1bb9e50cbbdf0a9c6e9482d12c7094fe8322c709111978ee05d2f2240ac324a524956fd62c0ecf8f83ed57de54da79f55b93ea9a6349798531e561137060bd8ff051b8c85b7fa8c6797e0a2c9fb386a2fe62af8f2d7b707ed665335a29dc36fdc0d38c7dd4045617c8c077cf09acacbb30337b4c6658179107b3ef3bb64c0632c3384c8d139606f184f1260f187fec513379cfd7de103df4bdeb1a8de5f7c6615149d3ae8e95f35c374f914596761516173c9b93fe8871e94b5148370b376589c917c3568e30eff3052a2ea8b49c045b1c7e696822d875fd87ab673feb1de19a738ef82376a2d2c42f9918e226e12770907438bd030b1437cd838d0f4f992f83e61a006ab7edf8ecbce5b161b82950f321486b168c4b9ed2cd046f69ad5b3117617fdd84d1b809a68f9a4ae358a57ec7277fd77ffcc7570efe4078dec3625ea28b159e50fed1761a9cce8fd9f5cd97431887a256888897e426a578e0d99c5464158da2a6f1e5c2805732cb03f78b9c26198a26afa59f7e411d54468f96ce7fe9bbcae75cfd5633b2718ae3b3e2b022ad653c0fbb0c6db7a49df998e7b59999e50f8ce5909445b35b0b441c6546f285804cf18065cb85cc55a631e95acb8f4773e15274cab7e9627e6688ad5993a61a1d31e5e903c63ffef005f51ffdc5dbfc89039b19d2c06155b0d206d2191362caf440a195b9c9b280a8c82a2e28d2faeaf1685aea212ab206102b52d2d8f3ceec15f0a03c72bc74f673ffb7bf62fb3f44953510592b4c1c70b252ce0acf162d79636b468535b3d2b222cb9289a266ab93f387988a371c6b1d9a5889e804965bcecd5c7ba5eb14d828b1452c365e63dc98f1c74c727cdf531af77de9178c19bc6ec3d45b4673822d12a4856685536081e5d0cab18020ddcc2a6e66977dc50165f4a25e805296162d8c070479a30e799f1f4e16c9b1443fb5fcdabac7c2ed57fc4552597d3429afe4370f4d22422b1061e35a8af8c6213b28651c33e2d11a57650b1efae55b8332701ebeadd930444dc1d83246ffcd094c65977d93904b093a33b74ee43c3c9f8c13b6e79099e2b143c6a9c994f03e6e0934673465d2a963b023a6f1f0cdaf89f7df7e791c4783530871605c1207f68e13b75408d2aac7ace12ac5a2568e45adb015793659c9339b223e37a477ade77b88defc7e06c2c256a230e47b7e5a86339f73ddb313689d399e9f845b9ffef9a1b39ff92e7f64c38908428b9fdee1db862c50d88a95ca14f3f32526889d6ccdfa98c3d8bbc596acace8e2d2ad377dcd5bac59b89b95678cab967518c5165bdc3851ab2703f40d641d04613c6982a9c3c61f3fb0ab71c7a77fc73bfac0b903333e0b1540760b9459c8ad09fa44c0700a317d8e0524ff5cfa212df5497a1f5806a36251201e20aca04438503cfb94ca928dcbf1cbc363950b5ef1aef2a68b3f9c94d7c449699839177bec9c5956d425105c3c2f25959339a9add6280669fc41056bed254c820c4ba414c2b14bb1b61f60010ea0d86a175c1453c439cba078282ac68eaf1852607176783b768cdd869109e2e3c6e38cf04fdc796de38e4ffcba997c7c2bee5916521f635ff1d4315973c0062ca4b5c08d21a42877a6280b0569473ecdd42f30bed9aad20366d6624adf21f28455879f484b163f922cee4b3ccb6f50193956dd75cd9f07a31b7ee6d5d69ab45c95e91be4933eacd7e324abac78dad68ced56fa64532b6478d000ceac9092abc03ab501659b156dbc1e7e254c0cc1e00831ab846cf86c2793163c47569e885c62d012bc48502c1932bf77481f387af2a849271e37f5c7ef7a55fda77ff79a74eae85abba7bfc17368dd6ca5491cc781eff9a8736c0228aac0eaa70a5c61b1921528c54513540fd1c2b8afa1a0e0d8768e89b24b76178a4031a6010544c72523587bfe8f47cfbde1774bc3eb8e8543ab4d50ae41bf50b440fcc0d88ac691550e197fe57136f74c104959d45e20619b9aa029b6f0171e6d1b198dae7629950f8c859a0b45960cdf9960051fbb32691c331641044674933710210c11861fd78d0fa1154e3ce14fddfbb55f8fefffeacbfa7e207c8aabcb7dc0569901124ef16b4482f4db17f15c8e2009f59568617cfb2dce83848aacbe077907f9c787c0a0d0a2d091162d3f9d347eb8e00f442f082f884b675ffbc9f2d6cbdf1bd4d64c7a9515c60f2a708708a2f8916cccf8585165cde6ed66971e966ed250dbbae5c49574ee21ee0c0b5b4d73db7649f841e896d0a263fe7c3462059f13599c17294a0313e3384e2126752d6e91171f33c9f8a3a634b93f9cbeef1baf8ff7dffed4286a94b240fa95ec4ef503785e5daa0038ee0ee2a52fba52114f2d9b8b0b1e4f1f65b1be2a0f060fcdc87d8d1314c4ae27fcdc8d749ff90d28a0256dc9225e589df4f7bce48f9275977da63eb2bd3e19ac32912999342ce17465137821b2b47d33d026ae56fe9ed1b2d56c79b2f176d8ead61d9d3792175a31041945e5cc4ba4d474422e9039bc02d4e19c6e821fd03632537d9cd04f6242f80bb13f8c27a1b70e1933f6c87953777ee637bcc3775dd2d703e1d90a220ad4de5b0a60a849dc9199f7ba97b898e0373623db4a241da45eaf57b1085c05e9c6661511c46bc9f3aeb26c294ec65f86f46f45a2ccc05516d25d9755599daa3ff821e9d2c53fff3fccc88e6f46a555493d5c6192b086d40481259fd8a1189a99b4ec940ed628ae28b46cd7a2c822d67cd6a3c0885bb355b033d9294bb6d849ab1d8ee5482b620596156501e241a9c77f1217884f69d5e2db90e21761049e7c76c74fa64c3075c2f863078c3978cf0b1b3ffbec1bccf881adfd2ab4707d8837ee171200c79ff18508db9dccabce5bef411ae8502a9d0dc764b18528df0ac17b5444a185b457bc48297d09d2773132fb32a52f2b116536496a058c174272a066c576358da38e8d2ff257eebaa37ae12b7e335cb5ed016fc56613956b26e29c54a91d646ea74eb0d34fb1658d151b7b85b8ceb710932482e089e037b262894240aa95560548a160ad85155a2c33b842a3d872c764fe29aae08458c83faef1fb8a6cd5c21fc4433c9b24b6c28b61b0d1cf67d7e1c43e133d7eeb2f4cddfef137988927fa7346f83441b18a6b929bc5fbd36a14c9ee50f36faf68ea1a11c5dd03150ec7a77029e7665a2d0a4efc654b2d9b0b4c5e98e745bbe354fbbb8c8ec9ea219a91174df1122b33b1951d1010014c344652b59352760a2f0d365e7473edbc17fd9619d974a4515a03b95445751e4ad71c5bb47c082edf70c251fb434a2603850f8ac104eb69d210b10575039143110641200552768b53ca27dacc64ea5ab1d805c6a2cc1e4370edd2f804570ebda197044767ad603e541ca772e07ede33fea3c89233f0fcf17163261e37f1d87e33f1e0b7fec3f45d7ff7ab269a6217537f814bb1ad847227ec183ddc779aaccbae5625d00b5244c24ae0b626cf0e82671fb3b27115a07df2bdbd0ff381f4d9c1bcab9c09be6f5f28623a9272246779daddf3c2abcbd80258e9095d2be0940e83ba8a1d2fcc4d34be6486ccddd9e78bc2a674f6d59fa8ec7ce6ff4c6b6ba793ea0a130543104f105a095bd32062a4904161c4ee419631a83ad87d1584143cd8841b45125bb7f0c7869bc376ff718970e0d7d96c327125f810130c974b082fdc11799b70661928d8b0b8836f1b225ef194091ac74cd8386ca61efddeff131fb8ed8ab8cf3e4b21e285ad3472ef715f9bbd0573dc805ec27bef776fec112b47dc13e86adbb28a3fd91e455938ae55c8e6afbe48439ad07b888aac0141c402fe70ec923c56fe629f478e2c257ea93a593def851f08576cffc7b4ba2189434e54ca41e69e1dffc4ba0c82cbbd91ce5f73412930ecb9a3e0a20c445165921875ad7c9e077e708c2dc65a4b0bc44226186c70ecee2136a0ec16c8319caac1266eb664c167160e5bb5dab14eb149d8a2e6d58dd71833c1e413861f909ebcebb3bfee1d7f68370ad3d9071695e6140e5994796f70fd149dbc8ac2540c7c968c4c97ba32c2306ce0745459d2825998fbd006f388abc895e281b4d37c79a21fc08f0b3b79a2d2135464f535adc767c595ac3961c25989bb220cbcf28aa3b5bdd7bfdd1f5effb05fe387a487a07b4ad05a881f5b90380e0a514959f58bb10390f36b71dc560077c82cb827edcde97221d8e1d92e466b598b168eb716c207159bbd6e673e0b419c3edf024677d23a8b759879976c17804cfb303d611a471fb961ea679f7b0d84d78acc43e1b157c58bb2638e78b7c5e8843f59252ebe7a0ea3d77c329d852d59b8ee66cb59a1eec36c985394028234d395f4ba54a8c8ea2d2ab2164fe13298ad285061e0af8c3f6285caaa4bf676032f0db63de3cb437baffd1dbf3a3ae10d8d1a1396ad68829072f1b3620aeb8823850c07a3739f7427266c8d82681237972c79114e5cd93712b166751aaed17e60da1e6745a6673fbd43d7348696a36ffbc068c40ab056c297e8c0bf5d325ca9f52d8d09134f1cf526f7fdd3ab1b8fde7c6da3de3f1395e256e382784f708f704fd9ed4aa125ad7ecdab5f5ea0b2e10f0f79bc451557363fe0e7431034322745392390a654b0f71015598ba588653385152a52d72d840d0a97b02916ba80e7870d7fe7d59f2cad3fefd3e9c8a638adac30315bb3101799abca705813e40fb27b8a3fd27d87bc2fa2301301d28728af188a428011bb2eff288470815cc6221a28186c8b161f4c5340c1289a28999c51a431b1f32887f363c3e536ee1b4521e394c4268823e34f1d34def4f11553777ff50dde811f5f15358a3f5129a26f0b55de4f8e9f963e59de230b9f095f08280499e8e926d2b28a7b5044783b32a1559007a4cc813e1b65c1a8c85a2ccc5e852a9fed23b46fd1b1058b152aa3988650335d1db01d54468f95f73eff8fbce18df725955526365593f843102f1511524d1128532760c5aa1c40a165d759b738d1632f2482b5066ff3102797ec7a2b188aab6c55d61d36acb9e13eb65e891011072e51d1f1f8a46182c68449279e30f543f75d39f64f1f7bb377f8ce2771be25f15b44a42b83176c2fdade1b19fa8f25d207446c8ceb6bdefa6504d21692994d6bf9f4a6288ad229b29a455914852997dde3c3525a2a38d89bad46011b8482ce4ee13037c1868bbf3fb4f705bf1557d71c8dca2b4d14d6206420f91833082dc64eeab55c4b8a4802fac10a3bf85cbd27dd84585220706c517389cba54890f146cdae45272bc4151b146bd6ec03cb026d2ead4f2baa10a68487f01907185d83b8017d376982e9e3c68cef33e9e107af9abae36fdfe89d78f0dcc20a2de97be5fd64ab2157b114950971c55d851415f2903a0e45161622b4b2eda6150127fe9c29ca5290a57ba547b46a28658174a74258082c88d97a45e3a3f49a5d43221fa0b3644777f18238dcf99c4f57b65efefea4b6793a2ead843862b761289fb9e160783b1b3b2298e57d11615875159e8cbcca6e737b8b8b6b111351441105b77c11c2c3ecbc59b996af6cbfb8507cd04d5cb88f3bad18b1f790f1b26188ac4b2288ad49e34f1d37c9c413a6b1ffb6578cfff02f7fd78c3db273e6998b837dfc3676d215cb970b641d9537ee9dbd47e2755981eb4f392e2bdb6ca6b7a2d026ae0a99b694be44d3520fe97e253c1014af86ca579a2ca8d3d8a0668d7a32bf9317562683739effc1f2eab3bfea0dad6fc4e18831fcae21045608e9c7f8715c4c3b312a3c9abdbfad2e2d0a04dbddc58ab1adbcf038b9a6ad375b9392ba25ddadb9ef25f21fb12d600c2b6f04f193a51559018e0993291346632690ef1b3e66ea8fddf192c6cf3eff4b269eae89d782c1badab6015af19aabb86527ddda6fe37200f7218d637ec192e9c8a69122892c52e4f1624a93be7a4048e35acff710bdf98b858d1e05616605e126deb4f93f0d3c3f4df8ca5e6fa8acdd7977f549bff4c670f58e9b93a1756952596152bf8cd8854d8125f14f5254f834c4dc87b081b148e087a4298e5a6280c7c0a4bca07147abac6347a2c58a28595258c97626ace46fb66438d2f2678da76bb5f2a42680700b3c99bfde9403cf9482c494fcc8040d761d1e32d3fb7ffc2bf1be5baec4c1051b088f3b89dbc8374cf997d72993c2628fed8a757761f9c1562cdff7624e7ccbfb309fd8ef258c57663dcbbbca6081b4c489a95b85a5d2558a55c2288bc20dee163dd28403e0b94843a9617b4875c3dedb6ae7def8e66068cd634965356254418c102d0a2a460d028b93424a0b94b434a11ca08924b01765c5012f8332ca5e0eaf97f36049979e1c4bbf1464564c49eb1617f4270e1c6b65f7d9502daea58c34c776e120aec9676832a1e5799109e11ec2cd70a2d28903c69b3878d6f4dd5ff88fc991fb76e31ab2838b412cb7c55e5cca38031158ec4086b3bb8fcb0d8a97388e3df9ba007022ab282d478c9fa368694a69d18782a5273d1a8a4533f280222d5a69ef076707dbaef8f2e865ffe2df072bb61c4c2aa3a6e15721025ceb0a2b15184511cde712628051cfcc8e39e3d8330ab396c9b829ca050a21f926a20dc78a298661e5998542ce8a2ae80fecb3665baeacf9a8d318668043ecb7fe784c84cace7ecc3ac53adf74a4e82afbb109a68f1a73e8ee1bea777df675fc90745aa04ad1f3fc98f7a65969cbfdc5c53771f76579817bc01b82476fef4511bbe6f8a323237b784ad1409a498a966e4e4636437dff4478c05091b558a804fa052a0ac4d86ef406cf0fa370e7359faaecb8f20fe2d268230a8750cb95a47d452626a51f89256f2bea95a638c23ac48f4da2301156b64bd49a1543906bd89f892c82b0dca079cbccc7c5a24684162cff4f441bc3a4b842783ec3e447ac710e7ef22781c8a2d00ae0b1e444d6c40113edbbe5358dbb6efa37e9d4a10dd9297a8e8889e6536fdd4f2bbaf2f766f9e1fb92c0445c392b2288976d6e530a4b51d3ce1c683ddf43f4e62f9222fd22c8777711d61e6ce5c9aa5219ed64577b0cdf383ce7eabff656edbed91fd99c406c99d82f1bf942b46fdff613286ad89593ef36695e23a510ffd26ccb94dd950d896f8a33b666d1c1010195adcd403c59e33d6bf9a3c822d029b2c2252b639b5938f5a96f22e34513c69f3c629289a3c1f8bddffaf5c6dd5ff845134d8df0889e62232f3025d8fb97dd4f27648b84d454339f5827f1fd30953754015b8d722d4785c055dc411044582fd8c352f2f0474b3f082dc433d4eee7dea1377e91204f15b7e04325ca6e301934ce85ad510b510a78c39b1ea99cfbe2df4b6a1b1e8dab6b4d02a1c5ef1b2632f0980d2f1c9fc5ae39db89e8600b1369bf081e65bb116d123eadea8803de930061d06c389c672c8f14a23ece8378fa7c331211624b9789274d523f669213fb4c7ce291a1c97bbffe86c6835fbfc134267afbc6217e04d80159ee8664220269c3cad4650fa77190e7ca4a52d25b4ed4178862a93f258f6424a6216773319f7b37c89f9b220ba699bf47a8c85a34c5f975393313db3259463a318685cb525e1a6cbff24b43bbafff9d7474f39164c516135556436485869365c6512c828a9395a20a147397e0eeb8082e548a9cdd3ec5e5f23a790f7c2f1b5b93f963a5b9b08a1381345bc0781e5bf1e671459373e5407b682d0097b46182f88429350e9a60f2c0e6c93b6ffacdc6c3dfbe2e8a7a3375860531a6721470af785f28bee59ed8eb2c14d4ac5d04f783df2f94f585a5919e51e8c82d67f2ad4233cbe01636bfd947389f9f4e924fdb886fe13f0536c8a8c8ea639891d85c15e329b2058bd92a4645ca6f03ca56c13e0cea05a57a70cef33e56dd71e57bbcd1ad63666825dd0ca48a69f0b57a5c0b1bb6025c0b4da0a0127110e35ab1e4f5f1ba39d81d9b9c80d537b60cb12f08f22eb40a18e90accc6715958abb38bd1d6eead96329ea3759c1c06e39de4676868d234c88370225fde3ae4b4fa7513246326997ac2348edc77c9f81d9f799377f49e0b7b36233c7fb1e296319a8820ae93f70c22542ead757dc5c246b71b202dc9b95c25c4e7ef447c117e3e35e3e5fb3a26aba0e01935d3ab1350eeb939dc36f7b3e594b4fb39134e26dcdc79d84a9be10a3fa507e8cd1f249ca6b2d5884810d92e107e79782cdcf3a2f795d65df81153db349d0e6d307e65d4846105d16dcd3ac182a2298050a058e3061484080757c8d825f7cf2ec41856ab300ab09be62a560e9c277297e4bcf49b4d15816d91aadc974d5cca5511b55672612b826b8430613259e9132639f1e8d3c76ffdc49bbc630fed467c5a27ef1aee948c7d3fc0bbda3d6c32b1e774e9aa48306e59fcba7b6394058334d34c4379dadde6f2d32d9cc0621cf0834f5bb27a88add19445503ce142d880cdd9d265ea02760f15339a4238b2617fe58297bd2d5db9e71bf1e8cec81f5e6fc21a272b2d99982d5a3145948d7f80eb7046ecd8286bfc3e1f0b111628b64cb115e6ccca934bb6e4d8d62c3b8ecbeee1a3b42d58ed70303eb34660bc0086b06c6b16ee314e14a7905930be7168b56c037beb589f3066eab0a9efffc92b8ffff823bfe145533d1a9f65afcbb6d8155c6ad9b70e9436589167ab8a72a6685aea212ab20605880d8a012bae506f49cb4c712b307f74cb7d9573ae7d8719d9f6d3b8bad1c4c14a3856b84724826d04b2028803f92972281ee9c2ebb4228b7eac5f8a2dfbcb31bb64b97e1a0457b38881f0e07dc2365bb49aee4e986255cecd3078bcc7f9ded9a245a91264e1b76014dd24a73ec461c816ade9132619df6fa61eb9f955d1e33f797a2fba0d138f1dabb6b5ad3dce85a48b2d7ead1e1445195c5cf9e84ce91d2ab24e8b02565cbe54a9d90641f55aecfa3509b65efe95ea39cffb9dd8af1dabfb2326f287215a4a56e820f2d276e4f18d43fbd6215b91e8deac27517888bfdc85b2ba660b97c95ed3773a53e416845373f2d1d4092e1ecb16401b065bb044586119b37092af9bd81633ce32ef5a09e5143c3f436698f0ca238374ca84f5c3105d53fee45d37fd9a77fc813d887bd7f219af04b145a48bfdf05b74edd6087866fd7263fa259e4ac1419a6721680b42a5eb74b7841b088a594853048826907f74287e85e27320fcb667fc7d69e3a51f4aaaebc7bdca28b451993ba448b06583bd048a2c1154ce1dc625f7e64516058fbc0420f782eb58d23f56dcddc09618dbcc10329d043b249a700f5baeac433e7c9e93c1d9f889d292f0d9eb45772f894c184f9a60ea90691cfad9cf4ddff1a95f3363fbceea5a8b1622cdc1ddbe0c3c73d7e62eac80d9ddcd48db1d5237564551960bb61c557a858aac3e47640245801317221032a1d50778a5ea64e98297bfbdb2f5b28f262bb6d793da4a9394864c0ca125df130cac78e135e6bb05a5e310aac9c77e691ae7f8a9acf5cace7d25aba025ae88688f9cb04a7db64c25521059d1c6562cbb8fc7f15c720cef31c3c2796c537c88c0784e66211b2f6a1a4e281ec0a96cc620b41e33d38ffce36b276ffdc89bccb1fbf7c24fe74b3b086bc42977c5849b2eab2fdf2c8fe79b0641d0579f445114a5bf59be25ee00c14a83c24004089730b8713ea0bea84d4a2b363f52b9e85ffc5e30baede6a4b2368dcba3260aaa105a76b2d298e2c90f717df4cd4bb282c8419124d72dc9192293f76101972e53424070a51edf178c45b859b71614570ccb4e526ab38b88b94c60c93929701137895f1ac36764cac9a40927f79b60e2116f7aff0ffeef13b7fff57ff6eac7d648009d869a8f3d94720f0a9ec54ffd9896942c5f645b8ab27890863401290ba6e025b0b2105ca5c1b144f691c2ba5c799d29fee8d607cbe75cf38efad0e6835175034456cd34f8e91dceca0e311398d0f85ec8024efcbbb154bc6a8bbd6edbdac51629fab1fe288564c391093011a6d97d727b39aeca8669c3e5b96592526e404c91580e8249cb19442002622f54cc6f1c4a77140ccb2099367e3466c2a98326def7dd5f9ebee7f3af30d1d410c3e8286900e5c84ba61865ccb38b54fa068ac16c5551943ec6d61acac0d09412505c9edf471fb3f6fc24d876e5172a3b9ef1deb8bc6a3c0e474c1c0ce13238793ac515a76de0c4a3105df60811974e60f2af334e65215da8a05d5e5058256cf9cbb689f8cc5ab52c36240e74978951f9391db8d0520a2d8a2c2ca5452b6bc59281f999c8e28cf4fcc0b49fd421b2264c30fd84091ac7838907bef68668ff8fae8ce3cece089fbf8ac2e354aea2f4092873fa2ad16acb6d6f5191352030db4b2b8fe47f6e043172575f15067eb9365ed9fbe20f94d75ff089747873232aad32f5b404d10431e38522b0ecf8282b6a52881907d7a4658a828883a2ecb76f66c0bbe1ccbdefd7125b587342cb834082486263829742ad66e7b49ff361ab158515c5961d0746f1c7b15af0217e09cb352f413871c39869ce9f75cc9889c3bbeb777de6bf98e30f9f6d7d75065e9a3c79ac489356b1c99e4477d00a4739539086ba9a669700166c9af07b44bf25969e53a494cab8d0282e8854ac50035c7a100afdf68b8b84a39b1ead9df7c23ff647b7de1257d6c4536955440d5bb438d89c43dc7d2a24089e849396ca6777ec53e1d55264a51059cd6e45f9cbdb62c76d255041b1cfb15726fb1c110413ee99bd8710551057410a81c5a5944dd6289e783b138aac187b70b0dc5e082d891375a09cd366291b25ec898d091ad3a63479c28413074c7ce4de6ba307bffe32138d0f8bc7a5267be6b6ed8d17c8eba30baf23bf5414e574f0fbec934728976c01a9f40415598b04f5286af56ca310f011dacab4b944ad9adaa61ca95efb8d60fd05b7acb8e09fbd251cdd7ebf575d63bc70188287ad581c75c641e858b29893962c0a2a2b24d86ae366bc1731d1ec02cc0496ac413ec17f8c6d2ee9465c6b9574f389614fcacfe6381f7cf63808f757865dc107d7e9c6b70d6dbce88771c05a60c76bc95b8fb0101108a6c621b48e98e8c16fffbbc603dfba018268c9c767e15e24894cfa95894065064e7c2bcae9a2a245590cac1794bec55618b6e2985979a4a2b25ca7589fe10571b8fdca2f8c9efdccb79beafaf1b4b20a7aa6848bc23e7613662d582ceaacc8098ccc719eda59ce99aa45738a40b246bd41a3b862ab15c765c990780a3477efa4ecb4ad59d6781ea83984cbfb6b6514fc2781359c844286474367b5a6936077a59c90fee18673b00d2e6c4c1a3376d8a4279ed83a76dba7fe207af09bd77462fe2c08add8b6b2650ead15a54fd06e4d65a9d0b4d45ba42a52fa13797b0c4b7e8f4f841685078482bc0dc749cda904fa14cf0fa3f0ecabffcff0f627fd9f60c5b649afbac2a4610dd757926bf569b84eb6155991c935dc02e8228ed5a2c6b0ee168a2f0a2cba5bb84207dbe2d4445abf5ca1c4464b4eecc0fbca38d9306d10f80bb596cf40f463bb1d33f1e6d9315bf20f41727c96174d187fe2a049260fee1cbfe70b6f488fdf7f5ea3512f4b004b84977feeb81e6a47f7f91f1b3fa50fd027a52c09105936f32b3d4145563fc30a14353bf3507b3ec28f1736d5f475411d0cad3a5cbef8956f0ed7eff97bafb62631e5aa49a41b0e50ec38d193b540f1f33bfc144fc28f3773bded179cedfa7346b8dff5feba5b451145a16afdd97df8cbfb8c13b26b32e060773615327c9c8703dcdd00791adf6e2414345c6bc603f1f25308adc671e34dee33d1d19f5d7de2477ff17673f0b6a721be4b2388d3d4f658629542549cb24b7342ab507477c6f75969a2c068d9ac9c314cef7e3fbd653e806846ee73520ed2a6d0c07a30432eb0af4ad4475f535ab5edfe70cff3de9d56d73d1655d79b28b4b3c1b3adc6631f9d0807d7a5375b583944004134397124e3bb60d2c2c45bc516adcc38c85ebe61c86d842f439c2868515679a601f102b1c4f36663b66cb722fc348b3239139e06bb316d16a3f8f338bd03855632654a1185d661533f74d7f3c77efa37ffd58ced3f2b59826f1c8abe4ae39217a5109a7680bf448cb72a135dbdc63d232ee5b17411dc83e68cef72fe2c2e45c1fe6890f815e361297392cf4b6e3def46dab7bb45febc4cdfd866e1a8f488ae1670cad2c3ecc44a9e8201390a86251c7d510599eaea73bcf517dcec6f7ff207e2dada8938a89988aff2c1d28023a76293c411f415050f2f9de589153e6cc18939328d8209ebd44a81082bbe41e8041697105d5230511c85b8879cc68a420ec7f23cd8676784c779f0d778755803a2cb965d9cda8167b6c62c658de7e71e2ea9bf52283d9e8653438409e23075d8f827f699fa81bb5e30f9d34ffc077ffaf006783b4370b64654361185a71511bcfed40bb37870dbc6abfbe0c4b2e0dd60f46c2cb286b76ed13c978f04d13ec37fafc95590ddbc27ca22e08f213e276724f7dc84f6eda566ae1f0799a0cab6249ed91a32980ed6ef192ab2164db113ab3c50c410796d6032955f1a9a4c773dff43c1da733e110fadab27e551938443b6258ba241848fbdf6008282a3b3ac688235eb6fbadbf62591402890686eaf07e5e1652d5a8917187ecac7fa6637216500050b848b07a1254611c3b710b99ebfd53644b69cf19c5c343bc4f8c227cc8e9b8b8c1f4f186ff288f1a78f9a89477ff46b53f77de5e78d699432dfa7476adf6d6494bcb8757df94279ae02ba3bf0bcf6dcb642e05af3ee748d4e5780ca60c3291ce6cb434c5bf9f4d58bb4c673f62e8f2bedb0be510600b6dc705c103f23635b56068bf2aaad0f962f7ef9ff346bcffeae59b12d4aab2b21b4cac6c0828013825a61c50913c26c4a85a6c092b700ecaadc1fac53838ac8e23af65b371e227bb08290d85dd8c4dd532ed952169940863ad8d62c8b3b9f5dd2a7489e4c5cb11529c6338a131c1353641953f112536e8c9b70f2b03779f757fe6372f8fef3cf747c164f55f603b92f149d24c6f938237defc9ee9f5404dcee7e1164055ef6ac0a485641ca1fa578e0f93413ad8a19e55474bf84533a821d736491fac35660c5ea0b3943fcd567df553defc63f48566cbb3da9ae49e3d2b04982b289396d022f9a9527fc0510391459b622654b14ab75dc1d291be996919595b6a58a7e32b7cc9f7433e2383bae2a3bceaa32864ac9949d236bf3e2f9b3e720dd9372008283b1eb91c8e77e621c9bd86e4b0abd209a34c1c401938e1f3c6bfca79ffdcfe6c4be9db81677f822c171547678f43e1285cce10561c701fa32c6bc0095827c8ea807a042f492a4d86fdce6ba78062aef2abd03e9bef7997e19636b1965e0608502062b73797e1c9c75d5e7ab17bdec3792e1f54f44436b4de40f997a1cc8479a39f89c95541c65ad36143d3cac79176c67a1b30442c44a19889eaca98bed4d6ecc90ebf2f3a55eb6e3b4e400981df5c576ac18820ae1302c364dc91853848e639d5171b1f18a533af821c2e1382f8ef9e29c128083e1bd1842ab7ecc34f6fff897a66efb9b379ac6444d762e9634412ca2304298294f8a7bc281fd214ca64545dcb34b2d08ddd5125614171fc453455681613a52eda22c0415594a5fc1f9b3826d4fff87f0acabde190fad3fd128ad34110484b454b1e083a860d71805467b21283a0c7eecd2997d032fefd7b548610d4b661118bb0eddfa1c70ca08fc15e3b17963d83416cc3e85164496746cb2858c5dbb38364822e34f1d35e9f1fd66ecc16fff6a63dff7af9b9e9aaa4ae08b00822d4868147f6c1541f8b60b35c4926bbc2a6bcb90beb974adc08b0b9ecddc854041e9971f16834a5f25166591b04f6b00f1c2f274b0e7851faa6cb9fc83fee8a6696f68ad09ca2b4d50aa99202c414af810336cb9b15d78f620db30c0b62dd792e52627e5fc51adcff1b85b06718263d9ea63051797cc2e2cb0f24b8485e3dc5c5d24937c33cc559aeee3d5b6e0831b22c0ae43b68bf966da84d10913c493c1d8ad1f7fa7f7e83fbe18072eaa458b15401afa31bfd1c85e395ea94c6f81f3f38d4a9e577e8567fe9721bcf1fd00938d524038f03dd7ad2b145c1473da92659ce57b8b6664a52f0987d73f5ebee0a5ef0a56eefc4e5a5b9ffa432b4c18564d10d881f06ec0378a3f54ab4e60d1cd9a1d67e5e490c30a2d0f02857b08679567bdcc59f4b964bb9088341c4f27f7b1692bd678048f7366cb3516c0342770e4d33b22b2706ee9cee338324e2b51377e7cc284938f9be4c8fd3b8fdcfc677fdeb8ff2b3744d122de384ca210d7408d2948bc1941f61c324afc2411dde5eff2c4defb628338b26954e9030a2eb0941e93af6114a5aff046363d129e75d5fbe3a10d87a3ca6ad3082ad012814c1115b31b2ed73265ff5224d9f154d28204bf5668e52b5d6c25d904af59d9e9c630b925350bbb0e39b3bb0b3d816799b43443ce42619539313c115b88929de4dc0a2dbe1169fd725c5603426bc2f8c931e3371e3361726464fc9ecffe8e77e4aecbf0cb7961793589f891c7d0c6c58aba40a6bac0561617771dcb105ef9f2bd7a6549605e64beea1710574df33d4445d62291d4da07f98b757ab63ab878411c6c7fd6e7aa5baef893a4b47632f56a2649cb22b022a819db7a944054506cd9b6298a2cd70a2534572c56725109d93156ee36caacf0925de05fe6d2e2a6756398a429a8e44c80db106c7cd331e003c176126763a5000b6ac62fa018e4ecf1c9b409e2ba09e3091346c74d69fa8049c71ebe70e2b6bf7ab3997c628b1c740ad2786a0831f7a931095bcd1859db12c77352582e4f58d9b0ab27db54946503cac299059dd23554642d92be49a9f2badce0ff8209ca23c7abe7bee8fdc19a9d9f4f86d6c67179b549c30a2e1d262d550e2b6cec3c5858ce48fa1421b68b90778c3e64ce3139864b7727b1968dcb92e321b6668603b25b2ead581478780cf25d4371e3f7156d78161ccd39b4b826e3a662ec8b4c006116427485f1b831d34f98e923f75edf78e04baf308dc921f17c32922488f9f0d952c7e8323a58b6ba288b07ef7eb6da158adabda3dd4efd411004d1429f55419ea9cef8de43da6a08e554f44f4a45f65e26d9ca1fddf86865ef8def4a6a9bee4e86d727a6b25206c02710301cdc2ead4e4026066db648e5a10777b3207c70e328b8d802d62a9a2052e4c7205bc16c80337f1b7223efc003edf16cc59201f80904178c0f86ee0cc6b66665a22d3bdef3f87dc42948c409134c1f32fef86366eafe7f7843b4ffe66b8c39c51b87691c32308ab904e7628b16c397716ab0220a2d76b466ab8ad20f687a55168c8aac4522bfba597f16140a018e0f02599fd53280dd401b9ff4ddeab92ffa3d6f74db83c9e89634aeae81ba81d0e24075dc0f0a2d7b5bdc32135bd8b0dd8904e28723c49d719fdc461e90bd814844a8d9f15cd6dabbe0e01fc7da92d8b686e561ab957b9b91cf0a21cb3a22c2170fa5d529c4265bb4bcfa09934e1c30d1c481ade377feed7f8ff7dff6b4249e7f46f8348942df2fc59eccc5c5aba1d0cab7c911c6595194d32146fe3bd98f15f7eb76d9fcca554e8a96b6ca40e005a57ab8fbfa8f57f75effdffc91cd87ccf07a2333c27b21c485d524f9f612d799ea068133238814e14b5d99001271242f79e596027ccbbc5979b321b44e42bf56a8315c8a354efa1e4045b18b90bdb95660b1738f61db88701e2dcea145bc18b2a81189d0f221b4e263f73d6de26737bdde8cefdf3ecf4078041c876588ac202ce33c2514f4ec804c4d23b19ff461c1efae42519433438594722a54642903831796a782b39ef3b7e18e2bdf9dd4368ea5e188b46671a675d7ae244208b068a4c8f2dc3faec3ace8e21fb65cb1c5893b284b9c34e13e1b065b0d5b7367b5cee1de32b44bce08df7273bf802975f84f440f8c5d999c9c5406e5235bca7713714a3fe644a58909ebe3f2e99dc6a1bbffd9f43d5ffc6533f1f89c03e1bd986f17b28792428ddd909efd5e628a78c859716e9c2f8b4e4fb177a2bb4590568aca998234a4f5a6b26034b12803855f1e1ef377ddf0c160eddebf8e2babe2b8346ae2a02ce2c562c592b42e5151a1d2e518ac99709b82873dc3761f1ba8ecd82e0a2bba7183428682c8092d9a5db70d5a1035543332e62a0b875d856cbde2d27553723ffc27be6f6208aa38c6713276cb3761e09b7229b403e11be3c6d48f0527eeffda7fa9dfffd597c363d906902399aea67154e15b8c0c4bcec13e48fecfce43dcb2085889db1de669012c1ca8c80bf484943c7df8866ad7f297321b1559cac0511ad9b0bf74cef33ee85537de6baa6bd234acd90f494b8b16c50b5b7250ee40c87050ba6810570c89b8714a84422b5f425114b937055b466cf7a3155d84ce722c8c2d4876b055162e601cacc8ca441f0590ac228e386f1263997052d59229f921428d4c104d9860fc31e38d3f56997ae43baf4e8eddb7378e227e54b14592fa5e9206fcbc104fcb73cbf58ad0b314acfa66e45cd43a0a850bef03ade8400cce7cae8a721298c7f3b4a5f3e227f8014645d6e9a049b6f0f8ebceff7175ef0bdeee8dee7c2c1dda60d26008222ba486620526dd671439acdf5b05125b92287018827d134f8c4248c66ad19cd07286a3d8922502cb0a190e30e78cf2318e8da505cd0a1db660c5f40a17fa6257a2156259368408a32bbb37e59b382684d082d093d3fbb0d884105ad5fa51138c3d7a49f4b3cffc9a37f6e09e5ceb0c146310c16712f8381ee141723182881eafcfea99e2bccdcd388975254259a5e3ee5561c9e2a922abc03851933dabe6b2db48f9047353b4483993596e5fbfb5bc0d14852f7014e574f0c3ca54b0ebf91fab6c7ffa1fa6d5f5e35e69154aa48a089684e20a42ab35d62a85366101852dba4360c99b78142a596b16fd58b8b4c7d8653baef50be165e3ab9cd8a2b950a4f093ee46ac4b1316b3220b4afb19672e3d8a2c8a371491fcbe212d88a660474c3075d0c48f7eef57a39ffdfdbf36934f6c868faaa98f8d7871bd6a87fa23749c2fffe664be25ad2858c1d79db635543cb8edc5bb078e22c74d999fa23cb77c3c28ba72c28b53ba685ddf23f4c62b034b50193d56de7dc387cb1bceff6432b26ddaab8c9a240c21c060811538711ca110b242846594e785c6f3616c4962b79decb1728a459868028833b6398951b0c1adb90f505825084c7c60874c80ca25f671db8eed226e458446ceace062388c035bc9d85a46d945571188f57163260ffbd1beeffeebe49ecfff62fac877ae4af7ffe019e9b107f798c674553e069ddacf03314c7b5e7735c5216b10e82afc95af28a70bf254e113906bcdca6896384af75191a50c34def0fafdd55dd7bfcf5fb1ed5b69a596f8a521e397abc60b295c203c28b2e2c4f00d3f4a182f08218838762b1b1b85309a422b2baa6c119b156039e1e2da63ec1e36d78b24c2f16c19a3f0921d19cc7a9e8465dbb7288ae881e20fcb6c5dde60e4bfd8b67c31ce3e67849f3e61ccc4e3c68ced5f153ff0e53725777ff63f9987bff573e991fb9f6c920827cb7a08103f9ed7ce286fc55651b0f7a9bb11626b56b6aa28cb02fd51d15b546429838de725fe868b6f1eda7bdd3bccc896c7d3e10d26290f9b28800ee1849d010550ca0906654991d56cc56a8a1e661308309655102d3efbefe0db999362addfb7904d1cdbc556281ccf60dacde2335459dabf084b5ad55ac28dae6cc9f2fc928d17e383f348eb5bd430c1e421e38f3db2da7be2d61be3c77ef84be6b11fbfcc348e219a75788a10542c61326cdb2d59345a77a31b14a56b475194e5818a2c65e081706a045b2eff6a75db15ef4d6aeb4f44e51590496513856cb9b2ad4c710c8985fadef7ac9861571fb7f9869fcd2636ab381144a1e58c03e2d9c264b172c978a188236911c376dea40b104b3906e790d62c59b2dd8bdf2f94b38b3b25088fa120649832ce8be172a2d2781a3a6adca4638f99f4f843c63f7aff4870fce1116fea8809d206c28c5ae1516821c0c2fdaa2d587414455196125b73280303e77662850d383b8192e1976a13a53d2ffcd0d096a77e30a8ad9b8cab2b4d1c0c19ca90c88770a1d8e2e074d4fa94385437b224228a08c40efc58a80e5a2d43ec86a36f69cd92fb0f416337b0af65edd027c596db959d3103e167e7e67e4a30cec965fd643e6388bc68caa4f509636069e3b84928be4c03822a920ceeae84d10a10d0cc73f41a9b5815455106111559838a0cf0d10a2c4f38bae9d1ca852f7d77b0fa9c2fa643eba2b434028155324910427db095c83371623f3f43b1c5aea5c5742fcd9450ec5e64f66a378637f7a36977112fd998aa1881dba9afb2d630f876e3b364807b025115d7610d7884d14d5aadac10d46e3245599ef4c340fd4186a5b532a8e820df59f8235b1fac9efdfc3ff247b6dd990e6f48a370d8c441c544d9370e65f24edeb5454f77c072cc1e2b12282bd6da8b3737f0dcf99156ac6c9dd8ed0405231d6cd7a4155bb2d776616622cb4131c5315aa97ca5303379b3d00a2c792b313b970bd35a01d00a40519401464596b2ccf0d260ebd3be3ebcf7856f49aa6b0f99ca1a9348b7610893a1e71029acfbdb5ab1d82a05b32288eeb6eb90937df263cf0edb7a64db9b2887641d6e79b3e3ade887fe79548be6db7f1048ec99b4db6c578be40d48199ccf1d881bcfcd38b205ae3dbe6eec95d56a3c97cc9c856dcdf28a7226e81baaca62d01257597670207cb8fd999fab6e7ef207d3dafaa9b8bcca445ec5761dfad99407e293ad3d6d2a48e413c76fd92df9b83337d86a9429262e685690b5b69dcd609603cfe849b831c595b446593f7cffb1394e0cd0b525b4b00e6357a7540170776d55f6ad44c2ec9eb7e5471fbdcede7ad08aa2f42d2ab2168b8f5a58e97bfcf2f089a15d377ea8b276ef97bddaa6280eabf281661158103ea97c42273201440e6595b326a975999985b05ffcb3bb8f7208eb103dac2d9dc97ebee92712c809a82c499de407b208ae346bd112b586ff59eb155bb2888486f5585abc1c2ecc8266f52e6b1edcaf2e9f515194e58c8aac01859d4a58cc5f6b2bc65f75d6ddb53d37bed31f5e7767525b937aa5aa7c0227a19881f12ec6103594382da398e16d6d09acbc36725d769069f2975d8356fe586b55f15c771b2e2c0a377eb2ae15b67d2bd14aa6d4772d5bb0e639ed3efe652b8d6bc5b27162186e0a0a1c2251b2ebc50192b6794f941c7a57146500285a89ab2c11a8ebb5903e159e97045b9efa8dd13d2f784b6964f3616f682ddc42dc388a18882b2f96290f647c14bdcbb82c5131f0c349414bf0c901f3bcd974f3e0553afb4c009146e3a410fcc44ddea4238f6289ad650e0aa32c5c69e7c269ec0364f71fd6a401d50a3d4a3fd7562503e15d9c3cb68f596b7e649ac6563704a8434978bf8a9f2dfaa84b53519453a0224b59d6787e1895763cf373c3db2eff805f5b399d866538428c4004d961e651d6e294491e277e522b86b8b4d90802ab999d6c8b9533f7669f582683e86e43b26289b3cb5b91e4c416d69d261241d6b2e69b87dc06d28205b35bd8661c1956161e3c5a73224bc46251b2bebd135da275c30b8e0a2d45190c8a52d22a4acff04ac363d55dcfff737fc559df0c86571baf3a6a62af0479c5ee3754761ebbe9b8eeea67649be63ac5d34cd1c24338bbba182a4b37eb7afe1809579634ba633b13416e8c95f5c27d344ec9d0945f36bc84e2cab686c9a108c7e928d76d69c76c3174b66e898bb8e7e3d23d786ec423131036262ec69d87e3b1fc3e1953897b44f5ae284a9fa3224b5158f9ae3cebdeea8ee77c20ac8c1e0eaa2b8d092b1026943214271405ae6eceafb7a052a037371f7b6a670eb5eee262f7e799292fec8cf379b89b2e8c85b46f89c8e2b921a6b271636c6513d1c2f3d16fee1c4e68f108dbd2d516811e333bc6dda30f5a8a5464290bc6e57507b7736e9a967a888a2c45219e9f843b9ef3b9cade57bc255979f6c17878ab892bab503c95218620805027731ef82449c45849db22cc8a9c96b17043b6e207a8e1c11905556c0f9801ab7a914dd2b2c41633b64cb9715d5650b5ba1be91bfbb80e1fae10e5bef64296b83811c6612e3fbda255092458744768f13c8037b5f0209e5a362b0b269fd7ddbadb4679a522ab8768465e34bdf9e5ad741ebf3c3c169cfbf23f2def78feefa7431bc693a1f52609874cecb395899fdbb183cee593355806ce38983dc13ef9c83492077b7ac420b4a4b0a354c22634058516cd76f8d9f157361bc2078416658774050a3ec2865ecb8c1d5db294a3ac51a778545938c68ef9ca5268d6b245e82603f885967b2fb123c81079897bd74416adf9b601ef20e710732d8a335b167b87ab1c15e554b4a795bcb822d9b626a81ea2224b5172f8a5da7869f7f57f555effa48f87c35ba7629911be0461444983fdd04e6118a07246e1c5515bd26d47b1e399200c217838201e32093536c756f9d87663ac5889e75bb638385d96920d5d56a4f8c85b0b4a917639e2b69be22a5b772d5f36d679033d198fd582f7ca2dbb590065154ee09e9723bfae28fd4696aeb3ad567a766e9abe7b4b37cb3845e90bfca1f58f0f9dfbb277faab767dc9abae4c93a00aa15596c94abdc0b7228b33aa27145956bcb08b3008ac7b9c24268eb306131e8342aebd95044788b576e4441070ae4e58e5c5d57c4526dd9bfef207e4690aac79f6779466e4b2654f689edccf9e8da20c3248e3bdc8ec4a868a2c45998597fa6bf7dc56de75c31fa6d50d07d2ea5a139746905b388716c41545535e10a19e96ba1a460943a125ee2630f91140cda22e958ebeaca18a7ea51d2ab78eca5f86e4d05f7b16851f84e3c272ad64a4b506da55dd2c4eb5bf83e4e296b05bb34b950004314fdcc30b57949e8062428556af509135c8e8ec93a70f272addf8a46f879baff86052d93ce9975618b668717ad1a8d98265e1f703d905c816aca6bb741332188e39b58289e6a7010c220a07b01585624a8a3f8aaabce58e71269d7f0893c2ca8aab933c5e69b162c05ce66d79932489ef5aaff2cf505106184de83d84a5b732a8e8af9733c22fd5262a7b6ffc90bfeefc9be2dac6465c1a35915736515c9771e5012a6b3f6057a1f5efde3a24acc8b92e86fd52b18b80b2a2cb892f2b9ae49019b4e45026ae169055657254d0146d27e354fb9701eef9288aa2740a2d6915e524f8a33bee1dbee8956f0987b7fc53525e9bc641cd34627e2c273109720fbb04ed277152115934aa2ecfc74e7107d28804372c2880e632fc9d6d73a9af7637a7d2dadcf986e25c068f99876c5904bad4e20a41d53c4f5300abd05214a583a8c8529493e2a5c19a3db70ded7efedbfca1758fa7e5d5691a544d1a940de7c24a29a600abe984528b7a47b621ba5079d3cd8a263afa92e19c2d9cfc511075d222969984edac857dbbd0da4c9cc3ac1d030fc7a5c0e48d0415588aa2740396d2ca8092a66c6b51ce18cf8fc373aeffe4c8c5bff0467fe5cec37e6da3494ba31051598b566c3bf7f876a1cff15910309c538bb8f13f4e60e55b9efc9ccdc43e36cecde5da9fec19e8ce494ef3268d64624d6145df1c9395996ce742692d0b4257bbb56d8ba38a2be57441da41b6b679b64f84ba26f61ea295f000e379fdf19db67ec00b4af5d239d7fd4df5ac2bdf9d8e6e9ef0aaaba19b4a2860392da9c58a2c1f6ed9acf099fba211d1c5acc9715bc416e8aed3afd58a451696850b9dd1bbfa8246174fa528cab2a7d0656f71d1827a39e285e5a9ead9d7fdeff2c68b3f93d636468d70c4c47e25133fd40ab6b588ad59f2591d08ad196f1c2e94d6a4e4c065d139d25c536c9d3c1bbb7622cdec026e64eb4e50142b8aa2740a2d61146511782bb73e543df7c56f0b6aabeee0db865e5041ad0d412582876ac683ee494d40a1c4ee3a761be61a6ae463d38bee1d6336cd675586079b1156bb1f0bcf673f706d993d466bf9c06e1e58201fd6f6f0d4b2c9489b5dba8ab200f0e3c935312bca29995d2a2b03838ec9ea009e9706eb2ff87179d7756f0f46b71d37b5cd260d2b2264380e2b496c2b14e7ce22ec34e4c82a3b2e8ad228533939e13337aebbd1b68e59b350b3896e732bcdaec3d99cf2343d06faa69b31e45d2bf583a8421c5b0f5c291c2acc9585a295f000a363b23a841fc4c159577fbabafd197f92d6364c249ca8d42f41645150c5d242e273503abcb2ab3090ba1dcf03eb7e9262297be8d032d11a2da3764a50cf367554ce5a0f151b4df2eb2d8adf72d53d198867e1b325cb559045ad28b5022f3c48467d31e0ddd137111d4454642d12ced19dad161e6dc9ea1c4165f498bff7457f1caedff31933bc21f12aab8c096b1041a1bc6dc84f17b2b20cc240ba0f298d522f8618e39232892d5e5c2622a6dc9b80dc67f7cf3ffd68debd39f33bd59730f3289ed96bee2b1012275e73f77e082476b6f766570f2b490fa2b7a095a56d12550a471004f83da5ba4559185a092f169d455dc928afdcf27065d7f3ffc81b5aff88575b6bfc72cd2441987d203ab22d5a811df3c329166693c29dc9a9cd329135db4e45969d9b03e2c9ec1317ab75ab7b0290636932a1e5b6c58a08d28e8a2c65c98028ec5e465366a0226bb16862557278eb2ff86165f773dfe1d5d61f4f39ad035bb3c2b2b43049ab14fca02a67aba289db8492135876607a66d93a1b78dc8b6f74392de618abe512ef721c004f81853b20b78042cb59917083f1b5a54451060315598bc48e96293e5a467707bf54990cf7bce82f832d4ff993a4ba662a0d4720b2868d1f84f20cd85292c676403c4769b16b504c1a2ad88a92405751844182f1ad37acb3de7702807e39a0de0ab4f6879a7d5c7a06ccd2365bcf35a6dcb9ccddb2b61c989d33dc3d9c7d2fbb8bb47c3a65ed1ea2d217145c142fdbdc5e0434230f2adae2d63582cae8d1ca792ffc80b7e6dc7f30233beae9d02a634a550899c07649c10f5bb66422d11c7ceb506af61966059708308f530df003d4568c59da0b73978511f68c47ce8f4acf9d0496afc06ae1442cad08b092ce57d458d7b259510600cdc8038bd6a4ddc41fdd71dfd0852fffed60ed39df31b58da9178ec0b162d284b3c007260842597a812f86da5d3e22cd6a558ce22a6bb54a6849361d04dc02f8f729c09a42ab9d5c3616a1d5125eedc3e79dc06a976a4a6f9156cb6c8c98135c30cdc48ad2e7a8c81a60b484ee269c3febc21f56f7dcf87b5e75dd134975adbc6d08856412882b19a3e5b36da9eda9b0954a5aaaa4f5428cb07b90e61a5a02af259c664a2db735d7d3b66e22b4660c8687d0cbd69613fd205a9cc802cbf11129cac0a1226b206137086b689d27ababe07efb1b2ffb6679cb53ff3819d93cce81f0a654c183084c048b13df44a83fad80b2cbd4b302cc70c0330419977c681ec767496b162732853f39825032b976a836b98560c464ab25a89aeeb0e50cc44ba1cb3bb666b97159584f60dae0a8287d8e8a2c455942bc527532d875c3074beb2efe883fb47eda94579a24ac9858c41505546a9266838a5d72ea26f7569933c26e438a2d0a2d19ae85a3652976f2fa97424be6992fbcb0eaba90286c99977ffe30a7aa1545e96354640d189c785226b7b4335a2a3da034bae9d1eac52fff5fe9ea5ddfaad7b6a751692d5cab103d2508a720679e89f19412c80c0e8a77156cee0d339342255186b86e44d7b53813bacda555baad5f4e832ea6d2248939db7b61bf3bc767ec0cf1d4fcab2c09484b7d50100c2e2ab20616cd57bdc41fd9767f65e735ef494b2b0fc6951526e624a5104f71cac94a2184531f9529e74362e56a8f493d7615054db1e57003a29533238ee5c3be852df3da445694392b0503cf078fa7f04dc44a41509135b06829d05bbc34dc7ac59787765ef5ee6068fdb8575d6bbc70059c4bc6f34b105421d643e9d2e3370fe318956b22933acc10585cf77cd7bac5bd2ab64e17548ebc8985cd1714576ee03b9ebb3e684519005464294a87f0cbc363e5dd37fc4579ddb91ff386364e707c561a54e463d29e5f865ce247a529b23cc3d91ae2d88eb9620b9788abcc7c882c59324cf8e7c82ebb3ed36663a72345709941c82de32c1fc7315bb20afde383022b135b85edd654fa0ba4a742a7f941474596a274108ecfaa5dfcf36f2f6dbdf4d3c1c8c68609468c17d80f497b3ee7ce2ac157889230107185fad50231e5195bcf526009d210e31a38e65455c0ba5b51d59c0e6006f31dd913fcee5500ac6c9af7b280306eb9f869d9ac2c09454ef3cb01cdc8034ba1aad2658dbf6ae71db54b5ff95bc1c8e61fa6e59570a84a4b96082daf844c8875eb134b27b450e1d225ab78835c3969051456dac6b3baa91d9c6c61bb15e7e6729d8c1c5c6fb77a4516b1def584e5ee627169135b8aa2f4312ab206152ff5d2941fc4550a40ea8feeb8b7bcf399ef0b6aebc783da3ae371a252df8ecb924fee70be2cc98eb32b58d785e4a0b66ad3577330b787c2d5dda7bc8ea5236bc9ea0bf512c771395b5514a58fd14a7891a00aec62b57006e82fe18201d5bbfdaacf96b75cf8717f78c37452aa4ab72145166715b0330bf099214bb2ab90cf2ff54c9a60210d3fade7b96091251e7170de48ef5a9266c3a6bb66db5b67c13d2d6cdecd0b692e11d7023d24a59f417ad27abe87e8cd3f6358472ce4362ea4cc3cd3729585736a38383a7bb45da9bc948511d6561facecbde1ddfe8aaddff02aebd3463862d2a084e4c3291dd89ac527e84372b8a1ed7c7c586207e7cb22adbf76cdc1ddf9d463c30273a5cd1e97b932f797fc08e0b5f202bb23b208eeb25dc9dd0377f6eec56241a8c8529401a0b7a5eda0c182bbbd026b6e67cbb9f69fca4f3b338e6987552bca67dbfca1140c7fddb9ff54de7dcddb92e10d8f05436bf0184b326f163fb13313b666857629e922333c77762f3a41904054d3e4cd41b8cd140a591a71e9259f6edaddba6233558c5cb137f77b919d80dd85eeedca4cb336974512589caea3542a4d659b8a72c630ed67ab4a97b1258eb2605095c95f59cf8d933929ac6064b980749ef7db345650999d0a5423acb738242b73518a04bf6fb8e9d27facee78ca3bd3a195e349b9669220345ee09b44ba8c38f1a8fda20abb100364512633cee4efe1f9b395cb41199d4f260e2b1c9c03fde78db4c2e815ee4d4a5c2e2eb63b693549125f2a1b8e7fe3bdce6e913b79fe1e761bb6ecd9d63dde1ba603cdbecae2716988e4d67b98b295de97b6fd86c7afc9a5f22914e9da81130b67dbcdc375bec145f7ccc4cded670bc449f667dbf8292bd6f49ff9e177efd8d562b7f967e6e3938a186110891eff2b85c32bd526c2dd2ff85079dd391ff7868613af32241f8766a118c77513436471dde707a3f144f9ccedf70e29b46c621091c23491199fb45bb7708569c19a1df3c56f1966e943fcf14f378c20bed9b6fbb24da623a015bbf683405ab2e41ef9365e9ce682e29514e9670904219b3115e58c70422b2fbe94ee624b17651104d058bd2b8d6de5383733f7318eda9a5554c2a1d507ab7b6e784f30bcee9e60644d1454474d1a86100010546cc9828a6747a1b456a5adf9ae4ef781f27816b46c1943ba40cae86da12bad35b2d6bdee425cbff4a137ef25e390ad17102d9b9545e1d232f3b9b38cc226f2e58066e4c5c21fe405a05d3eb9160cdbe5c49dc864d6492928c1da737f52bbf0956f08d65c705bb2e2acd41f5e6b82f2b00942df449057d38d2913c79149d9aae9e7121e1f7e3301503758a35c99cf02a60708370a364fd207adcbcc99209dd8ea3cbeef279ecfbb517c9087b56c5616454e54491d90135da816fa22d90f249a91174d6a1b01a492931fc6c0266e5bf7713c945b9f69d6cfc9f7935387c1bf7490760e988d876c312a327627616d3267b5a61404cf8f4bdb9ffdf7b5bdffec4dc1c8fac74d6d9d0986864d1a70cc506ca6e2d8c458da67cca799199e34dbb8684e4411973e5ae9b245b3c06dfa21f90daecf67642e77678eb9f6d12cf9f8b91f05108f2d0f1dc65636ece86f43674b501601f252bf95ab29d27ed7f299321315598b04b9cb63378e4bb236b7b190e6f85d8ea5b1cbb98cef90cde5de34eca79f938531e33c332a31c60496fd00469d6abfd3a2141b2f8883cd977f7568c7f3febb3fbce9685c5e65224e500ae5148478b63294ca3ef394cf5ecc3e73115878e6b4008f5a5240961cac50cf2c136422cae6b538b376b7f6fda7e3273101c73e6169e32329550ca9b56b15803d8f15545c733f668a481f56e44a7129682a5f1ea8c85a2469627f83a3c0b695062a1467ac5c4448e52c5ff1b8edf9f6bb634f1646fe3c3e2a09c2d13bacb264903c96b6ea65f5a179ab1ff082523d3cfbbabf2a6fbae23d717945230eaac6845519f82e331c50504368a514d73e4482086c0a168e1084c842920c609968914ff1642ac21afcb2658c664546deb274c63025dc9c9fec3c33f637ddf27e329bcf0f8ce7b669d76e4bb400f2530f7e0cb87821b7b866c0e2d1e57ba20c2aa8ab6215edbd4345d622f1fc10091662a6ed57f0426ee4a9fc48056957e725bf3f916e0e18962e2a329125eb58e3d5517b4799b35270fc726dbcbcfbf91f0c569c7db3b7e2ac24aaae316900b11ce049ca4b861459ad315522a7b3879e5f8a357bc49c98c819d3cc5ce6b001b4dcdaf713e7b6503f99f14d3eb66bf1478208316975751d875d22b1839d68fca1e45a7e8b04cb1658f122a6f4255257697761cfd08cbc4824a5ca8f025737b0056946353683bcfb42f7d3f2ccb59f66697b848820e3886c052f9ab1fa097f78eb4343e7befcb7ccf0f687e2ea3a13796513731a07b69a66532fb0edd4a63d9bfea4cb1026da48fc59f1e53707b8db9462f7b1ed75661aeaa6313684d7c04e4ef9719d06730c92ea0cfc35ef79096e918d517607e56f11617cb355a56050b8f40b4cf4d9aad20354642d12540fcdd7a1d86ac4368596e872d62db2c797fbd1db2a96e5632d764222a54ff0d270f353bf31bafb86ffe10f6f1a8f87d69a34ac9918e2881d6d2cd7c59ae9acf5dcc585e281691365aa6c3b8dedf4b6db169a09a58bd82e6db72ec28f6bddfd95dd3c97c4a46b124f51ba4fd68ac5b70b7b91e115a0226b914061c9300ef9965c53dcb0f3266819c557378c156ff608a5d3c50bb27a543690bd3463f51b323e6bc7359f2aafdef3496f686d3d298de2298659bb0b3573887447933e441e2250f9db5fd7763c94b468c921eca663f71c2437a781809b4d3b5d4ca74d639c39912f964988d8f30736e2d4bd0ac09e87a2331b9f16e0dee45f64e935223cad10edd63d51069c7e6a751b4454642d12a9a650fc6505a1b8d8db98338aaf6ed88cf3c229cb4cfc9bcaeb877c2d4de93782eae8d1e1dd2ffcc3f2ea73bfeb8d6caf2795b5701cc1732d9b8002459ebd154c14d7738f6aa2eca288a1e0829c811fa68f56ba05ede9a9e3366744bb86cd1f4d11033820df89d36250a4b828f3d2db84bc78fc19f95ee92a28f994c5e3126cfef6d1cd19ddbb612d0294cd760c4e46369b97dd50fa0d7ffd853f1cbef8575e1faedaf3dd74785b9254379a38a898989f8361ab101ead0884ec094be52c432f689457d6485384cd4a0d73a5a94e1a2340f3e5cd58b61eb1ec9f15ad0ee1fbc814edf372e9701565f0b1dfec547a024b3e6511c82be7c0deb8c4561462ac349c39b7ee58735c89747db06329e5bc4988229b0f94be841f925e77fe4faae7dcf0566f64fb43667853ea85353cd16a4e34b1c32d13537cda7077c28b6ff271c9b7f92cec22b3fb09bfd737575aeab459381929a774a0c0a1cc4aba944e6db7a0fcaacf7ed9dbeef6e2092d6d79282efd2458b274a422ab876825bc68522fe0079c418004cc356b33275feca605a839f836999bf287b909552f9b3af4f9f633145a5baef887eaee1b7fd3abac3e9a56569b342cb3214be4b37401220d724999c04f2af1c3c74c06d2d295c918fb7169197964973878ae74d479b392c699c434edde3c59ac6832933be1ee8aa22c126897fe493748ef218c594ee9017ae317092a06c95d6eb06c2fad1d5b697007ab56f130872fa59ff0c2f25478d6733e156ebce8436975456cca4310584c85100a5941cf872c4f1d9bae654b1aac24a9720c921d242fad57261031de9e96ba628c052266d3289175fecaee4a39448125b56362c76171d559d1d04ab1b8301d65ab7d01e24b91d557711e2434232f9214bfbc5940d37a8dadbcf04766d3ce84155bb40cb653e982d18c3500f8a5a189f28eabfe4fba6ac7bdf1e8c63486d0e2fc5931d4922b3a29ba88b417b111138f5e92a83465c15c9d9da5db5691cb957c31e0b6f36ef975e2f62fc60f9638a9081ac4c10a2d98c774da9d0a80150d3515d75d0b5611f2f15c20a27c6a4a01e943c1a22d593d446ffc62910ce66aaf62e07e898bd092d7d3652b346954b2f155fa1d7fdd793f1eda71d5db92caaac371659589383e8b1395e29973fc151f3a9fb4acf1d1b34ccdcc8b03986dc9725dca2d5cf26051406b4f2eae8870fbdd769e85f8b1cc10351086566f75278d4a36a1cacaf28b139e45125a2e6e5a291697933d9b7c5a6aa6b30ec3f3b49fab6d9b0d03dd898c320bcdc88b04891735830c35960c252d06b3922f1d166f1c5fb338431c78288fc6367f9db312e5f82c934481c906e92bfd8f1794a7839dd77cbcbaf58a3f894736d4d3ea4a938655149f48039056fcc7771dbc2084c1d1c712622b4012e08ba601b67d99672b9fde5c7b522b2d89f11fbd348b07d96832c32fc3e032f3e30afc597e780e3959b69f1bd601ffbb33f03d4952640dc688e7cfe28075cea6efe27da646a45c90b05bf78338f7f9f6e7d7932429cb8a5238a2289267937f86be6fd39023ff2ce77aee6702d246d3dacf93df26d939b59eef217af34f03c92648c776e0b1cd34b6b2e1f05e5b992cd4d8f2605b1f9831166b2d2473b97518b35bb6aa0c084175e5d1d2ce6b3f12aed8fe3d535b1ba525be6d18620f1312a74463614f71e51bdf0bc498c5655bf612972cb2a431470a4168489b766c97587e1d9615dcd6738e7ce13e17ee085922df24cc3bd2fc4ae1d37958d9f0a708a3de8a7dee5e9c01f98aaefdfee4f7b162ccef6bc7f985bf12fc69f95c40f06c64c80871cbf6b4cf6d27bce8c7d952e1d211c3cf9f3bbf9d3b9fbcf091ad2b5d4633f122616265a563932fea0766229acf4a03095f9a92909e1768f94ce1c4d6a9cc09330733531cc75219f203c22932a0754f50a3500a2a8382b772c73d95dd2f7aab37b2ed8ea8b2c6c4fe904920ac44e4a31c4d62a4013efe1869450a624e33c25fbd1061481f2eedd09a690ae9309fb6dcba4b9bed96f7dbf42f6170dd599b3fbad10fd33d8f816117f62d5dc5732a38fd0a04a744c05e4b20cba580951ecde1c265dea4719b956e10b4cee9f6d1f2d8b8713261a588e019c67c96cef8fca2286a0a1f927faeeed9d3b2677b46c670988e68c49d2b7fcefc3ae2c55f624a8f5091b548d2240a63fc028f5993b1bcce5ab288cb04145a0b350a33671c4f859065b92013104e0a81850c1e492667c58578d0432b6aca80e0f961146ebdf24bd59dd7bc23a9ac1b8f4aa3702b63078416944c0c9125753d0b59ce9f96b0f067faa0088fe1d756f892769bcc4ec3928e9da194c88ba6bc1f9a3d6eb61f87f567fd88b9b4cf3c24df2da42ceb3c51144b65c3372cdb0556be923a1d63fee30f1daeb7e3dc5c454b23746f3fc655d4885b1d363b30a5e7e0f9e0d1d834ee9e259f23cdc167e89e2bc550fed9bb634fd718860b93db247f3e925f577a8b8aacc522750813bbfd0563bb5222ec60063b1d638dd86e73f9cb5bde2f60d79064bc122ab0921d7f835f5b70a46765c0e0f8ac70eb33be505e7fc127bcd11d5351758d89832179f6be1f9810753327360f50fe06f8e3c36449b104a185dfddd97269cceaf985b8e3c784fcc52ee621c415f9a86bb5411447214f2671809871826629e0f5e42b5357f9115701e6cd96212d6b076134b0d09ab280e079c97371cf92e45b96884b5fc4f971fe696eff622d1f463e9c7698a672fb580fcc4e644a575091b5483c3f8c5991b18260d6c1ef572cf12b0695d6e9188f6fb7b9fce5ade90fff586549411da07067460f21b402fc60f7fcd8a3c8d25fc303895f5bf7786def8bdf5d5abbf7cb5e755d6c821ac5170afad07088bbef73b03b0a7f11dff00ff37c16b82ca89d003af37f28c6735b33ffb1a52a5b93bf4cb56c4d932dfc48e1007d8889087545572a80381bb0cc8a27c6ada0711c3c2bab33c555b24e6439a1e58cb88ad141f7bcbf3c61184e64ab4ac1c0338bdd73645ae2b373cf9eebee39e7448e2cf36ececf62cd1d9f37b78fcc9596102fb602283d4245d622e1b80ed609cd914e6cd16285816d97c0e7b7997ee4f0dc369d666ecf65d60f61278b6461549832a7362265e3c65636642c19ec68fd2a0386e725fedabd3f29edbaee3d6674f343f1f0461397571a7e1330c2338f59003b91c3c937f12f6ed6ef4825d25547b1054731ae5bf135d3ad7d7fde8d2d323cc7dce6fc505e895fbab1ab10e957c66831cd7a415d7e0c7481e97a7d285b457c9063e611388bc51dcf252bbbf62e40b73f5f2912babb38b4579a70634b965240f0cca4d8e5b3725d847c86cef2e4b7f369622999efdc39a63b756ee5d4a8c85a34a9c7ca4a061943cc50649dae8ec917f2a76df805155354e151b272ad236e0dc40dc5fc34e2a6bf6006193f88834d977dabbcfd59ef4d47b61d8d2b6b4dddab9928459a40e11fc56cb189902e62d388a1b92972785896eee666f189b9951e290e665b2b9db3b8f10ddf7ac41ec44d841f926dd01d91353d5df3031f51698d6ba1b5e27f7a465889b94ad7595e4c91bc8822eef8f663b90eb4562c2ef8ad6d9fa57b6679f84c5dcb16c90b1cf7cc97caf2ada773ede7b9b16f1c6633bfd27554642d12540e763cb1882b14d0f8277fe748e0a732c75cfbe63387db66f69539debdd044b006d761891f1e4f833284166a396560f14bb5f1e09ceb3e3ab4fd8a3ff686371d8faaeb4c521a463a084dec735678081a11e1483bec1ac3c2b535a1589e654c62f9f4e66ca69f9c2175b50c7922a11b7ce5ac59c964c7709b3f525299b70b3f04bca01b3f063c5486a56c5de261754c16b725c0dd9353d15ee9dafb41418afb9b559660e922a62c2978567e5e9cf3d939d1e59e6ddbb314dcba3bee4ccc85d37e0e1717c27d240c438a2cfdc1dd2354642d12cf0b230e30f65089b16261af8b6dd9b209fce466fdb4339fbf99dbd6f2fe63b658b1124365954060f155fe84952b5b0abcd2513f28ebb88e65406974e3a3a50b5ef2aecaf627bfdf1bdd3a9956d7989463f3c210256cd5a44105e9016903e9c266792b7ef816aa082e0a1eaa2fd9d72ab067628f137194a53f3108381706970c46b673960f1387005b31a448ab58417eea4e4b16e169397e51f24f1289b9789319d7b64023aec273ad63f94ad8e1b6e7aa7cddb1e532c7d505ac18ebd96ea560944aa5a9fcb3e6f3732d902e3d38f269c4e1dccec4da7122cfb5ac39a1459096a610cfaee53165262ab2168bc769b2d81dc76e3a8a1b767d84308a9d85188e6145858a87db6ef0edc28dc7733277549a290415bb86680c9702cb94609c37299cec56378cd27bc2a13507cbbbaefe90bf7ef74de9f09a382a0d9b241c3686028bf3680565dbda8934c3b4136329df38e4546a4c574ccf145e32b59a33fac132a67fa455116648efd86e5a2e8cd671f698969fec982c3c49bf48a30ccb78a569e4858e7765a0d2f15244c06a1b5b49b98a8995965b9e2ef9cacf55bc3417367162caed23ac10f3ee24dbdff17ba29c1e789efc16605318f379b5a79f7c7ac8bb7712c661ae74cc165cc4b13b915066a1226b91a449546ec49e8962df34928aa99bb2a9a725336586e6b5695333d39e336c7bc370afc9beba3f9cdb97ad9bbc5bb6ddb40adcaa66dac7b91156c347787e156195e05e31112ad3c42ff397392b15365d28cb046fe5cebb2bbb9ff7c7496dcd038da1b5a65e5a89f4316c22a4a17a5a310da49d06d24a3d33ee1343ba8bfc5ad31a4da31f2c83eaccfdd86e9a84013784d13a6ea61f394e96b080fe100fe49998e2cbf89c12a5e315002a1ebf3e3d0dd5c9efb8d98a281333b2df2d4f078645b154afd765e9dc5ca5472361188ab16226793f6edd85114551055e4e3f524ac73871e2c41a3c9fa640e6f32c954a4dc1e59e25f773ddb9d1960217be33e2e2e18cb873231e15f8d3babe4770bafd6c555908d1a33f7cf6a16fbeffd366e2c0b06fa690826126415a4641c9b40e3fbca359e9280bdc63a676ebd056a138ff827b16cc190e1edad4c2ccac311c645bf664e36dd288ad6210577c67aab472fbd4d086bd9f5979fe0b7f3f58bbe736fae501cae09334a686a66ffbe81b8fdef7f57f1f2453d5a07ea29ac675fc22403a4d6d3a6a252e9bd4c438d070563ab1c957fe34272f755d16d926c887e7b0bbf957c62c32a9ca311ccb9878d039e1706ac291b4b6fdd24f8d5cf96baf364165520ee910a864826f7ffbdb3f77d34d7ff7673ffde94f579f18b73de9f65a6c6ca5fb3f7f618b80952e2b3c56b66ce1202e6c1add28b078af9d1b2b611ee3b23b2b47fabbf4d24ba7aebefaeaff7ce38d37be0fdbda1a5d30907e9efdd18f7ef42bfbf6ed0b1e7cf0c1a6d0217caedca671dd3d53f7cc49be783f5d18960b271f9e5b77e7465a6a3ce319cf780bd2d23b6ab5da98ec54ba8a8aac45124f8dadacdffe37ffbe7ef0fee77a69bd9c4453a82d642207fe420e71434399ad947980691e3fd4a184f03bc28b5995c10539b279cfd9e0841cc94533a3f099a094b66e3c98fd829ecf6a902f3672a0b0075567a26cdd18848d1053db95180f55569df5bddad9cff990b7e6ecdb39303a0b57592644634f6c8a1ffdd68b1b471ebc3c3eb6ff69269a5a9dc68d329492ef05b61b2a4df9d968fecf34127501ea074970583285a272401a938498255856143cd61d64119d0f5f4cd658a10b533042c25964c06dd090c21fdbe2cd0f232fa8d63d3f3c513bf705ff2bd8fa94aff0181b5ae7387af4e8a69b6efaec6fdc79e71dff573d8acb8813f31adf64b1df6ec075d14e0754aa72202bd4aca26b8647a33b8d64e7c04256644143a518c3eadbb76fffca2b5ef18ad7ae5fbf7e1f3d2ac5627c7c7ce55ffee55f7e7d6262e2ec03070ed4e014302d719f1358844ecedab18f7e41ccf2c8f0703c7fd9677585859b5848f5c32588b66eddfa1308acd7edd9b3e7077454ba8f1404cae2688c1ddc18d48f6dc62a4ac706c4156ea44982b4d1e0e0139ff90a6227b49518ab156614546a92095af79b5d257097f7b1b0259925b797c1486dc66e3fcf0f28aa24f77a7c1bab54696005db019c4d6cbcb29d572789ca4969f4897068d521bee22f6ecaf2238d8368f2d81a7fe2f17392f127d6a6d1740d0909699393d442f4a388469dcf0f1688a04fe22894f48914cbb42a4bafc44fbb5078e17f5654d8194d59a0db9a84304c7a91349aa560a65eb81b2f8c528e0d443e90fc40fc52c3946bd351e24d96566d7d0862ab5b734279f7de7befc5dffffe775f373e31b5179233c40d18c6d5aec0be1262ccabb071e41aff30d6739491ac48dd3e18f359c475082957f1c9f1d9b1b8d7cdbad01d13e1f849d804c2f2b19d42601dabd56a775c71c5157fba6bd7ae5b33ef4ac1c0a3f2eebaebaecbefb9e79e97eedfbfff1a38adc5f395b74af03c5b0f1a609bb87440dab7f9e8659b867d4892769dfb102e56edbafcb1eb0db84f60bd0aa3c8e379791cf3117fd0303d72f9e865975df69e4b2fbdf4cb485bfa76618f509175fa649929ab3848f60b0659846ed6dd8aacd3c7fe3ac98300e1d62cb4b99fe7107f349eaf55012acb1d3f4d22887fa4118e3acf48d9b69a4b992807b095a23cb0da01750505183628b6e871563a9c4d26c004976e258cacf58cc1ba707cfc38e8513a9d9a9a1c9e9eae57112fc6218de3b8dc6834ca5886103cac2c39dd030737c30b8bc899793873e33273e185597f144c999bdbe9f6893b2a47b640b0f28c4aa5d2348de7a2bf72b92cdb434343dafadc074451543a7efc38c76795f95cebf57a05e988c247440f9f33dd90264a7093679f3d6b22f9807ee897dbdccf25b6b9cbedb3f905307d71097f6cf1a4a897694998e6c2306cc0eadc4777b885d56a756ccd9a358fd34d02507a02737fb6aa288aa2288aa22c15cd5fb68aa2288aa228cad2a1224b5114455114a503a8c8521445511445e9002ab21445511445513a808a2c4551144551940ea0224b5114455114a503a8c8521445511445e9002ab21445511445513a808a2c4551144551940ea0224b5114455114a503a8c8521445511445e9002ab21445511445513a808a2c4551144551940ea0224b5114455114a503a8c8521445511445e9002ab21445511445513a808a2c4551144551940ea0224b5114455114a503a8c8521445511445e9002ab21445511445513a808a2c4551144551940ea0224b5114455114a503a8c8521445511445e9002ab21445511445513a808a2c4551144551940ea0224b5114455114a503a8c8521445511445e9002ab21445511445513a808a2c4551144551940ea0224b5114455114a503a8c8521445511445e9002ab21445511445513a808a2c4551144551940ea0224b511445511465c931e6ff07b7f9483d498f9e440000000049454e44ae426082, '2025-11-26 07:52:51');

-- --------------------------------------------------------

--
-- Table structure for table `custom_quote_replies`
--

CREATE TABLE `custom_quote_replies` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `message` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `files` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`files`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `custom_quote_requests`
--

CREATE TABLE `custom_quote_requests` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `custom_type` varchar(50) NOT NULL,
  `specifications` longtext NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `message` longtext DEFAULT NULL,
  `selected_color` varchar(100) DEFAULT NULL,
  `selected_variant` varchar(100) DEFAULT NULL,
  `agree_terms` tinyint(1) DEFAULT 0,
  `status` enum('pending','quoted','completed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `defect_reports`
--

CREATE TABLE `defect_reports` (
  `id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `reported_by` int(11) NOT NULL,
  `defect_type` varchar(100) NOT NULL,
  `defect_description` text NOT NULL,
  `quantity_defective` int(11) NOT NULL DEFAULT 1,
  `severity` enum('minor','moderate','severe') NOT NULL DEFAULT 'moderate',
  `status` enum('pending','acknowledged','replacement_requested','resolved') NOT NULL DEFAULT 'pending',
  `reported_at` datetime NOT NULL DEFAULT current_timestamp(),
  `photo_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_bookings`
--

CREATE TABLE `delivery_bookings` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `delivery_schedule_id` int(11) NOT NULL,
  `booking_type` enum('delivery','pickup') NOT NULL DEFAULT 'delivery',
  `tracking_number` varchar(255) DEFAULT NULL COMMENT 'Tracking number from courier',
  `courier_name` varchar(255) DEFAULT NULL COMMENT 'Name of courier service',
  `vehicle_id` int(11) DEFAULT NULL COMMENT 'Reference to transportify_vehicle_list',
  `booking_reference` varchar(255) DEFAULT NULL COMMENT 'Courier booking reference',
  `estimated_pickup_time` datetime DEFAULT NULL,
  `actual_pickup_time` datetime DEFAULT NULL,
  `estimated_delivery_time` datetime DEFAULT NULL,
  `actual_delivery_time` datetime DEFAULT NULL,
  `delivery_proof_image` varchar(255) DEFAULT NULL COMMENT 'Delivery proof in webp format',
  `booking_notes` text DEFAULT NULL,
  `dispatcher_id` int(11) DEFAULT NULL,
  `booking_status` enum('pending','confirmed','in_transit','delivered','cancelled','picked_up') DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pickup_person_name` varchar(255) DEFAULT NULL COMMENT 'Name of person picking up (for pickup type)',
  `pickup_person_contact` varchar(50) DEFAULT NULL COMMENT 'Contact number of pickup person',
  `driver_name` varchar(255) DEFAULT NULL COMMENT 'Name of driver',
  `vehicle_plate_number` varchar(50) DEFAULT NULL COMMENT 'Vehicle plate number',
  `is_replacement` tinyint(1) DEFAULT 0 COMMENT 'Flag to indicate if this is a replacement delivery'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_bookings`
--

INSERT INTO `delivery_bookings` (`id`, `order_id`, `delivery_schedule_id`, `booking_type`, `tracking_number`, `courier_name`, `vehicle_id`, `booking_reference`, `estimated_pickup_time`, `actual_pickup_time`, `estimated_delivery_time`, `actual_delivery_time`, `delivery_proof_image`, `booking_notes`, `dispatcher_id`, `booking_status`, `created_by`, `created_at`, `updated_at`, `pickup_person_name`, `pickup_person_contact`, `driver_name`, `vehicle_plate_number`, `is_replacement`) VALUES
(1, 31, 1, 'delivery', 'fsfsfsaf', 'safsfsda', NULL, 'sfsafs', '2025-11-06 08:00:00', '2025-11-06 09:47:29', NULL, '2025-11-06 09:49:57', 'proof_1_1762393797.webp', 'fsafsafs', 13, 'delivered', 0, '2025-11-06 01:45:35', '2025-11-06 01:49:57', NULL, NULL, 'sfafsdf', 'FSAFSF', 0),
(2, 32, 3, 'delivery', 'fafasf', 'dsfsf', NULL, 'fsafsaf', '2025-11-07 08:00:00', '2025-11-07 16:56:50', NULL, '2025-11-07 16:57:53', 'proof_2_1762505872.webp', 'fasfasf', 13, 'delivered', 0, '2025-11-07 08:55:25', '2025-11-07 08:57:53', NULL, NULL, 'sfasaf', 'FSAFEASF', 0),
(3, 32, 4, 'delivery', 'RPL-4-20251108', 'Replacement Delivery', NULL, '#samplereference', '2025-11-08 08:00:00', '2025-11-08 07:51:30', NULL, '2025-11-08 07:54:03', 'proof_3_1762559643.webp', 'Replacement delivery for 1 item(s). Multiple replacement requests included in this shipment.', 13, 'delivered', 0, '2025-11-07 23:49:56', '2025-11-07 23:54:03', NULL, NULL, 'Wendhil Himarangan', 'SAMPLE PLATE', 1),
(4, 35, 7, 'delivery', 'dwDw', 'Default Courier', 1, 'fsFWAF', '2025-11-13 08:00:00', '2025-11-13 08:55:50', NULL, '2025-11-13 08:56:08', 'proof_4_1762995368.webp', 'FSFAF', 13, 'delivered', 0, '2025-11-13 00:51:23', '2025-11-13 00:56:08', NULL, NULL, 'FASFSF', 'FASFS', 0),
(5, 35, 8, 'delivery', '42424', 'Transfortify', NULL, 'fsafsaf', '2025-11-13 15:00:00', '2025-11-13 09:06:44', NULL, '2025-11-13 09:07:07', 'proof_5_1762996027.webp', 'Replacement delivery for 1 item(s). Multiple replacement requests included in this shipment.', 13, 'delivered', 0, '2025-11-13 01:05:00', '2025-11-13 01:07:07', NULL, NULL, 'fafasf', 'FSFSAF', 1),
(6, 41, 9, 'delivery', '3453654', 'Default Courier', 1, '43523234', '2025-11-14 08:00:00', '2025-11-14 16:50:05', NULL, '2025-11-14 16:50:51', 'proof_6_1763110251.webp', 'kgdfkgnehrd', 13, 'delivered', 0, '2025-11-14 08:48:07', '2025-11-14 08:50:51', NULL, NULL, 'egdgd', 'SGEAEE', 0),
(7, 44, 10, 'delivery', 'fwqr', 'Default Courier', 2, 'wetw', '2025-11-21 15:00:00', '2025-11-21 13:42:46', NULL, '2025-11-21 13:44:14', 'proof_7_1763703854.webp', 'wtwqetq3', 13, 'delivered', 0, '2025-11-21 05:41:55', '2025-11-21 05:44:14', NULL, NULL, 'wrtwet', 'WTWT', 0),
(8, 50, 11, 'delivery', 'fsdfs', 'Default Courier', 1, 'fse', '2025-11-22 09:00:00', '2025-11-22 11:05:39', NULL, '2025-11-22 11:05:56', 'proof_8_1763780756.webp', 'fesfeaf', 13, 'delivered', 0, '2025-11-22 03:04:52', '2025-11-22 03:05:56', NULL, NULL, 'efaefea', 'FASE', 0),
(9, 56, 12, 'delivery', '09128390234901236', 'Default Courier', 1, '09128390234901236', '2026-01-09 08:00:00', '2026-01-09 08:15:06', NULL, '2026-01-09 08:16:05', 'proof_9_1767917765.webp', '', 13, 'delivered', 0, '2026-01-09 00:11:56', '2026-01-09 00:16:05', NULL, NULL, 'mark jameasmakeikm akioer', '12132', 0),
(10, 6, 13, 'pickup', '09128390234901236', 'Customer Pickup', NULL, '', '2026-02-23 13:00:00', '2026-02-23 14:22:34', NULL, '2026-02-23 14:23:14', 'proof_10_1771827793.webp', 'test', 13, 'picked_up', 0, '2026-02-23 06:20:32', '2026-02-23 06:34:30', 'markjamesulo', '123', NULL, '12132', 0),
(11, 7, 14, 'delivery', 'jhghguygyib', 'Default Courier', 1, 'gghgygy', '2026-02-23 08:00:00', '2026-02-23 15:15:20', NULL, '2026-02-24 08:27:39', 'proof_11_1771892858.webp', 'gfyuftdct', 13, 'delivered', 0, '2026-02-23 07:14:27', '2026-02-24 00:27:39', NULL, NULL, 'fasfsf', 'FSAFASF', 0);

-- --------------------------------------------------------

--
-- Table structure for table `delivery_logs`
--

CREATE TABLE `delivery_logs` (
  `id` int(11) NOT NULL,
  `delivery_id` int(11) NOT NULL COMMENT 'FK to delivery_schedules.id',
  `order_id` int(11) NOT NULL COMMENT 'FK to orders.id',
  `action_type` enum('reschedule','assign','unassign','status_change','proof_upload','delivery_complete','third_party_assign','cancel','notes_update') NOT NULL COMMENT 'Type of action performed',
  `action_details` text DEFAULT NULL COMMENT 'Detailed description of the action',
  `old_values` longtext DEFAULT NULL COMMENT 'Previous values before change (JSON format)' CHECK (json_valid(`old_values`)),
  `new_values` longtext DEFAULT NULL COMMENT 'New values after change (JSON format)' CHECK (json_valid(`new_values`)),
  `created_by` varchar(255) NOT NULL COMMENT 'User who performed the action',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of user who made the change',
  `user_agent` text DEFAULT NULL COMMENT 'Browser/device information'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Log table for tracking all delivery-related actions and changes';

-- --------------------------------------------------------

--
-- Table structure for table `delivery_reschedule_log`
--

CREATE TABLE `delivery_reschedule_log` (
  `id` int(11) NOT NULL,
  `delivery_schedule_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `old_date` date NOT NULL,
  `old_time` time NOT NULL,
  `new_date` date NOT NULL,
  `new_time` time NOT NULL,
  `reason` text DEFAULT NULL,
  `rescheduled_by` varchar(100) DEFAULT NULL,
  `rescheduled_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_schedules`
--

CREATE TABLE `delivery_schedules` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `delivery_date` date NOT NULL,
  `delivery_time` time NOT NULL,
  `delivery_notes` text DEFAULT NULL,
  `created_by` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `delivery_type` enum('company','third_party','lalamove') DEFAULT NULL COMMENT 'Type of delivery service',
  `delivery_status` enum('scheduled','ready_for_booking','booked','ready_for_pickup','loading','out_for_delivery','delivered','picked_up','cancelled') DEFAULT 'scheduled',
  `item_type` enum('original','replacement') DEFAULT 'original'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_schedules`
--

INSERT INTO `delivery_schedules` (`id`, `order_id`, `item_id`, `delivery_date`, `delivery_time`, `delivery_notes`, `created_by`, `created_at`, `updated_at`, `delivery_type`, `delivery_status`, `item_type`) VALUES
(1, 31, 0, '2025-11-06', '08:00:00', 'sample note', 'warehouse2@gmail.com', '2025-11-06 00:15:06', '2025-11-06 01:47:29', NULL, 'out_for_delivery', 'original'),
(2, 31, 0, '2025-11-06', '08:00:00', 'palitan niyo ito', 'warehouse2@gmail.com', '2025-11-06 02:35:30', '2025-11-06 02:35:30', NULL, 'scheduled', 'replacement'),
(3, 32, 0, '2025-11-07', '08:00:00', 'I deliver niyo ito', 'warehouse2@gmail.com', '2025-11-07 04:50:38', '2025-11-07 08:56:50', NULL, 'out_for_delivery', 'original'),
(4, 32, 0, '2025-11-08', '08:00:00', 'Delivery niyo na ito', 'warehouse2@gmail.com', '2025-11-07 23:46:22', '2025-11-07 23:51:30', NULL, 'out_for_delivery', 'replacement'),
(5, 33, 0, '2025-11-08', '08:00:00', 'deliver niyo ito', 'warehouse2@gmail.com', '2025-11-08 00:31:53', '2025-11-08 00:31:53', NULL, 'scheduled', 'original'),
(6, 34, 0, '2025-11-09', '08:00:00', 'hello', 'warehouse2@gmail.com', '2025-11-08 00:46:25', '2025-11-08 00:46:25', NULL, 'scheduled', 'original'),
(7, 35, 0, '2025-11-13', '08:00:00', 'deliver niyo ito', 'warehouse2@gmail.com', '2025-11-13 00:50:17', '2025-11-13 00:55:50', NULL, 'out_for_delivery', 'original'),
(8, 35, 0, '2025-11-13', '15:00:00', 'rutykyoi', 'warehouse2@gmail.com', '2025-11-13 01:02:55', '2025-11-13 01:06:44', NULL, 'out_for_delivery', 'replacement'),
(9, 41, 0, '2025-11-14', '08:00:00', 'hfsjahjgnsj', 'warehouse2@gmail.com', '2025-11-14 08:45:18', '2025-11-14 08:50:05', NULL, 'out_for_delivery', 'original'),
(10, 44, 0, '2025-11-21', '15:00:00', 'drhhert', 'warehouse2@gmail.com', '2025-11-21 05:41:04', '2025-11-21 05:42:46', NULL, 'out_for_delivery', 'original'),
(11, 50, 0, '2025-11-22', '09:00:00', 'gereg', 'warehouse2@gmail.com', '2025-11-22 03:03:52', '2025-11-22 03:05:39', NULL, 'out_for_delivery', 'original'),
(12, 56, 0, '2026-01-21', '08:00:00', 'how they i live', 'warehouse2@gmail.com', '2026-01-09 00:08:03', '2026-01-09 00:15:06', NULL, 'out_for_delivery', 'original'),
(13, 6, 0, '2026-02-23', '13:00:00', 'hehehehe', 'warehouse2@gmail.com', '2026-02-23 06:19:35', '2026-02-23 06:22:34', NULL, '', 'original'),
(14, 7, 0, '2026-02-23', '08:00:00', 'hfghfgfgg', 'warehouse2@gmail.com', '2026-02-23 07:13:15', '2026-02-23 07:15:20', NULL, 'out_for_delivery', 'original');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_settings`
--

CREATE TABLE `delivery_settings` (
  `id` int(11) NOT NULL,
  `base_fee` decimal(10,2) NOT NULL,
  `per_km_rate` decimal(10,2) NOT NULL,
  `total_km_base_fee` decimal(10,2) NOT NULL,
  `location_name` varchar(255) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_settings`
--

INSERT INTO `delivery_settings` (`id`, `base_fee`, `per_km_rate`, `total_km_base_fee`, `location_name`, `latitude`, `longitude`, `created_at`) VALUES
(1, 500.00, 60.00, 5.00, 'MC Premiere Building, Old Samson Road, Balingasa, Balintawak, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1106, Philippines', 14.65700370, 121.00337600, '2025-08-17 23:49:37');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_sizes`
--

CREATE TABLE `delivery_sizes` (
  `id` int(11) NOT NULL,
  `size_name` varchar(50) NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_zones`
--

CREATE TABLE `delivery_zones` (
  `id` int(11) NOT NULL,
  `zone_name` varchar(50) NOT NULL,
  `zone_code` varchar(10) NOT NULL,
  `base_fee` decimal(10,2) NOT NULL,
  `included_km` decimal(5,2) NOT NULL DEFAULT 5.00,
  `per_km_rate` decimal(10,2) NOT NULL,
  `is_free_delivery` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_zones`
--

INSERT INTO `delivery_zones` (`id`, `zone_name`, `zone_code`, `base_fee`, `included_km`, `per_km_rate`, `is_free_delivery`, `created_at`, `updated_at`) VALUES
(1, 'Metro Manila (NCR)', 'NCR', 0.00, 0.00, 0.00, 1, '2025-09-03 07:29:05', '2025-09-03 07:29:05'),
(2, 'Luzon', 'LUZON', 120.00, 5.00, 12.00, 0, '2025-09-03 07:29:05', '2025-09-03 07:29:05'),
(3, 'Visayas', 'VISAYAS', 180.00, 5.00, 14.00, 0, '2025-09-03 07:29:05', '2025-09-03 07:29:05'),
(4, 'Mindanao', 'MINDANAO', 220.00, 5.00, 16.00, 0, '2025-09-03 07:29:05', '2025-09-03 07:29:05');

-- --------------------------------------------------------

--
-- Table structure for table `discount_images`
--

CREATE TABLE `discount_images` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `discount_images`
--

INSERT INTO `discount_images` (`id`, `filename`, `uploaded_at`, `category_id`, `is_active`) VALUES
(2, '../uploads/6886db8829e5b.webp', '2025-07-28 01:42:22', 2, 1),
(3, '../uploads/6886db81a56ae.webp', '2025-07-28 01:55:26', NULL, 1),
(4, '../uploads/6886d99f6d971.webp', '2025-07-28 01:55:31', 1, 1),
(5, '../uploads/688820ee96659.webp', '2025-07-29 01:16:31', 3, 1),
(6, '../uploads/688820f2d7cb6.webp', '2025-07-29 01:16:35', 7, 1),
(7, '../uploads/69648de9873ea.webp', '2025-07-29 01:16:39', 2, 1),
(8, '../uploads/688820faccce6.webp', '2025-07-29 01:16:43', 1, 1),
(9, '../uploads/68c9044ca4945.webp', '2025-09-16 06:31:41', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `plate_number` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `driver_list`
--

CREATE TABLE `driver_list` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `government_id_number` varchar(50) NOT NULL,
  `license_expiration_date` date NOT NULL,
  `emergency_contact_name` varchar(150) NOT NULL,
  `emergency_contact_number` varchar(20) NOT NULL,
  `employment_id` varchar(100) DEFAULT NULL,
  `company_affiliation` varchar(200) DEFAULT NULL,
  `status` enum('active','inactive','on_leave') DEFAULT 'active',
  `photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employeaccountreport`
--

CREATE TABLE `employeaccountreport` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employeaccountreport`
--

INSERT INTO `employeaccountreport` (`id`, `username`, `position`, `email`, `password`, `created_at`) VALUES
(1, 'wendhil himarangan', 'IT', 'wendhil10@gmail.com', '$2y$10$SQ0ZoVpzcvG/u0NdkT/YuuWyWEXe2rRGtmjwXSEQ1CsMQWBECgqgq', '2025-10-22 00:20:14'),
(2, 'Mark James F. Salvadora', 'I.T', 'rl_it@gmail.com', '$2y$10$NzXdPzBNitGUOgPo185o.eEV9mB69tBnnx6VMVYSJTz/HO.aqNg9C', '2025-10-22 00:25:04'),
(3, 'oneal0022', 'Marketing & IT Officer', 'macaserokahel@gmail.com', '$2y$10$z4MNw2AwUyp.UHBw7L9ckejD6r74r3JmnKzZ9pwfvSAgd9zl4heFa', '2025-10-22 02:27:52'),
(4, 'John Raphael', 'Graphic & Social Media', 'raphael.100folds@gmail.com', '$2y$10$1iwuUXzvxMjwJq.IK1PfFuVM3lKogm2JySufnscsU.4V5p74kvep2', '2025-10-22 05:42:02');

-- --------------------------------------------------------

--
-- Table structure for table `employee_tasks`
--

CREATE TABLE `employee_tasks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `task_title` varchar(255) NOT NULL,
  `task_description` text DEFAULT NULL,
  `task_type` enum('completed_today','planned_tomorrow','ongoing') DEFAULT 'ongoing',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `estimated_days` int(11) DEFAULT NULL,
  `actual_days` int(11) DEFAULT NULL,
  `status` enum('not_started','in_progress','completed','delayed') DEFAULT 'not_started',
  `progress_percentage` int(11) DEFAULT 0,
  `completed_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `delay_days` int(11) DEFAULT 0,
  `delay_start_date` date DEFAULT NULL,
  `is_rolled_over` tinyint(1) DEFAULT 0,
  `original_week_start` date DEFAULT NULL,
  `rollover_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_tasks`
--

INSERT INTO `employee_tasks` (`id`, `user_id`, `task_title`, `task_description`, `task_type`, `start_date`, `end_date`, `estimated_days`, `actual_days`, `status`, `progress_percentage`, `completed_date`, `created_at`, `updated_at`, `delay_days`, `delay_start_date`, `is_rolled_over`, `original_week_start`, `rollover_count`) VALUES
(2, 2, 'Logistics Adjustment', 'Adjusting the logic of the logistic to fit the process for the transfortify and other vehicle', 'ongoing', '2025-10-27', '2025-10-29', 8, NULL, 'completed', 100, '2025-10-28 07:44:44', '2025-10-22 00:31:30', '2025-10-28 07:44:44', 0, NULL, 0, '2025-10-22', 1),
(3, 1, 'layout find pro', 'planning what function that i include', 'ongoing', '2025-10-22', '2025-10-25', 4, NULL, 'completed', 100, '2025-10-23 05:16:15', '2025-10-22 00:34:58', '2025-10-23 05:16:24', 0, NULL, 0, NULL, 0),
(4, 1, 'layout of inspiration page', 'is like a history of each product why this product are best to choice', 'ongoing', '2025-10-22', '2025-10-22', 1, NULL, 'completed', 100, '2025-10-22 07:20:33', '2025-10-22 01:00:51', '2025-10-22 07:28:00', 0, NULL, 0, NULL, 0),
(5, 2, 'qr scanner page', 'integrated scanner page', 'ongoing', '2025-10-22', '2025-10-25', 4, NULL, 'completed', 100, '2025-10-22 01:44:22', '2025-10-22 01:40:53', '2025-10-22 07:28:00', 0, NULL, 0, NULL, 0),
(6, 2, 'Search Items', 'can search an items using the p.o number', 'ongoing', '2025-10-22', '2025-10-25', 4, NULL, 'completed', 100, '2025-10-22 01:44:27', '2025-10-22 01:41:53', '2025-10-22 07:28:00', 0, NULL, 0, NULL, 0),
(7, 2, 'updating status', 'updating status in the redirecting page when it is scan', 'ongoing', '2025-10-27', '2025-10-31', 4, NULL, 'completed', 100, '2025-10-28 07:45:11', '2025-10-22 01:43:20', '2025-10-28 07:45:11', 0, NULL, 0, '2025-10-22', 1),
(8, 2, 'generating qr code', 'this is used to generate qr code when the p.o number is search', 'ongoing', '2025-10-22', '2025-10-25', 4, NULL, 'completed', 100, '2025-10-22 01:46:26', '2025-10-22 01:46:05', '2025-10-22 07:28:00', 0, NULL, 0, NULL, 0),
(9, 3, 'Daily Monitoring', 'Monitoring all team', 'ongoing', '2025-10-22', '2025-10-25', 4, NULL, 'in_progress', 50, NULL, '2025-10-22 02:29:06', '2025-10-22 02:29:06', 0, NULL, 0, NULL, 0),
(19, 4, 'Daily Social Media Posting & Updates', '', 'ongoing', '2025-10-22', '2025-10-25', 4, NULL, 'completed', 100, '2025-10-22 06:03:28', '2025-10-22 05:45:19', '2025-10-22 07:28:00', 0, NULL, 0, NULL, 0),
(21, 4, 'Website wireframe & Layout', '', 'ongoing', '2025-10-22', '2025-10-24', 3, NULL, 'in_progress', 80, NULL, '2025-10-22 06:00:59', '2025-10-22 06:00:59', 0, NULL, 0, NULL, 0),
(22, 4, 'Company Profile', '', 'ongoing', '2025-10-22', '2025-10-25', 4, NULL, 'in_progress', 50, NULL, '2025-10-22 06:01:30', '2025-10-22 06:01:30', 0, NULL, 0, NULL, 0),
(23, 4, 'Product Catalogue', '', 'ongoing', '2025-10-22', '2025-10-25', 4, NULL, 'in_progress', 50, NULL, '2025-10-22 06:01:55', '2025-10-22 06:01:55', 0, NULL, 0, NULL, 0),
(24, 4, 'VIP Card Design', '', 'ongoing', '2025-10-27', '2025-11-03', 8, NULL, 'not_started', 0, NULL, '2025-10-22 06:09:48', '2025-10-22 06:09:48', 0, NULL, 0, NULL, 0),
(25, 4, 'Voucher Design', '', 'ongoing', '2025-10-27', '2025-11-03', 8, NULL, 'not_started', 0, NULL, '2025-10-22 06:10:05', '2025-10-22 06:10:05', 0, NULL, 0, NULL, 0),
(26, 4, 'Magazine', '', 'ongoing', '2025-11-03', '2025-11-07', 5, NULL, 'not_started', 0, NULL, '2025-10-22 06:10:59', '2025-10-22 06:10:59', 0, NULL, 0, NULL, 0),
(27, 4, 'Video Promotions, Reels, Advertisement', '', 'ongoing', '2025-10-22', '2025-10-25', 4, NULL, 'completed', 100, '2025-10-22 08:23:15', '2025-10-22 06:11:56', '2025-10-23 00:23:21', 0, NULL, 0, NULL, 0),
(29, 4, 'AAC Block Brochure', '', 'ongoing', '2025-10-23', '2025-10-28', 2, NULL, 'completed', 100, '2025-10-24 05:51:57', '2025-10-22 08:47:06', '2025-10-24 05:51:57', 0, NULL, 0, NULL, 0),
(30, 1, 'layout of inspiration Output UI ', '', 'ongoing', '2025-10-23', '2025-10-25', 3, NULL, 'completed', 100, '2025-10-23 00:32:12', '2025-10-23 00:31:56', '2025-10-23 00:32:12', 0, NULL, 0, NULL, 0),
(31, 1, 'layout of find pro page Output Ui', '', 'ongoing', '2025-10-27', '2025-10-28', 3, NULL, 'completed', 100, '2025-10-28 07:43:46', '2025-10-23 00:33:10', '2025-10-28 07:43:46', 0, NULL, 0, '2025-10-23', 1),
(32, 1, 'General computing new featured ', 'if a product has a target price and will have a 5% offer. For instance, if the price is 100,000, it will have a 2% discount (offer).', 'ongoing', '2025-10-27', '2025-11-08', 13, NULL, 'completed', 100, '2025-10-28 07:44:51', '2025-10-23 05:21:56', '2025-10-28 07:44:51', 0, NULL, 0, NULL, 0),
(33, 1, 'computation for AAC Block', 'this for to get per sqm like i input 50 sqm then automatic the calculate the many block needed and adhesive and bracket', 'ongoing', '2025-10-28', '2025-10-29', 2, NULL, 'completed', 100, '2025-10-28 07:46:36', '2025-10-28 07:46:31', '2025-10-28 07:46:40', 0, NULL, 0, NULL, 0),
(34, 2, 'Adding a subrole', 'in warehouse we will add a receiver of the item and for the logistic we will having a dispatcher for dispatching the items like loading it on the courrier or on the customer/client vehicle if it is pickup', 'ongoing', '2025-10-28', '2025-11-03', 7, NULL, 'in_progress', 90, NULL, '2025-10-28 07:49:53', '2025-10-28 07:49:53', 0, NULL, 0, NULL, 0),
(35, 2, 'Filtering in Accounting admin', 'adding a filter on every payment method and filtering also on which bank they pay in the qr code and on the bank transfer', 'ongoing', '2025-10-28', '2025-11-03', 7, NULL, 'completed', 100, '2025-10-29 08:17:42', '2025-10-28 07:51:43', '2025-10-29 08:17:42', 0, NULL, 0, NULL, 0),
(36, 2, 'Revising the replacement feature', 'Adjusting the replacement feature', 'ongoing', '2025-10-28', '2025-11-03', 7, NULL, 'in_progress', 0, NULL, '2025-10-28 07:53:41', '2025-10-28 07:53:41', 0, NULL, 0, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `images`
--

CREATE TABLE `images` (
  `id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `image_data` longblob NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `category` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nobleaccount`
--

CREATE TABLE `nobleaccount` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `lvl` enum('superadmin','sales','productspecialist','supplier','accountant','logistic','warehouse','hr') NOT NULL,
  `status` varchar(20) DEFAULT 'active',
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `fullname` varchar(255) NOT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `supplier_id` int(11) DEFAULT NULL,
  `sales_id` int(11) DEFAULT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT 0,
  `is_head` tinyint(1) NOT NULL DEFAULT 0,
  `subrole` varchar(100) DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT 0.00,
  `e_signature` longblob DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `remember_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nobleaccount`
--

INSERT INTO `nobleaccount` (`id`, `email`, `password`, `created_at`, `lvl`, `status`, `failed_attempts`, `locked_until`, `last_login`, `last_activity`, `fullname`, `verified`, `supplier_id`, `sales_id`, `is_online`, `is_head`, `subrole`, `commission_rate`, `e_signature`, `remember_token`, `remember_expires`) VALUES
(1, 'superadmin@gmail.com', '$2y$10$urBHsQL.wQX3KuWe0Z2XqOIfdyF.97Xw4VwSmcKw8f44yz7OFrosm', '2025-09-01 01:57:59', 'superadmin', 'active', 0, NULL, '2026-02-23 14:54:03', '2026-02-23 06:55:25', 'superadmin', 1, NULL, NULL, 0, 0, NULL, 0.00, NULL, NULL, NULL),
(2, 'sales1@gmail.com', '$2y$10$M4WbmBPQ2Jiggg7tKE4/Jec8HBO5rglxQLsYjgMqGf/.i27bfKWEi', '2025-09-01 01:58:57', 'sales', 'active', 0, NULL, '2026-02-24 08:30:09', '2026-02-24 00:35:37', 'sales', 1, NULL, 1, 0, 0, NULL, 5.00, NULL, NULL, NULL),
(3, 'sales2@gmail.com', '$2y$10$JT/JZriMzBySrkAgtTazEOJtb0qRoQ09csvjgOzbR/b91U18vO3e2', '2025-09-01 02:00:33', 'sales', 'active', 0, NULL, '2025-10-28 16:30:46', '2025-10-28 08:33:39', 'sales', 1, NULL, 2, 0, 0, NULL, 0.00, NULL, NULL, NULL),
(4, 'accountant@gmail.com', '$2y$10$OqWHrSMkQ2yNYAP0jAr0COszGjcf5jTZUd4zGObXe5Cmt9Jqd4G/i', '2025-09-01 02:01:24', 'accountant', 'active', 0, NULL, '2026-02-23 14:52:14', '2026-02-23 06:52:29', 'accountant', 1, NULL, NULL, 0, 0, NULL, 0.00, NULL, NULL, NULL),
(5, 'warehouse@gmail.com', '$2y$10$pdtiNsn./MXLUXal9c9A8.xj35YkFg5f4fKWwjIqsDAdA4lc01g5a', '2025-09-01 02:02:17', 'warehouse', 'active', 0, NULL, '2026-02-24 09:09:57', '2026-02-24 09:20:07', 'warehouse', 1, NULL, NULL, 1, 1, NULL, 0.00, NULL, NULL, NULL),
(6, 'hr@gmail.com', '$2y$10$R.8F1ttaddYDPN.2o4lFEebl.PlhaS4B6oWLKpF9hw.7IffhvOVge', '2025-09-01 02:03:29', 'hr', 'active', 0, NULL, '2026-02-23 07:57:21', '2026-02-23 05:53:14', 'Hr', 1, NULL, NULL, 0, 0, NULL, 0.00, NULL, NULL, NULL),
(7, 'ps@gmail.com', '$2y$10$G4CSbqB9fQT7r1wlklGEoOpvIZIpAHaGgTMom7TNf9pYwOBoE.czi', '2025-09-01 02:04:56', 'productspecialist', 'active', 0, NULL, '2026-02-23 14:46:54', '2026-02-23 06:48:58', 'productspecialist', 1, NULL, NULL, 0, 0, NULL, 0.00, NULL, NULL, NULL),
(8, 'logistic@gmail.com', '$2y$10$clemqgJxrMYrDHP9Q1GmYO0/M4/W515meIAQCzGJzjAb5uqrskUqe', '2025-09-01 02:05:33', 'logistic', 'active', 0, NULL, '2026-02-24 07:14:33', '2026-02-24 00:29:58', 'logistic', 1, NULL, NULL, 0, 1, NULL, 0.00, NULL, NULL, NULL),
(9, 'warehouse2@gmail.com', '$2y$10$0RN3GvC1XvEte6lnuauMUe2qkOaAlzi8dkEBI8TGLXNy1P4LZ55JS', '2025-09-01 02:12:56', 'warehouse', 'active', 0, NULL, '2026-02-24 08:48:40', '2026-02-24 01:09:44', 'warehouse_staff', 1, NULL, NULL, 0, 0, 'warehouse_staff', 0.00, NULL, NULL, NULL),
(10, 'sales3@gmail.com', '$2y$10$3dpTQqihJgyCRv5tRURxUeGZtJjRqfkN6W9kkzbA.qKKaeov5Zsfe', '2025-09-11 05:14:03', 'sales', 'active', 0, NULL, '2025-09-11 05:40:28', '2025-09-11 05:40:28', 'ralph', 1, NULL, 3, 0, 0, NULL, 0.00, NULL, NULL, NULL),
(11, 'accountant2@gmail.com', '$2y$10$qkKmTOxXfyZm5xnlLlq8xOsI/GktwkdQImxxkZV.3c7rdVtdkGPYy', '2025-09-30 03:28:02', 'accountant', 'active', 0, NULL, '2026-01-09 07:51:48', '2026-01-08 23:52:18', 'accountant', 0, NULL, NULL, 0, 0, NULL, 0.00, NULL, NULL, NULL),
(12, 'receiver@gmail.com', '$2y$10$kg1TxSQ7jOc13w5sRKFn8.Dcd4jGpUvp2Iui3zJv6MyPBwIoS.llS', '2025-11-05 23:56:27', 'warehouse', 'active', 0, NULL, '2026-02-24 08:46:34', '2026-02-24 00:48:28', 'receiver_warehouse', 0, NULL, NULL, 0, 0, 'warehouse_receiver', 0.00, NULL, NULL, NULL),
(13, 'dispatcher@gmail.com', '$2y$10$b5ZDRchUYEEN3KqqjnHGSecQllqLOHd3VThs6JfKyixX5u6BHvjbq', '2025-11-05 23:58:07', 'logistic', 'active', 0, NULL, '2026-02-23 15:14:54', '2026-02-23 07:51:23', 'Dispatcher_logistic', 0, NULL, NULL, 0, 0, 'dispatcher', 0.00, NULL, NULL, NULL),
(14, 'wendhilspecial@gmail.com', '$2y$10$.J1m.w/dbCv1y/9e2EKPXeKOu/B9yb8Sh2glrTCEGdEin/rNfZzhW', '2025-11-08 00:27:25', 'productspecialist', 'active', 0, NULL, '2025-11-08 08:28:04', '2025-11-08 00:28:11', 'Rolando Prudente', 0, NULL, NULL, 0, 0, NULL, 0.00, NULL, NULL, NULL),
(15, 'document@gmail.com', '$2y$10$2B/nkNA/yInTXdg5U3LgT.UCZglnuVH72Dq.sTR3MHtsGyos5K.bS', '2025-11-21 05:22:40', 'accountant', 'active', 0, NULL, '2026-02-23 14:55:47', '2026-02-23 06:56:07', 'Document Controller', 0, NULL, NULL, 0, 0, 'document_controller', 0.00, NULL, NULL, NULL),
(16, 'hr1@gmail.com', '$2y$10$EIggtMhyIJ0.rztJNt0i5eoQeHo/iM.lgEYzYU4t3Azol2MFrPKP6', '2026-02-11 06:26:42', 'productspecialist', 'active', 0, NULL, '2026-02-11 14:27:12', '2026-02-11 15:04:57', 'hr1', 0, NULL, NULL, 0, 0, NULL, 0.00, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `actor_id`, `type`, `message`, `is_read`, `created_at`) VALUES
(84, 21, NULL, 'verification_approved', '🎉 Great news! Your account verification has been approved by our admin team. You now have full access to all platform features.', 0, '2025-09-30 14:52:45'),
(85, 31, NULL, 'verification_approved', '🎉 Great news! Your account verification has been approved by our admin team. You now have full access to all platform features.', 1, '2025-10-11 11:27:02'),
(86, 16, 4, 'payment_verified', 'Your payment for Order #20 has been verified and confirmed by accountant. Amount: ₱29,298.11', 1, '2025-10-16 14:35:54'),
(88, 16, 4, 'payment_verified', 'Your payment for Order #11 has been verified and confirmed by accountant. Amount: ₱16,604.00', 1, '2025-10-16 14:47:11'),
(92, 16, 4, 'payment_verified', 'Your payment for Order #19 has been verified and confirmed by accountant. Amount: ₱682.10', 1, '2025-10-16 15:28:46'),
(93, 16, 4, 'payment_verified', 'Your payment for Order #15 has been verified and confirmed by accountant. Amount: ₱615.65', 1, '2025-10-16 15:28:55'),
(94, 16, 4, 'payment_verified', 'Your payment for Order #17 has been verified and confirmed by accountant. Amount: ₱578.69', 1, '2025-10-16 15:28:59'),
(95, 16, 4, 'payment_verified', 'Your payment for Order #14 has been verified and confirmed by accountant. Amount: ₱615.65', 1, '2025-10-16 15:29:09'),
(97, 16, 4, 'payment_verified', 'Your payment for Order #13 has been verified and confirmed by accountant. Amount: ₱615.65', 1, '2025-10-16 15:29:19'),
(99, 2, 4, 'payment_verified', 'Your payment for Order #8 has been verified and confirmed by accountant. Amount: ₱49,280.00', 1, '2025-10-16 15:35:30'),
(102, 9, 4, 'payment_verified', 'Your payment for Order #7 has been verified and confirmed by accountant. Amount: ₱616.00', 1, '2025-10-16 15:36:23'),
(107, 16, 4, 'payment_verified', 'Your payment for Order #31 has been verified and confirmed by accountant. Amount: ₱357.40', 1, '2025-11-06 07:51:46'),
(108, 16, NULL, 'order_confirmed', 'Your order #31 (Ref: NH9839164) has been confirmed and is now being processed. Please check your email or Spam.', 1, '2025-11-06 07:52:22'),
(109, 16, 4, 'payment_verified', 'Your payment for Order #32 has been verified and confirmed by accountant. Amount: ₱33,261.88', 1, '2025-11-07 12:44:00'),
(110, 16, NULL, 'order_confirmed', 'Your order #32 (Ref: NH9861910) has been confirmed and is now being processed. Please check your email or Spam.', 1, '2025-11-07 12:44:29'),
(111, 16, 4, 'payment_verified', 'Your payment for Order #33 has been verified and confirmed by accountant. Amount: ₱25,981.88', 1, '2025-11-08 08:22:42'),
(112, 16, NULL, 'order_confirmed', 'Your order #33 (Ref: NH9828950) has been confirmed and is now being processed. Please check your email or Spam.', 1, '2025-11-08 08:23:26'),
(113, 16, 4, 'payment_verified', 'Your payment for Order #34 has been verified and confirmed by accountant. Amount: ₱25,981.88', 1, '2025-11-08 08:40:38'),
(114, 16, NULL, 'order_confirmed', 'Your order #34 (Ref: NH9872121) has been confirmed and is now being processed. Please check your email or Spam.', 1, '2025-11-08 08:41:21'),
(115, 16, 4, 'payment_verified', 'Your payment for Order #35 has been verified and confirmed by accountant. Amount: ₱418.75', 1, '2025-11-13 08:09:45'),
(116, 16, NULL, 'order_confirmed', 'Your order #35 (Ref: NH9876750) has been confirmed and is now being processed. Please check your email or Spam.', 1, '2025-11-13 08:10:30'),
(119, 16, 4, 'payment_verified', 'Your payment for Order #44 has been verified and confirmed by accountant. Amount: ₱5,658.42', 1, '2025-11-21 13:21:11'),
(122, 38, NULL, 'verification_approved', 'Great news! Your account verification has been approved by our admin team. You now have full access to all platform features.', 0, '2026-02-19 12:58:44'),
(123, 17, 4, 'payment_verified', 'Your payment for Order #6 has been verified and confirmed by accountant. Amount: ₱1.12', 1, '2026-02-23 11:02:35'),
(124, 17, NULL, 'PICKUP_COMPLETED', 'Your order #6 has been picked up!', 1, '2026-02-23 14:23:14'),
(125, 16, 4, 'payment_verified', 'Your payment for Order #7 has been verified and confirmed by accountant. Amount: ₱1.12', 1, '2026-02-23 14:52:23'),
(126, 16, NULL, 'DELIVERY_COMPLETED', 'Your order #7 has been delivered! Please review the products', 1, '2026-02-23 15:15:52'),
(127, 16, NULL, 'DELIVERY_COMPLETED', 'Your order #7 has been delivered! Please review the products', 1, '2026-02-23 15:15:58'),
(128, 16, NULL, 'DELIVERY_COMPLETED', 'Your order #7 has been delivered! Please review the products', 1, '2026-02-23 15:19:41'),
(129, 16, NULL, 'DELIVERY_COMPLETED', 'Your order #7 has been delivered! Please review the products', 1, '2026-02-23 15:25:36'),
(130, 16, NULL, 'DELIVERY_COMPLETED', 'Your order #7 has been delivered! Please review the products', 1, '2026-02-24 07:55:22'),
(131, 16, NULL, 'DELIVERY_COMPLETED', 'Your order #7 has been delivered! Please review the products', 1, '2026-02-24 07:59:55'),
(132, 16, NULL, 'DELIVERY_COMPLETED', 'Your order #7 has been delivered! Please review the products', 1, '2026-02-24 08:13:45'),
(133, 16, NULL, 'DELIVERY_COMPLETED', 'Your order #7 has been delivered! Please review the products', 1, '2026-02-24 08:19:41'),
(134, 16, NULL, 'DELIVERY_COMPLETED', 'Your order #7 has been delivered! Please review the products', 1, '2026-02-24 08:27:39');

-- --------------------------------------------------------

--
-- Table structure for table `onsalebanner`
--

CREATE TABLE `onsalebanner` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `onsalebanner`
--

INSERT INTO `onsalebanner` (`id`, `filename`, `uploaded_at`) VALUES
(1, '../uploads/69603e918adb4.webp', '2026-01-08 23:32:33'),
(2, '../uploads/69603ea0684a5.webp', '2026-01-08 23:32:48');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `emp_id` int(11) DEFAULT NULL,
  `warehouse_employee_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `address` text DEFAULT NULL,
  `zipcode` varchar(10) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `completed_at` datetime DEFAULT NULL,
  `estimated_arrival_date` datetime DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `shipping_fee` decimal(10,2) DEFAULT 0.00,
  `final_total` decimal(10,2) DEFAULT 0.00,
  `mode_payment` varchar(50) DEFAULT NULL,
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `vat_amount` decimal(10,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rejection_reason` text DEFAULT NULL,
  `rejection_date` datetime DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `billing_address_id` int(11) DEFAULT NULL,
  `delivery_distance` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('pending','verified','rejected','paid') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `paymongo_session_id` varchar(255) DEFAULT NULL,
  `paymongo_payment_id` varchar(255) DEFAULT NULL,
  `paymongo_intent_id` varchar(255) DEFAULT NULL,
  `assigned_vehicle_id` int(11) DEFAULT NULL,
  `assigned_vehicle_type` varchar(100) DEFAULT NULL COMMENT 'Vehicle type name',
  `total_cubic_meters` decimal(10,3) DEFAULT 0.000 COMMENT 'Total cubic meters of order',
  `total_weight_kg` decimal(10,2) DEFAULT 0.00 COMMENT 'Total weight in kg',
  `total_width` decimal(10,2) DEFAULT 0.00 COMMENT 'Total width in meters',
  `total_height` decimal(10,2) DEFAULT 0.00 COMMENT 'Total height in meters',
  `total_length` decimal(10,2) DEFAULT 0.00 COMMENT 'Total length in meters',
  `delivery_type` enum('delivery','pickup') DEFAULT 'delivery' COMMENT 'Delivery or customer pickup',
  `sales_referral_code` varchar(20) DEFAULT NULL,
  `legacy_referral_user_id` int(11) DEFAULT NULL,
  `legacy_discount_amount` decimal(10,2) DEFAULT 0.00,
  `sales_commission_rate` decimal(5,2) DEFAULT 0.00,
  `sales_commission_amount` decimal(10,2) DEFAULT 0.00,
  `sales_user_id` int(11) DEFAULT NULL,
  `commission_claimed` tinyint(1) DEFAULT 0 COMMENT 'Tracks if this order commission has been claimed',
  `payment_confirmed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `emp_id`, `warehouse_employee_id`, `customer_name`, `email`, `mobile`, `total`, `created_at`, `address`, `zipcode`, `status`, `completed_at`, `estimated_arrival_date`, `discount`, `shipping_fee`, `final_total`, `mode_payment`, `delivery_fee`, `vat_amount`, `updated_at`, `rejection_reason`, `rejection_date`, `confirmed_at`, `rejected_at`, `latitude`, `longitude`, `reference_no`, `billing_address_id`, `delivery_distance`, `subtotal`, `payment_status`, `verified_by`, `rejected_by`, `reference_number`, `paymongo_session_id`, `paymongo_payment_id`, `paymongo_intent_id`, `assigned_vehicle_id`, `assigned_vehicle_type`, `total_cubic_meters`, `total_weight_kg`, `total_width`, `total_height`, `total_length`, `delivery_type`, `sales_referral_code`, `legacy_referral_user_id`, `legacy_discount_amount`, `sales_commission_rate`, `sales_commission_amount`, `sales_user_id`, `commission_claimed`, `payment_confirmed_at`) VALUES
(1, 17, NULL, NULL, 'BSIT4107_HIMARANGAN Wendhil', 'wendhil10@gmail.com', '09081031241', 1.12, '2026-02-21 01:55:29', '128 sitio pajo, quezon city, Metro Manila, Philippines', '1106', 'Processing', NULL, NULL, 0.00, 0.00, 0.00, 'QR Ph', 0.00, 0.12, '2026-02-21 01:55:29', NULL, NULL, NULL, NULL, 14.6631312, 121.0144921, 'NH9892610', 36, 0.00, 1.00, 'paid', NULL, NULL, NULL, NULL, 'pay_PEKKaueLHoiLrP1SUL8EsRaM', NULL, NULL, NULL, 0.000, 0.00, 0.00, 0.00, 0.00, 'pickup', NULL, NULL, 0.00, 0.00, 0.00, NULL, 0, '2026-02-21 01:55:29'),
(2, 17, NULL, NULL, 'BSIT4107_HIMARANGAN Wendhil', 'wendhil10@gmail.com', '09081031241', 1.12, '2026-02-21 01:58:23', '128 sitio pajo, quezon city, Metro Manila, Philippines', '1106', 'Processing', NULL, NULL, 0.00, 0.00, 0.00, 'QR Ph', 0.00, 0.12, '2026-02-21 01:58:23', NULL, NULL, NULL, NULL, 14.6631312, 121.0144921, 'NH9811332', 36, 0.00, 1.00, 'paid', NULL, NULL, NULL, NULL, 'pay_ES1mE3Pjn7eNcZoXbY2VVeKK', NULL, NULL, NULL, 0.000, 0.00, 0.00, 0.00, 0.00, 'pickup', NULL, NULL, 0.00, 0.00, 0.00, NULL, 0, '2026-02-21 01:58:23'),
(3, 17, NULL, NULL, 'BSIT4107_HIMARANGAN Wendhil', 'wendhil10@gmail.com', '09081031241', 1.12, '2026-02-21 02:03:18', '128 sitio pajo, quezon city, Metro Manila, Philippines', '1106', 'Processing', NULL, NULL, 0.00, 0.00, 0.00, 'QR Ph', 0.00, 0.12, '2026-02-21 02:03:18', NULL, NULL, NULL, NULL, 14.6631312, 121.0144921, 'NH9876134', 36, 0.00, 1.00, 'paid', NULL, NULL, NULL, NULL, 'pay_FK21uKA3phgXcQFfNg5Jkhfq', NULL, NULL, NULL, 0.000, 0.00, 0.00, 0.00, 0.00, 'pickup', NULL, NULL, 0.00, 0.00, 0.00, NULL, 0, '2026-02-21 02:03:18'),
(4, 17, NULL, NULL, 'BSIT4107_HIMARANGAN Wendhil', 'wendhil10@gmail.com', '09081031241', 1.12, '2026-02-21 02:14:34', '128 sitio pajo, quezon city, Metro Manila, Philippines', '1106', 'Processing', NULL, NULL, 0.00, 0.00, 0.00, 'QR Ph', 0.00, 0.12, '2026-02-21 02:14:34', NULL, NULL, NULL, NULL, 14.6631312, 121.0144921, 'NH9895585', 36, 0.00, 1.00, 'paid', NULL, NULL, NULL, NULL, 'pay_qdToVo4pSshhiokZb12cc2V4', NULL, NULL, NULL, 0.000, 0.00, 0.00, 0.00, 0.00, 'pickup', NULL, NULL, 0.00, 0.00, 0.00, NULL, 0, '2026-02-21 02:14:34'),
(5, 17, NULL, NULL, 'BSIT4107_HIMARANGAN Wendhil', 'wendhil10@gmail.com', '09081031241', 1.12, '2026-02-21 02:39:32', '128 sitio pajo, quezon city, Metro Manila, Philippines', '1106', 'Processing', NULL, NULL, 0.00, 0.00, 0.00, 'QR Ph', 0.00, 0.12, '2026-02-21 02:39:32', NULL, NULL, NULL, NULL, 14.6631312, 121.0144921, 'NH9876731', 36, 0.00, 1.00, 'paid', NULL, NULL, NULL, NULL, 'pay_8vHVVtikhKMAgKVxAXthahSx', NULL, NULL, NULL, 0.000, 0.00, 0.00, 0.00, 0.00, 'pickup', NULL, NULL, 0.00, 0.00, 0.00, NULL, 0, '2026-02-21 02:39:32'),
(6, 17, NULL, 9, 'BSIT4107_HIMARANGAN Wendhil', 'wendhil10@gmail.com', '09081031241', 1.12, '2026-02-21 02:54:19', '128 sitio pajo, quezon city, Metro Manila, Philippines', '1106', 'Completed', NULL, NULL, 0.00, 0.00, 1.12, 'QR Ph', 0.00, 0.12, '2026-02-23 06:36:18', NULL, NULL, '2026-02-23 03:02:35', NULL, 14.6631312, 121.0144921, 'NH9822515', 36, 0.00, 1.00, 'verified', 4, NULL, NULL, NULL, 'pay_7MtMBN8EaE66Prdp4MX5nFF1', NULL, NULL, NULL, 0.000, 0.00, 0.00, 0.00, 0.00, 'pickup', NULL, NULL, 0.00, 0.00, 0.00, NULL, 0, '2026-02-21 02:54:19'),
(7, 16, 2, 9, 'BSIT 4107-Salvadora, Mark James F.', 'salvadoramarkjamesfrayna@gmail.com', '09562604446', 1.12, '2026-02-23 06:50:43', 'Old Samson Road Balintawak, Quezon City, Metro Manila, Philippines', '1106', 'Delivered', NULL, NULL, 0.00, 0.00, 1.12, 'QR Ph', 0.00, 0.12, '2026-02-24 00:30:23', NULL, NULL, '2026-02-23 06:52:23', NULL, 14.6570037, 121.003376, 'NH9838852', 38, 0.00, 1.00, 'verified', 4, NULL, NULL, NULL, 'pay_Ct2zeooyuJjT5ZCW1bEAHpjx', NULL, 1, 'Sedan', 0.053, 125.00, 35.00, 25.00, 12.00, 'delivery', NULL, NULL, 0.00, 0.00, 0.00, NULL, 0, '2026-02-23 06:50:43');

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `after_order_delivered_record_sale` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    IF NEW.status = 'Delivered' AND OLD.status != 'Delivered' THEN
        -- Only record if not yet in sold_orders
        IF NOT EXISTS (SELECT 1 FROM sold_orders WHERE order_id = NEW.id) THEN
            CALL record_order_as_sold(NEW.id);
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_feedback`
--

CREATE TABLE `order_feedback` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `feedback_text` text DEFAULT NULL,
  `delivery_rating` int(11) DEFAULT NULL CHECK (`delivery_rating` >= 1 and `delivery_rating` <= 5),
  `product_quality_rating` int(11) DEFAULT NULL CHECK (`product_quality_rating` >= 1 and `product_quality_rating` <= 5),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_feedback`
--

INSERT INTO `order_feedback` (`id`, `order_id`, `user_id`, `email`, `rating`, `feedback_text`, `delivery_rating`, `product_quality_rating`, `created_at`) VALUES
(1, 6, 17, 'wendhil10@gmail.com', 5, 'test', 5, 5, '2026-02-23 06:24:16');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_name` varchar(100) DEFAULT NULL,
  `codename` varchar(100) DEFAULT NULL,
  `type_name` varchar(100) DEFAULT NULL,
  `variant_color` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `descrip6` text DEFAULT NULL,
  `descrip7` text DEFAULT NULL,
  `origin` varchar(255) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `manual_supplier_name` varchar(255) DEFAULT NULL,
  `po_number` varchar(50) DEFAULT NULL,
  `qr_code` varchar(100) DEFAULT NULL,
  `warehouse_location` varchar(255) DEFAULT NULL,
  `received_status` enum('pending','received') DEFAULT 'pending',
  `received_by` int(11) DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `color_id` int(11) DEFAULT NULL,
  `delivery_fee_per_item` decimal(10,2) DEFAULT 0.00,
  `item_total_delivery` decimal(10,2) DEFAULT 0.00,
  `lt_from` date DEFAULT NULL,
  `lt_to` date DEFAULT NULL,
  `tracking_status` varchar(125) DEFAULT 'pending' COMMENT 'Tracking status for individual items'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_name`, `codename`, `type_name`, `variant_color`, `price`, `quantity`, `subtotal`, `size`, `descrip6`, `descrip7`, `origin`, `supplier_id`, `manual_supplier_name`, `po_number`, `qr_code`, `warehouse_location`, `received_status`, `received_by`, `received_at`, `product_id`, `variant_id`, `color_id`, `delivery_fee_per_item`, `item_total_delivery`, `lt_from`, `lt_to`, `tracking_status`) VALUES
(1, 1, 'AAC ADHESIVES', 'AacBlock', 'AAC ADHESIVES', 'Normal', 0.20, 5, 1.00, '25kg', '', '', 'local', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, 28, 75, 273, 0.00, 0.00, NULL, NULL, 'pending'),
(2, 2, 'AAC ADHESIVES', 'AacBlock', 'AAC ADHESIVES', 'Normal', 0.20, 5, 1.00, '25kg', '', '', 'local', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, 28, 75, 273, 0.00, 0.00, NULL, NULL, 'pending'),
(3, 3, 'AAC ADHESIVES', 'AacBlock', 'AAC ADHESIVES', 'Normal', 0.20, 5, 1.00, '25kg', '', '', 'local', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, 28, 75, 273, 0.00, 0.00, NULL, NULL, 'pending'),
(4, 4, 'AAC ADHESIVES', 'AacBlock', 'AAC ADHESIVES', 'Normal', 0.20, 5, 1.00, '25kg', '', '', 'local', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, 28, 75, 273, 0.00, 0.00, NULL, NULL, 'pending'),
(5, 5, 'AAC ADHESIVES', 'AacBlock', 'AAC ADHESIVES', 'Normal', 0.20, 5, 1.00, '25kg', '', '', 'local', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, 28, 75, 273, 0.00, 0.00, NULL, NULL, 'pending'),
(6, 6, 'AAC ADHESIVES', 'AacBlock', 'AAC ADHESIVES', 'Normal', 0.20, 5, 1.00, '25kg', '', '', 'local', 2, NULL, 'NH022320261409072', 'https://noblehomedepot.com/admin/warehouse_management/receiver_scan_item_A1.php?item_id=6', 'ware 1', 'received', 12, '2026-02-23 14:18:26', 28, 75, 273, 0.00, 0.00, NULL, NULL, 'picked_up'),
(7, 7, 'AAC ADHESIVES', 'AacBlock', 'AAC ADHESIVES', 'Normal', 0.20, 5, 1.00, '25kg', '', '', 'local', 2, NULL, 'NH022320261453372', 'https://noblehomedepot.com/admin/warehouse_management/receiver_scan_item_A1.php?item_id=7', 'hello', 'received', 12, '2026-02-23 15:12:12', 28, 75, 273, 0.00, 0.00, NULL, NULL, 'delivered');

-- --------------------------------------------------------

--
-- Table structure for table `payment_qr_codes`
--

CREATE TABLE `payment_qr_codes` (
  `id` int(11) NOT NULL,
  `payment_method` varchar(50) NOT NULL COMMENT 'e.g., GCash, PayMaya, QR Ph, PayPal',
  `account_name` varchar(100) NOT NULL COMMENT 'Account holder name',
  `account_number` varchar(100) NOT NULL COMMENT 'Mobile number or account ID',
  `qr_code_image` varchar(255) NOT NULL COMMENT 'Path to QR code image',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '1 = Active, 0 = Inactive',
  `display_order` int(11) DEFAULT 0 COMMENT 'Order for displaying on frontend',
  `instructions` text DEFAULT NULL COMMENT 'Additional payment instructions',
  `created_by` int(11) NOT NULL COMMENT 'Admin user ID who created this',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL COMMENT 'Admin user ID who last updated'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores payment QR codes for various payment methods';

--
-- Dumping data for table `payment_qr_codes`
--

INSERT INTO `payment_qr_codes` (`id`, `payment_method`, `account_name`, `account_number`, `qr_code_image`, `is_active`, `display_order`, `instructions`, `created_by`, `created_at`, `updated_at`, `updated_by`) VALUES
(1, 'AUB', 'Noblehome', 'secret', 'uploads/qr_codes/qr_aub_1760597396.jpg', 1, 1, 'bayad muna bago bumili', 4, '2025-10-16 06:49:56', '2025-11-05 23:48:03', 4);

-- --------------------------------------------------------

--
-- Table structure for table `po_attachments`
--

CREATE TABLE `po_attachments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_replaced` tinyint(1) DEFAULT 0,
  `file_replaced_at` datetime DEFAULT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `po_number` varchar(100) DEFAULT NULL,
  `payment_terms` text DEFAULT NULL,
  `delivery_details` text DEFAULT NULL,
  `conditions` text DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `prepared_by` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `superadmin_approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `superadmin_approved_by` int(11) DEFAULT NULL,
  `superadmin_approved_at` datetime DEFAULT NULL,
  `superadmin_rejection_reason` text DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approval_requested_at` datetime DEFAULT NULL,
  `approval_requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `marked_as_ordered` tinyint(1) DEFAULT 0,
  `marked_as_ordered_at` datetime DEFAULT NULL,
  `po_status` varchar(50) DEFAULT NULL,
  `supplier_confirmed_at` datetime DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `out_for_delivery_at` datetime DEFAULT NULL,
  `currently_receiving_at` datetime DEFAULT NULL,
  `all_items_received` tinyint(1) DEFAULT 0,
  `all_items_received_at` datetime DEFAULT NULL,
  `status_updated_by` int(11) DEFAULT NULL,
  `marked_as_ordered_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `po_attachments`
--

INSERT INTO `po_attachments` (`id`, `order_id`, `supplier_name`, `original_filename`, `file_replaced`, `file_replaced_at`, `stored_filename`, `file_path`, `po_number`, `payment_terms`, `delivery_details`, `conditions`, `additional_notes`, `prepared_by`, `uploaded_at`, `superadmin_approval_status`, `superadmin_approved_by`, `superadmin_approved_at`, `superadmin_rejection_reason`, `approval_status`, `approval_requested_at`, `approval_requested_by`, `approved_by`, `approved_at`, `rejection_reason`, `marked_as_ordered`, `marked_as_ordered_at`, `po_status`, `supplier_confirmed_at`, `expected_delivery_date`, `out_for_delivery_at`, `currently_receiving_at`, `all_items_received`, `all_items_received_at`, `status_updated_by`, `marked_as_ordered_by`) VALUES
(1, 4, 'hello', 'PO_NH102120251103220_hello_warehouse.xlsx', 0, NULL, 'order_4_supplier_manual_hello_2025-10-21_11-03-40_68f6f80c1d91b.xlsx', '../../uploads/p.o_files/order_4_supplier_manual_hello_2025-10-21_11-03-40_68f6f80c1d91b.xlsx', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-21 03:03:40', 'approved', 1, '2026-02-23 14:10:41', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(2, 26, 'hello', 'PO_NH102420251402320_hello_warehouse.xlsx', 0, NULL, 'order_26_supplier_manual_hello_2025-10-24_14-03-01_68fb169564a54.xlsx', '../../uploads/p.o_files/order_26_supplier_manual_hello_2025-10-24_14-03-01_68fb169564a54.xlsx', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-24 06:03:01', 'pending', NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(3, 31, 'sample supplier', 'PO_NH11062025805190_sample_supplier_warehouse_staff.xlsx', 0, NULL, 'order_31_supplier_manual_sample_supplier_2025-11-06_08-05-49_690be65da897e.xlsx', '../../uploads/p.o_files/order_31_supplier_manual_sample_supplier_2025-11-06_08-05-49_690be65da897e.xlsx', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 00:05:49', 'pending', NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(4, 32, 'hello', 'PO_NH110720251246020_hello_warehouse_staff.xlsx', 0, NULL, 'order_32_supplier_manual_hello_2025-11-07_12-46-16_690d7998a3c76.xlsx', '../../uploads/p.o_files/order_32_supplier_manual_hello_2025-11-07_12-46-16_690d7998a3c76.xlsx', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-07 04:46:16', 'pending', NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(5, 33, 'sample', 'PO_NH11082025825160_sample_warehouse_staff.xlsx', 0, NULL, 'order_33_supplier_manual_sample_2025-11-08_08-28-41_690e8eb9c5b9c.xlsx', '../../uploads/p.o_files/order_33_supplier_manual_sample_2025-11-08_08-28-41_690e8eb9c5b9c.xlsx', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-08 00:28:41', 'pending', NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(6, 34, 'sample', 'PO_NH11082025842540_sample_warehouse_staff.xlsx', 0, NULL, 'order_34_supplier_manual_sample_2025-11-08_08-43-23_690e922bb5e4d.xlsx', '../../uploads/p.o_files/order_34_supplier_manual_sample_2025-11-08_08-43-23_690e922bb5e4d.xlsx', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-26 00:40:15', 'pending', NULL, NULL, NULL, 'approved', NULL, NULL, 15, '2025-11-26 08:40:15', NULL, 1, '2025-11-26 08:40:35', 'currently_receiving', '2025-11-26 08:41:37', '2025-11-28', '2025-11-26 08:41:45', '2025-11-26 08:41:49', 0, NULL, 9, 9),
(7, 35, 'Wendhil business', 'PO_NH11132025840081_Wendhil_business_warehouse_staff.xlsx', 0, NULL, 'order_35_supplier_1_2025-11-13_08-41-11_691529278c8ab.xlsx', '../../uploads/p.o_files/order_35_supplier_1_2025-11-13_08-41-11_691529278c8ab.xlsx', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-13 00:41:11', 'pending', NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(8, 41, 'Wendhil business', 'PO_NH111420251635361_Wendhil_business_warehouse_staff.xlsx', 0, NULL, 'order_41_supplier_1_2025-11-14_16-36-46_6916ea1e4b1c6.xlsx', '../../uploads/p.o_files/order_41_supplier_1_2025-11-14_16-36-46_6916ea1e4b1c6.xlsx', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-14 08:36:46', 'pending', NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(9, 44, 'Noblehome Construction Corp', 'PO_NH112120251333432_Noblehome_Construction_Corp_warehouse_staff (1).xlsx', 0, NULL, 'order_44_supplier_2_2025-11-21_13-34-29_691ff9e5bda12.xlsx', '../../uploads/po_files/PO_44_1763703331_691ffa23e0700.xlsx', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-21 05:35:31', 'pending', NULL, NULL, NULL, 'approved', NULL, NULL, 15, '2025-11-21 13:35:31', NULL, 1, '2025-11-21 13:37:00', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 9),
(10, 50, 'Noblehome Construction Corp', 'PO_NH112220251055592_Noblehome_Construction_Corp_warehouse_staff (1).xlsx', 0, NULL, 'order_50_supplier_2_2025-11-22_10-56-21_69212655b7f89.xlsx', '../../uploads/po_files/PO_50_1763780380_6921271cd4175.xlsx', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-22 02:59:40', 'pending', NULL, NULL, NULL, 'approved', NULL, NULL, 15, '2025-11-22 10:59:40', NULL, 1, '2025-11-22 11:00:13', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 9),
(11, 56, 'Noblehome Construction Corp', 'PO_NH01092026749272_Noblehome_Construction_Corp_warehouse_staff.pdf', 0, NULL, 'PO_NH01092026749272_Noblehome_Construction_Corp_2026-01-09_07-49-28_6960428824dcf.pdf', '../../uploads/p.o_files/PO_NH01092026749272_Noblehome_Construction_Corp_2026-01-09_07-49-28_6960428824dcf.pdf', 'NH01092026749272', '13', 'dadalhin sa USA at pupunta ng imigration at mag sisign ng contract para mas maging malakas para mabuo ko ang great wall of china', 'qweqweqwe', 'qweqweqwe', 'warehouse_staff', '2026-01-08 23:49:28', 'approved', 1, '2026-01-09 07:50:28', NULL, 'approved', NULL, NULL, 15, '2026-01-09 07:52:56', NULL, 1, '2026-01-09 07:54:24', 'received', '2026-01-09 07:55:41', '2030-12-09', '2026-01-09 07:56:00', '2026-01-09 07:57:30', 1, '2026-01-09 08:05:27', 5, 5),
(12, 6, 'Noblehome Construction Corp', 'PO_NH022320261409072_Noblehome_Construction_Corp_warehouse_staff.pdf', 0, NULL, 'PO_NH022320261409072_Noblehome_Construction_Corp_2026-02-23_14-09-07_699bef0392f66.pdf', '../../uploads/p.o_files/PO_NH022320261409072_Noblehome_Construction_Corp_2026-02-23_14-09-07_699bef0392f66.pdf', 'NH022320261409072', '7 days', 'test', 'test', 'test', 'warehouse_staff', '2026-02-23 06:09:07', 'approved', 1, '2026-02-23 14:10:38', NULL, 'approved', NULL, NULL, 15, '2026-02-23 14:12:15', NULL, 1, '2026-02-23 14:13:00', 'received', '2026-02-23 14:13:18', '2026-02-23', '2026-02-23 14:13:26', '2026-02-23 14:13:35', 1, '2026-02-23 14:18:51', 9, 9),
(13, 7, 'Noblehome Construction Corp', 'PO_NH022320261453372_Noblehome_Construction_Corp_warehouse_staff.pdf', 0, NULL, 'PO_NH022320261453372_Noblehome_Construction_Corp_2026-02-23_14-53-37_699bf971c5c1f.pdf', '../../uploads/p.o_files/PO_NH022320261453372_Noblehome_Construction_Corp_2026-02-23_14-53-37_699bf971c5c1f.pdf', 'NH022320261453372', '5', 'test', 'test', 'test', 'warehouse_staff', '2026-02-23 06:53:37', 'approved', 1, '2026-02-23 14:55:16', NULL, 'approved', NULL, NULL, 15, '2026-02-23 14:56:05', NULL, 1, '2026-02-23 14:57:15', 'received', '2026-02-23 14:57:33', '2026-03-06', '2026-02-23 14:57:38', '2026-02-23 14:57:46', 1, '2026-02-23 15:12:23', 9, 9);

-- --------------------------------------------------------

--
-- Table structure for table `po_receiver_assignments`
--

CREATE TABLE `po_receiver_assignments` (
  `id` int(11) NOT NULL,
  `po_attachment_id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `order_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` datetime NOT NULL,
  `status` enum('active','completed') DEFAULT 'active',
  `completed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `po_receiver_assignments`
--

INSERT INTO `po_receiver_assignments` (`id`, `po_attachment_id`, `po_number`, `order_id`, `receiver_id`, `assigned_by`, `assigned_at`, `status`, `completed_at`, `notes`) VALUES
(1, 6, 'NH11082025842540', 34, 12, 9, '2025-11-26 08:41:49', 'active', NULL, NULL),
(2, 11, 'NH01092026749272', 56, 12, 5, '2026-01-09 07:57:30', 'completed', '2026-01-09 08:05:27', 'Completed by user ID 12 on 2026-01-09 08:05:27'),
(3, 12, 'NH022320261409072', 6, 12, 9, '2026-02-23 14:13:35', 'completed', '2026-02-23 14:18:51', 'Completed by user ID 12 on 2026-02-23 14:18:51'),
(4, 13, 'NH022320261453372', 7, 12, 9, '2026-02-23 14:57:46', 'completed', '2026-02-23 15:12:23', 'Completed by user ID 12 on 2026-02-23 15:12:23');

-- --------------------------------------------------------

--
-- Table structure for table `po_status_logs`
--

CREATE TABLE `po_status_logs` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `po_status_logs`
--

INSERT INTO `po_status_logs` (`id`, `po_id`, `admin_id`, `old_status`, `new_status`, `notes`, `created_at`) VALUES
(1, 1, 1, 'pending', 'rejected', 'mali ito', '2025-11-28 07:40:48');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `codename` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `view_count` int(11) DEFAULT 0,
  `unique_view_count` int(11) DEFAULT 0,
  `price` decimal(10,2) DEFAULT NULL,
  `main_image` varchar(255) DEFAULT NULL,
  `sub_images` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `unit` varchar(255) DEFAULT NULL,
  `specification` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) NOT NULL DEFAULT 0,
  `descrip1` varchar(255) DEFAULT NULL,
  `descrip2` varchar(255) DEFAULT NULL,
  `descrip3` varchar(255) DEFAULT NULL,
  `descrip4` varchar(255) DEFAULT NULL,
  `descrip5` varchar(255) DEFAULT NULL,
  `descrip6` varchar(255) DEFAULT NULL,
  `descrip7` varchar(255) DEFAULT NULL,
  `descrip8` varchar(255) DEFAULT NULL,
  `descrip9` varchar(255) DEFAULT NULL,
  `descrip10` varchar(255) DEFAULT NULL,
  `product_images` longtext DEFAULT NULL CHECK (json_valid(`product_images`)),
  `descriptionpic` text DEFAULT NULL,
  `guide_enabled` tinyint(1) DEFAULT 0,
  `product_subcategory_id` int(11) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL COMMENT 'QR code filename',
  `is_archived` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_name`, `codename`, `quantity`, `view_count`, `unique_view_count`, `price`, `main_image`, `sub_images`, `description`, `created_at`, `unit`, `specification`, `updated_at`, `category_id`, `descrip1`, `descrip2`, `descrip3`, `descrip4`, `descrip5`, `descrip6`, `descrip7`, `descrip8`, `descrip9`, `descrip10`, `product_images`, `descriptionpic`, `guide_enabled`, `product_subcategory_id`, `qr_code`, `is_archived`) VALUES
(8, 'marine', 'buildingmaterials', 1, 17, 17, NULL, 'uploads/img_686e23b611f6a6.82459439.webp', '[\"sub_images\\/img_68c26e81b496c0.69731337.webp\",\"sub_images\\/img_68c26e81b5a236.78438855.webp\",\"sub_images\\/img_68c26e81bbb833.91144599.webp\",\"sub_images\\/img_68c26e81c690b6.38337301.webp\",\"sub_images\\/img_68c26e81cca811.77728610.webp\"]', 'Marine products made from various types of wood are designed to withstand moisture, making them ideal for boats, docks, and outdoor structures.', '2025-07-09 16:09:26', NULL, NULL, '2026-02-23 13:54:34', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '?PNG\r\n\Z\n\0\0\0\rIHDR\0\0@\0\0@\0\0\0B?2?\0\0\0	pHYs\0\0?\0\0??+\0\0?IDATx???Ar\"9\0??D???=;o?C0B??????Z???????\0M?~?\0?a?0C??!L?&`0?	?a?????ǅ?x?モ???x??W?]???+<?۸?}???ݰC??!L?&`0?	?a??ǃC???{X|?b???????:>?r|@????{q??\na?0C??!L?&`0?	?^\Z?X?', 1),
(19, 'Marine Double face', 'buildingmaterials', 1, 19, 19, NULL, '', '[]', 'Marine Board is a high-quality, water-resistant plywood designed to withstand moisture, making it ideal for kitchens, bathrooms, and areas exposed to humidity.', '2025-09-11 04:02:21', NULL, NULL, '2026-02-23 13:54:39', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '?PNG\r\n\Z\n\0\0\0\rIHDR\0\0@\0\0@\0\0\0B?2?\0\0\0	pHYs\0\0?\0\0??+\0\0?IDATx???An#9\0????????؃Ay(???#?cuK?*T??????????`??!L?&`0?	?a?0C??!????x{{?p/?7(?x?C&O??S7????7???????0C??!L?&`0?	?a?9?S\r??OO5?]???????uܓ_\'0?	?a?0C??!L?&`{j?ca?T', 1),
(23, 'AAC Blocks', 'AacBlock', 1, 158, 69, NULL, 'uploads/img_68da2141349447.19342965.webp', '[]', 'AAC Blocks are lightweight, durable, and eco-friendly building materials made from autoclaved aerated concrete. They provide excellent insulation, fire resistance, and easy workability, making construction faster, stronger, and more cost-efficient compared to traditional bricks.', '2025-09-23 02:53:03', NULL, NULL, '2026-02-22 16:09:08', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, 'qr_product_23.png', 0),
(24, 'Fiber Cement Board ( ECO-FLEX )', 'buildingmaterials', 1, 280, 267, NULL, 'uploads/img_68da217d7d2428.37419354.webp', '[]', 'Eco-Flex Fiber Cement Board – A durable and eco-friendly building material made from cement and natural fibers. It’s lightweight, strong, termite-resistant, and designed for walls, ceilings, and partitions. Perfect for sustainable and long-lasting construction.', '2025-09-24 01:37:12', NULL, NULL, '2026-02-23 18:25:59', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'qr_product_24.png', 0),
(25, 'Polished Tiles', 'Tiles', 1, 32, 24, NULL, 'uploads/img_68dc72953b3351.15140829.webp', '[]', 'High-quality ceramic or porcelain tiles with a smooth, glossy finish that brings out a luxurious and elegant look. Their mirror-like surface enhances the brightness of any space, making rooms appear bigger and more refined. Durable, easy to clean, and resistant to stains, polished tiles are ideal for living rooms, kitchens, bathrooms, and commercial spaces where style and practicality come together.', '2025-09-25 05:33:08', NULL, NULL, '2026-02-19 08:57:33', 0, '', '', '', '', '', 'unit:box', 'specification:4pcs', '', '', '', NULL, NULL, 0, NULL, 'qr_product_25.png', 0),
(26, 'Matte Tiles', 'Tiles', 1, 26, 25, NULL, 'uploads/img_68e484f4b04a01.14857362.webp', '[]', 'Matte tiles offer a sleek, non-reflective finish that brings a modern and sophisticated touch to any space. Their smooth yet slip-resistant surface makes them ideal for both floors and walls, providing durability, easy maintenance, and a timeless look. Perfect for creating a subtle elegance in kitchens, bathrooms, and living areas.', '2025-09-29 02:38:43', NULL, NULL, '2026-02-22 01:35:49', 0, '', '', '', '', '', 'unit:box', 'specifications:4pcs', '', '', '', NULL, NULL, 0, NULL, 'qr_product_26.png', 0),
(27, 'T8 TUBE LIGHT LED', 'lightingfixture', 1, 20, 20, NULL, 'uploads/img_68dc6ec0734fa2.97495866.webp', '[]', 'Brighten up your space with energy-efficient T8 LED Tube Lights. Designed for long-lasting performance, they provide excellent brightness, low power consumption, and a flicker-free experience perfect for offices, shops, and homes.', '2025-09-30 23:58:56', NULL, NULL, '2026-02-22 03:20:03', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'qr_product_27.png', 0),
(28, 'AAC ADHESIVES', 'AacBlock', 1, 138, 57, NULL, 'uploads/img_68de0a5ab2ff59.01336384.webp', '[]', 'AAC Block Adhesive is a specially formulated, ready-to-use thin-joint mortar designed for laying AAC (Autoclaved Aerated Concrete) blocks, concrete blocks, fly ash bricks, and other lightweight masonry units. Unlike conventional cement-sand mortar, AAC Adhesive ensures faster construction, higher bond strength, and reduced material consumption.', '2025-10-02 05:15:06', NULL, NULL, '2026-02-23 17:38:56', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'qr_product_28.png', 0),
(29, 'AAC BLOCKS BRACKETS', 'AacBlock', 1, 66, 47, NULL, 'uploads/img_68de0c13a74c75.26166001.webp', '[]', 'AAC Block Brackets are specially engineered metal supports designed to provide additional stability and reinforcement when laying AAC (Autoclaved Aerated Concrete) blocks. They help in securely connecting AAC blocks to adjoining walls, columns, or structural members, ensuring the masonry remains strong and durable.', '2025-10-02 05:22:27', NULL, NULL, '2026-02-23 00:15:43', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'qr_product_29.png', 0),
(51, 'LED PANEL LIGHT ( SURFACED TYPE )', 'lightingfixture', 1, 20, 20, NULL, 'uploads/img_1760064929_68e875a177dd4.webp', '[]', 'Illuminate your space with elegance and efficiency using our LED Panel Lights, available in three lighting tones — Warm Light, Natural White, and Daylight. Designed for both residential and commercial use, these slim and modern panels provide bright, even illumination that enhances any environment.', '2025-10-10 02:55:29', NULL, NULL, '2026-02-22 03:14:46', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'qr_product_51.png', 0),
(52, 'STRIPLIGHTS ', 'lightingfixture', 1, 31, 28, NULL, 'uploads/img_1760065469_68e877bdde72c.webp', '[]', 'Brighten your space with our LED Strip Light in Daylight color, designed to deliver clean, vibrant illumination perfect for modern interiors. The cool white tone enhances visibility and creates a refreshing atmosphere — ideal for workspaces, kitchens, retail displays, or any area that needs crisp, natural brightness.', '2025-10-10 03:04:30', NULL, NULL, '2026-02-23 01:17:09', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'qr_product_52.png', 0),
(77, 'SYCZ-001 SINTERED STONE DINING TABLE', 'furniture', 1, 12, 9, NULL, 'uploads/img_1770790573_698c1eada54aa.webp', '[]', 'Upgrade your dining space with the SYCZ-001 Sintered Stone Dining Table, designed for modern homes that value both style and durability. Featuring a premium sintered stone tabletop, this table offers a sleek marble-look finish while providing exceptional strength and long-lasting performance.\r\n\r\nThe surface is scratch-resistant, heat-resistant, and easy to clean, making it perfect for daily dining and special gatherings. Supported by a sturdy powder-coated metal base, the table ensures excellent stability and durability for years of use.\r\n\r\nWith its minimalist rectangular design, the SYCZ-001 blends seamlessly into contemporary, industrial, or luxury-inspired interiors. Ideal for 6–8 seaters, it provides ample space for family meals, meetings, or entertaining guests.', '2026-02-11 14:16:13', NULL, NULL, '2026-02-23 01:18:39', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(78, 'SOLID WOOD', 'furniture', 1, 1, 1, NULL, 'uploads/img_1770854262_698d177688e85.webp', NULL, 'Elevate your dining space with this beautifully crafted solid wood dining table set. Designed with smooth curves and a modern two-tone finish, this piece combines durability with elegant simplicity. The sturdy solid wood construction ensures long-lasting strength and stability, making it perfect for everyday meals and special gatherings.', '2026-02-12 07:57:42', NULL, NULL, '2026-02-16 07:39:52', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(79, 'BENZ TABLE', 'furniture', 1, 5, 5, NULL, 'uploads/img_1770854453_698d1835b47f0.webp', '[]', 'Inspired by luxury and refined design, the Benz Table (奔驰台) brings a bold yet elegant presence to any dining space. Crafted from premium solid wood, this table offers exceptional durability, stability, and long-lasting performance.', '2026-02-12 08:00:53', NULL, NULL, '2026-02-23 17:26:58', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(80, 'IRON FRAME ELEPHANT-LEG DINING TABLE', 'furniture', 1, 1, 1, NULL, 'uploads/img_1770855095_698d1ab742f29.webp', NULL, 'Make a bold statement in your dining space with this Iron Frame Elephant-Leg Dining Table. Designed with thick, sturdy “elephant-style” legs and a heavy-duty iron frame, this table offers exceptional stability and long-lasting durability.', '2026-02-12 08:11:35', NULL, NULL, '2026-02-20 11:13:17', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(81, 'SPCZ-004 DIAMOND-LEG DINING TABLE', 'furniture', 1, 20, 15, NULL, 'uploads/1770856333_main_Picture1.png', '[]', 'Upgrade your dining space with the SPCZ-004 Diamond-Leg Dining Table, designed to combine modern elegance with structural strength. Featuring a bold diamond-shaped leg design, this table delivers both eye-catching style and exceptional stability.', '2026-02-12 08:21:44', NULL, NULL, '2026-02-22 10:09:35', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(82, 'SYCZ-002 BLACK CROSS TABLE', 'furniture', 1, 8, 7, NULL, 'uploads/img_1770856548_698d20642d6e2.webp', NULL, 'The SYCZ-002 Black Cross Table combines bold design with reliable strength. Featuring a striking cross-base structure, this table delivers excellent stability while adding a modern, eye-catching touch to any space.', '2026-02-12 08:35:48', NULL, NULL, '2026-02-23 00:13:48', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(83, 'SYCZ-003 LARGE V-SHAPED DINING TABLE', 'furniture', 1, 8, 7, NULL, 'uploads/img_1770857733_698d250574a4f.webp', NULL, 'The SYCZ-003 Large V-Shaped Dining Table features a bold V-leg design that delivers both modern aesthetics and superior structural support. Its wide, stable base ensures excellent balance, while the spacious tabletop provides comfortable dining for family and guests.', '2026-02-12 08:55:33', NULL, NULL, '2026-02-22 08:06:52', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(84, 'SYCZ-006 TRIANGLE PIANO DINING TABLE', 'furniture', 1, 7, 7, NULL, 'uploads/img_1770965703_698ecac7f3349.webp', NULL, 'Crafted with a durable tabletop and a solid, well-balanced base, this dining table offers both stability and sophisticated style. The sculptural triangular support not only enhances visual appeal but also ensures strong load-bearing performance for everyday dining use.', '2026-02-13 14:55:04', NULL, NULL, '2026-02-23 01:36:57', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(85, 'SYCZ-008 CROSS BASE DINING TABLE', 'furniture', 1, 8, 6, NULL, 'uploads/img_1770965956_698ecbc42556a.webp', NULL, 'Built with a durable tabletop and a reinforced cross-leg foundation, this table ensures excellent stability and long-lasting performance. The clean lines and geometric silhouette complement contemporary, industrial, and minimalist interiors.', '2026-02-13 14:59:16', NULL, NULL, '2026-02-22 07:40:42', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(86, 'SYCZ-009 M-SHAPED DINING TABLE', 'furniture', 1, 9, 8, NULL, 'uploads/img_1771197598_6992549e39ca1.webp', NULL, 'The SYCZ-009 M-Shaped Dining Table features a modern and eye-catching M-shaped base design that brings both stability and style to your dining space. Crafted with a sturdy frame and a spacious tabletop, it provides ample room for family meals, gatherings, or casual everyday use.\r\n\r\nIts contemporary silhouette makes it a perfect centerpiece for modern homes, restaurants, or café interiors. The unique base structure not only enhances visual appeal but also ensures strong support and durability.', '2026-02-16 07:19:58', NULL, NULL, '2026-02-22 06:33:33', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(87, 'SYCZ-011 GOLD LARGE V DINING TABLE', 'furniture', 1, 8, 7, NULL, 'uploads/img_1771203450_69926b7a8e1c9.webp', NULL, 'The SYCZ-011 GOLD LARGE V DINING TABLE showcases a bold and elegant V-shaped base design finished in a luxurious gold tone. Its striking structure creates a strong visual statement while providing excellent stability and support.\r\n\r\nDesigned to elevate modern interiors, this dining table combines sophistication with functionality. The spacious tabletop comfortably accommodates family meals, gatherings, or formal dining occasions, making it a perfect centerpiece for upscale homes or stylish commercial spaces.', '2026-02-16 08:57:30', NULL, NULL, '2026-02-22 08:07:01', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(88, 'SYCZ-012 GOLD CROSS BASE TABLE', 'furniture', 1, 1, 1, NULL, 'uploads/img_1771203587_69926c035cacc.webp', NULL, 'The SYCZ-012 GOLD CROSS BASE TABLE features a modern cross-leg design with a luxurious gold finish, creating a bold and elegant statement in any dining space. The intersecting base structure not only enhances its visual appeal but also provides strong and stable support.\r\n\r\nDesigned for contemporary interiors, this table offers a spacious tabletop suitable for family meals, gatherings, or formal dining occasions. Its sleek and stylish silhouette makes it an ideal centerpiece for modern homes, restaurants, and upscale spaces.', '2026-02-16 08:59:47', NULL, NULL, '2026-02-23 14:41:38', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(89, 'SYCZ-013 PURPLE CROSS BASE TABLE', 'furniture', 1, 0, 0, NULL, 'uploads/img_1771203750_69926ca6d3e25.webp', NULL, 'The SYCZ-013 PURPLE CROSS BASE TABLE features a stylish cross-leg base design highlighted with a striking purple finish, bringing a bold and contemporary touch to any dining space. The intersecting base structure enhances both stability and visual appeal, making it a standout centerpiece.\r\n\r\nPerfect for modern interiors, this table offers a spacious and functional tabletop ideal for family meals, gatherings, or statement dining areas. Its unique color and geometric base design add personality while maintaining durability and strength.', '2026-02-16 09:02:31', NULL, NULL, '2026-02-16 09:02:31', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(90, 'SYCZ-015 DOUBLE D BARREL TABLE', 'furniture', 1, 0, 0, NULL, 'uploads/img_1771203953_69926d71cc1a3.webp', NULL, 'The SYCZ-015 DOUBLE D BARREL TABLE features a distinctive dual D-shaped barrel base design that delivers a bold and modern statement. Its unique structure combines artistic form with reliable stability, making it both decorative and functional.\r\n\r\nDesigned to stand out in contemporary interiors, this table offers a sturdy construction and a well-balanced silhouette suitable for dining areas, lounges, or stylish commercial spaces. The double D barrel base enhances visual impact while ensuring strong support.', '2026-02-16 09:05:54', NULL, NULL, '2026-02-16 09:05:54', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(91, 'SYCZ-016 T-SHAPED ACRYLIC TABLE', 'furniture', 1, 2, 2, NULL, 'uploads/img_1771204184_69926e58998d5.webp', NULL, 'The SYCZ-016 T-SHAPED ACRYLIC TABLE features a sleek T-shaped base design crafted with premium acrylic elements, offering a clean and modern aesthetic. Its transparent and glossy finish enhances the sense of space while adding a contemporary touch to any interior.\r\n\r\nDesigned for both style and durability, this table provides a stable structure and a spacious tabletop suitable for dining, meetings, or decorative setups. The minimalist silhouette makes it an ideal choice for modern homes, showrooms, cafés, and upscale spaces.', '2026-02-16 09:09:44', NULL, NULL, '2026-02-23 21:14:05', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(92, 'SYCZ-018 DOUBLE C DINING TABLE', 'furniture', 1, 5, 4, NULL, 'uploads/img_1771204326_69926ee6485cf.webp', NULL, 'The SYCZ-018 DOUBLE C DINING TABLE features a distinctive double C-shaped base design that delivers a bold and contemporary statement. Its symmetrical curved structure creates a unique visual appeal while providing excellent balance and stability.\r\n\r\nDesigned to complement modern interiors, this dining table offers a spacious tabletop ideal for family meals, gatherings, or formal dining occasions. The sculptural base design enhances elegance while ensuring strong and durable support.', '2026-02-16 09:12:06', NULL, NULL, '2026-02-23 15:50:02', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(93, 'SYCZ-019 RECTANGULAR COLUMN TABLE', 'furniture', 1, 1, 1, NULL, 'uploads/img_1771204447_69926f5fb63b5.webp', NULL, 'The SYCZ-019 RECTANGULAR COLUMN TABLE features a clean and structured rectangular column base design that delivers a modern and sophisticated look. Its solid vertical support provides excellent stability while maintaining a sleek and minimalist silhouette.\r\n\r\nDesigned for contemporary interiors, this table offers a spacious and practical tabletop suitable for dining, meetings, or decorative use. The strong geometric form enhances durability while adding a refined architectural touch to any space.', '2026-02-16 09:14:07', NULL, NULL, '2026-02-16 11:07:05', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(94, 'RING-SHAPED LONG TABLE', 'furniture', 1, 0, 0, NULL, 'uploads/img_1771204599_69926ff7cbf4f.webp', NULL, 'The RING-SHAPED LONG TABLE features a distinctive circular ring base design combined with an extended tabletop, creating a bold and elegant statement piece. Its sculptural structure offers both visual impact and reliable stability, making it a perfect centerpiece for modern interiors.\r\n\r\nDesigned for spacious dining areas or conference settings, this table provides ample surface space for gatherings, meetings, or formal occasions. The unique ring base enhances architectural appeal while ensuring strong and durable support.', '2026-02-16 09:16:40', NULL, NULL, '2026-02-16 09:16:40', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(95, 'SYCZ-020 (PANDORA / GLOSS SNOW MOUNTAIN STONE)', 'furniture', 1, 8, 7, NULL, 'uploads/img_1771204747_6992708b35c52.webp', NULL, 'The SYCZ-020 (PANDORA / GLOSS SNOW MOUNTAIN STONE) features a luxurious stone-inspired tabletop available in Pandora or Gloss Snow Mountain Stone finish. Its elegant surface showcases refined patterns and a high-gloss texture, creating a sophisticated and premium look.\r\n\r\nDesigned to elevate modern dining spaces, this table combines durability with timeless beauty. The smooth, polished finish enhances brightness and adds a refined touch, making it perfect for upscale homes, restaurants, and contemporary interiors.', '2026-02-16 09:19:07', NULL, NULL, '2026-02-22 09:29:53', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(96, 'SYCZ-021 FENDI-STYLE DINING TABLE', 'furniture', 1, 1, 1, NULL, 'uploads/img_1771205252_69927284d2433.webp', NULL, 'The SYCZ-021 FENDI-STYLE DINING TABLE showcases a luxurious and sophisticated design inspired by high-end contemporary aesthetics. With its refined detailing and elegant finish, this dining table brings a premium and stylish atmosphere to any space.\r\n\r\nCrafted for both beauty and durability, it features a spacious tabletop ideal for family dining, formal gatherings, or upscale interiors. Its modern silhouette and rich design elements make it a stunning centerpiece for luxury homes, restaurants, and designer spaces.\r\n\r\nKEY FEATURES:', '2026-02-16 09:27:32', NULL, NULL, '2026-02-23 13:03:47', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(97, 'MOON TABLE', 'furniture', 1, 7, 7, NULL, 'uploads/img_1771205431_69927337d0daf.webp', NULL, 'The MOON TABLE features a graceful and modern design inspired by the soft curves of the moon. Its rounded silhouette creates a warm and elegant atmosphere, making it a stunning centerpiece for dining or decorative spaces.\r\n\r\nDesigned to blend style and functionality, this table offers a smooth and spacious tabletop supported by a stable and durable base. The curved form adds a touch of sophistication while maintaining a clean and contemporary look.', '2026-02-16 09:30:31', NULL, NULL, '2026-02-22 05:18:28', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(98, 'SYCZ-022 TESLA-STYLE DINING TABLE (DESKTOP – PANDORA ONLY)', 'furniture', 1, 0, 0, NULL, 'uploads/img_1771205534_6992739e6c115.webp', NULL, 'The SYCZ-022 TESLA-STYLE DINING TABLE (DESKTOP – PANDORA ONLY) features a sleek and futuristic design inspired by modern luxury aesthetics. Designed with a Pandora-finish tabletop only, it delivers a refined stone-look surface that enhances elegance and sophistication in any dining space.\r\n\r\nIts clean lines and contemporary structure make it a perfect centerpiece for stylish homes and upscale interiors. The Pandora desktop finish provides a smooth, premium appearance while maintaining durability and functionality for everyday dining use.', '2026-02-16 09:32:14', NULL, NULL, '2026-02-16 09:32:14', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(99, 'SYCZ-023 (PANDORA)', 'furniture', 1, 1, 1, NULL, 'uploads/img_1771205656_6992741820914.webp', NULL, 'The SYCZ-023 (PANDORA) features a premium Pandora-finish tabletop that delivers a refined stone-inspired look with a smooth and elegant surface. Its sophisticated design enhances the overall ambiance of any dining space, combining modern style with timeless appeal.\r\n\r\nBuilt for both durability and aesthetics, this table offers a spacious and functional surface ideal for everyday dining, gatherings, or upscale interiors. The Pandora finish adds a luxurious touch while maintaining strength and practicality.\r\n\r\nKEY FEATURES:', '2026-02-16 09:34:16', NULL, NULL, '2026-02-24 01:04:57', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(100, 'SYCZ-024 SQUARE BARREL DINING TABLE', 'furniture', 1, 6, 6, NULL, 'uploads/img_1771205770_6992748ae9edb.webp', NULL, 'The SYCZ-024 SQUARE BARREL DINING TABLE features a bold and structured square barrel base design that combines modern geometry with strong visual presence. Its solid and well-balanced foundation provides excellent stability while enhancing the overall contemporary appeal.\r\n\r\nDesigned to complement modern dining interiors, this table offers a spacious and practical tabletop suitable for family meals, gatherings, or stylish commercial spaces. The clean lines and architectural form make it a striking centerpiece in any setting.', '2026-02-16 09:36:11', NULL, NULL, '2026-02-22 07:23:22', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(101, 'LARGE V-SHAPED LEG DINING TABLE', 'furniture', 1, 3, 3, NULL, 'uploads/img_1771374237_6995069de1588.webp', NULL, 'The Large V-Shaped Leg Dining Table features a bold and modern design highlighted by its striking angled V-style base. Crafted to create a strong visual statement, the uniquely slanted legs provide both stability and contemporary elegance.\r\n\r\nPerfect for dining rooms, event spaces, and stylish interiors, this table combines durability with a sleek architectural look. Its spacious tabletop offers ample room for family gatherings, meetings, or decorative setups.', '2026-02-18 08:23:58', NULL, NULL, '2026-02-22 08:23:37', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(102, 'HALF-MOON DINING TABLE', 'furniture', 1, 0, 0, NULL, 'uploads/img_1771374402_699507423d512.webp', NULL, 'The Half-Moon Dining Table features a graceful curved silhouette inspired by the elegant shape of a crescent moon. Its smooth, rounded design creates a soft and welcoming atmosphere, making it perfect for modern homes, event setups, and stylish commercial spaces.\r\n\r\nDesigned to maximize space while maintaining visual appeal, this table works beautifully against walls, in semi-circle arrangements, or as a statement piece in open layouts. The sturdy construction ensures stability, while the unique curved form adds a touch of sophistication and creativity.', '2026-02-18 08:26:42', NULL, NULL, '2026-02-18 08:26:42', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(103, 'SYCZ-028 DOUBLE ROUND-END DINING TABLE', 'furniture', 1, 2, 2, NULL, 'uploads/img_1771374761_699508a9cb996.webp', NULL, 'The SYCZ-028 Double Round-End Dining Table features a sleek rectangular body with smoothly rounded ends, combining modern structure with soft, elegant curves. The dual rounded edges create a balanced and refined look, making it a perfect centerpiece for dining areas, events, and contemporary interiors.\r\n\r\nDesigned for both style and functionality, this table offers a spacious surface while maintaining a visually light and sophisticated appearance. The rounded ends enhance safety and flow within the space, making it ideal for family dining or social gatherings.', '2026-02-18 08:32:42', NULL, NULL, '2026-02-22 17:08:37', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(104, 'THREE CYLINDER BASE DINING TABLE', 'furniture', 1, 3, 3, NULL, 'uploads/img_1771374881_69950921a82e3.webp', NULL, 'The Three Cylinder Base Dining Table showcases a bold and contemporary design supported by three solid cylindrical legs. This unique base structure creates a strong architectural statement while ensuring excellent stability and balance.\r\n\r\nIts clean tabletop combined with the rounded column supports adds a modern yet refined touch, making it ideal for dining spaces, event venues, and stylish interiors. The symmetrical cylinder base design enhances visual interest while maintaining a minimalist aesthetic.', '2026-02-18 08:34:41', NULL, NULL, '2026-02-22 16:48:40', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(105, 'SYCZ-029 WAIST ROUND DINING TABLE', 'furniture', 1, 0, 0, NULL, 'uploads/img_1771817871_699bcb8f6fd38.webp', NULL, 'The SYCZ-029 Waist Round Dining Table features a unique and elegant base design inspired by a slim “waist” silhouette, giving it a stylish and modern appearance. Its round tabletop creates a warm and intimate dining atmosphere, perfect for family meals and conversations.\r\n\r\nCrafted with a durable tabletop (sintered stone or marble-look finish option) and a sturdy sculpted base, this table combines beauty with stability. The smooth surface is easy to clean and maintain, making it ideal for everyday use as well as special gatherings.\r\n\r\nIts contemporary round design saves space while adding a sophisticated touch to any dining area, café, or reception space.', '2026-02-23 11:37:51', NULL, NULL, '2026-02-23 11:37:51', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(106, 'SYCZ-030 FOUR-LEAF CLOVER ROUND DINING TABLE', 'furniture', 1, 0, 0, NULL, 'uploads/img_1771817984_699bcc0056245.webp', NULL, 'The SYCZ-030 Four-Leaf Clover Round Dining Table features a uniquely designed base inspired by the shape of a four-leaf clover, symbolizing luck and harmony. Its elegant round tabletop promotes a warm and inviting dining experience, making it perfect for family gatherings and intimate meals.\r\n\r\nCrafted with a durable tabletop surface (available in sintered stone or marble-look finish), this table is resistant to scratches, heat, and stains—ensuring long-lasting beauty and easy maintenance. The sculptural clover-inspired base provides excellent stability while adding a distinctive modern touch to any space.\r\n\r\nIdeal for contemporary homes, cafés, and stylish dining areas, the SYCZ-030 combines meaningful design with everyday functionality.', '2026-02-23 11:39:44', NULL, NULL, '2026-02-23 11:39:44', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(107, 'SYCZ-031 RING ROUND DINING TABLE', 'furniture', 1, 0, 0, NULL, 'uploads/img_1771818086_699bcc6609560.webp', NULL, 'The SYCZ-031 Ring Round Dining Table showcases a sleek circular design complemented by a distinctive ring-style base, creating a clean and contemporary look. Its round tabletop encourages comfortable conversation and balanced seating, making it perfect for family meals, meetings, or intimate gatherings.\r\n\r\nDesigned with a durable tabletop surface (sintered stone or marble-look finish option), it offers excellent resistance to scratches, heat, and stains. The ring-inspired pedestal base not only enhances visual appeal but also provides strong structural support and stability.\r\n\r\nWith its modern minimalist aesthetic, the SYCZ-031 blends effortlessly into residential dining areas, cafés, and upscale commercial spaces.', '2026-02-23 11:41:26', NULL, NULL, '2026-02-23 11:41:26', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(108, 'SYCZ-032 BULLET-SHAPED ROUND DINING TABLE', 'furniture', 1, 0, 0, NULL, 'uploads/img_1771818222_699bccee990df.webp', NULL, 'The SYCZ-032 Bullet-Shaped Round Dining Table features a sleek pedestal base inspired by a smooth bullet silhouette, giving it a bold yet refined modern look. Its round tabletop creates a balanced and inviting dining setup, perfect for family meals, gatherings, or stylish commercial spaces.\r\n\r\nCrafted with a premium sintered stone or marble-look tabletop, this table is designed to resist scratches, heat, and stains while maintaining its elegant finish. The solid bullet-shaped base provides excellent stability and strong structural support, ensuring long-lasting durability.\r\n\r\nWith its minimalist form and sculptural presence, the SYCZ-032 adds a contemporary statement to any dining area.', '2026-02-23 11:43:42', NULL, NULL, '2026-02-23 11:43:42', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(109, 'SYCZ-033 GOLD CC ROUND DINING TABLE', 'furniture', 1, 0, 0, NULL, 'uploads/img_1771818306_699bcd4253f6c.webp', NULL, 'The SYCZ-033 Gold CC Round Dining Table features a striking double “C” pedestal base finished in elegant gold, creating a bold and luxurious statement. Its round tabletop design promotes a warm and intimate dining experience, perfect for family gatherings or stylish entertaining.\r\n\r\nCrafted with a durable sintered stone or marble-look tabletop, this table offers excellent resistance to scratches, heat, and stains while maintaining a refined, high-end appearance. The sculptural Gold CC base not only enhances its modern aesthetic but also provides strong and stable support.\r\n\r\nIdeal for contemporary homes, upscale dining areas, and elegant commercial spaces, the SYCZ-033 blends sophistication with everyday functionality.', '2026-02-23 11:45:06', NULL, NULL, '2026-02-23 11:45:06', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(110, 'SYCZ-034 HOURGLASS ROUND DINING TABLE', 'furniture', 1, 0, 0, NULL, 'uploads/img_1771818421_699bcdb55f514.webp', NULL, 'The SYCZ-034 Hourglass Round Dining Table features a beautifully sculpted base inspired by the elegant curves of an hourglass. Its refined silhouette creates a striking visual centerpiece while maintaining a clean and modern aesthetic.\r\n\r\nThe round tabletop promotes comfortable conversation and balanced seating, making it ideal for family dining, intimate gatherings, or stylish commercial spaces. Crafted with a premium sintered stone or marble-look surface, the tabletop is resistant to scratches, heat, and stains—ensuring durability and easy maintenance for everyday use.\r\n\r\nThe hourglass pedestal base provides strong structural support and excellent stability, combining artistic design with reliable functionality.', '2026-02-23 11:47:01', NULL, NULL, '2026-02-23 11:47:01', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(111, 'SYCZ-035 TEXT DESIGN ROUND DINING TABLE', 'furniture', 1, 0, 0, NULL, 'uploads/img_1771818607_699bce6f07566.webp', NULL, 'The SYCZ-035 Text Design Round Dining Table features a distinctive pedestal base inspired by artistic lettering or character-style design, creating a bold and eye-catching centerpiece. Its round tabletop offers a balanced and inviting dining experience, perfect for family gatherings, meetings, or stylish commercial spaces.\r\n\r\nCrafted with a premium sintered stone or marble-look surface, the tabletop is resistant to scratches, heat, and stains, ensuring long-lasting beauty and easy maintenance. The uniquely designed base not only enhances the modern aesthetic but also provides strong and stable support.\r\n\r\nWith its creative structure and contemporary appeal, the SYCZ-035 adds personality and sophistication to any dining area.', '2026-02-23 11:50:07', NULL, NULL, '2026-02-23 11:50:07', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(112, 'SYCZ-036 LOTUS FIXED ROUND DINING TABLE', 'furniture', 1, 0, 0, NULL, 'uploads/img_1771818853_699bcf659f3f5.webp', NULL, 'The SYCZ-036 Lotus Fixed Round Dining Table showcases a beautifully crafted pedestal base inspired by the elegant shape of a blooming lotus flower. Its sculptural design adds a graceful and luxurious touch to any dining space while maintaining a clean, modern look.\r\n\r\nThe fixed round tabletop creates a warm and balanced dining experience, ideal for family meals, gatherings, or refined commercial interiors. Made with a premium sintered stone or marble-look surface, the tabletop is scratch-resistant, heat-resistant, and easy to maintain for everyday use.\r\n\r\nThe lotus-inspired base provides strong and stable support, combining artistic beauty with reliable durability.', '2026-02-23 11:54:13', NULL, NULL, '2026-02-23 11:54:13', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(113, 'NEST ROUND DINING TABLE', 'furniture', 1, 0, 0, NULL, 'uploads/img_1771818958_699bcfcea4020.webp', NULL, 'The Nest Round Dining Table features a uniquely designed base inspired by the intricate structure of a bird’s nest. Its artistic framework creates a striking visual effect while maintaining strong and stable support.\r\n\r\nThe round tabletop encourages warm conversation and balanced seating, making it perfect for family dining or stylish commercial spaces. Crafted with a durable sintered stone or marble-look surface, it is resistant to scratches, heat, and stains—ensuring long-lasting performance and easy maintenance.', '2026-02-23 11:55:58', NULL, NULL, '2026-02-23 11:55:58', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0),
(114, 'INK ORCHID PURPLE DOUBLE C ROUND DINING TABLE', 'furniture', 1, 0, 0, NULL, 'uploads/img_1771819034_699bd01a12c3f.webp', NULL, 'The Ink Orchid Purple Double C Round Dining Table features a striking double “C” pedestal base finished in an elegant ink orchid purple tone, creating a bold yet refined statement piece. Its sculptural base design adds modern sophistication while ensuring strong and stable support.\r\n\r\nThe round tabletop promotes balanced seating and a warm dining atmosphere, perfect for family gatherings or upscale commercial spaces. Crafted with a premium sintered stone or marble-look surface, it offers excellent resistance to scratches, heat, and stains for long-lasting durability and easy maintenance.', '2026-02-23 11:57:14', NULL, NULL, '2026-02-23 11:57:14', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `product_colors`
--

CREATE TABLE `product_colors` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `color_name` varchar(255) DEFAULT NULL,
  `color_code` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `image2` varchar(255) DEFAULT NULL,
  `stock` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_colors`
--

INSERT INTO `product_colors` (`id`, `product_id`, `color_name`, `color_code`, `price`, `image`, `image2`, `stock`) VALUES
(9, 8, 'white', '', 5.00, 'uploads/img_686e23b6327dc4.05460925.webp', NULL, 0),
(10, 8, 'blue', '', 10.00, 'uploads/img_686e23b63a8558.32161970.webp', NULL, 0),
(11, 8, 'yellow', '', 15.00, 'uploads/img_686e23b644e802.66456187.webp', NULL, 0),
(12, 8, 'red', '', 20.00, 'uploads/img_686e23b64cbb51.89489377.webp', NULL, 0),
(13, 8, 'skyblue', '', 25.00, 'uploads/img_686e23b656d8c2.32011009.webp', NULL, 0),
(14, 8, 'violet', '', 30.00, 'uploads/img_686e23b6632609.88054852.webp', NULL, 0),
(15, 8, 'green', '', 35.00, 'uploads/img_686e23b66b6dd5.19280773.webp', NULL, 0),
(16, 8, 'gold', '', 39.99, 'uploads/img_686e23b673ea14.91225163.webp', NULL, 0),
(26, 19, 'AG-062', '', 0.00, '', NULL, 0),
(27, 19, 'AG-061', '', 0.00, '', NULL, 0),
(28, 19, 'AG-065', '', 0.00, '', NULL, 0),
(29, 19, 'AG-068', '', 0.00, '', NULL, 0),
(30, 19, 'AG-070', '', 0.00, '', NULL, 0),
(31, 19, 'AG-069', '', 0.00, '', NULL, 0),
(32, 19, 'AG-067', '', 0.00, '', NULL, 0),
(33, 19, 'AG-066', '', 0.00, '', NULL, 0),
(34, 19, 'AG-060', '', 0.00, '', NULL, 0),
(35, 19, 'AG-063', '', 0.00, '', NULL, 0),
(36, 19, 'ES-001', '', 0.00, '', NULL, 0),
(37, 19, 'ES-003', '', 0.00, '', NULL, 0),
(38, 19, 'ES-006', '', 0.00, '', NULL, 0),
(39, 19, 'ES-009', '', 0.00, '', NULL, 0),
(40, 19, 'ES-015', '', 0.00, '', NULL, 0),
(41, 19, 'ES-011', '', 0.00, '', NULL, 0),
(42, 19, 'ES-013', '', 0.00, '', NULL, 0),
(43, 19, 'ES-020', '', 0.00, '', NULL, 0),
(44, 19, 'ES-022', '', 0.00, '', NULL, 0),
(45, 19, 'ES-023', '', 0.00, '', NULL, 0),
(46, 19, 'ES-026', '', 0.00, '', NULL, 0),
(47, 19, 'ES-028', '', 0.00, '', NULL, 0),
(48, 19, 'WN-001', '', 0.00, '', NULL, 0),
(49, 19, 'WN-004', '', 0.00, '', NULL, 0),
(50, 19, 'MYS-9043', '', 0.00, '', NULL, 0),
(51, 19, 'MYS-9044', '', 0.00, '', NULL, 0),
(52, 19, 'MYS-9046', '', 0.00, '', NULL, 0),
(53, 19, 'HY-9007', '', 0.00, '', NULL, 0),
(54, 19, 'HY-9002', '', 0.00, '', NULL, 0),
(55, 19, 'HY-9003', '', 0.00, '', NULL, 0),
(56, 19, 'KH-9011', '', 0.00, '', NULL, 0),
(57, 19, 'KH-9014', '', 0.00, '', NULL, 0),
(58, 19, 'KH-9016', '', 0.00, '', NULL, 0),
(59, 19, 'KH-9017', '', 0.00, '', NULL, 0),
(60, 19, 'KH-9020', '', 0.00, '', NULL, 0),
(61, 19, 'KH-9021', '', 0.00, '', NULL, 0),
(62, 19, 'KH-9023', '', 0.00, '', NULL, 0),
(63, 19, 'ZS-9033', '', 0.00, '', NULL, 0),
(64, 19, 'KH-9026', '', 0.00, '', NULL, 0),
(65, 19, 'KH-9022', '', 0.00, '', NULL, 0),
(66, 19, 'KH-9024', '', 0.00, '', NULL, 0),
(67, 19, 'KH-9028', '', 0.00, '', NULL, 0),
(68, 19, 'KH-9029', '', 0.00, '', NULL, 0),
(69, 19, 'KH-9015', '', 0.00, '', NULL, 0),
(70, 19, 'KH-9013', '', 0.00, '', NULL, 0),
(71, 19, 'KH-9012', '', 0.00, '', NULL, 0),
(72, 19, 'HY-9001', '', 0.00, '', NULL, 0),
(73, 19, 'HY-9004', '', 0.00, '', NULL, 0),
(74, 19, 'HY-9006', '', 0.00, '', NULL, 0),
(75, 19, 'HY-9005', '', 0.00, '', NULL, 0),
(76, 19, 'MYS-9045', '', 0.00, '', NULL, 0),
(77, 19, 'MYS-9042', '', 0.00, '', NULL, 0),
(78, 19, 'MYS-9048', '', 0.00, '', NULL, 0),
(79, 19, 'WN-002', '', 0.00, '', NULL, 0),
(80, 19, 'ES-027', '', 0.00, '', NULL, 0),
(81, 19, 'ES-017', '', 0.00, '', NULL, 0),
(82, 19, 'ES-024', '', 0.00, '', NULL, 0),
(83, 19, 'ES-021', '', 0.00, '', NULL, 0),
(84, 19, 'ES-019', '', 0.00, '', NULL, 0),
(85, 19, 'ES-014', '', 0.00, '', NULL, 0),
(86, 19, 'ES-012', '', 0.00, '', NULL, 0),
(87, 19, 'ES-008', '', 0.00, '', NULL, 0),
(88, 19, 'ES-007', '', 0.00, '', NULL, 0),
(89, 19, 'ES-005', '', 0.00, '', NULL, 0),
(90, 19, 'ES-004', '', 0.00, '', NULL, 0),
(91, 19, 'ES-002', '', 0.00, '', NULL, 0),
(92, 8, '1', '', 0.00, 'uploads/img_68c26f03987a78.32562380_color.webp', NULL, 0),
(109, 8, '2', '', 0.00, 'uploads/img_68c272784e16f6.88049982_color.webp', NULL, 0),
(110, 8, '3', '', 0.00, 'uploads/img_68c27359b34082.68692149_color.webp', NULL, 0),
(111, 8, '4', '', 0.00, 'uploads/img_68c2784acb3633.88109943_color.webp', NULL, 0),
(112, 8, '5', '', 0.00, 'uploads/img_68c278706cf0d3.59353691_color.webp', NULL, 0),
(113, 8, '6', '', 0.00, 'uploads/img_68c2789d593b85.87630068_color.webp', NULL, 0),
(114, 8, '7', '', 0.00, 'uploads/img_68c278c5932f72.61373253_color.webp', NULL, 0),
(115, 8, '8', '', 0.00, 'uploads/img_68c278eb665669.29460726_color.webp', NULL, 0),
(116, 8, '9', '', 0.00, 'uploads/img_68c2791f4f95f0.58832238_color.webp', NULL, 0),
(117, 8, '10', '', 0.00, 'uploads/img_68c2794a581515.47164326_color.webp', NULL, 0),
(118, 8, '11', '', 0.00, 'uploads/img_68c279758fdc00.26776763_color.webp', NULL, 0),
(119, 8, '12', '', 0.00, 'uploads/img_68c279b4787954.87961954_color.webp', NULL, 0),
(120, 8, '13', '', 0.00, 'uploads/img_68c27a54211a38.96429589_color.webp', NULL, 0),
(121, 8, '14', '', 0.00, 'uploads/img_68c27a61cce1c5.60423159_color.webp', NULL, 0),
(122, 8, '15', '', 0.00, 'uploads/img_68c27a6f2e5119.47313264_color.webp', NULL, 0),
(123, 8, '16', '', 0.00, 'uploads/img_68c27a7cb772d7.76332325_color.webp', NULL, 0),
(124, 8, '17', '', 0.00, 'uploads/img_68c27a8b187537.71849092_color.webp', NULL, 0),
(125, 8, '18', '', 0.00, 'uploads/img_68c27bc0774d89.20657701_color.webp', NULL, 0),
(126, 8, '19', '', 0.00, 'uploads/img_68c27bccd6ae26.42844065_color.webp', NULL, 0),
(127, 8, '20', '', 0.00, 'uploads/img_68c27bd91a5546.49528872_color.webp', NULL, 0),
(128, 8, '21', '', 0.00, 'uploads/img_68c27c6b759f74.98549226_color.webp', NULL, 0),
(129, 8, '22', '', 0.00, 'uploads/img_68c27c78369387.85790378_color.webp', NULL, 0),
(130, 8, '23', '', 0.00, 'uploads/img_68c27c858d3755.19472165_color.webp', NULL, 0),
(131, 8, '24', '', 0.00, 'uploads/img_68c27cd0b736e0.22008101_color.webp', NULL, 0),
(132, 8, '25', '', 0.00, 'uploads/img_68c27cde22ba73.42817967_color.webp', NULL, 0),
(133, 8, '26', '', 0.00, 'uploads/img_68c27ceb4a31f8.89457525_color.webp', NULL, 0),
(134, 8, '27', '', 0.00, 'uploads/img_68c27d4c4608a1.35636052_color.webp', NULL, 0),
(135, 8, '28', '', 0.00, 'uploads/img_68c27d5946ae05.23928533_color.webp', NULL, 0),
(136, 8, '29', '', 0.00, 'uploads/img_68c27d67516be9.42882818_color.webp', NULL, 0),
(137, 8, '30', '', 0.00, 'uploads/img_68c27db525c2a6.15596249_color.webp', NULL, 0),
(138, 8, '31', '', 0.00, 'uploads/img_68c27dc215a718.31511531_color.webp', NULL, 0),
(139, 8, '32', '', 0.00, 'uploads/img_68c27dcfc81c92.12893687_color.webp', NULL, 0),
(140, 8, '33', '', 0.00, 'uploads/img_68c27e29f104b5.98940476_color.webp', NULL, 0),
(141, 8, '34', '', 0.00, 'uploads/img_68c27e37942e64.95623334_color.webp', NULL, 0),
(142, 8, '35', '', 0.00, 'uploads/img_68c27e45646313.29696027_color.webp', NULL, 0),
(143, 8, '36', '', 0.00, 'uploads/img_68c27e94510d37.77805986_color.webp', NULL, 0),
(144, 8, '37', '', 0.00, 'uploads/img_68c27ea1469240.55560586_color.webp', NULL, 0),
(145, 8, '38', '', 0.00, 'uploads/img_68c27eaeb579c6.05403467_color.webp', NULL, 0),
(146, 8, '39', '', 0.00, 'uploads/img_68c27eec5bae32.16865147_color.webp', NULL, 0),
(147, 8, '40', '', 0.00, 'uploads/img_68c27ef960b1f9.98222801_color.webp', NULL, 0),
(148, 8, '41', '', 0.00, 'uploads/img_68c27f05e48652.87861334_color.webp', NULL, 0),
(149, 8, '42', '', 0.00, 'uploads/img_68c27f78752967.36915111_color.webp', NULL, 0),
(150, 8, '43', '', 0.00, 'uploads/img_68c27f8772d368.34179546_color.webp', NULL, 0),
(151, 8, '44', '', 0.00, 'uploads/img_68c27f94ce8c42.50283385_color.webp', NULL, 0),
(152, 8, '45', '', 0.00, 'uploads/img_68c280561fc2f2.62986335_color.webp', NULL, 0),
(153, 8, '46', '', 0.00, 'uploads/img_68c28062f23f66.73230345_color.webp', NULL, 0),
(154, 8, '47', '', 0.00, 'uploads/img_68c2806f8a5112.99041081_color.webp', NULL, 0),
(155, 8, '48', '', 0.00, 'uploads/img_68c280ce287145.99506139_color.webp', NULL, 0),
(156, 8, '49', '', 0.00, 'uploads/img_68c280dd23b856.68902060_color.webp', NULL, 0),
(157, 8, '50', '', 0.00, 'uploads/img_68c280eb7b2388.39398005_color.webp', NULL, 0),
(158, 8, '51', '', 0.00, 'uploads/img_68c281ad4a6740.76471712_color.webp', NULL, 0),
(159, 8, '52', '', 0.00, 'uploads/img_68c281bb9fa8c4.04534921_color.webp', NULL, 0),
(160, 8, '53', '', 0.00, 'uploads/img_68c281c905b334.89336752_color.webp', NULL, 0),
(161, 8, '54', '', 0.00, 'uploads/img_68c28459cefaf3.51105312_color.webp', NULL, 0),
(162, 8, '55', '', 0.00, 'uploads/img_68c2846683dc92.39364704_color.webp', NULL, 0),
(163, 8, '56', '', 0.00, 'uploads/img_68c2847544d226.14569526_color.webp', NULL, 0),
(164, 8, '57', '', 0.00, 'uploads/img_68c284beb03769.05929482_color.webp', NULL, 0),
(165, 8, '58', '', 0.00, 'uploads/img_68c284cd4009c2.12062285_color.webp', NULL, 0),
(166, 8, '59', '', 0.00, 'uploads/img_68c284dbd0e283.20608490_color.webp', NULL, 0),
(167, 8, '60', '', 0.00, 'uploads/img_68c2852c9bf144.40100802_color.webp', NULL, 0),
(168, 8, '61', '', 0.00, 'uploads/img_68c285396ee320.54725419_color.webp', NULL, 0),
(169, 8, '62', '', 0.00, 'uploads/img_68c28546e05806.43524717_color.webp', NULL, 0),
(170, 8, '63', '', 0.00, 'uploads/img_68c285a9466710.66951619_color.webp', NULL, 0),
(171, 8, '64', '', 0.00, 'uploads/img_68c285b684e6e3.06868623_color.webp', NULL, 0),
(172, 8, '65', '', 0.00, 'uploads/img_68c285c492a4a2.21244686_color.webp', NULL, 0),
(173, 8, '66', '', 0.00, 'uploads/img_68c285d15957f0.30765583_color.webp', NULL, 0),
(225, 23, 'Default', '', 0.00, '', NULL, 0),
(226, 24, 'Normal', '', 0.00, '', NULL, 0),
(227, 25, 'GTM68000', '', 0.00, 'uploads/img_68d4d414d8c456.87861172.webp', NULL, 0),
(228, 25, 'GTM68001', '', 0.00, 'uploads/img_68d4d41537f9c8.16086886.webp', NULL, 0),
(229, 25, 'GTM68002', '', 0.00, 'uploads/img_68d4d415900484.29589741.webp', NULL, 0),
(230, 25, 'GTM68003', '', 0.00, 'uploads/img_68d4d415ed66b4.74176274.webp', NULL, 0),
(231, 25, 'GTM68004', '', 0.00, 'uploads/img_68d4d416567d53.64400670.webp', NULL, 0),
(232, 25, 'GTM68005', '', 0.00, 'uploads/img_68d4d416b7e2b0.25755517.webp', NULL, 0),
(233, 25, 'GTM68006', '', 0.00, 'uploads/img_68d4d4171e0053.92864770.webp', NULL, 0),
(234, 25, 'GTM68007', '', 0.00, 'uploads/img_68d4d417832794.02675400.webp', NULL, 0),
(235, 25, 'GTM68008', '', 0.00, 'uploads/img_68d4d417e09797.49745250.webp', NULL, 0),
(236, 25, 'GTM68009', '', 0.00, 'uploads/img_68d4d4184aaea9.21978232.webp', NULL, 0),
(237, 25, 'GTM68010', '', 0.00, 'uploads/img_68d4d418aa9c57.09045340.webp', NULL, 0),
(238, 25, 'GTM68011', '', 0.00, 'uploads/img_68d4d419143251.69280680.webp', NULL, 0),
(239, 25, 'GTM68012', '', 0.00, 'uploads/img_68d4d4196ec2a6.65450568.webp', NULL, 0),
(240, 25, 'GTM68013', '', 0.00, 'uploads/img_68d4d419cbccf4.27681158.webp', NULL, 0),
(241, 25, 'GTM68015', '', 0.00, 'uploads/img_68d4d41a2eba15.73125792.webp', NULL, 0),
(242, 25, 'GTM68016', '', 0.00, 'uploads/img_68d4d41a878083.70871246.webp', NULL, 0),
(243, 25, 'GTM68017', '', 0.00, 'uploads/img_68d4d41addf527.99122900.webp', NULL, 0),
(244, 25, 'GTM68018', '', 0.00, 'uploads/img_68d4d41b48dc80.16860113.webp', NULL, 0),
(245, 25, 'GTM68019', '', 0.00, 'uploads/img_68d4d41ba5fb98.00712035.webp', NULL, 0),
(246, 25, 'GTM68020', '', 0.00, 'uploads/img_68d4e331f066c8.99232601_color.webp', NULL, 0),
(247, 25, 'GTM68021', '', 0.00, 'uploads/img_68d4e3325bc962.42015590_color.webp', NULL, 0),
(248, 25, 'GTM68022', '', 0.00, 'uploads/img_68d4e332ab3d35.97085178_color.webp', NULL, 0),
(249, 25, 'GTM68023', '', 0.00, 'uploads/img_68d4e3331068d1.24973887_color.webp', NULL, 0),
(250, 25, 'GTM68025', '', 0.00, 'uploads/img_68d4e33364afe9.68279739_color.webp', NULL, 0),
(251, 25, 'GTM68026', '', 0.00, 'uploads/img_68d4e333bef0d5.17416514_color.webp', NULL, 0),
(252, 25, 'GTM68027', '', 0.00, 'uploads/img_68d4e3341f7f42.22677153_color.webp', NULL, 0),
(253, 26, 'MTM6000 ', '', 0.00, 'uploads/img_68da16a85594e6.08548256_color.webp', NULL, 0),
(254, 26, 'MTM6001 ', '', 0.00, 'uploads/img_68da1bb930ed63.07521886_color.webp', NULL, 0),
(255, 26, 'MTM6008 ', '', 0.00, 'uploads/img_68da1bb979b7f2.64634361_color.webp', NULL, 0),
(256, 26, 'MTM6010 ', '', 0.00, 'uploads/img_68da1bb9bf5b46.86674992_color.webp', NULL, 0),
(257, 26, 'MTM6011 ', '', 0.00, 'uploads/img_68da1bba0d4ae7.18366188_color.webp', NULL, 0),
(258, 26, 'MTM6016 ', '', 0.00, 'uploads/img_68da1bba57df52.46198703_color.webp', NULL, 0),
(259, 26, 'MTM6018 ', '', 0.00, 'uploads/img_68da1bbaa54510.52055634_color.webp', NULL, 0),
(260, 26, 'MTM6019 ', '', 0.00, 'uploads/img_68da1bbaf215a5.87900362_color.webp', NULL, 0),
(261, 26, 'MTM6019 ', '', 0.00, 'uploads/img_68da1bbb4808f7.33475193_color.webp', NULL, 0),
(262, 26, 'MTM6021 ', '', 0.00, 'uploads/img_68da1bbb94d076.62007963_color.webp', NULL, 0),
(263, 26, 'MTM6022 ', '', 0.00, 'uploads/img_68da1bbbe03904.18776700_color.webp', NULL, 0),
(264, 26, 'MTM6023 ', '', 0.00, 'uploads/img_68da1bbc39dac1.73240098_color.webp', NULL, 0),
(265, 26, 'MTM6025 ', '', 0.00, 'uploads/img_68da1bbc833aa8.58639833_color.webp', NULL, 0),
(266, 26, 'MTM6026 ', '', 0.00, 'uploads/img_68da1bbcd22575.06927434_color.webp', NULL, 0),
(267, 26, 'MTM6027', '', 0.00, 'uploads/img_68da1bbd2963f1.31254740_color.webp', NULL, 0),
(268, 26, 'MTM6031', '', 0.00, 'uploads/img_68da1bbd7a8716.38349510_color.webp', NULL, 0),
(269, 26, 'MTM6035', '', 0.00, 'uploads/img_68da1bbdcafa72.22947146_color.webp', NULL, 0),
(270, 27, 'WARM WHITE ', '3000K', 0.00, 'uploads/img_68dc6ec074ed08.26380372.webp', NULL, 0),
(271, 27, 'NATURAL WHITE', '4000K', 0.00, 'uploads/img_68dc6ec0756ba6.35147760.webp', NULL, 0),
(272, 27, 'DAYLIGHT', '6000K', 0.00, 'uploads/img_68dc6ec075b220.77421261.webp', NULL, 0),
(273, 28, 'Normal', '', 0.00, 'uploads/img_68de0a5b2827f4.99468874.webp', NULL, 0),
(274, 29, 'Normal', '', 0.00, 'uploads/img_68de0c13f17035.35144114.webp', NULL, 0),
(311, 51, 'WARM WHITE', '', 0.00, 'uploads/img_1760064929_68e875a1bcd41.webp', NULL, 0),
(312, 51, 'COOL WHITE', '', 0.00, 'uploads/img_1760064929_68e875a1dd46c.webp', NULL, 0),
(313, 51, 'DAY LIGHT', '', 0.00, 'uploads/img_1760064930_68e875a205610.webp', NULL, 0),
(314, 52, 'WARM WHITE', '', 0.00, 'uploads/img_1760065470_68e877be5bdcb.webp', NULL, 0),
(315, 52, 'COOL WHITE', '', 0.00, 'uploads/img_1760065470_68e877beae0ae.webp', NULL, 0),
(316, 52, 'DAY LIGHT', '', 0.00, 'uploads/img_1760065470_68e877bef14fe.webp', NULL, 0),
(343, 77, 'BLACK', '', 0.00, 'uploads/1770790818_1.png', NULL, 0),
(344, 77, 'WHITE', '', 0.00, 'uploads/1770790818_Picture1.png', '', 0),
(345, 78, 'WHITE', '', 0.00, 'uploads/img_1770854262_698d1776db8f9.webp', NULL, 0),
(346, 79, 'NORMAL', '', 0.00, 'uploads/1770854587_3.png', NULL, 0),
(347, 80, 'NORMAL', '', 0.00, 'uploads/img_1770855095_698d1ab78ab99.webp', NULL, 0),
(348, 81, 'NORMAL', '', 0.00, 'uploads/1770953386_6.png', 'uploads/1770856333_secondary_Picture1.png', 0),
(350, 82, 'NORMAL', '', 0.00, 'uploads/img_1770856548_698d206481984.webp', NULL, 0),
(351, 83, 'NORMAL', '', 0.00, 'uploads/img_1770857733_698d2505c591c.webp', NULL, 0),
(352, 84, 'NORMAL', '', 0.00, 'uploads/img_1770965704_698ecac871b12.webp', NULL, 0),
(353, 85, 'NORMAL', '', 0.00, 'uploads/img_1770965956_698ecbc4697b8.webp', NULL, 0),
(354, 86, 'NORMAL', '', 0.00, 'uploads/img_1771197598_6992549e822c9.webp', NULL, 0),
(355, 87, 'NORMAL', '', 0.00, 'uploads/img_1771203451_69926b7b31505.webp', NULL, 0),
(356, 88, 'NORMAL', '', 0.00, 'uploads/img_1771203587_69926c03cdb2e.webp', NULL, 0),
(357, 89, 'NORMAL', '', 0.00, 'uploads/img_1771203751_69926ca738bd9.webp', NULL, 0),
(358, 90, 'NORMAL', '', 0.00, 'uploads/img_1771203954_69926d723789d.webp', NULL, 0),
(359, 91, 'NORMAL', '', 0.00, 'uploads/img_1771204185_69926e590c9ac.webp', NULL, 0),
(360, 92, 'NORMAL', '', 0.00, 'uploads/img_1771204326_69926ee6931f7.webp', NULL, 0),
(361, 93, 'NORMAL', '', 0.00, 'uploads/img_1771204448_69926f60067b5.webp', NULL, 0),
(362, 94, 'NORMAL', '', 0.00, 'uploads/img_1771204600_69926ff8440c5.webp', NULL, 0),
(363, 95, 'NORMAL', '', 0.00, 'uploads/img_1771204747_6992708b86763.webp', NULL, 0),
(364, 96, 'NORMAL', '', 0.00, 'uploads/img_1771205253_6992728503b1b.webp', NULL, 0),
(365, 97, 'NORMAL', '', 0.00, NULL, NULL, 0),
(366, 98, 'NORMAL', '', 0.00, 'uploads/img_1771205534_6992739eadf4f.webp', NULL, 0),
(367, 99, 'NORMAL', '', 10.00, 'uploads/img_1771205656_699274187068c.webp', NULL, 0),
(368, 100, 'NORMAL', '', 0.00, 'uploads/img_1771205771_6992748b39544.webp', NULL, 0),
(369, 101, 'NORMAL', '', 0.00, 'uploads/img_1771374238_6995069e2f513.webp', NULL, 0),
(370, 102, 'NORMAL', '', 0.00, 'uploads/img_1771374402_6995074284135.webp', NULL, 0),
(371, 103, 'NORMAL', '', 0.00, 'uploads/img_1771374762_699508aa4d325.webp', NULL, 0),
(372, 104, 'NORMAL', '', 0.00, 'uploads/img_1771374882_6995092202875.webp', NULL, 0),
(373, 105, 'NORMAL', '', 0.00, 'uploads/img_1771817871_699bcb8fb20e5.webp', NULL, 0),
(374, 106, 'NORMAL', '', 0.00, 'uploads/img_1771817984_699bcc00cbceb.webp', NULL, 0),
(375, 107, 'NORMAL', '', 0.00, 'uploads/img_1771818086_699bcc667a5d0.webp', NULL, 0),
(376, 108, 'NORMAL', '', 0.00, 'uploads/img_1771818223_699bccef12302.webp', NULL, 0),
(377, 109, 'NORMAL', '', 0.00, NULL, 'uploads/img_1771818306_699bcd42b7fb4.webp', 0),
(378, 110, 'NORMAL', '', 0.00, 'uploads/img_1771818421_699bcdb5d3b5e.webp', NULL, 0),
(379, 111, 'NORMAL', '', 0.00, 'uploads/img_1771818607_699bce6f67e62.webp', NULL, 0),
(380, 112, 'NORMAL', '', 0.00, 'uploads/img_1771818854_699bcf661922a.webp', NULL, 0),
(381, 113, 'NORMAL', '', 0.00, 'uploads/img_1771818959_699bcfcf1e893.webp', NULL, 0),
(382, 114, 'NORMAL', '', 0.00, 'uploads/img_1771819034_699bd01a6eb2c.webp', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `product_ratings`
--

CREATE TABLE `product_ratings` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_ratings`
--

INSERT INTO `product_ratings` (`id`, `product_id`, `user_id`, `rating`, `comment`, `created_at`, `updated_at`) VALUES
(1, 23, 16, 5, NULL, '2025-11-06 06:48:46', '2025-11-06 06:48:46'),
(2, 23, 17, 5, NULL, '2025-11-21 06:11:31', '2025-11-21 06:11:31');

-- --------------------------------------------------------

--
-- Table structure for table `product_subcategories`
--

CREATE TABLE `product_subcategories` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `subcategory_name` varchar(100) NOT NULL,
  `subcategory_slug` varchar(100) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_subcategories`
--

INSERT INTO `product_subcategories` (`id`, `category_id`, `subcategory_name`, `subcategory_slug`, `image_path`) VALUES
(1, 6, 'AAC block', 'aac-block', 'aac-block_main.png'),
(2, 11, 'windows alluminum', 'windows-alluminum', NULL),
(3, 3, 'marine', 'marine', 'marine_main.webp'),
(4, 3, 'marine douible face', 'marine-douible-face', 'marine-douible-face_main.webp'),
(9, 3, 'WPC PANELS', 'wpc-panels', 'wpc-panels_main.png'),
(10, 3, 'PVC PANELS', 'pvc-panels', 'pvc-panels_main.webp'),
(14, 3, 'fiber cement board', 'fiber-cement-board', 'fiber-cement-board_main.webp'),
(23, 5, 'SINK', 'sink', 'sink_main.webp'),
(24, 5, 'FAUCET', 'faucet', 'faucet_main.webp'),
(25, 5, 'TOILET', 'toilet', 'toilet_main.webp'),
(26, 5, 'BIDET', 'bidet', 'bidet_main.webp'),
(27, 5, 'SHOWERS', 'showers', 'showers_main.webp'),
(29, 8, 'COUNTERTOP TABLES', 'countertop-tables', 'countertop-tables_main.webp'),
(30, 8, 'KITCHEN CABINETS', 'kitchen-cabinets', 'kitchen-cabinets_main.webp'),
(31, 8, 'DISHWASHER', 'dishwasher', 'dishwasher_main.webp'),
(32, 8, 'KITCHEN ISLAND', 'kitchen-island', 'kitchen-island_main.webp'),
(33, 1, 'Hotel-Furniture', 'furniture-hotel-furniture', 'furniture-hotel-furniture_main.jpg'),
(34, 1, 'Livingroom Furniture', 'furniture-livingroom-furniture', 'furniture-livingroom-furniture_main.jpg'),
(35, 1, 'Office Furniture', 'furniture-office-furniture', 'furniture-office-furniture_main.jpg'),
(36, 1, 'Kitchen Furniture', 'furniture-kitchen-furniture', 'furniture-kitchen-furniture_main.jpg'),
(37, 1, 'Dining Furniture', 'furniture-dining-furniture', 'furniture-dining-furniture_main.jpg'),
(38, 1, 'Bathroom Furniture', 'furniture-bathroom-furniture', 'furniture-bathroom-furniture_main.jpg'),
(39, 7, 'Window Type', 'aircon-window-type', NULL),
(40, 7, 'Split Type', 'aircon-split-type', NULL),
(41, 7, 'Floor Type', 'aircon-floor-type', NULL),
(42, 1, 'Bed Furniture', 'furniture-bed-furniture', 'furniture-bed-furniture_main.jpg'),
(43, 12, 'CEILING LIGHTS', 'lightingfixture-ceiling-lights', NULL),
(44, 12, 'WALL LIGHTINGS', 'lightingfixture-wall-lightings', NULL),
(45, 2, 'INDOOR TILES', 'iles-indoor-tiles', NULL),
(46, 2, 'INDOOR AND OUTDOOR TILES', 'iles-indoor-and-outdoor-tiles', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_sub_subcategories`
--

CREATE TABLE `product_sub_subcategories` (
  `id` int(11) NOT NULL,
  `subcategory_id` int(11) NOT NULL,
  `sub_subcategory_name` varchar(255) NOT NULL,
  `sub_subcategory_slug` varchar(255) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_sub_subcategories`
--

INSERT INTO `product_sub_subcategories` (`id`, `subcategory_id`, `sub_subcategory_name`, `sub_subcategory_slug`, `image_path`, `created_at`) VALUES
(1, 33, 'Hotel-Chair', 'hotel-chair', NULL, '2025-11-13 00:30:43'),
(2, 33, 'Hotel-Table', 'hotel-table', NULL, '2025-11-13 00:30:56'),
(3, 42, 'Bed-Single', 'bed-single', NULL, '2025-11-13 02:58:30'),
(4, 42, 'Bed-Double', 'bed-double', NULL, '2025-11-13 02:58:50'),
(5, 42, 'King-Bed', 'king-bed', NULL, '2025-11-13 03:00:03'),
(6, 42, 'Queen-Bed', 'queen-bed', NULL, '2025-11-13 03:00:18'),
(7, 1, 'FOR RESIDENTIAL AND PROJECT', 'for-residential-and-project', NULL, '2025-11-13 23:42:55'),
(8, 43, 'LED PANEL LIGHTS', 'led-panel-lights', NULL, '2025-11-14 02:32:01'),
(9, 43, 'T8 TUBE LIGHTS', 't8-tube-lights', NULL, '2025-11-14 02:48:59'),
(10, 44, 'STRIPLIGHTS', 'striplights', NULL, '2025-11-14 02:58:11'),
(11, 45, 'POLISHED TILES', 'polished-tiles', NULL, '2025-11-14 05:46:50'),
(12, 45, 'MATTE TILES', 'matte-tiles', NULL, '2025-11-14 05:46:58'),
(13, 46, 'MATTE_TILES', 'mattetiles', NULL, '2025-11-14 05:49:20'),
(14, 37, 'Dining Table', 'dining-table', 'dining-table_main.png', '2026-02-23 00:02:25'),
(15, 37, 'ROUND DINING TABLE', 'round-dining-table', 'round-dining-table_main.png', '2026-02-23 03:57:41');

-- --------------------------------------------------------

--
-- Table structure for table `product_sub_subcategory_links`
--

CREATE TABLE `product_sub_subcategory_links` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `sub_subcategory_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_tiers`
--

CREATE TABLE `product_tiers` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `min_quantity` int(11) DEFAULT NULL,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `free_shipping` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_tiers`
--

INSERT INTO `product_tiers` (`id`, `product_id`, `min_quantity`, `discount_percent`, `free_shipping`, `created_at`, `updated_at`) VALUES
(1, 23, 500000, 10.00, 1, '2025-10-28 08:32:47', NULL),
(2, 28, 50, 10.00, 0, '2025-12-03 02:28:34', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_types`
--

CREATE TABLE `product_types` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `type_name` varchar(100) DEFAULT NULL,
  `type_image` varchar(255) DEFAULT NULL,
  `rating` float DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_types`
--

INSERT INTO `product_types` (`id`, `product_id`, `type_name`, `type_image`, `rating`) VALUES
(8, 8, 'marine', 'uploads/img_686e23b6232ba8.52301655.webp', 0),
(18, 19, 'Marine Double face', '', 0),
(21, 23, 'AAC Blocks', 'uploads/img_68da214191c820.12038597_type.webp', 0),
(22, 24, 'Fiber Cement Board ', 'uploads/img_68da217dd8c394.85045008_type.webp', 0),
(23, 25, 'Polished Tiles', 'uploads/img_68dc72aa5220f8.31170605_type.webp', 0),
(24, 26, 'Matte Tiles', 'uploads/img_68e484f4d2a4a3.70322355_type.webp', 0),
(25, 27, 'T8 TUBE LIGHT LED', 'uploads/img_68dc6ec0748e86.22293190.webp', 0),
(26, 28, 'AAC ADHESIVES', 'uploads/img_68de0a5aea08b4.71623466.webp', 0),
(27, 29, 'AAC BLOCKS BRACKETS', 'uploads/img_68de0c13cd7087.96024268.webp', 0),
(49, 51, 'LED PANEL LIGHT ( SURFACED TYPE )', 'uploads/img_1760064929_68e875a197f7f.webp', 0),
(50, 52, 'STRIPLIGHTS', 'uploads/img_1760065470_68e877be23e2c.webp', 0),
(75, 77, 'SYCZ-001 SINTERED STONE DINING TABLE', 'uploads/img_1770790573_698c1eadef6ef.webp', 0),
(76, 78, 'SOLID WOOD', 'uploads/img_1770854262_698d1776b5aa7.webp', 0),
(77, 79, 'BENZ TABLE', 'uploads/img_1770854453_698d1835ea9f6.webp', 0),
(78, 80, 'IRON FRAME ELEPHANT-LEG DINING TABLE', 'uploads/img_1770855095_698d1ab767a6b.webp', 0),
(79, 81, 'SPCZ-004 DIAMOND-LEG DINING TABLE', 'uploads/type_images/1770856333_Picture1.png', 0),
(80, 82, 'SYCZ-002 BLACK CROSS TABLE', 'uploads/img_1770856548_698d206458112.webp', 0),
(81, 83, 'SYCZ-003 LARGE V-SHAPED DINING TABLE', 'uploads/img_1770857733_698d25059df85.webp', 0),
(82, 84, 'SYCZ-006 TRIANGLE PIANO DINING TABLE', 'uploads/img_1770965704_698ecac83bd6b.webp', 0),
(83, 85, 'SYCZ-008 CROSS BASE DINING TABLE', 'uploads/img_1770965956_698ecbc44b75e.webp', 0),
(84, 86, 'SYCZ-009 M-SHAPED DINING TABLE', 'uploads/img_1771197598_6992549e6379b.webp', 0),
(85, 87, 'SYCZ-011 GOLD LARGE V DINING TABLE', 'uploads/img_1771203450_69926b7adaa87.webp', 0),
(86, 88, 'SYCZ-012 GOLD CROSS BASE TABLE', 'uploads/img_1771203587_69926c039d471.webp', 0),
(87, 89, 'SYCZ-013 PURPLE CROSS BASE TABLE', 'uploads/img_1771203751_69926ca70d618.webp', 0),
(88, 90, 'SYCZ-015 DOUBLE D BARREL TABLE', 'uploads/img_1771203954_69926d7209c7a.webp', 0),
(89, 91, 'SYCZ-016 T-SHAPED ACRYLIC TABLE', 'uploads/img_1771204184_69926e58cdfa4.webp', 0),
(90, 92, 'SYCZ-018 DOUBLE C DINING TABLE', 'uploads/img_1771204326_69926ee66ff48.webp', 0),
(91, 93, 'SYCZ-019 RECTANGULAR COLUMN TABLE', 'uploads/img_1771204447_69926f5fd9b45.webp', 0),
(92, 94, 'RING-SHAPED LONG TABLE', 'uploads/img_1771204600_69926ff80fd2c.webp', 0),
(93, 95, 'SYCZ-020 (PANDORA / GLOSS SNOW MOUNTAIN STONE)', 'uploads/img_1771204747_6992708b60fa5.webp', 0),
(94, 96, 'SYCZ-021 FENDI-STYLE DINING TABLE', 'uploads/img_1771205252_69927284e6ce9.webp', 0),
(95, 97, 'MOON TABLE', 'uploads/img_1771205431_69927337f32e6.webp', 0),
(96, 98, 'SYCZ-022 TESLA-STYLE DINING TABLE (DESKTOP – PANDORA ONLY)', 'uploads/img_1771205534_6992739e8baca.webp', 0),
(97, 99, 'SYCZ-023 (PANDORA)', 'uploads/img_1771205656_699274184ddf7.webp', 0),
(98, 100, 'SYCZ-024 SQUARE BARREL DINING TABLE', 'uploads/img_1771205771_6992748b18ada.webp', 0),
(99, 101, 'LARGE V-SHAPED LEG DINING TABLE', 'uploads/img_1771374238_6995069e12b4c.webp', 0),
(100, 102, 'HALF-MOON DINING TABLE', 'uploads/img_1771374402_6995074262048.webp', 0),
(101, 103, 'SYCZ-028 DOUBLE ROUND-END DINING TABLE', 'uploads/img_1771374762_699508aa13574.webp', 0),
(102, 104, 'THREE CYLINDER BASE DINING TABLE', 'uploads/img_1771374881_69950921d44f5.webp', 0),
(103, 105, 'SYCZ-029 WAIST ROUND DINING TABLE', 'uploads/img_1771817871_699bcb8f9402a.webp', 0),
(104, 106, 'SYCZ-030 FOUR-LEAF CLOVER ROUND DINING TABLE', 'uploads/img_1771817984_699bcc009771c.webp', 0),
(105, 107, 'SYCZ-031 RING ROUND DINING TABLE', 'uploads/img_1771818086_699bcc66472fa.webp', 0),
(106, 108, 'SYCZ-032 BULLET-SHAPED ROUND DINING TABLE', 'uploads/img_1771818222_699bcceed5cd0.webp', 0),
(107, 109, 'SYCZ-033 GOLD CC ROUND DINING TABLE', 'uploads/img_1771818306_699bcd42896bf.webp', 0),
(108, 110, 'SYCZ-034 HOURGLASS ROUND DINING TABLE', 'uploads/img_1771818421_699bcdb59ce20.webp', 0),
(109, 111, 'SYCZ-035 TEXT DESIGN ROUND DINING TABLE', 'uploads/img_1771818607_699bce6f3f94a.webp', 0),
(110, 112, 'SYCZ-036 LOTUS FIXED ROUND DINING TABLE', 'uploads/img_1771818853_699bcf65d8e96.webp', 0),
(111, 113, 'NEST ROUND DINING TABLE', 'uploads/img_1771818958_699bcfced9cbe.webp', 0),
(112, 114, 'INK ORCHID PURPLE DOUBLE C ROUND DINING TABLE', 'uploads/img_1771819034_699bd01a40f72.webp', 0);

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `id` int(11) NOT NULL,
  `type_id` int(11) DEFAULT NULL,
  `sku_info` text DEFAULT NULL COMMENT 'JSON data containing SKU, barcode, supplier info, etc.',
  `product_id` int(11) DEFAULT NULL,
  `color` varchar(100) DEFAULT NULL,
  `size` varchar(255) DEFAULT NULL,
  `original_price` decimal(10,2) DEFAULT 0.00,
  `price` decimal(10,2) DEFAULT NULL,
  `percent` decimal(5,2) DEFAULT NULL,
  `discount` decimal(5,2) DEFAULT NULL,
  `width` decimal(10,2) DEFAULT NULL,
  `height` decimal(10,2) DEFAULT NULL,
  `length` decimal(10,2) DEFAULT NULL,
  `dimension_unit` enum('mm','cm','inches','m') DEFAULT 'cm',
  `weight` decimal(10,2) DEFAULT NULL,
  `weight_unit` enum('g','kg','lbs','oz') DEFAULT 'kg',
  `namevariant` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `imagedescription` varchar(255) DEFAULT NULL,
  `imagedescriptiontwo` varchar(255) DEFAULT NULL,
  `imagedescriptiontree` varchar(255) DEFAULT NULL,
  `imagedescriptionfour` varchar(255) DEFAULT NULL,
  `status` enum('new','old') DEFAULT 'old',
  `descriptionpic` text DEFAULT NULL,
  `origin` enum('international','local') DEFAULT 'local',
  `category_id` int(11) DEFAULT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `sub_subcategory_id` int(11) DEFAULT NULL,
  `category_name` varchar(255) DEFAULT NULL,
  `subcategory_name` text DEFAULT NULL,
  `delivery_size_id` int(11) DEFAULT NULL,
  `lead_count` int(11) DEFAULT NULL,
  `lead_interval` enum('day','week','month','year') DEFAULT NULL,
  `lead_gap` int(11) DEFAULT NULL,
  `sub_subcategory_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sub_subcategory_ids`)),
  `stock` int(11) NOT NULL DEFAULT 0,
  `timer_discount_percent` decimal(5,2) DEFAULT 0.00,
  `timer_discount_start` datetime DEFAULT NULL,
  `timer_discount_end` datetime DEFAULT NULL,
  `timer_discount_active` tinyint(1) DEFAULT 0,
  `timer_discount_duration_seconds` int(11) DEFAULT 0,
  `timer_discount_duration_formatted` varchar(255) DEFAULT 'No duration'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_variants`
--

INSERT INTO `product_variants` (`id`, `type_id`, `sku_info`, `product_id`, `color`, `size`, `original_price`, `price`, `percent`, `discount`, `width`, `height`, `length`, `dimension_unit`, `weight`, `weight_unit`, `namevariant`, `image`, `imagedescription`, `imagedescriptiontwo`, `imagedescriptiontree`, `imagedescriptionfour`, `status`, `descriptionpic`, `origin`, `category_id`, `subcategory_id`, `sub_subcategory_id`, `category_name`, `subcategory_name`, `delivery_size_id`, `lead_count`, `lead_interval`, `lead_gap`, `sub_subcategory_ids`, `stock`, `timer_discount_percent`, `timer_discount_start`, `timer_discount_end`, `timer_discount_active`, `timer_discount_duration_seconds`, `timer_discount_duration_formatted`) VALUES
(9, 8, NULL, 8, '', 'small', 0.00, 500.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'marine', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'international', 3, 3, NULL, 'buildingmaterials', '[\"marine\"]', 4, NULL, NULL, NULL, NULL, 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(10, 8, NULL, 8, '', 'medium', 0.00, 250.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'marine', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'international', 3, 3, NULL, 'buildingmaterials', '[\"marine\"]', 4, NULL, NULL, NULL, NULL, 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(11, 8, NULL, 8, '', 'large', 0.00, 300.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'marine', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'international', 3, 3, NULL, 'buildingmaterials', '[\"marine\"]', 4, NULL, NULL, NULL, NULL, 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(27, 18, NULL, 19, 'AG-062', '18mm 4x8', 0.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'Marine Double face', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'international', 3, 6, NULL, 'buildingmaterials', '[\"marine doubleface\"]', 4, NULL, NULL, NULL, NULL, 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(28, 18, NULL, 19, 'AG-061', '5mm 4x8', 0.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'Marine Double face', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'international', 3, 6, NULL, 'buildingmaterials', '[\"marine doubleface\"]', 4, NULL, NULL, NULL, NULL, 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(32, 21, NULL, 23, '', '600x200x100 750 PSI', 131.00, 131.00, 0.00, 0.00, 600.00, 200.00, 100.00, 'mm', 150.00, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', -50, 0.00, NULL, NULL, 0, 0, 'No duration'),
(33, 21, NULL, 23, '', '600x200x125 750 PSI', 164.00, 164.00, 0.00, 0.00, 600.00, 200.00, 175.00, 'mm', 180.00, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(34, 21, NULL, 23, '', '600x200x150 750 PSI', 197.00, 197.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(35, 21, NULL, 23, '', '600x200x175 750 PSI', 229.00, 229.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(36, 21, NULL, 23, '', '600x200x200 750 PSI', 262.00, 262.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(37, 21, NULL, 23, '', '600x200x100 580 PSI', 121.00, 121.00, 0.00, 0.00, 600.00, 200.00, 100.00, 'cm', 150.00, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', -100, 0.00, NULL, NULL, 0, 0, 'No duration'),
(38, 21, NULL, 23, '', '600x200x125 580 PSI', 151.00, 151.00, 0.00, 0.00, 600.00, 200.00, 125.00, 'cm', 150.00, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', -50, 0.00, NULL, NULL, 0, 0, 'No duration'),
(39, 21, NULL, 23, '', '600x200x150 580 PSI', 181.00, 181.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(40, 21, NULL, 23, '', '600x200x175 580 PSI', 212.00, 212.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(41, 21, NULL, 23, '', '600x200x200 580 PSI', 242.00, 242.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(42, 21, NULL, 23, '', '600x300x100 750 PSI ', 197.00, 197.00, 0.00, 0.00, 100.00, 300.00, 600.00, 'cm', NULL, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(43, 21, NULL, 23, '', '600x300x125 750 PSI ', 246.00, 246.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(44, 21, NULL, 23, '', '600x300x150 750 PSI ', 295.00, 295.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(45, 21, NULL, 23, '', '600x300x175 750 PSI ', 344.00, 344.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(46, 21, NULL, 23, '', '600x300x200 750 PSI ', 393.00, 393.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(47, 21, NULL, 23, '', '600x300x100 580 PSI ', 167.58, 167.58, 0.00, 0.00, 100.00, 300.00, 600.00, 'cm', NULL, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(48, 21, NULL, 23, '', '600x300x125 580 PSI ', 227.00, 227.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(49, 21, NULL, 23, '', '600x300x150 580 PSI ', 272.00, 272.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(50, 21, NULL, 23, '', '600x300x175 580 PSI ', 317.00, 317.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', -1, 0.00, NULL, NULL, 0, 0, 'No duration'),
(51, 21, NULL, 23, '', '600x300x200 580 PSI ', 362.00, 362.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'AAC Blocks', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(52, 22, NULL, 24, '', '2440*1200*4.5mm', 320.00, 320.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'Fiber Cement Board', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'international', 3, 14, NULL, 'buildingmaterials', '[\"fiber cement board\"]', NULL, NULL, NULL, NULL, NULL, 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(53, 22, NULL, 24, '', '2440*1200*6mm', 430.00, 430.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'Fiber Cement Board', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'international', 3, 14, NULL, 'buildingmaterials', '[\"fiber cement board\"]', NULL, NULL, NULL, NULL, NULL, 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(54, 22, NULL, 24, '', '2440*1200*9mm', 660.00, 660.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'Fiber Cement Board', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'international', 3, 14, NULL, 'buildingmaterials', '[\"fiber cement board\"]', NULL, NULL, NULL, NULL, NULL, 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(55, 22, NULL, 24, '', '2440*1200*12mm', 880.00, 880.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'Fiber Cement Board', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'international', 3, 14, NULL, 'buildingmaterials', '[\"fiber cement board\"]', NULL, NULL, NULL, NULL, NULL, 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(56, 23, NULL, 25, '', '600x600', 560.00, 560.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'Polished Tiles', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 2, 45, 11, 'Tiles', '[\"INDOOR TILES\"]', NULL, NULL, NULL, NULL, '[11]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(72, 24, NULL, 26, '', '600x600', 125.00, 560.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'Matte Tiles', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 2, 46, 12, 'Tiles', '[\"INDOOR TILES\",\"INDOOR AND OUTDOOR TILES\"]', NULL, NULL, NULL, NULL, '[12,13]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(73, 25, NULL, 27, '', '18W', 161.00, 161.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'T8 TUBE LIGHT LED', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'international', 12, 15, NULL, 'lightingfixture', '[\"T8 TUBE LIGHT LED\"]', NULL, NULL, NULL, NULL, NULL, 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(74, 25, NULL, 27, '', '24W', 161.00, 161.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'T8 TUBE LIGHT LED', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 12, 43, 8, 'lightingfixture', '[\"T8 TUBE LIGHT LED\",\"CEILING LIGHTS\"]', NULL, NULL, NULL, NULL, '[8,9]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(75, 26, NULL, 28, '', '25kg', 0.20, 0.20, 0.00, 0.00, 35.00, 25.00, 12.00, 'cm', 25.00, 'kg', 'AAC ADHESIVES', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, 1, 'week', 7, '[7]', 0, 20.00, '2025-12-17 09:46:00', '2025-12-17 09:59:00', 0, 780, '13 minutes'),
(76, 27, NULL, 29, '', 'AAC BRACKET', 27.50, 24.75, 0.00, 10.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'AAC BLOCKS BRACKET', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 6, 1, 7, 'AacBlock', '[\"AAC block\",\"AAC BLOCKS\"]', NULL, NULL, NULL, NULL, '[7]', -40, 0.00, NULL, NULL, 0, 0, 'No duration'),
(101, 49, NULL, 51, '', '60x60', 980.00, 1176.00, 20.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'LED PANEL LIGHT ( SUREFACED TYPE )', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 12, 43, 8, 'lightingfixture', '[\"LED PANEL LIGHTS\",\"CEILING LIGHTS\"]', NULL, NULL, NULL, NULL, '[8]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(102, 50, NULL, 52, '', '100 meters', 7176.00, 7176.00, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'STRIPLIGHTS', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', 12, 44, 10, 'lightingfixture', '[\"STRIPLIGHTS\",\"WALL LIGHTINGS\"]', NULL, NULL, NULL, NULL, '[10]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(130, 75, NULL, 77, NULL, '1.2*700', 2174.94, 2392.43, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-001 SINTERED STONE DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(131, 75, NULL, 77, NULL, '130*70', 2174.94, 2392.43, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-001 SINTERED STONE DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(132, 75, NULL, 77, NULL, '140*80', 2174.94, 2392.43, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-001 SINTERED STONE DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(133, 75, NULL, 77, NULL, '160*80', 2993.59, 3292.95, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-001 SINTERED STONE DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(134, 75, NULL, 77, NULL, '180*90', 2993.59, 3292.95, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-001 SINTERED STONE DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(135, 76, NULL, 78, '', '1300*700', 11974.38, 14489.00, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SOLID WOOD', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(136, 76, NULL, 78, '', '1400*800', 12707.50, 15376.08, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SOLID WOOD', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(137, 77, NULL, 79, NULL, '1400*800', 8919.69, 9811.66, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'BENZ TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(138, 77, NULL, 79, NULL, '1600*800', 10141.56, 11155.72, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'BENZ TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(139, 77, NULL, 79, NULL, '1800*900', 10508.13, 11558.94, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'BENZ TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(140, 77, NULL, 79, NULL, '2000*900', 15640.00, 17204.00, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'BENZ TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(141, 78, NULL, 80, '', '130*70', 4032.19, 4878.95, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'IRON FRAME ELEPHANT-LEG DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(142, 78, NULL, 80, '', '140*80', 4032.19, 4878.95, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'IRON FRAME ELEPHANT-LEG DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(143, 78, NULL, 80, '', '160*80', 4765.31, 5766.02, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'IRON FRAME ELEPHANT-LEG DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(144, 78, NULL, 80, '', '180*90', 5620.63, 6800.96, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'IRON FRAME ELEPHANT-LEG DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(145, 79, NULL, 81, '', '130*70', 3665.63, 4032.19, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SPCZ-004 DIAMOND-LEG DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(146, 79, NULL, 81, '', '140*80', 3665.63, 4032.19, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SPCZ-004 DIAMOND-LEG DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(147, 79, NULL, 81, '', '160*80', 4154.38, 4569.82, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SPCZ-004 DIAMOND-LEG DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(148, 79, NULL, 81, '', '180*90', 4765.31, 5241.84, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SPCZ-004 DIAMOND-LEG DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(149, 80, NULL, 82, '', '130*70', 5131.88, 6209.58, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-002 BLACK CROSS TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(150, 80, NULL, 82, '', '140*80', 5131.88, 6209.58, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-002 BLACK CROSS TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(151, 80, NULL, 82, '', '160*90', 6720.31, 8131.57, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-002 BLACK CROSS TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(152, 80, NULL, 82, '', '180*90', 6720.31, 8131.57, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-002 BLACK CROSS TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(153, 81, NULL, 83, '', '130*70', 4032.19, 4878.95, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-003 LARGE V-SHAPED DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(154, 81, NULL, 83, '', '140*80', 4032.19, 4878.95, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-003 LARGE V-SHAPED DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(155, 81, NULL, 83, '', '160*80', 5620.63, 6800.96, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-003 LARGE V-SHAPED DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(156, 81, NULL, 83, '', '180*90', 5620.63, 6800.96, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-003 LARGE V-SHAPED DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(157, 82, NULL, 84, '', '130*70', 5865.00, 7096.65, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-006 TRIANGLE PIANO DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(158, 82, NULL, 84, '', '140*80', 5987.19, 7244.50, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-006 TRIANGLE PIANO DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(159, 82, NULL, 84, '', '160*80', 6720.31, 8131.57, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-006 TRIANGLE PIANO DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(160, 82, NULL, 84, '', '180*90', 6964.69, 8427.28, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-006 TRIANGLE PIANO DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(161, 83, NULL, 85, '', '140*80', 6353.75, 7688.04, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-008 CROSS BASE DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(162, 83, NULL, 85, '', '160*80', 7209.06, 8722.97, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-008 CROSS BASE DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(163, 83, NULL, 85, '', '180*90', 7575.63, 9166.51, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-008 CROSS BASE DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(164, 84, NULL, 86, '', '140*80', 7575.63, 9166.51, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-009 M-SHAPED DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(165, 84, NULL, 86, '', '160*80', 8553.13, 10349.28, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-009 M-SHAPED DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(166, 84, NULL, 86, '', '180*90', 9164.06, 11088.52, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-009 M-SHAPED DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(167, 85, NULL, 87, '', '130*70', 6720.31, 8131.57, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-011 GOLD LARGE V DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(168, 85, NULL, 87, '', '140*80', 6720.31, 8131.57, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-011 GOLD LARGE V DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(169, 85, NULL, 87, '', '160*80', 7942.19, 9610.05, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-011 GOLD LARGE V DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(170, 85, NULL, 87, '', '180*90', 7942.19, 9610.05, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-011 GOLD LARGE V DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(171, 86, NULL, 88, '', '130*70', 10630.31, 12862.67, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-012 GOLD CROSS BASE TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(172, 86, NULL, 88, '', '140*80', 10630.31, 12862.67, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-012 GOLD CROSS BASE TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(173, 86, NULL, 88, '', '160*80', 11119.06, 13454.07, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-012 GOLD CROSS BASE TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(174, 86, NULL, 88, '', '180*90', 11119.06, 13454.07, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-012 GOLD CROSS BASE TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(175, 87, NULL, 89, '', '130*70', 6842.50, 8279.43, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-013 PURPLE CROSS BASE TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(176, 87, NULL, 89, '', '140*80', 7086.88, 8575.13, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-013 PURPLE CROSS BASE TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(177, 87, NULL, 89, '', '160*90', 7697.81, 9314.35, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-013 PURPLE CROSS BASE TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(178, 87, NULL, 89, '', '180*90', 7942.19, 9610.05, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-013 PURPLE CROSS BASE TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(179, 88, NULL, 90, '', '140*80', 7086.88, 8575.13, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-015 DOUBLE D BARREL TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(180, 88, NULL, 90, '', '160*80', 8308.75, 10053.59, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-015 DOUBLE D BARREL TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(181, 88, NULL, 90, '', '180*90', 8797.50, 10644.98, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-015 DOUBLE D BARREL TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(182, 88, NULL, 90, '', '200*100', 16739.69, 20255.03, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-015 DOUBLE D BARREL TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(183, 88, NULL, 90, '', '220*110', 17717.19, 21437.80, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-015 DOUBLE D BARREL TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(184, 88, NULL, 90, '', '240*120', 18939.06, 22916.27, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-015 DOUBLE D BARREL TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(185, 89, NULL, 91, '', '130*70', 9530.63, 11532.06, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-016 T-SHAPED ACRYLIC TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(186, 89, NULL, 91, '', '140*80', 9530.63, 11532.06, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-016 T-SHAPED ACRYLIC TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(187, 89, NULL, 91, '', '160*80', 10263.75, 12419.14, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-016 T-SHAPED ACRYLIC TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(188, 89, NULL, 91, '', '180*90', 10385.94, 12566.98, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-016 T-SHAPED ACRYLIC TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(189, 90, NULL, 92, '', '130*70', 14051.56, 17002.39, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-018 DOUBLE C DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(190, 90, NULL, 92, '', '140*80', 14418.13, 17445.93, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-018 DOUBLE C DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(191, 90, NULL, 92, '', '160*80', 15640.00, 18924.40, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-018 DOUBLE C DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(192, 90, NULL, 92, '', '180*90', 15640.00, 18924.40, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-018 DOUBLE C DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(193, 91, NULL, 93, '', '130*70', 9530.63, 11532.06, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-019 RECTANGULAR COLUMN TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(194, 91, NULL, 93, '', '140*80', 9530.63, 11532.06, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-019 RECTANGULAR COLUMN TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(195, 91, NULL, 93, '', '160*80', 10874.69, 13158.38, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-019 RECTANGULAR COLUMN TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(196, 92, NULL, 94, '', '140*80', 9652.81, 11679.90, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'RING-SHAPED LONG TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(197, 92, NULL, 94, '', '160*80', 16861.88, 20402.88, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'RING-SHAPED LONG TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(198, 92, NULL, 94, '', '180*90', 11485.63, 13897.61, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'RING-SHAPED LONG TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(199, 93, NULL, 95, '', '130*70', 14906.88, 18037.33, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-020 (PANDORA / GLOSS SNOW MOUNTAIN STONE)', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(200, 93, NULL, 95, '', '140*80', 15273.44, 18480.86, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-020 (PANDORA / GLOSS SNOW MOUNTAIN STONE)', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(201, 93, NULL, 95, '', '160*90', 16861.88, 20402.88, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-020 (PANDORA / GLOSS SNOW MOUNTAIN STONE)', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(202, 93, NULL, 95, '', '180*90', 17350.63, 20994.26, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-020 (PANDORA / GLOSS SNOW MOUNTAIN STONE)', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(203, 94, NULL, 96, '', '140*80', 12096.56, 14636.84, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-021 FENDI-STYLE DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(204, 94, NULL, 96, '', '160*80', 13196.25, 15967.47, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-021 FENDI-STYLE DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(205, 94, NULL, 96, '', '180*90', 13196.25, 15967.47, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-021 FENDI-STYLE DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(206, 95, NULL, 97, '', '140*80', 14418.13, 17445.93, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'MOON TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(207, 95, NULL, 97, '', '160*80', 15640.00, 18924.40, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'MOON TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(208, 95, NULL, 97, '', '180*90', 16250.94, 19663.63, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'MOON TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(209, 96, NULL, 98, '', '140*80', 14418.13, 17445.93, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-022 TESLA-STYLE DINING TABLE (DESKTOP – PANDORA ONLY)', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(210, 96, NULL, 98, '', '160*80', 15640.00, 18924.40, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-022 TESLA-STYLE DINING TABLE (DESKTOP – PANDORA ONLY)', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(211, 96, NULL, 98, '', '180*90', 15640.00, 18924.40, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-022 TESLA-STYLE DINING TABLE (DESKTOP – PANDORA ONLY)', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(212, 97, NULL, 99, '', '140*80', 15640.00, 18924.40, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-023 (PANDORA)', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(213, 97, NULL, 99, '', '160*80', 16739.69, 20255.03, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-023 (PANDORA)', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(214, 97, NULL, 99, '', '180*90', 16739.69, 20255.03, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-023 (PANDORA)', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(215, 98, NULL, 100, '', '140*80', 10385.94, 12566.98, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-024 SQUARE BARREL DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(216, 98, NULL, 100, '', '160*80', 11607.81, 14045.45, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-024 SQUARE BARREL DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(217, 98, NULL, 100, '', '180*90', 11974.38, 14489.00, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-024 SQUARE BARREL DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(218, 99, NULL, 101, '', '140*80', 13807.19, 16706.70, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'LARGE V-SHAPED LEG DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(219, 99, NULL, 101, '', '160*80', 15029.06, 18185.17, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'LARGE V-SHAPED LEG DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(220, 99, NULL, 101, '', '180*90', 15395.63, 18628.71, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'LARGE V-SHAPED LEG DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(221, 100, NULL, 102, '', '140*80', 8308.75, 10053.59, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'HALF-MOON DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(222, 100, NULL, 102, '', '160*80', 9286.25, 11236.37, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'HALF-MOON DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(223, 100, NULL, 102, '', '180*90', 9652.81, 11679.90, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'HALF-MOON DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(224, 101, NULL, 103, '', '140*80', 12585.31, 15228.22, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-028 DOUBLE ROUND-END DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(225, 101, NULL, 103, '', '160*80', 14051.56, 17002.39, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-028 DOUBLE ROUND-END DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(226, 101, NULL, 103, '', '180*90', 14418.13, 17445.93, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-028 DOUBLE ROUND-END DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(227, 101, NULL, 103, '', '200*100', 21749.38, 26316.75, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-028 DOUBLE ROUND-END DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(228, 102, NULL, 104, '', '140*80', 13807.19, 16706.70, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'THREE CYLINDER BASE DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(229, 102, NULL, 104, '', '160*80', 15029.06, 18185.17, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'THREE CYLINDER BASE DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(230, 102, NULL, 104, '', '180*90', 15640.00, 18924.40, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'THREE CYLINDER BASE DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 14, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[14]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(231, 103, NULL, 105, '', '1300圆+800转盘', 9750.56, 11798.18, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-029 WAIST ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(232, 103, NULL, 105, '', '1350圆+800转盘', 9750.56, 11798.18, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-029 WAIST ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(233, 103, NULL, 105, '', '1500圆+900转盘', 14906.88, 18037.33, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-029 WAIST ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(234, 104, NULL, 106, '', '1300圆+800转盘', 15640.00, 18924.40, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-030 FOUR-LEAF CLOVER ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(235, 104, NULL, 106, '', '1350圆+800转盘', 15640.00, 18924.40, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-030 FOUR-LEAF CLOVER ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(236, 104, NULL, 106, '', '1500圆+900转盘', 20649.69, 24986.13, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-030 FOUR-LEAF CLOVER ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(237, 105, NULL, 107, '', '1300圆+800转盘', 11974.38, 14489.00, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-031 RING ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(238, 105, NULL, 107, '', '1350圆+800转盘', 11974.38, 14489.00, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-031 RING ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(239, 105, NULL, 107, '', '1500圆+900转盘', 15273.44, 18480.86, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-031 RING ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(240, 106, NULL, 108, '', '1300圆+800转盘', 15640.00, 18924.40, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-032 BULLET-SHAPED ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(241, 106, NULL, 108, '', '1350圆+800转盘', 15640.00, 18924.40, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-032 BULLET-SHAPED ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(242, 106, NULL, 108, '', '1500圆+900转盘', 19794.38, 19794.38, 0.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-032 BULLET-SHAPED ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'old', NULL, 'local', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(243, 107, NULL, 109, '', '1300圆+800转盘', 21627.19, 26168.90, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-033 GOLD CC ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(244, 107, NULL, 109, '', '1350圆+800转盘', 21627.19, 26168.90, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-033 GOLD CC ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(245, 107, NULL, 109, '', '1500圆+900转盘', 25415.00, 30752.15, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-033 GOLD CC ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(246, 108, NULL, 110, '', '1300圆+800转盘', 16861.88, 20402.88, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-034 HOURGLASS ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration');
INSERT INTO `product_variants` (`id`, `type_id`, `sku_info`, `product_id`, `color`, `size`, `original_price`, `price`, `percent`, `discount`, `width`, `height`, `length`, `dimension_unit`, `weight`, `weight_unit`, `namevariant`, `image`, `imagedescription`, `imagedescriptiontwo`, `imagedescriptiontree`, `imagedescriptionfour`, `status`, `descriptionpic`, `origin`, `category_id`, `subcategory_id`, `sub_subcategory_id`, `category_name`, `subcategory_name`, `delivery_size_id`, `lead_count`, `lead_interval`, `lead_gap`, `sub_subcategory_ids`, `stock`, `timer_discount_percent`, `timer_discount_start`, `timer_discount_end`, `timer_discount_active`, `timer_discount_duration_seconds`, `timer_discount_duration_formatted`) VALUES
(247, 108, NULL, 110, '', '1350圆+800转盘', 16861.88, 20402.88, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-034 HOURGLASS ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(248, 108, NULL, 110, '', '1500圆+900转盘', 19916.56, 24099.04, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-034 HOURGLASS ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(249, 109, NULL, 111, '', '1300圆+800转盘', 12585.31, 15228.22, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-035 TEXT DESIGN ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(250, 109, NULL, 111, '', '1350圆+800转盘', 12829.69, 15523.93, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-035 TEXT DESIGN ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(251, 109, NULL, 111, '', '1500圆+900转盘', 16861.88, 20402.88, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-035 TEXT DESIGN ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(252, 110, NULL, 112, '', '1300圆+800转盘', 13685.00, 16558.85, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-036 LOTUS FIXED ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(253, 110, NULL, 112, '', '1350圆+800转盘', 13685.00, 16558.85, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-036 LOTUS FIXED ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(254, 110, NULL, 112, '', '1500圆+900转盘', 17350.63, 20994.26, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'SYCZ-036 LOTUS FIXED ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(255, 111, NULL, 113, '', '1300圆+800转盘', 21627.19, 26168.90, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'NEST ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(256, 111, NULL, 113, '', '1350圆+800转盘', 21627.19, 26168.90, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'NEST ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(257, 111, NULL, 113, '', '1500圆+900转盘', 25415.00, 30752.15, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'NEST ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(258, 111, NULL, 113, '', '1600圆+900转盘', 25415.00, 30752.15, 10.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'NEST ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration'),
(259, 112, NULL, 114, '', '1350圆+800转盘', 18083.75, 23915.76, 15.00, 0.00, NULL, NULL, NULL, 'cm', NULL, 'kg', 'INK ORCHID PURPLE DOUBLE C ROUND DINING TABLE', NULL, NULL, NULL, NULL, NULL, 'new', NULL, 'international', 1, 37, 15, 'furniture', '[\"Dining Furniture\"]', NULL, NULL, NULL, NULL, '[15]', 0, 0.00, NULL, NULL, 0, 0, 'No duration');

-- --------------------------------------------------------

--
-- Table structure for table `product_variant_colors`
--

CREATE TABLE `product_variant_colors` (
  `id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `color_id` int(11) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_variant_colors`
--

INSERT INTO `product_variant_colors` (`id`, `variant_id`, `color_id`, `stock_quantity`) VALUES
(1, 75, 273, 1678),
(2, 76, 274, 2910),
(4, 37, 225, 899),
(5, 32, 225, 950),
(6, 38, 225, 950),
(7, 33, 225, 1000),
(8, 39, 225, 1000),
(9, 34, 225, 1000),
(10, 40, 225, 1000),
(11, 35, 225, 1000),
(12, 41, 225, 1000),
(13, 36, 225, 1000),
(14, 47, 225, 1000),
(15, 42, 225, 1000),
(16, 48, 225, 1000),
(17, 43, 225, 1000),
(18, 49, 225, 1000),
(19, 44, 225, 1000),
(20, 50, 225, 1000),
(21, 45, 225, 1000),
(22, 51, 225, 997),
(23, 46, 225, 1000);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `sales_user_id` int(11) NOT NULL,
  `po_number` varchar(100) NOT NULL,
  `po_date` date NOT NULL,
  `ship_to` text NOT NULL,
  `target_delivery_date` date NOT NULL,
  `payment_terms` varchar(255) NOT NULL,
  `project_scope` text DEFAULT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `client_po_path` varchar(500) DEFAULT NULL,
  `status` enum('pending','approved','processing','rejected','delivered','cancelled') NOT NULL,
  `accounting_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `document_controller_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `accounting_approved_by` int(11) DEFAULT NULL,
  `document_controller_approved_by` int(11) DEFAULT NULL,
  `accounting_approved_at` timestamp NULL DEFAULT NULL,
  `document_controller_approved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `company_id`, `sales_user_id`, `po_number`, `po_date`, `ship_to`, `target_delivery_date`, `payment_terms`, `project_scope`, `attachment_path`, `client_po_path`, `status`, `accounting_status`, `document_controller_status`, `approved_by`, `approved_at`, `accounting_approved_by`, `document_controller_approved_by`, `accounting_approved_at`, `document_controller_approved_at`, `created_at`, `updated_at`) VALUES
(1, 1, 2, '43553', '2025-11-27', 'gdfdrgrdh gesgv', '2025-11-27', 'rgsrg gseg', NULL, '../../uploads/purchase_orders/po_1_1764226397.pdf', '../../uploads/client_pos/client_po_1_1764226397.pdf', '', 'pending', 'pending', 1, '2025-11-28 07:40:48', NULL, NULL, NULL, NULL, '2025-11-27 14:53:17', '2025-11-28 07:40:48'),
(2, 1, 2, 'sample', '2025-12-03', 'sample', '2025-12-12', 'sample', 'sample', '../../uploads/purchase_orders/po_1_1764729277.pdf', '../../uploads/client_pos/client_po_1_1764729278.pdf', 'pending', 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-03 10:34:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_color_id` int(11) NOT NULL,
  `product_variant_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `color_name` varchar(100) NOT NULL,
  `size` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `is_custom_size` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qrph_codes`
--

CREATE TABLE `qrph_codes` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `qr_code_id` varchar(100) NOT NULL,
  `paymongo_payment_id` varchar(100) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `qr_image_url` longtext DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `paymongo_intent_id` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qrph_pending_sessions`
--

CREATE TABLE `qrph_pending_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL COMMENT 'PayMongo checkout session ID',
  `temp_ref` varchar(50) NOT NULL COMMENT 'Temp reference (e.g. NH9812345)',
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `session_data` longtext NOT NULL COMMENT 'JSON snapshot: cart, address, sales info',
  `status` enum('pending','processed','expired') NOT NULL DEFAULT 'pending',
  `order_id` int(11) DEFAULT NULL COMMENT 'Set after webhook creates the order',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qrph_pending_sessions`
--

INSERT INTO `qrph_pending_sessions` (`id`, `session_id`, `temp_ref`, `user_id`, `amount`, `session_data`, `status`, `order_id`, `created_at`, `expires_at`, `updated_at`) VALUES
(1, 'cs_2a9617512dc787d55f6aeba4', 'NH9876134', 17, 1.12, '{\"user_id\":17,\"amount\":1.12,\"delivery_fee\":0,\"temp_ref\":\"NH9876134\",\"customer_name\":\"BSIT4107_HIMARANGAN Wendhil\",\"email\":\"wendhil10@gmail.com\",\"mobile\":\"09081031241\",\"address\":\"128 sitio pajo, quezon city, Metro Manila, Philippines\",\"zipcode\":\"1106\",\"billing_address_id\":\"36\",\"latitude\":\"14.66313120\",\"longitude\":\"121.01449210\",\"delivery_distance\":0,\"delivery_type\":\"pickup\",\"assigned_vehicle_id\":null,\"assigned_vehicle_type\":null,\"total_cubic_meters\":0,\"total_weight_kg\":0,\"total_width\":0,\"total_height\":0,\"total_length\":0,\"cart_snapshot\":[{\"product_id\":28,\"variant_id\":75,\"color_id\":273,\"quantity\":5,\"price\":\"0.20\",\"type_name\":\"AAC ADHESIVES\",\"variant_name\":\"AAC ADHESIVES\",\"color_name\":\"Normal\",\"size\":\"25kg\",\"codename\":\"AacBlock\",\"descrip6\":\"\",\"descrip7\":\"\",\"product_name\":\"AAC ADHESIVES\",\"origin\":\"local\"}],\"sales_info\":[]}', 'processed', 3, '2026-02-21 01:44:38', '2026-02-21 02:44:38', '2026-02-21 02:03:18'),
(2, 'cs_4bfc12d76256725330799013', 'NH9811332', 17, 1.12, '{\"user_id\":17,\"amount\":1.12,\"delivery_fee\":0,\"temp_ref\":\"NH9811332\",\"customer_name\":\"BSIT4107_HIMARANGAN Wendhil\",\"email\":\"wendhil10@gmail.com\",\"mobile\":\"09081031241\",\"address\":\"128 sitio pajo, quezon city, Metro Manila, Philippines\",\"zipcode\":\"1106\",\"billing_address_id\":\"36\",\"latitude\":\"14.66313120\",\"longitude\":\"121.01449210\",\"delivery_distance\":0,\"delivery_type\":\"pickup\",\"assigned_vehicle_id\":null,\"assigned_vehicle_type\":null,\"total_cubic_meters\":0,\"total_weight_kg\":0,\"total_width\":0,\"total_height\":0,\"total_length\":0,\"cart_snapshot\":[{\"product_id\":28,\"variant_id\":75,\"color_id\":273,\"quantity\":5,\"price\":\"0.20\",\"type_name\":\"AAC ADHESIVES\",\"variant_name\":\"AAC ADHESIVES\",\"color_name\":\"Normal\",\"size\":\"25kg\",\"codename\":\"AacBlock\",\"descrip6\":\"\",\"descrip7\":\"\",\"product_name\":\"AAC ADHESIVES\",\"origin\":\"local\"}],\"sales_info\":[]}', 'processed', 2, '2026-02-21 01:48:40', '2026-02-21 02:48:40', '2026-02-21 01:58:23'),
(3, 'cs_34006886ad5cfc0de7e7d0b7', 'NH9892610', 17, 1.12, '{\"user_id\":17,\"amount\":1.12,\"delivery_fee\":0,\"temp_ref\":\"NH9892610\",\"customer_name\":\"BSIT4107_HIMARANGAN Wendhil\",\"email\":\"wendhil10@gmail.com\",\"mobile\":\"09081031241\",\"address\":\"128 sitio pajo, quezon city, Metro Manila, Philippines\",\"zipcode\":\"1106\",\"billing_address_id\":\"36\",\"latitude\":\"14.66313120\",\"longitude\":\"121.01449210\",\"delivery_distance\":0,\"delivery_type\":\"pickup\",\"assigned_vehicle_id\":null,\"assigned_vehicle_type\":null,\"total_cubic_meters\":0,\"total_weight_kg\":0,\"total_width\":0,\"total_height\":0,\"total_length\":0,\"cart_snapshot\":[{\"product_id\":28,\"variant_id\":75,\"color_id\":273,\"quantity\":5,\"price\":\"0.20\",\"type_name\":\"AAC ADHESIVES\",\"variant_name\":\"AAC ADHESIVES\",\"color_name\":\"Normal\",\"size\":\"25kg\",\"codename\":\"AacBlock\",\"descrip6\":\"\",\"descrip7\":\"\",\"product_name\":\"AAC ADHESIVES\",\"origin\":\"local\"}],\"sales_info\":[]}', 'processed', 1, '2026-02-21 01:54:59', '2026-02-21 02:54:59', '2026-02-21 01:55:29'),
(4, 'cs_0cdab55039b673b5ce69152e', 'NH9895585', 17, 1.12, '{\"user_id\":17,\"amount\":1.12,\"delivery_fee\":0,\"temp_ref\":\"NH9895585\",\"customer_name\":\"BSIT4107_HIMARANGAN Wendhil\",\"email\":\"wendhil10@gmail.com\",\"mobile\":\"09081031241\",\"address\":\"128 sitio pajo, quezon city, Metro Manila, Philippines\",\"zipcode\":\"1106\",\"billing_address_id\":\"36\",\"latitude\":\"14.66313120\",\"longitude\":\"121.01449210\",\"delivery_distance\":0,\"delivery_type\":\"pickup\",\"assigned_vehicle_id\":null,\"assigned_vehicle_type\":null,\"total_cubic_meters\":0,\"total_weight_kg\":0,\"total_width\":0,\"total_height\":0,\"total_length\":0,\"cart_snapshot\":[{\"product_id\":28,\"variant_id\":75,\"color_id\":273,\"quantity\":5,\"price\":\"0.20\",\"type_name\":\"AAC ADHESIVES\",\"variant_name\":\"AAC ADHESIVES\",\"color_name\":\"Normal\",\"size\":\"25kg\",\"codename\":\"AacBlock\",\"descrip6\":\"\",\"descrip7\":\"\",\"product_name\":\"AAC ADHESIVES\",\"origin\":\"local\"}],\"sales_info\":[]}', 'processed', 4, '2026-02-21 02:13:56', '2026-02-21 03:13:56', '2026-02-21 02:14:34'),
(5, 'cs_8e727e6774093b7501c0c3e8', 'NH9876731', 17, 1.12, '{\"user_id\":17,\"amount\":1.12,\"delivery_fee\":0,\"temp_ref\":\"NH9876731\",\"customer_name\":\"BSIT4107_HIMARANGAN Wendhil\",\"email\":\"wendhil10@gmail.com\",\"mobile\":\"09081031241\",\"address\":\"128 sitio pajo, quezon city, Metro Manila, Philippines\",\"zipcode\":\"1106\",\"billing_address_id\":\"36\",\"latitude\":\"14.66313120\",\"longitude\":\"121.01449210\",\"delivery_distance\":0,\"delivery_type\":\"pickup\",\"assigned_vehicle_id\":null,\"assigned_vehicle_type\":null,\"total_cubic_meters\":0,\"total_weight_kg\":0,\"total_width\":0,\"total_height\":0,\"total_length\":0,\"cart_snapshot\":[{\"product_id\":28,\"variant_id\":75,\"color_id\":273,\"quantity\":5,\"price\":\"0.20\",\"type_name\":\"AAC ADHESIVES\",\"variant_name\":\"AAC ADHESIVES\",\"color_name\":\"Normal\",\"size\":\"25kg\",\"codename\":\"AacBlock\",\"descrip6\":\"\",\"descrip7\":\"\",\"product_name\":\"AAC ADHESIVES\",\"origin\":\"local\"}],\"sales_info\":[]}', 'processed', 5, '2026-02-21 02:37:32', '2026-02-21 03:37:32', '2026-02-21 02:39:32'),
(6, 'cs_11a2cf5c98aadd2e44822a03', 'NH9822515', 17, 1.12, '{\"user_id\":17,\"amount\":1.12,\"delivery_fee\":0,\"temp_ref\":\"NH9822515\",\"customer_name\":\"BSIT4107_HIMARANGAN Wendhil\",\"email\":\"wendhil10@gmail.com\",\"mobile\":\"09081031241\",\"address\":\"128 sitio pajo, quezon city, Metro Manila, Philippines\",\"zipcode\":\"1106\",\"billing_address_id\":\"36\",\"latitude\":\"14.66313120\",\"longitude\":\"121.01449210\",\"delivery_distance\":0,\"delivery_type\":\"pickup\",\"assigned_vehicle_id\":null,\"assigned_vehicle_type\":null,\"total_cubic_meters\":0,\"total_weight_kg\":0,\"total_width\":0,\"total_height\":0,\"total_length\":0,\"cart_snapshot\":[{\"product_id\":28,\"variant_id\":75,\"color_id\":273,\"quantity\":5,\"price\":\"0.20\",\"type_name\":\"AAC ADHESIVES\",\"variant_name\":\"AAC ADHESIVES\",\"color_name\":\"Normal\",\"size\":\"25kg\",\"codename\":\"AacBlock\",\"descrip6\":\"\",\"descrip7\":\"\",\"product_name\":\"AAC ADHESIVES\",\"origin\":\"local\"}],\"sales_info\":[]}', 'processed', 6, '2026-02-21 02:53:42', '2026-02-21 03:53:42', '2026-02-21 02:54:21'),
(7, 'cs_fa244bf6bbc1b93e182acf8b', 'NH9898535', 17, 1.12, '{\"user_id\":17,\"amount\":1.12,\"delivery_fee\":0,\"temp_ref\":\"NH9898535\",\"customer_name\":\"BSIT4107_HIMARANGAN Wendhil\",\"email\":\"wendhil10@gmail.com\",\"mobile\":\"09081031241\",\"address\":\"128 sitio pajo, quezon city, Metro Manila, Philippines\",\"zipcode\":\"1106\",\"billing_address_id\":\"36\",\"latitude\":\"14.66313120\",\"longitude\":\"121.01449210\",\"delivery_distance\":0,\"delivery_type\":\"pickup\",\"assigned_vehicle_id\":null,\"assigned_vehicle_type\":null,\"total_cubic_meters\":0,\"total_weight_kg\":0,\"total_width\":0,\"total_height\":0,\"total_length\":0,\"cart_snapshot\":[{\"product_id\":28,\"variant_id\":75,\"color_id\":273,\"quantity\":5,\"price\":\"0.20\",\"type_name\":\"AAC ADHESIVES\",\"variant_name\":\"AAC ADHESIVES\",\"color_name\":\"Normal\",\"size\":\"25kg\",\"codename\":\"AacBlock\",\"descrip6\":\"\",\"descrip7\":\"\",\"product_name\":\"AAC ADHESIVES\",\"origin\":\"local\"}],\"sales_info\":[]}', 'pending', NULL, '2026-02-21 02:58:16', '2026-02-21 03:58:16', '2026-02-21 02:58:16'),
(8, 'cs_ebb1679cdf865de6a1191d16', 'NH9842969', 17, 1.12, '{\"user_id\":17,\"amount\":1.12,\"delivery_fee\":0,\"temp_ref\":\"NH9842969\",\"customer_name\":\"BSIT4107_HIMARANGAN Wendhil\",\"email\":\"wendhil10@gmail.com\",\"mobile\":\"09081031241\",\"address\":\"128 sitio pajo, quezon city, Metro Manila, Philippines\",\"zipcode\":\"1106\",\"billing_address_id\":\"36\",\"latitude\":\"14.66313120\",\"longitude\":\"121.01449210\",\"delivery_distance\":0,\"delivery_type\":\"pickup\",\"assigned_vehicle_id\":null,\"assigned_vehicle_type\":null,\"total_cubic_meters\":0,\"total_weight_kg\":0,\"total_width\":0,\"total_height\":0,\"total_length\":0,\"cart_snapshot\":[{\"product_id\":28,\"variant_id\":75,\"color_id\":273,\"quantity\":5,\"price\":\"0.20\",\"type_name\":\"AAC ADHESIVES\",\"variant_name\":\"AAC ADHESIVES\",\"color_name\":\"Normal\",\"size\":\"25kg\",\"codename\":\"AacBlock\",\"descrip6\":\"\",\"descrip7\":\"\",\"product_name\":\"AAC ADHESIVES\",\"origin\":\"local\"}],\"sales_info\":[]}', 'pending', NULL, '2026-02-21 02:59:02', '2026-02-21 03:59:02', '2026-02-21 02:59:02'),
(9, 'cs_534dd25af342042f1536265a', 'NH9855874', 17, 1.12, '{\"user_id\":17,\"amount\":1.12,\"delivery_fee\":0,\"temp_ref\":\"NH9855874\",\"customer_name\":\"BSIT4107_HIMARANGAN Wendhil\",\"email\":\"wendhil10@gmail.com\",\"mobile\":\"09081031241\",\"address\":\"128 sitio pajo, quezon city, Metro Manila, Philippines\",\"zipcode\":\"1106\",\"billing_address_id\":\"36\",\"latitude\":\"14.66313120\",\"longitude\":\"121.01449210\",\"delivery_distance\":0,\"delivery_type\":\"pickup\",\"assigned_vehicle_id\":null,\"assigned_vehicle_type\":null,\"total_cubic_meters\":0,\"total_weight_kg\":0,\"total_width\":0,\"total_height\":0,\"total_length\":0,\"cart_snapshot\":[{\"product_id\":28,\"variant_id\":75,\"color_id\":273,\"quantity\":5,\"price\":\"0.20\",\"type_name\":\"AAC ADHESIVES\",\"variant_name\":\"AAC ADHESIVES\",\"color_name\":\"Normal\",\"size\":\"25kg\",\"codename\":\"AacBlock\",\"descrip6\":\"\",\"descrip7\":\"\",\"product_name\":\"AAC ADHESIVES\",\"origin\":\"local\"}],\"sales_info\":[]}', 'pending', NULL, '2026-02-21 03:32:53', '2026-02-21 04:32:53', '2026-02-21 03:32:53'),
(10, 'cs_95dcf6ae4d0024873d4341da', 'NH9832556', 17, 1.12, '{\"user_id\":17,\"amount\":1.12,\"delivery_fee\":0,\"temp_ref\":\"NH9832556\",\"customer_name\":\"BSIT4107_HIMARANGAN Wendhil\",\"email\":\"wendhil10@gmail.com\",\"mobile\":\"09081031241\",\"address\":\"128 sitio pajo, quezon city, Metro Manila, Philippines\",\"zipcode\":\"1106\",\"billing_address_id\":\"36\",\"latitude\":\"14.66313120\",\"longitude\":\"121.01449210\",\"delivery_distance\":0,\"delivery_type\":\"pickup\",\"assigned_vehicle_id\":null,\"assigned_vehicle_type\":null,\"total_cubic_meters\":0,\"total_weight_kg\":0,\"total_width\":0,\"total_height\":0,\"total_length\":0,\"cart_snapshot\":[{\"product_id\":28,\"variant_id\":75,\"color_id\":273,\"quantity\":5,\"price\":\"0.20\",\"type_name\":\"AAC ADHESIVES\",\"variant_name\":\"AAC ADHESIVES\",\"color_name\":\"Normal\",\"size\":\"25kg\",\"codename\":\"AacBlock\",\"descrip6\":\"\",\"descrip7\":\"\",\"product_name\":\"AAC ADHESIVES\",\"origin\":\"local\"}],\"sales_info\":[]}', 'pending', NULL, '2026-02-21 03:35:45', '2026-02-21 04:35:45', '2026-02-21 03:35:45'),
(11, 'cs_9e8d901a24ad5670a390e222', 'NH9841647', 17, 1.12, '{\"user_id\":17,\"amount\":1.12,\"delivery_fee\":0,\"temp_ref\":\"NH9841647\",\"customer_name\":\"BSIT4107_HIMARANGAN Wendhil\",\"email\":\"wendhil10@gmail.com\",\"mobile\":\"09081031241\",\"address\":\"128 sitio pajo, quezon city, Metro Manila, Philippines\",\"zipcode\":\"1106\",\"billing_address_id\":\"36\",\"latitude\":\"14.66313120\",\"longitude\":\"121.01449210\",\"delivery_distance\":0,\"delivery_type\":\"pickup\",\"assigned_vehicle_id\":null,\"assigned_vehicle_type\":null,\"total_cubic_meters\":0,\"total_weight_kg\":0,\"total_width\":0,\"total_height\":0,\"total_length\":0,\"cart_snapshot\":[{\"product_id\":28,\"variant_id\":75,\"color_id\":273,\"quantity\":5,\"price\":\"0.20\",\"type_name\":\"AAC ADHESIVES\",\"variant_name\":\"AAC ADHESIVES\",\"color_name\":\"Normal\",\"size\":\"25kg\",\"codename\":\"AacBlock\",\"descrip6\":\"\",\"descrip7\":\"\",\"product_name\":\"AAC ADHESIVES\",\"origin\":\"local\"}],\"sales_info\":[]}', 'pending', NULL, '2026-02-23 00:00:49', '2026-02-23 01:00:49', '2026-02-23 00:00:49'),
(12, 'cs_8517c52a86e395639fff1bd4', 'NH9838852', 16, 1.12, '{\"user_id\":16,\"amount\":1.12,\"delivery_fee\":0,\"temp_ref\":\"NH9838852\",\"customer_name\":\"BSIT 4107-Salvadora, Mark James F.\",\"email\":\"salvadoramarkjamesfrayna@gmail.com\",\"mobile\":\"09562604446\",\"address\":\"Old Samson Road Balintawak, Quezon City, Metro Manila, Philippines\",\"zipcode\":\"1106\",\"billing_address_id\":\"38\",\"latitude\":\"14.65700370\",\"longitude\":\"121.00337600\",\"delivery_distance\":0,\"delivery_type\":\"delivery\",\"assigned_vehicle_id\":1,\"assigned_vehicle_type\":\"Sedan\",\"total_cubic_meters\":0.053,\"total_weight_kg\":125,\"total_width\":35,\"total_height\":25,\"total_length\":12,\"cart_snapshot\":[{\"product_id\":28,\"variant_id\":75,\"color_id\":273,\"quantity\":5,\"price\":\"0.20\",\"type_name\":\"AAC ADHESIVES\",\"variant_name\":\"AAC ADHESIVES\",\"color_name\":\"Normal\",\"size\":\"25kg\",\"codename\":\"AacBlock\",\"descrip6\":\"\",\"descrip7\":\"\",\"product_name\":\"AAC ADHESIVES\",\"origin\":\"local\"}],\"sales_info\":[]}', 'processed', 7, '2026-02-23 06:49:55', '2026-02-23 07:49:55', '2026-02-23 06:50:43');

-- --------------------------------------------------------

--
-- Table structure for table `quotations`
--

CREATE TABLE `quotations` (
  `id` int(11) NOT NULL,
  `quotation_no` varchar(50) NOT NULL,
  `quotation_for` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `quotation_date` date NOT NULL,
  `contact_person` varchar(255) NOT NULL,
  `prepared_by` varchar(255) NOT NULL,
  `valid_gap` int(11) NOT NULL DEFAULT 30,
  `valid_until` date NOT NULL,
  `employee` varchar(255) DEFAULT NULL,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','sent','approved','cancelled') DEFAULT 'draft',
  `created_by` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quotations`
--

INSERT INTO `quotations` (`id`, `quotation_no`, `quotation_for`, `address`, `quotation_date`, `contact_person`, `prepared_by`, `valid_gap`, `valid_until`, `employee`, `grand_total`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(8, 'QT-20250808-465', 'Rolando', 'brgy.32 Holy Spirit Kalokohan City', '2025-08-08', 'Rolando', 'wendhil himarangan', 30, '2025-09-07', 'Rolando', 9009.52, 'draft', 'wendhil10@gmail.com', '2025-08-08 06:34:26', '2025-08-08 06:34:26'),
(9, 'QT-20250809-995', 'Wendhil', 'test', '2025-08-09', 'weh', 'wendhil himarangan', 30, '2025-09-08', 'wen', 8043.71, 'draft', 'wendhil08@gmail.com', '2025-08-09 03:16:51', '2025-08-09 03:16:51');

-- --------------------------------------------------------

--
-- Table structure for table `quotation_history`
--

CREATE TABLE `quotation_history` (
  `id` int(11) NOT NULL,
  `quotation_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `old_values` longtext DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext DEFAULT NULL CHECK (json_valid(`new_values`)),
  `performed_by` varchar(255) NOT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quotation_items`
--

CREATE TABLE `quotation_items` (
  `id` int(11) NOT NULL,
  `quotation_id` int(11) NOT NULL,
  `item_identifier` enum('Custom','Mats') NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `width_mm` decimal(10,2) DEFAULT 0.00,
  `height_mm` decimal(10,2) DEFAULT 0.00,
  `size_display` varchar(255) DEFAULT NULL,
  `unit` varchar(50) NOT NULL DEFAULT 'pcs',
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_material_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `unit_total_material` decimal(15,2) NOT NULL DEFAULT 0.00,
  `labor_percentage` decimal(5,2) DEFAULT 0.00,
  `unit_labor` decimal(15,2) DEFAULT 0.00,
  `unit_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quotation_items`
--

INSERT INTO `quotation_items` (`id`, `quotation_id`, `item_identifier`, `item_name`, `description`, `width_mm`, `height_mm`, `size_display`, `unit`, `quantity`, `unit_material_price`, `unit_total_material`, `labor_percentage`, `unit_labor`, `unit_total`, `total`, `created_at`) VALUES
(1, 8, 'Custom', '123', '232', 2323.00, 3232.00, '7.5079 m²', 'pcs', 1.00, 1000.00, 7507.94, 20.00, 1501.59, 9009.52, 9009.52, '2025-08-08 06:34:26'),
(2, 9, 'Custom', 'sliding', 'sample', 1234.00, 5432.00, '6.7031 m²', 'pcs', 1.00, 1000.00, 6703.09, 20.00, 1340.62, 8043.71, 8043.71, '2025-08-09 03:16:51');

-- --------------------------------------------------------

--
-- Table structure for table `quote_replies`
--

CREATE TABLE `quote_replies` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `message` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recent_views`
--

CREATE TABLE `recent_views` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recent_views`
--

INSERT INTO `recent_views` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `product_id`, `viewed_at`) VALUES
(498, 17, 'u2nc2pivqd71d538b35h9tmkaj', '112.209.76.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-11-24 08:45:00'),
(499, 17, 'u2nc2pivqd71d538b35h9tmkaj', '112.209.76.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 29, '2025-11-24 08:45:00'),
(501, NULL, 'fmdb2a6og2u7vqnd4ockdeelib', '203.188.171.166', '', 24, '2025-11-24 22:57:00'),
(502, NULL, 'fpqtnc4185qfaisskhcj6mpp9r', '112.209.76.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-11-24 23:10:00'),
(503, 17, 'fpqtnc4185qfaisskhcj6mpp9r', '112.209.76.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-11-24 23:28:00'),
(504, 17, 'fpqtnc4185qfaisskhcj6mpp9r', '112.209.76.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 26, '2025-11-24 23:29:00'),
(516, NULL, 'fpqtnc4185qfaisskhcj6mpp9r', '112.209.76.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 52, '2025-11-25 02:59:00'),
(517, NULL, 'fpqtnc4185qfaisskhcj6mpp9r', '112.209.76.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 51, '2025-11-25 03:01:00'),
(518, NULL, 'fpqtnc4185qfaisskhcj6mpp9r', '112.209.76.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 27, '2025-11-25 03:01:00'),
(519, NULL, 'fpqtnc4185qfaisskhcj6mpp9r', '112.209.76.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 52, '2025-11-25 03:01:00'),
(522, NULL, 'fpqtnc4185qfaisskhcj6mpp9r', '112.209.76.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 25, '2025-11-25 05:15:00'),
(523, NULL, 'fpqtnc4185qfaisskhcj6mpp9r', '112.209.76.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-11-25 05:15:00'),
(524, NULL, 'fpqtnc4185qfaisskhcj6mpp9r', '112.209.76.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-11-25 05:41:00'),
(525, NULL, 'fpqtnc4185qfaisskhcj6mpp9r', '112.209.76.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 52, '2025-11-25 06:24:00'),
(526, 17, 'nusmd0fckpbqkr1g64fu3j6oe6', '112.209.74.62', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-11-25 23:23:00'),
(527, 17, 'nusmd0fckpbqkr1g64fu3j6oe6', '112.209.74.62', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-11-25 23:24:00'),
(528, 17, 'nusmd0fckpbqkr1g64fu3j6oe6', '112.209.74.62', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-11-25 23:27:00'),
(532, 17, 'nusmd0fckpbqkr1g64fu3j6oe6', '112.209.74.62', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-11-26 01:13:00'),
(533, 17, 'nusmd0fckpbqkr1g64fu3j6oe6', '112.209.74.62', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-11-26 02:39:00'),
(534, 17, 'nusmd0fckpbqkr1g64fu3j6oe6', '112.209.74.62', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-11-26 02:40:00'),
(535, 17, 'nusmd0fckpbqkr1g64fu3j6oe6', '112.209.74.62', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-11-26 02:44:00'),
(536, 17, '4fbhpgrifhest15gve0clp45qu', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-11-27 00:36:00'),
(537, NULL, 'cah8cnf8np4d1c5u8buvlabk1h', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-11-27 02:37:00'),
(538, NULL, '1g3hi3lm12gnr9qlkopbq86utf', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-11-27 02:38:00'),
(542, 3, '0fodlvabhrmrnf4umo2hv8grt9', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-11-27 06:38:00'),
(543, NULL, 'ft6ushbkla5tv0isa5kvrqv186', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-11-28 07:33:00'),
(544, NULL, 'ft6ushbkla5tv0isa5kvrqv186', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-11-28 07:37:00'),
(545, NULL, 'td7f55tbagb2mv5tiak36ma3sb', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-11-28 07:47:00'),
(546, NULL, 'td7f55tbagb2mv5tiak36ma3sb', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-11-28 07:49:00'),
(547, NULL, 'td7f55tbagb2mv5tiak36ma3sb', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-11-28 07:50:00'),
(548, NULL, 'td7f55tbagb2mv5tiak36ma3sb', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-11-28 07:52:00'),
(549, NULL, 'td7f55tbagb2mv5tiak36ma3sb', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-11-28 07:53:00'),
(550, 17, 'no4pqjnfa6bv3cs3paj6an0iav', '124.104.80.51', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 23, '2025-11-28 08:03:00'),
(551, NULL, 'n3dif67283v8b21goi4bp4rdg9', '66.249.65.162', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.7390.122 Mobile Safari/537.36 (compatible; GoogleOther)', 28, '2025-11-28 23:34:00'),
(552, 17, '100tl7pev2n3f7t4rvpsiv35t8', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-11-29 01:21:00'),
(555, 17, '100tl7pev2n3f7t4rvpsiv35t8', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-11-29 01:39:00'),
(556, 17, '100tl7pev2n3f7t4rvpsiv35t8', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-11-29 03:49:00'),
(557, 17, 'hbn6jv5nmjvn575hatltkbv78b', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-11-30 23:19:00'),
(568, NULL, 'dfns2orum8eg11hh51o7bekcgo', '2001:4860:7:c0c::f2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 24, '2025-12-01 10:30:00'),
(571, NULL, 'lbf6km6b946udgbs4onktd3csr', '2001:4451:13cd:3c00:b1d9:2f10:d891:5e29', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-12-02 00:18:00'),
(572, 8, 'lbf6km6b946udgbs4onktd3csr', '2001:4451:13cd:3c00:b1d9:2f10:d891:5e29', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-12-02 00:18:00'),
(573, 8, 'lbf6km6b946udgbs4onktd3csr', '2001:4451:13cd:3c00:b1d9:2f10:d891:5e29', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-12-02 00:20:00'),
(574, 17, '6cnesoqaj44dblvc1ruijqdpch', '2001:4451:13cd:3c00:9c40:15e2:5fcf:4519', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-12-02 01:59:00'),
(575, NULL, '69pe5m3gnpaam2kd95bpdp3fuc', '2001:4860:7:80c::ff', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 24, '2025-12-02 02:26:00'),
(576, 17, '6cnesoqaj44dblvc1ruijqdpch', '2001:4451:13cd:3c00:9c40:15e2:5fcf:4519', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 25, '2025-12-02 02:33:00'),
(577, 17, '6cnesoqaj44dblvc1ruijqdpch', '2001:4451:13cd:3c00:9c40:15e2:5fcf:4519', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 26, '2025-12-02 02:34:00'),
(580, 17, '6cnesoqaj44dblvc1ruijqdpch', '2001:4451:13cd:3c00:9c40:15e2:5fcf:4519', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-12-02 06:03:00'),
(581, 17, '6cnesoqaj44dblvc1ruijqdpch', '2001:4451:13cd:3c00:9c40:15e2:5fcf:4519', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-12-02 06:03:00'),
(582, 17, '6cnesoqaj44dblvc1ruijqdpch', '2001:4451:13cd:3c00:9c40:15e2:5fcf:4519', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-12-02 06:06:00'),
(584, NULL, 'mogbf27dc9uf3vgh3pepfoi8i6', '66.249.65.163', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.7390.122 Mobile Safari/537.36 (compatible; GoogleOther)', 29, '2025-12-02 10:19:00'),
(585, NULL, 'cg4qra2ltt06mhcj0lopb35cu7', '5.39.109.189', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 26, '2025-12-02 10:20:00'),
(588, NULL, '58j8pv3rshd8ahm0nhkp7qrli3', '66.249.65.162', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.7390.122 Mobile Safari/537.36 (compatible; GoogleOther)', 23, '2025-12-02 10:22:00'),
(589, NULL, 'jlrsv9rlcmfacprdcr18mg1b30', '66.249.65.163', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.7390.122 Mobile Safari/537.36 (compatible; GoogleOther)', 24, '2025-12-02 10:22:00'),
(590, NULL, 'j7ls4egf9ad9kf40k6m63aqcf7', '51.75.236.148', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 26, '2025-12-02 10:25:00'),
(636, NULL, 'd31sjkpi10f5rksa9tpu632qm8', '142.44.228.147', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2025-12-03 01:33:00'),
(637, NULL, '5onh4u8j90a4634ip8ggb1811n', '15.235.98.213', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2025-12-03 01:34:00'),
(639, NULL, 'v1lus9cifj4c3kgn27i3m66qka', '51.222.95.16', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2025-12-03 01:51:00'),
(640, 17, 'qskdhpedd1me65qi0mp793600s', '112.209.67.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-12-03 01:54:00'),
(641, NULL, 'mrtm47qjhfuom9ugj6fpkdt254', '51.161.65.13', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2025-12-03 01:55:00'),
(642, NULL, 'sko2jv58jllon1435jk9e2g1dj', '54.39.89.136', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2025-12-03 02:08:00'),
(643, NULL, 'vqj98pk6gd9mflb2e37f9qav5v', '15.235.98.51', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2025-12-03 02:10:00'),
(645, 17, 'qskdhpedd1me65qi0mp793600s', '112.209.67.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-12-03 02:28:00'),
(647, 17, 'f2l1m4ag7eojtqki57vs5hpap3', '112.209.67.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-12-03 02:49:00'),
(648, 17, 'f2l1m4ag7eojtqki57vs5hpap3', '112.209.67.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-12-03 02:57:00'),
(649, NULL, '7c2gees7k1cq0q57mh92hcveim', '2001:4860:7:80c::fe', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 24, '2025-12-03 03:15:00'),
(650, NULL, '62f85087vsj9ckdtll2b190inp', '2001:4860:7:80c::ea', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 24, '2025-12-03 03:15:00'),
(651, NULL, 'nsctoickp0a161e45gsjpa3nug', '5.39.1.241', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2025-12-03 03:16:00'),
(652, NULL, '21fs6fg568hkel8m2u6b41old2', '5.39.1.230', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2025-12-03 03:20:00'),
(653, NULL, '78rm82kghagml9jtcusrjefah7', '37.59.204.134', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2025-12-03 03:33:00'),
(654, NULL, 'honbttfdt7rj7a0ck96hpiests', '51.75.236.150', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2025-12-03 03:35:00'),
(655, NULL, '5q2p8l0236k0s66sairm1cvmsa', '51.75.236.134', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2025-12-03 03:51:00'),
(656, NULL, 'ofcbsasla2the5730fib084tgu', '51.68.247.207', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2025-12-03 03:54:00'),
(657, NULL, 'lp142q9re5tat8ppgmrc2rg29k', '2001:4860:7:80c::ea', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 24, '2025-12-03 05:23:00'),
(658, NULL, '60jt2ojv4lt7pqi9j1su61tnrp', '5.39.1.245', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 25, '2025-12-03 05:49:00'),
(659, NULL, '9q1pu26rn6h6nhghp8qt53d4ql', '51.68.247.200', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 25, '2025-12-03 05:50:00'),
(660, 17, 'qskdhpedd1me65qi0mp793600s', '112.209.67.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-12-03 06:34:00'),
(675, NULL, 'afvk2djmp40luauaq2dlmpj35s', '2001:4860:7:80c::e2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 24, '2025-12-03 20:28:00'),
(678, NULL, '8def23gvt332sagfjgrblmg07i', '2001:4860:7:40c::dd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 24, '2025-12-04 00:20:00'),
(679, NULL, '50dgknus663tq5ke0d5i4ogdve', '2001:4860:7:40c::e3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 24, '2025-12-04 00:57:00'),
(684, NULL, 'k4caovl5od8sgpi7j1glnd1np0', '2001:4860:7:c0c::f9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 24, '2025-12-04 07:23:00'),
(696, NULL, 't7563np5908ojgplq9enh0uauc', '122.162.144.123', 'Mozilla/5.0 (X11; Fedora; Linux x86_64; rv:114.0) Gecko/20100101 Firefox/114.0', 24, '2025-12-04 07:30:00'),
(697, NULL, 't7563np5908ojgplq9enh0uauc', '122.162.144.123', 'Mozilla/5.0 (X11; Fedora; Linux x86_64; rv:114.0) Gecko/20100101 Firefox/114.0', 29, '2025-12-04 07:30:00'),
(709, NULL, 't7563np5908ojgplq9enh0uauc', '122.162.144.123', 'Mozilla/5.0 (X11; Fedora; Linux x86_64; rv:114.0) Gecko/20100101 Firefox/114.0', 24, '2025-12-04 07:32:00'),
(710, NULL, 't7563np5908ojgplq9enh0uauc', '122.162.144.123', 'Mozilla/5.0 (X11; Fedora; Linux x86_64; rv:114.0) Gecko/20100101 Firefox/114.0', 29, '2025-12-04 07:32:00'),
(711, NULL, 't7563np5908ojgplq9enh0uauc', '122.162.144.123', 'Mozilla/5.0 (X11; Fedora; Linux x86_64; rv:114.0) Gecko/20100101 Firefox/114.0', 28, '2025-12-04 07:32:00'),
(712, NULL, 't7563np5908ojgplq9enh0uauc', '122.162.144.123', 'Mozilla/5.0 (X11; Fedora; Linux x86_64; rv:114.0) Gecko/20100101 Firefox/114.0', 23, '2025-12-04 07:32:00'),
(713, NULL, 'ac0c6srcllj1ukdac1nkn1g11t', '103.231.240.86', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 24, '2025-12-04 07:39:00'),
(714, NULL, 'ac0c6srcllj1ukdac1nkn1g11t', '103.231.240.86', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 24, '2025-12-04 07:44:00'),
(715, NULL, 'u1jirh8njfucdt3k8n4i8ac2mu', '2001:4860:7:50c::f8', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 24, '2025-12-04 10:00:00'),
(718, NULL, '0qtovp08ud61cbp61tma6olt73', '51.195.244.140', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2025-12-04 17:09:00'),
(719, NULL, 'tifsoedv77ebcrtpau5si0o7eg', '51.195.183.34', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2025-12-04 17:10:00'),
(752, NULL, 't8p8rebetrihv9rmnv1m7fn33a', '2001:4860:7:50c::e9', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 24, '2025-12-04 23:05:00'),
(765, NULL, 'oo7bvge8ciilqitdl7o828vcf5', '2001:4860:7:40c::ea', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 24, '2025-12-05 00:36:00'),
(766, NULL, '0mjvaccmck922h1r2ip8osaqjj', '198.244.242.176', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 8, '2025-12-05 01:27:00'),
(767, NULL, 'umopjbld6oevsu9cvoaotmdafe', '51.89.129.225', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 8, '2025-12-05 01:30:00'),
(768, NULL, 'f47ov9atjc2t8sc6s1o0dmn7hu', '198.244.168.38', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 19, '2025-12-05 01:40:00'),
(769, NULL, 'cmu818u3cq569ujceijkt99gtn', '198.244.168.49', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 19, '2025-12-05 01:40:00'),
(772, NULL, 'r22fh0lpl5als6f0tr871gkvrb', '2001:4453:145:8400:f94a:cfad:b0d2:fe3b', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 23, '2025-12-05 02:35:00'),
(773, NULL, 'qh3jph3aokuun2rkqt9rafktv5', '2001:4453:145:8400:f94a:cfad:b0d2:fe3b', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 23, '2025-12-05 03:01:00'),
(774, NULL, 'eo9ip84b6iah93p0bgps6un652', '198.244.226.122', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2025-12-05 03:14:00'),
(775, NULL, '1i2ooqc79p083n7m2hiqak0nan', '51.195.215.209', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2025-12-05 03:15:00'),
(776, 17, '61v3tmp68c5f5g9jtjuj0mqsme', '2001:4451:13d5:f00:4534:c766:9deb:ddb0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-12-05 05:51:00'),
(779, NULL, 'nqrn23gcn1qgfvs5sln9dn50n7', '2001:4451:13d5:f00:4534:c766:9deb:ddb0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-12-05 07:02:00'),
(780, 17, '61v3tmp68c5f5g9jtjuj0mqsme', '2001:4451:13d5:f00:4534:c766:9deb:ddb0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 28, '2025-12-05 07:09:00'),
(781, 17, '61v3tmp68c5f5g9jtjuj0mqsme', '2001:4451:13d5:f00:4534:c766:9deb:ddb0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-12-05 07:22:00'),
(784, NULL, 'igvg7v8hmu3obbihp40r7a9dhu', '198.244.168.235', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 52, '2025-12-05 15:57:00'),
(785, NULL, '65b5abfvacuctrclivo2rhc291', '198.244.240.187', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 27, '2025-12-05 19:55:00'),
(786, NULL, '61ee764vt8dmthmgb7c0ob9kmi', '51.89.129.116', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 27, '2025-12-05 19:56:00'),
(788, NULL, 'jqfhgkksdd288b9ru66oh849v8', '2a03:2880:f806:1a::', 'meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)', 23, '2025-12-07 15:40:00'),
(800, NULL, '3nqojp9bqcuq930op65j8nmp0p', '198.244.226.170', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 26, '2025-12-08 13:40:00'),
(802, NULL, 'rdgrf66lba6m0immhs01venlj2', '51.89.129.215', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2025-12-09 00:37:00'),
(803, NULL, 'gcjcu65al0rm225ctu9u96comj', '51.89.129.161', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2025-12-09 02:02:00'),
(808, NULL, 'fu40ic9un3r3ge6jd67e3l7iqq', '2001:4860:7:80c::d6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 24, '2025-12-09 08:36:00'),
(813, NULL, '1l2csg7551sc850lkvtsub81ge', '51.195.215.103', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 25, '2025-12-09 10:33:00'),
(815, NULL, '3fj9mac38vfef1b65q8vu6k7lo', '198.244.240.129', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2025-12-09 11:15:00'),
(816, NULL, 'll4o0m40a5ifb42u9kuvhum6v0', '51.89.129.127', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2025-12-09 11:39:00'),
(817, NULL, 'ohodqvfoe2vpibr29v86ep4mhi', '54.38.147.224', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2025-12-09 12:01:00'),
(835, NULL, '974b4dgtcogronlrassm82jpk0', '2001:4860:7:b0c::f', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 24, '2025-12-10 07:06:00'),
(836, 7, 'vffb6jm3gl4v8h27vfibc3mf2v', '124.104.82.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 25, '2025-12-10 07:39:00'),
(860, NULL, 'lcog6u41d5l21bb6sork0bhl17', '198.244.242.199', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2025-12-10 22:53:00'),
(861, NULL, 'c8h8hgv8lp5cr076fk6kucrc40', '51.89.129.158', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 8, '2025-12-11 04:46:00'),
(862, NULL, 'ooalrs7be6n665oi2ii0o7ibmk', '51.195.244.228', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 19, '2025-12-11 05:14:00'),
(863, 17, 'it0e5a4o668t9j4bpkj2e3p2ja', '124.104.82.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-11 05:57:00'),
(864, 17, 'it0e5a4o668t9j4bpkj2e3p2ja', '124.104.82.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-11 06:15:00'),
(865, 17, 'it0e5a4o668t9j4bpkj2e3p2ja', '124.104.82.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2025-12-11 06:16:00'),
(868, 17, 'it0e5a4o668t9j4bpkj2e3p2ja', '124.104.82.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-11 08:03:00'),
(869, 17, 'it0e5a4o668t9j4bpkj2e3p2ja', '124.104.82.88', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 23, '2025-12-11 08:04:00'),
(870, 17, 'it0e5a4o668t9j4bpkj2e3p2ja', '124.104.82.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-11 08:11:00'),
(871, 17, 'it0e5a4o668t9j4bpkj2e3p2ja', '124.104.82.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2025-12-11 08:16:00'),
(872, 17, 'it0e5a4o668t9j4bpkj2e3p2ja', '124.104.82.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2025-12-11 08:18:00'),
(873, NULL, 'hhucidkvbqd5tk3002llri99lk', '51.89.129.68', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2025-12-11 09:57:00'),
(876, NULL, 'mqns1m41sr647q6ed7q26tlck4', '37.59.204.156', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 52, '2025-12-11 21:51:00'),
(878, NULL, 'bndl7j54c389qv8mib6slpfr3s', '2001:4860:7:80c::ea', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 24, '2025-12-12 01:40:00'),
(880, NULL, 'agg2mbc21bkr7up3kjo06o7v30', '5.39.109.172', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 51, '2025-12-12 05:03:00'),
(881, 17, '7iaijp4rj791um7b6gguvvc6o1', '2001:4451:1371:c000:4d54:858f:201b:833e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2025-12-12 05:38:00'),
(882, 8, '8u7ocuu25mjmf1f6bjo7bfkdv1', '2001:4451:1371:c000:40e6:a16b:448f:b3ac', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 06:46:00'),
(883, 8, '8u7ocuu25mjmf1f6bjo7bfkdv1', '2001:4451:1371:c000:40e6:a16b:448f:b3ac', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 06:51:00'),
(884, 8, '8u7ocuu25mjmf1f6bjo7bfkdv1', '2001:4451:1371:c000:40e6:a16b:448f:b3ac', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 06:56:00'),
(885, 17, '7iaijp4rj791um7b6gguvvc6o1', '2001:4451:1371:c000:4d54:858f:201b:833e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 06:59:00'),
(886, 8, '8u7ocuu25mjmf1f6bjo7bfkdv1', '2001:4451:1371:c000:40e6:a16b:448f:b3ac', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 07:12:00'),
(887, 8, '8u7ocuu25mjmf1f6bjo7bfkdv1', '2001:4451:1371:c000:40e6:a16b:448f:b3ac', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 07:29:00'),
(888, 8, '8u7ocuu25mjmf1f6bjo7bfkdv1', '2001:4451:1371:c000:40e6:a16b:448f:b3ac', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 07:34:00'),
(889, 8, '8u7ocuu25mjmf1f6bjo7bfkdv1', '2001:4451:1371:c000:40e6:a16b:448f:b3ac', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 07:36:00'),
(890, 8, '8u7ocuu25mjmf1f6bjo7bfkdv1', '2001:4451:1371:c000:40e6:a16b:448f:b3ac', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2025-12-12 07:43:00'),
(891, 8, '8u7ocuu25mjmf1f6bjo7bfkdv1', '2001:4451:1371:c000:40e6:a16b:448f:b3ac', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 07:50:00'),
(892, 8, '8u7ocuu25mjmf1f6bjo7bfkdv1', '2001:4451:1371:c000:40e6:a16b:448f:b3ac', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2025-12-12 07:55:00'),
(893, 8, '8u7ocuu25mjmf1f6bjo7bfkdv1', '2001:4451:1371:c000:40e6:a16b:448f:b3ac', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 07:59:00'),
(894, 8, '8u7ocuu25mjmf1f6bjo7bfkdv1', '2001:4451:1371:c000:40e6:a16b:448f:b3ac', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2025-12-12 08:09:00'),
(895, 17, '7iaijp4rj791um7b6gguvvc6o1', '2001:4451:1371:c000:4d54:858f:201b:833e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 08:11:00'),
(896, 17, '7iaijp4rj791um7b6gguvvc6o1', '2001:4451:1371:c000:4d54:858f:201b:833e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 08:15:00'),
(897, NULL, '6sl7e5sb90vfcugh8ilimet52s', '2001:4451:1371:c000:49b3:5dd2:8bf8:473e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-12-12 08:18:00'),
(898, 17, '7iaijp4rj791um7b6gguvvc6o1', '2001:4451:1371:c000:4d54:858f:201b:833e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 08:20:00'),
(899, 8, '8u7ocuu25mjmf1f6bjo7bfkdv1', '2001:4451:1371:c000:40e6:a16b:448f:b3ac', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2025-12-12 08:20:00'),
(900, 8, '8u7ocuu25mjmf1f6bjo7bfkdv1', '2001:4451:1371:c000:40e6:a16b:448f:b3ac', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 08:20:00'),
(901, NULL, '6sl7e5sb90vfcugh8ilimet52s', '2001:4451:1371:c000:49b3:5dd2:8bf8:473e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 23, '2025-12-12 08:21:00'),
(902, 17, '7iaijp4rj791um7b6gguvvc6o1', '2001:4451:1371:c000:4d54:858f:201b:833e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 08:24:00'),
(903, 17, '7iaijp4rj791um7b6gguvvc6o1', '2001:4451:1371:c000:4d54:858f:201b:833e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 08:25:00'),
(904, 17, '7iaijp4rj791um7b6gguvvc6o1', '2001:4451:1371:c000:4d54:858f:201b:833e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 08:27:00'),
(905, 17, '7iaijp4rj791um7b6gguvvc6o1', '2001:4451:1371:c000:4d54:858f:201b:833e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 08:38:00'),
(906, 17, '7iaijp4rj791um7b6gguvvc6o1', '2001:4451:1371:c000:4d54:858f:201b:833e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 08:40:00'),
(907, 17, '7iaijp4rj791um7b6gguvvc6o1', '2001:4451:1371:c000:4d54:858f:201b:833e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 08:44:00'),
(908, 17, '7iaijp4rj791um7b6gguvvc6o1', '2001:4451:1371:c000:4d54:858f:201b:833e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 08:45:00'),
(911, 17, '7iaijp4rj791um7b6gguvvc6o1', '2001:4451:1371:c000:4d54:858f:201b:833e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 08:55:00'),
(912, 17, '7iaijp4rj791um7b6gguvvc6o1', '2001:4451:1371:c000:4d54:858f:201b:833e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 08:56:00'),
(913, 17, '7iaijp4rj791um7b6gguvvc6o1', '2001:4451:1371:c000:4d54:858f:201b:833e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 08:57:00'),
(914, 17, '7iaijp4rj791um7b6gguvvc6o1', '2001:4451:1371:c000:4d54:858f:201b:833e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-12 08:58:00'),
(916, NULL, 'fn85h7fs3jt9blgelei5jc5m5i', '2001:4451:1371:c000:6d3d:5864:6188:cba4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-13 00:08:00'),
(917, 17, 'a0jnadgqel678ikl47hld2b13n', '112.209.71.168', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-13 01:56:00'),
(918, 8, 'fn85h7fs3jt9blgelei5jc5m5i', '112.209.71.168', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-13 03:34:00'),
(926, 35, 's9t5m2fo0gj6c70mtnp18qjaas', '112.209.189.162', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 23, '2025-12-15 05:59:00'),
(930, 17, '47em27dd11jvotf080s32143cd', '112.209.185.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2025-12-15 23:39:00'),
(931, 17, '47em27dd11jvotf080s32143cd', '112.209.185.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-16 00:00:00'),
(932, 17, '47em27dd11jvotf080s32143cd', '112.209.185.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2025-12-16 01:04:00'),
(933, 17, '47em27dd11jvotf080s32143cd', '112.209.185.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2025-12-16 01:04:00'),
(935, NULL, '9jkjd2ot1l1pp33pu6j2l9luei', '2a03:2880:f806:1f::', 'meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)', 29, '2025-12-16 19:12:00'),
(936, NULL, 'rr84eg5780nprficvui0hitlk4', '37.59.204.131', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2025-12-16 19:24:00'),
(938, 17, 'ft1t215p2ka7lr66o78flltast', '112.209.180.181', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-17 00:58:00'),
(939, 17, 'ft1t215p2ka7lr66o78flltast', '112.209.180.181', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2025-12-17 00:58:00'),
(947, 17, 'ft1t215p2ka7lr66o78flltast', '112.209.180.181', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 51, '2025-12-17 01:00:00'),
(949, 17, 'ft1t215p2ka7lr66o78flltast', '112.209.180.181', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 25, '2025-12-17 01:00:00'),
(951, 17, 'ft1t215p2ka7lr66o78flltast', '112.209.180.181', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2025-12-17 01:00:00'),
(952, 17, 'ft1t215p2ka7lr66o78flltast', '112.209.180.181', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 8, '2025-12-17 01:00:00'),
(956, 17, 'ft1t215p2ka7lr66o78flltast', '112.209.180.181', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2025-12-17 01:42:00'),
(957, 17, 'ft1t215p2ka7lr66o78flltast', '112.209.180.181', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-17 01:42:00'),
(958, 17, 'ft1t215p2ka7lr66o78flltast', '112.209.180.181', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2025-12-17 01:42:00'),
(959, 17, 'ft1t215p2ka7lr66o78flltast', '112.209.180.181', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2025-12-17 01:43:00'),
(960, 17, 'ft1t215p2ka7lr66o78flltast', '112.209.180.181', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2025-12-17 01:47:00'),
(961, 17, 'ft1t215p2ka7lr66o78flltast', '112.209.180.181', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2025-12-17 01:47:00'),
(979, 17, 'ft1t215p2ka7lr66o78flltast', '112.209.180.181', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2025-12-17 04:48:00'),
(980, 17, 'ft1t215p2ka7lr66o78flltast', '112.209.180.181', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-17 06:50:00'),
(981, NULL, 'd92e9hdc34sgegj0vl0f162omb', '2001:4860:7:50c::ff', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2025-12-17 10:23:00'),
(982, NULL, 'h9jq1ehg1uv1vcfrv6tgu6rk8c', '2001:4860:7:40c::ea', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2025-12-17 10:25:00'),
(991, NULL, 'ngct91blr7sr77mcv69qld9lgp', '2a03:2880:f806:6::', 'meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)', 29, '2025-12-18 15:29:00'),
(996, NULL, 'php29th1begbqt95p4tgem7m2n', '112.209.76.241', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-18 23:31:00'),
(999, 17, '1h90fq66svmrbo2d59779jl9g6', '112.209.76.241', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-19 03:02:00'),
(1000, 17, '1h90fq66svmrbo2d59779jl9g6', '112.209.76.241', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2025-12-19 06:19:00'),
(1002, 17, '1h90fq66svmrbo2d59779jl9g6', '112.209.76.241', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2025-12-19 06:28:00'),
(1003, 17, '1h90fq66svmrbo2d59779jl9g6', '112.209.76.241', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2025-12-19 06:28:00'),
(1006, NULL, 'fucmf0ogr9h6gbqrdnrtuc3uen', '112.209.76.241', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 28, '2025-12-20 03:37:00'),
(1010, NULL, 'go3seqh7m7ehoio6kcplicf9ad', '2001:4860:7:80c::cb', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2025-12-20 07:32:00'),
(1012, NULL, 'qnllku3fln4v3rq6ul0pte6135', '74.7.242.139', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36; compatible; OAI-SearchBot/1.3; +https://openai.com/searchbot', 24, '2025-12-20 11:18:00'),
(1019, NULL, 'mhrmr5q8rcmuklu5rmnrr22p4q', '112.209.178.171', 'Mozilla/5.0 (Linux; Android 13; SM-G981B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 23, '2025-12-22 03:23:00'),
(1022, NULL, 'pmri39ohm5faufvffc7d8jchbb', '66.249.65.165', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.7390.122 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 24, '2025-12-22 03:56:00'),
(1026, NULL, '6n51pbufsaaeu8uam1fctala4o', '2a03:2880:f806:b::', 'meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)', 24, '2025-12-22 12:53:00'),
(1028, NULL, 'unlg9clpn4chbs9f0cumigh7s4', '2a03:2880:f806:20::', 'meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)', 28, '2025-12-22 13:03:00'),
(1043, NULL, 'vqho5c5a3jcfgdr73q5a3us7dm', '94.23.188.207', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 52, '2025-12-23 13:32:00'),
(1044, NULL, '8ed5ee7ph8nbj0bbagr4p0pnho', '51.75.236.149', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 27, '2025-12-23 14:07:00'),
(1046, NULL, '1g7lno140dnd2ef79i2823q2ql', '47.251.95.232', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', 24, '2025-12-24 03:16:00'),
(1056, NULL, 'gds7rqsep35vjbl1qn0o07nhdt', '2001:4860:7:40c::fe', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2025-12-25 23:34:00'),
(1057, NULL, 'nvvqn3dtbpb4vnmc81m3923bne', '47.82.11.83', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 Edg/135.0.0.0', 24, '2025-12-26 04:20:00'),
(1059, NULL, 'kbpdht5dj059ehidgldcqgnc8d', '40.77.167.27', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36', 28, '2025-12-26 19:53:00'),
(1072, NULL, 'lv4khamtr5a3vk6gl7kvm9n6op', '2001:4860:7:40c::fb', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2025-12-27 06:23:00'),
(1073, NULL, 'c9kp64p3e8lhsavd4pq8mosihc', '40.77.167.235', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36', 24, '2025-12-28 21:23:00'),
(1084, NULL, 'ldafevk3co8ogqcob4o1tr00uf', '122.162.144.86', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36', 29, '2025-12-29 05:07:00'),
(1085, NULL, 'ldafevk3co8ogqcob4o1tr00uf', '122.162.144.86', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36', 28, '2025-12-29 05:07:00'),
(1086, NULL, 'ldafevk3co8ogqcob4o1tr00uf', '122.162.144.86', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36', 24, '2025-12-29 05:07:00'),
(1087, NULL, 'ldafevk3co8ogqcob4o1tr00uf', '122.162.144.86', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36', 23, '2025-12-29 05:07:00'),
(1098, NULL, 'ldafevk3co8ogqcob4o1tr00uf', '122.162.144.86', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36', 29, '2025-12-29 05:09:00'),
(1099, NULL, 'ldafevk3co8ogqcob4o1tr00uf', '122.162.144.86', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36', 28, '2025-12-29 05:09:00'),
(1100, NULL, 'ldafevk3co8ogqcob4o1tr00uf', '122.162.144.86', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36', 24, '2025-12-29 05:09:00'),
(1101, NULL, 'ldafevk3co8ogqcob4o1tr00uf', '122.162.144.86', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36', 23, '2025-12-29 05:09:00'),
(1112, NULL, '8do9d27ptva6kq6403a3ta3e3v', '122.162.144.86', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36', 29, '2025-12-29 09:28:00'),
(1130, NULL, 'a97t8ls437qd95vt575ph1j5oo', '124.156.162.36', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36', 23, '2025-12-30 23:21:00'),
(1136, NULL, '0tpsl4ea5307gochusd6qhihu6', '2001:4860:7:40c::e1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2025-12-31 03:56:00'),
(1143, NULL, 'u5n80bbtedr214cml1j9cgiqeo', '111.119.212.122', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 29, '2025-12-31 08:54:00'),
(1144, NULL, 'oe1d7u6s0ks8bjhg49vb712dgb', '119.28.133.27', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.64 Safari/537.36 Edg/101.0.1210.47', 23, '2025-12-31 09:52:00'),
(1147, NULL, '96j5ike4u7mvak71j89blpk1pm', '124.156.104.13', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 28, '2025-12-31 10:36:00'),
(1149, NULL, '1nqg9fg1jbsuvpid43p74ogcdh', '2001:4860:7:50c::f1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2025-12-31 12:28:00'),
(1150, NULL, 't8d0e5dpo9jtbhnrq9de5jif2k', '2001:4860:7:40c::dd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2025-12-31 12:29:00'),
(1151, NULL, '5cs8pm7886estsfc2fd8qn1l12', '119.28.227.140', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.64 Safari/537.36 Edg/101.0.1210.47', 29, '2025-12-31 12:34:00'),
(1156, NULL, 'm3vdj05q95fp9j0espqjph68t9', '2a03:2880:f814:2::', 'meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)', 19, '2026-01-01 03:14:00'),
(1157, NULL, '8t9gvtasn58d78h9u6m43idh5u', '37.59.204.128', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-01-01 06:34:00'),
(1158, NULL, 'j9vc1j0o68qgi11u4trsj6seu4', '37.59.204.152', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-01-01 06:38:00'),
(1159, NULL, '0u7iurq67blp1d999789v3rb0k', '51.68.247.201', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2026-01-01 10:28:00'),
(1160, NULL, 'c3ktelvviv9cfbvmt190jl7ctd', '5.39.1.225', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2026-01-01 10:33:00'),
(1161, NULL, 'u7hgs0k59jhn8mr8i273jvu1fe', '37.59.204.148', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 26, '2026-01-01 14:51:00'),
(1162, NULL, 'vop7c240oevb0e03h26j7t1nke', '2001:4860:7:50c::ec', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-02 07:00:00'),
(1163, NULL, 'umda5pv0g3rpc71dv6nqtamdf9', '2001:4860:7:50c::fe', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-02 15:12:00'),
(1166, NULL, 'jc8cek75qfktc11cnjuukcj64f', '66.249.65.172', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.7390.122 Mobile Safari/537.36 (compatible; GoogleOther)', 8, '2026-01-02 17:25:00'),
(1167, NULL, 'kun45cvhnvpr5lci7okcpeebj9', '66.249.65.163', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.7390.122 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 24, '2026-01-02 17:30:00'),
(1168, NULL, '6bbim44bdj5h552n08s255mrs6', '2001:4860:7:c0c::f6', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-02 17:37:00'),
(1169, NULL, 'ich9uedtvgtkceko92h0h6vj0j', '43.159.195.223', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36', 52, '2026-01-02 17:38:00'),
(1170, NULL, 'fibi5fc765tn5o8n5ft65td6nn', '2001:4860:7:50c::f8', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-02 18:58:00'),
(1178, NULL, '38thbunbcdt4jb0gucmhlls2s3', '176.31.139.11', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-01-02 21:43:00'),
(1180, NULL, '1h9mueabsdtt2vgfdu2q174ndm', '166.108.205.112', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36', 51, '2026-01-02 22:47:00'),
(1189, NULL, 's45tgcdhga73nh7pd3fnij4gqe', '82.156.88.218', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 8, '2026-01-03 03:39:00'),
(1191, NULL, '8vb7h6jep15keejbpelltv6ndl', '43.129.204.90', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 24, '2026-01-03 04:23:00'),
(1193, NULL, 'vdf7e1i59aijq2h0kkfj8lge77', '51.195.244.232', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-01-03 05:47:00'),
(1194, NULL, 'l84d9vo180s1h62rc57mb3sm91', '198.244.240.106', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-01-03 05:51:00'),
(1198, NULL, 'epburpr6mmrl7ovd4l9b9md6le', '43.138.123.56', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 27, '2026-01-03 07:22:00'),
(1200, NULL, 'cdri1c2at5pf9nr9d1i2sn79j9', '124.156.159.18', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.64 Safari/537.36 Edg/101.0.1210.47', 25, '2026-01-03 08:08:00'),
(1238, NULL, '2a016gpa31b7j5l8be5fo110ea', '51.89.129.24', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 25, '2026-01-04 13:30:00'),
(1247, NULL, '6qf8brhqok1l8d8tgp3bqng605', '198.244.226.46', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-01-04 16:59:00');
INSERT INTO `recent_views` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `product_id`, `viewed_at`) VALUES
(1248, NULL, 'sjliun9nfcgvsa4kp5fb52p3tb', '198.244.183.33', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-01-04 17:11:00'),
(1257, NULL, 'o628ge5tbpt7snco9imvjko430', '51.195.244.223', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-01-04 18:49:00'),
(1258, NULL, '48c8pvh3q3tjktmfrsj6eq1o1i', '198.244.240.29', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-01-04 18:53:00'),
(1259, NULL, '3vbrgu4o6ljmhcan9off6vm4pu', '198.244.242.78', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-01-04 19:32:00'),
(1260, NULL, 'dslffrb0guecuv94v0cbj7cike', '54.38.147.244', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-01-04 19:33:00'),
(1285, NULL, '8kpqjkvuvq7l083uqimc69jmm5', '2001:4860:7:80c::cc', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-04 23:13:00'),
(1290, NULL, '9duhvfccauadrellb8ps9i7a4q', '198.244.183.13', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 8, '2026-01-04 23:40:00'),
(1291, NULL, 'cun2jg50q7eos35g33vjtgq3m3', '54.38.147.165', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 19, '2026-01-04 23:45:00'),
(1292, NULL, 'dnlbd914he2gp11tt21u7126pn', '54.38.147.226', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2026-01-04 23:51:00'),
(1294, NULL, 'doadm7g01hfi5hj23k9gr8rg6o', '54.38.147.102', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2026-01-05 00:41:00'),
(1295, NULL, 'uqag19ksga0g5r0mkitubvqv26', '51.195.244.97', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 51, '2026-01-05 00:56:00'),
(1296, NULL, 'efjf3k6tqoc8n12cufmhds5pau', '51.195.215.82', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 27, '2026-01-05 01:02:00'),
(1297, NULL, '6gtufd0usd5u9mfktuuh8cpjd4', '51.195.215.25', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 52, '2026-01-05 01:07:00'),
(1301, 17, 'qsuqt2jih102s78c8ub3bnv7vk', '112.209.76.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-05 05:35:00'),
(1302, 17, 'qsuqt2jih102s78c8ub3bnv7vk', '112.209.76.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-05 05:47:00'),
(1303, 17, 'qsuqt2jih102s78c8ub3bnv7vk', '112.209.76.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-05 05:48:00'),
(1305, NULL, '2po5pdcuujdqocvfi1d3tbvgve', '2001:4860:7:80c::e6', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-05 06:41:00'),
(1306, NULL, 'hr1t5vgjjcjnl2ipmpuhpl0ovi', '2001:4860:7:50c::f5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-05 06:41:00'),
(1308, 17, 'qsuqt2jih102s78c8ub3bnv7vk', '112.209.76.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-05 06:54:00'),
(1310, NULL, '2maqo6q2fk1bml5ou4j84rjjkl', '112.209.76.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2026-01-05 07:47:00'),
(1312, NULL, 'um7k9bv05g5kncekt2stodns3u', '2001:4860:7:80c::fd', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-05 09:22:00'),
(1320, NULL, 'fuijgsq83sb6t4jfbp7otj6a3e', '20.169.78.127', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot', 24, '2026-01-05 19:09:00'),
(1322, NULL, 'uplatu7fd7nlt0nqe5iiuemo8p', '112.209.76.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-06 00:22:00'),
(1323, 17, 'uplatu7fd7nlt0nqe5iiuemo8p', '112.209.76.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2026-01-06 00:22:00'),
(1324, 17, 'uplatu7fd7nlt0nqe5iiuemo8p', '112.209.76.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2026-01-06 00:22:00'),
(1325, 17, 'uplatu7fd7nlt0nqe5iiuemo8p', '112.209.76.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2026-01-06 00:23:00'),
(1326, 17, 'uplatu7fd7nlt0nqe5iiuemo8p', '112.209.76.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2026-01-06 00:23:00'),
(1327, 17, 'uplatu7fd7nlt0nqe5iiuemo8p', '112.209.76.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2026-01-06 01:26:00'),
(1328, NULL, 'eoobbf3md044rjn32lgc3ql8oe', '110.54.188.242', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-06 02:57:00'),
(1332, NULL, '1kf8oq2pgf687bugf0ev2huiki', '2001:4451:138c:d00:7c0f:29ed:e5da:267c', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-07 06:21:00'),
(1333, NULL, '282n8u1ch81gj3rk81m32ns5p6', '180.190.230.69', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/537.1.0.47.110;FBBV/846660078;FBDV/iPhone14,3;FBMD/iPhone;FBSN/iOS;FBSV/18.6.2;FBSS/3;FBCR/;FBID/phone;FBLC/en_US;FBOP/80]', 24, '2026-01-07 06:26:00'),
(1346, NULL, 'v5cp63lg0r6sm7sfqb2an6i7d6', '122.162.146.217', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 Vivaldi/5.3.2679.68', 29, '2026-01-07 08:29:00'),
(1347, NULL, 'v5cp63lg0r6sm7sfqb2an6i7d6', '122.162.146.217', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 Vivaldi/5.3.2679.68', 28, '2026-01-07 08:29:00'),
(1348, NULL, 'v5cp63lg0r6sm7sfqb2an6i7d6', '122.162.146.217', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 Vivaldi/5.3.2679.68', 24, '2026-01-07 08:29:00'),
(1359, NULL, 'v5cp63lg0r6sm7sfqb2an6i7d6', '122.162.146.217', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 Vivaldi/5.3.2679.68', 29, '2026-01-07 08:31:00'),
(1360, NULL, 'v5cp63lg0r6sm7sfqb2an6i7d6', '122.162.146.217', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 Vivaldi/5.3.2679.68', 28, '2026-01-07 08:31:00'),
(1361, NULL, 'v5cp63lg0r6sm7sfqb2an6i7d6', '122.162.146.217', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 Vivaldi/5.3.2679.68', 24, '2026-01-07 08:31:00'),
(1362, NULL, 'v5cp63lg0r6sm7sfqb2an6i7d6', '122.162.146.217', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 Vivaldi/5.3.2679.68', 23, '2026-01-07 08:31:00'),
(1392, NULL, 't0741esvsrllkvihdfhajl5vku', '2001:4860:7:408::fc', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-08 06:14:00'),
(1400, NULL, 'ngrj58nr1h4scv36gbq1fmvm7a', '51.68.247.221', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 26, '2026-01-08 20:57:00'),
(1402, 17, '07o8fn0apbd78h3iibl3vqmgpp', '2001:4451:138c:d00:fc32:a8a7:ce86:d5aa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 25, '2026-01-08 23:08:00'),
(1403, 17, '07o8fn0apbd78h3iibl3vqmgpp', '2001:4451:138c:d00:fc32:a8a7:ce86:d5aa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 25, '2026-01-08 23:11:00'),
(1404, 17, '07o8fn0apbd78h3iibl3vqmgpp', '2001:4451:138c:d00:fc32:a8a7:ce86:d5aa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 25, '2026-01-08 23:24:00'),
(1405, 17, '07o8fn0apbd78h3iibl3vqmgpp', '2001:4451:138c:d00:fc32:a8a7:ce86:d5aa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2026-01-08 23:25:00'),
(1406, 17, '07o8fn0apbd78h3iibl3vqmgpp', '2001:4451:138c:d00:fc32:a8a7:ce86:d5aa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2026-01-08 23:26:00'),
(1407, 17, '07o8fn0apbd78h3iibl3vqmgpp', '2001:4451:138c:d00:fc32:a8a7:ce86:d5aa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2026-01-08 23:35:00'),
(1409, 17, '07o8fn0apbd78h3iibl3vqmgpp', '112.209.78.250', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2026-01-09 00:25:00'),
(1410, NULL, 'lo4jtseq25eu7l0jtpedl82pm5', '176.31.139.23', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-01-09 01:11:00'),
(1411, 17, '07o8fn0apbd78h3iibl3vqmgpp', '112.209.75.199', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 25, '2026-01-09 01:32:00'),
(1413, NULL, '07o8fn0apbd78h3iibl3vqmgpp', '175.176.27.94', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 25, '2026-01-09 06:26:00'),
(1414, 17, '07o8fn0apbd78h3iibl3vqmgpp', '175.176.27.94', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 25, '2026-01-09 07:52:00'),
(1415, 17, '07o8fn0apbd78h3iibl3vqmgpp', '175.176.27.94', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 25, '2026-01-09 07:53:00'),
(1416, NULL, 'po6mkru1bjv4ns7pgmqcjvovcu', '51.195.244.27', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-01-09 11:33:00'),
(1417, NULL, '7v0p7ros728aq4an70krrt5ec1', '198.244.240.160', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-01-09 13:27:00'),
(1418, NULL, 'jckcihiqnrjjujdjalomfr1bee', '131.226.102.75', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_1_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/143.0.7499.151 Mobile/15E148 Safari/604.1', 24, '2026-01-09 14:44:00'),
(1420, NULL, 'lemvmo01behf0sv53n7vdpcc41', '2001:4860:7:40c::f8', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-10 01:43:00'),
(1424, NULL, '9jum526jeclovukkdt44ujd0gk', '112.209.188.46', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-10 02:51:00'),
(1428, NULL, '6kg16gnl72a0q9ptm7rqn13r74', '51.75.236.132', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 19, '2026-01-10 07:42:00'),
(1429, NULL, 'a7i9t52jph0rcvvabo4rhh82rl', '92.222.108.118', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 19, '2026-01-10 07:46:00'),
(1430, NULL, '3r4k11h1effqs8lj5i26juctt8', '66.249.65.160', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.7499.192 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 24, '2026-01-10 09:51:00'),
(1431, NULL, 'ot1rl0dha87d37va2c80va2k3o', '2001:4860:7:c0c::f9', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-10 10:51:00'),
(1433, NULL, '2s5abn3g22v21dlkpo7bk034t8', '2001:4860:7:50c::ec', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-10 16:02:00'),
(1434, NULL, 'jral3n4mj3vgrn2roo7uscvc1g', '2001:4860:7:80c::fe', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-11 01:50:00'),
(1443, NULL, 'ie78b3corl91onhk5u8objoq74', '5.39.1.237', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-01-11 10:56:00'),
(1445, NULL, 'l06us69lkvtqpd7iqhp7ob3hp6', '193.24.123.112', 'Mozilla/5.0 (Linux; Android 9; ASUS_I005DA Build/PI; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/133.0.6943.122 Mobile', 24, '2026-01-11 13:19:00'),
(1448, NULL, '0ks363tu63pst4ml9g360kk3ec', '2001:4860:7:40c::f8', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', 24, '2026-01-11 14:59:00'),
(1449, NULL, 'kibt2aecnrn3cnilhf4t54vfkv', '198.244.168.147', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 25, '2026-01-11 15:16:00'),
(1451, NULL, '5e75cjk11sft74mbnro2oj1qld', '2001:4860:7:80c::e6', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-11 22:39:00'),
(1452, NULL, '9u69g0s1bssg34l3vn8e8u02b8', '2001:4860:7:40c::ed', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-11 22:41:00'),
(1453, NULL, 'c3151pqlbe1kpqsenegoalfs2t', '2001:4860:7:50c::f4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-11 23:18:00'),
(1458, NULL, 'h6l9dc038oto5mbk35e35ccpnt', '198.244.168.193', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-01-12 00:28:00'),
(1459, NULL, 'p0966q7v81ipvt33cuvv8dn51f', '198.244.183.130', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-01-12 01:17:00'),
(1460, NULL, '7hf6bish8vem5u2duv4n5rb7ia', '54.38.147.169', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 8, '2026-01-12 01:24:00'),
(1461, NULL, 'l2gd4jfm8hfctskli07tolaa71', '198.244.168.75', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 51, '2026-01-12 02:02:00'),
(1462, NULL, 'oq49u7ao6orcsb030o7h59hssh', '198.244.240.98', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 52, '2026-01-12 03:20:00'),
(1465, 17, 'qe7kl7hm2v890jscijs75ij8n7', '112.209.75.167', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-12 06:12:00'),
(1468, NULL, '6o53tkgve5gmencduse07gn80m', '2001:4860:7:1733::fe', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-12 13:25:00'),
(1470, NULL, 'kufgp07bdlmp7vfr1ldadsq4h7', '51.68.247.197', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 26, '2026-01-12 22:01:00'),
(1471, NULL, 'hb5jamgm4bfr9r7hcdeic8qn6j', '103.186.207.227', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-12 23:09:00'),
(1472, NULL, 'tldk10uga285s42eoqphoeekcg', '2001:4860:7:b0c::fe', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-13 00:51:00'),
(1473, NULL, 'r8sn5111goanc4331qjql5umhj', '2001:4860:7:80c::d0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-01-13 00:58:00'),
(1475, NULL, 'tk6ebct8fs9ji0b31isv75svsv', '37.59.204.156', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 25, '2026-01-13 05:55:00'),
(1476, NULL, 'afnaotu2o2921r780effjlm7hr', '2001:4860:7:50c::fe', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-13 23:12:00'),
(1477, 17, 'alnvknut22olhi42vvngnivtrd', '112.209.65.196', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2026-01-13 23:14:00'),
(1478, 17, 'alnvknut22olhi42vvngnivtrd', '112.209.65.196', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 25, '2026-01-13 23:15:00'),
(1479, NULL, 'juib2t19k4ortke6ksbge0suc9', '2001:4860:7:50c::fd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-14 00:46:00'),
(1484, NULL, 'd5cuf1ih2sfu8a1v0gp929n6fj', '112.209.65.196', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-14 06:06:00'),
(1485, NULL, 'odgmevbmhk6bvksptmh2l90sh4', '223.25.9.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', 24, '2026-01-14 06:21:00'),
(1486, NULL, '3ivi20bmrb65mmgbr0d1a9kbtv', '122.49.212.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-14 06:49:00'),
(1487, 17, 'alnvknut22olhi42vvngnivtrd', '112.209.65.196', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-14 08:57:00'),
(1488, NULL, '3vbk7q9jo721q5p8g99i9sqdhv', '2001:4860:7:405::d4', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-14 09:08:00'),
(1489, NULL, '056c6mmj6cmkjfrevlsreafnfr', '31.171.59.121', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.4282.51 Safari/537.36', 24, '2026-01-14 09:26:00'),
(1490, NULL, '1qam122d2b3s2peg90aojl4e8u', '2001:4451:41ff:6400:ad2e:e40e:e10e:d538', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-14 10:08:00'),
(1491, NULL, '57b1dg5ojdan6q3g74qlpvpph2', '2001:4860:7:50c::f9', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-14 11:26:00'),
(1492, NULL, '2ihkbrth2i8ig1egb8b6e02ra8', '2001:4860:7:50c::eb', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-14 11:35:00'),
(1493, NULL, 'rppm40c5dtbuhrgpgkou0ho6gk', '2001:4860:7:80c::df', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-14 11:46:00'),
(1495, NULL, 'a1iqnv2k8ditih2udn1neoe4t7', '216.73.216.151', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 29, '2026-01-15 04:39:00'),
(1498, NULL, 'lnv8nk5kebivcpsjb8vuqt56k8', '216.73.216.151', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 28, '2026-01-15 04:39:00'),
(1502, NULL, '5cf6cs90bc7o48itdvolosijj9', '216.73.216.151', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 24, '2026-01-15 04:39:00'),
(1503, NULL, '5cf6cs90bc7o48itdvolosijj9', '216.73.216.151', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 23, '2026-01-15 04:39:00'),
(1507, NULL, 'kaurcolih17re4mugoc3dh06bp', '216.73.216.151', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 28, '2026-01-15 06:01:00'),
(1512, NULL, 'gcdhm5bkghv0o28lvs74dnnok8', '216.73.216.151', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 23, '2026-01-15 06:02:00'),
(1513, NULL, 'lr91up2acqs0umcoq1hp72qrk6', '216.73.216.151', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 29, '2026-01-15 06:02:00'),
(1523, NULL, 'ecpqlttrvh9kg3d86u0qnegmin', '216.73.216.151', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 8, '2026-01-15 06:11:00'),
(1524, NULL, 'adsab7mb16uphm6srbdsjhb9g5', '216.73.216.151', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 26, '2026-01-15 06:11:00'),
(1525, NULL, '351m6rbufj664lv6jg99f8qceb', '216.73.216.151', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 52, '2026-01-15 06:11:00'),
(1527, NULL, 'at9iaeuka6ukrhttfjm4fn2ql8', '216.73.216.151', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 51, '2026-01-15 06:12:00'),
(1528, NULL, 't5ck7hmuts0vlu4iavcuaubhaa', '216.73.216.151', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 27, '2026-01-15 06:12:00'),
(1529, NULL, 't5ck7hmuts0vlu4iavcuaubhaa', '216.73.216.151', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 19, '2026-01-15 06:12:00'),
(1530, NULL, '4o88h6n0a8pra5njlaic623277', '2001:4860:7:80c::e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-15 06:25:00'),
(1549, NULL, 'q53vqpdcisiiq14panj6o2blji', '216.73.216.151', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 24, '2026-01-15 07:30:00'),
(1552, NULL, 'isoo40hto16qnl84p2bph9cidv', '216.73.216.151', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)', 25, '2026-01-15 07:31:00'),
(1557, NULL, 'mtr8j3i6o7ukvdimmrk07l42r7', '2001:4860:7:40c::e1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-15 07:52:00'),
(1558, NULL, 'kv03ldmfn9270jkng9pll9a46g', '198.244.183.145', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 26, '2026-01-15 07:55:00'),
(1564, NULL, 'f6239886afa8jp24p1efd3s3v3', '51.89.129.75', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-01-15 10:03:00'),
(1565, 17, '8ump4qnrcedcevoi9m736ug94k', '2001:4451:133e:1a00:c59b:9bd9:513a:cf2a', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-15 23:49:00'),
(1566, 17, '8ump4qnrcedcevoi9m736ug94k', '2001:4451:133e:1a00:c59b:9bd9:513a:cf2a', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-15 23:50:00'),
(1567, 17, '8ump4qnrcedcevoi9m736ug94k', '2001:4451:133e:1a00:c59b:9bd9:513a:cf2a', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-15 23:59:00'),
(1568, 17, '8ump4qnrcedcevoi9m736ug94k', '2001:4451:133e:1a00:c59b:9bd9:513a:cf2a', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-16 00:00:00'),
(1582, 17, '5gu88kgmdqfv32dp3o3n1f7913', '2001:4451:133e:1a00:c59b:9bd9:513a:cf2a', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-16 00:56:00'),
(1583, 17, '5gu88kgmdqfv32dp3o3n1f7913', '2001:4451:133e:1a00:c59b:9bd9:513a:cf2a', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-16 00:57:00'),
(1586, 17, '5gu88kgmdqfv32dp3o3n1f7913', '2001:4451:133e:1a00:c59b:9bd9:513a:cf2a', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-16 05:40:00'),
(1587, 17, '5gu88kgmdqfv32dp3o3n1f7913', '2001:4451:133e:1a00:c59b:9bd9:513a:cf2a', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-16 05:42:00'),
(1588, 17, '5gu88kgmdqfv32dp3o3n1f7913', '2001:4451:133e:1a00:c59b:9bd9:513a:cf2a', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-16 05:43:00'),
(1589, 17, '5gu88kgmdqfv32dp3o3n1f7913', '2001:4451:133e:1a00:c59b:9bd9:513a:cf2a', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2026-01-16 06:03:00'),
(1590, NULL, 'hj4bdpfv8jr8ef3ao3k7p4ut70', '20.169.78.184', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot', 24, '2026-01-16 08:42:00'),
(1592, NULL, 'dk587hhenh3nsd06ea2qmvdpnh', '51.89.129.91', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-01-16 16:28:00'),
(1593, NULL, 'egn3sjb04j5okaq55771f6duct', '2001:4860:7:c0c::f8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-17 01:50:00'),
(1594, NULL, '3gqsst24he2r3muslj6u53dogs', '2001:4860:7:c0c::f0', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-17 03:57:00'),
(1596, NULL, 'dvmf0bie234fo4t3rttugk0vtn', '2001:4860:7:50c::ff', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-17 05:53:00'),
(1597, NULL, 'a94diql7g9uhaoovt1b10rl50o', '2001:4860:7:80c::f9', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-17 07:38:00'),
(1604, NULL, 'nhblstgsntf9ar7uuvvt8s4j3l', '2001:4860:7:80c::e9', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-17 12:14:00'),
(1610, NULL, 'b8uir4gih9hvhrtgvi09k7evua', '66.249.79.7', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.7499.192 Mobile Safari/537.36 (compatible; GoogleOther)', 23, '2026-01-18 06:47:00'),
(1614, NULL, '8ve8lnligprqustgdeh5alh5u6', '66.249.79.4', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.7499.192 Mobile Safari/537.36 (compatible; GoogleOther)', 24, '2026-01-18 21:15:00'),
(1620, NULL, 'ggcnldl4agpkt61etvp74gidrn', '66.249.79.6', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.7499.192 Mobile Safari/537.36 (compatible; GoogleOther)', 28, '2026-01-18 21:56:00'),
(1633, NULL, '30jb1br2ooavvkp4hgjv3s1jq5', '66.249.79.8', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.7499.192 Mobile Safari/537.36 (compatible; GoogleOther)', 28, '2026-01-18 22:29:00'),
(1634, NULL, 'm23jbdk427arbru4kghi4576f7', '66.249.79.7', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.7499.192 Mobile Safari/537.36 (compatible; GoogleOther)', 29, '2026-01-18 22:29:00'),
(1642, 17, '86l0s6g8nk5rm640064q0imdpp', '2001:4451:133e:1a00:55f4:d1c0:c8e1:bad7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-18 23:54:00'),
(1647, 17, '86l0s6g8nk5rm640064q0imdpp', '2001:4451:133e:1a00:55f4:d1c0:c8e1:bad7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-19 00:04:00'),
(1648, 17, '86l0s6g8nk5rm640064q0imdpp', '2001:4451:133e:1a00:55f4:d1c0:c8e1:bad7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-19 00:56:00'),
(1658, 17, '86l0s6g8nk5rm640064q0imdpp', '2001:4451:133e:1a00:55f4:d1c0:c8e1:bad7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-19 04:01:00'),
(1662, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:133e:1a00:941a:d732:c071:8663', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-19 08:38:00'),
(1672, NULL, 'b1an6j0bsmdroisqnfbtdq18qk', '148.113.130.170', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-01-19 17:44:00'),
(1675, NULL, '3pt41hrpvt37os047m3oiu3tj7', '51.195.244.32', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 51, '2026-01-19 22:11:00'),
(1681, NULL, '8752mq3357if7b11laansogqo7', '198.244.240.146', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 8, '2026-01-19 23:38:00'),
(1682, NULL, '8vp2mivjhsduh7moopitagen6a', '198.244.168.66', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 19, '2026-01-19 23:45:00'),
(1684, NULL, '8870gtitnhrffcr37rh2spfc5m', '198.244.240.173', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 27, '2026-01-20 00:32:00'),
(1688, NULL, 'vma9239kckn1p41cb34jm46h91', '54.38.147.4', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 52, '2026-01-20 01:48:00'),
(1689, NULL, 'oara55sof3u13vop99tmsu0upt', '198.244.226.237', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-01-20 02:02:00'),
(1691, NULL, '39cvqsljh4jsunb1etpf7lla8u', '198.244.240.165', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-01-20 02:37:00'),
(1692, NULL, 'qmk9nqns6llruuk59nl3d708fo', '15.235.96.230', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-01-20 03:41:00'),
(1698, NULL, '71c9f3ishovfapien11pqa7o3m', '198.244.240.18', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-01-20 05:20:00'),
(1700, NULL, 'a3d5askk8j9k8g14i1g99qo8js', '142.44.233.29', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-01-20 06:29:00'),
(1704, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:6922:2e00:81c4:228b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2026-01-20 23:55:00'),
(1705, NULL, 'lb3ohtc8abcebiet3v3c2lgm6j', '216.144.93.142', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.6.1 Safari/605.1.15', 24, '2026-01-21 00:03:00'),
(1714, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:6922:2e00:81c4:228b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2026-01-21 00:17:00'),
(1715, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:6922:2e00:81c4:228b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-21 00:17:00'),
(1716, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:6922:2e00:81c4:228b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2026-01-21 00:17:00'),
(1718, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:6922:2e00:81c4:228b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-21 00:18:00'),
(1719, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:6922:2e00:81c4:228b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 51, '2026-01-21 00:18:00'),
(1720, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:6922:2e00:81c4:228b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 26, '2026-01-21 00:18:00'),
(1721, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:6922:2e00:81c4:228b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 25, '2026-01-21 00:19:00'),
(1724, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:6922:2e00:81c4:228b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 52, '2026-01-21 00:22:00'),
(1725, NULL, 'hbteniovmud7fir2d3i6mbf2fn', '66.249.73.69', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.7499.192 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 24, '2026-01-21 00:41:00'),
(1727, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:6922:2e00:81c4:228b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2026-01-21 02:31:00'),
(1728, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:6922:2e00:81c4:228b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2026-01-21 02:42:00'),
(1730, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:6922:2e00:81c4:228b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2026-01-21 02:44:00'),
(1731, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:6922:2e00:81c4:228b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2026-01-21 03:34:00'),
(1732, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:1d26:e65a:2628:c7a5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-21 05:04:00'),
(1733, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:1d26:e65a:2628:c7a5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2026-01-21 05:10:00'),
(1734, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:1d26:e65a:2628:c7a5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2026-01-21 05:12:00'),
(1735, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '2001:4451:13f7:1d00:1d26:e65a:2628:c7a5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-21 05:12:00'),
(1736, NULL, '1bvg76fe3p9fir9hq8g5qhrv31', '57.155.170.166', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.7390.0 Safari/537.36', 24, '2026-01-21 10:46:00'),
(1737, NULL, 'uc2r839l51juvmlmv3r8a7h56h', '57.155.170.166', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.7390.0 Safari/537.36', 24, '2026-01-21 10:46:00'),
(1738, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '112.209.78.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-21 23:42:00'),
(1739, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '112.209.78.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2026-01-21 23:42:00'),
(1742, NULL, 'g3krollha9cdu2trevvcda5k58', '112.209.78.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2026-01-22 03:27:00'),
(1743, NULL, 'g3krollha9cdu2trevvcda5k58', '112.209.78.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 29, '2026-01-22 03:36:00'),
(1745, NULL, 'ibb5gkkldsbheqh9d90javj0vi', '2001:4860:7:80c::d1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-01-22 04:18:00'),
(1746, NULL, '490f0j8spg87h99l1hv8759mlp', '2001:4860:7:80c::ed', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-22 05:24:00'),
(1747, NULL, '37athcp2aabmf5lc30f7rphlaf', '198.244.168.105', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 19, '2026-01-22 06:31:00'),
(1757, NULL, '92r3rd271rh2ttjg0qnppv0tt7', '51.68.247.193', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 26, '2026-01-22 13:17:00'),
(1759, NULL, 'c22oshi14n04kk0tfr0bu8butr', '51.195.183.128', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 52, '2026-01-22 14:28:00'),
(1760, NULL, '91lsqd8ke3nb29c21fbrt6lqc5', '198.244.242.70', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 52, '2026-01-22 14:31:00'),
(1761, NULL, 'rn78uuqe2gi35h0j86jds7uf87', '54.38.147.205', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 51, '2026-01-22 14:41:00'),
(1762, NULL, 'rhgvr8k7oaoi0jl2fpme7qdasn', '198.244.226.213', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 51, '2026-01-22 14:45:00'),
(1763, NULL, 'uquhfh1csi7v07srvfi6p4a12d', '198.244.226.118', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 27, '2026-01-22 14:53:00'),
(1767, 17, 'bi3tp5rhr6jtmr1gi7jnhun7k8', '112.209.78.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-22 23:18:00'),
(1768, NULL, 'd7rn9n12jpp9qut9bkqf01d53p', '112.209.78.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 Edg/129.0.0.0', 24, '2026-01-22 23:18:00'),
(1772, 17, 'bi3tp5rhr6jtmr1gi7jnhun7k8', '112.209.78.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2026-01-23 01:43:00'),
(1773, 17, 'bi3tp5rhr6jtmr1gi7jnhun7k8', '112.209.78.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2026-01-23 01:44:00'),
(1775, 17, 'bi3tp5rhr6jtmr1gi7jnhun7k8', '112.209.78.95', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 28, '2026-01-23 05:15:00'),
(1776, NULL, 'lfio41o2l9a48f22tqnttjv25h', '2001:4860:7:80c::d1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-23 07:59:00'),
(1777, NULL, 'nqnt6khsrhf0ngn84ogs4jldlh', '2001:4860:7:506::df', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-23 07:59:00'),
(1778, NULL, 'j5rque1lfq3pgv5r629tuc8vea', '2001:4860:7:40c::dd', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-23 08:00:00'),
(1779, NULL, 'pjkm522v37tv8sfntg6036eul4', '2001:4860:7:40c::e8', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-01-23 08:01:00'),
(1781, NULL, '4gen3eo1795ilsefvgg3emjbq6', '2001:4860:7:c0c::fd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-24 00:59:00'),
(1792, NULL, 'i50mgu9kjdvu2i6j7dak4su407', '2001:4860:7:80c::e6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-01-25 06:49:00'),
(1806, NULL, 'hbnakttq8ungm35f6ho0ss72fe', '122.2.76.239', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1', 24, '2026-01-26 02:37:00'),
(1809, NULL, '8haep8c7bevm5uo2hlju0nqkqm', '112.209.179.39', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 23, '2026-01-26 03:31:00'),
(1811, NULL, '2kdhudfispau3omce1l68bhc20', '51.89.129.180', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 25, '2026-01-26 09:41:00'),
(1812, NULL, 'gp0k34jmjccjbpri7u2r4m8tng', '2001:4860:7:40c::fc', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-01-26 10:14:00'),
(1813, NULL, '8rrb8e18fvtpevrk670hiplq7i', '2001:4860:7:80c::ec', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-01-26 11:11:00'),
(1815, NULL, '3grimfdntn2tn7lbs79e1dmnh0', '2001:4860:7:c0c::eb', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-26 15:21:00'),
(1819, NULL, 'r5uovpj8c9f7fnsudcb6ojajsi', '51.195.244.95', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 51, '2026-01-26 19:10:00'),
(1821, NULL, 'tlkev4f8g67dg6ml1v51aisvfr', '51.89.129.224', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 27, '2026-01-26 19:48:00'),
(1822, NULL, 'p91jr1dcvjc019hd8rlme257hl', '51.195.244.162', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 19, '2026-01-26 20:04:00'),
(1825, NULL, 'af55c68u0v7q6tr117973a0gec', '2001:4860:7:50c::f6', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-01-26 22:18:00'),
(1826, NULL, 'afggf0g35j8oaub55fte03jdc8', '51.195.244.70', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-01-26 22:22:00'),
(1827, NULL, 'u5sipi39qhj42fal9om95791r8', '198.244.226.244', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-01-26 22:48:00'),
(1828, NULL, '0llcgsarhv6871ofk4jdv36vm5', '5.39.1.243', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-01-26 23:11:00'),
(1830, NULL, '0v8ga4t4v548np11e7f9lu51cj', '176.31.139.3', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-01-27 01:29:00'),
(1831, NULL, '0bbklf0vdnn5tifbp8trtoqo2u', '5.39.1.250', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2026-01-27 01:35:00'),
(1836, NULL, 'sbpdcs46dmasj4ad4q6biueldn', '135.232.20.19', 'Mozilla/5.0 (Linux; Android 9; itel A27) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.6943.143 Mobile Safari/537.36', 24, '2026-01-27 03:07:00'),
(1839, NULL, '29uag54siltouomle7o46ll9to', '2001:4860:7:80c::de', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-01-27 08:37:00'),
(1840, NULL, 'eb1dudvpuk7ol89f8fn329djus', '54.37.118.71', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-01-27 10:03:00'),
(1841, NULL, 'qqek1mn237ocsapa43km8mdjdj', '94.23.188.221', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-01-27 10:27:00'),
(1844, NULL, 'gipdleg6cs3ekjqa3tk93n2dvp', '54.38.147.216', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-01-27 16:26:00'),
(1854, NULL, 'h1sd2fm0j7eue80hhk1nmlm2ls', '2001:4860:7:40c::f8', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-01-28 00:21:00'),
(1855, NULL, '5l2fneqe6j086veij4fttns6up', '112.209.178.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-01-28 00:24:00'),
(1858, 37, 'b2mdu68ui8bbn9uhj3rd3u99h6', '112.209.178.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-01-28 01:00:00'),
(1859, NULL, 're02oedbeja4nrqii2lg44qgfp', '112.209.178.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 23, '2026-01-28 01:41:00'),
(1860, NULL, 're02oedbeja4nrqii2lg44qgfp', '112.209.178.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 52, '2026-01-28 01:42:00'),
(1862, NULL, 'o1ctqu6r5am3at09cc3rsb3ngk', '2001:4860:7:40c::fb', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-28 03:45:00'),
(1863, NULL, '320e4i3c32pv4hr809ad24m6ss', '2001:4860:7:80c::fe', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-01-28 03:47:00'),
(1864, NULL, 'k3mkls65j7t0s540abm8jgu0o6', '2001:4860:7:80c::cd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-28 06:54:00'),
(1866, NULL, '9ju0v76hu6t52akp7130vlaptc', '2001:4860:7:80c::fe', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-28 10:25:00'),
(1867, NULL, 'iiae3oa50ttrdtj585dj223l3m', '2001:4860:7:80c::e5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-01-28 10:25:00'),
(1869, NULL, 'ah71juu84o7nbph1smh2jgg49c', '5.39.1.226', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 52, '2026-01-28 21:43:00'),
(1871, NULL, 'gntd54hdk47nu11e7o09m39u2n', '94.23.188.219', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 27, '2026-01-28 23:06:00'),
(1874, NULL, '076hapqfhbo5rstcm4135cmvto', '2001:4860:7:50c::fe', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-01-29 00:21:00'),
(1875, NULL, '076hapqfhbo5rstcm4135cmvto', '112.200.198.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-01-29 00:24:00'),
(1876, 17, 'romgdbrs2t0nbbnh8lmr5aln5u', '112.209.70.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-01-29 00:53:00'),
(1877, 17, 'romgdbrs2t0nbbnh8lmr5aln5u', '112.209.70.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 23, '2026-01-29 00:54:00'),
(1883, NULL, 'scffijuf9t2u5k66oi3q2tdc4r', '2001:4860:7:80c::e5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 24, '2026-01-29 06:48:00'),
(1884, NULL, '3a8sg6l2g5d81dmjmduc2gm17o', '66.249.73.160', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.7499.192 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 24, '2026-01-29 07:58:00'),
(1891, NULL, 'jlodu9mf704ickufvaanmcdjvn', '112.209.70.113', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-01-30 00:48:00'),
(1892, NULL, 'jlodu9mf704ickufvaanmcdjvn', '112.209.70.113', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 23, '2026-01-30 00:48:00'),
(1893, NULL, 'mlkuab2g8q2q6jp4te5pg3d3gm', '2001:4860:7:c0c::f0', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-01-30 03:07:00'),
(1894, NULL, '3jpkicb1ij50d564luelfr3utt', '2001:4860:7:50c::ff', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-01-30 06:56:00'),
(1895, NULL, 't4i2eu1mof4qsg4r4vd0bbmi7g', '2001:4860:7:40c::f9', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-01-30 07:46:00'),
(1896, NULL, 'bgcffdk8ijgk9v10hcj2cvittl', '2001:4860:7:40c::e1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-01-30 10:18:00'),
(1914, NULL, 'tci9thbncjlkqvj7fc3009ortb', '2001:4860:7:c0c::eb', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-01-31 01:37:00'),
(1918, NULL, 'nh90b44avav30ckmse6078ffec', '92.222.104.196', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-01-31 15:48:00'),
(1919, NULL, 'h5jt614ighugtk26f47tvs17gb', '176.31.139.28', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-01-31 15:51:00'),
(1938, NULL, 'maqajaeiob78b3fne9k2o6f4dl', '51.89.129.117', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 51, '2026-02-01 00:56:00'),
(1942, NULL, '2v4den7k4qsgss226bh28h8ttv', '51.195.183.7', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 27, '2026-02-01 01:47:00'),
(1943, NULL, 'tqeujhvf1pj851mo0u4m1p5hbt', '51.195.215.89', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 52, '2026-02-01 03:49:00'),
(1944, NULL, '1sun4p5penh64l2lojbmg2s2ku', '51.195.183.8', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 19, '2026-02-01 04:00:00'),
(1945, NULL, 'qcmaisb58laj9263ojs9jmu0al', '51.75.236.157', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-02-01 04:43:00'),
(1946, NULL, 'k2p01cdefnra051mp0a8tgcgac', '51.195.183.103', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 8, '2026-02-01 04:57:00'),
(1949, NULL, 'teoqsbp5estcgldiuipfes0met', '51.195.183.152', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-02-01 06:49:00'),
(1952, NULL, 'elr5fpu55kj440p7o5qam5fcij', '198.244.242.244', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-02-01 07:29:00'),
(1956, NULL, 'ulj3d6vc804milujmn53f4v944', '5.39.109.165', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-02-01 09:06:00');
INSERT INTO `recent_views` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `product_id`, `viewed_at`) VALUES
(1959, NULL, 'lkel8oago3ktjnetevhpn4g9rl', '198.244.240.20', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2026-02-01 11:14:00'),
(1960, NULL, 'mjqiv8371o2t7qrhbv67n3u90q', '198.244.226.96', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2026-02-01 11:25:00'),
(1961, NULL, 'tdsuvru6jvt6s24h8m94fl1puj', '51.195.183.207', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-02-01 11:56:00'),
(1973, NULL, '3bnadef9kkjrl4ab82fkpgcaqh', '198.244.168.172', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 26, '2026-02-01 17:16:00'),
(1980, NULL, 'ja7754unhoa611no5vtks57cfd', '2001:4860:7:80c::d5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-01 23:07:00'),
(1981, NULL, '1ab6qdo72qenbrmdiiu2sog4sb', '2001:4860:7:c0c::f8', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-01 23:43:00'),
(1984, NULL, 'lu3ngs2c1st34qhn7ll8jl3p2u', '94.23.188.214', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2026-02-02 01:50:00'),
(1989, NULL, '32cn6g1i1huok4efrtt3ausui0', '2001:4860:7:80c::ff', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-02 06:06:00'),
(1995, NULL, 'd83h4d5p9q7lo6bdbk6e20snts', '2001:4860:7:80c::e5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Mobile Safari/537.36', 24, '2026-02-02 08:26:00'),
(2004, NULL, '26f5suedrcbjk8t81j403ll5tp', '37.59.204.138', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-02-02 13:08:00'),
(2014, NULL, 'dvu2nsl1csehhp03r3qaekjs53', '135.232.20.17', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 15_7_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 24, '2026-02-03 00:36:00'),
(2016, NULL, 'bc0rurshoskgcmf88uu3jbbfjq', '2001:4860:7:50c::fb', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-03 01:16:00'),
(2017, NULL, 'qmttecabvubi074be799ttbdk5', '176.31.139.13', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-02-03 02:57:00'),
(2018, NULL, '5etg7nj404d3a814p25ahkblhr', '9.169.121.184', 'Mozilla/5.0 (Linux; Android 15; 24117RK2CC Build/AQ3A.240829.003) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.7390.124 Mobile Safari/537.36', 24, '2026-02-03 03:18:00'),
(2019, NULL, 'jo2uallqqi47v28f75gp55u4ua', '9.169.121.184', 'Mozilla/5.0 (Linux; Android 15; 24117RK2CC Build/AQ3A.240829.003) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.7390.124 Mobile Safari/537.36', 24, '2026-02-03 03:41:00'),
(2021, NULL, 'jrm88o2rms4oq1av2e09k2fv9m', '9.169.121.185', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.7444.163 Safari/537.36', 24, '2026-02-03 06:16:00'),
(2023, NULL, '228fk8u80ftf6vker505rje2a0', '2001:4860:7:80c::fe', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-03 06:53:00'),
(2024, NULL, 'r4njadctqbjqn6keiarckvi92n', '51.68.247.199', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-02-03 08:00:00'),
(2026, NULL, 'o1lm53oeku3hsq0rgb4co8spci', '51.68.247.217', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-02-03 13:58:00'),
(2027, NULL, '1he6lmim8knvet3cklmvu5qlq2', '2001:4860:7:f0e::f8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-03 15:12:00'),
(2030, NULL, 'i7pj7avr9160nisi79p5qco58h', '94.23.188.199', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 52, '2026-02-03 19:48:00'),
(2035, NULL, '57hbqb4cp0vf2rf0k1rbl1to5e', '49.150.76.55', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-03 23:12:00'),
(2036, NULL, 'aijs88qmhiqtgfn0qvjllt369m', '2001:4860:7:50c::f5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-03 23:17:00'),
(2037, NULL, '6cgg8ber4ii0p8ng6eepti1iar', '112.209.71.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-04 00:38:00'),
(2038, NULL, 'd3f6o295vrs26kadkns48if40n', '92.222.108.111', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 51, '2026-02-04 00:41:00'),
(2039, NULL, '3cti3mtc0f6fmohnm25bmsnu1e', '92.222.104.209', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 27, '2026-02-04 01:06:00'),
(2047, NULL, 'm44p3ihbsv88ae2bpidoglj6ct', '2001:4860:7:80c::d5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-04 04:16:00'),
(2055, 17, '3pe7rh347uvin6pba27d2igb0n', '112.209.71.92', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 23, '2026-02-04 07:46:00'),
(2058, NULL, 'rcjmaknu12f6at6r6ss4u4fdaq', '92.222.104.215', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 19, '2026-02-04 13:42:00'),
(2067, NULL, 'f2673lr8fi5q5bq09ovtghf4nf', '2001:4860:7:50c::ff', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-04 23:23:00'),
(2068, NULL, 'eij1o5787ru111knk9n2s2g43t', '2001:4860:7:40c::ea', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-05 01:38:00'),
(2069, NULL, '74307b10t6qs5v8oelmeuntcjn', '2001:4453:52b:fd00:3b2d:e79a:6587:221f', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-05 02:43:00'),
(2070, NULL, '608d0ot38578rha15s2boovhbh', '2001:4860:7:50c::f9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-05 03:42:00'),
(2072, NULL, 'aoomg4crofldu0c453udoqq257', '2405:8d40:4ccc:91ae:e827:f2ff:fea6:f5ac', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-05 04:41:00'),
(2073, NULL, '3j7dnpc3jaje0dki7j1kn15lnn', '2001:4860:7:50c::fd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-05 05:07:00'),
(2074, NULL, 'a2rc5e0iknhes0bvti1r5b58bq', '2001:4860:7:80c::cd', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-05 05:30:00'),
(2075, NULL, '6pftbthdv3pitsvs3k0fffgfbf', '2001:4860:7:50c::fc', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-05 06:09:00'),
(2076, NULL, 'o6g2o9fjbpn0al1ej9qbqcvnt8', '2001:4860:7:80c::f9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-05 06:40:00'),
(2077, 17, 'sicplqpjcd964s2ru4vpf0gtfk', '112.209.68.145', 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 52, '2026-02-05 06:46:00'),
(2078, 17, 'sicplqpjcd964s2ru4vpf0gtfk', '112.209.68.145', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 52, '2026-02-05 08:39:00'),
(2079, NULL, 'i0a1e6f20ftii0f6p4ebh0jprs', '2001:4860:7:80c::ec', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-05 08:53:00'),
(2080, NULL, '3bgpbk1hc6oebnto1m0sffv2i9', '2001:4860:7:50c::f9', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-05 09:50:00'),
(2081, NULL, 'd21550qgqt1r7tqnh8qosh56qf', '2001:4860:7:40c::ea', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-05 09:50:00'),
(2082, NULL, '9fie3attis6hj34nu4kuuhfc86', '2001:4860:7:80c::cf', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-05 10:12:00'),
(2083, NULL, '555hkff4cr9s6u7qqrv3gtlapd', '2001:4860:7:50d::fc', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-05 10:23:00'),
(2091, NULL, 'ngkmvu723tgrhtjhv7k76ajqp5', '51.75.236.131', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-02-05 21:35:00'),
(2093, NULL, '8uiiabdp1h1pu9kfte6tsk2tsr', '92.222.108.123', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 25, '2026-02-05 23:03:00'),
(2097, NULL, 't1h4e5ndcqbvltlns2u9i5pecr', '2001:4860:7:40c::ed', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-06 00:51:00'),
(2099, NULL, 's5tl5fes33h5nh88ghg994d0c8', '2001:4860:7:80c::ff', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-06 01:02:00'),
(2100, NULL, 'l8bq58vqgkdjmbehl8v6ebg1gv', '2001:4860:7:80c::ea', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-06 02:06:00'),
(2103, NULL, 'u4lbitn7h4lt5n7s6d9q9d5bav', '120.28.137.219', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 26, '2026-02-06 06:09:00'),
(2104, NULL, 't0p3f2cr4a7d99j24at95fvh31', '2001:4860:7:50c::fa', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-06 06:12:00'),
(2105, NULL, 'vp74p69gnsfqft1lqe1grv524d', '2001:4860:7:80c::d9', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36', 24, '2026-02-06 07:49:00'),
(2106, NULL, '7cfldjso6gnros2mn2b7l5k3r4', '103.134.2.121', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 25, '2026-02-06 08:22:00'),
(2107, NULL, 'a3ir62drpnkhkqlmvm8ch6i1mn', '2001:4860:7:80c::e2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-06 08:45:00'),
(2108, NULL, '7cfldjso6gnros2mn2b7l5k3r4', '2402:e000:4a0:5045:8135:ec7a:2fd3:dae', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 23, '2026-02-06 08:54:00'),
(2109, NULL, '9gqao9bf6kju5rtg0rp92qi136', '66.249.73.70', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.7559.132 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 24, '2026-02-06 09:16:00'),
(2111, NULL, 'fgb6stmjupds1jnt0j7mvnv2bi', '158.62.19.82', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-06 19:03:00'),
(2112, NULL, 'fgb6stmjupds1jnt0j7mvnv2bi', '158.62.19.82', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 23, '2026-02-06 19:04:00'),
(2113, 17, 'rs9jiqb8uo00fvfkjhtqpj8ap2', '2001:4451:13d7:8d00:c993:80ba:ce27:9148', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 28, '2026-02-07 00:39:00'),
(2114, NULL, 'qoeadfsq8makat00e7562lkr7g', '2001:4860:7:50c::fa', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-07 02:25:00'),
(2115, NULL, 'bov8rl3dlmki2medujtu6lpujn', '110.54.150.240', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36', 24, '2026-02-07 04:01:00'),
(2116, NULL, 'igk76eaecbn54ufsgjg1el10fp', '14.1.64.88', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_2_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/144.0.7559.95 Mobile/15E148 Safari/604.1', 24, '2026-02-07 06:36:00'),
(2117, NULL, '73p49gkjskk19r4e380h0po5l6', '2001:4860:7:80c::e2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-07 23:42:00'),
(2118, NULL, '037f0mlb9dlmfcbsmbbo23shri', '2001:4860:7:50c::f7', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-07 23:56:00'),
(2119, NULL, 'a1b2ij5pvbn9ki20erhb7lrffb', '2001:4860:7:1628::ff', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-08 17:41:00'),
(2120, NULL, 'm82am6qst1hseq77cgcrb2h0fn', '86.175.159.53', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36', 29, '2026-02-08 18:17:00'),
(2121, NULL, '9bq7i8lsj7k11ph1f632l7cg16', '120.29.90.155', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 23, '2026-02-08 19:08:00'),
(2122, NULL, '9bq7i8lsj7k11ph1f632l7cg16', '120.29.90.155', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 23, '2026-02-08 19:09:00'),
(2126, NULL, 'j5e5qp7mg6lkiaol6mogftummk', '2001:4860:7:50e::fe', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-09 00:20:00'),
(2127, NULL, 'oj2in8u41v66obidpmkg0et0jk', '2001:4860:7:50c::f7', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-09 00:21:00'),
(2128, NULL, 'mo44ohul830fbem8kb6rrnv396', '9.169.121.185', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 24, '2026-02-09 00:21:00'),
(2130, NULL, 'em03lgr5pdq31cvins1hflbd3r', '2001:4860:7:50c::fd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-09 01:48:00'),
(2131, NULL, 'veiiisrn00slrpehtu0rllsim1', '2001:4860:7:50c::fa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-09 02:16:00'),
(2133, NULL, 'j284ulf99mh3hd87t263im9tus', '2001:4860:7:50c::ea', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-09 02:46:00'),
(2134, NULL, 's4ip6rlrtdo7jkot3kbk2ruori', '2001:4860:7:50c::fb', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-09 02:48:00'),
(2135, NULL, '1b2go6gj22e138mtaqmtf6pna2', '2001:4860:7:50c::ff', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-09 03:54:00'),
(2136, NULL, '2sluvbm9rik7c2rcba2q3l1nbl', '2001:4860:7:50c::fb', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-09 04:21:00'),
(2138, NULL, '3e5o42i377qoenv43shq5ob6gv', '2001:4860:7:50c::fa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-09 05:50:00'),
(2139, NULL, 'embms4r4pfef4c5q152ffe8ss8', '175.176.26.93', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-09 06:29:00'),
(2141, NULL, 'dcubb67gjbupf61hj4l1poghav', '2001:4860:7:80c::ed', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-09 06:54:00'),
(2143, NULL, 'o84jhu0nvtg4re1qtcf5264ub7', '2001:4451:13d7:8d00:b6c5:3f38:8a84:beed', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-09 08:43:00'),
(2144, 17, 'o84jhu0nvtg4re1qtcf5264ub7', '2001:4451:13d7:8d00:b6c5:3f38:8a84:beed', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-09 08:44:00'),
(2145, 17, 'o84jhu0nvtg4re1qtcf5264ub7', '2001:4451:13d7:8d00:b6c5:3f38:8a84:beed', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 23, '2026-02-09 08:44:00'),
(2150, NULL, 'vtraujt2uk8h0ndqffvdbt82vf', '72.152.84.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.7444.162 Safari/537.36', 24, '2026-02-09 23:28:00'),
(2151, NULL, 'p1ov95r4spqf4ro9qtn5vfueuu', '72.152.84.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.7444.162 Safari/537.36', 24, '2026-02-09 23:28:00'),
(2152, NULL, 'hf03336e7bojna5e5cvpaf588t', '43.153.20.180', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36', 26, '2026-02-10 00:43:00'),
(2155, NULL, 'qbt29od364qk2l1ptml5aljf3b', '112.209.183.247', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 23, '2026-02-10 03:26:00'),
(2158, NULL, '3si3k425e986uh1btusc9jpuj6', '2001:4860:7:80c::ee', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-10 07:36:00'),
(2163, NULL, '9cif2ssf078bq1u10vggd7tdr4', '121.37.89.237', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36', 52, '2026-02-10 14:54:00'),
(2166, NULL, '355aaafkovni9ast9ld1jb3lsk', '2001:4860:7:50c::ff', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-11 02:00:00'),
(2167, NULL, '3pobm9kb0uel5op56gm2kfpdrl', '2001:4860:7:50c::fd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-11 02:04:00'),
(2168, NULL, 'ntficfd1ugtt9h90ov34a2f4tm', '112.209.66.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 77, '2026-02-11 06:23:00'),
(2169, NULL, 'a2j9nnmg0g7ppldu09ofkn9d4k', '2001:4860:7:40c::ee', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-11 06:32:00'),
(2170, NULL, 'e5ksahvjjpj2oigh9tm6b0m88u', '112.209.66.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 77, '2026-02-11 07:53:00'),
(2171, 17, 'e5ksahvjjpj2oigh9tm6b0m88u', '112.209.66.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 77, '2026-02-11 07:54:00'),
(2173, 17, 'e5ksahvjjpj2oigh9tm6b0m88u', '112.209.66.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 77, '2026-02-11 08:00:00'),
(2178, NULL, 'se156mbgq88mgjuhea6n46mvl4', '2001:4860:7:40c::f8', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-11 10:48:00'),
(2180, NULL, 'jb0hetct1qthm2lbvpn2c74t55', '2001:4860:7:80c::ff', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-11 11:11:00'),
(2181, NULL, 'ibimckdvb891h6flk31c1ghtc9', '2001:4860:7:40c::fc', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-11 13:26:00'),
(2182, NULL, 'uf41cvod6iio5j9o28c1gkjgbt', '2001:4860:7:304::fc', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-11 15:46:00'),
(2183, NULL, 'njacbt0mpj9s10s81nj6jjvhqf', '2607:fea8:6c42:8c00:26fb:d425:fef0:640d', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-11 15:47:00'),
(2185, NULL, 'e52maffnm5r02f9jl46n3s8orc', '51.222.168.165', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-02-11 21:16:00'),
(2189, NULL, 'l7fivleneq89qba59qjg55gtdh', '2001:4860:7:80c::ff', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-12 01:45:00'),
(2190, NULL, 'hinsqhoqhjd1344iqeujed81ii', '2001:4860:7:80c::e8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-12 03:22:00'),
(2191, NULL, '4urmtieepr96kav360jmc0r8ka', '15.235.98.11', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 51, '2026-02-12 04:23:00'),
(2192, NULL, '8slukuvbn0dsgl9n9dgc9amk5b', '54.39.203.220', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 27, '2026-02-12 05:00:00'),
(2193, NULL, '93m01k35gvurci62drab03f5oe', '45.163.216.233', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:136.0) Gecko/20100101 Firefox/136.0', 26, '2026-02-12 07:45:00'),
(2195, NULL, '6q7q0dj3h0re120nop8r5lcvqg', '148.113.128.27', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 8, '2026-02-12 11:32:00'),
(2197, NULL, 'kc9ema141clr6p3efoieasa37t', '142.44.220.11', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-02-12 12:09:00'),
(2198, NULL, '0t98vf7hfd1l8pk4ul0gcl1d68', '15.235.98.214', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2026-02-12 12:19:00'),
(2202, NULL, 'at4bu9c599snu32bnjg22s8kg7', '2001:4451:47d0:cb00:7839:749b:6cab:54e9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-12 18:53:00'),
(2208, 17, 'dbc5ejdkfh0acob5i80nsjg3lo', '2001:4451:13c6:5100:b990:9251:721c:75a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 83, '2026-02-13 00:33:00'),
(2209, 17, 'dbc5ejdkfh0acob5i80nsjg3lo', '2001:4451:13c6:5100:b990:9251:721c:75a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 83, '2026-02-13 00:34:00'),
(2210, NULL, 'eqdl9utvsj9old3s5eeis2au1u', '2001:4860:7:805::fa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-13 02:41:00'),
(2211, 17, '7io6lpq6j9mk8p25vr30imsf87', '2001:4451:13c6:5100:b990:9251:721c:75a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 81, '2026-02-13 03:25:00'),
(2212, NULL, '3n0dusrsfc66a99uo29eifpi4m', '2001:4451:13c6:5100:d08:6585:437b:26f', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 81, '2026-02-13 03:30:00'),
(2215, 17, '7io6lpq6j9mk8p25vr30imsf87', '2001:4451:13c6:5100:b990:9251:721c:75a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 28, '2026-02-13 05:34:00'),
(2217, NULL, '3n0dusrsfc66a99uo29eifpi4m', '2001:4451:13c6:5100:d08:6585:437b:26f', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 81, '2026-02-13 06:24:00'),
(2218, NULL, '3n0dusrsfc66a99uo29eifpi4m', '2001:4451:13c6:5100:d08:6585:437b:26f', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 81, '2026-02-13 06:25:00'),
(2219, NULL, '3n0dusrsfc66a99uo29eifpi4m', '2001:4451:13c6:5100:d08:6585:437b:26f', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 81, '2026-02-13 06:31:00'),
(2225, 17, '7io6lpq6j9mk8p25vr30imsf87', '2001:4451:13c6:5100:b990:9251:721c:75a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 23, '2026-02-13 07:51:00'),
(2226, 17, '7io6lpq6j9mk8p25vr30imsf87', '2001:4451:13c6:5100:b990:9251:721c:75a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 85, '2026-02-13 07:54:00'),
(2227, NULL, '3n0dusrsfc66a99uo29eifpi4m', '2001:4451:13c6:5100:8c6d:3b1d:f17d:9d45', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 81, '2026-02-13 07:54:00'),
(2228, 17, '7io6lpq6j9mk8p25vr30imsf87', '2001:4451:13c6:5100:b990:9251:721c:75a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 77, '2026-02-13 07:55:00'),
(2229, 17, '7io6lpq6j9mk8p25vr30imsf87', '2001:4451:13c6:5100:b990:9251:721c:75a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 85, '2026-02-13 07:56:00'),
(2230, 17, '7io6lpq6j9mk8p25vr30imsf87', '2001:4451:13c6:5100:b990:9251:721c:75a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 23, '2026-02-13 07:59:00'),
(2231, 17, '7io6lpq6j9mk8p25vr30imsf87', '2001:4451:13c6:5100:b990:9251:721c:75a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 23, '2026-02-13 08:01:00'),
(2232, 17, '7io6lpq6j9mk8p25vr30imsf87', '2001:4451:13c6:5100:b990:9251:721c:75a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 23, '2026-02-13 08:02:00'),
(2233, 17, '7io6lpq6j9mk8p25vr30imsf87', '2001:4451:13c6:5100:b990:9251:721c:75a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 23, '2026-02-13 08:06:00'),
(2234, 17, '7io6lpq6j9mk8p25vr30imsf87', '2001:4451:13c6:5100:b990:9251:721c:75a7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 23, '2026-02-13 08:07:00'),
(2242, NULL, 'fv50phbs0g3j5k7hdas9b2i60k', '142.44.220.90', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-02-13 13:53:00'),
(2243, NULL, 'oppgb75g9ep7d2bnamlocgemmk', '2001:4860:7:812::e', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 24, '2026-02-13 15:29:00'),
(2244, NULL, 'v500mldgfrug4mnu3i9j3hdo8q', '2001:4860:7:80c::ec', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 24, '2026-02-13 15:30:00'),
(2249, NULL, '5a9n4m04vdej3oi3er7v7hulb2', '112.209.72.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 81, '2026-02-14 00:12:00'),
(2250, NULL, 'rddgmk39k1danngheh22bpbevt', '112.209.72.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 81, '2026-02-14 00:30:00'),
(2251, NULL, '72i69p3u5s9ojpc55gvdlunmjp', '2001:4860:7:80c::ec', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-14 00:49:00'),
(2252, NULL, 'nfefoako620s5udsepgs1h3k0r', '2001:4860:7:50c::f1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-14 00:49:00'),
(2253, NULL, '0hroaaqi9iga0atqvumrd2icer', '136.158.62.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 77, '2026-02-14 01:18:00'),
(2256, NULL, '1d73s6h9svg6gfktadlovi8lje', '66.249.73.71', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.7559.132 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 24, '2026-02-14 03:27:00'),
(2257, NULL, 'a8d0cgda4lnge3hm2rrbem1ko3', '2001:4860:7:80c::cf', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-14 04:26:00'),
(2259, NULL, 'bpvd17aaci3ar6h3pjg3u22d52', '47.82.11.143', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36', 25, '2026-02-14 05:34:00'),
(2261, NULL, '6negqqp3ahjopldascg3q85pop', '58.69.249.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-14 07:01:00'),
(2265, NULL, 'j7c28nbhujt1qikdo046opl9ld', '2001:4860:7:50c::ff', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-14 10:22:00'),
(2266, NULL, 'g23n4rtclbdokskhu2mghdgfos', '2001:4860:7:80c::e9', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-14 14:35:00'),
(2275, NULL, 'gc56a4du9loa395dk38p1f9t6f', '2001:4860:7:50c::f', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-15 02:10:00'),
(2277, NULL, 'h1bu6sfr6j2pd47cl0bqos80o0', '2001:4860:7:80c::d9', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-15 04:37:00'),
(2283, NULL, '9q6u9ltmfmhllkf4bkelscbpst', '176.110.103.89', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 Edg/135.0.0.0', 27, '2026-02-15 09:57:00'),
(2289, NULL, 'e4h3rorlnu3sgg17k7rfilc8rp', '51.182.4.205', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 Edg/135.0.0.0', 51, '2026-02-15 16:52:00'),
(2290, NULL, '3kjakl4i3fbkf2pr9kprksrcv3', '198.244.183.16', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 52, '2026-02-15 17:04:00'),
(2292, NULL, '9ppti0nr3tibp7n80ikmrlktoe', '198.244.240.80', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 52, '2026-02-15 17:08:00'),
(2293, NULL, 'aebp24jugtlf987tvo9mbj289r', '51.89.129.83', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 26, '2026-02-15 17:23:00'),
(2295, NULL, '75uk1448v3buissbd9i3ep6nlk', '198.244.183.165', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 26, '2026-02-15 17:28:00'),
(2296, NULL, '18pfslk86d4svjj6v78f62qml7', '54.38.147.88', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 25, '2026-02-15 17:29:00'),
(2297, NULL, 's03vocgqimefubqf1ij2m8tvjh', '47.128.109.35', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 24, '2026-02-15 18:40:00'),
(2298, NULL, '2gev5hgn65kk9l050u4c8s8qtv', '2001:4860:7:50c::fd', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 24, '2026-02-15 19:26:00'),
(2299, NULL, 'jiokb13jhqai4sq95nd8b1f56e', '2001:4860:7:50c::f8', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 24, '2026-02-15 19:27:00'),
(2300, NULL, 'ktqhnrnn4rv3ifqidrjt3urphr', '47.128.116.223', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 27, '2026-02-15 22:41:00'),
(2301, NULL, '0gc3b86l65qalnggcd1tmd5lvj', '124.104.82.183', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 81, '2026-02-15 23:05:00'),
(2302, NULL, 'uc0iklv1dc045j9eu5s17ej21q', '51.195.215.146', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 81, '2026-02-15 23:06:00'),
(2303, NULL, '0gc3b86l65qalnggcd1tmd5lvj', '124.104.82.183', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 81, '2026-02-15 23:20:00'),
(2304, NULL, '0gc3b86l65qalnggcd1tmd5lvj', '124.104.82.183', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 86, '2026-02-15 23:20:00'),
(2305, NULL, 'ng5g7j61gghhi0vnk29jsnjgqo', '147.161.147.84', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 29, '2026-02-16 00:12:00'),
(2306, 17, 't517q3bd838fmubh7d98tkfb0i', '124.104.82.183', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 87, '2026-02-16 00:58:00'),
(2307, 17, 't517q3bd838fmubh7d98tkfb0i', '124.104.82.183', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 87, '2026-02-16 00:59:00'),
(2308, 17, 't517q3bd838fmubh7d98tkfb0i', '124.104.82.183', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 86, '2026-02-16 01:00:00'),
(2309, NULL, '0gc3b86l65qalnggcd1tmd5lvj', '124.104.82.183', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 86, '2026-02-16 01:19:00'),
(2310, 17, 't517q3bd838fmubh7d98tkfb0i', '124.104.82.183', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 95, '2026-02-16 01:20:00'),
(2311, 17, 't517q3bd838fmubh7d98tkfb0i', '124.104.82.183', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 92, '2026-02-16 01:21:00'),
(2312, 17, 't517q3bd838fmubh7d98tkfb0i', '124.104.82.183', 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 95, '2026-02-16 01:23:00'),
(2313, 17, 't517q3bd838fmubh7d98tkfb0i', '124.104.82.183', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 97, '2026-02-16 01:53:00'),
(2314, 17, 't517q3bd838fmubh7d98tkfb0i', '124.104.82.183', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 92, '2026-02-16 01:53:00'),
(2315, NULL, '1e0q0ms7806ns4815qm98qm5pa', '84.0.10.76', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.1 Safari/605.1.15', 23, '2026-02-16 01:54:00'),
(2316, NULL, 't4cm6brk9iahoktk406k3uvfp7', '2001:4860:7:50c::fc', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 24, '2026-02-16 01:59:00'),
(2317, NULL, 's3e5flidoffhdoqfo7ncgkusl1', '198.244.240.212', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 77, '2026-02-16 02:16:00'),
(2318, NULL, 'ihvoj63m30lncc8omk98q0g78b', '2001:4860:7:80c::dc', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-16 02:25:00'),
(2319, NULL, 'n0j7ob4au50ihmbommi2ovjdrd', '2001:4860:7:40c::f3', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 24, '2026-02-16 03:01:00'),
(2320, NULL, 'pcrr3bou10rqucrm7mjrkvn2gt', '47.128.112.1', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 26, '2026-02-16 03:07:00'),
(2321, NULL, 'ne6eun46tg3agkvkm8dje1ofd4', '54.38.147.61', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 83, '2026-02-16 04:06:00'),
(2322, NULL, 'b3eatugke5ohb5hifqpvu9sm9j', '51.195.244.87', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 83, '2026-02-16 04:09:00'),
(2323, NULL, '2837rbgf8a0i3o40mpdt2sn6l8', '47.128.42.64', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 25, '2026-02-16 04:16:00'),
(2324, NULL, 'b9l40jed3ggpmeocbh5p8hf2nk', '54.38.147.226', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 84, '2026-02-16 04:57:00'),
(2325, NULL, 'oin4uqqolh4a99q6bnmipvmcfv', '198.244.183.158', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 84, '2026-02-16 04:59:00'),
(2326, NULL, '550sk8t6mq3u3mtjlrm3i11h4d', '51.89.129.211', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 97, '2026-02-16 05:44:00'),
(2327, NULL, '8guptcebq75ugr9tr330g34n0u', '51.195.244.66', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 97, '2026-02-16 05:48:00'),
(2328, NULL, 'v3ci45cqiffpqc34dt74vtpldp', '198.244.242.54', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 95, '2026-02-16 06:25:00'),
(2329, NULL, '5mlkvfht33n3qcucu3dcurejqc', '54.38.147.179', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 95, '2026-02-16 06:28:00'),
(2330, NULL, 'kh1bhc9h0gqt04lstjf8bi688p', '51.89.129.13', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 87, '2026-02-16 06:57:00'),
(2331, NULL, 'lng9c8id23bcjj9gbqih7nomth', '198.244.183.234', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 87, '2026-02-16 06:59:00'),
(2332, NULL, 'rtbioqpgkho0po2jh39r0829o2', '198.244.168.11', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 86, '2026-02-16 07:06:00'),
(2333, NULL, 'ivhlau10ahne58ikeo2o4ohdq7', '198.244.240.194', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 86, '2026-02-16 07:09:00'),
(2334, NULL, 'apg9jj2elf9ar9j93m8qobj9fi', '119.93.244.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', 100, '2026-02-16 07:18:00'),
(2335, NULL, 'apg9jj2elf9ar9j93m8qobj9fi', '119.93.244.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', 85, '2026-02-16 07:18:00'),
(2336, NULL, 'e3clcs4mvcl6coiv73nbov23mn', '198.244.168.214', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 81, '2026-02-16 07:19:00'),
(2337, NULL, '3ffgj40bb16gl6sn1orig0mklo', '198.244.183.14', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 81, '2026-02-16 07:23:00'),
(2338, 17, 't517q3bd838fmubh7d98tkfb0i', '124.104.82.183', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 100, '2026-02-16 07:26:00'),
(2339, 17, 't517q3bd838fmubh7d98tkfb0i', '124.104.82.183', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 85, '2026-02-16 07:28:00'),
(2340, NULL, 'm4n2vt3olan8k3uadr3jfpf51f', '198.244.240.117', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 82, '2026-02-16 07:29:00'),
(2341, NULL, '4ksbds62qrvsoont5ake6mal70', '198.244.183.167', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 82, '2026-02-16 07:33:00'),
(2342, NULL, 'qfnm5q12l34tflehhk53ecc5qf', '198.244.226.95', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 78, '2026-02-16 07:39:00'),
(2343, NULL, 'cl9vv2on7hsvgq8bpvlqkvthj8', '197.51.223.148', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:137.0) Gecko/20100101 Firefox/137.0', 28, '2026-02-16 08:06:00'),
(2344, NULL, 'hibmdla7ff9dlf0ao0n7eg1di4', '198.244.168.169', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 52, '2026-02-16 08:13:00'),
(2345, NULL, 's8eqqk1r6n7gv5qe1f41i28f7h', '51.89.129.217', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 52, '2026-02-16 08:19:00'),
(2346, NULL, 'do8tudf2893b50i966dq6llimi', '51.195.183.56', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 25, '2026-02-16 08:51:00'),
(2347, NULL, '9vna4rdul06m3ob9s8e6splmeo', '198.244.242.131', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 25, '2026-02-16 08:54:00'),
(2348, NULL, 'u3vqcrickurg3oafeqbqfeuhr0', '198.244.168.33', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 100, '2026-02-16 09:12:00'),
(2349, NULL, '6tjbmspufr23c7f0i459ql0rvf', '51.195.183.6', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 19, '2026-02-16 09:30:00'),
(2350, NULL, '0dh62qgdr5q4uhc20udntr6qe2', '198.244.226.102', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 19, '2026-02-16 09:34:00'),
(2351, NULL, 'd78mdmu1cp5u57hj326gbvmfnu', '182.252.69.24', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36', 29, '2026-02-16 10:43:00'),
(2352, NULL, 'm9ou88bovmav7mtj9r2mm7qq2j', '54.38.147.199', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 93, '2026-02-16 11:07:00'),
(2353, NULL, 'occdbit6r948siuo6128m9usj8', '114.119.159.246', 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 24, '2026-02-16 12:27:00'),
(2354, NULL, '730qt40b5404m2vblst6mqal7k', '2001:4860:7:40c::ec', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 24, '2026-02-16 12:49:00'),
(2355, NULL, '0gsu0kdog6lc9mv0risqil0tmd', '2001:4860:7:232::fe', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 24, '2026-02-16 14:30:00'),
(2356, NULL, '4icajmqirtak2u4oi78t69r5e4', '47.128.57.97', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 23, '2026-02-16 18:36:00'),
(2357, NULL, 'nsrslh2shl08366f7oab1697ao', '47.128.35.61', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 19, '2026-02-16 20:44:00'),
(2358, NULL, '6c13qpdfo91ar5dim37hi7fli0', '8.24.210.105', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_2_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/144.0.7559.95 Mobile/15E148 Safari/604.1', 24, '2026-02-16 23:40:00'),
(2359, NULL, '81rfetj029hd0uursvt1t0r10b', '2001:4860:7:50c::f6', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 24, '2026-02-17 01:09:00'),
(2360, NULL, 'er5jki90jjgsjj9fe8sr4o0gbr', '2001:4860:7:80c::df', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-17 03:05:00'),
(2361, NULL, 'c0ia7lkpu186av6jsuslpbnoaf', '114.119.138.230', 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 8, '2026-02-17 03:53:00'),
(2369, NULL, 'mdud3edbfra11qedm7rdkfht7o', '47.128.118.250', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 28, '2026-02-17 10:13:00'),
(2370, NULL, '9u0gbhr172pco47suo08o6mmph', '114.119.159.246', 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 8, '2026-02-17 12:32:00'),
(2372, NULL, 'uqidtukvt3vc3agsbkqpai9l03', '114.119.159.246', 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 29, '2026-02-17 13:24:00'),
(2374, NULL, 'cf01jk616b3vsudddo6tr4to44', '47.128.120.124', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 29, '2026-02-17 15:54:00'),
(2375, NULL, 'j7sdq3o7ub5u53ensc4o1ifkaq', '47.128.52.135', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 26, '2026-02-17 15:58:00'),
(2377, NULL, 'qn7ru94k32721hfj8pfu9saeoh', '47.128.33.219', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 23, '2026-02-17 21:18:00'),
(2378, NULL, 'eb22pamac5i7ueati8oju0lofo', '47.128.48.32', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 19, '2026-02-17 21:54:00'),
(2379, NULL, '1l9pu477ldt4ir3uvsibcrjvvk', '114.119.136.24', 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 27, '2026-02-17 22:01:00'),
(2380, 17, 'nhjp7i5cm97teaukbucurtlnnm', '124.104.82.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 23, '2026-02-18 02:19:00'),
(2381, NULL, 'i0eq1qcnqi1efe4rotf3amgo8e', '2001:4860:7:50c::fc', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-18 02:22:00'),
(2382, 17, 'nhjp7i5cm97teaukbucurtlnnm', '124.104.82.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 23, '2026-02-18 02:35:00'),
(2383, 17, 'nhjp7i5cm97teaukbucurtlnnm', '124.104.82.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 23, '2026-02-18 02:36:00'),
(2384, 17, 'nhjp7i5cm97teaukbucurtlnnm', '124.104.82.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-18 02:37:00'),
(2385, NULL, 'c7d7e2poq21anf1ievil6ndeo3', '13.76.223.48', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot', 24, '2026-02-18 02:37:00'),
(2386, NULL, 'hb9fr7aals4oflg73k5vj1n2aq', '2001:4860:7:50c::e9', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-18 03:39:00'),
(2387, 17, 'nhjp7i5cm97teaukbucurtlnnm', '124.104.82.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 23, '2026-02-18 03:56:00'),
(2388, 17, 'nhjp7i5cm97teaukbucurtlnnm', '124.104.82.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-18 03:56:00'),
(2389, NULL, '1ta9cjg35dv2fp0omj8jrqsjo4', '114.119.136.24', 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 28, '2026-02-18 03:59:00'),
(2390, NULL, 'ltbenurfe67lvhvjaffe4kv3dr', '114.119.138.230', 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 26, '2026-02-18 04:29:00'),
(2391, NULL, '3iqtph5024pl8duu18uprafksr', '2001:4860:7:80c::cc', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-18 05:08:00'),
(2392, 17, 'nhjp7i5cm97teaukbucurtlnnm', '124.104.82.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-18 05:19:00'),
(2393, 17, 'nhjp7i5cm97teaukbucurtlnnm', '124.104.82.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-18 05:30:00'),
(2394, 17, 'nhjp7i5cm97teaukbucurtlnnm', '124.104.82.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-18 05:41:00'),
(2395, 17, 'nhjp7i5cm97teaukbucurtlnnm', '124.104.82.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-18 05:57:00'),
(2396, 17, 'nhjp7i5cm97teaukbucurtlnnm', '124.104.82.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-18 06:14:00'),
(2397, NULL, 'g2o83kknlnhv0varhralgtcji0', '47.128.125.120', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 51, '2026-02-18 06:15:00'),
(2398, NULL, 'patbajaotno5fnqoo9bbpn9f2f', '2001:4860:7:80c::f8', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 24, '2026-02-18 07:08:00'),
(2399, 17, 'nhjp7i5cm97teaukbucurtlnnm', '124.104.82.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-18 07:19:00'),
(2400, 17, 'nhjp7i5cm97teaukbucurtlnnm', '124.104.82.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-18 07:26:00');
INSERT INTO `recent_views` (`id`, `user_id`, `session_id`, `ip_address`, `user_agent`, `product_id`, `viewed_at`) VALUES
(2401, NULL, 'ebn8uh1fnl6j2g8dsv5c3er2r5', '47.128.119.217', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 8, '2026-02-18 07:44:00'),
(2402, 17, 'nhjp7i5cm97teaukbucurtlnnm', '124.104.82.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-18 07:54:00'),
(2403, 17, 'nhjp7i5cm97teaukbucurtlnnm', '124.104.82.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-18 08:03:00'),
(2404, NULL, '8e8ugcp06rs2re39sprcc1ftcn', '114.119.138.230', 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 52, '2026-02-18 10:00:00'),
(2406, NULL, 'a4eahchii2o6j0ea26lhlfmrto', '47.128.43.175', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 23, '2026-02-18 10:54:00'),
(2407, NULL, '31qaif6u134k628abrvbdtmttg', '114.119.136.24', 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 26, '2026-02-18 13:41:00'),
(2408, NULL, '4nfknjc7eamvj7g0mcgt26ep66', '144.48.109.154', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.3 Safari/605.1.15', 52, '2026-02-18 13:55:00'),
(2409, NULL, '7e5vqoe56km7b6p9um0h3r3qdp', '114.119.159.246', 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 29, '2026-02-18 14:50:00'),
(2410, NULL, '4a6hovoqbt5etfildlm153j1ob', '47.128.59.110', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 28, '2026-02-18 15:27:00'),
(2411, NULL, '9deff1urcp4snmg172d16ftv8u', '47.128.49.149', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 51, '2026-02-18 15:31:00'),
(2413, NULL, '0b5hn08rkamfhmk0i3vem3b3tj', '2001:4860:7:50c::fb', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-18 16:02:00'),
(2414, NULL, '3flsr67q2n3jn1a5t2m0p0quuu', '47.128.125.85', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 29, '2026-02-18 16:04:00'),
(2418, NULL, 'jag2iv16jff8ubpk2c8b9502si', '47.128.55.101', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 25, '2026-02-18 17:07:00'),
(2419, NULL, 'n0k71gg8ub85fls25nrefr4stj', '114.119.159.246', 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 52, '2026-02-18 17:33:00'),
(2421, NULL, '4tps0eqgh8a28ks2a6tsm16gnr', '47.128.55.215', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 26, '2026-02-18 18:22:00'),
(2422, NULL, '90o07sipq4pqcssudjjj8jj6r0', '47.128.58.42', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 81, '2026-02-18 22:30:00'),
(2423, NULL, 'h4djutunra5g2iu8vv11t41bd4', '47.128.113.44', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 27, '2026-02-18 22:42:00'),
(2424, 3, 'pv4hcp2l1b885t2lbbg12vn1mk', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-18 23:11:00'),
(2426, 17, 'hma4pfk4ojojic4cvd6nv69k2u', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-18 23:20:00'),
(2429, NULL, 'me4hiacl1cg1g8cbu3pl86dpuo', '2001:4860:7:50c::fd', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-19 00:55:00'),
(2431, NULL, 's5icc4t4me4j0pa8cl6kmskkbk', '2001:4860:7:811::fa', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-19 01:50:00'),
(2432, NULL, '94qjsj06i8qhcj7ani938memtj', '2405:9800:b660:e870:e72b:e6f:9bee:f703', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 24, '2026-02-19 01:51:00'),
(2433, 17, 'cqv13vnuf54mebjf8qmt9uoa9h', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-19 02:43:00'),
(2434, 17, 'cqv13vnuf54mebjf8qmt9uoa9h', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-19 02:45:00'),
(2435, 17, 'cqv13vnuf54mebjf8qmt9uoa9h', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-19 02:50:00'),
(2437, NULL, 'ru9ul766gn70tqgfnskoqtjn9o', '2001:4860:7:40c::e7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 24, '2026-02-19 03:06:00'),
(2438, NULL, '9q4pgoufg8vfq7iuu2hhps5egt', '47.128.58.29', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 52, '2026-02-19 04:21:00'),
(2439, 17, 'cqv13vnuf54mebjf8qmt9uoa9h', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-19 04:26:00'),
(2440, 38, 'hdp9i88m0eju4iq2ac7pkkd92n', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-19 04:57:00'),
(2441, 38, 'hdp9i88m0eju4iq2ac7pkkd92n', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-19 04:58:00'),
(2442, 38, 'hdp9i88m0eju4iq2ac7pkkd92n', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-19 04:59:00'),
(2443, NULL, 'j9vu0f6bm8i2709dqpjji66jg0', '47.128.48.28', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 81, '2026-02-19 05:00:00'),
(2444, 11, '6fch86hkmt1gj29mui3fts1v56', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', 28, '2026-02-19 05:05:00'),
(2445, 17, 'cqv13vnuf54mebjf8qmt9uoa9h', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-19 05:25:00'),
(2446, 17, 'cqv13vnuf54mebjf8qmt9uoa9h', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-19 05:47:00'),
(2447, 17, 'cqv13vnuf54mebjf8qmt9uoa9h', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-19 06:00:00'),
(2448, 17, 'cqv13vnuf54mebjf8qmt9uoa9h', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-19 06:02:00'),
(2449, 17, 'cqv13vnuf54mebjf8qmt9uoa9h', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 91, '2026-02-19 06:09:00'),
(2450, NULL, '4nj5oh3h6r9k7viqh2o4slt2ap', '76.133.128.24', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15', 24, '2026-02-19 06:28:00'),
(2451, 17, 'cqv13vnuf54mebjf8qmt9uoa9h', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-19 06:35:00'),
(2452, 11, 'qtm7ldfgob9pv7khjlhgdpfm56', '112.209.74.230', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 Edg/145.0.0.0', 28, '2026-02-19 06:46:00'),
(2453, NULL, 'p9874u52mho7np482donngvnrj', '207.46.13.102', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36', 28, '2026-02-19 06:53:00'),
(2456, NULL, '3l73t5h6q6el7jsrqeemm92ujh', '2001:4860:7:40c::f4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 24, '2026-02-19 08:52:00'),
(2457, NULL, 'q55r8d2998gt4vff5qn02b9c30', '47.128.53.48', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 25, '2026-02-19 08:57:00'),
(2460, NULL, 'ph3ednc1qg92mo4mfarhbiaeg6', '47.128.115.143', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 24, '2026-02-19 15:08:00'),
(2462, 17, 'gpvenohkhnsopfrbpqqibsa0db', '112.209.64.58', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-19 23:59:00'),
(2463, 17, 'gpvenohkhnsopfrbpqqibsa0db', '112.209.64.58', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 23, '2026-02-20 00:00:00'),
(2464, 17, 'gpvenohkhnsopfrbpqqibsa0db', '112.209.64.58', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-20 03:42:00'),
(2465, NULL, 'tqat1cpl6kl7pb4qfss3suu9it', '66.249.65.165', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.7559.132 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 24, '2026-02-20 03:50:00'),
(2466, NULL, 'iep8kf13vjpba3giqjb2i683fa', '101.100.189.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 23, '2026-02-20 04:09:00'),
(2467, NULL, 'iep8kf13vjpba3giqjb2i683fa', '101.100.189.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 29, '2026-02-20 04:10:00'),
(2468, NULL, 'iep8kf13vjpba3giqjb2i683fa', '101.100.189.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 23, '2026-02-20 04:10:00'),
(2469, NULL, 'egbqk6fspn5an033ufgto29f6h', '178.156.230.46', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.105 Safari/537.36', 24, '2026-02-20 05:23:00'),
(2470, NULL, 'o6nk1kfsb515lip6uethtadlvd', '2001:4860:7:40c::eb', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 24, '2026-02-20 06:48:00'),
(2471, 17, 'gpvenohkhnsopfrbpqqibsa0db', '112.209.64.58', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 104, '2026-02-20 08:21:00'),
(2472, NULL, 'jkiqjacichhjkh6gh73ql1ospj', '82.21.238.57', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 24, '2026-02-20 08:33:00'),
(2476, NULL, '92t368hl8f1dcatmq21et6939l', '47.128.17.124', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 80, '2026-02-20 11:13:00'),
(2477, NULL, '3gnkjole7ej2a6djimevibvqs0', '47.128.29.10', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 28, '2026-02-20 13:48:00'),
(2478, NULL, '8fek9qscd4i5shbt8e5i2dqjs9', '47.128.44.240', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 79, '2026-02-20 15:21:00'),
(2480, NULL, 'ovg07rvr89q3h56qgnsjdb9ubh', '47.128.119.51', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 19, '2026-02-20 16:03:00'),
(2482, NULL, 'dfgj7hp39g6c1912rvfajt622a', '51.195.244.127', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 81, '2026-02-20 18:13:00'),
(2483, NULL, 'g893ekn9990ef92i2qa8j6v7mm', '198.244.183.137', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 81, '2026-02-20 18:17:00'),
(2487, NULL, '5cbt5a13fgoppbmu9p02t3cere', '47.128.34.29', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 51, '2026-02-20 21:07:00'),
(2489, NULL, 'p99ikrv0jl55vj7j11uck14k6b', '47.128.41.92', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 28, '2026-02-20 22:19:00'),
(2493, NULL, 'e6t4qie73vgui5q2cvqkic87gi', '40.77.167.28', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36', 28, '2026-02-21 00:01:00'),
(2494, NULL, '3psde76lkitkg9jofoef90gdlf', '2001:4860:7:40c::f', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 24, '2026-02-21 00:18:00'),
(2495, 17, '86bi4unepv90rm7rm0hhrsr69h', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-21 00:37:00'),
(2496, NULL, 'l0npacv2h0ffrlda2m68om90o6', '92.222.108.124', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-02-21 00:44:00'),
(2497, NULL, 'grokp5nkjae4is3aord34pmbkl', '2001:4860:7:50c::fa', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 24, '2026-02-21 01:25:00'),
(2498, NULL, '02vek1q7vhaflt180t1595bkfa', '47.128.31.79', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 52, '2026-02-21 01:30:00'),
(2499, 17, '86bi4unepv90rm7rm0hhrsr69h', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-21 01:44:00'),
(2502, 17, '86bi4unepv90rm7rm0hhrsr69h', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-21 02:12:00'),
(2503, NULL, 'fot3sujrp6mjh9e6kbfaa3gend', '47.128.30.222', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 29, '2026-02-21 02:21:00'),
(2504, 17, '86bi4unepv90rm7rm0hhrsr69h', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-21 02:35:00'),
(2505, 17, '86bi4unepv90rm7rm0hhrsr69h', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-21 02:52:00'),
(2506, 17, '86bi4unepv90rm7rm0hhrsr69h', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-21 02:57:00'),
(2507, 17, '86bi4unepv90rm7rm0hhrsr69h', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 79, '2026-02-21 03:04:00'),
(2509, NULL, 'qasqgjaff49ro0v1h1jbtjf05k', '47.128.49.103', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 26, '2026-02-21 03:20:00'),
(2511, NULL, '8g7s24qlr4olksmhqegraoro94', '47.128.31.67', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 23, '2026-02-21 04:18:00'),
(2512, NULL, '11s3p5j9qs9j2hbov873j8ojec', '47.128.18.157', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 27, '2026-02-21 05:06:00'),
(2514, NULL, '2tfvjg39tpd8prj3rpgkeskj0e', '111.90.237.37', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 23, '2026-02-21 06:19:00'),
(2517, NULL, '9t3bcm6m9fvrlqj88hrsha02d8', '2001:4860:7:90c::fc', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 24, '2026-02-21 07:23:00'),
(2518, NULL, '883k9eopjqltlsgd9g2uo7972o', '2001:4860:7:50c::f8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 24, '2026-02-21 07:24:00'),
(2519, NULL, 'meekouf9nrv9pbrtgn0c45706n', '2001:4860:7:50c::fe', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 24, '2026-02-21 08:03:00'),
(2520, NULL, '7bki4p25epdvqps5k23d17cb1p', '47.128.20.45', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 101, '2026-02-21 09:23:00'),
(2521, NULL, 'mo23o68ppo09hvs906mtppnoc7', '47.128.127.77', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 24, '2026-02-21 09:32:00'),
(2523, NULL, 'k4s5ciuvhdqlm435geqvvmg6jv', '47.128.51.63', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 97, '2026-02-21 13:21:00'),
(2524, NULL, 'n8gjm6lasdpmm9hflhvk2k012q', '47.128.27.116', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 85, '2026-02-21 13:33:00'),
(2525, NULL, 'agko7q1u4c1spcpelhifd6mavg', '2001:4860:7:50c::fd', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 24, '2026-02-21 14:22:00'),
(2526, NULL, 'ecajhr9qlidqurs02ge0i8kbko', '47.128.25.31', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 82, '2026-02-21 14:24:00'),
(2529, NULL, 'thrd8081gi24jm7qu2nsavqed4', '47.128.57.199', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 77, '2026-02-21 16:15:00'),
(2530, NULL, 'kk0i4219571frnpvogi7323ne8', '47.128.127.2', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 97, '2026-02-21 16:58:00'),
(2531, NULL, '369d0klqr24inkheq4b55td9if', '47.128.98.82', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 77, '2026-02-21 18:00:00'),
(2532, NULL, 'ar6pskidnlh2n0qmg6b4f7va0s', '114.119.159.246', 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 24, '2026-02-21 18:40:00'),
(2533, NULL, '84uof6eg6agl33t58sm3uh2kmo', '47.128.26.85', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 29, '2026-02-21 20:11:00'),
(2534, NULL, 'i24781fee44u1et6c3312fvjhu', '47.128.18.177', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 87, '2026-02-21 21:22:00'),
(2537, NULL, '4k5k2vqrnmg52v4iedd8kfcl0s', '47.128.31.241', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 100, '2026-02-21 22:03:00'),
(2539, NULL, 'tu6mlnq6mj2r49hsdig44j133k', '47.128.20.199', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 100, '2026-02-21 23:39:00'),
(2540, NULL, 'bpifi29o83ndaksi2t35o6oahr', '47.128.118.146', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 95, '2026-02-22 00:15:00'),
(2541, NULL, 'su5m7pga55djoi7r1pmpb6fdr2', '47.128.127.173', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 86, '2026-02-22 01:05:00'),
(2542, NULL, 'rf4jmmmvm45ccp3rs5av4pblt0', '37.59.204.149', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 26, '2026-02-22 01:35:00'),
(2543, NULL, 'd05qiionvsak32vn22ki2iq8b9', '47.128.27.171', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 83, '2026-02-22 02:06:00'),
(2544, NULL, 'jvbv6hs4d93qa7ldq7a7nlkqnn', '47.128.38.179', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 86, '2026-02-22 03:07:00'),
(2545, NULL, '62aq9q2rco0nf3la3gmioebch1', '176.31.139.25', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 51, '2026-02-22 03:14:00'),
(2546, NULL, 'emgumbc4tfp8tf0c5o3f3difeu', '176.31.139.6', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 27, '2026-02-22 03:20:00'),
(2547, NULL, 'm0d9sieqcnefac9qaddvcnlt3e', '51.195.244.154', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-02-22 03:45:00'),
(2549, NULL, 'jlcolcp9487ug9j83gv94f9qbd', '2001:4860:7:90c::fc', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 24, '2026-02-22 04:06:00'),
(2550, NULL, '7s4ugesjk89bvh3dgeoubdqae1', '2001:4860:7:50c::fd', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 24, '2026-02-22 04:07:00'),
(2551, NULL, 'fc5gp4l2i5i1gt09cp6b41s3q5', '2001:4452:32b:f00:84c:1f0c:3f3:1f0e', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 24, '2026-02-22 04:08:00'),
(2552, NULL, 'emc40sip24thd04ursp4f1adrr', '2001:4860:7:50c::f9', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 24, '2026-02-22 04:09:00'),
(2553, NULL, 'g2ui7al03b8f2pd6ncleqvtc9b', '2001:4860:7:50c::fa', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', 24, '2026-02-22 04:12:00'),
(2554, NULL, 'q382r128vrfpuk5tlptp59l9ud', '198.244.168.83', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-02-22 05:03:00'),
(2555, NULL, '559ap3t01ipgmo666pspvfpcp7', '198.244.183.218', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 97, '2026-02-22 05:14:00'),
(2556, NULL, '411aj5ssltp0rur9n8v5sirr3h', '198.244.240.147', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 97, '2026-02-22 05:18:00'),
(2557, NULL, 'rh91rbsl87p36ll40hr02s6n77', '198.244.226.13', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 95, '2026-02-22 05:40:00'),
(2558, NULL, '6vhm03qf2lsrjkuqbeta4tr4so', '198.244.240.4', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 95, '2026-02-22 05:43:00'),
(2559, NULL, 'bn3dv62fdjbramqlotr3nuin19', '51.195.244.126', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 92, '2026-02-22 06:01:00'),
(2560, NULL, 'dkg8bmi8c9eijkqk6dat2rn9ii', '51.195.244.254', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 92, '2026-02-22 06:03:00'),
(2561, NULL, 'gb6g02cv83iig4pbdnhvqarh93', '54.38.147.250', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 87, '2026-02-22 06:21:00'),
(2562, NULL, '32o7v1els40bs22vsrji3e9pla', '51.195.183.68', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 87, '2026-02-22 06:23:00'),
(2563, NULL, 'gp45kp6v71a5ak3rum8ippe5ss', '198.244.226.232', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 86, '2026-02-22 06:31:00'),
(2564, NULL, '1o6re7ciek78skk83hegj8ru7l', '198.244.242.27', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 86, '2026-02-22 06:33:00'),
(2565, NULL, 'sjunu8msdqcbh34al8g1klh4gl', '198.244.242.122', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 85, '2026-02-22 06:42:00'),
(2566, NULL, 'kcur88s5mtaculhuje1omdii1g', '51.195.183.177', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 85, '2026-02-22 06:43:00'),
(2567, NULL, '16g43rldlqkopgn0c84kp1bq83', '198.244.183.176', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 84, '2026-02-22 06:52:00'),
(2568, NULL, 'isa2oecbu5qsh8nbugg9175rou', '198.244.240.38', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 84, '2026-02-22 06:53:00'),
(2569, NULL, '5lpvgtbnpgjfuse9hgcpll1a9k', '198.244.168.17', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 83, '2026-02-22 07:02:00'),
(2570, NULL, '9i56b45f5jttl1hqiuaoevi6k0', '198.244.240.165', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 83, '2026-02-22 07:03:00'),
(2571, NULL, '0egc7hgj7n49okbq0dqf7n5214', '198.244.240.192', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 77, '2026-02-22 07:13:00'),
(2572, NULL, 'dmn7oit1qcuohvmchi8d0jf4pj', '198.244.226.24', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 77, '2026-02-22 07:18:00'),
(2573, NULL, 'fp50h78t7951ag7gnef4009adb', '198.244.168.40', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 100, '2026-02-22 07:23:00'),
(2574, NULL, 'a2eujt4mchio0rcqav7hgg1epl', '51.195.244.29', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 81, '2026-02-22 07:35:00'),
(2575, NULL, '6rbjrmb8o40ik5k1av9rcq30rc', '47.128.25.234', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 82, '2026-02-22 07:36:00'),
(2576, NULL, '8fugf9ajstbqtpbe9oicltj773', '198.244.183.40', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 81, '2026-02-22 07:38:00'),
(2577, NULL, 'n13vttrcdm2i9ov2j1e0hqus84', '47.128.125.199', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 85, '2026-02-22 07:40:00'),
(2578, NULL, 'hhj1nruarasekht6d7rdv5122i', '198.244.183.90', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 82, '2026-02-22 07:46:00'),
(2579, NULL, 'vljv9lcn04vc08khptfsnou6r6', '51.195.244.72', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 82, '2026-02-22 07:48:00'),
(2580, NULL, '82s7rto6l3mdm95tbbmtaua7p2', '47.128.127.189', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 83, '2026-02-22 08:06:00'),
(2581, NULL, 'h70nq2o02052dqo6lmtdarc5hf', '47.128.116.188', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 87, '2026-02-22 08:07:00'),
(2583, NULL, 'tav0fgq97c4g3puoaituk6lt2s', '54.37.118.89', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 101, '2026-02-22 08:21:00'),
(2584, NULL, '063la8sr1l6kflto8uk4qppu9b', '54.37.118.88', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 101, '2026-02-22 08:23:00'),
(2585, NULL, 'pfos3j9h4dkaaa8q41opme9fu9', '92.222.108.116', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 19, '2026-02-22 09:02:00'),
(2586, NULL, 'jua9sctg7ruqm9t08ldenaf32e', '92.222.104.212', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 8, '2026-02-22 09:13:00'),
(2588, NULL, '958gc2pihla2mf4k65r5sg9hhl', '47.128.52.99', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 95, '2026-02-22 09:29:00'),
(2589, NULL, 'vt09mcs6jkh5p8harc6l7q6mc7', '47.128.55.145', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 84, '2026-02-22 09:29:00'),
(2590, NULL, 'tn39bkve6b1gifhunlm452a094', '47.128.61.205', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 84, '2026-02-22 09:33:00'),
(2591, NULL, '17ou1alqp97nhokptv4pu784m2', '40.77.167.50', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36', 28, '2026-02-22 09:44:00'),
(2592, NULL, 'c91cmvec48vodb123l7eohtr9n', '92.222.104.222', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2026-02-22 09:49:00'),
(2593, NULL, '58em5c6kobppc1a3tdh6tn9319', '47.128.118.44', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 81, '2026-02-22 10:09:00'),
(2594, NULL, 'feoqon7n0u21rtvihbsgdml0q8', '176.31.139.4', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-02-22 10:20:00'),
(2595, NULL, 'p7k4d3hed3kc6iobd28hn0rgfk', '5.39.109.164', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 29, '2026-02-22 10:25:00'),
(2598, NULL, 'ria3aeuugts05ot1itsacghjo9', '198.244.168.207', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 79, '2026-02-22 11:04:00'),
(2600, NULL, 'tttshvbtko9c98kn7regog9upa', '198.244.240.158', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 79, '2026-02-22 11:08:00'),
(2601, NULL, '00qgm5t3n4l9378hqt433q890h', '198.244.226.125', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 28, '2026-02-22 11:31:00'),
(2605, NULL, '80a752qd4jiqlgtn45uv97jo3d', '198.244.242.201', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 23, '2026-02-22 16:09:00'),
(2606, NULL, 'fjs95cksebhnb8o774trkqj5th', '54.38.147.121', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 24, '2026-02-22 16:13:00'),
(2609, NULL, 's9vi165lhjs6esjvucjd8d4dnd', '51.195.244.221', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 104, '2026-02-22 16:44:00'),
(2610, NULL, 'sqf8e4hii05u117dehao2q3rpn', '54.38.147.111', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 104, '2026-02-22 16:48:00'),
(2611, NULL, 't7tfrteguprqpir19385bnhlo3', '198.244.226.248', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 103, '2026-02-22 17:03:00'),
(2612, NULL, '2uf6lrg5bb5pc35umn4uqbd0i6', '198.244.242.179', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)', 103, '2026-02-22 17:08:00'),
(2617, NULL, '2fb7ns2gvpmc12uaej4fv2d36o', '114.119.136.24', 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 8, '2026-02-22 21:06:00'),
(2620, NULL, 'vbg3g45b6qkeatsaibpnoad5l2', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 82, '2026-02-23 00:08:00'),
(2622, NULL, 'vbg3g45b6qkeatsaibpnoad5l2', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 82, '2026-02-23 00:13:00'),
(2623, 17, 'l5dr84p5la9vps55o834ihq3pd', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 29, '2026-02-23 00:15:00'),
(2625, NULL, 'v8835uauspt3bdl2asst7lr4dr', '47.128.28.71', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 52, '2026-02-23 01:17:00'),
(2626, 17, 'l5dr84p5la9vps55o834ihq3pd', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 77, '2026-02-23 01:18:00'),
(2627, NULL, 'vbg3g45b6qkeatsaibpnoad5l2', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 84, '2026-02-23 01:36:00'),
(2628, NULL, 'ue00ko3eaj4qtbk5jd85ab46t5', '2001:4860:7:40c::f2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 24, '2026-02-23 03:58:00'),
(2633, 16, 'obc09vq00169rhkputj94muqbp', '124.104.83.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 28, '2026-02-23 06:43:00'),
(2635, NULL, 'l7oh5hfg64h6q9cchrph04kc6v', '114.119.136.24', 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 26, '2026-02-23 10:10:00'),
(2637, NULL, 'hhgjnsqmnsqpgpt4519ngoolch', '47.128.24.54', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 96, '2026-02-23 13:03:00'),
(2638, NULL, 'ofsn082qdp2uaajhoitja2hs51', '47.128.38.246', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 88, '2026-02-23 14:41:00'),
(2639, NULL, 'ili3phreij45uk1aqnfprvmf0l', '47.128.98.108', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 92, '2026-02-23 15:50:00'),
(2640, NULL, '3fathpja7236c5td91cuanjtj9', '47.128.115.99', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 79, '2026-02-23 17:26:00'),
(2641, NULL, 'snfnbtoe5ugnghjb1ma4gfhbbv', '114.119.138.230', 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 28, '2026-02-23 17:38:00'),
(2642, NULL, '9ihv0k0n2qra8uts34j497ihj1', '114.119.138.230', 'Mozilla/5.0 (Linux; Android 7.0;) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)', 24, '2026-02-23 18:25:00'),
(2644, NULL, 'v56gfj4s4l7qnbl8lsv0rn2dmb', '47.128.62.67', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 91, '2026-02-23 21:14:00'),
(2645, NULL, 'tk8uck1t45ufqfpljnsd7lv8c0', '47.128.51.154', 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', 99, '2026-02-24 01:04:00');

-- --------------------------------------------------------

--
-- Table structure for table `referral_codes`
--

CREATE TABLE `referral_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `referral_code` varchar(20) NOT NULL,
  `qr_code_path` varchar(255) DEFAULT NULL,
  `base_url` varchar(500) DEFAULT NULL,
  `total_scans` int(11) DEFAULT 0,
  `total_conversions` int(11) DEFAULT 0,
  `total_revenue` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `discount_type` enum('percentage','fixed') DEFAULT 'percentage',
  `discount_value` decimal(10,2) DEFAULT 0.00,
  `discount_enabled` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `referral_codes`
--

INSERT INTO `referral_codes` (`id`, `user_id`, `referral_code`, `qr_code_path`, `base_url`, `total_scans`, `total_conversions`, `total_revenue`, `is_active`, `discount_type`, `discount_value`, `discount_enabled`, `created_at`, `updated_at`) VALUES
(1, 2, 'NH-9JEMN5', 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E?ref=NH-9JEMN5', 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E', 4, 0, 0.00, 0, 'percentage', 20.00, 1, '2025-11-21 05:03:55', '2025-11-28 23:42:17'),
(2, 2, 'NH-XL5D4T', 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E?ref=NH-XL5D4T', 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E', 0, 0, 0.00, 0, 'percentage', 0.00, 0, '2025-11-28 23:42:17', '2025-11-28 23:42:47'),
(3, 2, 'NH-JVJY24', 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E?ref=NH-JVJY24', 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E', 0, 0, 0.00, 0, 'percentage', 0.00, 0, '2025-11-28 23:42:47', '2025-11-28 23:42:58'),
(4, 2, 'NH-QHHGJX', 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E?ref=NH-QHHGJX', 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E', 0, 0, 0.00, 0, 'percentage', 0.00, 0, '2025-11-28 23:42:58', '2025-11-28 23:43:06'),
(5, 2, 'NH-546DTN', 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E?ref=NH-546DTN', 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E', 0, 0, 0.00, 0, 'percentage', 0.00, 0, '2025-11-28 23:43:06', '2025-11-28 23:43:22'),
(6, 2, 'NH-D9JJHX', 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E?ref=NH-D9JJHX', 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E', 0, 0, 0.00, 0, 'percentage', 0.00, 0, '2025-11-28 23:43:22', '2025-11-28 23:43:53'),
(7, 2, 'NH-G28HUD', NULL, 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E', 0, 0, 0.00, 0, 'percentage', 0.00, 0, '2025-11-28 23:43:53', '2025-11-28 23:43:57'),
(8, 2, 'NH-FK4RFU', 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E?ref=NH-FK4RFU', 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E', 1, 0, 0.00, 0, 'percentage', 0.00, 0, '2025-11-28 23:43:57', '2025-11-29 00:10:40'),
(9, 2, 'NH-XY2E7N', 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E?ref=NH-XY2E7N', 'https://noblehomedepot.com/user/otherpage/index-page-1-A-B-C-D-E', 6, 0, 0.00, 1, 'percentage', 0.00, 0, '2025-11-29 00:10:40', '2025-12-05 07:09:28');

-- --------------------------------------------------------

--
-- Table structure for table `referral_visits`
--

CREATE TABLE `referral_visits` (
  `id` int(11) NOT NULL,
  `referral_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `referral_code` varchar(20) NOT NULL,
  `visit_date` date NOT NULL,
  `visit_time` datetime NOT NULL,
  `visitor_ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `referral_visits`
--

INSERT INTO `referral_visits` (`id`, `referral_id`, `user_id`, `referral_code`, `visit_date`, `visit_time`, `visitor_ip`, `user_agent`, `created_at`) VALUES
(1, 1, 2, 'NH-9JEMN5', '2025-11-21', '2025-11-21 05:17:30', '124.104.82.28', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-21 05:17:30'),
(2, 1, 2, 'NH-9JEMN5', '2025-11-22', '2025-11-22 02:43:53', '124.104.82.28', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-22 02:43:53'),
(3, 1, 2, 'NH-9JEMN5', '2025-11-22', '2025-11-22 02:51:40', '2001:fd8:401:bc43:1:2:2874:fe5a', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', '2025-11-22 02:51:40'),
(4, 1, 2, 'NH-9JEMN5', '2025-11-28', '2025-11-28 23:41:20', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-28 23:41:20'),
(5, 8, 2, 'NH-FK4RFU', '2025-11-28', '2025-11-28 23:44:11', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-28 23:44:11'),
(6, 9, 2, 'NH-XY2E7N', '2025-11-29', '2025-11-29 00:23:23', '124.104.80.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 00:23:23'),
(7, 9, 2, 'NH-XY2E7N', '2025-12-03', '2025-12-03 02:40:52', '112.209.67.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 02:40:52'),
(8, 9, 2, 'NH-XY2E7N', '2025-12-03', '2025-12-03 02:52:36', '112.209.67.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 02:52:36'),
(9, 9, 2, 'NH-XY2E7N', '2025-12-03', '2025-12-03 02:53:13', '112.209.67.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 02:53:13'),
(10, 9, 2, 'NH-XY2E7N', '2025-12-05', '2025-12-05 07:08:57', '2001:4451:13d5:f00:4534:c766:9deb:ddb0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 07:08:57'),
(11, 9, 2, 'NH-XY2E7N', '2025-12-05', '2025-12-05 07:09:28', '2001:4451:13d5:f00:4534:c766:9deb:ddb0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 07:09:28');

-- --------------------------------------------------------

--
-- Table structure for table `replacement_requests`
--

CREATE TABLE `replacement_requests` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `reason` enum('defective','damaged','wrong_item','wrong_size','not_as_described','other') NOT NULL,
  `details` text DEFAULT NULL,
  `replacement_quantity` int(11) NOT NULL DEFAULT 1,
  `po_number` varchar(50) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `warehouse_location` varchar(255) DEFAULT NULL,
  `received_status` enum('pending','received') DEFAULT 'pending',
  `received_by` int(11) DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `defect_image_overview` varchar(255) NOT NULL COMMENT 'Filename for overview image showing full product',
  `defect_image_closeup` varchar(255) NOT NULL COMMENT 'Filename for close-up image of defect area',
  `defect_image_detail` varchar(255) NOT NULL COMMENT 'Filename for additional detail image',
  `status` enum('pending','approved','processing','In Warehouse','scheduled','ready_for_pickup','item_is_loaded','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL COMMENT 'Notes from admin/support team',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `delivery_schedule_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `replacement_requests`
--

INSERT INTO `replacement_requests` (`id`, `order_id`, `order_item_id`, `user_email`, `reason`, `details`, `replacement_quantity`, `po_number`, `qr_code`, `warehouse_location`, `received_status`, `received_by`, `received_at`, `defect_image_overview`, `defect_image_closeup`, `defect_image_detail`, `status`, `admin_notes`, `created_at`, `updated_at`, `delivery_schedule_id`) VALUES
(1, 31, 31, 'salvadoramarkjamesfrayna@gmail.com', 'defective', 'sira na order ko', 1, 'REP-NH3111062025558', 'https://www.noblehomedepot.com/noble/admin/warehouse_management/scan_replacement.php?replacement_id=1', 'Conference Room', 'pending', NULL, NULL, 'defect_31_31_overview_1762394338.jpg', 'defect_31_31_closeup_1762394338.png', 'defect_31_31_detail_1762394338.jpg', 'scheduled', '', '2025-11-06 01:58:58', '2025-11-06 02:35:30', NULL),
(2, 32, 32, 'salvadoramarkjamesfrayna@gmail.com', 'defective', 'sfafsdaf', 1, 'REP-NH3211072025576', 'https://noblehomedepot.com/admin/warehouse_management/scan_replacement.php?replacement_id=2', 'Conference Room', 'pending', NULL, NULL, 'defect_32_32_overview_1762505935.png', 'defect_32_32_closeup_1762505935.png', 'defect_32_32_detail_1762505935.png', 'delivered', '', '2025-11-07 08:58:55', '2025-11-07 23:54:03', 4),
(3, 35, 35, 'salvadoramarkjamesfrayna@gmail.com', 'defective', 'rgrdrgdg', 1, 'REP-NH3511132025764', 'https://noblehomedepot.com/admin/warehouse_management/scan_replacement.php?replacement_id=3', 'In warehouse', 'pending', NULL, NULL, 'defect_35_35_overview_1762995431.png', 'defect_35_35_closeup_1762995431.png', 'defect_35_35_detail_1762995431.png', 'delivered', '', '2025-11-13 00:57:11', '2025-11-13 01:07:07', 8),
(4, 7, 7, 'salvadoramarkjamesfrayna@gmail.com', 'wrong_size', 'test', 5, 'REP-NH702242026548', NULL, NULL, 'pending', NULL, NULL, 'defect_7_7_overview_1771892982.webp', 'defect_7_7_closeup_1771892982.webp', 'defect_7_7_detail_1771892982.webp', 'approved', '', '2026-02-24 00:29:42', '2026-02-24 01:07:47', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sold_items`
--

CREATE TABLE `sold_items` (
  `id` int(11) NOT NULL,
  `sold_order_id` int(11) NOT NULL,
  `original_order_item_id` int(11) DEFAULT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(100) DEFAULT NULL,
  `codename` varchar(100) DEFAULT NULL,
  `type_name` varchar(100) DEFAULT NULL,
  `variant_color` varchar(100) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL COMMENT 'Unit price',
  `quantity` int(11) DEFAULT NULL COMMENT 'Quantity sold',
  `subtotal` decimal(10,2) DEFAULT NULL COMMENT 'Item subtotal',
  `delivery_fee_per_item` decimal(10,2) DEFAULT 0.00,
  `item_total_delivery` decimal(10,2) DEFAULT 0.00,
  `item_total` decimal(10,2) GENERATED ALWAYS AS (`subtotal` + `item_total_delivery`) STORED,
  `descrip6` text DEFAULT NULL,
  `descrip7` text DEFAULT NULL,
  `origin` varchar(255) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `manual_supplier_name` varchar(255) DEFAULT NULL,
  `po_number` varchar(50) DEFAULT NULL,
  `qr_code` varchar(100) DEFAULT NULL,
  `warehouse_location` varchar(255) DEFAULT NULL,
  `lt_from` date DEFAULT NULL,
  `lt_to` date DEFAULT NULL,
  `sold_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Sales history - Record of all sold items';

--
-- Dumping data for table `sold_items`
--

INSERT INTO `sold_items` (`id`, `sold_order_id`, `original_order_item_id`, `order_id`, `product_id`, `product_name`, `codename`, `type_name`, `variant_color`, `size`, `price`, `quantity`, `subtotal`, `delivery_fee_per_item`, `item_total_delivery`, `descrip6`, `descrip7`, `origin`, `supplier_id`, `manual_supplier_name`, `po_number`, `qr_code`, `warehouse_location`, `lt_from`, `lt_to`, `sold_at`) VALUES
(2, 2, 32, 32, 55, '', 'Bedfurniture', 'CANOPY OR FOUR-POST KING BED', 'CANOPY OR FOUR-POST KING BED', '200 cm (W) × 220 cm (L) × 210 cm (H)', 29500.00, 1, 29500.00, 0.00, 0.00, '', '', 'local', 0, 'hello', 'NH110720251246020', 'https://noblehomedepot.com/admin/warehouse_management/scan_item.php?item_id=32', 'Conference Room', NULL, NULL, '2025-11-07 08:57:53'),
(5, 5, 46, 44, 28, 'AAC ADHESIVES', 'AacBlock', 'AAC ADHESIVES', 'Normal', '25kg', 550.00, 10, 5500.00, 0.00, 0.00, '', '', 'local', 2, NULL, 'NH112120251333432', 'https://noblehomedepot.com/admin/warehouse_management/scan_item.php?item_id=46', 'nasa conference', NULL, NULL, '2025-11-21 05:44:14'),
(6, 5, 47, 44, 29, 'AAC BLOCKS BRACKET', 'AacBlock', 'AAC BLOCKS BRACKETS', 'Normal', 'AAC BRACKET', 24.75, 10, 247.50, 0.00, 0.00, '', '', 'local', 2, NULL, 'NH112120251333432', 'https://noblehomedepot.com/admin/warehouse_management/scan_item.php?item_id=47', 'nasa conference', NULL, NULL, '2025-11-21 05:44:14'),
(8, 6, 56, 50, 28, 'AAC ADHESIVES', 'AacBlock', 'AAC ADHESIVES', 'Normal', '25kg', 440.00, 1, 440.00, 0.00, 0.00, '', '', 'local', 2, NULL, 'NH112220251055592', 'https://noblehomedepot.com/admin/warehouse_management/scan_item.php?item_id=56', 'confksjf', NULL, NULL, '2025-11-22 03:05:56'),
(9, 7, 62, 56, 28, 'AAC ADHESIVES', 'AacBlock', 'AAC ADHESIVES', 'Normal', '25kg', 550.00, 5, 2750.00, 0.00, 0.00, '', '', 'local', 2, NULL, 'NH01092026749272', 'https://noblehomedepot.com/admin/warehouse_management/receiver_scan_item_A1.php?item_id=62', 'shelter1', NULL, NULL, '2026-01-09 00:16:05'),
(10, 8, 7, 7, 28, 'AAC ADHESIVES', 'AacBlock', 'AAC ADHESIVES', 'Normal', '25kg', 0.20, 5, 1.00, 0.00, 0.00, '', '', 'local', 2, NULL, 'NH022320261453372', 'https://noblehomedepot.com/admin/warehouse_management/receiver_scan_item_A1.php?item_id=7', 'hello', NULL, NULL, '2026-02-24 00:27:39');

-- --------------------------------------------------------

--
-- Table structure for table `sold_orders`
--

CREATE TABLE `sold_orders` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `emp_id` int(11) DEFAULT NULL,
  `warehouse_employee_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `shipping_fee` decimal(10,2) DEFAULT 0.00,
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `vat_amount` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) DEFAULT NULL,
  `final_total` decimal(10,2) DEFAULT 0.00,
  `gross_sales` decimal(10,2) GENERATED ALWAYS AS (`subtotal`) STORED,
  `net_sales` decimal(10,2) GENERATED ALWAYS AS (`final_total`) STORED,
  `address` text DEFAULT NULL,
  `zipcode` varchar(10) DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `delivery_distance` decimal(10,2) DEFAULT 0.00,
  `delivery_type` enum('delivery','pickup') DEFAULT 'delivery',
  `assigned_vehicle_id` int(11) DEFAULT NULL,
  `assigned_vehicle_type` varchar(100) DEFAULT NULL,
  `total_cubic_meters` decimal(10,3) DEFAULT 0.000,
  `total_weight_kg` decimal(10,2) DEFAULT 0.00,
  `total_width` decimal(10,2) DEFAULT 0.00,
  `total_height` decimal(10,2) DEFAULT 0.00,
  `total_length` decimal(10,2) DEFAULT 0.00,
  `mode_payment` varchar(50) DEFAULT NULL,
  `payment_status` enum('pending','verified','rejected') DEFAULT 'verified',
  `bank_type` varchar(10) DEFAULT NULL,
  `payment_screenshot` varchar(255) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `paypal_order_id` varchar(100) DEFAULT NULL,
  `paypal_capture_id` varchar(100) DEFAULT NULL,
  `paypal_payer_email` varchar(255) DEFAULT NULL,
  `paypal_payer_name` varchar(255) DEFAULT NULL,
  `paymongo_session_id` varchar(255) DEFAULT NULL,
  `billing_address_id` int(11) DEFAULT NULL,
  `order_date` datetime DEFAULT NULL COMMENT 'Date when order was created',
  `confirmed_at` timestamp NULL DEFAULT NULL COMMENT 'Date when order was confirmed',
  `completed_at` datetime DEFAULT NULL COMMENT 'Date when order was completed',
  `delivered_at` datetime DEFAULT NULL COMMENT 'Date when order was delivered',
  `sale_recorded_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Date moved to sales record',
  `fiscal_year` year(4) GENERATED ALWAYS AS (year(`delivered_at`)) STORED,
  `fiscal_month` tinyint(4) GENERATED ALWAYS AS (month(`delivered_at`)) STORED,
  `fiscal_quarter` tinyint(4) GENERATED ALWAYS AS (quarter(`delivered_at`)) STORED,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Sales history - Record of all sold/delivered orders';

--
-- Dumping data for table `sold_orders`
--

INSERT INTO `sold_orders` (`id`, `order_id`, `user_id`, `emp_id`, `warehouse_employee_id`, `customer_name`, `email`, `mobile`, `subtotal`, `discount`, `shipping_fee`, `delivery_fee`, `vat_amount`, `total`, `final_total`, `address`, `zipcode`, `latitude`, `longitude`, `delivery_distance`, `delivery_type`, `assigned_vehicle_id`, `assigned_vehicle_type`, `total_cubic_meters`, `total_weight_kg`, `total_width`, `total_height`, `total_length`, `mode_payment`, `payment_status`, `bank_type`, `payment_screenshot`, `reference_no`, `reference_number`, `verified_by`, `paypal_order_id`, `paypal_capture_id`, `paypal_payer_email`, `paypal_payer_name`, `paymongo_session_id`, `billing_address_id`, `order_date`, `confirmed_at`, `completed_at`, `delivered_at`, `sale_recorded_at`, `updated_at`) VALUES
(1, 31, 16, 2, 9, 'BSIT 4107-Salvadora, Mark James F.', 'salvadoramarkjamesfrayna@gmail.com', '09671677760', 140.18, 0.00, 0.00, 198.11, 19.11, 357.40, 357.40, 'Sitio Pajo, Quezon City, Metro Manila, Philippines', '1106', 14.663605, 121.0146951, 2.00, 'delivery', NULL, '0', 0.000, 0.00, 0.00, 0.00, 0.00, 'PayMongo', 'verified', NULL, NULL, 'NH9839164', NULL, 4, NULL, NULL, NULL, NULL, 'cs_Wy1EN1WG5DE9W4o1639mhpXv', 32, '2025-11-05 23:45:37', '2025-11-05 23:52:22', NULL, '2025-11-06 09:49:57', '2025-11-06 01:49:57', '2025-11-06 01:49:57'),
(2, 32, 16, 2, 9, 'BSIT 4107-Salvadora, Mark James F.', 'salvadoramarkjamesfrayna@gmail.com', '09671677760', 29096.12, 0.00, 0.00, 198.11, 3967.65, 33261.88, 33261.88, 'Sitio Pajo, Quezon City, Metro Manila, Philippines', '1106', 14.663605, 121.0146951, 2.00, 'delivery', NULL, '0', 0.000, 0.00, 0.00, 0.00, 0.00, 'PayMongo', 'verified', NULL, NULL, 'NH9861910', NULL, 4, NULL, NULL, NULL, NULL, 'cs_wtkRFD3gx2ykTfDJrswspmG8', 32, '2025-11-07 04:41:44', '2025-11-07 04:44:29', NULL, '2025-11-07 16:57:53', '2025-11-07 08:57:53', '2025-11-07 08:57:53'),
(3, 35, 16, 2, 9, 'BSIT 4107-Salvadora, Mark James F.', 'salvadoramarkjamesfrayna@gmail.com', '09671677760', 197.00, 0.00, 0.00, 198.11, 23.64, 418.75, 418.75, 'Sitio Pajo, Quezon City, Metro Manila, Philippines', '1106', 14.663605, 121.0146951, 2.00, 'delivery', 1, 'Sedan', 0.027, 1.00, 0.00, 0.00, 0.00, 'PayMongo', 'verified', NULL, NULL, 'NH9876750', NULL, 4, NULL, NULL, NULL, NULL, 'cs_YDUq24XxdH7889JwJ3xTM3Td', 32, '2025-11-13 00:06:52', '2025-11-13 00:10:30', NULL, '2025-11-13 08:56:08', '2025-11-13 00:56:08', '2025-11-13 00:56:08'),
(4, 41, 17, 2, 9, 'BSIT4107_HIMARANGAN Wendhil', 'wendhil10@gmail.com', '09081031241', 121.00, 0.00, 0.00, 205.26, 14.52, 340.78, 340.78, '128 sitio pajo, quezon city, Metro Manila, Philippines', '1106', 14.6631312, 121.0144921, 3.00, 'delivery', 1, 'Sedan', 0.027, 1.00, 0.00, 0.00, 0.00, 'PayMongo', 'verified', NULL, NULL, 'NH9866376', NULL, 4, NULL, NULL, NULL, NULL, 'cs_SuH2wNm2VcXYWD7qY5Q65rUg', 36, '2025-11-14 08:05:57', '2025-11-14 08:33:33', NULL, '2025-11-14 16:50:51', '2025-11-14 08:50:51', '2025-11-14 08:50:51'),
(5, 44, 16, NULL, 9, 'BSIT 4107-Salvadora, Mark James F.', 'salvadoramarkjamesfrayna@gmail.com', '09671677760', 4598.00, 0.00, 0.00, 508.66, 551.76, 5658.42, 5658.42, 'Sitio Pajo, Quezon City, Metro Manila, Philippines', '1106', 14.663605, 121.0146951, 2.91, 'delivery', 2, 'MPV/SUV', 0.540, 20.00, 0.00, 0.00, 0.00, 'PayMongo', 'verified', NULL, NULL, 'NH9808567', NULL, 4, NULL, NULL, NULL, NULL, 'cs_mBhQo2TYEodqLjTsxX5KBpRV', 32, '2025-11-21 05:15:45', '2025-11-21 05:21:11', NULL, '2025-11-21 13:44:14', '2025-11-21 05:44:14', '2025-11-21 05:44:14'),
(6, 50, 17, NULL, 9, 'BSIT4107_HIMARANGAN Wendhil', 'wendhil10@gmail.com', '09081031241', 352.00, 0.00, 0.00, 205.26, 42.24, 599.50, 599.50, '128 sitio pajo, quezon city, Metro Manila, Philippines', '1106', 14.6631312, 121.0144921, 3.26, 'delivery', 1, 'Sedan', 0.027, 1.00, 0.00, 0.00, 0.00, 'PayMongo', 'verified', NULL, NULL, 'NH9810810', NULL, 4, NULL, NULL, NULL, NULL, 'cs_6cTnCystFRAyUKxVmhFkRs2i', 36, '2025-11-22 02:47:57', '2025-11-22 02:55:00', NULL, '2025-11-22 11:05:56', '2025-11-22 03:05:56', '2025-11-22 03:05:56'),
(7, 56, 17, NULL, 9, 'BSIT4107_HIMARANGAN Wendhil', 'wendhil10@gmail.com', '09081031241', 2750.00, 0.00, 0.00, 250.24, 330.00, 3330.24, 3330.24, 'Zamboanga Street Nayong Kanluran, Quezon City, Metro Manila, Philippines', '1104', 14.6396825, 121.0231851, 5.51, 'delivery', 1, 'Sedan', 0.116, 125.00, 35.00, 55.00, 12.00, 'PayMongo', 'verified', NULL, NULL, 'NH9849067', NULL, 4, NULL, NULL, NULL, NULL, 'cs_8hfQFwZnii7UnLfmiTF4NN5v', 35, '2026-01-08 23:43:31', '2026-01-08 23:45:42', NULL, '2026-01-09 08:16:05', '2026-01-09 00:16:05', '2026-01-09 00:16:05'),
(8, 7, 16, NULL, 9, 'BSIT 4107-Salvadora, Mark James F.', 'salvadoramarkjamesfrayna@gmail.com', '09562604446', 1.00, 0.00, 0.00, 0.00, 0.12, 1.12, 1.12, 'Old Samson Road Balintawak, Quezon City, Metro Manila, Philippines', '1106', 14.6570037, 121.003376, 0.00, 'delivery', 1, 'Sedan', 0.053, 125.00, 35.00, 25.00, 12.00, 'QR Ph', 'verified', NULL, NULL, 'NH9838852', NULL, 4, NULL, NULL, NULL, NULL, NULL, 38, '2026-02-23 06:50:43', '2026-02-23 06:52:23', NULL, '2026-02-24 08:27:39', '2026-02-24 00:27:39', '2026-02-24 00:27:39');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_addresses`
--

CREATE TABLE `supplier_addresses` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'Philippines',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `address_type` enum('main','warehouse','factory','branch','delivery','other') NOT NULL DEFAULT 'main',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` varchar(100) DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_list`
--

CREATE TABLE `supplier_list` (
  `id` int(11) NOT NULL,
  `business_name` varchar(255) NOT NULL,
  `business_address` text NOT NULL,
  `business_type` enum('Manufacturer','Wholesaler','Distributor','Retailer','Service Provider','Other') NOT NULL,
  `country_region` varchar(100) NOT NULL,
  `primary_contact_name` varchar(255) NOT NULL,
  `job_title` varchar(255) NOT NULL,
  `phone_number` varchar(50) NOT NULL,
  `email_address` varchar(255) NOT NULL,
  `logo_path` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_list`
--

INSERT INTO `supplier_list` (`id`, `business_name`, `business_address`, `business_type`, `country_region`, `primary_contact_name`, `job_title`, `phone_number`, `email_address`, `logo_path`, `status`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 'Wendhil business', 'Wendhil Address', 'Service Provider', 'Philippines / Manila', 'Ace Manganaan', 'Project Manager', '09671677760', 'ace@gmail.com', 'uploads/supplier_logos/supplier_6892a99004727.jpg', 'active', '2025-08-06 01:02:08', '2025-08-06 01:02:08', NULL),
(2, 'Noblehome Construction Corp', '1181 MC Premier Balintawak', 'Other', 'Metro Manila', 'NobleHome depot', 'Sales and Marketing Staff', '09108346508', 'noblehomeconst.ph@gmail.com', 'uploads/supplier_logos/supplier_6896ae39ad69d.png', 'active', '2025-08-09 02:11:05', '2025-08-09 02:11:05', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `supplier_products`
--

CREATE TABLE `supplier_products` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `unit` varchar(100) DEFAULT NULL,
  `specification` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_products`
--

INSERT INTO `supplier_products` (`id`, `supplier_id`, `item_code`, `product_name`, `description`, `category`, `image`, `status`, `created_at`, `updated_at`, `unit`, `specification`) VALUES
(1, 1, 'C12341', 'Mercedes Coffee Table', 'test', 'cabinet', '../uploads/68832e7b74bea.webp', 'active', '2025-07-25 07:12:59', '2025-07-25 07:50:28', 'pcs', ''),
(2, 1, 'C23', 'V-shape low coffee table', 'test', 'cabinet', '../uploads/688330aa0c8da.webp', 'active', '2025-07-25 07:22:18', '2025-07-25 07:22:18', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `supplier_product_variants`
--

CREATE TABLE `supplier_product_variants` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `color` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_product_variants`
--

INSERT INTO `supplier_product_variants` (`id`, `product_id`, `color`, `price`, `image`, `created_at`) VALUES
(2, 2, 'red', 10.00, '../uploads/688330aa62cc2.webp', '2025-07-25 07:22:18'),
(3, 2, 'blue', 20.00, '../uploads/688330aaad25f.webp', '2025-07-25 07:22:19'),
(4, 2, 'white', 30.00, '../uploads/688330ab05d8d.webp', '2025-07-25 07:22:19'),
(5, 2, 'black', 40.00, '../uploads/688330ab5258b.webp', '2025-07-25 07:22:19'),
(7, 1, 'red', 200.00, '../uploads/68833744aef7b.webp', '2025-07-25 07:50:29');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_variant_sizes`
--

CREATE TABLE `supplier_variant_sizes` (
  `id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `size` varchar(50) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_variant_sizes`
--

INSERT INTO `supplier_variant_sizes` (`id`, `variant_id`, `size`, `stock`, `created_at`, `updated_at`) VALUES
(2, 2, 'S', 16, '2025-07-25 07:22:18', '2025-07-25 07:22:18'),
(3, 3, 'M', 7, '2025-07-25 07:22:19', '2025-07-25 07:22:19'),
(4, 4, 'L', 9, '2025-07-25 07:22:19', '2025-07-25 07:22:19'),
(5, 5, 'XL', 12, '2025-07-25 07:22:19', '2025-07-25 07:22:19'),
(7, 7, 'red', 10, '2025-07-25 07:50:29', '2025-07-25 07:50:29');

-- --------------------------------------------------------

--
-- Table structure for table `supp_link_products`
--

CREATE TABLE `supp_link_products` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `supplier_type` enum('primary','secondary') NOT NULL DEFAULT 'secondary',
  `supplier_price` decimal(10,2) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supp_link_products`
--

INSERT INTO `supp_link_products` (`id`, `supplier_id`, `product_id`, `variant_id`, `supplier_type`, `supplier_price`, `status`, `created_at`, `updated_at`) VALUES
(6, 2, 8, NULL, 'secondary', NULL, 'active', '2025-08-12 09:48:40', '2025-08-12 09:48:40'),
(11, 1, 8, NULL, 'primary', NULL, 'active', '2025-09-19 15:37:18', '2025-09-19 15:37:18'),
(12, 1, 19, NULL, 'primary', NULL, 'active', '2025-09-19 15:37:19', '2025-09-19 15:37:19'),
(29, 2, 19, NULL, 'secondary', NULL, 'active', '2025-09-19 15:38:32', '2025-09-19 15:38:32'),
(36, 2, 28, NULL, 'primary', NULL, 'active', '2025-11-08 08:04:35', '2025-11-08 08:04:35'),
(37, 2, 23, NULL, 'secondary', NULL, 'active', '2025-11-08 08:04:52', '2025-11-08 08:04:52'),
(38, 1, 23, NULL, 'primary', 131.00, 'active', '2025-11-13 08:31:07', '2025-11-13 08:31:07'),
(39, 1, 29, NULL, 'primary', 27.00, 'active', '2025-11-13 08:33:11', '2025-11-13 08:33:11'),
(41, 2, 28, 75, 'primary', 550.00, 'active', '2025-11-21 13:30:16', '2025-11-21 13:30:16'),
(42, 2, 29, 76, 'primary', 27.50, 'active', '2025-11-21 13:31:04', '2025-11-21 13:31:46');

-- --------------------------------------------------------

--
-- Table structure for table `tiercard`
--

CREATE TABLE `tiercard` (
  `id` int(11) NOT NULL,
  `card_name` varchar(100) NOT NULL,
  `card_discount` decimal(5,2) NOT NULL DEFAULT 0.00,
  `card_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tiercard`
--

INSERT INTO `tiercard` (`id`, `card_name`, `card_discount`, `card_image`, `created_at`) VALUES
(1, 'silver', 2.00, '1758782132_3.png', '2025-09-25 06:35:32'),
(2, 'gold', 5.00, '1758782139_1.png', '2025-09-25 06:35:39'),
(3, 'platinum', 10.00, '1758782149_2.png', '2025-09-25 06:35:49');

-- --------------------------------------------------------

--
-- Table structure for table `transportify_vehicle_list`
--

CREATE TABLE `transportify_vehicle_list` (
  `id` int(11) NOT NULL,
  `courier_name` varchar(255) NOT NULL DEFAULT 'Default Courier',
  `vehicle_type` varchar(100) NOT NULL,
  `vehicle_variant` varchar(100) DEFAULT NULL,
  `vehicle_description` text DEFAULT NULL,
  `base_fare` decimal(10,2) NOT NULL,
  `add_per_km` decimal(10,2) NOT NULL,
  `per_km_rate` decimal(10,2) NOT NULL DEFAULT 1.00 COMMENT 'KM where per km rate starts (1 or 40)',
  `length` decimal(10,2) DEFAULT NULL COMMENT 'Length in feet',
  `width` decimal(10,2) DEFAULT NULL COMMENT 'Width in feet',
  `height` decimal(10,2) DEFAULT NULL COMMENT 'Height in feet',
  `max_cubic_meter` decimal(10,2) DEFAULT NULL COMMENT 'Maximum cubic meter capacity',
  `max_weight_capacity` decimal(10,2) DEFAULT NULL COMMENT 'Maximum weight capacity in kg',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transportify_vehicle_list`
--

INSERT INTO `transportify_vehicle_list` (`id`, `courier_name`, `vehicle_type`, `vehicle_variant`, `vehicle_description`, `base_fare`, `add_per_km`, `per_km_rate`, `length`, `width`, `height`, `max_cubic_meter`, `max_weight_capacity`, `created_at`, `updated_at`) VALUES
(1, 'Default Courier', 'Sedan', 'Sedan', 'Small Vehicle', 0.00, 0.00, 0.00, 3.50, 2.00, 2.50, 0.50, 200.00, '2025-10-08 23:40:47', '2026-02-23 06:49:33'),
(2, 'Default Courier', 'MPV/SUV', 'MPV/SUV', 'description here', 160.00, 120.00, 0.00, 5.00, 3.20, 2.80, 1.27, 300.00, '2025-10-08 23:48:18', '2025-10-14 06:11:32'),
(3, 'Default Courier', 'Light Van', 'Light Van', 'Description Here', 250.00, 25.00, 0.00, 5.50, 3.80, 3.80, 2.25, 600.00, '2025-10-08 23:50:08', '2025-10-14 06:11:35'),
(4, 'Default Courier', 'L300/Van', 'Regular L300/FB', 'Description Here', 430.00, 27.00, 0.00, 8.00, 4.50, 4.50, 4.59, 1000.00, '2025-10-08 23:51:50', '2025-10-14 06:11:36'),
(5, 'Default Courier', 'L300/Van', 'Long/H100', 'Description', 713.00, 27.00, 0.00, 10.00, 4.50, 4.50, 5.73, 1000.00, '2025-10-08 23:53:10', '2025-10-14 06:11:42'),
(6, 'Default Courier', 'Closed Van', 'Closed Van', 'Description', 1800.00, 45.00, 0.00, 10.00, 6.00, 6.00, 10.19, 2000.00, '2025-10-08 23:55:51', '2025-10-14 06:11:45'),
(7, 'Default Courier', 'Closed Van', 'Closed Van', 'Description', 2400.00, 45.00, 0.00, 12.00, 6.00, 6.00, 12.23, 3000.00, '2025-10-08 23:56:46', '2025-10-14 06:11:49'),
(8, 'Default Courier', 'Closed Van', 'Closed Van', 'Description', 2700.00, 48.00, 0.00, 14.00, 6.00, 6.00, 14.27, 4000.00, '2025-10-08 23:57:51', '2025-10-14 06:11:52'),
(9, 'Default Courier', 'Open Truck', 'Regular 10ft and 14ft (2000kg)', 'Description', 2300.00, 50.00, 0.00, 10.00, 6.00, NULL, NULL, 2000.00, '2025-10-08 23:59:29', '2025-10-14 06:11:56'),
(10, 'Default Courier', 'Open Truck', 'Regular 10ft and 14ft (2000kg)', 'Description', 2300.00, 50.00, 0.00, 14.00, 6.00, NULL, NULL, 2000.00, '2025-10-09 00:00:46', '2025-10-14 06:12:03'),
(11, 'Default Courier', 'Open Truck', '18ft and 21ft (7000kg)', 'Description', 4850.00, 70.00, 0.00, 18.00, 6.00, NULL, NULL, 7000.00, '2025-10-09 00:03:04', '2025-10-14 06:12:01'),
(12, 'Default Courier', 'Open Truck', '18ft and 21ft (7000kg)', NULL, 5350.00, 70.00, 0.00, 21.00, 6.00, NULL, NULL, 7000.00, '2025-10-09 00:57:50', '2025-10-14 06:12:08'),
(13, 'Default Courier', '6w Fwd Truck', 'Regular 6w Fwd Truck', 'Description', 4850.00, 50.00, 0.00, 18.00, 6.00, 7.00, 21.41, 7000.00, '2025-10-09 01:19:38', '2025-10-14 06:12:11'),
(14, 'Default Courier', '6w Fwd Truck', 'Wing-Van-Type', 'Description', 4850.00, 50.00, 0.00, 18.00, 6.00, 7.00, 21.41, 7000.00, '2025-10-09 01:23:34', '2025-10-14 06:12:13'),
(15, 'Default Courier', 'Wing Van', 'Wing Van', 'Description', 7000.00, 85.00, 40.00, 32.00, 7.80, 7.80, 55.13, 12000.00, '2025-10-09 02:23:01', '2025-10-09 02:25:47'),
(16, 'Default Courier', 'Wing Van', 'Wing Van', 'Description', 7400.00, 85.00, 40.00, 34.00, 7.80, 7.80, 58.58, 15000.00, '2025-10-09 02:25:20', '2025-10-09 02:25:20'),
(17, 'Default Courier', 'Wing Van', 'Wing Van', 'Description', 9150.00, 85.00, 60.00, 36.00, 7.80, 7.80, 62.02, 20000.00, '2025-10-09 02:27:28', '2025-10-09 02:27:28'),
(18, 'Default Courier', 'Wing Van', 'Wing Van', 'Description', 11200.00, 140.00, 40.00, 38.00, 7.80, 7.80, 65.47, 25000.00, '2025-10-09 02:28:50', '2025-10-09 02:28:50'),
(19, 'Default Courier', 'Wing Van', 'Wing Van', 'Description', 11700.00, 140.00, 40.00, 40.00, 7.80, 7.80, 68.91, 28000.00, '2025-10-09 02:30:41', '2025-10-09 02:30:41');

-- --------------------------------------------------------

--
-- Table structure for table `truck_schedules`
--

CREATE TABLE `truck_schedules` (
  `id` int(11) NOT NULL,
  `truck_id` varchar(50) NOT NULL,
  `scheduled_date` date NOT NULL,
  `max_capacity` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `assigned_driver_id` int(11) DEFAULT NULL COMMENT 'ID of assigned driver for this truck schedule',
  `status` enum('scheduled','active','completed') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `referred_by_code` varchar(20) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `login_method` enum('normal','google') DEFAULT 'normal',
  `otp_code` varchar(10) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `tiercard_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `mobile`, `password`, `remember_token`, `referred_by_code`, `google_id`, `profile_picture`, `created_at`, `login_method`, `otp_code`, `otp_expires_at`, `reset_token`, `reset_token_expires`, `tiercard_id`) VALUES
(2, 'NHCC Marketing', 'nhccmarketing2025@gmail.com', '9089769868', '', '24fdbaa1df3e9ac4a87c7123649ea406', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocJkXcYcX3U1ZN0qyZMXNwZx9SIFqqbr9N9tS69l6BZQMp-s9rs=s100', '2025-08-13 03:34:36', 'google', NULL, NULL, NULL, NULL, NULL),
(3, 'NobleHome', 'noblehomeconst.ph@gmail.com', '09108346508', '', 'd1de071477d1027bd708d2113d5fec0f', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocI87pIH0mp9P5kPb4TiRmgxeCNySNMxUgG824BhTQzhygRMFgvi=s100', '2025-08-15 03:35:05', 'google', NULL, NULL, NULL, NULL, NULL),
(6, 'blue dragon', 'bdragon2202@gmail.com', '9153564574', '', 'bc15649432c001dfa0ec8887c196a099', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocKLgFFsvvEuITaHo2fuNLaybjnc5MBw0y4CZ8ArtJ1zrVmK2Q=s100', '2025-08-26 05:28:08', 'google', NULL, NULL, NULL, NULL, NULL),
(7, 'Lion King', 'lking0641@gmail.com', '09671677760', '', 'b8922904cc15b5a5027de929eef060c2', 'NH-XY2E7N', NULL, 'https://lh3.googleusercontent.com/a/ACg8ocLtyc1-AX7qlYkc513xZvpMaOYobEt9zq6nLRUCgs4A-Bv4JjI6=s100', '2025-08-26 23:37:33', 'google', NULL, NULL, NULL, NULL, NULL),
(8, 'Jb Sy', 'onetask.1995@gmail.com', '9851245929', '', '8ffcf8219070d0635f56f67620e5a52a', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocJp2M8bHSZvLAAaPYm7XmENlIqi1xhxkLIN1fch9O5ttzg2tE5M=s100', '2025-09-02 23:26:35', 'google', NULL, NULL, NULL, NULL, NULL),
(9, 'Froilan Linga Bawag', 'bawagfroilan81@gmail.com', '9155919182', '', '14902cbf3d97e09ac62dd0e6be4727a1', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocINMvpfoHKxSCh2rFlxqTZYmMN-lZdPO-SVFoP-w-J-XrhD-TA=s100', '2025-09-03 23:31:57', 'google', NULL, NULL, NULL, NULL, NULL),
(10, 'Christine', 'joydelarosa030@gmail.com', '9935487799', '$2y$10$i5m1p381uU1o/10gnA0hXe1SiXy3aYDEWrJR4PvHmW3nDn0HTUAJy', NULL, NULL, NULL, NULL, '2025-09-06 01:44:50', 'normal', NULL, NULL, NULL, NULL, NULL),
(11, 'Mary Grace Rivera', 'marygracerivera854@gmail.com', '9382041746', '', 'f3744cb939b6df65eed5a1ea95b4eb1c', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocJs5qhWhZ_Cpwheh0n3Y-s4Pph6wPSJLzowSkh4FPDcjS0JJdOD=s100', '2025-09-08 08:06:46', 'google', NULL, NULL, NULL, NULL, NULL),
(12, 'Kahel Macasero', 'macaserokahel@gmail.com', NULL, '', '43b42196647875776016fb85e9992665', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocJvXQU1ClkYjTeH3yMGwEvax--_bWQCTB58ejovmI9GKnXqSzs=s100', '2025-09-11 06:07:16', 'google', NULL, NULL, NULL, NULL, NULL),
(13, 'Raphael.100Folds', 'raphael.100folds@gmail.com', NULL, '', '7c44571d94edd9b3778a8c8b0d466df3', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocI_xBHbVOp3djjPPrAo3h9QrEdSAdROiyWrn4SzKis4BRl_U80=s100', '2025-09-11 06:42:08', 'google', NULL, NULL, NULL, NULL, NULL),
(15, 'Wendhil Himarangan', 'narutowendhil@gmail.com', NULL, '', '58e8b4759f862c56725a0b48b0675c01', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocKHM-gbO3n6AkHZlBx5HSKPNH1G4v7F08xmPJ9OenCA_JbVzw=s100', '2025-09-16 06:08:08', 'google', NULL, NULL, NULL, NULL, NULL),
(16, 'BSIT 4107-Salvadora, Mark James F.', 'salvadoramarkjamesfrayna@gmail.com', '9671677760', '', 'd88fffb1f72fe279914f26d71013c03e', 'NH-9JEMN5', NULL, 'https://lh3.googleusercontent.com/a/ACg8ocJjQWeZg-XSPbJPEMmbFzlB4uBfUEFNRqO7hIZlL5RH-tYKkv4=s100', '2025-09-16 06:11:53', 'google', NULL, NULL, NULL, NULL, NULL),
(17, 'BSIT4107_HIMARANGAN Wendhil', 'wendhil10@gmail.com', '9081031241', '', '7694dce73fe37b81594e4281a03c3bfc', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocI2Z5alnJRXOeXSDMPl0wC3YwMvf3kYEBay_FyArpeln17pAOO4=s100', '2025-09-16 06:41:18', 'google', NULL, NULL, NULL, NULL, NULL),
(18, 'Angelica May G. Busa', 'angelicamayguirina@gmail.com', NULL, '', 'b2bca45afdafebb2664e1c2d011cc0e2', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocL7LYnUsZxFq7LWZUfanmjlA_6Th4ooOv312MEtW1nR9AZHg_U=s100', '2025-09-19 05:04:02', 'google', NULL, NULL, NULL, NULL, NULL),
(19, 'Earl Rizo', 'earlrizo10@gmail.com', NULL, '', 'cbe03e4fa8ae704d4d99ff112f6633ec', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocK2WvzX5zg8znnHqy4i1Gw-5Xws4z2tFL8n9coGpCzz2olBZA=s100', '2025-09-23 02:58:40', 'google', NULL, NULL, NULL, NULL, NULL),
(20, 'Martin Gomez', 'onetask.jb@gmail.com', '09851245929', '$2y$10$nhbjptOhGPlgTXnoUZX3t.FdfmHCjJhqKpFwmoym6YNuICQIs2Mzu', NULL, NULL, NULL, NULL, '2025-09-30 06:32:08', 'normal', '912066', '2025-09-30 06:37:23', NULL, NULL, NULL),
(21, 'Martin Gomez', 'clarencejulian229@gmail.com', '9625604775', '$2y$10$BHD/aZjU0hRJtVAmdGR4ouggkK.Hqxku2nY/z7C.MM2zk04X5ZHEu', NULL, NULL, NULL, NULL, '2025-09-30 06:38:18', 'normal', NULL, NULL, NULL, NULL, NULL),
(22, 'HR Cherry', 'hradnoblehomeconst@gmail.com', '9123456789', '', 'dfbdb404ab003808af778a6e9c879551', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocIMByvhZoumqbhxJcdKmzb3faqGSnUYYj3Sue4VXGsKrp1CgQ=s100', '2025-10-03 08:42:43', 'google', NULL, NULL, NULL, NULL, NULL),
(23, 'marjorie gime', 'marjoriegime10@gmail.com', NULL, '', '74829e750c81cfc2303079f13e5eb4c1', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocL488Wmlg3_BaDl8Qt2qIsXP2xVOFdiJBru2j2M0Ue4mGKuSy0=s100', '2025-10-03 08:43:47', 'google', NULL, NULL, NULL, NULL, NULL),
(24, 'jolina jean epa', 'epajolinajean@gmail.com', NULL, '', 'fba181b11e7c862a5152e7b775372a7d', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocK8tgxa15qVNghVSIvEQ0qgSMp2D0J1tgSAyuGmeyiBCcqHrH4j=s100', '2025-10-03 08:46:15', 'google', NULL, NULL, NULL, NULL, NULL),
(25, 'Ken Yang', 'mingkun3084@gmail.com', NULL, '', '97507f12720d1b93877f626405029a68', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocJL87c81cUMOJBakhBuIzt5voox2AgpngOPEAJ47VBLxTbWQg=s100', '2025-10-07 05:05:59', 'google', NULL, NULL, NULL, NULL, NULL),
(26, 'Superadmin', 'superadmin@gmail.com', NULL, '$2y$10$5VEKKBtbafoulFQs0Q1Ks.Z2WzGeULQzmiDHfL7o4OOtK.2qa.aQK', NULL, NULL, NULL, NULL, '2025-10-07 05:10:29', '', '289312', '2025-10-07 05:15:29', NULL, NULL, NULL),
(27, 'Mingkung3084', 'mingkung3084@gmail.com', NULL, '$2y$10$G0EYTYhgC2fT/P13sOICxeEoM2v.uXqmAx0m9Eqagz6/z4cnnNAMS', NULL, NULL, NULL, NULL, '2025-10-07 05:14:28', '', '886768', '2025-10-07 05:19:57', NULL, NULL, NULL),
(28, 'Ken_rl', 'ken_rl@hotmail.com', NULL, '$2y$10$aV6No78O9LnKEr/t5Rg73.64xVfBwqiSeXO3wzRE2FyD9Za2RLErm', NULL, NULL, NULL, NULL, '2025-10-07 05:20:35', '', NULL, NULL, NULL, NULL, NULL),
(29, 'Mary Grace Rivera', 'misschingrivera@icloud.com', '09382041746', '$2y$10$VRPT5FHIrQj/G3CGMLyFtOpnWIafjksS0TikOizyzh0.kYVEDhsUG', NULL, NULL, NULL, NULL, '2025-10-10 07:32:46', 'normal', NULL, NULL, NULL, NULL, NULL),
(31, 'Wenswens Himars', 'wenswenshimars@gmail.com', '9487455744', '', '3f481c1edefc4c9afb12adf31217436f', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocIYVHx4_o9AoP7L6piPmiQPyoQZA_QtstzE-61Jkq_7yK_LVw=s100', '2025-10-11 03:21:08', 'google', NULL, NULL, NULL, NULL, NULL),
(32, 'Wendhil10', 'wendhil10@gmail.co', NULL, '$2y$10$vdzIR929poqqQl6nnt0w1uDTzrEPSF9ESaPdKs01mBgCihH0htAu.', NULL, NULL, NULL, NULL, '2025-10-20 03:58:02', '', '264919', '2025-10-20 04:03:02', NULL, NULL, NULL),
(33, 'Maria Himarangan', 'shuppedennarow@gmail.com', NULL, '', 'aa00518f8436f19574c9200b287b3638', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocIs3xkz6T7c8maYg0K6T8RrZJCfecrxWiNIvZa01VbPc280pQ=s100', '2025-11-03 03:41:47', 'google', NULL, NULL, NULL, NULL, NULL),
(34, 'test123123', 'james.jhon@gmail.com', '09123456782', '$2y$10$1RjXdst7/NbdtpKA4D2wBu3OMcvmWKSjmFy0FW11fEog3LK1zUjRy', NULL, NULL, NULL, NULL, '2025-11-23 05:05:14', 'normal', NULL, NULL, NULL, NULL, NULL),
(35, 'Adriano, Hazel Anne', 'hazeladriano725@gmail.com', NULL, '', '5d959062de0cfe4aee886ff806b0e788', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocJSjiubnYgCaftJ3NWnSBea82Z7VuypXVA0II-Z4WO5wTeYvnpF8A=s100', '2025-12-15 05:59:21', 'google', NULL, NULL, NULL, NULL, NULL),
(36, 'Wendhil Himarangan', 'wendhil08@gmail.com', NULL, '', 'd4fcbc913a48bba6ae1aaab484d8c247', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocIOV2Cihn81EotKC_bxVWuuLay2iemVpyv5QYC-tpx01Q3Fag=s100', '2026-01-28 00:37:45', 'google', NULL, NULL, NULL, NULL, NULL),
(37, 'Wendhil Himarangan', 'wendhil09@gmail.com', NULL, '', '240b03f5fc6004e69858b8830319d1ad', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocImZYQqyQO2XwDKxkl86Fqw7yi4c5LCQa3NaznSRZ4Doku02CQ=s100', '2026-01-28 00:48:26', 'google', NULL, NULL, NULL, NULL, NULL),
(38, 'kelly llaneta', 'kellyllaneta01@gmail.com', '09535375146', '', '93a6bffa6f3146b492860a1b57695679', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocLQeolgrK0fivQpbhlz4WcMz_Yx5qMCpoU0XliLwHVbjI0nAD0a=s100', '2026-02-19 04:57:14', 'google', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_cart_items`
--

CREATE TABLE `user_cart_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `color_id` int(11) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `type_name` varchar(100) DEFAULT NULL,
  `variant_name` varchar(100) DEFAULT NULL,
  `color_name` varchar(100) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `codename` varchar(100) DEFAULT NULL,
  `descrip6` text DEFAULT NULL,
  `descrip7` text DEFAULT NULL,
  `added_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_cart_items`
--

INSERT INTO `user_cart_items` (`id`, `user_id`, `product_id`, `color_id`, `variant_id`, `quantity`, `price`, `type_name`, `variant_name`, `color_name`, `size`, `codename`, `descrip6`, `descrip7`, `added_at`) VALUES
(9, 7, 24, 226, 55, 1, 880.00, 'Fiber Cement Board ', 'Fiber Cement Board', 'Normal', '2440*1200*12mm', 'buildingmaterials', '', '', '2025-10-08 03:17:09'),
(24, 31, 23, 225, 32, 1, 131.00, 'AAC Blocks', 'AAC Blocks', 'Normal', '600x200x100 750 PSI', 'AacBlock', '', '', '2025-10-11 03:27:21'),
(83, 3, 28, 273, 75, 1, 0.20, 'AAC ADHESIVES', 'AAC ADHESIVES', 'Normal', '25kg', 'AacBlock', '', '', '2026-02-18 23:11:14'),
(103, 17, 28, 273, 75, 5, 0.20, 'AAC ADHESIVES', 'AAC ADHESIVES', 'Normal', '25kg', 'AacBlock', '', '', '2026-02-21 02:57:58');

-- --------------------------------------------------------

--
-- Table structure for table `user_details`
--

CREATE TABLE `user_details` (
  `detail_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sex` enum('male','female','other') DEFAULT NULL,
  `birthplace` varchar(255) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `id_type` varchar(50) DEFAULT NULL,
  `government_id_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_details`
--

INSERT INTO `user_details` (`detail_id`, `user_id`, `sex`, `birthplace`, `birthdate`, `occupation`, `is_verified`, `id_type`, `government_id_path`) VALUES
(3, 3, 'male', 'manila', '2002-11-11', 'web', 1, NULL, NULL),
(4, 7, 'male', 'Manila', '2025-08-22', 'International Tambay', 1, 'pwd_id', 'gov_id_7_1756710482.jpg'),
(5, 8, 'other', 'SA MAMA KO', '2000-02-02', 'kargador ', 1, 'philhealth_id', 'gov_id_8_1756855839.png'),
(6, 9, 'male', 'QC', '2025-09-03', 'construction worker', 1, 'drivers_license', 'gov_id_9_1756942430.jpg'),
(7, 10, 'female', 'Bulacan', '1992-04-30', 'sales', 1, 'passport', 'gov_id_10_1757123244.jpg'),
(9, 11, 'other', 'PASAY CITY', '2001-07-05', 'ACCOUNTANT', 1, 'tin_id', 'gov_id_11_1757318954.png'),
(10, 6, 'male', 'kahit saan', '2025-09-16', 'International Tambay', 1, 'passport', 'gov_id_6_1758002897.jpg'),
(11, 16, 'male', 'Calbayog City, Samar', '2025-09-16', 'Software Dinevelop', 1, 'postal_id', 'gov_id_16_1758003151.jpg'),
(12, 17, 'male', 'southern leyte bontoc', '2002-11-11', 'web developer', 1, 'pwd_id', 'gov_id_17_1758004936.jpg'),
(13, 2, 'male', 'southern leyte bontoc', '2002-11-11', 'web developer', 1, 'national_id', 'gov_id_2_1758588355.jpg'),
(14, 21, 'male', 'balintawak', '2000-02-02', 'DPWH', 1, 'passport', 'gov_id_21_1759214415.png'),
(15, 22, 'female', 'new york', '1999-09-26', 'freelance', 0, 'passport', 'gov_id_22_1759481654.jpg'),
(16, 31, 'male', 'wetewteg', '2025-10-11', 'gsdagdag', 1, 'national_id', 'gov_id_31_1760153132.jpg'),
(17, 38, 'female', 'trece marteris city', '2001-06-21', 'architect', 1, 'Passport', 'gov_id_38_1771477111.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `user_feedback`
--

CREATE TABLE `user_feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_feedback`
--

INSERT INTO `user_feedback` (`id`, `user_id`, `author_id`, `rating`, `comment`, `created_at`) VALUES
(2, 3, 3, 4, 'not finish improve some page', '2025-09-06 01:36:38'),
(3, 8, 8, 1, 'DECLINE KASI AKO SA VERIFY', '2025-10-16 07:24:40');

-- --------------------------------------------------------

--
-- Table structure for table `variants`
--

CREATE TABLE `variants` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `variant_tracking`
--

CREATE TABLE `variant_tracking` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `variant_id` int(11) NOT NULL,
  `place` varchar(255) NOT NULL,
  `driver_name` varchar(100) DEFAULT NULL,
  `truck_plate` varchar(50) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `order_item_id` int(11) DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_address` text DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `variant_color` varchar(100) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `vat_amount` decimal(10,2) DEFAULT NULL,
  `shipping_fee` decimal(10,2) DEFAULT NULL,
  `delivery_fee` decimal(10,2) DEFAULT NULL,
  `final_total` decimal(10,2) DEFAULT NULL,
  `mode_payment` varchar(50) DEFAULT NULL,
  `description1` text DEFAULT NULL,
  `description2` text DEFAULT NULL,
  `status` varchar(100) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_list`
--

CREATE TABLE `vehicle_list` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `truck_type` enum('6-wheeler','10-wheeler','van','closed truck','trailer truck','mini truck','delivery van','refrigerated truck','flatbed truck','other') NOT NULL,
  `make` varchar(100) NOT NULL,
  `model` varchar(100) NOT NULL,
  `year` year(4) NOT NULL,
  `weight_capacity` decimal(10,2) DEFAULT NULL COMMENT 'Weight capacity in tons',
  `volume_capacity` decimal(10,2) DEFAULT NULL COMMENT 'Volume capacity in cubic meters',
  `capacity_unit_weight` enum('tons','kg','lbs') DEFAULT 'tons',
  `capacity_unit_volume` enum('cubic meters','cubic feet') DEFAULT 'cubic meters',
  `truck_identification_number` varchar(100) DEFAULT NULL COMMENT 'Fleet ID or Body Number',
  `vin_number` varchar(50) DEFAULT NULL COMMENT 'Vehicle Identification Number',
  `registration_number` varchar(50) NOT NULL COMMENT 'OR/CR Number',
  `registration_expiration_date` date NOT NULL,
  `insurance_provider` varchar(200) DEFAULT NULL,
  `insurance_policy_number` varchar(100) DEFAULT NULL,
  `insurance_expiration_date` date DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `fuel_type` enum('gasoline','diesel','hybrid','electric','other') DEFAULT 'diesel',
  `status` enum('active','maintenance','out_of_service','retired') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `unavailable_days` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_list`
--

INSERT INTO `vehicle_list` (`id`, `plate_number`, `truck_type`, `make`, `model`, `year`, `weight_capacity`, `volume_capacity`, `capacity_unit_weight`, `capacity_unit_volume`, `truck_identification_number`, `vin_number`, `registration_number`, `registration_expiration_date`, `insurance_provider`, `insurance_policy_number`, `insurance_expiration_date`, `photo_path`, `fuel_type`, `status`, `notes`, `created_at`, `updated_at`, `unavailable_days`) VALUES
(2, 'AFAGFA-56447', '6-wheeler', 'Mercedez Bench', 'Rayuma', '2000', 5.00, 25.00, 'tons', 'cubic meters', '44564454', '1577', '326554544564', '2025-08-26', 'Wendhil', '456445', '2025-08-26', '../../uploads/truck_photo_collection/truck_1756168499_3907.jpg', 'gasoline', 'active', 'ajoigsgfieqgeagh', '2025-08-26 00:34:59', '2025-08-26 00:34:59', 'wednesday'),
(3, 'abc-5858', 'delivery van', 'Mercedez Bench', 'Rayuma', '2021', 5.00, 25.00, 'tons', 'cubic meters', '44564454', '1577', '326554544564', '2025-08-26', 'Wendhil', '456445', '2025-08-26', '../../uploads/truck_photo_collection/truck_1756168721_6414.jpg', 'hybrid', 'active', 'jkxAJdasjhsf', '2025-08-26 00:38:41', '2025-08-26 00:38:41', 'monday');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accountantrecord`
--
ALTER TABLE `accountantrecord`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `adminsuppliers`
--
ALTER TABLE `adminsuppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_inspiration`
--
ALTER TABLE `admin_inspiration`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_target_admin` (`target_admin_id`),
  ADD KEY `idx_target_role` (`target_role`);

--
-- Indexes for table `admin_notification_actions_log`
--
ALTER TABLE `admin_notification_actions_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_history_id` (`notification_history_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `admin_notification_history`
--
ALTER TABLE `admin_notification_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification_id` (`notification_id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_type` (`notification_type`);

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_order_item_id` (`order_item_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `bestseller`
--
ALTER TABLE `bestseller`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `fk_product_bestseller` (`product_id`);

--
-- Indexes for table `bestsellertwo`
--
ALTER TABLE `bestsellertwo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bestseller_id` (`bestseller_id`);

--
-- Indexes for table `billing_addresses`
--
ALTER TABLE `billing_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_country` (`country`),
  ADD KEY `idx_city` (`city`),
  ADD KEY `idx_state` (`state`);

--
-- Indexes for table `blocks`
--
ALTER TABLE `blocks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categorysub`
--
ALTER TABLE `categorysub`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chat_sender_user_time` (`sender_user_id`,`created_at`),
  ADD KEY `idx_chat_receiver_user_time` (`receiver_user_id`,`created_at`),
  ADD KEY `idx_chat_sender_noble_time` (`sender_noble_id`,`created_at`),
  ADD KEY `idx_chat_receiver_noble_time` (`receiver_noble_id`,`created_at`),
  ADD KEY `idx_chat_unread` (`is_read`,`receiver_user_id`,`receiver_noble_id`),
  ADD KEY `idx_chat_user_to_noble` (`sender_user_id`,`receiver_noble_id`,`created_at`),
  ADD KEY `idx_chat_noble_to_user` (`sender_noble_id`,`receiver_user_id`,`created_at`),
  ADD KEY `fk_sales` (`sales_id`);

--
-- Indexes for table `client_info`
--
ALTER TABLE `client_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `commission_claims`
--
ALTER TABLE `commission_claims`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sales_user` (`sales_user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_referral_code` (`referral_code`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sales_user_id` (`sales_user_id`);

--
-- Indexes for table `company_logos`
--
ALTER TABLE `company_logos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `custom_quote_replies`
--
ALTER TABLE `custom_quote_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `custom_quote_requests`
--
ALTER TABLE `custom_quote_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `defect_reports`
--
ALTER TABLE `defect_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_item_id` (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `reported_by` (`reported_by`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `delivery_bookings`
--
ALTER TABLE `delivery_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_delivery_schedule_id` (`delivery_schedule_id`),
  ADD KEY `idx_vehicle_id` (`vehicle_id`),
  ADD KEY `idx_tracking_number` (`tracking_number`),
  ADD KEY `idx_booking_type` (`booking_type`),
  ADD KEY `idx_booking_status` (`booking_status`),
  ADD KEY `idx_dispatcher` (`dispatcher_id`);

--
-- Indexes for table `delivery_logs`
--
ALTER TABLE `delivery_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_delivery_id` (`delivery_id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_delivery_action` (`delivery_id`,`action_type`),
  ADD KEY `idx_order_action` (`order_id`,`action_type`);

--
-- Indexes for table `delivery_reschedule_log`
--
ALTER TABLE `delivery_reschedule_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_delivery_schedule` (`delivery_schedule_id`),
  ADD KEY `idx_order` (`order_id`);

--
-- Indexes for table `delivery_schedules`
--
ALTER TABLE `delivery_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_item_id` (`item_id`),
  ADD KEY `idx_delivery_date` (`delivery_date`),
  ADD KEY `idx_delivery_date_time` (`delivery_date`,`delivery_time`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_delivery_type` (`delivery_type`),
  ADD KEY `idx_delivery_status` (`delivery_status`);

--
-- Indexes for table `delivery_settings`
--
ALTER TABLE `delivery_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `delivery_sizes`
--
ALTER TABLE `delivery_sizes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `size_name` (`size_name`);

--
-- Indexes for table `delivery_zones`
--
ALTER TABLE `delivery_zones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `discount_images`
--
ALTER TABLE `discount_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `driver_list`
--
ALTER TABLE `driver_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plate_number` (`plate_number`),
  ADD KEY `idx_government_id` (`government_id_number`),
  ADD KEY `idx_license_expiration` (`license_expiration_date`),
  ADD KEY `idx_full_name` (`first_name`,`last_name`);

--
-- Indexes for table `employeaccountreport`
--
ALTER TABLE `employeaccountreport`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `employee_tasks`
--
ALTER TABLE `employee_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `images`
--
ALTER TABLE `images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `variant_id` (`variant_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `nobleaccount`
--
ALTER TABLE `nobleaccount`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_id` (`supplier_id`),
  ADD UNIQUE KEY `sales_id` (`sales_id`),
  ADD KEY `idx_department_head` (`lvl`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `actor_id` (`actor_id`);

--
-- Indexes for table `onsalebanner`
--
ALTER TABLE `onsalebanner`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_orders_user` (`user_id`),
  ADD KEY `billing_address_id` (`billing_address_id`),
  ADD KEY `fk_orders_employee` (`emp_id`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_verified_by` (`verified_by`),
  ADD KEY `idx_rejected_by` (`rejected_by`),
  ADD KEY `idx_warehouse_employee` (`warehouse_employee_id`),
  ADD KEY `idx_assigned_vehicle` (`assigned_vehicle_id`),
  ADD KEY `idx_delivery_type` (`delivery_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_referral_code` (`sales_referral_code`),
  ADD KEY `idx_referral_user` (`legacy_referral_user_id`),
  ADD KEY `idx_sales_user` (`sales_user_id`),
  ADD KEY `idx_payment_method` (`mode_payment`);

--
-- Indexes for table `order_feedback`
--
ALTER TABLE `order_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_order_feedback` (`order_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `fk_order_items_product_id` (`product_id`),
  ADD KEY `idx_order_items_manual_supplier` (`manual_supplier_name`),
  ADD KEY `idx_tracking_status` (`tracking_status`),
  ADD KEY `idx_received_status` (`received_status`);

--
-- Indexes for table `payment_qr_codes`
--
ALTER TABLE `payment_qr_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_method` (`payment_method`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_display_order` (`display_order`),
  ADD KEY `fk_created_by` (`created_by`),
  ADD KEY `fk_updated_by` (`updated_by`);

--
-- Indexes for table `po_attachments`
--
ALTER TABLE `po_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_supplier` (`supplier_name`),
  ADD KEY `idx_superadmin_approval` (`superadmin_approval_status`);

--
-- Indexes for table `po_receiver_assignments`
--
ALTER TABLE `po_receiver_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_attachment_id` (`po_attachment_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_receiver` (`receiver_id`,`status`),
  ADD KEY `idx_po_number` (`po_number`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `po_status_logs`
--
ALTER TABLE `po_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_view_count` (`view_count`),
  ADD KEY `idx_unique_view_count` (`unique_view_count`),
  ADD KEY `product_subcategory_id` (`product_subcategory_id`);
ALTER TABLE `products` ADD FULLTEXT KEY `product_name` (`product_name`,`description`,`codename`);

--
-- Indexes for table `product_colors`
--
ALTER TABLE `product_colors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_image2` (`image2`);

--
-- Indexes for table `product_ratings`
--
ALTER TABLE `product_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_id` (`product_id`,`user_id`),
  ADD KEY `idx_user_ratings` (`user_id`),
  ADD KEY `idx_product_ratings` (`product_id`),
  ADD KEY `idx_rating_value` (`rating`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `product_subcategories`
--
ALTER TABLE `product_subcategories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_subcategory_category` (`category_id`);

--
-- Indexes for table `product_sub_subcategories`
--
ALTER TABLE `product_sub_subcategories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sub_subslug` (`sub_subcategory_slug`),
  ADD KEY `fk_subsubcat_subcat` (`subcategory_id`);

--
-- Indexes for table `product_sub_subcategory_links`
--
ALTER TABLE `product_sub_subcategory_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_link` (`product_id`,`sub_subcategory_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_sub_subcategory_id` (`sub_subcategory_id`);

--
-- Indexes for table `product_tiers`
--
ALTER TABLE `product_tiers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `min_amount` (`min_quantity`);

--
-- Indexes for table `product_types`
--
ALTER TABLE `product_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_variant_combo` (`type_id`,`color`,`size`),
  ADD UNIQUE KEY `unique_variant_combination` (`type_id`,`color`,`size`,`namevariant`),
  ADD KEY `fk_product_id` (`product_id`),
  ADD KEY `idx_product_variants_delivery_size` (`delivery_size_id`),
  ADD KEY `sub_subcategory_id` (`sub_subcategory_id`),
  ADD KEY `idx_timer_discount` (`timer_discount_active`,`timer_discount_end`);

--
-- Indexes for table `product_variant_colors`
--
ALTER TABLE `product_variant_colors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `variant_id` (`variant_id`),
  ADD KEY `color_id` (`color_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `sales_user_id` (`sales_user_id`),
  ADD KEY `fk_approved_by` (`approved_by`),
  ADD KEY `fk_accounting_approved_by` (`accounting_approved_by`),
  ADD KEY `idx_document_controller_status` (`document_controller_status`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `product_color_id` (`product_color_id`),
  ADD KEY `product_variant_id` (`product_variant_id`),
  ADD KEY `idx_po_id` (`po_id`);

--
-- Indexes for table `qrph_codes`
--
ALTER TABLE `qrph_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qr_code_id` (`qr_code_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `paymongo_payment_id` (`paymongo_payment_id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_status_order` (`status`,`order_id`);

--
-- Indexes for table `qrph_pending_sessions`
--
ALTER TABLE `qrph_pending_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD UNIQUE KEY `temp_ref` (`temp_ref`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `quotations`
--
ALTER TABLE `quotations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quotation_no` (`quotation_no`),
  ADD KEY `idx_quotation_no` (`quotation_no`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_quotation_date` (`quotation_date`),
  ADD KEY `idx_quotation_date_status` (`quotation_date`,`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_grand_total` (`grand_total`);

--
-- Indexes for table `quotation_history`
--
ALTER TABLE `quotation_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quotation_id` (`quotation_id`),
  ADD KEY `idx_performed_by` (`performed_by`);

--
-- Indexes for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quotation_id` (`quotation_id`),
  ADD KEY `idx_item_identifier` (`item_identifier`);

--
-- Indexes for table `quote_replies`
--
ALTER TABLE `quote_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `recent_views`
--
ALTER TABLE `recent_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_unique_view` (`session_id`,`product_id`,`viewed_at`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_product_user` (`product_id`,`user_id`),
  ADD KEY `idx_product_session` (`product_id`,`session_id`),
  ADD KEY `idx_viewed_at` (`viewed_at`);

--
-- Indexes for table `referral_codes`
--
ALTER TABLE `referral_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `referral_visits`
--
ALTER TABLE `referral_visits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_referral_id` (`referral_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_visit_date` (`visit_date`),
  ADD KEY `idx_referral_code` (`referral_code`);

--
-- Indexes for table `replacement_requests`
--
ALTER TABLE `replacement_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_order_item_id` (`order_item_id`),
  ADD KEY `idx_user_email` (`user_email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_po_number` (`po_number`),
  ADD KEY `delivery_schedule_id` (`delivery_schedule_id`),
  ADD KEY `idx_received_status` (`received_status`);

--
-- Indexes for table `sold_items`
--
ALTER TABLE `sold_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sold_order_id` (`sold_order_id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_product_name` (`product_name`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_sold_at` (`sold_at`),
  ADD KEY `idx_type_name` (`type_name`);

--
-- Indexes for table `sold_orders`
--
ALTER TABLE `sold_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_id` (`order_id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_customer_name` (`customer_name`),
  ADD KEY `idx_delivered_at` (`delivered_at`),
  ADD KEY `idx_sale_recorded_at` (`sale_recorded_at`),
  ADD KEY `idx_fiscal_year` (`fiscal_year`),
  ADD KEY `idx_fiscal_month` (`fiscal_month`),
  ADD KEY `idx_emp_id` (`emp_id`),
  ADD KEY `idx_warehouse_employee_id` (`warehouse_employee_id`),
  ADD KEY `idx_delivery_type` (`delivery_type`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_mode_payment` (`mode_payment`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `supplier_addresses`
--
ALTER TABLE `supplier_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_address_type` (`address_type`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_supplier_addresses_location` (`latitude`,`longitude`),
  ADD KEY `idx_supplier_addresses_composite` (`supplier_id`,`is_active`,`address_type`);

--
-- Indexes for table `supplier_list`
--
ALTER TABLE `supplier_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_business_name` (`business_name`),
  ADD KEY `idx_email` (`email_address`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `supplier_products`
--
ALTER TABLE `supplier_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_item` (`supplier_id`,`item_code`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `category` (`category`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `supplier_product_variants`
--
ALTER TABLE `supplier_product_variants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `color` (`color`);

--
-- Indexes for table `supplier_variant_sizes`
--
ALTER TABLE `supplier_variant_sizes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `variant_size` (`variant_id`,`size`),
  ADD KEY `variant_id` (`variant_id`),
  ADD KEY `size` (`size`);

--
-- Indexes for table `supp_link_products`
--
ALTER TABLE `supp_link_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_supplier_product_variant` (`supplier_id`,`product_id`,`variant_id`),
  ADD KEY `fk_supplier` (`supplier_id`),
  ADD KEY `fk_product` (`product_id`),
  ADD KEY `idx_product_supplier_type` (`product_id`,`supplier_type`),
  ADD KEY `idx_variant_id` (`variant_id`);

--
-- Indexes for table `tiercard`
--
ALTER TABLE `tiercard`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transportify_vehicle_list`
--
ALTER TABLE `transportify_vehicle_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vehicle_type` (`vehicle_type`),
  ADD KEY `idx_base_fare` (`base_fare`),
  ADD KEY `idx_courier_name` (`courier_name`);

--
-- Indexes for table `truck_schedules`
--
ALTER TABLE `truck_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_truck_date` (`truck_id`,`scheduled_date`),
  ADD KEY `idx_assigned_driver_id` (`assigned_driver_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `mobile` (`mobile`),
  ADD KEY `fk_user_tiercard` (`tiercard_id`);

--
-- Indexes for table `user_cart_items`
--
ALTER TABLE `user_cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_details`
--
ALTER TABLE `user_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `fk_user_id` (`user_id`);

--
-- Indexes for table `user_feedback`
--
ALTER TABLE `user_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `author_id` (`author_id`);

--
-- Indexes for table `variants`
--
ALTER TABLE `variants`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `variant_tracking`
--
ALTER TABLE `variant_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_variant_tracking_order` (`order_id`),
  ADD KEY `fk_variant_tracking_order_item` (`order_item_id`),
  ADD KEY `fk_tracking_driver` (`driver_id`),
  ADD KEY `fk_user_tracking` (`user_id`);

--
-- Indexes for table `vehicle_list`
--
ALTER TABLE `vehicle_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`),
  ADD KEY `idx_plate_number` (`plate_number`),
  ADD KEY `idx_truck_type` (`truck_type`),
  ADD KEY `idx_registration_expiration` (`registration_expiration_date`),
  ADD KEY `idx_insurance_expiration` (`insurance_expiration_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_make_model` (`make`,`model`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accountantrecord`
--
ALTER TABLE `accountantrecord`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `adminsuppliers`
--
ALTER TABLE `adminsuppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_inspiration`
--
ALTER TABLE `admin_inspiration`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `admin_notification_actions_log`
--
ALTER TABLE `admin_notification_actions_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `admin_notification_history`
--
ALTER TABLE `admin_notification_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bestseller`
--
ALTER TABLE `bestseller`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bestsellertwo`
--
ALTER TABLE `bestsellertwo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `billing_addresses`
--
ALTER TABLE `billing_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `blocks`
--
ALTER TABLE `blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `categorysub`
--
ALTER TABLE `categorysub`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_info`
--
ALTER TABLE `client_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commission_claims`
--
ALTER TABLE `commission_claims`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `company_logos`
--
ALTER TABLE `company_logos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `custom_quote_replies`
--
ALTER TABLE `custom_quote_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custom_quote_requests`
--
ALTER TABLE `custom_quote_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `defect_reports`
--
ALTER TABLE `defect_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_bookings`
--
ALTER TABLE `delivery_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `delivery_logs`
--
ALTER TABLE `delivery_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_reschedule_log`
--
ALTER TABLE `delivery_reschedule_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_schedules`
--
ALTER TABLE `delivery_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `delivery_settings`
--
ALTER TABLE `delivery_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `delivery_sizes`
--
ALTER TABLE `delivery_sizes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_zones`
--
ALTER TABLE `delivery_zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `discount_images`
--
ALTER TABLE `discount_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `driver_list`
--
ALTER TABLE `driver_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employeaccountreport`
--
ALTER TABLE `employeaccountreport`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `employee_tasks`
--
ALTER TABLE `employee_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `images`
--
ALTER TABLE `images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nobleaccount`
--
ALTER TABLE `nobleaccount`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=135;

--
-- AUTO_INCREMENT for table `onsalebanner`
--
ALTER TABLE `onsalebanner`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `order_feedback`
--
ALTER TABLE `order_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `payment_qr_codes`
--
ALTER TABLE `payment_qr_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `po_attachments`
--
ALTER TABLE `po_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `po_receiver_assignments`
--
ALTER TABLE `po_receiver_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `po_status_logs`
--
ALTER TABLE `po_status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- AUTO_INCREMENT for table `product_colors`
--
ALTER TABLE `product_colors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=383;

--
-- AUTO_INCREMENT for table `product_ratings`
--
ALTER TABLE `product_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `product_subcategories`
--
ALTER TABLE `product_subcategories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `product_sub_subcategories`
--
ALTER TABLE `product_sub_subcategories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `product_sub_subcategory_links`
--
ALTER TABLE `product_sub_subcategory_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_tiers`
--
ALTER TABLE `product_tiers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `product_types`
--
ALTER TABLE `product_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=260;

--
-- AUTO_INCREMENT for table `product_variant_colors`
--
ALTER TABLE `product_variant_colors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qrph_codes`
--
ALTER TABLE `qrph_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qrph_pending_sessions`
--
ALTER TABLE `qrph_pending_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `quotations`
--
ALTER TABLE `quotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `quotation_history`
--
ALTER TABLE `quotation_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quotation_items`
--
ALTER TABLE `quotation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `quote_replies`
--
ALTER TABLE `quote_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recent_views`
--
ALTER TABLE `recent_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2646;

--
-- AUTO_INCREMENT for table `referral_codes`
--
ALTER TABLE `referral_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `referral_visits`
--
ALTER TABLE `referral_visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `replacement_requests`
--
ALTER TABLE `replacement_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sold_items`
--
ALTER TABLE `sold_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `sold_orders`
--
ALTER TABLE `sold_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier_addresses`
--
ALTER TABLE `supplier_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier_list`
--
ALTER TABLE `supplier_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplier_products`
--
ALTER TABLE `supplier_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplier_product_variants`
--
ALTER TABLE `supplier_product_variants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `supplier_variant_sizes`
--
ALTER TABLE `supplier_variant_sizes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `supp_link_products`
--
ALTER TABLE `supp_link_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `tiercard`
--
ALTER TABLE `tiercard`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `transportify_vehicle_list`
--
ALTER TABLE `transportify_vehicle_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `truck_schedules`
--
ALTER TABLE `truck_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `user_cart_items`
--
ALTER TABLE `user_cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `user_details`
--
ALTER TABLE `user_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `user_feedback`
--
ALTER TABLE `user_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `variants`
--
ALTER TABLE `variants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `variant_tracking`
--
ALTER TABLE `variant_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicle_list`
--
ALTER TABLE `vehicle_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_inspiration`
--
ALTER TABLE `admin_inspiration`
  ADD CONSTRAINT `admin_inspiration_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `admin_notification_actions_log`
--
ALTER TABLE `admin_notification_actions_log`
  ADD CONSTRAINT `admin_notification_actions_log_ibfk_1` FOREIGN KEY (`notification_history_id`) REFERENCES `admin_notification_history` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_notification_history`
--
ALTER TABLE `admin_notification_history`
  ADD CONSTRAINT `admin_notification_history_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `admin_notifications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `banners`
--
ALTER TABLE `banners`
  ADD CONSTRAINT `banners_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bestseller`
--
ALTER TABLE `bestseller`
  ADD CONSTRAINT `fk_product_bestseller` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `bestsellertwo`
--
ALTER TABLE `bestsellertwo`
  ADD CONSTRAINT `bestsellertwo_ibfk_1` FOREIGN KEY (`bestseller_id`) REFERENCES `bestseller` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`sender_noble_id`) REFERENCES `nobleaccount` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_messages_ibfk_3` FOREIGN KEY (`receiver_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_messages_ibfk_4` FOREIGN KEY (`receiver_noble_id`) REFERENCES `nobleaccount` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chat_receiver_noble` FOREIGN KEY (`receiver_noble_id`) REFERENCES `nobleaccount` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_receiver_user` FOREIGN KEY (`receiver_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_sender_noble` FOREIGN KEY (`sender_noble_id`) REFERENCES `nobleaccount` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_sender_user` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sales` FOREIGN KEY (`sales_id`) REFERENCES `nobleaccount` (`sales_id`);

--
-- Constraints for table `companies`
--
ALTER TABLE `companies`
  ADD CONSTRAINT `companies_ibfk_1` FOREIGN KEY (`sales_user_id`) REFERENCES `nobleaccount` (`id`);

--
-- Constraints for table `custom_quote_replies`
--
ALTER TABLE `custom_quote_replies`
  ADD CONSTRAINT `custom_quote_replies_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `custom_quote_requests` (`id`);

--
-- Constraints for table `delivery_bookings`
--
ALTER TABLE `delivery_bookings`
  ADD CONSTRAINT `fk_booking_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_booking_schedule` FOREIGN KEY (`delivery_schedule_id`) REFERENCES `delivery_schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_booking_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `transportify_vehicle_list` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_delivery_bookings_dispatcher` FOREIGN KEY (`dispatcher_id`) REFERENCES `nobleaccount` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `delivery_logs`
--
ALTER TABLE `delivery_logs`
  ADD CONSTRAINT `delivery_logs_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `delivery_schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `delivery_logs_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_tasks`
--
ALTER TABLE `employee_tasks`
  ADD CONSTRAINT `employee_tasks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `employeaccountreport` (`id`);

--
-- Constraints for table `images`
--
ALTER TABLE `images`
  ADD CONSTRAINT `images_ibfk_1` FOREIGN KEY (`variant_id`) REFERENCES `variants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_actor` FOREIGN KEY (`actor_id`) REFERENCES `nobleaccount` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_rejected_by_nobleaccount` FOREIGN KEY (`rejected_by`) REFERENCES `nobleaccount` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_sales_user` FOREIGN KEY (`sales_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_vehicle` FOREIGN KEY (`assigned_vehicle_id`) REFERENCES `transportify_vehicle_list` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_verified_by_nobleaccount` FOREIGN KEY (`verified_by`) REFERENCES `nobleaccount` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_warehouse_employee` FOREIGN KEY (`warehouse_employee_id`) REFERENCES `nobleaccount` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_feedback`
--
ALTER TABLE `order_feedback`
  ADD CONSTRAINT `order_feedback_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_qr_codes`
--
ALTER TABLE `payment_qr_codes`
  ADD CONSTRAINT `fk_qr_created_by` FOREIGN KEY (`created_by`) REFERENCES `nobleaccount` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_qr_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `nobleaccount` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `po_attachments`
--
ALTER TABLE `po_attachments`
  ADD CONSTRAINT `po_attachments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `po_receiver_assignments`
--
ALTER TABLE `po_receiver_assignments`
  ADD CONSTRAINT `po_receiver_assignments_ibfk_1` FOREIGN KEY (`po_attachment_id`) REFERENCES `po_attachments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `po_receiver_assignments_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `po_receiver_assignments_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `nobleaccount` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `po_receiver_assignments_ibfk_4` FOREIGN KEY (`assigned_by`) REFERENCES `nobleaccount` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `po_status_logs`
--
ALTER TABLE `po_status_logs`
  ADD CONSTRAINT `po_status_logs_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `po_status_logs_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `nobleaccount` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`product_subcategory_id`) REFERENCES `product_subcategories` (`id`);

--
-- Constraints for table `product_colors`
--
ALTER TABLE `product_colors`
  ADD CONSTRAINT `product_colors_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_ratings`
--
ALTER TABLE `product_ratings`
  ADD CONSTRAINT `fk_ratings_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ratings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_subcategories`
--
ALTER TABLE `product_subcategories`
  ADD CONSTRAINT `fk_category_subcategory` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_subcategory_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_sub_subcategories`
--
ALTER TABLE `product_sub_subcategories`
  ADD CONSTRAINT `fk_subsubcat_subcat` FOREIGN KEY (`subcategory_id`) REFERENCES `product_subcategories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_sub_subcategory_links`
--
ALTER TABLE `product_sub_subcategory_links`
  ADD CONSTRAINT `product_sub_subcategory_links_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_sub_subcategory_links_ibfk_2` FOREIGN KEY (`sub_subcategory_id`) REFERENCES `product_sub_subcategories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_types`
--
ALTER TABLE `product_types`
  ADD CONSTRAINT `product_types_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `fk_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_product_variants_delivery_size` FOREIGN KEY (`delivery_size_id`) REFERENCES `delivery_sizes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `product_variants_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `product_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_variants_ibfk_2` FOREIGN KEY (`sub_subcategory_id`) REFERENCES `product_sub_subcategories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_variant_colors`
--
ALTER TABLE `product_variant_colors`
  ADD CONSTRAINT `product_variant_colors_ibfk_1` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_variant_colors_ibfk_2` FOREIGN KEY (`color_id`) REFERENCES `product_colors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `fk_accounting_approved_by` FOREIGN KEY (`accounting_approved_by`) REFERENCES `nobleaccount` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `nobleaccount` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`sales_user_id`) REFERENCES `nobleaccount` (`id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `purchase_order_items_ibfk_3` FOREIGN KEY (`product_color_id`) REFERENCES `product_colors` (`id`),
  ADD CONSTRAINT `purchase_order_items_ibfk_4` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `qrph_codes`
--
ALTER TABLE `qrph_codes`
  ADD CONSTRAINT `qrph_codes_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`);

--
-- Constraints for table `quotation_history`
--
ALTER TABLE `quotation_history`
  ADD CONSTRAINT `quotation_history_ibfk_1` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD CONSTRAINT `quotation_items_ibfk_1` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quote_replies`
--
ALTER TABLE `quote_replies`
  ADD CONSTRAINT `quote_replies_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `custom_quote_requests` (`id`);

--
-- Constraints for table `recent_views`
--
ALTER TABLE `recent_views`
  ADD CONSTRAINT `recent_views_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `referral_codes`
--
ALTER TABLE `referral_codes`
  ADD CONSTRAINT `referral_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `nobleaccount` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `referral_visits`
--
ALTER TABLE `referral_visits`
  ADD CONSTRAINT `referral_visits_ibfk_1` FOREIGN KEY (`referral_id`) REFERENCES `referral_codes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `referral_visits_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `nobleaccount` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `replacement_requests`
--
ALTER TABLE `replacement_requests`
  ADD CONSTRAINT `replacement_requests_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `replacement_requests_ibfk_2` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `replacement_requests_ibfk_3` FOREIGN KEY (`delivery_schedule_id`) REFERENCES `delivery_schedules` (`id`);

--
-- Constraints for table `sold_items`
--
ALTER TABLE `sold_items`
  ADD CONSTRAINT `fk_sold_items_order` FOREIGN KEY (`sold_order_id`) REFERENCES `sold_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_addresses`
--
ALTER TABLE `supplier_addresses`
  ADD CONSTRAINT `fk_supplier_addresses_supplier_id` FOREIGN KEY (`supplier_id`) REFERENCES `nobleaccount` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supplier_product_variants`
--
ALTER TABLE `supplier_product_variants`
  ADD CONSTRAINT `fk_variants_product` FOREIGN KEY (`product_id`) REFERENCES `supplier_products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supplier_variant_sizes`
--
ALTER TABLE `supplier_variant_sizes`
  ADD CONSTRAINT `fk_sizes_variant` FOREIGN KEY (`variant_id`) REFERENCES `supplier_product_variants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supp_link_products`
--
ALTER TABLE `supp_link_products`
  ADD CONSTRAINT `fk_supp_link_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_supp_link_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier_list` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `truck_schedules`
--
ALTER TABLE `truck_schedules`
  ADD CONSTRAINT `fk_truck_schedule_driver` FOREIGN KEY (`assigned_driver_id`) REFERENCES `driver_list` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `truck_schedules_ibfk_1` FOREIGN KEY (`truck_id`) REFERENCES `vehicle_list` (`plate_number`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_tiercard` FOREIGN KEY (`tiercard_id`) REFERENCES `tiercard` (`id`);

--
-- Constraints for table `user_cart_items`
--
ALTER TABLE `user_cart_items`
  ADD CONSTRAINT `user_cart_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_details`
--
ALTER TABLE `user_details`
  ADD CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_feedback`
--
ALTER TABLE `user_feedback`
  ADD CONSTRAINT `user_feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_feedback_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `variant_tracking`
--
ALTER TABLE `variant_tracking`
  ADD CONSTRAINT `fk_tracking_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`),
  ADD CONSTRAINT `fk_user_tracking` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_variant_tracking_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_variant_tracking_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
