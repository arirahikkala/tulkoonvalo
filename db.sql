-- MySQL dump 10.13  Distrib 5.1.44, for apple-darwin8.11.1 (i386)
--
-- Host: localhost    Database: webdali
-- ------------------------------------------------------
-- Server version	5.1.44

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
-- Table structure for table `groups`
--

DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `groups` (
  `child_id` char(32) DEFAULT NULL,
  `parent_id` char(32) DEFAULT NULL,
  KEY `child_id` (`child_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `groups_ibfk_1` FOREIGN KEY (`child_id`) REFERENCES `lights` (`permanent_id`),
  CONSTRAINT `groups_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `lights` (`permanent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `groups`
--

LOCK TABLES `groups` WRITE;
/*!40000 ALTER TABLE `groups` DISABLE KEYS */;
INSERT INTO `groups` VALUES ('1','3'),('2','3'),('5','4'),('3','6'),('4','6');
/*!40000 ALTER TABLE `groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `light_activations`
--

DROP TABLE IF EXISTS `light_activations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `light_activations` (
  `current_level` int(11) NOT NULL,
  `activated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ends_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `id` char(32) DEFAULT NULL,
  UNIQUE KEY `id_2` (`id`),
  KEY `id` (`id`),
  CONSTRAINT `light_activations_ibfk_1` FOREIGN KEY (`id`) REFERENCES `lights` (`permanent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `light_activations`
--

LOCK TABLES `light_activations` WRITE;
/*!40000 ALTER TABLE `light_activations` DISABLE KEYS */;
INSERT INTO `light_activations` VALUES (75,'2013-03-14 11:51:02','2013-03-14 13:47:11','7'),(49,'2013-03-15 14:39:45','2013-03-15 17:14:03','3'),(49,'2013-03-15 14:39:43','2013-03-15 16:44:02','4'),(49,'2013-03-15 14:39:43','2013-03-15 16:44:02','5'),(49,'2013-03-15 14:39:45','2013-03-15 17:14:03','1'),(49,'2013-03-15 14:39:45','2013-03-15 17:14:03','2'),(49,'2013-03-15 14:39:43','2013-03-15 16:44:02','6');
/*!40000 ALTER TABLE `light_activations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lights`
--

DROP TABLE IF EXISTS `lights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lights` (
  `name` text NOT NULL,
  `permanent_id` char(32) NOT NULL,
  `isGroup` tinyint(1) NOT NULL,
  PRIMARY KEY (`permanent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lights`
--

LOCK TABLES `lights` WRITE;
/*!40000 ALTER TABLE `lights` DISABLE KEYS */;
INSERT INTO `lights` VALUES ('Aula Etu','1',0),('Aula Taka','2',0),('Aula','3',1),('Eteinen','4',1),('Ulkovalo','5',0),('Yritys','6',1),('Kokoushuone','7',1);
/*!40000 ALTER TABLE `lights` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `program_times`
--

DROP TABLE IF EXISTS `program_times`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `program_times` (
  `program_id` int(32) NOT NULL,
  `date_start` date DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `weekdays` char(7) NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  KEY `program_id` (`program_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `program_times`
--

LOCK TABLES `program_times` WRITE;
/*!40000 ALTER TABLE `program_times` DISABLE KEYS */;
INSERT INTO `program_times` VALUES (1,'0000-01-01','0000-06-06','1001111','06:00:00','18:00:00'),(2,NULL,NULL,'0000001','09:00:00','10:00:00'),(1,NULL,NULL,'1111111','08:00:00','18:00:00');
/*!40000 ALTER TABLE `program_times` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `programs`
--

DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `programs` (
  `id` int(32) NOT NULL AUTO_INCREMENT,
  `target_id` char(32) NOT NULL,
  `name` char(32) NOT NULL,
  `light_detector` tinyint(1) NOT NULL,
  `motion_detector` tinyint(1) NOT NULL,
  `light_level` int(3) NOT NULL,
  `motion_level` int(3) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programs`
--

LOCK TABLES `programs` WRITE;
/*!40000 ALTER TABLE `programs` DISABLE KEYS */;
INSERT INTO `programs` VALUES (1,'6','Arki',1,1,50,100),(2,'6','Wappu',1,0,25,0);
/*!40000 ALTER TABLE `programs` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2013-03-18 10:16:49
