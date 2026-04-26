-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: vio
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
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,'Admin','POST /livewire/update','127.0.0.1','{\"route\":\"default.livewire.update\",\"status\":200}','2026-04-26 08:52:52','2026-04-26 08:52:52'),(2,1,'Admin','POST /livewire/update','127.0.0.1','{\"route\":\"default.livewire.update\",\"status\":200}','2026-04-26 08:52:55','2026-04-26 08:52:55'),(3,1,'Admin','POST /livewire/update','127.0.0.1','{\"route\":\"default.livewire.update\",\"status\":200}','2026-04-26 08:53:04','2026-04-26 08:53:04'),(4,1,'Admin','POST /livewire/update','127.0.0.1','{\"route\":\"default.livewire.update\",\"status\":200}','2026-04-26 08:54:08','2026-04-26 08:54:08'),(5,1,'Admin','POST /livewire/update','127.0.0.1','{\"route\":\"default.livewire.update\",\"status\":200}','2026-04-26 08:54:08','2026-04-26 08:54:08'),(6,1,'Admin','POST /livewire/update','127.0.0.1','{\"route\":\"default.livewire.update\",\"status\":200}','2026-04-26 08:54:17','2026-04-26 08:54:17'),(7,1,'Admin','POST /livewire/update','127.0.0.1','{\"route\":\"default.livewire.update\",\"status\":200}','2026-04-26 08:55:19','2026-04-26 08:55:19'),(8,1,'Admin','POST /livewire/update','127.0.0.1','{\"route\":\"default.livewire.update\",\"status\":200}','2026-04-26 08:55:20','2026-04-26 08:55:20'),(9,1,'Admin','POST /livewire/update','127.0.0.1','{\"route\":\"default.livewire.update\",\"status\":200}','2026-04-26 08:55:30','2026-04-26 08:55:30'),(10,1,'Admin','POST /livewire/update','127.0.0.1','{\"route\":\"default.livewire.update\",\"status\":200}','2026-04-26 08:55:31','2026-04-26 08:55:31'),(11,1,'Admin','POST /livewire/update','127.0.0.1','{\"route\":\"default.livewire.update\",\"status\":200}','2026-04-26 08:55:32','2026-04-26 08:55:32'),(12,1,'Admin','POST /livewire/update','127.0.0.1','{\"route\":\"default.livewire.update\",\"status\":200}','2026-04-26 08:55:33','2026-04-26 08:55:33'),(13,1,'Admin','POST /livewire/update','127.0.0.1','{\"route\":\"default.livewire.update\",\"status\":200}','2026-04-26 08:55:39','2026-04-26 08:55:39'),(14,1,'Admin','POST /livewire/update','127.0.0.1','{\"route\":\"default.livewire.update\",\"status\":200}','2026-04-26 08:55:40','2026-04-26 08:55:40'),(15,1,'Admin','Imported violations file: 4','127.0.0.1','{\"rows\":43,\"file\":\"imports\\/01KQ4V89D82ACRZ10QE9KZ7DWJ.xlsx\"}','2026-04-26 09:11:59','2026-04-26 09:11:59'),(16,1,'Admin','Updated authority: Judu','127.0.0.1',NULL,'2026-04-26 09:22:19','2026-04-26 09:22:19'),(17,1,'Admin','Updated authority: Unipark','127.0.0.1',NULL,'2026-04-26 09:22:35','2026-04-26 09:22:35'),(18,1,'Admin','Updated authority: Julianus inkaso','127.0.0.1',NULL,'2026-04-26 09:22:40','2026-04-26 09:22:40'),(19,1,'Admin','Updated authority: Unipark','127.0.0.1',NULL,'2026-04-26 09:23:46','2026-04-26 09:23:46'),(20,1,'Admin','Updated authority: Judu','127.0.0.1',NULL,'2026-04-26 09:23:55','2026-04-26 09:23:55'),(21,1,'Admin','Updated authority: Julianus inkaso','127.0.0.1',NULL,'2026-04-26 09:23:57','2026-04-26 09:23:57'),(22,1,'Admin','Updated authority: Vilniaus miesto savivaldybė','127.0.0.1',NULL,'2026-04-26 09:24:50','2026-04-26 09:24:50'),(23,1,'Admin','Imported violations file: 5','127.0.0.1','{\"rows\":43,\"file\":\"imports\\/01KQ4WCKH5KTHGCWYN3QBY2HM5.xlsx\"}','2026-04-26 09:31:49','2026-04-26 09:31:49'),(24,1,'Admin','Imported violations file: 6','127.0.0.1','{\"rows\":43,\"file\":\"imports\\/01KQ4X2MN4H6V98M1SB25YGXYZ.xlsx\"}','2026-04-26 09:43:52','2026-04-26 09:43:52'),(25,1,'Admin','Sent violation email: E1034606 (Import #6, Row 3)','127.0.0.1','{\"violation_id\":217,\"import_id\":6,\"row_number\":3,\"ticket_number\":\"E1034606\"}','2026-04-26 09:51:55','2026-04-26 09:51:55'),(26,1,'Admin','Sent violation email: 2797897 (Import #6, Row 4)','127.0.0.1','{\"violation_id\":218,\"import_id\":6,\"row_number\":4,\"ticket_number\":\"2797897\"}','2026-04-26 09:51:55','2026-04-26 09:51:55'),(27,1,'Admin','Imported violations file: 7','127.0.0.1','{\"rows\":43,\"file\":\"imports\\/01KQ4Y3TJF3E0AXKN0GZRTQF77.xlsx\"}','2026-04-26 10:01:59','2026-04-26 10:01:59'),(28,1,'Admin','Violation email FAIL: 26297905221 | Driver: RAIMONDAS SVIACKEVIČIUS | Email: sviaraim@gmail.com | Car plate: 02317717 (Import #7, Row 2)','127.0.0.1','{\"violation_id\":259,\"import_id\":7,\"row_number\":2,\"ticket_number\":\"26297905221\",\"driver_name\":\"RAIMONDAS SVIACKEVI\\u010cIUS\",\"driver_email\":\"sviaraim@gmail.com\",\"car_plate_number\":\"02317717\",\"send_status\":\"failed\"}','2026-04-26 10:10:26','2026-04-26 10:10:26'),(29,1,'Admin','Updated authority: Vilniaus miesto savivaldybė','127.0.0.1',NULL,'2026-04-26 10:22:40','2026-04-26 10:22:40'),(30,1,'Admin','Updated authority: Unipark','127.0.0.1',NULL,'2026-04-26 10:22:53','2026-04-26 10:22:53'),(31,1,'Admin','Updated authority: Judu','127.0.0.1',NULL,'2026-04-26 10:22:55','2026-04-26 10:22:55'),(32,1,'Admin','Updated authority: Julianus inkaso','127.0.0.1',NULL,'2026-04-26 10:22:57','2026-04-26 10:22:57'),(33,1,'Admin','Imported violations file: 8','127.0.0.1','{\"rows\":43,\"file\":\"imports\\/01KQ4ZDNMKCF9Q8DY3EY9B2GAA.xlsx\"}','2026-04-26 10:24:50','2026-04-26 10:24:50'),(34,1,'Admin','Violation email FAIL: 26297905221 | Driver: RAIMONDAS SVIACKEVIČIUS | Email: sviaraim@gmail.com | Car plate: NLV508 | Error: Authority Email is empty on this violation. (Import #8, Row 2)','127.0.0.1','{\"violation_id\":302,\"send_status\":\"failed\",\"send_error\":\"Authority Email is empty on this violation.\"}','2026-04-26 10:31:16','2026-04-26 10:31:16'),(35,1,'Admin','Violation email SUCCESS: 26297905221 | Driver: RAIMONDAS SVIACKEVIČIUS | Email: sviaraim@gmail.com | Car plate: NLV508 (Import #8, Row 2)','127.0.0.1','{\"violation_id\":302,\"import_id\":8,\"row_number\":2,\"ticket_number\":\"26297905221\",\"driver_name\":\"RAIMONDAS SVIACKEVI\\u010cIUS\",\"driver_email\":\"sviaraim@gmail.com\",\"car_plate_number\":\"NLV508\",\"send_status\":\"sent\"}','2026-04-26 10:34:38','2026-04-26 10:34:38'),(36,1,'Admin','Violation email SUCCESS: E1034606 | Driver: PHILIPP KNIERZINGER | Email: d.grundl@knierzinger.at | Car plate: MSH946 (Import #8, Row 3)','127.0.0.1','{\"violation_id\":303,\"import_id\":8,\"row_number\":3,\"ticket_number\":\"E1034606\",\"driver_name\":\"PHILIPP KNIERZINGER\",\"driver_email\":\"d.grundl@knierzinger.at\",\"car_plate_number\":\"MSH946\",\"send_status\":\"sent\"}','2026-04-26 10:41:10','2026-04-26 10:41:10'),(37,1,'Admin','Violation email SUCCESS: 2797897 | Driver: MAKSIM MOISA | Email: maksimmoisa@gmail.com | Car plate: NNR078 (Import #8, Row 4)','127.0.0.1','{\"violation_id\":304,\"import_id\":8,\"row_number\":4,\"ticket_number\":\"2797897\",\"driver_name\":\"MAKSIM MOISA\",\"driver_email\":\"maksimmoisa@gmail.com\",\"car_plate_number\":\"NNR078\",\"send_status\":\"sent\"}','2026-04-26 11:19:09','2026-04-26 11:19:09'),(38,1,'Admin','Viewed page: /preview/email/violation','127.0.0.1','{\"route\":null,\"status\":200}','2026-04-26 14:08:58','2026-04-26 14:08:58');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `authorities`
--

DROP TABLE IF EXISTS `authorities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `authorities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `from_email` varchar(255) DEFAULT NULL,
  `main_email` varchar(255) DEFAULT NULL,
  `to_email` varchar(255) DEFAULT NULL,
  `mail_template` text DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `authorities`
--

LOCK TABLES `authorities` WRITE;
/*!40000 ALTER TABLE `authorities` DISABLE KEYS */;
INSERT INTO `authorities` VALUES (1,'Vilniaus miesto savivaldybė','anam@vilnius.lt','info@vilnius.lt','anam@vilnius.lt','Pagal gautą Vilniaus miesto savivaldybės administracijos viešosios tvarkos grupės \npranešimą Nr. [%1%], pateikiama informacija apie asmenį, [%2%], \n[%3%], padariusį pažeidimą transporto priemone \nvalstybinis numeris [%4%]\n\nVardas, pavardė: [%6%]\nGimimo data arba asmens  kodas: [%7%]\nEl. Paštas: [%8%]\nAdresas: [%9%]\nTelefonas: [%10%]\n','info@vilnius.lt','2026-04-26 08:51:16','2026-04-26 10:22:40'),(2,'Unipark','no.reply@unipark.lt','info@unipark.lt','info@unipark.lt','Sveiki,\nautomobilį [%4%] pažeidimo metu vairavo:\n\nvardas, pavardė:  [%2%]\ngim. data : [%7%]\ntelefono nr.:  [%10%]\nel.paštas :  [%8%]\nvair. pažymėjimo numeris: [%5%]\nvair. pažymėjimo išdavimo data :  [%11%]\nvair.pažymėjimą išdavusi valstybė : [%12%]\nAdresas: [%9%]\n','info@unipark.lt','2026-04-26 08:51:16','2026-04-26 10:22:53'),(3,'Judu','noreply@judu.lt','info@judu.lt','info@judu.lt','Sveiki,\nautomobilį [%4%] pažeidimo metu vairavo:\n\nvardas, pavardė:  [%2%]\ngim. data : [%7%]\ntelefono nr.:  [%10%]\nel.paštas :  [%8%]\nvair. pažymėjimo numeris: [%5%]\nvair. pažymėjimo išdavimo data :  [%11%]\nvair.pažymėjimą išdavusi valstybė : [%12%]\nAdresas: [%9%]\n','info@judu.lt','2026-04-26 08:51:16','2026-04-26 10:22:55'),(4,'Julianus inkaso','klausimai@julianus.lt','info@julianus.lt','info@julianus.lt','Sveiki,\nautomobilį [%4%] pažeidimo metu vairavo:\n\nvardas, pavardė:  [%2%]\ngim. data : [%7%]\ntelefono nr.:  [%10%]\nel.paštas :  [%8%]\nvair. pažymėjimo numeris: [%5%]\nvair. pažymėjimo išdavimo data :  [%11%]\nvair.pažymėjimą išdavusi valstybė : [%12%]\nAdresas: [%9%]\n','info@julianus.lt','2026-04-26 08:51:16','2026-04-26 10:22:57');
/*!40000 ALTER TABLE `authorities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('livewire-rate-limiter:a17961fa74e9275d529f489537f179c05d50c2f3','i:1;',1777223305),('livewire-rate-limiter:a17961fa74e9275d529f489537f179c05d50c2f3:timer','i:1777223305;',1777223305);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_settings`
--

DROP TABLE IF EXISTS `email_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `from_name` varchar(255) DEFAULT NULL,
  `from_email` varchar(255) DEFAULT NULL,
  `reply_to` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_settings`
--

LOCK TABLES `email_settings` WRITE;
/*!40000 ALTER TABLE `email_settings` DISABLE KEYS */;
INSERT INTO `email_settings` VALUES (1,'Sit and go','liudas@greenlease.lt','liudas@greenlease.lt','Test violattion message','2026-04-26 10:06:48','2026-04-26 10:06:48');
/*!40000 ALTER TABLE `email_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `imports`
--

DROP TABLE IF EXISTS `imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `imports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file_path` varchar(255) NOT NULL,
  `imported_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `imported_at` datetime NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `imports_user_id_foreign` (`user_id`),
  CONSTRAINT `imports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `imports`
--

LOCK TABLES `imports` WRITE;
/*!40000 ALTER TABLE `imports` DISABLE KEYS */;
INSERT INTO `imports` VALUES (8,'imports/01KQ4ZDNMKCF9Q8DY3EY9B2GAA.xlsx',43,'2026-04-26 13:24:04',1,'2026-04-26 10:24:50','2026-04-26 10:24:50');
/*!40000 ALTER TABLE `imports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_04_16_000003_create_imports_table',1),(5,'2026_04_16_000004_create_authorities_table',1),(6,'2026_04_16_000005_create_violations_table',1),(7,'2026_04_17_000010_add_excel_columns_to_violations_table',1),(8,'2026_04_17_000011_backfill_violation_columns_from_row_data',1),(9,'2026_04_17_000020_change_imports_imported_at_to_datetime',1),(10,'2026_04_17_000030_add_status_to_violations_table',1),(11,'2026_04_26_000100_create_activity_logs_table',1),(12,'2026_04_26_001100_update_authorities_fields',1),(13,'2026_04_26_002000_add_selected_import_columns_to_violations_table',1),(14,'2026_04_26_002100_add_licence_issue_place_to_violations_table',1),(15,'2026_04_26_002200_add_agent_to_violations_table',1),(16,'2026_04_26_002300_add_driver_city_to_violations_table',1),(17,'2026_04_26_002400_add_authority_email_to_violations_table',1),(18,'2026_04_26_002500_create_email_settings_table',1),(19,'2026_04_26_002600_add_send_error_to_violations_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('82TpjA5NKMmThV1ZATEANhEh281Qxi6pjbGaywR3',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Cursor/3.2.11 Chrome/142.0.7444.265 Electron/39.8.1 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiVVM0SGRBbEhDb3RTa3lyUHM3Q1VqYmFTVU1BNEdDSjc4cFZNdGdweSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6NDU6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9wcmV2aWV3L2VtYWlsL3Zpb2xhdGlvbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1777223334),('h8lKcbOrrqHIaRpN4pOKVU9LFv7wZo6uzzQqBlSF',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','YTo2OntzOjY6Il90b2tlbiI7czo0MDoieWY5dUJnRG5EZGpJSldLanFCY1ZHNVpJUUJHbDlzaHNFWXlURDhaUyI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQ1OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvcHJldmlldy9lbWFpbC92aW9sYXRpb24iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjYwOiIkMnkkMTIkVEZtUkd4TmFWVHIuWWFPYjBKY2pELnBob3VUaml0L1guNnNnRGJSS1ZKcGJra2dWemNVbEMiO30=',1777223338);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin','liumis@liumis.com',NULL,'$2y$12$TFmRGxNaVTr.YaOb0JcjD.phouTjit/X.6sgDbRKVJpbkkgVzcUlC',NULL,'2026-04-16 02:03:06','2026-04-26 07:39:33');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `violations`
--

DROP TABLE IF EXISTS `violations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `violations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `import_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `row_number` int(10) unsigned NOT NULL,
  `station` varchar(255) DEFAULT NULL,
  `vehicle` varchar(255) DEFAULT NULL,
  `total_charge` varchar(255) DEFAULT NULL,
  `issuing_authority` varchar(255) DEFAULT NULL,
  `ticket_date` varchar(255) DEFAULT NULL,
  `ticket_number` varchar(255) DEFAULT NULL,
  `entry_date` varchar(255) DEFAULT NULL,
  `agr_no` varchar(255) DEFAULT NULL,
  `issued_by` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `driver_code` varchar(255) DEFAULT NULL,
  `driver` varchar(255) DEFAULT NULL,
  `corporate_code` varchar(255) DEFAULT NULL,
  `corporate` varchar(255) DEFAULT NULL,
  `tax_id` varchar(255) DEFAULT NULL,
  `tax_office` varchar(255) DEFAULT NULL,
  `violation_type` varchar(255) DEFAULT NULL,
  `hearing_date` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `zip_code` varchar(255) DEFAULT NULL,
  `driver_license` varchar(255) DEFAULT NULL,
  `customer_address` text DEFAULT NULL,
  `customer_city` varchar(255) DEFAULT NULL,
  `customer_zip_code` varchar(255) DEFAULT NULL,
  `customer_country` varchar(255) DEFAULT NULL,
  `payment_date` varchar(255) DEFAULT NULL,
  `invoice` varchar(255) DEFAULT NULL,
  `fine_received_date` varchar(255) DEFAULT NULL,
  `invoice_date` varchar(255) DEFAULT NULL,
  `driver_tax_id` varchar(255) DEFAULT NULL,
  `driver_id_number` varchar(255) DEFAULT NULL,
  `driver_address` text DEFAULT NULL,
  `driver_city` varchar(255) DEFAULT NULL,
  `driver_zip_code` varchar(255) DEFAULT NULL,
  `driver_country` varchar(255) DEFAULT NULL,
  `driver_state` varchar(255) DEFAULT NULL,
  `check_out` varchar(255) DEFAULT NULL,
  `check_in` varchar(255) DEFAULT NULL,
  `check_out_time` varchar(255) DEFAULT NULL,
  `check_in_time` varchar(255) DEFAULT NULL,
  `traffic_charge` varchar(255) DEFAULT NULL,
  `admin_fee` varchar(255) DEFAULT NULL,
  `contract_start_date` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `birth_date` varchar(255) DEFAULT NULL,
  `driver_telephone` varchar(255) DEFAULT NULL,
  `licence_issue_date` varchar(255) DEFAULT NULL,
  `customer_paid` varchar(255) DEFAULT NULL,
  `agent` varchar(255) DEFAULT NULL,
  `authority_email` varchar(255) DEFAULT NULL,
  `licence_issue_place` varchar(255) DEFAULT NULL,
  `row_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`row_data`)),
  `status` varchar(32) NOT NULL DEFAULT 'not_sent',
  `send_error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `violations_import_id_foreign` (`import_id`),
  KEY `violations_user_id_foreign` (`user_id`),
  CONSTRAINT `violations_import_id_foreign` FOREIGN KEY (`import_id`) REFERENCES `imports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `violations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=345 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `violations`
--

LOCK TABLES `violations` WRITE;
/*!40000 ALTER TABLE `violations` DISABLE KEYS */;
INSERT INTO `violations` VALUES (302,8,1,2,NULL,'NLV508',NULL,NULL,'2026-03-05','26297905221',NULL,'RNT-83159',NULL,NULL,NULL,'RAIMONDAS SVIACKEVIČIUS',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'02317717',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Mūrinė 42B',NULL,NULL,'LT',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'sviaraim@gmail.com','1975-05-26','+37068537056','2024-01-23','72.6','BTA Baltic Insurance Company','info@vilnius.lt','LT','[]','sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:34:38'),(303,8,1,3,NULL,'MSH946',NULL,NULL,'2026-02-25','E1034606',NULL,'RNT-83282',NULL,NULL,NULL,'PHILIPP KNIERZINGER',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'1806871',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Raning 6','Gnas',NULL,'AT',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'d.grundl@knierzinger.at','1974-05-29','+43166413558119','2003-03-28','72.6','Greenmotion International','info@vilnius.lt','AT','[]','sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:41:10'),(304,8,1,4,NULL,'NNR078',NULL,NULL,'2026-02-12','2797897',NULL,'RNT-82933',NULL,NULL,NULL,'MAKSIM MOISA',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'MOISA811162M99GA',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'FLAT 13','JORDAN COURT,FL-AWN WAY,EYNESB',NULL,'GB',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'maksimmoisa@gmail.com','1982-11-16','+447772125125','2025-01-21','72.6','Greenmotion International','info@vilnius.lt','GB','[]','sent',NULL,'2026-04-26 10:24:50','2026-04-26 11:19:09'),(305,8,1,5,NULL,'NJC415',NULL,NULL,'2026-02-19','26296761559',NULL,'RNT-83102',NULL,NULL,NULL,'SERGIO GALINDO CASAS',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'38105602-F',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Granda del Penedes 24 Str. Ap. 1','Barcelona',NULL,'ES',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'sergigalindo@yahoo.es','1970-07-22','+34630560181',NULL,'72.6','Greenmotion International','info@vilnius.lt','FR','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(306,8,1,6,NULL,'MPU376',NULL,NULL,'2026-02-17','24AD6C',NULL,'RNT-83071',NULL,NULL,NULL,'Jonathan Fernandez Herrera',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'17765679-L',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Feike de Boerlaan 104','Amsterdam',NULL,'NL',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'jonathanfernandez89@gmail.com','1997-10-05','441616025400','2022-06-15','72.6','Greenmotion International','info@vilnius.lt','ES','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(307,8,1,7,NULL,'NCY837',NULL,NULL,'2026-02-21','GNKEKP',NULL,'RNT-83133',NULL,NULL,NULL,'NUNO MIGUEL ALBUQUERQUE VALENTIM',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'1775210460',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Vloeienoe 80d.1','Kapellen',NULL,'BE',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'nvalentim@gmail.com','1983-04-02','+32485264235',NULL,'72.6','Greenmotion International','info@vilnius.lt','BE','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(308,8,1,8,NULL,'MDS751',NULL,NULL,'2026-01-23','714278102-6722659',NULL,'RNT-82601',NULL,NULL,NULL,'ZAMIR KEMULARIIA',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'9918507871',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'11 crra de Tenerife','Barcelona',NULL,'ES',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'special789@protonmail.com','1981-10-19','+3467406622','2020-10-24','72.6','Greenmotion International','info@vilnius.lt','RU','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(309,8,1,9,NULL,'NPJ881',NULL,NULL,'2026-01-31','GA30591424',NULL,'RNT-82587',NULL,NULL,NULL,'VIATCHESLAV KAGAN',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ID311763213',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Saerot 64 Ramat-ban','Jerusalem',NULL,'IL',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'slava15.92@gmail.com','1992-02-15','+972544569349','2016-01-25','72.6','Discover Car Hire (U) - POA','info@vilnius.lt','IL','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(310,8,1,10,NULL,'NYD368',NULL,NULL,'2026-03-06','E1035024',NULL,'RNT-83491',NULL,NULL,NULL,'Andreas Richard Hellingbrunner',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'5127946863',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Reizigerswej 183','Amsterdam',NULL,'NL',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ar.heilingbrunner@gmail.com','1966-05-23','+31644873128','2021-04-13','72.6','Greenmotion International','info@vilnius.lt','NL','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(311,8,1,11,NULL,'MGN894',NULL,NULL,'2026-03-02','444482',NULL,'RNT-83389',NULL,NULL,NULL,'OLEG MUSATOV',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'MUSAT710231O99LC',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'34 STIFFORD ROAD','AVELEY,ENDON',NULL,'LT',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'muswood71@gmail.com','1971-10-23','+491776475487','2023-01-28','72.6','Greenmotion International','info@vilnius.lt','GB','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(312,8,1,12,NULL,'NYD326',NULL,NULL,'2025-09-18','26297551835',NULL,'RNT-79798',NULL,NULL,NULL,'David Lanesman',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2033279',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Agadti 11','Tel Aviv',NULL,'IL',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'lanesmandavid@gmail.com','1955-01-27','441616025400','2025-01-08','72.6','Greenmotion International','info@vilnius.lt','IL','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(313,8,1,13,NULL,'NSC213',NULL,NULL,'2026-03-06','26297639705',NULL,'RNT-83420',NULL,NULL,NULL,'RAMUNE IMENICKIENE',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'56150232789',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'oterveien 25','Tromsoe',NULL,'NO',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ramune.imenickiene@gmail.com','1971-09-19','+47 97582793','2015-06-02','72.6','Greenmotion International','info@vilnius.lt','NO','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(314,8,1,14,NULL,'NDB417',NULL,NULL,'2025-12-28','714094881',NULL,'RNT-82064',NULL,NULL,NULL,'NINO NAMOCO',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'N04-99-329 184',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'84 ERMIN GARCIA ST.','CUBAO QUEZON CITY',NULL,'PH',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ninonamoco@gmail.com','1979-01-01','9992243254',NULL,'72.6','Greenmotion International','info@vilnius.lt','PH','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(315,8,1,15,NULL,'MTK804',NULL,NULL,'2026-03-11','A1354629',NULL,'RNT-83604',NULL,NULL,NULL,'MARCO SCHIAVINI',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'U110K3695J',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'4 Ameno','Regione Persighello',NULL,'IT',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'marcoschiavini@virgilio.it','1958-08-13','0039349080702','2022-07-08','72.6','Discover Car Hire (U) - POA','info@vilnius.lt','IT','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(316,8,1,16,NULL,'NRD882',NULL,NULL,'2026-03-02','K7M3B8',NULL,'RNT-83406',NULL,NULL,NULL,'Marcus Baank',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'5349193470',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'David ricardoweg 8','Zwaag',NULL,'NL',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Mbaank@allsorb.com','1968-07-09','0031617042276','2017-11-29','72.6','Greenmotion International','info@vilnius.lt','NL','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(317,8,1,17,NULL,'NSJ702',NULL,NULL,'2026-03-06','6HUL82',NULL,'RNT-83504',NULL,NULL,NULL,'OXANA POLICHSHUK',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'POLIC761199O99PA 43',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Viršuliškių g. 77-32','Vilnius',NULL,'LT',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'polioxy@gmail.com','1979-11-19','+447527932228','2021-12-09','72.6','Greenmotion International','info@vilnius.lt','GB','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(318,8,1,18,NULL,'NNO271',NULL,NULL,'2025-11-02','26297909892',NULL,'RNT-80967',NULL,NULL,NULL,'Yusuf Arikan',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'505535',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Cihangir Mahallesi, Kilim Sokak, NO:3-8 Avc&amp;#305;lar','ISTANBUL',NULL,'TR',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ysfark@gmail.com','1992-06-06','+195342653888','2010-12-31','72.6','Greenmotion International','info@vilnius.lt','TR','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(319,8,1,19,NULL,'NCI157',NULL,NULL,'2026-03-10','26298001079',NULL,'RNT-83551',NULL,NULL,NULL,'CHARLES-RAPHAÉL RENÉ BLANC',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'CO10AZ3HPR83',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Schopemhauris Str. 19','Berlin',NULL,'DE',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'charlesblanc40@gmail.com','1974-05-13','+491721411937','2025-12-09','72.6','Greenmotion International','info@vilnius.lt','DE','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(320,8,1,20,NULL,'MSY688',NULL,NULL,'2026-03-12','26298089926',NULL,'RNT-83605',NULL,NULL,NULL,'ION LAZAR',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'S0068004V',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'izvorulu 2','vatra dornei',NULL,'RO',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'strotexwood@gmail.com','1975-01-05','+40 753694881','2023-09-06','72.6','Greenmotion International','info@vilnius.lt','RO','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(321,8,1,21,NULL,'MSY688',NULL,NULL,'2026-03-11','26298445235',NULL,'RNT-83605',NULL,NULL,NULL,'ION LAZAR',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'S0068004V',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'izvorulu 2','vatra dornei',NULL,'RO',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'strotexwood@gmail.com','1975-01-05','+40 753694881','2023-09-06','72.6','Greenmotion International','info@vilnius.lt','RO','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(322,8,1,22,NULL,'NYU774',NULL,NULL,'2025-10-30','26298561338',NULL,'RNT-80913',NULL,NULL,NULL,'HSIU MIN CHENG',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'05965164',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'No. 65 Lane 395 chenggong Road','Taiping District,Taichang city',NULL,'TW',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'a32341224@yahoo.com.tw','1965-09-25','+88694984841','2024-02-02','72.6','Greenmotion International','info@vilnius.lt','TW','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(323,8,1,23,NULL,'NJU824',NULL,NULL,'2025-11-12','26298462131',NULL,'RNT-81167',NULL,NULL,NULL,'ONDŘEJ LODES',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'EP497815',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ONDREA LORES','LAMY 27061',NULL,'CZ',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'info@supplio.cz','1985-04-03','+420774346476','2022-08-30','72.6','Greenmotion International','info@vilnius.lt','CZ','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(324,8,1,24,NULL,'NLV512',NULL,NULL,'2026-03-18','A51-50921/26',NULL,'RNT-83479',NULL,NULL,NULL,'MINDAUGAS VARNAUSKAS',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'00697016',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Verkiu g. 50','Vilnius',NULL,'LT',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ieuprojects@gmail.com','1974-10-15',NULL,'2016-03-30','72.6','Greenmotion International','info@vilnius.lt','LT','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(325,8,1,25,NULL,'NJC415',NULL,NULL,'2026-03-20','26298294450',NULL,'RNT-83733',NULL,NULL,NULL,'MARTYNAS PILIPAS',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'1406514',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Taikos gatvė 20 - 34','Vilnius',NULL,'LT',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'pilipas.martynas@gmail.com','1989-03-25','+37067186280',NULL,'72.6','EASY ASSISTANCE','info@vilnius.lt','LT','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(326,8,1,26,NULL,'NYU765',NULL,NULL,'2025-09-15','26296760379',NULL,'RNT-79726',NULL,NULL,NULL,'YOUNG WHA LEE',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'13-90-628370-64',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Dongjak-gu','Seoul',NULL,'KR',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'black-123@hanmail.net','1957-12-17','+821055168151','2025-05-13','72.6','Greenmotion International','info@vilnius.lt','KR','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(327,8,1,27,NULL,'NJU643',NULL,NULL,'2025-09-29','26297252954',NULL,'RNT-80140',NULL,NULL,NULL,'Su jin Park',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'189308006112',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Dangsanro 214 406-1501, Yeongduengpogu','Seoul',NULL,'KR',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'CHEOLP@GMAIL.COM','1971-05-22','+821043514870','2018-10-10','72.6','Greenmotion International','info@vilnius.lt','KR','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(328,8,1,28,NULL,'NYD324',NULL,NULL,'2026-02-11','GA30638659',NULL,'RNT-82955',NULL,NULL,NULL,'ELDHO ANIL GEORGE',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'B5138441',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ITHAKKAN HOUSE MANAKKAPADY ASOKAPURAM P O','ALUVA',NULL,'IN',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'anilgthakkan@gmail.com','1995-10-09','+356 77761870',NULL,'72.6','Do You Spain (U) - POA','info@vilnius.lt','IN','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(329,8,1,29,NULL,'MOB823',NULL,NULL,'2026-03-02','3ZYDSX',NULL,'RNT-83368',NULL,NULL,NULL,'JORDI PIERRE CARABAL VÉLIZ',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'47321613-X',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Carrer Menorca 4','Barcelona',NULL,'ES',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'jordycarabaly@gmail.com','2003-01-08','+34651801007','2023-02-03','72.6','WALK-IN RES','info@vilnius.lt','ES','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(330,8,1,30,NULL,'NIV667',NULL,NULL,'2025-11-10','26298484804',NULL,'RNT-81129',NULL,NULL,NULL,'PARMINDER SINGH',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'25AZ94209',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'14 ROUTE DECLERMONT','BERNES-SUR-OISE',NULL,'FR',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'parminder_32@hotmail.com','1972-11-03','441616025400','2025-10-22','72.6','Greenmotion International','info@vilnius.lt','FR','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(331,8,1,31,NULL,'NIV667',NULL,NULL,'2025-11-12','26298484838',NULL,'RNT-81129',NULL,NULL,NULL,'PARMINDER SINGH',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'25AZ94209',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'14 ROUTE DECLERMONT','BERNES-SUR-OISE',NULL,'FR',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'parminder_32@hotmail.com','1972-11-03','441616025400','2025-10-22','72.6','Greenmotion International','info@vilnius.lt','FR','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(332,8,1,32,NULL,'NJL401',NULL,NULL,'2025-11-27','26298658498',NULL,'RNT-81521',NULL,NULL,NULL,'Andrius Kelpsa',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'92230479560',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Raneisvegen 307','Fagernes',NULL,'NO',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'andrius@j-atak.no','1989-09-01','+47 917 70 996','2023-03-16','72.6','Greenmotion International','info@vilnius.lt','NO','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(333,8,1,33,NULL,'NCG227',NULL,NULL,'2025-09-27','26296478949',NULL,'RNT-80078',NULL,NULL,NULL,'André Heller',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'M20000MAK11',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Hans-Oster-Straße 13A','Dresden',NULL,'DE',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'andre_heller@web.de','1970-07-04','+491723754417','2003-03-21','72.6','Greenmotion International','info@vilnius.lt','DE','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(334,8,1,34,NULL,'MDS751',NULL,NULL,'2026-01-25','714278160-6722661',NULL,'RNT-82601',NULL,NULL,NULL,'ZAMIR KEMULARIIA',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'9918507871',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'11 crra de Tenerife','Barcelona',NULL,'ES',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'special789@protonmail.com','1981-10-19','+3467406622','2020-10-24','72.6','Greenmotion International','info@vilnius.lt','RU','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(335,8,1,35,NULL,'MDS751',NULL,NULL,'2026-01-26','714278173-6722662',NULL,'RNT-82601',NULL,NULL,NULL,'ZAMIR KEMULARIIA',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'9918507871',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'11 crra de Tenerife','Barcelona',NULL,'ES',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'special789@protonmail.com','1981-10-19','+3467406622','2020-10-24','72.6','Greenmotion International','info@vilnius.lt','RU','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(336,8,1,36,NULL,'MJU260',NULL,NULL,'2026-02-22','26297093119',NULL,'RNT-83184',NULL,NULL,NULL,'Edgaras Kaminskis',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'01693693',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Pušų gatvė 5','Vilniaus rajonas',NULL,'LT',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'edviuxs123@gmail.com','1995-11-25','+37066409480','2021-01-03','72.6','WALK-IN RES','info@vilnius.lt','LT','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(337,8,1,37,NULL,'MRG615',NULL,NULL,'2026-02-16','26297662699',NULL,'RNT-83125',NULL,NULL,NULL,'Marius Mikučionis',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'15653636',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Kalno gatvė 69','Vilnius',NULL,'LT',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'marius.mikucionis@easyassistance.lt','1981-11-09','+370 (641) 55 666','2021-06-16','72.6','EASY ASSISTANCE','info@vilnius.lt','LT','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(338,8,1,38,NULL,'NLL370',NULL,NULL,'2026-01-12','714349866',NULL,'RNT-82298',NULL,NULL,NULL,'UAB \"SOLORENTA\"',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'72.6','WALK-IN RES','info@vilnius.lt',NULL,'[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(339,8,1,39,NULL,'NPJ883',NULL,NULL,'2026-03-05','YVGY9V',NULL,'RNT-83360',NULL,NULL,NULL,'Ales Kotasek',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'EQ595572',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Tesice, 204','Mikul&#269;ice',NULL,'CZ',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'aleskotasek@seznam.cz','1984-07-20','00420734513863','2023-10-04','72.6','Greenmotion International','info@vilnius.lt','CZ','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(340,8,1,40,NULL,'MJU260',NULL,NULL,'2026-03-11','445558',NULL,'RNT-83184',NULL,NULL,NULL,'Edgaras Kaminskis',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'01693693',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Pušų gatvė 5','Vilniaus rajonas',NULL,'LT',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'edviuxs123@gmail.com','1995-11-25','+37066409480','2021-01-03','72.6','WALK-IN RES','info@vilnius.lt','LT','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(341,8,1,41,NULL,'MFT121',NULL,NULL,'2026-03-10','RDLHMB',NULL,'RNT-83347',NULL,NULL,NULL,'ARTUR LIBERCHUK',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'ID304530892',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Hazav 1','Nes  Zona',NULL,'IL',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'liuba160570@gmail.com','1967-03-04','+972584030467','2017-02-06','72.6','Greenmotion International','info@vilnius.lt','IL','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(342,8,1,42,NULL,'MSY688',NULL,NULL,'2026-03-11','26298445250',NULL,'RNT-83605',NULL,NULL,NULL,'ION LAZAR',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'S0068004V',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'izvorulu 2','vatra dornei',NULL,'RO',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'strotexwood@gmail.com','1975-01-05','+40 753694881','2023-09-06','72.6','Greenmotion International','info@vilnius.lt','RO','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(343,8,1,43,NULL,'NTG986',NULL,NULL,'2026-03-31','A1360877',NULL,'RNT-84088',NULL,NULL,NULL,'Pierluigi Nicolis',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'VR5724521M',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Via Villa Girardi 29','San Pietro in Cariano',NULL,'IT',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'villameneghello@gmail.com','1998-11-18',NULL,'2017-10-25','72.6','Greenmotion International','info@vilnius.lt','IT','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50'),(344,8,1,44,NULL,'NPJ883',NULL,NULL,'2026-03-30','A1360519',NULL,'RNT-84024',NULL,NULL,NULL,'CRISTIAN TUDORICI',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'B00671438V',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Zaharia stancu no 18','Rasnov',NULL,'RO',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'cristian.tudorici@gmail.com','1966-01-01','+40723099929','2023-03-27','72.6','Greenmotion International','info@vilnius.lt','RO','[]','not_sent',NULL,'2026-04-26 10:24:50','2026-04-26 10:24:50');
/*!40000 ALTER TABLE `violations` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-26 20:15:05
