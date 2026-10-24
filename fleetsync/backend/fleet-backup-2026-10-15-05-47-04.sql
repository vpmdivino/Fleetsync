-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: fleet
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `drivers`
--

DROP TABLE IF EXISTS `drivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `drivers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `license_no` varchar(30) NOT NULL,
  `license_expiry` date NOT NULL,
  `contact` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `employment_status` enum('Full-Time','Part-Time','Contractor','Seasonal') NOT NULL DEFAULT 'Full-Time',
  `date_hired` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `license_no` (`license_no`),
  KEY `idx_employment_status` (`employment_status`),
  KEY `idx_license_expiry` (`license_expiry`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drivers`
--

LOCK TABLES `drivers` WRITE;
/*!40000 ALTER TABLE `drivers` DISABLE KEYS */;
INSERT INTO `drivers` VALUES (2,'Will','D-213218','2026-07-06','0991','Crimson ln','Full-Time','2026-07-06','2026-07-06 02:11:24','2026-07-06 03:00:25'),(3,'Joe','DL-123-ABC','2027-03-17','09999999999','123 Poblacion, Makati','Full-Time','2025-06-11','2026-09-30 00:48:48','2026-09-30 00:48:48'),(4,'Lebron James','DL-1213098','2029-11-14','0921391231','1213 Ermita Manila','Full-Time','2026-10-12','2026-10-08 02:41:44','2026-10-08 02:41:44');
/*!40000 ALTER TABLE `drivers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `expense_category` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date_incurred` date NOT NULL,
  `vendor` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_expense_category` (`expense_category`),
  KEY `idx_date_incurred` (`date_incurred`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,'Maintenance & Repair',100.00,'2026-07-07','asds','2026-07-07 07:46:52','2026-07-07 07:46:52'),(2,'Maintenance & Repair',100.00,'2026-07-07','','2026-09-30 01:25:23','2026-09-30 01:25:23'),(3,'Fuel',4500.00,'2026-10-09','Petron','2026-10-08 02:48:16','2026-10-08 02:48:16');
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fuel_logs`
--

DROP TABLE IF EXISTS `fuel_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fuel_logs` (
  `id` bigint(20) unsigned NOT NULL,
  `vehicle_id` bigint(20) unsigned NOT NULL,
  `gallons_pumped` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `log_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fuel_logs`
--

LOCK TABLES `fuel_logs` WRITE;
/*!40000 ALTER TABLE `fuel_logs` DISABLE KEYS */;
INSERT INTO `fuel_logs` VALUES (0,2,42.00,12.00,'2026-07-06 03:15:46','2026-07-06 03:15:46','2026-07-06 03:15:46'),(0,3,16.20,1450.00,'2026-09-30 01:01:17','2026-09-30 01:01:17','2026-09-30 01:01:17'),(0,4,60.00,4500.00,'2026-10-08 02:44:09','2026-10-08 02:44:09','2026-10-08 02:44:09');
/*!40000 ALTER TABLE `fuel_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trip_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('Draft','Sent','Paid','Void') NOT NULL DEFAULT 'Draft',
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `payment_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `fk_invoice_trip` (`trip_id`),
  KEY `idx_status` (`status`),
  KEY `idx_client_name` (`client_name`),
  CONSTRAINT `fk_invoice_trip` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (8,4,'INV-2026-0001','Client for ewrewr',3552.00,'Paid','2026-07-07','2026-08-06','2026-07-07','2026-07-07 07:13:48','2026-07-07 07:46:04'),(9,4,'INV-2026-0002','Client for ewrewr',179614.00,'Paid','2026-07-07','2026-08-06','2026-07-07','2026-07-07 07:59:38','2026-07-07 08:00:58'),(10,4,'INV-2026-0003','Client for ewrewr',2698.00,'Void','2026-09-22','2026-10-22',NULL,'2026-09-22 15:17:26','2026-09-22 15:18:17'),(11,6,'INV-2026-0004','Client for Caloocan',98266.00,'Paid','2026-09-30','2026-10-01','2026-09-02','2026-09-30 00:54:13','2026-09-30 00:56:50'),(12,6,'INV-2026-0005','Client for Caloocan',184848.00,'Paid','2026-09-30','2026-10-30','2026-09-02','2026-09-30 00:55:22','2026-09-30 00:56:15'),(13,6,'INV-2026-0006','Client for Caloocan',87441.00,'Void','2026-09-30','2026-09-02',NULL,'2026-09-30 01:06:24','2026-09-30 01:25:55'),(14,7,'INV-2026-0007','Client for Baguio',101835.00,'Paid','2026-10-08','2026-10-23','2026-10-21','2026-10-08 02:45:59','2026-10-08 02:47:27');
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maintenance_logs`
--

DROP TABLE IF EXISTS `maintenance_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maintenance_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `vehicle_id` bigint(20) NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `scheduled_date` date NOT NULL,
  `completion_date` date DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT 0.00,
  `status` varchar(50) NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maintenance_logs`
--

LOCK TABLES `maintenance_logs` WRITE;
/*!40000 ALTER TABLE `maintenance_logs` DISABLE KEYS */;
INSERT INTO `maintenance_logs` VALUES (3,2,'Oil change','check','2026-07-07','2026-07-08',888.00,'Completed','2026-07-06 06:26:53','2026-09-30 01:01:51'),(4,4,'Oil Change','','2026-10-19',NULL,0.00,'Completed','2026-10-08 02:44:46','2026-10-08 02:45:04');
/*!40000 ALTER TABLE `maintenance_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_logs` (
  `id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `actor_name` varchar(191) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_logs`
--

LOCK TABLES `system_logs` WRITE;
/*!40000 ALTER TABLE `system_logs` DISABLE KEYS */;
INSERT INTO `system_logs` VALUES (0,3,'admin admin','Updated user (ID: 2) status to Active.','::1','2026-07-08 06:20:09'),(0,3,'admin admin','Created a database backup.','::1','2026-07-08 06:26:26'),(0,4,'perry divino','Created new user: vincentperry@gmail.com with role Fleet Manager.','::1','2026-09-22 15:09:10'),(0,4,'perry divino','Updated user profile for email: perrydivino@gmail.com.','::1','2026-09-22 15:11:01'),(0,4,'perry divino','Created new user: vincentdivino@gmail.com with role Accountant.','::1','2026-09-22 15:11:23'),(0,5,'vincent perry','Added new vehicle with plate number: ABC 321.','::1','2026-09-30 00:47:01'),(0,4,'perry divino','Created a database backup.','::1','2026-09-30 00:58:54'),(0,5,'vincent perry','Edited vehicle with plate number: ABC 321.','::1','2026-09-30 01:00:05'),(0,5,'vincent perry','Edited vehicle with plate number: ABC 321.','::1','2026-09-30 01:02:08'),(0,4,'perry divino','Created new user: aj@gmail.com with role Admin.','::1','2026-09-30 13:09:23'),(0,4,'perry divino','Restored database from a backup file.','::1','2026-10-08 02:39:14'),(0,5,'vincent perry','Added new vehicle with plate number: PAS 1232.','::1','2026-10-08 02:40:48');
/*!40000 ALTER TABLE `system_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trip_events`
--

DROP TABLE IF EXISTS `trip_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trip_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trip_id` bigint(20) unsigned NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `event_datetime` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trip_events`
--

LOCK TABLES `trip_events` WRITE;
/*!40000 ALTER TABLE `trip_events` DISABLE KEYS */;
INSERT INTO `trip_events` VALUES (1,2,'Out for Delivery','aasd','2026-07-08 05:33:00'),(2,3,'Delivered','ADAd','2026-07-07 05:37:00'),(3,5,'Out for Delivery','lkl','2026-07-08 03:19:00'),(4,6,'Out for Delivery','','2026-09-01 00:50:00'),(5,7,'Out for Delivery','','2026-10-09 02:45:00'),(6,7,'Delivered','','2026-10-09 04:45:00');
/*!40000 ALTER TABLE `trip_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trips`
--

DROP TABLE IF EXISTS `trips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_trip_number` (`trip_number`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trips`
--

LOCK TABLES `trips` WRITE;
/*!40000 ALTER TABLE `trips` DISABLE KEYS */;
INSERT INTO `trips` VALUES (4,'T-2026070707102',2,2,'asdasad','ewrewr','2026-07-07 15:10:00','2026-07-11 15:10:00','FASF',32.00,22.00,'Completed','dsadsadsad','2026-07-07 07:10:39','2026-07-07 07:10:39'),(5,'T-2026070803180',2,2,'tewe','arear','2026-07-08 11:18:00','2026-07-08 11:19:47','asdsa',22.00,22.00,'Completed','asdasdsa','2026-07-08 03:18:23','2026-07-08 03:19:47'),(6,'T-2026093000492',3,3,'Makati','Caloocan','2026-09-01 08:50:00','2026-09-30 08:51:20','',NULL,NULL,'Completed','','2026-09-30 00:50:22','2026-09-30 00:51:20'),(7,'T-2026100802415',4,4,'Mandaluyong','Baguio','2026-10-09 10:30:00','2026-10-08 10:43:48','2 tons of bag',25.00,60.00,'Completed','','2026-10-08 02:42:58','2026-10-08 02:43:48'),(8,'T-2026101201303',4,3,'austin warehouse','dallas hub','2026-10-12 00:00:00','2030-12-31 00:00:00','2 pallets of electronics',-0.40,NULL,'In Progress','','2026-10-12 01:32:56','2026-10-12 01:32:56'),(9,'T-2026101201330',NULL,NULL,'austin','dallas','2012-10-20 00:00:00','2023-12-25 00:00:00','2 pallets',-12.00,NULL,'In Progress','','2026-10-12 01:36:22','2026-10-12 01:36:22');
/*!40000 ALTER TABLE `trips` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Fleet Manager','Accountant') NOT NULL,
  `status` enum('Active','Deactivated') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (2,'Will','Chris','w@gmail.com','$2y$10$nLB0quAUtAvUfVCyXe7theYs5le7HU6BKh/JapPcC9KVJGmDNURTC','Fleet Manager','Active','2026-07-08 03:38:36','2026-07-08 06:20:09'),(3,'admin','admin','admin@gmail.com','$2y$10$qrMSd9oxlkh171.QzuJoLuUkh2bcU2u4Y7XN9YrPB7yvqzl.xxXI2','Admin','Active','2026-07-08 03:44:27','2026-07-08 03:44:27'),(4,'perry','divino','perrydivino@gmail.com','$2y$10$7jTWOFHOCnVAJWUWT7CSwumOW5DfDhqY0M/sZfYE1H1la0lGYQGV2','Admin','Active','2026-09-20 19:36:05','2026-09-22 15:11:01'),(5,'vincent','perry','vincentperry@gmail.com','$2y$10$LYTsW77TkOchML.76rFY4.fUZH7zWgbTpQcZiGwRh9Xv2zFw3mtOC','Fleet Manager','Active','2026-09-22 15:09:10','2026-09-22 15:09:10'),(6,'vincent','divino','vincentdivino@gmail.com','$2y$10$VbBUAWvb97S21e3rtPBHpOTRuLp4bmwJ94Nu3f/FUP7QsJN/V6/bi','Accountant','Active','2026-09-22 15:11:23','2026-09-22 15:11:23'),(7,'Angelene','De Guzman','aj@gmail.com','$2y$10$79cluonSK1dxefe.L9JhMenC8jX0xpoo2i3X1LOOCxjTcCfp9hnOS','Admin','Active','2026-09-30 13:09:23','2026-09-30 13:09:23'),(8,'tina','pascua','sample@gmail.com','$2y$10$pdqoufue2I8TgiqLlcXI0e2aPsSMTYRb2LKERhf1l6KHPoTgNLBn2','Admin','Active','2026-10-12 01:23:44','2026-10-12 01:23:44'),(9,'tina','pascua','sample1@gmail.com','$2y$10$4sxp9je404mUpqsnOXwPKudfILdSIunLkUwYLO.Rm3ILFUWcwkfH6','Fleet Manager','Active','2026-10-12 01:28:44','2026-10-12 01:28:44'),(10,'tina','pascua','sample2@gmail.com','$2y$10$mhDZcEmRB/4hMuycJvA30OffJasbkP9lCmThApuB91X.jXIcaypJe','Accountant','Active','2026-10-12 01:37:57','2026-10-12 01:37:57');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicles`
--

DROP TABLE IF EXISTS `vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plate_number` varchar(20) NOT NULL,
  `vehicle_type` varchar(50) NOT NULL,
  `brand` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `capacity` varchar(30) NOT NULL,
  `status` enum('Available','In Use','Under Maintenance','Retired') NOT NULL DEFAULT 'Available',
  `mileage` int(10) unsigned NOT NULL DEFAULT 0,
  `registration_date` date NOT NULL,
  `insurance_expiry` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `plate_number` (`plate_number`),
  KEY `idx_status` (`status`),
  KEY `idx_plate` (`plate_number`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicles`
--

LOCK TABLES `vehicles` WRITE;
/*!40000 ALTER TABLE `vehicles` DISABLE KEYS */;
INSERT INTO `vehicles` VALUES (2,'DBJ 5834','Sedan','Toyota','Wigo','15 tons','In Use',12323,'2026-07-01','2026-07-31','2026-07-04 14:51:38','2026-07-08 03:21:37'),(3,'ABC 321','SUV','Toyota','Rush','7','Available',15021,'2025-12-19','2026-12-04','2026-09-30 00:47:01','2026-09-30 01:02:08'),(4,'PAS 1232','Sedan','Toyota','Vios','2','Available',11902,'2026-01-14','2027-10-22','2026-10-08 02:40:48','2026-10-08 02:40:48');
/*!40000 ALTER TABLE `vehicles` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-15 11:47:05
