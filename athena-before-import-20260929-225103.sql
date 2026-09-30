-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: athena
-- ------------------------------------------------------
-- Server version	8.0.30

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `attribute_changes` json DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcement_images`
--

DROP TABLE IF EXISTS `announcement_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `announcement_images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `research_call_id` bigint unsigned DEFAULT NULL,
  `image_path` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `announcement_images_research_call_id_foreign` (`research_call_id`),
  KEY `announcement_images_archived_at_index` (`archived_at`),
  CONSTRAINT `announcement_images_research_call_id_foreign` FOREIGN KEY (`research_call_id`) REFERENCES `research_calls` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcement_images`
--

LOCK TABLES `announcement_images` WRITE;
/*!40000 ALTER TABLE `announcement_images` DISABLE KEYS */;
INSERT INTO `announcement_images` VALUES (1,NULL,'announcements/WToCR75ERr9QsAZNrEPS77F6qX9EpHYG0j05SAkl.png',NULL,'2026-07-26 04:16:25','2026-07-26 04:16:25');
/*!40000 ALTER TABLE `announcement_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('laravel-cache-356a192b7913b04c54574d18c28d46e6395428ab','i:1;',1790577155),('laravel-cache-356a192b7913b04c54574d18c28d46e6395428ab:timer','i:1790577155;',1790577155),('laravel-cache-424f74a6a7ed4d4ed4761507ebcd209a6ef0937b','i:2;',1790387444),('laravel-cache-424f74a6a7ed4d4ed4761507ebcd209a6ef0937b:timer','i:1790387444;',1790387444),('laravel-cache-spatie.permission.cache','a:3:{s:5:\"alias\";a:0:{}s:11:\"permissions\";a:0:{}s:5:\"roles\";a:0:{}}',1790779128);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
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
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
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
-- Table structure for table `harvested_literature_sources`
--

DROP TABLE IF EXISTS `harvested_literature_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `harvested_literature_sources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `repository_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `fingerprint` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `authors` text COLLATE utf8mb4_unicode_ci,
  `abstract` text COLLATE utf8mb4_unicode_ci,
  `publication_year` smallint unsigned DEFAULT NULL,
  `doi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `failure_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `queued_at` timestamp NULL DEFAULT NULL,
  `last_attempted_at` timestamp NULL DEFAULT NULL,
  `harvested_at` timestamp NULL DEFAULT NULL,
  `next_harvest_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `harvested_literature_sources_url_hash_unique` (`url_hash`),
  KEY `harvest_repository_due_index` (`repository_key`,`next_harvest_at`),
  KEY `harvest_status_year_index` (`status`,`publication_year`),
  KEY `harvested_literature_sources_repository_key_index` (`repository_key`),
  KEY `harvested_literature_sources_fingerprint_index` (`fingerprint`),
  KEY `harvested_literature_sources_doi_index` (`doi`),
  FULLTEXT KEY `harvest_metadata_fulltext` (`title`,`authors`,`abstract`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `harvested_literature_sources`
--

LOCK TABLES `harvested_literature_sources` WRITE;
/*!40000 ALTER TABLE `harvested_literature_sources` DISABLE KEYS */;
/*!40000 ALTER TABLE `harvested_literature_sources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
INSERT INTO `jobs` VALUES (1,'default','{\"uuid\":\"ee2c56e1-e7a0-41cc-85e9-2586ce3b3922\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":18:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:4;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:46:\\\"App\\\\Notifications\\\\ProposalActivityNotification\\\":8:{s:5:\\\"title\\\";s:29:\\\"Proposal workspace invitation\\\";s:7:\\\"message\\\";s:62:\\\"Neil Carlo Delmo invited you to collaborate on “Sample 2”.\\\";s:3:\\\"url\\\";s:54:\\\"http:\\/\\/localhost\\/athena-app\\/faculty\\/proposal-drafts\\/14\\\";s:5:\\\"level\\\";s:4:\\\"info\\\";s:7:\\\"topicId\\\";N;s:9:\\\"actionUrl\\\";s:71:\\\"http:\\/\\/localhost\\/athena-app\\/notifications\\/proposal-invitations\\/1\\/accept\\\";s:10:\\\"actionData\\\";a:2:{s:14:\\\"proposal_title\\\";s:8:\\\"Sample 2\\\";s:12:\\\"inviter_name\\\";s:16:\\\"Neil Carlo Delmo\\\";}s:2:\\\"id\\\";s:36:\\\"fe9f0c57-a4cb-4749-b6a6-45599a701acf\\\";}s:4:\\\"data\\\";a:7:{s:5:\\\"title\\\";s:29:\\\"Proposal workspace invitation\\\";s:7:\\\"message\\\";s:62:\\\"Neil Carlo Delmo invited you to collaborate on “Sample 2”.\\\";s:3:\\\"url\\\";s:54:\\\"http:\\/\\/localhost\\/athena-app\\/faculty\\/proposal-drafts\\/14\\\";s:5:\\\"level\\\";s:4:\\\"info\\\";s:8:\\\"topic_id\\\";N;s:10:\\\"action_url\\\";s:71:\\\"http:\\/\\/localhost\\/athena-app\\/notifications\\/proposal-invitations\\/1\\/accept\\\";s:11:\\\"action_data\\\";a:2:{s:14:\\\"proposal_title\\\";s:8:\\\"Sample 2\\\";s:12:\\\"inviter_name\\\";s:16:\\\"Neil Carlo Delmo\\\";}}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1784960904,\"delay\":null}',0,NULL,1784960904,1784960904),(2,'default','{\"uuid\":\"a3c02f1d-9fcf-495d-86ec-f9e9ce14fcce\",\"displayName\":\"App\\\\Notifications\\\\ProposalWorkspaceInvitation\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\",\"command\":\"O:48:\\\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\\\":4:{s:11:\\\"notifiables\\\";O:29:\\\"Illuminate\\\\Support\\\\Collection\\\":2:{s:8:\\\"\\u0000*\\u0000items\\\";a:1:{i:0;O:44:\\\"Illuminate\\\\Notifications\\\\AnonymousNotifiable\\\":1:{s:6:\\\"routes\\\";a:1:{s:4:\\\"mail\\\";a:1:{s:28:\\\"23-73453@g.batstate-u.edu.ph\\\";s:18:\\\"Djanisse Villaflor\\\";}}}}s:28:\\\"\\u0000*\\u0000escapeWhenCastingToString\\\";b:0;}s:12:\\\"notification\\\";O:45:\\\"App\\\\Notifications\\\\ProposalWorkspaceInvitation\\\":9:{s:13:\\\"recipientName\\\";s:18:\\\"Djanisse Villaflor\\\";s:11:\\\"inviterName\\\";s:16:\\\"Neil Carlo Delmo\\\";s:12:\\\"projectTitle\\\";s:8:\\\"Sample 2\\\";s:12:\\\"invitedEmail\\\";s:28:\\\"23-73453@g.batstate-u.edu.ph\\\";s:12:\\\"workspaceUrl\\\";s:54:\\\"http:\\/\\/localhost\\/athena-app\\/faculty\\/proposal-drafts\\/14\\\";s:13:\\\"accountLinked\\\";b:1;s:18:\\\"requiresAcceptance\\\";b:1;s:2:\\\"id\\\";s:36:\\\"8e6527ed-27ec-4cc9-80e1-b851b3ee2911\\\";s:11:\\\"afterCommit\\\";b:1;}s:8:\\\"channels\\\";a:1:{i:0;s:4:\\\"mail\\\";}s:11:\\\"afterCommit\\\";b:1;}\",\"batchId\":null},\"createdAt\":1784960905,\"delay\":null}',0,NULL,1784960905,1784960905),(3,'default','{\"uuid\":\"be28703b-e8a7-43fa-84cd-4779db822605\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":18:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:4;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:46:\\\"App\\\\Notifications\\\\ProposalActivityNotification\\\":8:{s:5:\\\"title\\\";s:29:\\\"Proposal workspace invitation\\\";s:7:\\\"message\\\";s:60:\\\"Neil Carlo Delmo invited you to collaborate on “Sample”.\\\";s:3:\\\"url\\\";s:54:\\\"http:\\/\\/localhost\\/athena-app\\/faculty\\/proposal-drafts\\/15\\\";s:5:\\\"level\\\";s:4:\\\"info\\\";s:7:\\\"topicId\\\";N;s:9:\\\"actionUrl\\\";s:71:\\\"http:\\/\\/localhost\\/athena-app\\/notifications\\/proposal-invitations\\/2\\/accept\\\";s:10:\\\"actionData\\\";a:2:{s:14:\\\"proposal_title\\\";s:6:\\\"Sample\\\";s:12:\\\"inviter_name\\\";s:16:\\\"Neil Carlo Delmo\\\";}s:2:\\\"id\\\";s:36:\\\"bf428d81-9f93-4854-83dd-6c5eaf27ca19\\\";}s:4:\\\"data\\\";a:7:{s:5:\\\"title\\\";s:29:\\\"Proposal workspace invitation\\\";s:7:\\\"message\\\";s:60:\\\"Neil Carlo Delmo invited you to collaborate on “Sample”.\\\";s:3:\\\"url\\\";s:54:\\\"http:\\/\\/localhost\\/athena-app\\/faculty\\/proposal-drafts\\/15\\\";s:5:\\\"level\\\";s:4:\\\"info\\\";s:8:\\\"topic_id\\\";N;s:10:\\\"action_url\\\";s:71:\\\"http:\\/\\/localhost\\/athena-app\\/notifications\\/proposal-invitations\\/2\\/accept\\\";s:11:\\\"action_data\\\";a:2:{s:14:\\\"proposal_title\\\";s:6:\\\"Sample\\\";s:12:\\\"inviter_name\\\";s:16:\\\"Neil Carlo Delmo\\\";}}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1785079903,\"delay\":null}',0,NULL,1785079903,1785079903),(4,'default','{\"uuid\":\"b0582248-b80f-4bcf-bf1d-9d49ef746a90\",\"displayName\":\"App\\\\Notifications\\\\ProposalWorkspaceInvitation\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\",\"command\":\"O:48:\\\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\\\":4:{s:11:\\\"notifiables\\\";O:29:\\\"Illuminate\\\\Support\\\\Collection\\\":2:{s:8:\\\"\\u0000*\\u0000items\\\";a:1:{i:0;O:44:\\\"Illuminate\\\\Notifications\\\\AnonymousNotifiable\\\":1:{s:6:\\\"routes\\\";a:1:{s:4:\\\"mail\\\";a:1:{s:28:\\\"23-73453@g.batstate-u.edu.ph\\\";s:18:\\\"Djanisse Villaflor\\\";}}}}s:28:\\\"\\u0000*\\u0000escapeWhenCastingToString\\\";b:0;}s:12:\\\"notification\\\";O:45:\\\"App\\\\Notifications\\\\ProposalWorkspaceInvitation\\\":9:{s:13:\\\"recipientName\\\";s:18:\\\"Djanisse Villaflor\\\";s:11:\\\"inviterName\\\";s:16:\\\"Neil Carlo Delmo\\\";s:12:\\\"projectTitle\\\";s:6:\\\"Sample\\\";s:12:\\\"invitedEmail\\\";s:28:\\\"23-73453@g.batstate-u.edu.ph\\\";s:12:\\\"workspaceUrl\\\";s:54:\\\"http:\\/\\/localhost\\/athena-app\\/faculty\\/proposal-drafts\\/15\\\";s:13:\\\"accountLinked\\\";b:1;s:18:\\\"requiresAcceptance\\\";b:1;s:2:\\\"id\\\";s:36:\\\"98d8fccd-10cb-484d-83a0-5a45eaa62936\\\";s:11:\\\"afterCommit\\\";b:1;}s:8:\\\"channels\\\";a:1:{i:0;s:4:\\\"mail\\\";}s:11:\\\"afterCommit\\\";b:1;}\",\"batchId\":null},\"createdAt\":1785079903,\"delay\":null}',0,NULL,1785079903,1785079903),(5,'default','{\"uuid\":\"1fe10f9c-19af-4934-98d7-2d5029097b61\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":18:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:46:\\\"App\\\\Notifications\\\\ProposalActivityNotification\\\":8:{s:5:\\\"title\\\";s:32:\\\"Collaborator accepted invitation\\\";s:7:\\\"message\\\";s:75:\\\"Djanisse Villaflor accepted your invitation to collaborate on “Sample”.\\\";s:3:\\\"url\\\";s:54:\\\"http:\\/\\/localhost\\/athena-app\\/faculty\\/proposal-drafts\\/15\\\";s:5:\\\"level\\\";s:4:\\\"info\\\";s:7:\\\"topicId\\\";N;s:9:\\\"actionUrl\\\";N;s:10:\\\"actionData\\\";a:0:{}s:2:\\\"id\\\";s:36:\\\"8d0ea597-e739-4db6-a8bd-b21441b4f8a4\\\";}s:4:\\\"data\\\";a:7:{s:5:\\\"title\\\";s:32:\\\"Collaborator accepted invitation\\\";s:7:\\\"message\\\";s:75:\\\"Djanisse Villaflor accepted your invitation to collaborate on “Sample”.\\\";s:3:\\\"url\\\";s:54:\\\"http:\\/\\/localhost\\/athena-app\\/faculty\\/proposal-drafts\\/15\\\";s:5:\\\"level\\\";s:4:\\\"info\\\";s:8:\\\"topic_id\\\";N;s:10:\\\"action_url\\\";N;s:11:\\\"action_data\\\";a:0:{}}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1785079937,\"delay\":null}',0,NULL,1785079937,1785079937),(6,'default','{\"uuid\":\"fca9f6b4-24df-4247-a5a8-84fca2a6a9d1\",\"displayName\":\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\",\"command\":\"O:48:\\\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\\\":4:{s:11:\\\"notifiables\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";a:1:{i:0;i:1;}s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:49:\\\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\\\":5:{s:14:\\\"researchCallId\\\";i:2;s:17:\\\"researchCallTitle\\\";s:26:\\\"AUGUST 2026 Implementation\\\";s:3:\\\"url\\\";s:42:\\\"http:\\/\\/localhost\\/athena-app\\/research-calls\\\";s:2:\\\"id\\\";s:36:\\\"fd57023c-3f84-4afd-9226-70857e4fcb86\\\";s:11:\\\"afterCommit\\\";b:1;}s:8:\\\"channels\\\";a:1:{i:0;s:8:\\\"database\\\";}s:11:\\\"afterCommit\\\";b:1;}\",\"batchId\":null},\"createdAt\":1785091398,\"delay\":null}',0,NULL,1785091398,1785091398),(7,'default','{\"uuid\":\"8643d9f7-8518-4cc0-8ab3-2ffb070873e6\",\"displayName\":\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\",\"command\":\"O:48:\\\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\\\":4:{s:11:\\\"notifiables\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";a:1:{i:0;i:1;}s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:49:\\\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\\\":5:{s:14:\\\"researchCallId\\\";i:2;s:17:\\\"researchCallTitle\\\";s:26:\\\"AUGUST 2026 Implementation\\\";s:3:\\\"url\\\";s:42:\\\"http:\\/\\/localhost\\/athena-app\\/research-calls\\\";s:2:\\\"id\\\";s:36:\\\"fd57023c-3f84-4afd-9226-70857e4fcb86\\\";s:11:\\\"afterCommit\\\";b:1;}s:8:\\\"channels\\\";a:1:{i:0;s:9:\\\"broadcast\\\";}s:11:\\\"afterCommit\\\";b:1;}\",\"batchId\":null},\"createdAt\":1785091398,\"delay\":null}',0,NULL,1785091398,1785091398),(8,'default','{\"uuid\":\"e39f5358-9462-4f79-929f-d6fdad2c45d8\",\"displayName\":\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\",\"command\":\"O:48:\\\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\\\":4:{s:11:\\\"notifiables\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";a:1:{i:0;i:2;}s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:49:\\\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\\\":5:{s:14:\\\"researchCallId\\\";i:2;s:17:\\\"researchCallTitle\\\";s:26:\\\"AUGUST 2026 Implementation\\\";s:3:\\\"url\\\";s:42:\\\"http:\\/\\/localhost\\/athena-app\\/research-calls\\\";s:2:\\\"id\\\";s:36:\\\"5ca97ef2-a880-41ad-a0b3-15ddbd85d273\\\";s:11:\\\"afterCommit\\\";b:1;}s:8:\\\"channels\\\";a:1:{i:0;s:8:\\\"database\\\";}s:11:\\\"afterCommit\\\";b:1;}\",\"batchId\":null},\"createdAt\":1785091398,\"delay\":null}',0,NULL,1785091398,1785091398),(9,'default','{\"uuid\":\"793f86b2-e6a5-477a-8e79-1ed0498ea441\",\"displayName\":\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\",\"command\":\"O:48:\\\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\\\":4:{s:11:\\\"notifiables\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";a:1:{i:0;i:2;}s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:49:\\\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\\\":5:{s:14:\\\"researchCallId\\\";i:2;s:17:\\\"researchCallTitle\\\";s:26:\\\"AUGUST 2026 Implementation\\\";s:3:\\\"url\\\";s:42:\\\"http:\\/\\/localhost\\/athena-app\\/research-calls\\\";s:2:\\\"id\\\";s:36:\\\"5ca97ef2-a880-41ad-a0b3-15ddbd85d273\\\";s:11:\\\"afterCommit\\\";b:1;}s:8:\\\"channels\\\";a:1:{i:0;s:9:\\\"broadcast\\\";}s:11:\\\"afterCommit\\\";b:1;}\",\"batchId\":null},\"createdAt\":1785091398,\"delay\":null}',0,NULL,1785091398,1785091398),(10,'default','{\"uuid\":\"b9f9b01d-c884-47e5-b766-91be98fb4058\",\"displayName\":\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\",\"command\":\"O:48:\\\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\\\":4:{s:11:\\\"notifiables\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";a:1:{i:0;i:3;}s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:49:\\\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\\\":5:{s:14:\\\"researchCallId\\\";i:2;s:17:\\\"researchCallTitle\\\";s:26:\\\"AUGUST 2026 Implementation\\\";s:3:\\\"url\\\";s:42:\\\"http:\\/\\/localhost\\/athena-app\\/research-calls\\\";s:2:\\\"id\\\";s:36:\\\"7fc8f61b-5042-4a9f-a6eb-0befe848efb2\\\";s:11:\\\"afterCommit\\\";b:1;}s:8:\\\"channels\\\";a:1:{i:0;s:8:\\\"database\\\";}s:11:\\\"afterCommit\\\";b:1;}\",\"batchId\":null},\"createdAt\":1785091398,\"delay\":null}',0,NULL,1785091398,1785091398),(11,'default','{\"uuid\":\"2679ae9f-b551-449c-82fe-b12e13081242\",\"displayName\":\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\",\"command\":\"O:48:\\\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\\\":4:{s:11:\\\"notifiables\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";a:1:{i:0;i:3;}s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:49:\\\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\\\":5:{s:14:\\\"researchCallId\\\";i:2;s:17:\\\"researchCallTitle\\\";s:26:\\\"AUGUST 2026 Implementation\\\";s:3:\\\"url\\\";s:42:\\\"http:\\/\\/localhost\\/athena-app\\/research-calls\\\";s:2:\\\"id\\\";s:36:\\\"7fc8f61b-5042-4a9f-a6eb-0befe848efb2\\\";s:11:\\\"afterCommit\\\";b:1;}s:8:\\\"channels\\\";a:1:{i:0;s:9:\\\"broadcast\\\";}s:11:\\\"afterCommit\\\";b:1;}\",\"batchId\":null},\"createdAt\":1785091398,\"delay\":null}',0,NULL,1785091398,1785091398),(12,'default','{\"uuid\":\"41a74db9-a338-4e52-ac84-b442dbaad892\",\"displayName\":\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\",\"command\":\"O:48:\\\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\\\":4:{s:11:\\\"notifiables\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";a:1:{i:0;i:4;}s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:49:\\\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\\\":5:{s:14:\\\"researchCallId\\\";i:2;s:17:\\\"researchCallTitle\\\";s:26:\\\"AUGUST 2026 Implementation\\\";s:3:\\\"url\\\";s:42:\\\"http:\\/\\/localhost\\/athena-app\\/research-calls\\\";s:2:\\\"id\\\";s:36:\\\"82cb9c65-87c2-427d-a0b0-09e51b8dd8d8\\\";s:11:\\\"afterCommit\\\";b:1;}s:8:\\\"channels\\\";a:1:{i:0;s:8:\\\"database\\\";}s:11:\\\"afterCommit\\\";b:1;}\",\"batchId\":null},\"createdAt\":1785091398,\"delay\":null}',0,NULL,1785091398,1785091398),(13,'default','{\"uuid\":\"72898ea0-9d3b-47dd-9be1-0bb32057c4bb\",\"displayName\":\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\",\"command\":\"O:48:\\\"Illuminate\\\\Notifications\\\\SendQueuedNotifications\\\":4:{s:11:\\\"notifiables\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";a:1:{i:0;i:4;}s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:49:\\\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\\\":5:{s:14:\\\"researchCallId\\\";i:2;s:17:\\\"researchCallTitle\\\";s:26:\\\"AUGUST 2026 Implementation\\\";s:3:\\\"url\\\";s:42:\\\"http:\\/\\/localhost\\/athena-app\\/research-calls\\\";s:2:\\\"id\\\";s:36:\\\"82cb9c65-87c2-427d-a0b0-09e51b8dd8d8\\\";s:11:\\\"afterCommit\\\";b:1;}s:8:\\\"channels\\\";a:1:{i:0;s:9:\\\"broadcast\\\";}s:11:\\\"afterCommit\\\";b:1;}\",\"batchId\":null},\"createdAt\":1785091398,\"delay\":null}',0,NULL,1785091398,1785091398),(14,'default','{\"uuid\":\"5953c80b-2680-45f4-a2a3-559b3eec36ee\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":18:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:49:\\\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\\\":5:{s:14:\\\"researchCallId\\\";i:2;s:17:\\\"researchCallTitle\\\";s:26:\\\"AUGUST 2026 Implementation\\\";s:3:\\\"url\\\";s:42:\\\"http:\\/\\/localhost\\/athena-app\\/research-calls\\\";s:2:\\\"id\\\";s:36:\\\"726786d6-d96e-497e-b0b5-e499d9324170\\\";s:11:\\\"afterCommit\\\";b:1;}s:4:\\\"data\\\";a:5:{s:5:\\\"title\\\";s:21:\\\"Research call updated\\\";s:7:\\\"message\\\";s:95:\\\"The research call “AUGUST 2026 Implementation” has been updated. Review the latest details.\\\";s:3:\\\"url\\\";s:42:\\\"http:\\/\\/localhost\\/athena-app\\/research-calls\\\";s:5:\\\"level\\\";s:4:\\\"info\\\";s:16:\\\"research_call_id\\\";i:2;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1785091695,\"delay\":null}',0,NULL,1785091695,1785091695),(15,'default','{\"uuid\":\"f4a72706-0132-48d8-a4e0-eda4c979fdf5\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":18:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:2;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:49:\\\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\\\":5:{s:14:\\\"researchCallId\\\";i:2;s:17:\\\"researchCallTitle\\\";s:26:\\\"AUGUST 2026 Implementation\\\";s:3:\\\"url\\\";s:42:\\\"http:\\/\\/localhost\\/athena-app\\/research-calls\\\";s:2:\\\"id\\\";s:36:\\\"000c867b-f74e-498f-8565-89825bdba71a\\\";s:11:\\\"afterCommit\\\";b:1;}s:4:\\\"data\\\";a:5:{s:5:\\\"title\\\";s:21:\\\"Research call updated\\\";s:7:\\\"message\\\";s:95:\\\"The research call “AUGUST 2026 Implementation” has been updated. Review the latest details.\\\";s:3:\\\"url\\\";s:42:\\\"http:\\/\\/localhost\\/athena-app\\/research-calls\\\";s:5:\\\"level\\\";s:4:\\\"info\\\";s:16:\\\"research_call_id\\\";i:2;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1785091695,\"delay\":null}',0,NULL,1785091695,1785091695),(16,'default','{\"uuid\":\"5f5515ab-6862-4346-bba6-8f85880f9934\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":18:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:3;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:49:\\\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\\\":5:{s:14:\\\"researchCallId\\\";i:2;s:17:\\\"researchCallTitle\\\";s:26:\\\"AUGUST 2026 Implementation\\\";s:3:\\\"url\\\";s:42:\\\"http:\\/\\/localhost\\/athena-app\\/research-calls\\\";s:2:\\\"id\\\";s:36:\\\"ffab70fc-794e-44e4-8425-cbc4c80e8ac2\\\";s:11:\\\"afterCommit\\\";b:1;}s:4:\\\"data\\\";a:5:{s:5:\\\"title\\\";s:21:\\\"Research call updated\\\";s:7:\\\"message\\\";s:95:\\\"The research call “AUGUST 2026 Implementation” has been updated. Review the latest details.\\\";s:3:\\\"url\\\";s:42:\\\"http:\\/\\/localhost\\/athena-app\\/research-calls\\\";s:5:\\\"level\\\";s:4:\\\"info\\\";s:16:\\\"research_call_id\\\";i:2;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1785091695,\"delay\":null}',0,NULL,1785091695,1785091695),(17,'default','{\"uuid\":\"74e8e7ac-5f15-4a2e-8535-8bc3543d7050\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":18:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:4;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:49:\\\"App\\\\Notifications\\\\ResearchCallUpdatedNotification\\\":5:{s:14:\\\"researchCallId\\\";i:2;s:17:\\\"researchCallTitle\\\";s:26:\\\"AUGUST 2026 Implementation\\\";s:3:\\\"url\\\";s:42:\\\"http:\\/\\/localhost\\/athena-app\\/research-calls\\\";s:2:\\\"id\\\";s:36:\\\"b2020d7e-2667-4f8e-9072-f520ede3a05d\\\";s:11:\\\"afterCommit\\\";b:1;}s:4:\\\"data\\\";a:5:{s:5:\\\"title\\\";s:21:\\\"Research call updated\\\";s:7:\\\"message\\\";s:95:\\\"The research call “AUGUST 2026 Implementation” has been updated. Review the latest details.\\\";s:3:\\\"url\\\";s:42:\\\"http:\\/\\/localhost\\/athena-app\\/research-calls\\\";s:5:\\\"level\\\";s:4:\\\"info\\\";s:16:\\\"research_call_id\\\";i:2;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1785091695,\"delay\":null}',0,NULL,1785091695,1785091695),(18,'default','{\"uuid\":\"a6cf78bf-8b5b-4bde-95df-18de65d76aca\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":18:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:46:\\\"App\\\\Notifications\\\\ProposalActivityNotification\\\":8:{s:5:\\\"title\\\";s:22:\\\"New proposal submitted\\\";s:7:\\\"message\\\";s:51:\\\"Neil Carlo Delmo submitted “Sample” for review.\\\";s:3:\\\"url\\\";s:36:\\\"http:\\/\\/localhost\\/athena-app\\/topics\\/1\\\";s:5:\\\"level\\\";s:4:\\\"info\\\";s:7:\\\"topicId\\\";i:1;s:9:\\\"actionUrl\\\";N;s:10:\\\"actionData\\\";a:0:{}s:2:\\\"id\\\";s:36:\\\"2f4898c0-4b0a-49d8-bbc5-17add190c87e\\\";}s:4:\\\"data\\\";a:7:{s:5:\\\"title\\\";s:22:\\\"New proposal submitted\\\";s:7:\\\"message\\\";s:51:\\\"Neil Carlo Delmo submitted “Sample” for review.\\\";s:3:\\\"url\\\";s:36:\\\"http:\\/\\/localhost\\/athena-app\\/topics\\/1\\\";s:5:\\\"level\\\";s:4:\\\"info\\\";s:8:\\\"topic_id\\\";i:1;s:10:\\\"action_url\\\";N;s:11:\\\"action_data\\\";a:0:{}}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1785094404,\"delay\":null}',0,NULL,1785094404,1785094404),(19,'default','{\"uuid\":\"c9920885-52e3-40cb-b748-379dbc5c9f58\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"deleteWhenMissingModels\":false,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":18:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:46:\\\"App\\\\Notifications\\\\ProposalActivityNotification\\\":9:{s:5:\\\"title\\\";s:18:\\\"Revision requested\\\";s:7:\\\"message\\\";s:97:\\\"1 proposal file(s) require changes in “Sample”. Review the comments and submit a new version.\\\";s:3:\\\"url\\\";s:36:\\\"http:\\/\\/localhost\\/athena-app\\/topics\\/1\\\";s:5:\\\"level\\\";s:7:\\\"warning\\\";s:7:\\\"topicId\\\";i:1;s:9:\\\"actionUrl\\\";N;s:10:\\\"actionData\\\";a:0:{}s:9:\\\"workspace\\\";N;s:2:\\\"id\\\";s:36:\\\"91a93388-1ac2-4690-8dfb-65696117c9e6\\\";}s:4:\\\"data\\\";a:8:{s:5:\\\"title\\\";s:18:\\\"Revision requested\\\";s:7:\\\"message\\\";s:97:\\\"1 proposal file(s) require changes in “Sample”. Review the comments and submit a new version.\\\";s:3:\\\"url\\\";s:36:\\\"http:\\/\\/localhost\\/athena-app\\/topics\\/1\\\";s:5:\\\"level\\\";s:7:\\\"warning\\\";s:8:\\\"topic_id\\\";i:1;s:10:\\\"action_url\\\";N;s:11:\\\"action_data\\\";a:0:{}s:9:\\\"workspace\\\";N;}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:23:\\\"deleteWhenMissingModels\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:12:\\\"messageGroup\\\";N;s:12:\\\"deduplicator\\\";N;s:13:\\\"debounceOwner\\\";s:0:\\\"\\\";s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\",\"batchId\":null},\"createdAt\":1785105342,\"delay\":null}',0,NULL,1785105343,1785105343);
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `literature_collection_source`
--

DROP TABLE IF EXISTS `literature_collection_source`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `literature_collection_source` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `literature_collection_id` bigint unsigned NOT NULL,
  `literature_source_id` bigint unsigned NOT NULL,
  `added_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `literature_collection_source_unique` (`literature_collection_id`,`literature_source_id`),
  KEY `literature_collection_source_literature_source_id_foreign` (`literature_source_id`),
  KEY `literature_collection_source_added_by_foreign` (`added_by`),
  CONSTRAINT `literature_collection_source_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `literature_collection_source_literature_collection_id_foreign` FOREIGN KEY (`literature_collection_id`) REFERENCES `literature_collections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `literature_collection_source_literature_source_id_foreign` FOREIGN KEY (`literature_source_id`) REFERENCES `literature_sources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `literature_collection_source`
--

LOCK TABLES `literature_collection_source` WRITE;
/*!40000 ALTER TABLE `literature_collection_source` DISABLE KEYS */;
/*!40000 ALTER TABLE `literature_collection_source` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `literature_collections`
--

DROP TABLE IF EXISTS `literature_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `literature_collections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_by` bigint unsigned DEFAULT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `literature_collections_slug_unique` (`slug`),
  KEY `literature_collections_created_by_foreign` (`created_by`),
  KEY `literature_collections_name_id_index` (`name`,`id`),
  CONSTRAINT `literature_collections_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `literature_collections`
--

LOCK TABLES `literature_collections` WRITE;
/*!40000 ALTER TABLE `literature_collections` DISABLE KEYS */;
/*!40000 ALTER TABLE `literature_collections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `literature_sources`
--

DROP TABLE IF EXISTS `literature_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `literature_sources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `added_by` bigint unsigned DEFAULT NULL,
  `fingerprint` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `authors` text COLLATE utf8mb4_unicode_ci,
  `abstract` text COLLATE utf8mb4_unicode_ci,
  `publication_year` smallint unsigned DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `venue` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `volume` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pages` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publisher` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` text COLLATE utf8mb4_unicode_ci,
  `full_text_url` text COLLATE utf8mb4_unicode_ci,
  `provider` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_identifier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `citation_count` int unsigned DEFAULT NULL,
  `is_open_access` tinyint(1) NOT NULL DEFAULT '0',
  `access_status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `publication_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `literature_sources_fingerprint_unique` (`fingerprint`),
  KEY `literature_sources_added_by_foreign` (`added_by`),
  KEY `literature_sources_created_at_id_index` (`created_at`,`id`),
  KEY `literature_sources_publication_year_index` (`publication_year`),
  KEY `literature_sources_doi_index` (`doi`),
  KEY `literature_sources_access_status_index` (`access_status`),
  CONSTRAINT `literature_sources_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `literature_sources`
--

LOCK TABLES `literature_sources` WRITE;
/*!40000 ALTER TABLE `literature_sources` DISABLE KEYS */;
/*!40000 ALTER TABLE `literature_sources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_06_26_130541_create_permission_tables',1),(5,'2026_06_27_090000_create_research_calls_and_categories_tables',1),(6,'2026_06_27_102810_create_topic_table',1),(7,'2026_06_27_111058_create_activity_log_table',1),(8,'2026_06_30_000002_create_topic_reviews_table',1),(9,'2026_06_30_000003_create_topic_expert_assignments_table',1),(10,'2026_06_30_000004_create_proposal_versions_table',1),(11,'2026_07_02_000001_create_notifications_table',1),(12,'2026_07_02_000002_create_proposal_version_files_table',1),(13,'2026_07_02_000003_create_proposal_templates_table',1),(14,'2026_07_03_000001_create_topic_review_file_revisions_table',1),(15,'2026_07_05_000000_rename_proposal_limit_to_research_workload_limit',1),(16,'2026_07_05_000001_make_legacy_proposal_metadata_optional',1),(17,'2026_07_05_000002_make_proposal_category_optional',1),(18,'2026_07_05_000003_add_workflow_stage_to_proposal_templates',1),(19,'2026_07_05_000004_enforce_research_call_budget_ceiling',1),(20,'2026_07_05_000005_move_legacy_proposal_template_paths',1),(21,'2026_07_10_000001_create_project_progress_reports_table',1),(22,'2026_07_15_215144_create_research_knowledge_entries_table',1),(23,'2026_07_16_155504_create_proposal_drafts_table',1),(24,'2026_07_16_155505_create_proposal_draft_documents_table',1),(25,'2026_07_16_155506_add_source_data_to_proposal_version_files_table',1),(26,'2026_07_16_222241_add_college_to_users_table',1),(27,'2026_07_17_184821_create_proposal_draft_members_table',1),(28,'2026_07_17_184822_add_lock_version_to_proposal_drafts_and_proposal_draft_documents_tables',1),(29,'2026_07_19_125846_create_proposal_draft_document_versions_table',1),(30,'2026_07_19_132558_archive_proposal_document_history_and_add_change_metadata',1),(31,'2026_07_21_211909_add_uploaded_by_to_proposal_version_files_table',2),(32,'2026_07_22_202055_create_proposal_file_annotations_table',2),(33,'2026_07_25_141339_add_accepted_at_to_proposal_draft_members_table',2),(34,'2026_07_26_055130_add_workflow_dates_and_reference_image_to_research_calls_table',3),(35,'2026_07_26_115843_create_announcement_images_table',4),(36,'2026_07_27_013825_create_research_assistant_conversations_table',5),(37,'2026_07_27_072202_add_topic_id_to_proposal_drafts_table',6),(38,'2026_07_30_120000_add_contact_number_to_users_table',7),(39,'2026_08_01_144357_add_notice_to_proceed_fields_to_topics_table',8),(40,'2026_08_01_182752_normalize_all_research_call_budgets_to_institutional_limit',9),(41,'2026_08_01_213903_add_monitoring_tool_data_to_project_progress_reports_table',10),(42,'2026_08_01_221810_create_project_narrative_reports_table',11),(43,'2026_08_01_234520_add_structured_content_to_project_narrative_reports_table',12),(44,'2026_08_02_144826_create_proposal_draft_literature_sources_table',13),(45,'2026_08_02_163803_create_shared_literature_library_tables',14),(46,'2026_08_08_184507_add_research_call_id_to_announcement_images_table',15),(47,'2026_08_08_192032_add_notice_to_proceed_data_to_topics_table',16),(48,'2026_08_08_203950_create_topic_collaborators_table',17),(49,'2026_08_09_104140_add_required_signature_file_ids_to_topic_reviews_table',18),(50,'2026_08_09_111918_add_preparation_fields_to_project_reports_tables',19),(51,'2026_08_09_121322_add_rrl_upgrade_fields_to_literature_tables',20),(52,'2026_08_09_161523_add_archived_at_to_announcement_images_table',21),(53,'2026_08_09_213205_add_opening_notification_tracking_to_research_calls_table',22),(54,'2026_08_09_230018_add_signature_lifecycle_to_proposal_files_and_reviews',23),(55,'2026_08_09_233501_add_quarter_version_fields_to_project_progress_reports_table',24),(56,'2026_08_11_165123_create_project_monitoring_drafts_table',25),(57,'2026_08_11_170000_create_project_narrative_report_drafts_table',26),(58,'2026_08_11_194909_add_research_context_to_proposal_draft_literature_sources_table',27),(59,'2026_08_15_174929_add_summary_to_research_assistant_conversations_table',28),(60,'2026_08_24_224219_make_research_call_optional_for_proposal_drafts_table',29),(61,'2026_08_25_223730_create_research_call_deadline_dismissals_table',30),(62,'2026_08_28_232548_create_proposal_file_review_checks_table',31),(63,'2026_08_29_115711_restore_unread_research_head_review_notifications',32),(64,'2026_08_30_120000_add_editor_target_to_proposal_file_annotations_table',33),(65,'2026_08_31_114508_create_harvested_literature_sources_table',34),(66,'2026_09_01_094949_add_faculty_resolution_to_topic_review_file_revisions_table',35),(67,'2026_09_06_114149_create_personal_reminders_table',36),(68,'2026_09_06_114150_create_proposal_stage_transitions_table',37),(69,'2026_09_06_153118_add_feedback_source_to_proposal_file_annotations',38),(70,'2026_09_06_210627_add_lrec_review_rounds_to_proposals',39),(71,'2026_09_07_091026_make_research_call_optional_for_topics',40),(72,'2026_09_07_182101_add_reporting_periods_and_terminal_report_type',41),(73,'2026_09_07_202214_create_proposal_similarity_checks_table',42),(74,'2026_09_08_105144_create_proposal_signatories_table',43),(75,'2026_09_08_122247_add_terminal_content_and_separate_narrative_drafts',44),(76,'2026_09_12_194818_create_project_conferences_table',45),(77,'2026_09_20_092327_add_research_secretary_to_project_monitoring',46),(78,'2026_09_25_000000_add_project_roles_to_research_team_members',47),(79,'2026_09_26_095535_create_project_documents_table',48),(80,'2026_09_26_231840_add_research_head_viewed_version_id_to_topics_table',48);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (1,'App\\Models\\User',1),(2,'App\\Models\\User',2),(4,'App\\Models\\User',3),(2,'App\\Models\\User',4),(5,'App\\Models\\User',4);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES ('000c867b-f74e-498f-8565-89825bdba71a','App\\Notifications\\ResearchCallUpdatedNotification','App\\Models\\User',2,'{\"title\":\"Research call updated\",\"message\":\"The research call \\u201cAUGUST 2026 Implementation\\u201d has been updated. Review the latest details.\",\"url\":\"http:\\/\\/localhost\\/athena-app\\/research-calls\",\"level\":\"info\",\"research_call_id\":2}',NULL,'2026-07-26 18:48:15','2026-07-26 18:48:15'),('2f4898c0-4b0a-49d8-bbc5-17add190c87e','App\\Notifications\\ProposalActivityNotification','App\\Models\\User',1,'{\"title\":\"New proposal submitted\",\"message\":\"Neil Carlo Delmo submitted \\u201cSample\\u201d for review.\",\"url\":\"http:\\/\\/localhost\\/athena-app\\/topics\\/1\",\"level\":\"info\",\"topic_id\":1,\"action_url\":null,\"action_data\":[]}','2026-07-26 19:33:59','2026-07-26 19:33:24','2026-07-26 19:33:59'),('726786d6-d96e-497e-b0b5-e499d9324170','App\\Notifications\\ResearchCallUpdatedNotification','App\\Models\\User',1,'{\"title\":\"Research call updated\",\"message\":\"The research call \\u201cAUGUST 2026 Implementation\\u201d has been updated. Review the latest details.\",\"url\":\"http:\\/\\/localhost\\/athena-app\\/research-calls\",\"level\":\"info\",\"research_call_id\":2}','2026-07-26 19:31:14','2026-07-26 18:48:15','2026-07-26 19:31:14'),('8d0ea597-e739-4db6-a8bd-b21441b4f8a4','App\\Notifications\\ProposalActivityNotification','App\\Models\\User',1,'{\"title\":\"Collaborator accepted invitation\",\"message\":\"Djanisse Villaflor accepted your invitation to collaborate on \\u201cSample\\u201d.\",\"url\":\"http:\\/\\/localhost\\/athena-app\\/faculty\\/proposal-drafts\\/15\",\"level\":\"info\",\"topic_id\":null,\"action_url\":null,\"action_data\":[]}','2026-07-26 17:14:35','2026-07-26 15:32:17','2026-07-26 17:14:35'),('91a93388-1ac2-4690-8dfb-65696117c9e6','App\\Notifications\\ProposalActivityNotification','App\\Models\\User',1,'{\"title\":\"Revision requested\",\"message\":\"1 proposal file(s) require changes in \\u201cSample\\u201d. Review the comments and submit a new version.\",\"url\":\"http:\\/\\/localhost\\/athena-app\\/topics\\/1\",\"level\":\"warning\",\"topic_id\":1,\"action_url\":null,\"action_data\":[],\"workspace\":null}','2026-07-26 22:36:40','2026-07-26 22:35:42','2026-07-26 22:36:40'),('b2020d7e-2667-4f8e-9072-f520ede3a05d','App\\Notifications\\ResearchCallUpdatedNotification','App\\Models\\User',4,'{\"title\":\"Research call updated\",\"message\":\"The research call \\u201cAUGUST 2026 Implementation\\u201d has been updated. Review the latest details.\",\"url\":\"http:\\/\\/localhost\\/athena-app\\/research-calls\",\"level\":\"info\",\"research_call_id\":2}','2026-07-26 19:15:00','2026-07-26 18:48:15','2026-07-26 19:15:00'),('bf428d81-9f93-4854-83dd-6c5eaf27ca19','App\\Notifications\\ProposalActivityNotification','App\\Models\\User',4,'{\"title\":\"Proposal workspace invitation\",\"message\":\"Neil Carlo Delmo invited you to collaborate on \\u201cSample\\u201d.\",\"url\":\"http:\\/\\/localhost\\/athena-app\\/faculty\\/proposal-drafts\\/15\",\"level\":\"info\",\"topic_id\":null,\"action_url\":\"http:\\/\\/localhost\\/athena-app\\/notifications\\/proposal-invitations\\/2\\/accept\",\"action_data\":{\"proposal_title\":\"Sample\",\"inviter_name\":\"Neil Carlo Delmo\"}}','2026-07-26 15:32:18','2026-07-26 15:31:42','2026-07-26 15:32:18'),('fe9f0c57-a4cb-4749-b6a6-45599a701acf','App\\Notifications\\ProposalActivityNotification','App\\Models\\User',4,'{\"title\":\"Proposal workspace invitation\",\"message\":\"Neil Carlo Delmo invited you to collaborate on \\u201cSample 2\\u201d.\",\"url\":\"http:\\/\\/localhost\\/athena-app\\/faculty\\/proposal-drafts\\/14\",\"level\":\"info\",\"topic_id\":null,\"action_url\":\"http:\\/\\/localhost\\/athena-app\\/notifications\\/proposal-invitations\\/1\\/accept\",\"action_data\":{\"proposal_title\":\"Sample 2\",\"inviter_name\":\"Neil Carlo Delmo\"}}','2026-07-25 06:29:27','2026-07-25 06:28:23','2026-07-25 06:29:27'),('ffab70fc-794e-44e4-8425-cbc4c80e8ac2','App\\Notifications\\ResearchCallUpdatedNotification','App\\Models\\User',3,'{\"title\":\"Research call updated\",\"message\":\"The research call \\u201cAUGUST 2026 Implementation\\u201d has been updated. Review the latest details.\",\"url\":\"http:\\/\\/localhost\\/athena-app\\/research-calls\",\"level\":\"info\",\"research_call_id\":2}',NULL,'2026-07-26 18:48:15','2026-07-26 18:48:15');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
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
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_reminders`
--

DROP TABLE IF EXISTS `personal_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_reminders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `starts_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `personal_reminders_user_id_starts_at_index` (`user_id`,`starts_at`),
  CONSTRAINT `personal_reminders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_reminders`
--

LOCK TABLES `personal_reminders` WRITE;
/*!40000 ALTER TABLE `personal_reminders` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_reminders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_conferences`
--

DROP TABLE IF EXISTS `project_conferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_conferences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint unsigned NOT NULL,
  `added_by` bigint unsigned NOT NULL,
  `fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `official_url` text COLLATE utf8mb4_unicode_ci,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Researcher entry',
  `location` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submission_deadline` date DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `attendance_mode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fees` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publication_details` text COLLATE utf8mb4_unicode_ci,
  `source_checked_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'shortlisted',
  `submitted_on` date DEFAULT NULL,
  `accepted_on` date DEFAULT NULL,
  `presented_on` date DEFAULT NULL,
  `evidence_url` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_conferences_topic_id_fingerprint_unique` (`topic_id`,`fingerprint`),
  KEY `project_conferences_added_by_foreign` (`added_by`),
  CONSTRAINT `project_conferences_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`),
  CONSTRAINT `project_conferences_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_conferences`
--

LOCK TABLES `project_conferences` WRITE;
/*!40000 ALTER TABLE `project_conferences` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_conferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_documents`
--

DROP TABLE IF EXISTS `project_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint unsigned NOT NULL,
  `uploaded_by` bigint unsigned NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `checksum` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_documents_uploaded_by_foreign` (`uploaded_by`),
  KEY `project_documents_topic_id_category_created_at_index` (`topic_id`,`category`,`created_at`),
  KEY `project_documents_category_index` (`category`),
  CONSTRAINT `project_documents_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_documents`
--

LOCK TABLES `project_documents` WRITE;
/*!40000 ALTER TABLE `project_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_monitoring_drafts`
--

DROP TABLE IF EXISTS `project_monitoring_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_monitoring_drafts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `source_report_id` bigint unsigned DEFAULT NULL,
  `source_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_data` json NOT NULL,
  `lock_version` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_monitoring_drafts_topic_id_user_id_source_key_unique` (`topic_id`,`user_id`,`source_key`),
  KEY `project_monitoring_drafts_user_id_foreign` (`user_id`),
  KEY `project_monitoring_drafts_source_report_id_foreign` (`source_report_id`),
  CONSTRAINT `project_monitoring_drafts_source_report_id_foreign` FOREIGN KEY (`source_report_id`) REFERENCES `project_progress_reports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_monitoring_drafts_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_monitoring_drafts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_monitoring_drafts`
--

LOCK TABLES `project_monitoring_drafts` WRITE;
/*!40000 ALTER TABLE `project_monitoring_drafts` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_monitoring_drafts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_narrative_report_drafts`
--

DROP TABLE IF EXISTS `project_narrative_report_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_narrative_report_drafts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `source_data` json NOT NULL,
  `lock_version` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `report_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'progress',
  PRIMARY KEY (`id`),
  UNIQUE KEY `narrative_draft_project_user_type_unique` (`topic_id`,`user_id`,`report_type`),
  KEY `project_narrative_report_drafts_user_id_foreign` (`user_id`),
  CONSTRAINT `project_narrative_report_drafts_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_narrative_report_drafts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_narrative_report_drafts`
--

LOCK TABLES `project_narrative_report_drafts` WRITE;
/*!40000 ALTER TABLE `project_narrative_report_drafts` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_narrative_report_drafts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_narrative_reports`
--

DROP TABLE IF EXISTS `project_narrative_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_narrative_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint unsigned NOT NULL,
  `submitted_by` bigint unsigned NOT NULL,
  `submission_date` date NOT NULL,
  `tracking_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `researchers` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `implementation_start` date NOT NULL,
  `implementation_end` date NOT NULL,
  `budget` decimal(12,2) NOT NULL,
  `funding_agency` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accomplishment_summary` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `accomplishments` json DEFAULT NULL,
  `introduction` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `rationale` longtext COLLATE utf8mb4_unicode_ci,
  `objectives` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `methodology` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `results_discussion` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `photos` json NOT NULL,
  `submission_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `official_pdf_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `official_pdf_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `official_pdf_checksum` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `official_pdf_size` bigint unsigned DEFAULT NULL,
  `prepared_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `prepared_by_date_signed` date DEFAULT NULL,
  `review_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `research_head_remarks` text COLLATE utf8mb4_unicode_ci,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `report_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'progress',
  `terminal_data` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_narrative_reports_submitted_by_foreign` (`submitted_by`),
  KEY `project_narrative_reports_reviewed_by_foreign` (`reviewed_by`),
  KEY `project_narrative_reports_topic_id_submission_date_index` (`topic_id`,`submission_date`),
  KEY `project_narrative_reports_review_status_index` (`review_status`),
  KEY `project_narrative_reports_submission_status_index` (`submission_status`),
  CONSTRAINT `project_narrative_reports_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_narrative_reports_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_narrative_reports_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_narrative_reports`
--

LOCK TABLES `project_narrative_reports` WRITE;
/*!40000 ALTER TABLE `project_narrative_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_narrative_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_progress_reports`
--

DROP TABLE IF EXISTS `project_progress_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_progress_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint unsigned NOT NULL,
  `submitted_by` bigint unsigned NOT NULL,
  `reporting_date` date NOT NULL,
  `reporting_year` smallint unsigned DEFAULT NULL,
  `reporting_quarter` tinyint unsigned DEFAULT NULL,
  `version_number` smallint unsigned NOT NULL DEFAULT '1',
  `supersedes_report_id` bigint unsigned DEFAULT NULL,
  `tracking_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `progress_percentage` tinyint unsigned NOT NULL,
  `accomplishments` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `issues` text COLLATE utf8mb4_unicode_ci,
  `work_plan` json DEFAULT NULL,
  `budget_utilization` json DEFAULT NULL,
  `budget_prepared_by` bigint unsigned DEFAULT NULL,
  `budget_prepared_at` timestamp NULL DEFAULT NULL,
  `prepared_by_date_signed` date DEFAULT NULL,
  `attachment_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submission_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `official_pdf_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `official_pdf_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `official_pdf_checksum` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `official_pdf_size` bigint unsigned DEFAULT NULL,
  `prepared_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `review_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `research_head_remarks` text COLLATE utf8mb4_unicode_ci,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `progress_reports_quarter_version_unique` (`topic_id`,`reporting_year`,`reporting_quarter`,`version_number`),
  KEY `project_progress_reports_submitted_by_foreign` (`submitted_by`),
  KEY `project_progress_reports_reviewed_by_foreign` (`reviewed_by`),
  KEY `project_progress_reports_topic_id_reporting_date_index` (`topic_id`,`reporting_date`),
  KEY `project_progress_reports_submission_status_index` (`submission_status`),
  KEY `project_progress_reports_supersedes_report_id_foreign` (`supersedes_report_id`),
  KEY `progress_reports_quarter_index` (`topic_id`,`reporting_year`,`reporting_quarter`),
  KEY `project_progress_reports_budget_prepared_by_foreign` (`budget_prepared_by`),
  CONSTRAINT `project_progress_reports_budget_prepared_by_foreign` FOREIGN KEY (`budget_prepared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_progress_reports_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_progress_reports_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_progress_reports_supersedes_report_id_foreign` FOREIGN KEY (`supersedes_report_id`) REFERENCES `project_progress_reports` (`id`),
  CONSTRAINT `project_progress_reports_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_progress_reports`
--

LOCK TABLES `project_progress_reports` WRITE;
/*!40000 ALTER TABLE `project_progress_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_progress_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_draft_document_versions`
--

DROP TABLE IF EXISTS `proposal_draft_document_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_draft_document_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proposal_draft_id` bigint unsigned DEFAULT NULL,
  `topic_id` bigint unsigned DEFAULT NULL,
  `proposal_draft_document_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `document_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` smallint unsigned NOT NULL DEFAULT '0',
  `version_number` int unsigned NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT '1',
  `action` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'saved',
  `change_note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `change_summary` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changes` json DEFAULT NULL,
  `restored_from_version_id` bigint unsigned DEFAULT NULL,
  `source_data` json DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `checksum` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_draft_document_version_unique` (`proposal_draft_id`,`document_type`,`position`,`version_number`),
  KEY `draft_doc_versions_document_fk` (`proposal_draft_document_id`),
  KEY `draft_doc_versions_creator_fk` (`created_by`),
  KEY `draft_doc_versions_draft_created_index` (`proposal_draft_id`,`created_at`),
  KEY `draft_doc_versions_current_index` (`proposal_draft_id`,`document_type`,`is_current`),
  KEY `draft_doc_versions_restored_fk` (`restored_from_version_id`),
  KEY `draft_doc_versions_topic_created_index` (`topic_id`,`created_at`),
  CONSTRAINT `draft_doc_versions_creator_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `draft_doc_versions_document_fk` FOREIGN KEY (`proposal_draft_document_id`) REFERENCES `proposal_draft_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `draft_doc_versions_draft_fk` FOREIGN KEY (`proposal_draft_id`) REFERENCES `proposal_drafts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `draft_doc_versions_restored_fk` FOREIGN KEY (`restored_from_version_id`) REFERENCES `proposal_draft_document_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `draft_doc_versions_topic_fk` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_draft_document_versions`
--

LOCK TABLES `proposal_draft_document_versions` WRITE;
/*!40000 ALTER TABLE `proposal_draft_document_versions` DISABLE KEYS */;
INSERT INTO `proposal_draft_document_versions` VALUES (1,5,NULL,1,4,'detailed_proposal',0,1,1,'saved',NULL,'Completed Detailed Research Proposal.','[]',NULL,'{\"sdgs\": [\"8\"], \"staff\": null, \"rationale\": \"SAMPLE\", \"objectives\": \"SAMPLE\", \"references\": \"SAMPLE\", \"methodology\": {\"data_analysis\": \"SAMPLE\", \"research_design\": \"SAMPLE\", \"specific_methods\": \"SAMPLE\"}, \"leader_email\": \"23-73453@g.batstate-u.edu.ph\", \"leader_contact\": \"09778666545\", \"executive_brief\": \"SAMPLE\", \"research_agenda\": \"SAMPLE\", \"expected_outputs\": {\"patent\": \"SAMPLE\", \"policy\": \"SAMPLE\", \"product\": \"SAMPLE\", \"publication\": \"SAMPLE\", \"social_impact\": \"SAMPLE\", \"people_service\": \"SAMPLE\", \"economic_impact\": \"SAMPLE\", \"place_partnership\": \"SAMPLE\"}, \"proponent_campus\": \"ARASOF-Nasugbu\", \"responsibilities\": [{\"name\": \"Djanisse Villaflor\", \"duties\": \"SAMPLE\"}], \"proponent_college\": \"COLLEGE OF INFORMATICS AND COMPUTING SCIENCES\", \"cooperating_agency\": null, \"related_literature\": \"SAMPLE\", \"proponent_department\": \"CICS\"}',NULL,NULL,NULL,NULL,NULL,'2026-07-20 01:48:05','2026-07-20 01:48:06','2026-07-20 01:48:06'),(2,10,NULL,2,4,'expense_breakdown',0,1,0,'saved',NULL,'Completed Estimated Expense Breakdown.','[]',NULL,'{\"items\": [{\"unit\": \"pc\", \"account\": \"Communication Expenses\", \"details\": \"Sample\", \"purpose\": \"Sample\", \"category\": \"mooe\", \"quantity\": \"10\", \"unit_cost\": \"24.00\", \"particulars\": \"Credit Card\", \"sub_account\": \"Sample\"}, {\"unit\": \"hours\", \"account\": \"Professional Services\", \"details\": \"Back End Developer\", \"purpose\": \"rESPINSIBLE FOR dEVELOPMENT\", \"category\": \"capital_outlay\", \"quantity\": \"240\", \"unit_cost\": \"320\", \"particulars\": \"Back End Developer\", \"sub_account\": \"Other Professional Services\"}]}',NULL,NULL,NULL,NULL,NULL,'2026-07-20 23:30:26','2026-07-20 23:30:27','2026-07-20 23:33:28'),(3,10,NULL,2,4,'expense_breakdown',0,2,1,'saved',NULL,'Updated Estimated Expense Breakdown (1 field changed).','[{\"after\": \"Updated content (2 items)\", \"field\": \"source_data.items\", \"label\": \"Estimated expense items\", \"before\": \"Previous content (2 items)\"}]',NULL,'{\"items\": [{\"unit\": \"pc\", \"account\": \"Communication Expenses\", \"details\": \"Sample\", \"purpose\": \"Sample\", \"category\": \"mooe\", \"quantity\": \"10\", \"unit_cost\": \"200.00\", \"particulars\": \"Prepaid Call\", \"sub_account\": \"Telephone Expences\"}, {\"unit\": \"hours\", \"account\": \"Professional Services\", \"details\": \"Back End Developer\", \"purpose\": \"Responsible for Development\", \"category\": \"capital_outlay\", \"quantity\": \"240\", \"unit_cost\": \"320\", \"particulars\": \"Back End Developer\", \"sub_account\": \"Other Professional Services\"}]}',NULL,NULL,NULL,NULL,NULL,'2026-07-20 23:33:28','2026-07-20 23:33:28','2026-07-20 23:33:28'),(4,14,NULL,3,1,'detailed_proposal',0,1,0,'saved',NULL,'Completed Detailed Research Proposal.','[]',NULL,'{\"sdgs\": [\"1\"], \"staff\": null, \"rationale\": null, \"objectives\": null, \"references\": null, \"methodology\": {\"data_analysis\": null, \"research_design\": null, \"specific_methods\": null}, \"leader_email\": \"23-78498@g.batstate-u.edu.ph\", \"leader_contact\": \"09631639454\", \"executive_brief\": null, \"research_agenda\": \"sample\", \"expected_outputs\": {\"patent\": null, \"policy\": null, \"product\": null, \"publication\": null, \"social_impact\": null, \"people_service\": null, \"economic_impact\": null, \"place_partnership\": null}, \"proponent_campus\": \"ARASOF-Nasugbu\", \"responsibilities\": [{\"name\": \"Neil Carlo Delmo\", \"duties\": null}], \"proponent_college\": \"\", \"cooperating_agency\": null, \"related_literature\": null, \"proponent_department\": \"\"}',NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-25 12:23:40','2026-07-26 11:56:43'),(5,14,NULL,4,4,'work_plan',0,1,1,'saved',NULL,'Completed Attachment A: Work Plan.','[]',NULL,'{\"entries\": [{\"months\": [\"1\"], \"activity\": null, \"objective\": null, \"expected_output\": null}]}',NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-25 12:26:27','2026-07-25 12:26:27'),(6,14,NULL,3,4,'detailed_proposal',0,2,1,'saved',NULL,'Saved Detailed Research Proposal as a draft.','[{\"after\": \"College of Informatics and Computing Sciences\", \"field\": \"source_data.proponent_college\", \"label\": \"Proponent College\", \"before\": \"Not provided\"}]',NULL,'{\"sdgs\": [\"1\"], \"staff\": null, \"rationale\": null, \"objectives\": null, \"references\": null, \"methodology\": {\"data_analysis\": null, \"research_design\": null, \"specific_methods\": null}, \"leader_email\": \"23-78498@g.batstate-u.edu.ph\", \"leader_contact\": \"09631639454\", \"executive_brief\": null, \"research_agenda\": \"sample\", \"expected_outputs\": {\"patent\": null, \"policy\": null, \"product\": null, \"publication\": null, \"social_impact\": null, \"people_service\": null, \"economic_impact\": null, \"place_partnership\": null}, \"proponent_campus\": \"ARASOF-Nasugbu\", \"responsibilities\": [{\"name\": \"Neil Carlo Delmo\", \"duties\": null}], \"proponent_college\": \"College of Informatics and Computing Sciences\", \"cooperating_agency\": null, \"related_literature\": null, \"proponent_department\": \"\"}',NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-26 11:56:43','2026-07-26 11:56:43'),(7,NULL,1,NULL,1,'detailed_proposal',0,1,0,'saved',NULL,'Saved Detailed Research Proposal as a draft.','[]',NULL,'{\"sdgs\": [\"1\", \"2\", \"3\"], \"staff\": [{\"name\": null, \"email\": null, \"contact\": null}], \"rationale\": null, \"objectives\": null, \"references\": null, \"methodology\": {\"data_analysis\": null, \"research_design\": null, \"specific_methods\": null}, \"leader_email\": \"23-78498@g.batstate-u.edu.ph\", \"leader_contact\": null, \"executive_brief\": null, \"research_agenda\": \"Sample\", \"expected_outputs\": {\"patent\": null, \"policy\": null, \"product\": null, \"publication\": null, \"social_impact\": null, \"people_service\": null, \"economic_impact\": null, \"place_partnership\": null}, \"proponent_campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"responsibilities\": [{\"name\": \"Neil Carlo Delmo\", \"duties\": null}], \"proponent_college\": \"\", \"cooperating_agency\": null, \"related_literature\": null, \"proponent_department\": \"\"}',NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-26 15:30:16','2026-07-26 19:33:24'),(8,NULL,1,NULL,1,'detailed_proposal',0,2,0,'saved',NULL,'Saved Detailed Research Proposal as a draft.','[{\"after\": \"Updated content (1 item)\", \"field\": \"source_data.staff\", \"label\": \"Project staff\", \"before\": \"Previous content (1 item)\"}, {\"after\": \"Sample\", \"field\": \"source_data.rationale\", \"label\": \"Rationale\", \"before\": \"Not provided\"}, {\"after\": \"Sample\", \"field\": \"source_data.objectives\", \"label\": \"Objectives\", \"before\": \"Not provided\"}, {\"after\": \"Sample\", \"field\": \"source_data.references\", \"label\": \"References\", \"before\": \"Not provided\"}, {\"after\": \"Data Analysis: Sample; Research Design: Sample; Specific Methods: Sample\", \"field\": \"source_data.methodology\", \"label\": \"Methodology\", \"before\": \"Data Analysis: None; Research Design: None; Specific Methods: None\"}, {\"after\": \"Sample\", \"field\": \"source_data.executive_brief\", \"label\": \"Executive brief\", \"before\": \"Not provided\"}, {\"after\": \"Patent: Sample; Policy: Sample; Product: Sample; Publication: Sample; Social Impact: Sample; People Service: Sample; Economic Impact: Sample; Place Partnership:...\", \"field\": \"source_data.expected_outputs\", \"label\": \"Expected outputs\", \"before\": \"Patent: None; Policy: None; Product: None; Publication: None; Social Impact: None; People Service: None; Economic Impact: None; Place Partnership: None\"}, {\"after\": \"Updated content (2 items)\", \"field\": \"source_data.responsibilities\", \"label\": \"Responsibilities\", \"before\": \"Previous content (1 item)\"}, {\"after\": \"Sample\", \"field\": \"source_data.related_literature\", \"label\": \"Related literature\", \"before\": \"Not provided\"}]',NULL,'{\"sdgs\": [\"1\", \"2\", \"3\"], \"staff\": [{\"name\": \"Djanisse Villaflor\", \"email\": \"23-73453@g.batstate-u.edu.ph\", \"contact\": null}], \"rationale\": \"Sample\", \"objectives\": \"Sample\", \"references\": \"Sample\", \"methodology\": {\"data_analysis\": \"Sample\", \"research_design\": \"Sample\", \"specific_methods\": \"Sample\"}, \"leader_email\": \"23-78498@g.batstate-u.edu.ph\", \"leader_contact\": null, \"executive_brief\": \"Sample\", \"research_agenda\": \"Sample\", \"expected_outputs\": {\"patent\": \"Sample\", \"policy\": \"Sample\", \"product\": \"Sample\", \"publication\": \"Sample\", \"social_impact\": \"Sample\", \"people_service\": \"Sample\", \"economic_impact\": \"Sample\", \"place_partnership\": \"Sample\"}, \"proponent_campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"responsibilities\": [{\"name\": \"Neil Carlo Delmo\", \"duties\": \"Sample\"}, {\"name\": \"Djanisse Villaflor\", \"duties\": \"Sample\"}], \"proponent_college\": \"\", \"cooperating_agency\": null, \"related_literature\": \"Sample\", \"proponent_department\": \"\"}',NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-26 15:36:05','2026-07-26 19:33:24'),(9,NULL,1,NULL,1,'detailed_proposal',0,3,0,'saved',NULL,'Saved Detailed Research Proposal as a draft.','[{\"after\": \"Updated content (1 item)\", \"field\": \"source_data.staff\", \"label\": \"Project staff\", \"before\": \"Previous content (1 item)\"}, {\"after\": \"09385327606\", \"field\": \"source_data.leader_contact\", \"label\": \"Project leader contact\", \"before\": \"Not provided\"}]',NULL,'{\"sdgs\": [\"1\", \"2\", \"3\"], \"staff\": [{\"name\": \"Djanisse Villaflor\", \"email\": \"23-73453@g.batstate-u.edu.ph\", \"contact\": \"09385327606\"}], \"rationale\": \"Sample\", \"objectives\": \"Sample\", \"references\": \"Sample\", \"methodology\": {\"data_analysis\": \"Sample\", \"research_design\": \"Sample\", \"specific_methods\": \"Sample\"}, \"leader_email\": \"23-78498@g.batstate-u.edu.ph\", \"leader_contact\": \"09385327606\", \"executive_brief\": \"Sample\", \"research_agenda\": \"Sample\", \"expected_outputs\": {\"patent\": \"Sample\", \"policy\": \"Sample\", \"product\": \"Sample\", \"publication\": \"Sample\", \"social_impact\": \"Sample\", \"people_service\": \"Sample\", \"economic_impact\": \"Sample\", \"place_partnership\": \"Sample\"}, \"proponent_campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"responsibilities\": [{\"name\": \"Neil Carlo Delmo\", \"duties\": \"Sample\"}, {\"name\": \"Djanisse Villaflor\", \"duties\": \"Sample\"}], \"proponent_college\": \"\", \"cooperating_agency\": null, \"related_literature\": \"Sample\", \"proponent_department\": \"\"}',NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-26 15:36:51','2026-07-26 19:33:24'),(10,NULL,1,NULL,1,'detailed_proposal',0,4,0,'saved',NULL,'Updated Detailed Research Proposal (2 fields changed).','[{\"after\": \"CICS\", \"field\": \"source_data.proponent_college\", \"label\": \"Proponent College\", \"before\": \"Not provided\"}, {\"after\": \"Sample\", \"field\": \"source_data.cooperating_agency\", \"label\": \"Cooperating Agency\", \"before\": \"Not provided\"}]',NULL,'{\"sdgs\": [\"1\", \"2\", \"3\"], \"staff\": [{\"name\": \"Djanisse Villaflor\", \"email\": \"23-73453@g.batstate-u.edu.ph\", \"contact\": \"09385327606\"}], \"rationale\": \"Sample\", \"objectives\": \"Sample\", \"references\": \"Sample\", \"methodology\": {\"data_analysis\": \"Sample\", \"research_design\": \"Sample\", \"specific_methods\": \"Sample\"}, \"leader_email\": \"23-78498@g.batstate-u.edu.ph\", \"leader_contact\": \"09385327606\", \"executive_brief\": \"Sample\", \"research_agenda\": \"Sample\", \"expected_outputs\": {\"patent\": \"Sample\", \"policy\": \"Sample\", \"product\": \"Sample\", \"publication\": \"Sample\", \"social_impact\": \"Sample\", \"people_service\": \"Sample\", \"economic_impact\": \"Sample\", \"place_partnership\": \"Sample\"}, \"proponent_campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"responsibilities\": [{\"name\": \"Neil Carlo Delmo\", \"duties\": \"Sample\"}, {\"name\": \"Djanisse Villaflor\", \"duties\": \"Sample\"}], \"proponent_college\": \"CICS\", \"cooperating_agency\": \"Sample\", \"related_literature\": \"Sample\", \"proponent_department\": \"\"}',NULL,NULL,NULL,NULL,NULL,'2026-07-26 15:37:58','2026-07-26 15:37:58','2026-07-26 19:33:24'),(11,NULL,1,NULL,1,'work_plan',0,1,0,'saved',NULL,'Completed Attachment A: Work Plan.','[]',NULL,'{\"entries\": [{\"months\": [\"1\", \"2\", \"3\"], \"activity\": \"Sample\", \"objective\": \"Sample\", \"expected_output\": \"Sample\"}]}',NULL,NULL,NULL,NULL,NULL,'2026-07-26 15:39:20','2026-07-26 15:39:20','2026-07-26 19:33:24'),(12,NULL,1,NULL,1,'line_item_budget',0,1,0,'saved',NULL,'Completed Attachment B: Line-Item Budget.','[]',NULL,'{\"staff\": [{\"name\": null, \"campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"college\": null}], \"amounts\": {\"other_mooe\": null, \"rent_lease\": null, \"contingency\": null, \"ict_equipment\": null, \"office_supplies\": null, \"postage_courier\": null, \"general_services\": null, \"office_equipment\": null, \"travelling_local\": null, \"supplies_materials\": null, \"telephone_expenses\": null, \"other_mooe_expenses\": null, \"repairs_maintenance\": null, \"travelling_expenses\": \"0.04\", \"printing_publication\": null, \"training_scholarship\": null, \"professional_services\": null, \"subscription_expenses\": null, \"communication_expenses\": null, \"other_general_services\": null, \"representation_expenses\": null, \"other_supplies_materials\": null, \"other_machinery_equipment\": null, \"semi_expendable_equipment\": null, \"machinery_equipment_outlay\": null, \"other_professional_services\": null, \"technical_scientific_equipment\": null}, \"approval_body\": null, \"leader_campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"level_of_call\": null, \"leader_college\": \"\", \"resolution_year\": null, \"resolution_number\": null}',NULL,NULL,NULL,NULL,NULL,'2026-07-26 15:43:37','2026-07-26 15:43:37','2026-07-26 19:33:24'),(13,NULL,1,NULL,1,'expense_breakdown',0,1,0,'saved',NULL,'Completed Estimated Expense Breakdown.','[]',NULL,'{\"items\": [{\"unit\": \"2\", \"account\": \"General Services\", \"details\": \"Sample\", \"purpose\": \"Sample\", \"category\": \"mooe\", \"quantity\": \"10\", \"unit_cost\": \"50\", \"particulars\": \"Sample\", \"sub_account\": \"Other General Services\"}]}',NULL,NULL,NULL,NULL,NULL,'2026-07-26 15:52:51','2026-07-26 15:52:51','2026-07-26 19:33:24'),(14,NULL,1,NULL,1,'curriculum_vitae',0,1,0,'saved',NULL,'Completed Attachment C: Curriculum Vitae.','[]',NULL,'{\"people\": [{\"email\": \"23-78498@g.batstate-u.edu.ph\", \"agency\": \"Sample\", \"awards\": [{\"rank\": \"Sample\", \"title\": \"Sample\", \"category\": null, \"year_granted\": \"2010\", \"granting_institution\": \"Sample\"}, {\"rank\": null, \"title\": null, \"category\": null, \"year_granted\": null, \"granting_institution\": null}, {\"rank\": null, \"title\": null, \"category\": null, \"year_granted\": null, \"granting_institution\": null}], \"gender\": \"female\", \"street\": \"Sample\", \"barangay\": \"Sample\", \"birthday\": \"2026-03-05\", \"landline\": \"Sample\", \"projects\": [{\"title\": \"Sample\", \"sector\": \"Sample\", \"year_to\": \"2026\", \"year_from\": \"2030\", \"designation\": null, \"current_status\": \"Sample\"}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}], \"province\": \"Sample\", \"cellphone\": \"Sample\", \"last_name\": \"Delmo\", \"employment\": [{\"agency\": \"Sample\", \"end_date\": \"2026-07-04\", \"start_date\": \"2026-07-01\", \"monthly_salary\": \"300\", \"appointment_status\": null, \"plantilla_position\": \"Sample\"}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}], \"first_name\": \"Neil\", \"middle_name\": \"Carlo\", \"municipality\": \"Sample\", \"publications\": [{\"place\": \"Sample\", \"title\": \"Sample\", \"authoring_type\": \"Sample\", \"year_published\": \"2026\", \"publication_group\": \"Sample\"}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}], \"scholarships\": [{\"sponsor\": \"Sample\", \"period_end\": \"2026-07-04\", \"period_start\": \"2020-07-01\", \"date_released\": \"2026-07-05\", \"extension_end\": \"2026-07-04\", \"item_expenses\": \"Sample\", \"amount_approved\": \"10000\", \"amount_released\": \"10000\", \"extension_start\": \"2026-07-01\", \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}], \"presentations\": [{\"date\": \"2026-07-01\", \"title\": \"Sample\", \"venue\": \"Sample\", \"sponsor\": \"Sample\", \"category\": null, \"conference_title\": \"Sample\"}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}], \"specializations\": [{\"field\": \"Sample\", \"primary_field\": null}, {\"field\": null, \"primary_field\": null}, {\"field\": null, \"primary_field\": null}, {\"field\": null, \"primary_field\": null}], \"academic_background\": [{\"degree\": \"Sample\", \"sector\": \"Sample\", \"status\": \"Ongoing\", \"thesis\": \"Sample\", \"year_end\": null, \"year_start\": \"2020\", \"major_field\": \"Sample\", \"learning_institution\": \"Sample\"}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}]}, {\"email\": \"23-73453@g.batstate-u.edu.ph\", \"agency\": \"Sample\", \"awards\": [{\"rank\": null, \"title\": null, \"category\": null, \"year_granted\": null, \"granting_institution\": null}, {\"rank\": null, \"title\": null, \"category\": null, \"year_granted\": null, \"granting_institution\": null}, {\"rank\": null, \"title\": null, \"category\": null, \"year_granted\": null, \"granting_institution\": null}], \"gender\": \"female\", \"street\": \"Sample\", \"barangay\": \"Sample\", \"birthday\": \"2026-07-26\", \"landline\": \"Sample\", \"projects\": [{\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}], \"province\": \"Sample\", \"cellphone\": \"Sample\", \"last_name\": \"Villaflor\", \"employment\": [{\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}], \"first_name\": \"Djanisse\", \"middle_name\": \"Sample\", \"municipality\": \"Sample\", \"publications\": [{\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}], \"scholarships\": [{\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}], \"presentations\": [{\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}], \"specializations\": [{\"field\": null, \"primary_field\": null}, {\"field\": null, \"primary_field\": null}, {\"field\": null, \"primary_field\": null}, {\"field\": null, \"primary_field\": null}], \"academic_background\": [{\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}]}]}',NULL,NULL,NULL,NULL,NULL,'2026-07-26 16:01:47','2026-07-26 16:01:47','2026-07-26 19:33:24'),(15,14,NULL,10,1,'expense_breakdown',0,1,1,'saved',NULL,'Saved Estimated Expense Breakdown as a draft.','[]',NULL,'{\"items\": [{\"unit\": \"pc\", \"account\": null, \"details\": null, \"purpose\": null, \"category\": \"mooe\", \"quantity\": \"1\", \"unit_cost\": \"11\", \"particulars\": \"Prepaid Call\", \"sub_account\": null}]}',NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-26 20:25:52','2026-07-26 20:25:52'),(16,16,NULL,11,NULL,'detailed_proposal',0,4,0,'captured',NULL,'Captured the existing Detailed Research Proposal as version history.','[]',NULL,'{\"sdgs\": [\"1\", \"2\", \"3\"], \"staff\": [{\"name\": \"Djanisse Villaflor\", \"email\": \"23-73453@g.batstate-u.edu.ph\", \"contact\": \"09385327606\"}], \"rationale\": \"Sample\", \"objectives\": \"Sample\", \"references\": \"Sample\", \"methodology\": {\"data_analysis\": \"Sample\", \"research_design\": \"Sample\", \"specific_methods\": \"Sample\"}, \"leader_email\": \"23-78498@g.batstate-u.edu.ph\", \"leader_contact\": \"09385327606\", \"executive_brief\": \"Sample\", \"research_agenda\": \"Sample\", \"expected_outputs\": {\"patent\": \"Sample\", \"policy\": \"Sample\", \"product\": \"Sample\", \"publication\": \"Sample\", \"social_impact\": \"Sample\", \"people_service\": \"Sample\", \"economic_impact\": \"Sample\", \"place_partnership\": \"Sample\"}, \"proponent_campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"responsibilities\": [{\"name\": \"Neil Carlo Delmo\", \"duties\": \"Sample\"}, {\"name\": \"Djanisse Villaflor\", \"duties\": \"Sample\"}], \"proponent_college\": \"CICS\", \"cooperating_agency\": \"Sample\", \"related_literature\": \"Sample\", \"proponent_department\": \"\"}',NULL,NULL,NULL,NULL,NULL,'2026-07-26 23:30:09','2026-07-26 23:30:55','2026-07-26 23:30:55'),(17,16,NULL,11,1,'detailed_proposal',0,5,0,'saved',NULL,'Updated Detailed Research Proposal (1 field changed).','[{\"after\": \"Patent: Sample; Policy: Sample; Product: Sample; Publication: Sample; Social Impact: Sample; People Service: Sample; Economic Impact: Sample Samplew; Place Part...\", \"field\": \"source_data.expected_outputs\", \"label\": \"Expected outputs\", \"before\": \"Patent: Sample; Policy: Sample; Product: Sample; Publication: Sample; Social Impact: Sample; People Service: Sample; Economic Impact: Sample; Place Partnership:...\"}]',NULL,'{\"sdgs\": [\"1\", \"2\", \"3\"], \"staff\": [{\"name\": \"Djanisse Villaflor\", \"email\": \"23-73453@g.batstate-u.edu.ph\", \"contact\": \"09385327606\"}], \"rationale\": \"Sample\", \"objectives\": \"Sample\", \"references\": \"Sample\", \"methodology\": {\"data_analysis\": \"Sample\", \"research_design\": \"Sample\", \"specific_methods\": \"Sample\"}, \"leader_email\": \"23-78498@g.batstate-u.edu.ph\", \"leader_contact\": \"09385327606\", \"executive_brief\": \"Sample\", \"research_agenda\": \"Sample\", \"expected_outputs\": {\"patent\": \"Sample\", \"policy\": \"Sample\", \"product\": \"Sample\", \"publication\": \"Sample\", \"social_impact\": \"Sample\", \"people_service\": \"Sample\", \"economic_impact\": \"Sample Samplew\", \"place_partnership\": \"Sample\"}, \"proponent_campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"responsibilities\": [{\"name\": \"Neil Carlo Delmo\", \"duties\": \"Sample\"}, {\"name\": \"Djanisse Villaflor\", \"duties\": \"Sample\"}], \"proponent_college\": \"CICS\", \"cooperating_agency\": \"Sample\", \"related_literature\": \"Sample\", \"proponent_department\": \"\"}',NULL,NULL,NULL,NULL,NULL,'2026-07-26 23:30:55','2026-07-26 23:30:55','2026-07-27 01:20:48'),(18,16,NULL,11,1,'detailed_proposal',0,6,0,'saved','Exact downloaded file staged for revision submission.','Replaced Detailed Research Proposal with sample-detailed-research-proposal.docx.','[{\"after\": \"sample-detailed-research-proposal.docx\", \"field\": \"original_filename\", \"label\": \"File name\", \"before\": \"Not provided\"}, {\"after\": \"Updated PDF contents\", \"field\": \"checksum\", \"label\": \"File contents\", \"before\": \"No uploaded PDF\"}, {\"after\": \"221 KB\", \"field\": \"file_size\", \"label\": \"File size\", \"before\": \"Not available\"}]',NULL,'{\"sdgs\": [\"1\", \"2\", \"3\"], \"staff\": [{\"name\": \"Djanisse Villaflor\", \"email\": \"23-73453@g.batstate-u.edu.ph\", \"contact\": \"09385327606\"}], \"rationale\": \"Sample\", \"objectives\": \"Sample\", \"references\": \"Sample\", \"methodology\": {\"data_analysis\": \"Sample\", \"research_design\": \"Sample\", \"specific_methods\": \"Sample\"}, \"leader_email\": \"23-78498@g.batstate-u.edu.ph\", \"leader_contact\": \"09385327606\", \"executive_brief\": \"Sample\", \"research_agenda\": \"Sample\", \"expected_outputs\": {\"patent\": \"Sample\", \"policy\": \"Sample\", \"product\": \"Sample\", \"publication\": \"Sample\", \"social_impact\": \"Sample\", \"people_service\": \"Sample\", \"economic_impact\": \"Sample Samplew\", \"place_partnership\": \"Sample\"}, \"proponent_campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"responsibilities\": [{\"name\": \"Neil Carlo Delmo\", \"duties\": \"Sample\"}, {\"name\": \"Djanisse Villaflor\", \"duties\": \"Sample\"}], \"proponent_college\": \"CICS\", \"cooperating_agency\": \"Sample\", \"related_literature\": \"Sample\", \"proponent_department\": \"\"}','proposal-drafts/1/16/revision/detailed_proposal/etZi2eJytDc59FAJsdGEIKr1qqZdi6KdNzhOTEKV.docx','sample-detailed-research-proposal.docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',226811,'e653e7f6fc7e2eb084633326384705839e02502b1bc217f402f67607038f2cb3','2026-07-27 01:20:48','2026-07-27 01:20:48','2026-07-30 00:56:57'),(19,16,NULL,11,1,'detailed_proposal',0,7,1,'saved','Exact downloaded file staged for revision submission.','Replaced Detailed Research Proposal with sample-detailed-research-proposal.docx.','[{\"after\": \"Updated PDF contents\", \"field\": \"checksum\", \"label\": \"File contents\", \"before\": \"Previous PDF contents\"}, {\"after\": \"221 KB\", \"field\": \"file_size\", \"label\": \"File size\", \"before\": \"221 KB\"}]',NULL,'{\"sdgs\": [\"1\", \"2\", \"3\"], \"staff\": [{\"name\": \"Djanisse Villaflor\", \"email\": \"23-73453@g.batstate-u.edu.ph\", \"contact\": \"09385327606\"}], \"rationale\": \"Sample\", \"objectives\": \"Sample\", \"references\": \"Sample\", \"methodology\": {\"data_analysis\": \"Sample\", \"research_design\": \"Sample\", \"specific_methods\": \"Sample\"}, \"leader_email\": \"23-78498@g.batstate-u.edu.ph\", \"leader_contact\": \"09385327606\", \"executive_brief\": \"Sample\", \"research_agenda\": \"Sample\", \"expected_outputs\": {\"patent\": \"Sample\", \"policy\": \"Sample\", \"product\": \"Sample\", \"publication\": \"Sample\", \"social_impact\": \"Sample\", \"people_service\": \"Sample\", \"economic_impact\": \"Sample Samplew\", \"place_partnership\": \"Sample\"}, \"proponent_campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"responsibilities\": [{\"name\": \"Neil Carlo Delmo\", \"duties\": \"Sample\"}, {\"name\": \"Djanisse Villaflor\", \"duties\": \"Sample\"}], \"proponent_college\": \"CICS\", \"cooperating_agency\": \"Sample\", \"related_literature\": \"Sample\", \"proponent_department\": \"\"}','proposal-drafts/1/16/revision/detailed_proposal/wmx6MVl1MP0OBb0x5zyrMDXjpr9WJrXDpnJKg4Qd.docx','sample-detailed-research-proposal.docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',226809,'4306992addfa0c80a6eb8ab478dd9c81ca5bc36715f8b9bf0293f3c7b502b420','2026-07-30 00:56:57','2026-07-30 00:56:57','2026-07-30 00:56:57'),(20,17,NULL,16,1,'detailed_proposal',0,1,1,'checkpoint',NULL,'Saved Detailed Research Proposal as a draft.','[]',NULL,'{\"staff\": null, \"rationale\": \"\", \"references\": \"<p></p>\", \"methodology\": {\"data_analysis\": \"\", \"research_design\": \"\", \"specific_methods\": \"\"}, \"introduction\": \"\", \"leader_email\": \"23-78498@g.batstate-u.edu.ph\", \"leader_title\": null, \"leader_contact\": \"\", \"executive_brief\": \"\", \"research_agenda\": null, \"expected_outputs\": {\"patent\": [], \"policy\": [], \"product\": [], \"publication\": [], \"social_impact\": [], \"people_service\": [], \"economic_impact\": [], \"place_partnership\": []}, \"proponent_campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"responsibilities\": [{\"name\": \"\", \"duties\": \"\", \"percentage\": \"100\"}], \"general_objective\": \"\", \"proponent_college\": \"\", \"cooperating_agency\": null, \"methodology_images\": [], \"related_literature\": \"\", \"specific_objectives\": [], \"literature_citations\": \"[]\", \"proponent_department\": \"\", \"specific_method_objectives\": [{\"heading\": \"\", \"methods\": []}], \"literature_research_history\": \"[]\"}',NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-27 18:48:04','2026-09-27 18:48:04');
/*!40000 ALTER TABLE `proposal_draft_document_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_draft_documents`
--

DROP TABLE IF EXISTS `proposal_draft_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_draft_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proposal_draft_id` bigint unsigned NOT NULL,
  `document_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` smallint unsigned NOT NULL DEFAULT '0',
  `source_data` json DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `checksum` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `lock_version` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_draft_document_slot_unique` (`proposal_draft_id`,`document_type`,`position`),
  CONSTRAINT `proposal_draft_documents_proposal_draft_id_foreign` FOREIGN KEY (`proposal_draft_id`) REFERENCES `proposal_drafts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_draft_documents`
--

LOCK TABLES `proposal_draft_documents` WRITE;
/*!40000 ALTER TABLE `proposal_draft_documents` DISABLE KEYS */;
INSERT INTO `proposal_draft_documents` VALUES (1,5,'detailed_proposal',0,'{\"sdgs\": [\"8\"], \"staff\": null, \"rationale\": \"SAMPLE\", \"objectives\": \"SAMPLE\", \"references\": \"SAMPLE\", \"methodology\": {\"data_analysis\": \"SAMPLE\", \"research_design\": \"SAMPLE\", \"specific_methods\": \"SAMPLE\"}, \"leader_email\": \"23-73453@g.batstate-u.edu.ph\", \"leader_contact\": \"09778666545\", \"executive_brief\": \"SAMPLE\", \"research_agenda\": \"SAMPLE\", \"expected_outputs\": {\"patent\": \"SAMPLE\", \"policy\": \"SAMPLE\", \"product\": \"SAMPLE\", \"publication\": \"SAMPLE\", \"social_impact\": \"SAMPLE\", \"people_service\": \"SAMPLE\", \"economic_impact\": \"SAMPLE\", \"place_partnership\": \"SAMPLE\"}, \"proponent_campus\": \"ARASOF-Nasugbu\", \"responsibilities\": [{\"name\": \"Djanisse Villaflor\", \"duties\": \"SAMPLE\"}], \"proponent_college\": \"COLLEGE OF INFORMATICS AND COMPUTING SCIENCES\", \"cooperating_agency\": null, \"related_literature\": \"SAMPLE\", \"proponent_department\": \"CICS\"}',NULL,NULL,NULL,NULL,NULL,'2026-07-20 01:48:05',1,'2026-07-20 01:48:05','2026-07-20 01:48:05'),(2,10,'expense_breakdown',0,'{\"items\": [{\"unit\": \"pc\", \"account\": \"Communication Expenses\", \"details\": \"Sample\", \"purpose\": \"Sample\", \"category\": \"mooe\", \"quantity\": \"10\", \"unit_cost\": \"200.00\", \"particulars\": \"Prepaid Call\", \"sub_account\": \"Telephone Expences\"}, {\"unit\": \"hours\", \"account\": \"Professional Services\", \"details\": \"Back End Developer\", \"purpose\": \"Responsible for Development\", \"category\": \"capital_outlay\", \"quantity\": \"240\", \"unit_cost\": \"320\", \"particulars\": \"Back End Developer\", \"sub_account\": \"Other Professional Services\"}]}',NULL,NULL,NULL,NULL,NULL,'2026-07-20 23:33:28',2,'2026-07-20 23:30:27','2026-07-20 23:33:28'),(3,14,'detailed_proposal',0,'{\"sdgs\": [\"1\"], \"staff\": null, \"rationale\": null, \"objectives\": null, \"references\": null, \"methodology\": {\"data_analysis\": null, \"research_design\": null, \"specific_methods\": null}, \"leader_email\": \"23-78498@g.batstate-u.edu.ph\", \"leader_contact\": \"09631639454\", \"executive_brief\": null, \"research_agenda\": \"sample\", \"expected_outputs\": {\"patent\": null, \"policy\": null, \"product\": null, \"publication\": null, \"social_impact\": null, \"people_service\": null, \"economic_impact\": null, \"place_partnership\": null}, \"proponent_campus\": \"ARASOF-Nasugbu\", \"responsibilities\": [{\"name\": \"Neil Carlo Delmo\", \"duties\": null}], \"proponent_college\": \"College of Informatics and Computing Sciences\", \"cooperating_agency\": null, \"related_literature\": null, \"proponent_department\": \"\"}',NULL,NULL,NULL,NULL,NULL,NULL,2,'2026-07-25 12:23:40','2026-07-26 11:56:43'),(4,14,'work_plan',0,'{\"entries\": [{\"months\": [\"1\"], \"activity\": null, \"objective\": null, \"expected_output\": null}]}',NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-07-25 12:26:27','2026-07-25 12:26:27'),(10,14,'expense_breakdown',0,'{\"items\": [{\"unit\": \"pc\", \"account\": null, \"details\": null, \"purpose\": null, \"category\": \"mooe\", \"quantity\": \"1\", \"unit_cost\": \"11\", \"particulars\": \"Prepaid Call\", \"sub_account\": null}]}',NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-07-26 20:25:52','2026-07-26 20:25:52'),(11,16,'detailed_proposal',0,'{\"sdgs\": [\"1\", \"2\", \"3\"], \"staff\": [{\"name\": \"Djanisse Villaflor\", \"email\": \"23-73453@g.batstate-u.edu.ph\", \"contact\": \"09385327606\"}], \"rationale\": \"Sample\", \"objectives\": \"Sample\", \"references\": \"Sample\", \"methodology\": {\"data_analysis\": \"Sample\", \"research_design\": \"Sample\", \"specific_methods\": \"Sample\"}, \"leader_email\": \"23-78498@g.batstate-u.edu.ph\", \"leader_contact\": \"09385327606\", \"executive_brief\": \"Sample\", \"research_agenda\": \"Sample\", \"expected_outputs\": {\"patent\": \"Sample\", \"policy\": \"Sample\", \"product\": \"Sample\", \"publication\": \"Sample\", \"social_impact\": \"Sample\", \"people_service\": \"Sample\", \"economic_impact\": \"Sample Samplew\", \"place_partnership\": \"Sample\"}, \"proponent_campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"responsibilities\": [{\"name\": \"Neil Carlo Delmo\", \"duties\": \"Sample\"}, {\"name\": \"Djanisse Villaflor\", \"duties\": \"Sample\"}], \"proponent_college\": \"CICS\", \"cooperating_agency\": \"Sample\", \"related_literature\": \"Sample\", \"proponent_department\": \"\"}','proposal-drafts/1/16/revision/detailed_proposal/wmx6MVl1MP0OBb0x5zyrMDXjpr9WJrXDpnJKg4Qd.docx','sample-detailed-research-proposal.docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',226809,'4306992addfa0c80a6eb8ab478dd9c81ca5bc36715f8b9bf0293f3c7b502b420','2026-07-30 00:56:57',7,'2026-07-26 23:30:09','2026-07-30 00:56:57'),(12,16,'work_plan',0,'{\"entries\": [{\"months\": [\"1\", \"2\", \"3\"], \"activity\": \"Sample\", \"objective\": \"Sample\", \"expected_output\": \"Sample\"}]}',NULL,NULL,NULL,NULL,NULL,'2026-07-26 23:30:09',1,'2026-07-26 23:30:09','2026-07-26 23:30:09'),(13,16,'expense_breakdown',0,'{\"items\": [{\"unit\": \"2\", \"account\": \"General Services\", \"details\": \"Sample\", \"purpose\": \"Sample\", \"category\": \"mooe\", \"quantity\": \"10\", \"unit_cost\": \"50\", \"particulars\": \"Sample\", \"sub_account\": \"Other General Services\"}]}',NULL,NULL,NULL,NULL,NULL,'2026-07-26 23:30:09',1,'2026-07-26 23:30:09','2026-07-26 23:30:09'),(14,16,'line_item_budget',0,'{\"staff\": [{\"name\": null, \"campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"college\": null}], \"amounts\": {\"other_mooe\": null, \"rent_lease\": null, \"contingency\": null, \"ict_equipment\": null, \"office_supplies\": null, \"postage_courier\": null, \"general_services\": null, \"office_equipment\": null, \"travelling_local\": null, \"supplies_materials\": null, \"telephone_expenses\": null, \"other_mooe_expenses\": null, \"repairs_maintenance\": null, \"travelling_expenses\": \"0.04\", \"printing_publication\": null, \"training_scholarship\": null, \"professional_services\": null, \"subscription_expenses\": null, \"communication_expenses\": null, \"other_general_services\": null, \"representation_expenses\": null, \"other_supplies_materials\": null, \"other_machinery_equipment\": null, \"semi_expendable_equipment\": null, \"machinery_equipment_outlay\": null, \"other_professional_services\": null, \"technical_scientific_equipment\": null}, \"approval_body\": null, \"leader_campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"level_of_call\": null, \"leader_college\": \"\", \"resolution_year\": null, \"resolution_number\": null}',NULL,NULL,NULL,NULL,NULL,'2026-07-26 23:30:09',1,'2026-07-26 23:30:09','2026-07-26 23:30:09'),(15,16,'curriculum_vitae',0,'{\"people\": [{\"email\": \"23-78498@g.batstate-u.edu.ph\", \"agency\": \"Sample\", \"awards\": [{\"rank\": \"Sample\", \"title\": \"Sample\", \"category\": null, \"year_granted\": \"2010\", \"granting_institution\": \"Sample\"}, {\"rank\": null, \"title\": null, \"category\": null, \"year_granted\": null, \"granting_institution\": null}, {\"rank\": null, \"title\": null, \"category\": null, \"year_granted\": null, \"granting_institution\": null}], \"gender\": \"female\", \"street\": \"Sample\", \"barangay\": \"Sample\", \"birthday\": \"2026-03-05\", \"landline\": \"Sample\", \"projects\": [{\"title\": \"Sample\", \"sector\": \"Sample\", \"year_to\": \"2026\", \"year_from\": \"2030\", \"designation\": null, \"current_status\": \"Sample\"}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}], \"province\": \"Sample\", \"cellphone\": \"Sample\", \"last_name\": \"Delmo\", \"employment\": [{\"agency\": \"Sample\", \"end_date\": \"2026-07-04\", \"start_date\": \"2026-07-01\", \"monthly_salary\": \"300\", \"appointment_status\": null, \"plantilla_position\": \"Sample\"}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}], \"first_name\": \"Neil\", \"middle_name\": \"Carlo\", \"municipality\": \"Sample\", \"publications\": [{\"place\": \"Sample\", \"title\": \"Sample\", \"authoring_type\": \"Sample\", \"year_published\": \"2026\", \"publication_group\": \"Sample\"}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}], \"scholarships\": [{\"sponsor\": \"Sample\", \"period_end\": \"2026-07-04\", \"period_start\": \"2020-07-01\", \"date_released\": \"2026-07-05\", \"extension_end\": \"2026-07-04\", \"item_expenses\": \"Sample\", \"amount_approved\": \"10000\", \"amount_released\": \"10000\", \"extension_start\": \"2026-07-01\", \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}], \"presentations\": [{\"date\": \"2026-07-01\", \"title\": \"Sample\", \"venue\": \"Sample\", \"sponsor\": \"Sample\", \"category\": null, \"conference_title\": \"Sample\"}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}], \"specializations\": [{\"field\": \"Sample\", \"primary_field\": null}, {\"field\": null, \"primary_field\": null}, {\"field\": null, \"primary_field\": null}, {\"field\": null, \"primary_field\": null}], \"academic_background\": [{\"degree\": \"Sample\", \"sector\": \"Sample\", \"status\": \"Ongoing\", \"thesis\": \"Sample\", \"year_end\": null, \"year_start\": \"2020\", \"major_field\": \"Sample\", \"learning_institution\": \"Sample\"}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}]}, {\"email\": \"23-73453@g.batstate-u.edu.ph\", \"agency\": \"Sample\", \"awards\": [{\"rank\": null, \"title\": null, \"category\": null, \"year_granted\": null, \"granting_institution\": null}, {\"rank\": null, \"title\": null, \"category\": null, \"year_granted\": null, \"granting_institution\": null}, {\"rank\": null, \"title\": null, \"category\": null, \"year_granted\": null, \"granting_institution\": null}], \"gender\": \"female\", \"street\": \"Sample\", \"barangay\": \"Sample\", \"birthday\": \"2026-07-26\", \"landline\": \"Sample\", \"projects\": [{\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}], \"province\": \"Sample\", \"cellphone\": \"Sample\", \"last_name\": \"Villaflor\", \"employment\": [{\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}], \"first_name\": \"Djanisse\", \"middle_name\": \"Sample\", \"municipality\": \"Sample\", \"publications\": [{\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}], \"scholarships\": [{\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}], \"presentations\": [{\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}], \"specializations\": [{\"field\": null, \"primary_field\": null}, {\"field\": null, \"primary_field\": null}, {\"field\": null, \"primary_field\": null}, {\"field\": null, \"primary_field\": null}], \"academic_background\": [{\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}]}]}',NULL,NULL,NULL,NULL,NULL,'2026-07-26 23:30:10',1,'2026-07-26 23:30:10','2026-07-26 23:30:10'),(16,17,'detailed_proposal',0,'{\"staff\": null, \"rationale\": \"\", \"references\": \"<p></p>\", \"methodology\": {\"data_analysis\": \"\", \"research_design\": \"\", \"specific_methods\": \"\"}, \"introduction\": \"\", \"leader_email\": \"23-78498@g.batstate-u.edu.ph\", \"leader_title\": null, \"leader_contact\": \"\", \"executive_brief\": \"\", \"research_agenda\": null, \"expected_outputs\": {\"patent\": [], \"policy\": [], \"product\": [], \"publication\": [], \"social_impact\": [], \"people_service\": [], \"economic_impact\": [], \"place_partnership\": []}, \"proponent_campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"responsibilities\": [{\"name\": \"\", \"duties\": \"\", \"percentage\": \"100\"}], \"general_objective\": \"\", \"proponent_college\": \"\", \"cooperating_agency\": null, \"methodology_images\": [], \"related_literature\": \"\", \"specific_objectives\": [], \"literature_citations\": \"[]\", \"proponent_department\": \"\", \"specific_method_objectives\": [{\"heading\": \"\", \"methods\": []}], \"literature_research_history\": \"[]\"}',NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-09-27 18:48:04','2026-09-27 18:48:04');
/*!40000 ALTER TABLE `proposal_draft_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_draft_literature_sources`
--

DROP TABLE IF EXISTS `proposal_draft_literature_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_draft_literature_sources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proposal_draft_id` bigint unsigned NOT NULL,
  `literature_source_id` bigint unsigned DEFAULT NULL,
  `saved_by` bigint unsigned DEFAULT NULL,
  `fingerprint` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `authors` text COLLATE utf8mb4_unicode_ci,
  `abstract` text COLLATE utf8mb4_unicode_ci,
  `publication_year` smallint unsigned DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `venue` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `volume` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pages` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publisher` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` text COLLATE utf8mb4_unicode_ci,
  `full_text_url` text COLLATE utf8mb4_unicode_ci,
  `provider` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_identifier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `citation_count` int unsigned DEFAULT NULL,
  `is_open_access` tinyint(1) NOT NULL DEFAULT '0',
  `access_status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `publication_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rrl_note` longtext COLLATE utf8mb4_unicode_ci,
  `rrl_draft_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `rrl_evidence_basis` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rrl_word_count` smallint unsigned DEFAULT NULL,
  `rrl_generated_at` timestamp NULL DEFAULT NULL,
  `reference_text` text COLLATE utf8mb4_unicode_ci,
  `research_context` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_literature_source_unique` (`proposal_draft_id`,`fingerprint`),
  UNIQUE KEY `proposal_shared_literature_source_unique` (`proposal_draft_id`,`literature_source_id`),
  KEY `proposal_draft_literature_sources_saved_by_foreign` (`saved_by`),
  KEY `proposal_literature_draft_created_index` (`proposal_draft_id`,`created_at`),
  KEY `proposal_draft_literature_sources_literature_source_id_foreign` (`literature_source_id`),
  CONSTRAINT `proposal_draft_literature_sources_literature_source_id_foreign` FOREIGN KEY (`literature_source_id`) REFERENCES `literature_sources` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proposal_draft_literature_sources_proposal_draft_id_foreign` FOREIGN KEY (`proposal_draft_id`) REFERENCES `proposal_drafts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proposal_draft_literature_sources_saved_by_foreign` FOREIGN KEY (`saved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_draft_literature_sources`
--

LOCK TABLES `proposal_draft_literature_sources` WRITE;
/*!40000 ALTER TABLE `proposal_draft_literature_sources` DISABLE KEYS */;
/*!40000 ALTER TABLE `proposal_draft_literature_sources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_draft_members`
--

DROP TABLE IF EXISTS `proposal_draft_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_draft_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proposal_draft_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accepted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `project_role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_draft_member_email_unique` (`proposal_draft_id`,`email`),
  UNIQUE KEY `proposal_draft_member_user_unique` (`proposal_draft_id`,`user_id`),
  KEY `proposal_draft_members_user_id_foreign` (`user_id`),
  CONSTRAINT `proposal_draft_members_proposal_draft_id_foreign` FOREIGN KEY (`proposal_draft_id`) REFERENCES `proposal_drafts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proposal_draft_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_draft_members`
--

LOCK TABLES `proposal_draft_members` WRITE;
/*!40000 ALTER TABLE `proposal_draft_members` DISABLE KEYS */;
INSERT INTO `proposal_draft_members` VALUES (1,14,4,'Djanisse Villaflor','23-73453@g.batstate-u.edu.ph','2026-07-25 06:29:27',NULL,'2026-07-25 06:28:22','2026-07-25 06:29:27');
/*!40000 ALTER TABLE `proposal_draft_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_drafts`
--

DROP TABLE IF EXISTS `proposal_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_drafts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `research_call_id` bigint unsigned DEFAULT NULL,
  `topic_id` bigint unsigned DEFAULT NULL,
  `project_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_months` smallint unsigned DEFAULT NULL,
  `planned_start` date DEFAULT NULL,
  `planned_end` date DEFAULT NULL,
  `project_leader` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `lock_version` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `signatory_selections` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_drafts_topic_id_unique` (`topic_id`),
  KEY `proposal_drafts_research_call_id_foreign` (`research_call_id`),
  KEY `proposal_drafts_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `proposal_drafts_research_call_id_foreign` FOREIGN KEY (`research_call_id`) REFERENCES `research_calls` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proposal_drafts_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `proposal_drafts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_drafts`
--

LOCK TABLES `proposal_drafts` WRITE;
/*!40000 ALTER TABLE `proposal_drafts` DISABLE KEYS */;
INSERT INTO `proposal_drafts` VALUES (4,4,1,NULL,'hit',NULL,NULL,NULL,NULL,'draft',0,'2026-07-20 01:25:04','2026-07-20 01:25:04',NULL),(5,4,1,NULL,'Athena',12,'2026-08-01','2027-08-01','Djanisse Villaflor','draft',2,'2026-07-20 01:36:47','2026-07-20 01:39:53',NULL),(6,4,1,NULL,'hdfxhhh',1,'2026-07-30','2026-08-30','Djanisse Villaflor','draft',2,'2026-07-20 17:45:18','2026-07-20 17:46:16',NULL),(7,4,1,NULL,'sample',2,'2026-07-21','2026-09-21','Djanisse Villaflor','draft',1,'2026-07-20 18:44:15','2026-07-20 18:44:30',NULL),(10,4,1,NULL,'saa',3,'2026-07-21','2026-10-21','Djanisse Villaflor','draft',2,'2026-07-20 19:09:58','2026-07-20 23:35:39',NULL),(11,1,1,NULL,'AEGIS - Badadim Badadum',99,'2026-08-05','2034-11-05','Neil Carlo Delmo','draft',3,'2026-07-21 03:44:52','2026-07-21 03:45:27',NULL),(12,1,1,NULL,'sample1',1,'2026-07-24','2026-08-24','Neil Carlo Delmo','draft',4,'2026-07-24 07:39:28','2026-07-24 17:06:00',NULL),(14,1,1,NULL,'Sample 2',3,'2026-07-31','2026-10-31','Neil Carlo Delmo','draft',1,'2026-07-24 17:15:43','2026-07-25 06:42:52',NULL),(16,1,2,1,'Sample',3,'2026-07-27','2026-10-26','Neil Carlo Delmo','draft',0,'2026-07-26 23:30:09','2026-07-26 23:30:09',NULL),(17,1,NULL,NULL,'sample1',NULL,NULL,NULL,'','draft',1,'2026-09-27 16:46:44','2026-09-27 18:48:04',NULL);
/*!40000 ALTER TABLE `proposal_drafts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_file_annotations`
--

DROP TABLE IF EXISTS `proposal_file_annotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_file_annotations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proposal_version_file_id` bigint unsigned NOT NULL,
  `reviewer_id` bigint unsigned DEFAULT NULL,
  `topic_review_file_revision_id` bigint unsigned DEFAULT NULL,
  `annotation_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_number` smallint unsigned NOT NULL,
  `selected_text` text COLLATE utf8mb4_unicode_ci,
  `rectangles` json NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `editor_target` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `feedback_source` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'research_head',
  `co_evaluator_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proposal_file_annotations_reviewer_id_foreign` (`reviewer_id`),
  KEY `proposal_file_annotations_file_page_index` (`proposal_version_file_id`,`page_number`),
  KEY `proposal_file_annotations_revision_created_index` (`topic_review_file_revision_id`,`created_at`),
  CONSTRAINT `proposal_file_annotations_proposal_version_file_id_foreign` FOREIGN KEY (`proposal_version_file_id`) REFERENCES `proposal_version_files` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proposal_file_annotations_reviewer_id_foreign` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `proposal_file_annotations_revision_fk` FOREIGN KEY (`topic_review_file_revision_id`) REFERENCES `topic_review_file_revisions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_file_annotations`
--

LOCK TABLES `proposal_file_annotations` WRITE;
/*!40000 ALTER TABLE `proposal_file_annotations` DISABLE KEYS */;
INSERT INTO `proposal_file_annotations` VALUES (1,1,1,1,'text',1,'X. Expected Output of the Project: (based on expanded 6Ps & 2Is of research)\n1. Publication: Sample\n2. Patent: Sample\n3. Product: Sample\n4. People Service: Sample\n5. Place & Partnership: Sample\n6. Policy: Sample\n7. Social Impact: Sample\n8. Economic Impact: Samp','[{\"x\": 0.082899, \"y\": 0.639826, \"width\": 0.267338, \"height\": 0.013006}, {\"x\": 0.349888, \"y\": 0.639826, \"width\": 0.004507, \"height\": 0.013006}, {\"x\": 0.354594, \"y\": 0.639826, \"width\": 0.307752, \"height\": 0.013006}, {\"x\": 0.10268, \"y\": 0.653326, \"width\": 0.013498, \"height\": 0.013006}, {\"x\": 0.116156, \"y\": 0.653326, \"width\": 0.004507, \"height\": 0.013006}, {\"x\": 0.14162, \"y\": 0.654023, \"width\": 0.149642, \"height\": 0.013006}, {\"x\": 0.10268, \"y\": 0.667523, \"width\": 0.013498, \"height\": 0.013006}, {\"x\": 0.116156, \"y\": 0.667523, \"width\": 0.004507, \"height\": 0.013006}, {\"x\": 0.14162, \"y\": 0.668118, \"width\": 0.11273, \"height\": 0.013006}, {\"x\": 0.10268, \"y\": 0.68172, \"width\": 0.013498, \"height\": 0.013006}, {\"x\": 0.116156, \"y\": 0.68172, \"width\": 0.004507, \"height\": 0.013006}, {\"x\": 0.14162, \"y\": 0.682315, \"width\": 0.123695, \"height\": 0.013006}, {\"x\": 0.10268, \"y\": 0.695815, \"width\": 0.013498, \"height\": 0.013006}, {\"x\": 0.116156, \"y\": 0.695815, \"width\": 0.004507, \"height\": 0.013006}, {\"x\": 0.14162, \"y\": 0.69641, \"width\": 0.175012, \"height\": 0.013006}, {\"x\": 0.10268, \"y\": 0.710012, \"width\": 0.013498, \"height\": 0.013006}, {\"x\": 0.116156, \"y\": 0.710012, \"width\": 0.004507, \"height\": 0.013006}, {\"x\": 0.14162, \"y\": 0.710607, \"width\": 0.211411, \"height\": 0.013006}, {\"x\": 0.10268, \"y\": 0.724107, \"width\": 0.013498, \"height\": 0.013006}, {\"x\": 0.116156, \"y\": 0.724107, \"width\": 0.004507, \"height\": 0.013006}, {\"x\": 0.14162, \"y\": 0.724804, \"width\": 0.113708, \"height\": 0.013006}, {\"x\": 0.10268, \"y\": 0.738289, \"width\": 0.013498, \"height\": 0.013006}, {\"x\": 0.116156, \"y\": 0.738289, \"width\": 0.004507, \"height\": 0.013006}, {\"x\": 0.14162, \"y\": 0.738899, \"width\": 0.167079, \"height\": 0.013006}, {\"x\": 0.10268, \"y\": 0.752384, \"width\": 0.013498, \"height\": 0.013006}, {\"x\": 0.116156, \"y\": 0.753313, \"width\": 0.004507, \"height\": 0.011758}, {\"x\": 0.116156, \"y\": 0.752384, \"width\": 0.004507, \"height\": 0.013006}, {\"x\": 0.14162, \"y\": 0.753096, \"width\": 0.181736, \"height\": 0.013006}]','add more',NULL,'2026-07-26 22:35:05','2026-07-26 22:35:38','research_head',NULL);
/*!40000 ALTER TABLE `proposal_file_annotations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_file_review_checks`
--

DROP TABLE IF EXISTS `proposal_file_review_checks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_file_review_checks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proposal_version_file_id` bigint unsigned NOT NULL,
  `reviewer_id` bigint unsigned NOT NULL,
  `reviewed_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_file_review_checks_file_reviewer_unique` (`proposal_version_file_id`,`reviewer_id`),
  KEY `proposal_file_review_checks_reviewer_reviewed_index` (`reviewer_id`,`reviewed_at`),
  CONSTRAINT `proposal_file_review_checks_proposal_version_file_id_foreign` FOREIGN KEY (`proposal_version_file_id`) REFERENCES `proposal_version_files` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proposal_file_review_checks_reviewer_id_foreign` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_file_review_checks`
--

LOCK TABLES `proposal_file_review_checks` WRITE;
/*!40000 ALTER TABLE `proposal_file_review_checks` DISABLE KEYS */;
/*!40000 ALTER TABLE `proposal_file_review_checks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_signatories`
--

DROP TABLE IF EXISTS `proposal_signatories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_signatories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proposal_signatories_role_key_index` (`role_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_signatories`
--

LOCK TABLES `proposal_signatories` WRITE;
/*!40000 ALTER TABLE `proposal_signatories` DISABLE KEYS */;
/*!40000 ALTER TABLE `proposal_signatories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_similarity_checks`
--

DROP TABLE IF EXISTS `proposal_similarity_checks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_similarity_checks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proposal_version_file_id` bigint unsigned NOT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `handled_by` bigint unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'requested',
  `similarity_score` decimal(5,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `report_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_similarity_checks_proposal_version_file_id_unique` (`proposal_version_file_id`),
  KEY `proposal_similarity_checks_requested_by_foreign` (`requested_by`),
  KEY `proposal_similarity_checks_handled_by_foreign` (`handled_by`),
  KEY `proposal_similarity_checks_status_index` (`status`),
  CONSTRAINT `proposal_similarity_checks_handled_by_foreign` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `proposal_similarity_checks_proposal_version_file_id_foreign` FOREIGN KEY (`proposal_version_file_id`) REFERENCES `proposal_version_files` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proposal_similarity_checks_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_similarity_checks`
--

LOCK TABLES `proposal_similarity_checks` WRITE;
/*!40000 ALTER TABLE `proposal_similarity_checks` DISABLE KEYS */;
/*!40000 ALTER TABLE `proposal_similarity_checks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_stage_transitions`
--

DROP TABLE IF EXISTS `proposal_stage_transitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_stage_transitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint unsigned NOT NULL,
  `from_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `previous_started_at` timestamp NULL DEFAULT NULL,
  `changed_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proposal_stage_transitions_topic_id_foreign` (`topic_id`),
  KEY `proposal_stage_transitions_from_status_changed_at_index` (`from_status`,`changed_at`),
  CONSTRAINT `proposal_stage_transitions_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_stage_transitions`
--

LOCK TABLES `proposal_stage_transitions` WRITE;
/*!40000 ALTER TABLE `proposal_stage_transitions` DISABLE KEYS */;
/*!40000 ALTER TABLE `proposal_stage_transitions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_templates`
--

DROP TABLE IF EXISTS `proposal_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `revision_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workflow_stage` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'initial_submission',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `checksum` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_templates_slug_unique` (`slug`),
  KEY `proposal_templates_uploaded_by_foreign` (`uploaded_by`),
  KEY `proposal_templates_is_active_name_index` (`is_active`,`name`),
  KEY `proposal_templates_workflow_stage_index` (`workflow_stage`),
  CONSTRAINT `proposal_templates_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_templates`
--

LOCK TABLES `proposal_templates` WRITE;
/*!40000 ALTER TABLE `proposal_templates` DISABLE KEYS */;
INSERT INTO `proposal_templates` VALUES (1,'detailed-proposal','Detailed Research Proposal','Primary BatStateU research proposal form (Revision 04)',NULL,NULL,'initial_submission','proposals/templates/BatStateU-FO-RES-02_Detailed Research Proposal_Rev.04.docx','BatStateU-FO-RES-02_Detailed Research Proposal_Rev.04.docx',NULL,NULL,NULL,1,NULL,'2026-07-20 00:11:56','2026-07-20 00:11:56'),(2,'work-plan','Attachment A - Work Plan','Required project activities and schedule template',NULL,NULL,'initial_submission','proposals/templates/BatStateU-FO-RES-02 (Attachment A - Work Plan).docx','BatStateU-FO-RES-02 (Attachment A - Work Plan).docx',NULL,NULL,NULL,1,NULL,'2026-07-20 00:11:56','2026-07-20 00:11:56'),(3,'line-item-budget','Attachment B - Line-Item Budget','Required detailed project budget template',NULL,NULL,'initial_submission','proposals/templates/BatStateU-FO-RES-02 (Attachment B - Line-Item Budget) 10.17.24.docx','BatStateU-FO-RES-02 (Attachment B - Line-Item Budget) 10.17.24.docx',NULL,NULL,NULL,1,NULL,'2026-07-20 00:11:56','2026-07-20 00:11:56'),(4,'curriculum-vitae','Attachment C - Curriculum Vitae','Curriculum vitae form for the project team',NULL,NULL,'initial_submission','proposals/templates/BatStateU-FO-RES-02 (Attachment C - Curriculum Vitae).docx','BatStateU-FO-RES-02 (Attachment C - Curriculum Vitae).docx',NULL,NULL,NULL,1,NULL,'2026-07-20 00:11:56','2026-07-20 00:11:56'),(5,'expense-breakdown','Estimated Expense Breakdown','Spreadsheet for itemized expense details',NULL,NULL,'initial_submission','proposals/templates/Estimated Breakdown and Details of Expenses.xlsx','Estimated Breakdown and Details of Expenses.xlsx',NULL,NULL,NULL,1,NULL,'2026-07-20 00:11:56','2026-07-20 00:11:56'),(6,'extended-work-plan','Work Plan - More Than 12 Months','Additional work-plan format for longer projects',NULL,NULL,'initial_submission','proposals/templates/Sample Template of Work Plan for above 12 months.docx','Sample Template of Work Plan for above 12 months.docx',NULL,NULL,NULL,1,NULL,'2026-07-20 00:11:56','2026-07-20 00:11:56'),(7,'gad-generic-checklist','GAD Generic Checklist','Required gender-responsiveness checklist for the initial proposal package','Faculty proponents must complete and include this checklist with the initial submission.',NULL,'initial_submission','proposals/templates/Box 7a GAD Generic Checklist.docx','Box 7a GAD Generic Checklist.docx',NULL,NULL,NULL,1,NULL,'2026-07-20 00:11:58','2026-07-20 00:11:58'),(8,'initial-screening-form','Initial Screening Form','Official screening form for the Research/RDES Head and assigned co-evaluator','Prepare two copies: one for the Research/RDES Head and one for the assigned co-evaluator.',NULL,'initial_screening','proposals/templates/BatStateU-FO-RES-03_Initial Screening Form_Rev. 02.doc','BatStateU-FO-RES-03_Initial Screening Form_Rev. 02.doc',NULL,NULL,NULL,1,NULL,'2026-07-20 00:11:58','2026-07-20 00:11:58'),(9,'lrec-comment-response-form','Comment-Response Form (Institutional) - LREC','Form for documenting responses and exact locations of changes made after evaluation','Use during revision to answer each comment and identify the corresponding page and paragraph.',NULL,'revision_response','proposals/templates/Comment-Response Form (Institutional) - LREC.docx','Comment-Response Form (Institutional) - LREC.docx',NULL,NULL,NULL,1,NULL,'2026-07-20 00:11:58','2026-07-20 00:11:58');
/*!40000 ALTER TABLE `proposal_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_version_files`
--

DROP TABLE IF EXISTS `proposal_version_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_version_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proposal_version_id` bigint unsigned NOT NULL,
  `source_version_file_id` bigint unsigned DEFAULT NULL,
  `document_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` smallint unsigned NOT NULL DEFAULT '0',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `checksum` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_data` json DEFAULT NULL,
  `is_carried_forward` tinyint(1) NOT NULL DEFAULT '0',
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `superseded_at` timestamp NULL DEFAULT NULL,
  `superseded_by_version_file_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_version_file_slot_unique` (`proposal_version_id`,`document_type`,`position`),
  KEY `proposal_version_files_source_version_file_id_foreign` (`source_version_file_id`),
  KEY `proposal_version_files_proposal_version_id_document_type_index` (`proposal_version_id`,`document_type`),
  KEY `proposal_version_files_uploaded_by_foreign` (`uploaded_by`),
  KEY `proposal_version_files_proposal_version_id_uploaded_by_index` (`proposal_version_id`,`uploaded_by`),
  KEY `proposal_version_files_superseded_by_version_file_id_foreign` (`superseded_by_version_file_id`),
  KEY `proposal_version_files_superseded_at_index` (`superseded_at`),
  CONSTRAINT `proposal_version_files_proposal_version_id_foreign` FOREIGN KEY (`proposal_version_id`) REFERENCES `proposal_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proposal_version_files_source_version_file_id_foreign` FOREIGN KEY (`source_version_file_id`) REFERENCES `proposal_version_files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `proposal_version_files_superseded_by_version_file_id_foreign` FOREIGN KEY (`superseded_by_version_file_id`) REFERENCES `proposal_version_files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `proposal_version_files_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_version_files`
--

LOCK TABLES `proposal_version_files` WRITE;
/*!40000 ALTER TABLE `proposal_version_files` DISABLE KEYS */;
INSERT INTO `proposal_version_files` VALUES (1,1,NULL,'detailed_proposal',0,'proposal-packages/1/ec7603da-1088-445b-9b41-73168e25a255/detailed-proposal/8c81dc00-a6e7-43ae-8c56-254234129263.pdf','sample-detailed-research-proposal.pdf','application/pdf',223874,'491cf799bbd92fe799f93a4947557e6478a8476130a6b5aa02bf105a81c48f7b','{\"sdgs\": [\"1\", \"2\", \"3\"], \"staff\": [{\"name\": \"Djanisse Villaflor\", \"email\": \"23-73453@g.batstate-u.edu.ph\", \"contact\": \"09385327606\"}], \"rationale\": \"Sample\", \"objectives\": \"Sample\", \"references\": \"Sample\", \"methodology\": {\"data_analysis\": \"Sample\", \"research_design\": \"Sample\", \"specific_methods\": \"Sample\"}, \"leader_email\": \"23-78498@g.batstate-u.edu.ph\", \"project_title\": \"Sample\", \"leader_contact\": \"09385327606\", \"project_leader\": \"Neil Carlo Delmo\", \"executive_brief\": \"Sample\", \"research_agenda\": \"Sample\", \"expected_outputs\": {\"patent\": \"Sample\", \"policy\": \"Sample\", \"product\": \"Sample\", \"publication\": \"Sample\", \"social_impact\": \"Sample\", \"people_service\": \"Sample\", \"economic_impact\": \"Sample\", \"place_partnership\": \"Sample\"}, \"proponent_campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"responsibilities\": [{\"name\": \"Neil Carlo Delmo\", \"duties\": \"Sample\"}, {\"name\": \"Djanisse Villaflor\", \"duties\": \"Sample\"}], \"proponent_college\": \"CICS\", \"cooperating_agency\": \"Sample\", \"related_literature\": \"Sample\", \"proponent_department\": \"\"}',0,NULL,NULL,NULL,'2026-07-26 19:33:23','2026-07-26 19:33:23'),(2,1,NULL,'work_plan',0,'proposal-packages/1/ec7603da-1088-445b-9b41-73168e25a255/work-plan/bd426b51-cd53-4f4a-95d6-11c5e2273b0f.pdf','sample-work-plan.pdf','application/pdf',130027,'88c42dba0951a8582924a34a4aca3d9dbf6b868b8205fe52e8aaf1550caeb788','{\"entries\": [{\"months\": [\"1\", \"2\", \"3\"], \"activity\": \"Sample\", \"objective\": \"Sample\", \"expected_output\": \"Sample\"}], \"planned_end\": \"2026-10-31\", \"prepared_by\": \"Neil Carlo Delmo\", \"planned_start\": \"2026-07-31\", \"project_title\": \"Sample\", \"total_duration_months\": 3}',0,NULL,NULL,NULL,'2026-07-26 19:33:23','2026-07-26 19:33:23'),(3,1,NULL,'line_item_budget',0,'proposal-packages/1/ec7603da-1088-445b-9b41-73168e25a255/line-item-budget/a646d2cd-dba9-4da6-b66e-ef3ff7319031.pdf','sample-line-item-budget.pdf','application/pdf',170135,'67b22c7e056365769fe9770a5506b71246d996ae579a00a68e5ad230f33af138','{\"staff\": [{\"name\": null, \"campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"college\": null}], \"amounts\": {\"other_mooe\": null, \"rent_lease\": null, \"contingency\": null, \"ict_equipment\": null, \"office_supplies\": null, \"postage_courier\": null, \"general_services\": null, \"office_equipment\": null, \"travelling_local\": null, \"supplies_materials\": null, \"telephone_expenses\": null, \"other_mooe_expenses\": null, \"repairs_maintenance\": null, \"travelling_expenses\": \"0.04\", \"printing_publication\": null, \"training_scholarship\": null, \"professional_services\": null, \"subscription_expenses\": null, \"communication_expenses\": null, \"other_general_services\": null, \"representation_expenses\": null, \"other_supplies_materials\": null, \"other_machinery_equipment\": null, \"semi_expendable_equipment\": null, \"machinery_equipment_outlay\": null, \"other_professional_services\": null, \"technical_scientific_equipment\": null}, \"planned_end\": \"2026-10-31\", \"approval_body\": null, \"leader_campus\": \"BatStateU The NEU ARASOF-Nasugbu Campus\", \"level_of_call\": null, \"planned_start\": \"2026-07-31\", \"project_title\": \"Sample\", \"leader_college\": \"\", \"project_leader\": \"Neil Carlo Delmo\", \"resolution_year\": null, \"resolution_number\": null}',0,NULL,NULL,NULL,'2026-07-26 19:33:23','2026-07-26 19:33:23'),(4,1,NULL,'expense_breakdown',0,'proposal-packages/1/ec7603da-1088-445b-9b41-73168e25a255/expense-breakdown/9ebdd16a-83c7-41a9-b83d-d933256d3e7b.xlsx','sample-estimated-expense-breakdown.xlsx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',11091,'b43fe8099a07d179c0d26dc5426646edd2c0911542031f3922794a1c34071d1d','{\"items\": [{\"unit\": \"2\", \"account\": \"General Services\", \"details\": \"Sample\", \"purpose\": \"Sample\", \"category\": \"mooe\", \"quantity\": \"10\", \"unit_cost\": \"50\", \"particulars\": \"Sample\", \"sub_account\": \"Other General Services\"}], \"project_title\": \"Sample\"}',0,NULL,NULL,NULL,'2026-07-26 19:33:23','2026-07-26 19:33:23'),(5,1,NULL,'curriculum_vitae',0,'proposal-packages/1/ec7603da-1088-445b-9b41-73168e25a255/curriculum-vitae/4b0a8035-6958-47bb-971f-f7d9ffbb059e.pdf','sample-curriculum-vitae.pdf','application/pdf',483417,'4843884d55269a23d16cdc1a0b8c276d06c900a851a32a422cec64afe72e8c04','{\"people\": [{\"email\": \"23-78498@g.batstate-u.edu.ph\", \"agency\": \"Sample\", \"awards\": [{\"rank\": \"Sample\", \"title\": \"Sample\", \"category\": null, \"year_granted\": \"2010\", \"granting_institution\": \"Sample\"}, {\"rank\": null, \"title\": null, \"category\": null, \"year_granted\": null, \"granting_institution\": null}, {\"rank\": null, \"title\": null, \"category\": null, \"year_granted\": null, \"granting_institution\": null}], \"gender\": \"female\", \"street\": \"Sample\", \"barangay\": \"Sample\", \"birthday\": \"2026-03-05\", \"landline\": \"Sample\", \"projects\": [{\"title\": \"Sample\", \"sector\": \"Sample\", \"year_to\": \"2026\", \"year_from\": \"2030\", \"designation\": null, \"current_status\": \"Sample\"}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}], \"province\": \"Sample\", \"cellphone\": \"Sample\", \"last_name\": \"Delmo\", \"employment\": [{\"agency\": \"Sample\", \"end_date\": \"2026-07-04\", \"start_date\": \"2026-07-01\", \"monthly_salary\": \"300\", \"appointment_status\": null, \"plantilla_position\": \"Sample\"}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}], \"first_name\": \"Neil\", \"middle_name\": \"Carlo\", \"municipality\": \"Sample\", \"publications\": [{\"place\": \"Sample\", \"title\": \"Sample\", \"authoring_type\": \"Sample\", \"year_published\": \"2026\", \"publication_group\": \"Sample\"}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}], \"scholarships\": [{\"sponsor\": \"Sample\", \"period_end\": \"2026-07-04\", \"period_start\": \"2020-07-01\", \"date_released\": \"2026-07-05\", \"extension_end\": \"2026-07-04\", \"item_expenses\": \"Sample\", \"amount_approved\": \"10000\", \"amount_released\": \"10000\", \"extension_start\": \"2026-07-01\", \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}], \"presentations\": [{\"date\": \"2026-07-01\", \"title\": \"Sample\", \"venue\": \"Sample\", \"sponsor\": \"Sample\", \"category\": null, \"conference_title\": \"Sample\"}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}], \"specializations\": [{\"field\": \"Sample\", \"primary_field\": null}, {\"field\": null, \"primary_field\": null}, {\"field\": null, \"primary_field\": null}, {\"field\": null, \"primary_field\": null}], \"academic_background\": [{\"degree\": \"Sample\", \"sector\": \"Sample\", \"status\": \"Ongoing\", \"thesis\": \"Sample\", \"year_end\": null, \"year_start\": \"2020\", \"major_field\": \"Sample\", \"learning_institution\": \"Sample\"}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}]}, {\"email\": \"23-73453@g.batstate-u.edu.ph\", \"agency\": \"Sample\", \"awards\": [{\"rank\": null, \"title\": null, \"category\": null, \"year_granted\": null, \"granting_institution\": null}, {\"rank\": null, \"title\": null, \"category\": null, \"year_granted\": null, \"granting_institution\": null}, {\"rank\": null, \"title\": null, \"category\": null, \"year_granted\": null, \"granting_institution\": null}], \"gender\": \"female\", \"street\": \"Sample\", \"barangay\": \"Sample\", \"birthday\": \"2026-07-26\", \"landline\": \"Sample\", \"projects\": [{\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}, {\"title\": null, \"sector\": null, \"year_to\": null, \"year_from\": null, \"designation\": null, \"current_status\": null}], \"province\": \"Sample\", \"cellphone\": \"Sample\", \"last_name\": \"Villaflor\", \"employment\": [{\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}, {\"agency\": null, \"end_date\": null, \"start_date\": null, \"monthly_salary\": null, \"appointment_status\": null, \"plantilla_position\": null}], \"first_name\": \"Djanisse\", \"middle_name\": \"Sample\", \"municipality\": \"Sample\", \"publications\": [{\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}, {\"place\": null, \"title\": null, \"authoring_type\": null, \"year_published\": null, \"publication_group\": null}], \"scholarships\": [{\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}, {\"sponsor\": null, \"period_end\": null, \"period_start\": null, \"date_released\": null, \"extension_end\": null, \"item_expenses\": null, \"amount_approved\": null, \"amount_released\": null, \"extension_start\": null, \"primary_sponsor\": null}], \"presentations\": [{\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}, {\"date\": null, \"title\": null, \"venue\": null, \"sponsor\": null, \"category\": null, \"conference_title\": null}], \"specializations\": [{\"field\": null, \"primary_field\": null}, {\"field\": null, \"primary_field\": null}, {\"field\": null, \"primary_field\": null}, {\"field\": null, \"primary_field\": null}], \"academic_background\": [{\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}, {\"degree\": null, \"sector\": null, \"status\": null, \"thesis\": null, \"year_end\": null, \"year_start\": null, \"major_field\": null, \"learning_institution\": null}]}]}',0,NULL,NULL,NULL,'2026-07-26 19:33:23','2026-07-26 19:33:23'),(6,1,NULL,'gad_checklist',0,'proposal-packages/1/ec7603da-1088-445b-9b41-73168e25a255/gad-checklist/133aa0d4-6490-4c74-b485-d361a1680d0e.pdf','sample-gad-checklist.pdf','application/pdf',354553,'13c537058d24a1ea1003f99a763393335fa54de3ae7683ee451c561dc4a29bd7','{\"project_title\": \"Sample\", \"project_leader\": \"Neil Carlo Delmo\"}',0,NULL,NULL,NULL,'2026-07-26 19:33:24','2026-07-26 19:33:24'),(7,1,NULL,'initial_screening_form',0,'proposal-packages/1/ec7603da-1088-445b-9b41-73168e25a255/initial-screening-form/23017373-88f2-45b3-b77c-e44891612636.pdf','sample-initial-screening-form.pdf','application/pdf',154386,'f0d79df689b41181caaab439022892c0c2861f3edda6fa82a3dba0cabe7691ab','{\"project_title\": \"Sample\", \"project_leader\": \"Neil Carlo Delmo\"}',0,NULL,NULL,NULL,'2026-07-26 19:33:24','2026-07-26 19:33:24');
/*!40000 ALTER TABLE `proposal_version_files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proposal_versions`
--

DROP TABLE IF EXISTS `proposal_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint unsigned NOT NULL,
  `submitted_by` bigint unsigned DEFAULT NULL,
  `version_number` int unsigned NOT NULL,
  `submission_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `change_summary` text COLLATE utf8mb4_unicode_ci,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `checksum` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `estimated_budget` decimal(12,2) DEFAULT NULL,
  `estimated_duration_months` smallint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_versions_topic_id_version_number_unique` (`topic_id`,`version_number`),
  KEY `proposal_versions_submitted_by_foreign` (`submitted_by`),
  KEY `proposal_versions_topic_id_created_at_index` (`topic_id`,`created_at`),
  CONSTRAINT `proposal_versions_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `proposal_versions_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proposal_versions`
--

LOCK TABLES `proposal_versions` WRITE;
/*!40000 ALTER TABLE `proposal_versions` DISABLE KEYS */;
INSERT INTO `proposal_versions` VALUES (1,1,1,1,'initial',NULL,'proposal-packages/1/ec7603da-1088-445b-9b41-73168e25a255/detailed-proposal/8c81dc00-a6e7-43ae-8c56-254234129263.pdf','sample-detailed-research-proposal.pdf','application/pdf',223874,'491cf799bbd92fe799f93a4947557e6478a8476130a6b5aa02bf105a81c48f7b','Sample',NULL,NULL,3,'2026-07-26 19:33:23','2026-07-26 19:33:23');
/*!40000 ALTER TABLE `proposal_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `research_assistant_conversations`
--

DROP TABLE IF EXISTS `research_assistant_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `research_assistant_conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `messages` json NOT NULL,
  `context` json DEFAULT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci,
  `summarized_message_count` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `research_assistant_conversations_user_id_updated_at_index` (`user_id`,`updated_at`),
  CONSTRAINT `research_assistant_conversations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `research_assistant_conversations`
--

LOCK TABLES `research_assistant_conversations` WRITE;
/*!40000 ALTER TABLE `research_assistant_conversations` DISABLE KEYS */;
INSERT INTO `research_assistant_conversations` VALUES (1,1,'Refine my research question','[{\"role\": \"user\", \"content\": \"Refine my research question\", \"sources\": []}, {\"role\": \"assistant\", \"content\": \"I would be happy to help you refine your research question! \\n\\nTo get started, please share:\\n1. **Your current draft or the general topic** you are interested in.\\n2. **The specific angle or problem** you want to address.\\n3. **Your target audience or context** (e.g., is this for a specific field, region, or industry?).\\n\\nIf you are planning to submit this for an upcoming institutional call (such as the BatStateU research calls), it can also be helpful to keep the following criteria in mind so we can align your question accordingly:\\n* **Alignment with the BatStateU The NEU Research Agenda** [ATHENA 3].\\n* **Focus areas:** Does it fall under Education, Environment, Health, or Technology? [ATHENA 1].\\n* **Broader Impact:** Does it address the Philippine Development Plan (PDP) or the Sustainable Development Goals (SDGs)? [ATHENA 3].\\n* **Practical Outcomes:** Is there an opportunity to focus on collaborative research, product development, or patent generation? [ATHENA 3].\\n\\nPlease reply with your initial ideas, and we can work together to make your research question clear, focused, and impactful!\", \"sources\": [{\"url\": \"http://localhost/athena-app/research-calls\", \"title\": \"Institutional Research Call 2026\", \"reference\": \"ATHENA 1\"}, {\"url\": \"http://localhost/athena-app/proposal-templates/detailed-proposal/download\", \"title\": \"Detailed Research Proposal\", \"reference\": \"ATHENA 2\"}, {\"url\": \"http://localhost/athena-app/research-calls\", \"title\": \"AUGUST 2026 Implementation\", \"reference\": \"ATHENA 3\"}, {\"url\": \"http://localhost/athena-app/proposal-templates/initial-screening-form/download\", \"title\": \"Initial Screening Form\", \"reference\": \"ATHENA 4\"}]}]',NULL,NULL,0,'2026-07-26 18:02:33','2026-07-26 18:02:52'),(2,1,'suggest topic','[{\"role\": \"user\", \"content\": \"suggest topic\", \"sources\": []}, {\"role\": \"assistant\", \"content\": \"Hello, Neil. I would be glad to help you brainstorm research topics. \\n\\nTo give you a starting point, here are a few highly relevant, interdisciplinary research areas currently trending in academic literature:\\n\\n1. **AI and Higher Education:** Assessing the impact of generative AI tools on academic integrity, curriculum design, or student learning outcomes.\\n2. **Organizational Resilience:** Investigating how remote/hybrid work models affect institutional culture, employee well-being, or productivity in post-pandemic environments.\\n3. **Sustainable Development:** Analyzing local community-level adaptation strategies to climate change or the viability of circular economy practices in small-to-medium enterprises (SMEs).\\n4. **Digital Health & Well-being:** Evaluating the effectiveness of mobile health applications or telemedicine in underserved communities.\\n\\nTo help me narrow this down and provide highly tailored suggestions, **what is your specific field of study, department, or primary research interest?**\", \"sources\": []}]',NULL,NULL,0,'2026-07-26 18:03:09','2026-07-26 18:03:17'),(3,1,'suggest category','[{\"role\": \"user\", \"content\": \"suggest category\", \"sources\": []}, {\"role\": \"assistant\", \"content\": \"Hello Neil Carlo. I would be glad to help you suggest a category, but I need a little more context to give you the most relevant options. \\n\\nCould you clarify what you are categorizing? For example, are you looking for:\\n\\n1. **Research Methodology/Design** (e.g., qualitative, quantitative, mixed-methods)?\\n2. **Proposal Funding or Subject Area** (e.g., applied research, institutional development, STEM vs. social sciences)?\\n3. **Literature Review Themes** (organizing your sources into conceptual categories)?\\n\\nOnce you share a brief description of your project or what you are working on, I can provide specific, tailored suggestions!\", \"sources\": []}]',NULL,NULL,0,'2026-07-26 18:04:23','2026-07-26 18:04:30'),(4,1,'sdg','[{\"role\": \"user\", \"content\": \"sdg\", \"sources\": []}, {\"role\": \"assistant\", \"content\": \"In the context of the BatStateU The NEU Research Call (for the August 2026 Implementation, Academic Year 2026-2027, 1st Term), the **Sustainable Development Goals (SDGs)** are a key focus area [ATHENA 1]. \\n\\nTo be eligible for this research call, proposals must be **cross-disciplinary or interdisciplinary research projects** that address either the SDGs or the Philippine Development Plan (PDP) [ATHENA 1]. \\n\\nAdditionally, submitted proposals must meet the following criteria:\\n* **Alignment:** Aligned with the BatStateU The NEU Research Agenda [ATHENA 1].\\n* **Budget:** Have a budget requirement not\", \"sources\": [{\"url\": \"http://localhost/athena-app/research-calls\", \"title\": \"AUGUST 2026 Implementation\", \"reference\": \"ATHENA 1\"}]}, {\"role\": \"user\", \"content\": \"can you read the proposal package in the title sample from my drafts\", \"sources\": []}, {\"role\": \"assistant\", \"content\": \"Based on the available records, the ATHENA knowledge base does not currently contain your personal drafts or a specific \\\"title sample\\\" from your drafts. \\n\\nHowever, if you are looking for\", \"sources\": [{\"url\": \"http://localhost/athena-app/proposal-templates/detailed-proposal/download\", \"title\": \"Detailed Research Proposal\", \"reference\": \"ATHENA 1\"}, {\"url\": \"http://localhost/athena-app/proposal-templates/gad-generic-checklist/download\", \"title\": \"GAD Generic Checklist\", \"reference\": \"ATHENA 2\"}, {\"url\": \"http://localhost/athena-app/proposal-templates/expense-breakdown/download\", \"title\": \"Estimated Expense Breakdown\", \"reference\": \"ATHENA 3\"}, {\"url\": \"http://localhost/athena-app/proposal-templates/work-plan/download\", \"title\": \"Attachment A - Work Plan\", \"reference\": \"ATHENA 4\"}, {\"url\": \"http://localhost/athena-app/proposal-templates/line-item-budget/download\", \"title\": \"Attachment B - Line-Item Budget\", \"reference\": \"ATHENA 5\"}]}]',NULL,NULL,0,'2026-07-26 18:04:38','2026-07-26 18:29:01'),(5,1,'suggest category for that','[{\"role\": \"user\", \"content\": \"suggest category for that\", \"sources\": []}, {\"role\": \"assistant\", \"content\": \"Because your current proposal title is **\\\"Sample\\\"** and has a short duration of **3 months**, it is highly likely a placeholder or a short-term micro-grant/institutional project. \\n\\nTo help you categorize it correctly—and to address the reviewer\'s comment (*\\\"you should add more\\\"*), here are the standard research categories typically used in research portals (especially those in Philippine HEIs and government agencies like DOST/DPMIS, given the reviewer\'s name):\\n\\n---\\n\\n### 1. Categorization by Research Sector (Choose the one that fits your actual topic)\\n\\n*   **Information, Education, and Communication (IEC) / Social Sciences**\\n    *   *Use this if:* Your 3-month project is a survey, a policy review, a feasibility study, or community assessment.\\n    *   *Example Category:* *Social Sciences, Education, or Policy Research.*\\n*   **Industry, Energy, and Emerging Technology**\\n    *   *Use this if:* You are developing a quick software prototype, an IT solution, or testing a basic engineering concept.\\n    *   *Example Category:* *Information and Communications Technology (ICT) / Emerging Technologies.*\\n*   **Health and Medicine**\\n    *   *Use this if:* You are doing a quick public health assessment, dietary survey, or minor lab-scale bio-screening.\\n    *   *Example Category:* *Public Health / Diagnostics / Digital Health.*\\n*   **Agriculture, Aquatic, and Natural Resources**\\n    *   *Use this if:* You are doing soil testing, local flora/fauna mapping, or post-harvest assessments.\\n    *   *Example Category\", \"sources\": []}]','{\"topic_id\": 1}',NULL,0,'2026-07-26 23:23:45','2026-07-26 23:24:02'),(6,1,'suggest topics','[{\"role\": \"user\", \"content\": \"suggest topics\", \"sources\": []}, {\"role\": \"assistant\", \"content\": \"Hello, Neil. I would be glad to help you brainstorm research topics for your department or personal agenda. \\n\\nTo give you a starting point, here are a few high-impact, contemporary research areas across different domains:\\n\\n*   **Educational Technology & AI:** The impact of generative AI tools on academic integrity, curriculum design, or student learning outcomes in higher education.\\n*   **Organizational Behavior & Higher Ed:** The long-term effects of hybrid work models on faculty collaboration, research productivity, and institutional culture.\\n*   **Public Health & Community Resilience:** Evaluating the effectiveness of community-based mental health interventions in post-pandemic urban environments.\\n*   **Sustainable Development:** Assessing the barriers to adopting green technologies or sustainable waste management practices in local municipalities.\\n\\nTo help me tailor these suggestions to your specific needs, **what is your primary field of study, or what is the overarching theme of your research group?** (e.g., STEM, Social Sciences, Business, Health Sciences, etc.)\", \"sources\": []}]',NULL,NULL,0,'2026-07-30 00:26:10','2026-07-30 00:26:18');
/*!40000 ALTER TABLE `research_assistant_conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `research_call_category`
--

DROP TABLE IF EXISTS `research_call_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `research_call_category` (
  `research_call_id` bigint unsigned NOT NULL,
  `research_category_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`research_call_id`,`research_category_id`),
  KEY `research_call_category_research_category_id_foreign` (`research_category_id`),
  CONSTRAINT `research_call_category_research_call_id_foreign` FOREIGN KEY (`research_call_id`) REFERENCES `research_calls` (`id`) ON DELETE CASCADE,
  CONSTRAINT `research_call_category_research_category_id_foreign` FOREIGN KEY (`research_category_id`) REFERENCES `research_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `research_call_category`
--

LOCK TABLES `research_call_category` WRITE;
/*!40000 ALTER TABLE `research_call_category` DISABLE KEYS */;
INSERT INTO `research_call_category` VALUES (1,1),(2,1),(1,2),(2,2),(1,3),(2,3),(1,4);
/*!40000 ALTER TABLE `research_call_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `research_call_deadline_dismissals`
--

DROP TABLE IF EXISTS `research_call_deadline_dismissals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `research_call_deadline_dismissals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `research_call_id` bigint unsigned NOT NULL,
  `dismissed_on` date NOT NULL,
  `deadline_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `research_call_deadline_user_call_unique` (`user_id`,`research_call_id`),
  KEY `research_call_deadline_dismissals_research_call_id_foreign` (`research_call_id`),
  KEY `research_call_deadline_user_day_index` (`user_id`,`dismissed_on`),
  CONSTRAINT `research_call_deadline_dismissals_research_call_id_foreign` FOREIGN KEY (`research_call_id`) REFERENCES `research_calls` (`id`) ON DELETE CASCADE,
  CONSTRAINT `research_call_deadline_dismissals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `research_call_deadline_dismissals`
--

LOCK TABLES `research_call_deadline_dismissals` WRITE;
/*!40000 ALTER TABLE `research_call_deadline_dismissals` DISABLE KEYS */;
/*!40000 ALTER TABLE `research_call_deadline_dismissals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `research_calls`
--

DROP TABLE IF EXISTS `research_calls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `research_calls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `academic_year` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `term` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `reference_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opens_at` datetime NOT NULL,
  `closes_at` datetime NOT NULL,
  `initial_evaluation_start_date` date DEFAULT NULL,
  `initial_evaluation_end_date` date DEFAULT NULL,
  `paper_revisions_start_date` date DEFAULT NULL,
  `paper_revisions_end_date` date DEFAULT NULL,
  `lrec_start_date` date DEFAULT NULL,
  `lrec_end_date` date DEFAULT NULL,
  `implementation_start_date` date DEFAULT NULL,
  `implementation_end_date` date DEFAULT NULL,
  `max_active_research_per_faculty` smallint unsigned NOT NULL DEFAULT '2',
  `maximum_budget` decimal(12,2) DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `opening_reminder_sent_at` timestamp NULL DEFAULT NULL,
  `faculty_open_notification_sent_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `research_calls_created_by_foreign` (`created_by`),
  KEY `research_calls_status_opens_at_closes_at_index` (`status`,`opens_at`,`closes_at`),
  CONSTRAINT `research_calls_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `research_calls`
--

LOCK TABLES `research_calls` WRITE;
/*!40000 ALTER TABLE `research_calls` DISABLE KEYS */;
INSERT INTO `research_calls` VALUES (1,'Institutional Research Call 2026','2026-2027','First Semester','Prototype institutional call for faculty research proposals.',NULL,'2026-07-13 08:12:29','2026-09-20 08:12:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,150000.00,'open',NULL,NULL,1,'2026-07-20 00:12:29','2026-07-20 00:12:29'),(2,'AUGUST 2026 Implementation','2026-2027','1st','THE RESEARCH PROPOSALS MUST BE:\r\n\r\n✔ Aligned with the BatStateU The NEU Research Agenda\r\n\r\n✔ With a budget requirement not exceeding ₱150,000.00\r\n\r\n✔ Cross-disciplinary or interdisciplinary research projects addressing the Philippine Development Plan (PDP) or the Sustainable Development Goals (SDGs)\r\n\r\n✔ Collaborative research focusing on product development and patent generation','research-calls/cm3eNgINV0UqqrvjG7YXnvMoMnLZxvtDP0sqrQxo.png','2026-07-26 13:00:00','2026-08-08 23:59:00','2026-08-11','2026-08-22','2026-08-08','2026-09-05','2026-09-08','2026-09-19','2026-08-01',NULL,5,150000.00,'open',NULL,NULL,1,'2026-07-26 02:58:53','2026-07-26 18:48:15');
/*!40000 ALTER TABLE `research_calls` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `research_categories`
--

DROP TABLE IF EXISTS `research_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `research_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `research_categories_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `research_categories`
--

LOCK TABLES `research_categories` WRITE;
/*!40000 ALTER TABLE `research_categories` DISABLE KEYS */;
INSERT INTO `research_categories` VALUES (1,'Environment',NULL,1,'2026-07-20 00:12:29','2026-07-20 00:12:29'),(2,'Education',NULL,1,'2026-07-20 00:12:29','2026-07-20 00:12:29'),(3,'Technology',NULL,1,'2026-07-20 00:12:29','2026-07-20 00:12:29'),(4,'Health',NULL,1,'2026-07-20 00:12:29','2026-07-20 00:12:29');
/*!40000 ALTER TABLE `research_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `research_knowledge_entries`
--

DROP TABLE IF EXISTS `research_knowledge_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `research_knowledge_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `research_knowledge_entries_created_by_foreign` (`created_by`),
  KEY `research_knowledge_entries_is_active_category_index` (`is_active`,`category`),
  CONSTRAINT `research_knowledge_entries_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `research_knowledge_entries`
--

LOCK TABLES `research_knowledge_entries` WRITE;
/*!40000 ALTER TABLE `research_knowledge_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `research_knowledge_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `research_publication_topic`
--

DROP TABLE IF EXISTS `research_publication_topic`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `research_publication_topic` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `research_publication_id` bigint unsigned NOT NULL,
  `topic_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `publication_topic_unique` (`research_publication_id`,`topic_id`),
  KEY `research_publication_topic_topic_id_foreign` (`topic_id`),
  CONSTRAINT `research_publication_topic_research_publication_id_foreign` FOREIGN KEY (`research_publication_id`) REFERENCES `research_publications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `research_publication_topic_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `research_publication_topic`
--

LOCK TABLES `research_publication_topic` WRITE;
/*!40000 ALTER TABLE `research_publication_topic` DISABLE KEYS */;
/*!40000 ALTER TABLE `research_publication_topic` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `research_publications`
--

DROP TABLE IF EXISTS `research_publications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `research_publications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `openalex_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `authors` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `venue` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` smallint unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` text COLLATE utf8mb4_unicode_ci,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Researcher entry',
  `source_checked_at` timestamp NULL DEFAULT NULL,
  `confirmed_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `research_publications_user_id_fingerprint_unique` (`user_id`,`fingerprint`),
  CONSTRAINT `research_publications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `research_publications`
--

LOCK TABLES `research_publications` WRITE;
/*!40000 ALTER TABLE `research_publications` DISABLE KEYS */;
/*!40000 ALTER TABLE `research_publications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `researcher_profiles`
--

DROP TABLE IF EXISTS `researcher_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `researcher_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `openalex_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_name` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `affiliation` text COLLATE utf8mb4_unicode_ci,
  `orcid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scholar_url` text COLLATE utf8mb4_unicode_ci,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `researcher_profiles_user_id_unique` (`user_id`),
  CONSTRAINT `researcher_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `researcher_profiles`
--

LOCK TABLES `researcher_profiles` WRITE;
/*!40000 ALTER TABLE `researcher_profiles` DISABLE KEYS */;
/*!40000 ALTER TABLE `researcher_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'research_head','web','2026-07-20 00:12:27','2026-07-20 00:12:27'),(2,'faculty','web','2026-07-20 00:12:27','2026-07-20 00:12:27'),(3,'faculty_researcher','web','2026-07-20 00:12:27','2026-07-20 00:12:27'),(4,'expert','web','2026-07-20 00:12:27','2026-07-20 00:12:27'),(5,'research_coordinator','web','2026-07-20 00:12:27','2026-07-20 00:12:27');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
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
INSERT INTO `sessions` VALUES ('c5sEsQ75LfVO2muJvMvmaHY34pZJwhGSHEpD43qL',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','eyJfdG9rZW4iOiJPczZaZ0s4anhyOUhqU3BWRmF2NTcxOGRpOVQxYnVldzRLN3prcHNyIiwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjEsIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwOlwvXC9sb2NhbGhvc3RcL2F0aGVuYS1hcHAiLCJyb3V0ZSI6bnVsbH0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1790655360),('d3sNbqRbDFqGOOkGua6n1eQufCSVkWJpwEb8MqpX',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','eyJfdG9rZW4iOiJZbXFQTDVBUldiWmduakdob3NFSzBhTXBPWmVHWmdWYkVncjVYVU80IiwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjEsIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwOlwvXC9sb2NhbGhvc3RcL2F0aGVuYS1hcHBcL25vdGlmaWNhdGlvbnMiLCJyb3V0ZSI6Im5vdGlmaWNhdGlvbnMuaW5kZXgifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1790693439),('E959YEphLMrmJ2ndovT9lZm9e0L2IBWq8yki2RvC',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','eyJfdG9rZW4iOiJGTlBJQkNkZThuQnEyTTdBZmlCTm1IMmRrNE9vYlRYbnNhdHY5ZmZFIiwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjEsIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwOlwvXC9sb2NhbGhvc3RcL2F0aGVuYS1hcHBcL25vdGlmaWNhdGlvbnMiLCJyb3V0ZSI6Im5vdGlmaWNhdGlvbnMuaW5kZXgifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1790655558);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `topic_collaborators`
--

DROP TABLE IF EXISTS `topic_collaborators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `topic_collaborators` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `project_role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `topic_collaborator_email_unique` (`topic_id`,`email`),
  UNIQUE KEY `topic_collaborator_user_unique` (`topic_id`,`user_id`),
  KEY `topic_collaborators_user_id_foreign` (`user_id`),
  CONSTRAINT `topic_collaborators_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_collaborators_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `topic_collaborators`
--

LOCK TABLES `topic_collaborators` WRITE;
/*!40000 ALTER TABLE `topic_collaborators` DISABLE KEYS */;
/*!40000 ALTER TABLE `topic_collaborators` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `topic_expert_assignments`
--

DROP TABLE IF EXISTS `topic_expert_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `topic_expert_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint unsigned NOT NULL,
  `expert_id` bigint unsigned NOT NULL,
  `assigned_by` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `recommendation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `topic_expert_assignments_expert_id_foreign` (`expert_id`),
  KEY `topic_expert_assignments_assigned_by_foreign` (`assigned_by`),
  KEY `topic_expert_assignments_topic_id_expert_id_index` (`topic_id`,`expert_id`),
  CONSTRAINT `topic_expert_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `topic_expert_assignments_expert_id_foreign` FOREIGN KEY (`expert_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_expert_assignments_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `topic_expert_assignments`
--

LOCK TABLES `topic_expert_assignments` WRITE;
/*!40000 ALTER TABLE `topic_expert_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `topic_expert_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `topic_review_file_revisions`
--

DROP TABLE IF EXISTS `topic_review_file_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `topic_review_file_revisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `topic_review_id` bigint unsigned NOT NULL,
  `proposal_version_file_id` bigint unsigned DEFAULT NULL,
  `resolved_by_version_file_id` bigint unsigned DEFAULT NULL,
  `document_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revision_note` text COLLATE utf8mb4_unicode_ci,
  `resolution_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `faculty_response` text COLLATE utf8mb4_unicode_ci,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `topic_review_file_revisions_proposal_version_file_id_foreign` (`proposal_version_file_id`),
  KEY `topic_review_file_revisions_resolved_by_version_file_id_foreign` (`resolved_by_version_file_id`),
  KEY `topic_review_file_revisions_topic_review_id_resolved_at_index` (`topic_review_id`,`resolved_at`),
  KEY `topic_review_file_revisions_document_type_resolved_at_index` (`document_type`,`resolved_at`),
  CONSTRAINT `topic_review_file_revisions_proposal_version_file_id_foreign` FOREIGN KEY (`proposal_version_file_id`) REFERENCES `proposal_version_files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `topic_review_file_revisions_resolved_by_version_file_id_foreign` FOREIGN KEY (`resolved_by_version_file_id`) REFERENCES `proposal_version_files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `topic_review_file_revisions_topic_review_id_foreign` FOREIGN KEY (`topic_review_id`) REFERENCES `topic_reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `topic_review_file_revisions`
--

LOCK TABLES `topic_review_file_revisions` WRITE;
/*!40000 ALTER TABLE `topic_review_file_revisions` DISABLE KEYS */;
INSERT INTO `topic_review_file_revisions` VALUES (1,1,1,NULL,'detailed_proposal','sample-detailed-research-proposal.pdf','See 1 highlighted revision comment(s) in ATHENA.',NULL,NULL,NULL,'2026-07-26 22:35:38','2026-07-26 22:35:38');
/*!40000 ALTER TABLE `topic_review_file_revisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `topic_reviews`
--

DROP TABLE IF EXISTS `topic_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `topic_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint unsigned NOT NULL,
  `reviewer_id` bigint unsigned DEFAULT NULL,
  `decision` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `required_signature_file_ids` json DEFAULT NULL,
  `signature_proposal_version_id` bigint unsigned DEFAULT NULL,
  `signature_superseded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `review_stage` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'initial',
  `committee_comments` json DEFAULT NULL,
  `feedback_responses` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `topic_reviews_reviewer_id_foreign` (`reviewer_id`),
  KEY `topic_reviews_topic_id_created_at_index` (`topic_id`,`created_at`),
  KEY `topic_reviews_signature_proposal_version_id_foreign` (`signature_proposal_version_id`),
  KEY `topic_reviews_signature_superseded_at_index` (`signature_superseded_at`),
  CONSTRAINT `topic_reviews_reviewer_id_foreign` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `topic_reviews_signature_proposal_version_id_foreign` FOREIGN KEY (`signature_proposal_version_id`) REFERENCES `proposal_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `topic_reviews_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `topic_reviews`
--

LOCK TABLES `topic_reviews` WRITE;
/*!40000 ALTER TABLE `topic_reviews` DISABLE KEYS */;
INSERT INTO `topic_reviews` VALUES (1,1,1,'revision_requested','you should add more',NULL,NULL,NULL,'2026-07-26 22:35:38','2026-07-26 22:35:38','initial',NULL,NULL);
/*!40000 ALTER TABLE `topic_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `topics`
--

DROP TABLE IF EXISTS `topics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `topics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `research_secretary_id` bigint unsigned DEFAULT NULL,
  `research_call_id` bigint unsigned DEFAULT NULL,
  `research_category_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `estimated_budget` decimal(12,2) DEFAULT NULL,
  `estimated_duration_months` smallint unsigned DEFAULT NULL,
  `initial_file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `final_file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signed_approval_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notice_to_proceed_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notice_to_proceed_original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notice_to_proceed_issued_by` bigint unsigned DEFAULT NULL,
  `notice_to_proceed_issued_at` timestamp NULL DEFAULT NULL,
  `notice_to_proceed_data` json DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `project_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status_started_at` timestamp NULL DEFAULT NULL,
  `research_head_viewed_version_id` bigint unsigned DEFAULT NULL,
  `review_stage` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'initial',
  `lrec_cleared_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `topics_user_id_foreign` (`user_id`),
  KEY `topics_research_category_id_foreign` (`research_category_id`),
  KEY `topics_research_call_id_user_id_index` (`research_call_id`,`user_id`),
  KEY `topics_status_research_category_id_index` (`status`,`research_category_id`),
  KEY `topics_notice_to_proceed_issued_by_foreign` (`notice_to_proceed_issued_by`),
  KEY `topics_notice_to_proceed_issued_at_index` (`notice_to_proceed_issued_at`),
  KEY `topics_status_started_at_index` (`status_started_at`),
  KEY `topics_research_secretary_id_foreign` (`research_secretary_id`),
  KEY `topics_research_head_viewed_version_id_foreign` (`research_head_viewed_version_id`),
  CONSTRAINT `topics_notice_to_proceed_issued_by_foreign` FOREIGN KEY (`notice_to_proceed_issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `topics_research_call_id_foreign` FOREIGN KEY (`research_call_id`) REFERENCES `research_calls` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topics_research_category_id_foreign` FOREIGN KEY (`research_category_id`) REFERENCES `research_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `topics_research_head_viewed_version_id_foreign` FOREIGN KEY (`research_head_viewed_version_id`) REFERENCES `proposal_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `topics_research_secretary_id_foreign` FOREIGN KEY (`research_secretary_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `topics_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `topics`
--

LOCK TABLES `topics` WRITE;
/*!40000 ALTER TABLE `topics` DISABLE KEYS */;
INSERT INTO `topics` VALUES (1,1,NULL,2,NULL,'Sample',NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'revision_requested',NULL,'2026-07-26 19:33:23','2026-07-26 22:35:38',NULL,1,'initial',NULL);
/*!40000 ALTER TABLE `topics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar` text COLLATE utf8mb4_unicode_ci,
  `college` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_number` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Neil Carlo Delmo','23-78498@g.batstate-u.edu.ph','https://lh3.googleusercontent.com/a/ACg8ocJAczS3hToyakXfYEk1yHpFppWzENXitu5IB6M0F3w1eM7EnNU8=s96-c',NULL,NULL,'104465273411615136681','2026-09-26 01:50:23','$2y$12$EbcZsbuTGPpSx/4H9UYg8u.CvGY7JTUIi97i9xa.TiT3PR7zwmbrq','5X20VUXOecE4bZTeHyYIAJUgMnYjg8yu3g69GmXqcXh5stZBp2AiqQPUW9dv','2026-07-20 00:12:28','2026-09-26 01:50:25'),(2,'Demo Faculty','faculty@example.com',NULL,NULL,NULL,NULL,'2026-07-20 00:12:29','$2y$12$tZxJ3Rr6q8WgFabQXleCLecAcbwkhlFugy906IDFC6nackKh3x3ki',NULL,'2026-07-20 00:12:29','2026-07-20 00:12:29'),(3,'Environmental Subject Expert','expert@example.com',NULL,NULL,NULL,NULL,'2026-07-20 00:12:29','$2y$12$VkbvOoNKtjxJTUSS96hgmuKrHYkS9w6wmFaZRH/kwj2KhRwF8qw0C',NULL,'2026-07-20 00:12:29','2026-07-20 00:12:29'),(4,'Djanisse Villaflor','23-73453@g.batstate-u.edu.ph','https://lh3.googleusercontent.com/a/ACg8ocKj_hdLHiH6f6AjbV6gesW0UtsBMjuzJr2DoacA-B0CT46ux8k=s96-c','College of Informatics and Computing Sciences',NULL,'106152513072693985660','2026-07-30 00:41:19',NULL,'aiXOqS1QVMwl60s1fiaZZBbzjlsM5GwXrLw5e6C6jPuRbbH88zctVyNP8vNG','2026-07-20 00:16:59','2026-07-30 00:41:19');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'athena'
--

--
-- Dumping routines for database 'athena'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-29 22:51:08
