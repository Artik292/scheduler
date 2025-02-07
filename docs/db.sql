-- MySQL dump 10.13  Distrib 5.6.51, for Linux (x86_64)
--
-- Host: localhost    Database: vecaku-diena
-- ------------------------------------------------------
-- Server version	5.6.51-google-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `vecaku-diena`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `vecaku-diena` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;

USE `vecaku-diena`;

--
-- Table structure for table `counter`
--

DROP TABLE IF EXISTS `counter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `counter` (
  `id` int(11) unsigned NOT NULL,
  `counter` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `counter`
--

LOCK TABLES `counter` WRITE;
/*!40000 ALTER TABLE `counter` DISABLE KEYS */;
INSERT INTO `counter` VALUES (1,78);
/*!40000 ALTER TABLE `counter` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subject`
--

DROP TABLE IF EXISTS `subject`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subject` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=168 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subject`
--

LOCK TABLES `subject` WRITE;
/*!40000 ALTER TABLE `subject` DISABLE KEYS */;
INSERT INTO `subject` VALUES (2,'Lolo'),(162,'Valodas'),(163,'Humanitārās zinātnes'),(164,'Dabaszinatnes'),(165,'Fizikas,matemātikas un datorzinības zinatnes'),(166,'Sports'),(167,'Sākumskola');
/*!40000 ALTER TABLE `subject` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher`
--

DROP TABLE IF EXISTS `teacher`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `surname` varchar(255) DEFAULT NULL,
  `class` varchar(150) DEFAULT NULL,
  `cabinet` varchar(50) DEFAULT NULL,
  `subject_id` int(11) unsigned NOT NULL,
  `available` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `fk_teacher_subject1_idx` (`subject_id`),
  CONSTRAINT `fk_teacher_subject1` FOREIGN KEY (`subject_id`) REFERENCES `subject` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=248 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher`
--

LOCK TABLES `teacher` WRITE;
/*!40000 ALTER TABLE `teacher` DISABLE KEYS */;
INSERT INTO `teacher` VALUES (222,'Jeļena','Serdjukova',NULL,'110',167,1),(223,'Nataļja','Mihailova',NULL,'109',167,1),(224,'Nataļja','Minajeva',NULL,'108',167,1),(225,'Valentīna','Sapogova',NULL,'103',167,1),(226,'Ksenija','Kirilova',NULL,'213',167,1),(227,'Tamāra','Meškova',NULL,'203',167,1),(228,'Tatjana','Aļeņina',NULL,'304',167,1),(229,'Irina','Panova',NULL,'310',167,1),(230,'Jeļena','Isjko',NULL,'206',167,1),(231,'Ļubova','Korņilova',NULL,'311',167,1),(232,'Oxana','Panashenko',NULL,'207',167,1),(233,'Jeļena','Ivaņenko',NULL,'309',167,1),(234,'Valentīna','Kruļikovska',NULL,'208',167,1),(235,'Inna','Jegorova',NULL,'46',167,1),(236,'Gaļina','Jerjomina',NULL,'29',167,1),(237,'Jeļena','Martjanova',NULL,'27',167,1),(238,'Larisa','Mickeviča',NULL,'212',167,1),(239,'Natālija','Jakovele',NULL,'206. a',167,1),(240,'Inita','Poļaka',NULL,'106. a',167,1),(241,'Lorina','Skurjate',NULL,'5. a',167,1),(242,'Valentīna','Lomakina',NULL,'79',167,1),(243,'Alla','Cavicka',NULL,'213. a',167,1),(244,'Jevgēnija','Urbane-Orbane',NULL,'304',167,1),(245,'Evija','Kijonoka',NULL,'Skolotāju ist.',167,1),(246,'Jekaterina','Koršunova',NULL,'103',167,1),(247,'Nataļja','Piļipenko',NULL,'213',167,1);
/*!40000 ALTER TABLE `teacher` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `text`
--

DROP TABLE IF EXISTS `text`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `text` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(255) DEFAULT NULL,
  `text` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `text`
--

LOCK TABLES `text` WRITE;
/*!40000 ALTER TABLE `text` DISABLE KEYS */;
INSERT INTO `text` VALUES (1,'main_page_middle_text','Cienījamie vecāki! Lūdzam ievērot reglamentu: no 17.00 līdz 19.00.'),(2,'main_page_sub_text','2024.gada 7.novembrī plkst. 17.00 - 19.00.'),(3,'parents_page_instruction_text','Izvēlaties priekšmētu, skolotāju un\r\nvelāmo laiku. Ja Jūs uzskatāt, ka 10 min būs par maz, registrējāties uz diviem laikiem pēc kārtas.');
/*!40000 ALTER TABLE `text` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vecaki`
--

DROP TABLE IF EXISTS `vecaki`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vecaki` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_name` varchar(255) DEFAULT NULL,
  `grade` varchar(255) DEFAULT NULL,
  `parent_name` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(255) DEFAULT NULL,
  `time` varchar(20) DEFAULT NULL,
  `is_avaliable` tinyint(1) DEFAULT NULL,
  `teacher_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_vecaki_teacher1_idx` (`teacher_id`),
  CONSTRAINT `fk_vecaki_teacher1` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vecaki`
--

LOCK TABLES `vecaki` WRITE;
/*!40000 ALTER TABLE `vecaki` DISABLE KEYS */;
INSERT INTO `vecaki` VALUES (1,'raw','rfawf','awfaw','afa','17:00',NULL,243);
/*!40000 ALTER TABLE `vecaki` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-02-04 10:52:41
