-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 29, 2026 at 03:00 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lot_mapping`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES
(82, 1, 'logout', 'user', 1, NULL, '::1', '2026-10-28 17:42:12'),
(83, 1, 'login', 'user', 1, NULL, '::1', '2026-10-28 17:42:17');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `contact_number` varchar(40) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `installments`
--

CREATE TABLE `installments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ownership_id` bigint(20) UNSIGNED NOT NULL,
  `first_due_date` date NOT NULL,
  `term_months` smallint(5) UNSIGNED NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `installment_payments`
--

CREATE TABLE `installment_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `installment_id` bigint(20) UNSIGNED NOT NULL,
  `installment_number` smallint(5) UNSIGNED NOT NULL,
  `due_date` date NOT NULL,
  `payment_date` date DEFAULT NULL,
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `lots`
--

CREATE TABLE `lots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `map_area_id` bigint(20) UNSIGNED NOT NULL,
  `block_number` varchar(50) NOT NULL,
  `lot_number` varchar(50) NOT NULL,
  `lot_area_sqm` decimal(12,2) DEFAULT NULL,
  `price` decimal(14,2) DEFAULT NULL,
  `status` enum('available','held','spot_cash','installment_current','overdue','forfeiture_review') NOT NULL DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lot_shapes`
--

CREATE TABLE `lot_shapes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lot_id` bigint(20) UNSIGNED NOT NULL,
  `polygon_points` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`polygon_points`)),
  `verification_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `verified_by` bigint(20) UNSIGNED DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `map_areas`
--

CREATE TABLE `map_areas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `area_name` varchar(180) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `map_image_path` varchar(500) DEFAULT NULL,
  `map_image_width` int(10) UNSIGNED DEFAULT NULL,
  `map_image_height` int(10) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `map_areas`
--

INSERT INTO `map_areas` (`id`, `area_name`, `location`, `description`, `map_image_path`, `map_image_width`, `map_image_height`, `created_by`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 'Baluan Area', 'Brgy. Baluan General Santos City', NULL, 'uploads/db3fe1a92d1a22f183e25935f6196b4fe02d1893.jpg', 864, 1232, 1, 1, '2026-10-23 19:14:40', '2026-10-23 19:14:40'),
(3, 'Nopol', 'Nopol B Mabuhay General Santos City', NULL, 'uploads/ada741d33744099349270e6bff1c2e6602f77130.jpg', 1216, 874, 1, 1, '2026-10-24 04:57:08', '2026-10-24 04:57:08'),
(5, 'San jose Project 2', 'San jose purok shuttle General Santos City', NULL, 'uploads/993401e19e5f10762807ff99abf65a4f1d4152ad.jpg', NULL, NULL, 1, 1, '2026-10-24 12:26:44', '2026-10-24 12:29:53'),
(13, 'Klinan 6 Project 1', 'Purok Masipag Polomolok South Cotabato', NULL, 'uploads/antongwapo.jpg', 864, 1232, 1, 1, '2026-10-24 13:07:52', '2026-10-24 13:09:19'),
(14, 'Ligaya Area', 'Ligaya Phase 1 to 4', NULL, 'uploads/9c328a450ade0a6270838d6aa87ee192eacf84a0.jpg', 864, 1232, 1, 1, '2026-10-27 07:50:13', '2026-10-27 07:50:13');

-- --------------------------------------------------------

--
-- Table structure for table `ownership_history`
--

CREATE TABLE `ownership_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lot_id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `ownership_type` enum('spot_cash','installment') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `ownership_status` enum('active','completed','backed_out','transferred','forfeiture_review') NOT NULL DEFAULT 'active',
  `backout_date` date DEFAULT NULL,
  `backout_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `username` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff','viewer') NOT NULL DEFAULT 'viewer',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `password_hash`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Anton Siangko', 'admin', '$2y$10$a6TYcpB2L5S4p.hr1WP9ou2OLrmWFtNifh/Tu4y2hd16J.jMNyaaG', 'admin', 1, '2026-10-23 18:59:22', '2026-10-23 18:59:22');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_activity_user` (`user_id`),
  ADD KEY `idx_activity_action` (`action`),
  ADD KEY `idx_activity_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_activity_created` (`created_at`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_clients_created_by` (`created_by`),
  ADD KEY `idx_clients_name` (`last_name`,`first_name`),
  ADD KEY `idx_clients_contact` (`contact_number`);

--
-- Indexes for table `installments`
--
ALTER TABLE `installments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_installments_ownership` (`ownership_id`),
  ADD KEY `fk_installments_created_by` (`created_by`),
  ADD KEY `idx_installments_first_due` (`first_due_date`);

--
-- Indexes for table `installment_payments`
--
ALTER TABLE `installment_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_installment_number` (`installment_id`,`installment_number`),
  ADD KEY `fk_payments_recorded_by` (`recorded_by`),
  ADD KEY `idx_payments_due_date` (`due_date`),
  ADD KEY `idx_payments_payment_date` (`payment_date`);

--
-- Indexes for table `lots`
--
ALTER TABLE `lots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_area_block_lot` (`map_area_id`,`block_number`,`lot_number`),
  ADD KEY `fk_lots_created_by` (`created_by`),
  ADD KEY `idx_lots_status` (`status`),
  ADD KEY `idx_lots_block` (`map_area_id`,`block_number`);

--
-- Indexes for table `lot_shapes`
--
ALTER TABLE `lot_shapes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_lot_shapes_verified_by` (`verified_by`),
  ADD KEY `fk_lot_shapes_created_by` (`created_by`),
  ADD KEY `idx_lot_shapes_lot` (`lot_id`),
  ADD KEY `idx_lot_shapes_verification` (`verification_status`);

--
-- Indexes for table `map_areas`
--
ALTER TABLE `map_areas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_map_areas_created_by` (`created_by`),
  ADD KEY `idx_map_areas_name` (`area_name`),
  ADD KEY `idx_map_areas_active` (`is_active`);

--
-- Indexes for table `ownership_history`
--
ALTER TABLE `ownership_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ownership_recorded_by` (`recorded_by`),
  ADD KEY `idx_ownership_lot_status` (`lot_id`,`ownership_status`),
  ADD KEY `idx_ownership_client` (`client_id`),
  ADD KEY `idx_ownership_dates` (`start_date`,`end_date`),
  ADD KEY `idx_ownership_backout` (`backout_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `installments`
--
ALTER TABLE `installments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `installment_payments`
--
ALTER TABLE `installment_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lots`
--
ALTER TABLE `lots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `lot_shapes`
--
ALTER TABLE `lot_shapes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `map_areas`
--
ALTER TABLE `map_areas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `ownership_history`
--
ALTER TABLE `ownership_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `fk_clients_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `installments`
--
ALTER TABLE `installments`
  ADD CONSTRAINT `fk_installments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_installments_ownership` FOREIGN KEY (`ownership_id`) REFERENCES `ownership_history` (`id`);

--
-- Constraints for table `installment_payments`
--
ALTER TABLE `installment_payments`
  ADD CONSTRAINT `fk_payments_installment` FOREIGN KEY (`installment_id`) REFERENCES `installments` (`id`),
  ADD CONSTRAINT `fk_payments_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lots`
--
ALTER TABLE `lots`
  ADD CONSTRAINT `fk_lots_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_lots_map_area` FOREIGN KEY (`map_area_id`) REFERENCES `map_areas` (`id`);

--
-- Constraints for table `lot_shapes`
--
ALTER TABLE `lot_shapes`
  ADD CONSTRAINT `fk_lot_shapes_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_lot_shapes_lot` FOREIGN KEY (`lot_id`) REFERENCES `lots` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lot_shapes_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `map_areas`
--
ALTER TABLE `map_areas`
  ADD CONSTRAINT `fk_map_areas_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ownership_history`
--
ALTER TABLE `ownership_history`
  ADD CONSTRAINT `fk_ownership_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `fk_ownership_lot` FOREIGN KEY (`lot_id`) REFERENCES `lots` (`id`),
  ADD CONSTRAINT `fk_ownership_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
