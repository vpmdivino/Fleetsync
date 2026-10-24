-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 17, 2026 at 09:07 PM
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
-- Database: `fleet`
--

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `license_no` varchar(30) NOT NULL,
  `license_expiry` date NOT NULL,
  `contact` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `employment_status` enum('Full-Time','Part-Time','Contractor','Seasonal') NOT NULL DEFAULT 'Full-Time',
  `date_hired` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `name`, `license_no`, `license_expiry`, `contact`, `address`, `employment_status`, `date_hired`, `created_at`, `updated_at`) VALUES
(2, 'Will', 'D-213218', '2026-07-06', '0991', 'Crimson ln', 'Full-Time', '2026-07-06', '2026-07-06 02:11:24', '2026-07-06 03:00:25');

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `expense_category` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date_incurred` date NOT NULL,
  `vendor` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `expense_category`, `amount`, `date_incurred`, `vendor`, `created_at`, `updated_at`) VALUES
(1, 'Maintenance & Repair', 100.00, '2026-07-07', 'asds', '2026-07-07 07:46:52', '2026-07-07 07:46:52');

-- --------------------------------------------------------

--
-- Table structure for table `fuel_logs`
--

CREATE TABLE `fuel_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vehicle_id` bigint(20) UNSIGNED NOT NULL,
  `gallons_pumped` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `log_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fuel_logs`
--

INSERT INTO `fuel_logs` (`id`, `vehicle_id`, `gallons_pumped`, `total_cost`, `log_date`, `created_at`, `updated_at`) VALUES
(0, 2, 42.00, 12.00, '2026-07-06 03:15:46', '2026-07-06 03:15:46', '2026-07-06 03:15:46');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `trip_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('Draft','Sent','Paid','Void') NOT NULL DEFAULT 'Draft',
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `payment_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `trip_id`, `invoice_number`, `client_name`, `amount`, `status`, `issue_date`, `due_date`, `payment_date`, `created_at`, `updated_at`) VALUES
(8, 4, 'INV-2026-0001', 'Client for ewrewr', 3552.00, 'Paid', '2026-07-07', '2026-08-06', '2026-07-07', '2026-07-07 07:13:48', '2026-07-07 07:46:04'),
(9, 4, 'INV-2026-0002', 'Client for ewrewr', 179614.00, 'Paid', '2026-07-07', '2026-08-06', '2026-07-07', '2026-07-07 07:59:38', '2026-07-07 08:00:58');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_logs`
--

CREATE TABLE `maintenance_logs` (
  `id` bigint(20) NOT NULL,
  `vehicle_id` bigint(20) NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `scheduled_date` date NOT NULL,
  `completion_date` date DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT 0.00,
  `status` varchar(50) NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_logs`
--

INSERT INTO `maintenance_logs` (`id`, `vehicle_id`, `service_type`, `details`, `scheduled_date`, `completion_date`, `cost`, `status`, `created_at`, `updated_at`) VALUES
(3, 2, 'Oil change', 'check', '2026-07-07', '2026-07-08', 888.00, 'Scheduled', '2026-07-06 06:26:53', '2026-07-08 03:20:41');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `actor_name` varchar(191) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `actor_name`, `action`, `ip_address`, `created_at`) VALUES
(0, 3, 'admin admin', 'Updated user (ID: 2) status to Active.', '::1', '2026-07-08 06:20:09'),
(0, 3, 'admin admin', 'Created a database backup.', '::1', '2026-07-08 06:26:26'),
(0, 4, 'asd asd', 'Updated user (ID: 2) status to Deactivated.', '::1', '2026-10-16 08:08:04'),
(0, 4, 'asd asd', 'Updated user (ID: 2) status to Active.', '::1', '2026-10-16 08:08:08');

-- --------------------------------------------------------

--
-- Table structure for table `trips`
--

CREATE TABLE `trips` (
  `id` int(11) NOT NULL,
  `trip_number` varchar(50) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `origin` varchar(255) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `departure_datetime` datetime DEFAULT NULL,
  `arrival_datetime` datetime DEFAULT NULL,
  `cargo` varchar(255) NOT NULL,
  `distance` decimal(10,2) DEFAULT NULL,
  `fuel_used` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `trips`
--

INSERT INTO `trips` (`id`, `trip_number`, `driver_id`, `vehicle_id`, `origin`, `destination`, `departure_datetime`, `arrival_datetime`, `cargo`, `distance`, `fuel_used`, `status`, `remarks`, `created_at`, `updated_at`) VALUES
(4, 'T-2026070707102', 2, 2, 'asdasad', 'ewrewr', '2026-07-07 15:10:00', '2026-07-11 15:10:00', 'FASF', 32.00, 22.00, 'Completed', 'dsadsadsad', '2026-07-07 07:10:39', '2026-07-07 07:10:39'),
(5, 'T-2026070803180', 2, 2, 'tewe', 'arear', '2026-07-08 11:18:00', '2026-07-08 11:19:47', 'asdsa', 22.00, 22.00, 'Completed', 'asdasdsa', '2026-07-08 03:18:23', '2026-07-08 03:19:47');

-- --------------------------------------------------------

--
-- Table structure for table `trip_events`
--

CREATE TABLE `trip_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `trip_id` bigint(20) UNSIGNED NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `event_datetime` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trip_events`
--

INSERT INTO `trip_events` (`id`, `trip_id`, `event_type`, `notes`, `event_datetime`) VALUES
(1, 2, 'Out for Delivery', 'aasd', '2026-07-08 05:33:00'),
(2, 3, 'Delivered', 'ADAd', '2026-07-07 05:37:00'),
(3, 5, 'Out for Delivery', 'lkl', '2026-07-08 03:19:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Fleet Manager','Accountant') NOT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('Active','Deactivated') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password`, `role`, `email_verified`, `status`, `created_at`, `updated_at`) VALUES
(11, 'wc', 'wc', 'wchristianbarrios@gmail.com', '$2y$10$xF/w1d4VXsbsE2g80fzAI.OGsZmReGdBafdToxabz.QFm0s1qoZAa', 'Fleet Manager', 1, 'Active', '2026-10-17 19:02:51', '2026-10-17 19:03:40');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `vehicle_type` varchar(50) NOT NULL,
  `brand` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `capacity` varchar(30) NOT NULL,
  `status` enum('Available','In Use','Under Maintenance','Retired') NOT NULL DEFAULT 'Available',
  `mileage` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `registration_date` date NOT NULL,
  `insurance_expiry` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `plate_number`, `vehicle_type`, `brand`, `model`, `capacity`, `status`, `mileage`, `registration_date`, `insurance_expiry`, `created_at`, `updated_at`) VALUES
(2, 'DBJ 5834', 'Sedan', 'Toyota', 'Wigo', '15 tons', 'In Use', 12323, '2026-07-01', '2026-07-31', '2026-07-04 14:51:38', '2026-07-08 03:21:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_no` (`license_no`),
  ADD KEY `idx_employment_status` (`employment_status`),
  ADD KEY `idx_license_expiry` (`license_expiry`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ev_user` (`user_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expense_category` (`expense_category`),
  ADD KEY `idx_date_incurred` (`date_incurred`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `fk_invoice_trip` (`trip_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_client_name` (`client_name`);

--
-- Indexes for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_trip_number` (`trip_number`);

--
-- Indexes for table `trip_events`
--
ALTER TABLE `trip_events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_users_email` (`email`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_plate` (`plate_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `trips`
--
ALTER TABLE `trips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `trip_events`
--
ALTER TABLE `trip_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD CONSTRAINT `fk_ev_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoice_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
