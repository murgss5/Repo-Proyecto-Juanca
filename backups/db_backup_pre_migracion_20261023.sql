-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: control_asistencia
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
-- Table structure for table `aprendiz`
--

DROP TABLE IF EXISTS `aprendiz`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `aprendiz` (
  `id_aprendiz` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_rfid` varchar(50) NOT NULL,
  `fk_ficha` int(11) NOT NULL,
  `fk_usuario` int(11) NOT NULL,
  PRIMARY KEY (`id_aprendiz`),
  UNIQUE KEY `codigo_rfid` (`codigo_rfid`),
  UNIQUE KEY `fk_usuario` (`fk_usuario`),
  KEY `fk_aprendiz_ficha` (`fk_ficha`),
  CONSTRAINT `fk_aprendiz_ficha` FOREIGN KEY (`fk_ficha`) REFERENCES `ficha` (`id_ficha`) ON UPDATE CASCADE,
  CONSTRAINT `fk_aprendiz_usuario` FOREIGN KEY (`fk_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=182 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aprendiz`
--

LOCK TABLES `aprendiz` WRITE;
/*!40000 ALTER TABLE `aprendiz` DISABLE KEYS */;
INSERT INTO `aprendiz` VALUES (134,'0002304538',24,149),(135,'0002333775',24,150),(136,'0002271972',24,151),(137,'0009433667',24,152),(138,'0002287847',24,153),(139,'0002252118',24,154),(140,'0002266653',24,155),(141,'678678678',24,156),(142,'1225446',24,157),(143,'6565',24,158),(144,'56565656',24,159),(145,'54353',24,160),(146,'6678',24,161),(147,'5677',24,162),(148,'6867868',24,163),(149,'334344',24,164),(150,'67868',24,165),(151,'879789',24,166),(152,'68678678',24,167),(153,'68687',24,168),(155,'5665',24,170),(157,'6468',24,172),(158,'566533322',24,173),(159,'786868',24,174),(160,'789789',24,175),(161,'678867',25,176),(162,'67867',25,177);
/*!40000 ALTER TABLE `aprendiz` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asistencia`
--

DROP TABLE IF EXISTS `asistencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asistencia` (
  `id_asistencia` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_asistencia` date NOT NULL,
  `entrada` datetime DEFAULT NULL,
  `salida` datetime DEFAULT NULL,
  `estado_entrada` varchar(45) DEFAULT NULL,
  `estado_salida` varchar(45) DEFAULT NULL,
  `minutos_retardo` int(11) NOT NULL DEFAULT 0,
  `minutos_salida_anticipada` int(11) NOT NULL DEFAULT 0,
  `fk_aprendiz` int(11) NOT NULL,
  `fk_sesion_jornada` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_asistencia`),
  UNIQUE KEY `uq_asistencia_aprendiz_fecha` (`fk_aprendiz`,`fecha_asistencia`),
  KEY `idx_asistencia_sesion` (`fk_sesion_jornada`)
) ENGINE=InnoDB AUTO_INCREMENT=274 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asistencia`
--

LOCK TABLES `asistencia` WRITE;
/*!40000 ALTER TABLE `asistencia` DISABLE KEYS */;
INSERT INTO `asistencia` VALUES (161,'2026-10-15','2026-10-15 00:06:24',NULL,'PUNTUAL',NULL,0,0,134,2),(162,'2026-10-15','2026-10-15 00:06:28',NULL,'PUNTUAL',NULL,0,0,135,2),(163,'2026-10-15','2026-10-15 00:06:29',NULL,'PUNTUAL',NULL,0,0,136,2),(164,'2026-10-15','2026-10-15 00:06:41',NULL,'PUNTUAL',NULL,0,0,137,2),(165,'2026-10-15','2026-10-15 00:06:43',NULL,'PUNTUAL',NULL,0,0,138,2),(166,'2026-10-15','2026-10-15 00:06:44',NULL,'PUNTUAL',NULL,0,0,139,2),(167,'2026-10-15','2026-10-15 00:06:45',NULL,'PUNTUAL',NULL,0,0,140,2),(168,'2026-10-15','2026-10-15 00:06:47',NULL,'PUNTUAL',NULL,0,0,141,2),(169,'2026-10-15','2026-10-15 00:06:48',NULL,'PUNTUAL',NULL,0,0,142,2),(170,'2026-10-15','2026-10-15 00:22:05',NULL,'RETARDO',NULL,13,0,143,2),(171,'2026-10-15',NULL,NULL,'INASISTENCIA',NULL,0,0,144,2),(172,'2026-10-15',NULL,NULL,'INASISTENCIA',NULL,0,0,145,2),(173,'2026-10-15',NULL,NULL,'INASISTENCIA',NULL,0,0,146,2),(174,'2026-10-15',NULL,NULL,'INASISTENCIA',NULL,0,0,147,2),(175,'2026-10-15',NULL,NULL,'INASISTENCIA',NULL,0,0,148,2),(176,'2026-10-15',NULL,NULL,'INASISTENCIA',NULL,0,0,149,2),(177,'2026-10-15',NULL,NULL,'INASISTENCIA',NULL,0,0,150,2),(178,'2026-10-15',NULL,NULL,'INASISTENCIA',NULL,0,0,151,2),(179,'2026-10-15',NULL,NULL,'INASISTENCIA',NULL,0,0,152,2),(180,'2026-10-15',NULL,NULL,'INASISTENCIA',NULL,0,0,153,2),(181,'2026-10-15',NULL,NULL,'INASISTENCIA',NULL,0,0,155,2),(182,'2026-10-15',NULL,NULL,'INASISTENCIA',NULL,0,0,157,2),(183,'2026-10-15',NULL,NULL,'INASISTENCIA',NULL,0,0,158,2),(184,'2026-10-15',NULL,NULL,'INASISTENCIA',NULL,0,0,159,2),(185,'2026-10-15',NULL,NULL,'INASISTENCIA',NULL,0,0,160,2),(186,'2026-10-16','2026-10-16 15:14:10',NULL,'RETARDO',NULL,5,0,134,3),(187,'2026-10-16','2026-10-16 15:14:12',NULL,'RETARDO',NULL,5,0,135,3),(188,'2026-10-16','2026-10-16 15:14:15',NULL,'RETARDO',NULL,5,0,136,3),(189,'2026-10-16','2026-10-16 15:14:38',NULL,'RETARDO',NULL,5,0,137,3),(190,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,138,3),(191,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,139,3),(192,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,140,3),(193,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,141,3),(194,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,142,3),(195,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,143,3),(196,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,144,3),(197,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,145,3),(198,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,146,3),(199,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,147,3),(200,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,148,3),(201,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,149,3),(202,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,150,3),(203,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,151,3),(204,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,152,3),(205,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,153,3),(206,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,155,3),(207,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,157,3),(208,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,158,3),(209,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,159,3),(210,'2026-10-16',NULL,NULL,'INASISTENCIA',NULL,0,0,160,3),(211,'2026-10-19','2026-10-19 08:41:07',NULL,'PUNTUAL',NULL,0,0,134,4),(212,'2026-10-19','2026-10-19 08:41:09',NULL,'PUNTUAL',NULL,0,0,135,4),(213,'2026-10-19','2026-10-19 08:41:12',NULL,'PUNTUAL',NULL,0,0,136,4),(214,'2026-10-19','2026-10-19 08:41:15',NULL,'PUNTUAL',NULL,0,0,137,4),(215,'2026-10-19','2026-10-19 09:20:50',NULL,'RETARDO',NULL,30,0,138,4),(216,'2026-10-19','2026-10-19 09:20:53',NULL,'RETARDO',NULL,30,0,139,4),(217,'2026-10-19','2026-10-19 09:20:54',NULL,'RETARDO',NULL,30,0,140,4),(218,'2026-10-19','2026-10-19 11:21:12',NULL,'RETARDO',NULL,150,0,141,4),(219,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,142,4),(220,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,143,4),(221,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,144,4),(222,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,145,4),(223,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,146,4),(224,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,147,4),(225,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,148,4),(226,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,149,4),(227,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,150,4),(228,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,151,4),(229,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,152,4),(230,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,153,4),(231,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,155,4),(232,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,157,4),(233,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,158,4),(234,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,159,4),(235,'2026-10-19',NULL,NULL,'INASISTENCIA',NULL,0,0,160,4);
/*!40000 ALTER TABLE `asistencia` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `excusa`
--

DROP TABLE IF EXISTS `excusa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `excusa` (
  `id_excusa` int(11) NOT NULL AUTO_INCREMENT,
  `archivo` varchar(450) NOT NULL,
  `observacion` varchar(450) DEFAULT NULL,
  `estado` varchar(45) NOT NULL,
  `fecha_subida` date NOT NULL,
  `fecha_revision` date DEFAULT NULL,
  `fk_asistencia` int(11) NOT NULL,
  `fk_usuario_instructor` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_excusa`),
  UNIQUE KEY `uq_excusa_asistencia` (`fk_asistencia`),
  KEY `fk_excusa_instructor` (`fk_usuario_instructor`),
  CONSTRAINT `fk_excusa_asistencia` FOREIGN KEY (`fk_asistencia`) REFERENCES `asistencia` (`id_asistencia`) ON UPDATE CASCADE,
  CONSTRAINT `fk_excusa_instructor` FOREIGN KEY (`fk_usuario_instructor`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `excusa`
--

LOCK TABLES `excusa` WRITE;
/*!40000 ALTER TABLE `excusa` DISABLE KEYS */;
INSERT INTO `excusa` VALUES (2,'661e535399261d4c95501293471d62ae.pdf','falte porque tuve una cita medica','APROBADA','2026-10-16','2026-10-16',191,53),(3,'9a021b6f3e58e70c5c6b21d0949fd242.pdf','cfgbcfh','APROBADA','2026-10-17','2026-10-19',173,53),(4,'06e7683894fe13e82b0ca224ba1d570e.pdf','','APROBADA','2026-10-17','2026-10-19',198,53),(5,'dfb5649b115134b4eb6f2626e594fe00.pdf','','PENDIENTE','2026-10-19',NULL,192,NULL);
/*!40000 ALTER TABLE `excusa` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ficha`
--

DROP TABLE IF EXISTS `ficha`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ficha` (
  `id_ficha` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_programa` varchar(100) NOT NULL,
  `jornada` varchar(45) NOT NULL,
  `ambiente_principal` varchar(100) DEFAULT NULL,
  `fk_usuario` int(11) NOT NULL,
  PRIMARY KEY (`id_ficha`),
  KEY `fk_ficha_usuario` (`fk_usuario`),
  CONSTRAINT `fk_ficha_usuario` FOREIGN KEY (`fk_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3389187 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ficha`
--

LOCK TABLES `ficha` WRITE;
/*!40000 ALTER TABLE `ficha` DISABLE KEYS */;
INSERT INTO `ficha` VALUES (24,'Analisis y desarrollo de Software','Diurna','taller 9',53),(25,'entrenamiento deportivo','Tarde','taller 15',53),(3064749,'TECNÓLOGO Análisis y desarrollo de software','Mañana','Por asignar',53),(3216504,'TECNÓLOGO ENTRENAMIENTO DEPORTIVO','Mañana','Por asignar',53),(3389186,'TECNÓLOGO Análisis y desarrollo de sofware','Tarde','Por asignar',53);
/*!40000 ALTER TABLE `ficha` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `horario`
--

DROP TABLE IF EXISTS `horario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `horario` (
  `id_horario` int(11) NOT NULL AUTO_INCREMENT,
  `dia_semana` varchar(15) NOT NULL,
  `entrada` time NOT NULL,
  `salida` time NOT NULL,
  `competencia` varchar(100) DEFAULT NULL,
  `tipo_bloque` enum('completo','partido') NOT NULL DEFAULT 'completo',
  `ambiente` varchar(100) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `observaciones` varchar(255) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `fk_ficha` int(11) NOT NULL,
  `instructor_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_horario`),
  KEY `fk_horario_ficha` (`fk_ficha`),
  KEY `idx_horario_instructor` (`instructor_id`),
  KEY `idx_horario_dia` (`dia_semana`),
  CONSTRAINT `fk_horario_ficha` FOREIGN KEY (`fk_ficha`) REFERENCES `ficha` (`id_ficha`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_horario_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=642 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horario`
--

LOCK TABLES `horario` WRITE;
/*!40000 ALTER TABLE `horario` DISABLE KEYS */;
INSERT INTO `horario` VALUES (116,'Lunes','06:00:00','10:00:00',NULL,'completo',NULL,'Activo',NULL,NULL,NULL,24,NULL),(118,'Viernes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-03','2026-07-03',3389186,1000008),(119,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-01','2026-07-01',3389186,1000006),(120,'Jueves','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-02','2026-07-02',3389186,1000007),(121,'Viernes','15:00:00','18:00:00','ÉTICA','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-03','2026-07-03',3389186,1000010),(122,'Sábado','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-04','2026-07-04',3389186,1000009),(123,'Martes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-07','2026-07-07',3389186,1000011),(124,'Miércoles','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-08','2026-07-08',3389186,182),(125,'Viernes','12:00:00','15:00:00','SST','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-10','2026-07-10',3389186,1000012),(126,'Lunes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-06','2026-07-06',3389186,1000006),(127,'Martes','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-07','2026-07-07',3389186,1000013),(128,'Miércoles','15:00:00','18:00:00','Comunicación','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-08','2026-07-08',3389186,1000014),(129,'Jueves','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-09','2026-07-09',3389186,1000007),(130,'Viernes','15:00:00','18:00:00','AMBIENTAL','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-10','2026-07-10',3389186,1000015),(131,'Sábado','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-11','2026-07-11',3389186,1000007),(132,'Lunes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-13','2026-07-13',3389186,1000008),(133,'Martes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-14','2026-07-14',3389186,1000011),(134,'Miércoles','12:00:00','15:00:00','AMBIENTAL','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-15','2026-07-15',3389186,1000015),(135,'Viernes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-17','2026-07-17',3389186,1000011),(136,'Lunes','15:00:00','18:00:00','ÉTICA','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-13','2026-07-13',3389186,1000010),(137,'Martes','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-14','2026-07-14',3389186,1000013),(138,'Miércoles','15:00:00','18:00:00','DERECHOS FUNDAMENTALES','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-15','2026-07-15',3389186,1000016),(139,'Jueves','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-16','2026-07-16',3389186,1000009),(140,'Viernes','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-17','2026-07-17',3389186,1000013),(141,'Sábado','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-18','2026-07-18',3389186,1000007),(142,'Miércoles','12:00:00','15:00:00','Comunicación','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-22','2026-07-22',3389186,1000014),(143,'Jueves','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-23','2026-07-23',3389186,182),(144,'Sábado','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-25','2026-07-25',3389186,1000011),(145,'Martes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-21','2026-07-21',3389186,1000009),(146,'Miércoles','15:00:00','18:00:00','DERECHOS FUNDAMENTALES','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-22','2026-07-22',3389186,1000016),(147,'Jueves','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-23','2026-07-23',3389186,1000017),(148,'Viernes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-24','2026-07-24',3389186,1000007),(149,'Sábado','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-25','2026-07-25',3389186,1000013),(150,'Martes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-28','2026-07-28',3389186,1000011),(151,'Jueves','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-30','2026-07-30',3389186,1000011),(152,'Viernes','12:00:00','15:00:00','Comunicación','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-31','2026-07-31',3389186,1000014),(153,'Lunes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-27','2026-07-27',3389186,1000006),(154,'Martes','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-28','2026-07-28',3389186,1000013),(155,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-29','2026-07-29',3389186,1000007),(156,'Jueves','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-30','2026-07-30',3389186,1000013),(157,'Viernes','15:00:00','18:00:00','SST','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-31','2026-07-31',3389186,1000012),(158,'Sábado','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-01','2026-08-01',3389186,1000009),(159,'Lunes','12:00:00','15:00:00','ÉTICA','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-03','2026-08-03',3389186,1000010),(160,'Martes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-04','2026-08-04',3389186,1000011),(161,'Sábado','12:00:00','15:00:00','DERECHOS FUNDAMENTALES','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-08','2026-08-08',3389186,1000016),(162,'Lunes','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-03','2026-08-03',3389186,1000008),(163,'Martes','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-04','2026-08-04',3389186,1000013),(164,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-05','2026-08-05',3389186,1000007),(165,'Jueves','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-06','2026-08-06',3389186,1000009),(166,'Sábado','15:00:00','18:00:00','SST','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-08','2026-08-08',3389186,1000012),(167,'Miércoles','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-12','2026-08-12',3389186,182),(168,'Viernes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-14','2026-08-14',3389186,1000017),(169,'Lunes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-10','2026-08-10',3389186,1000006),(170,'Martes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-11','2026-08-11',3389186,1000007),(171,'Miércoles','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-12','2026-08-12',3389186,1000011),(172,'Jueves','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-13','2026-08-13',3389186,1000006),(173,'Viernes','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-14','2026-08-14',3389186,1000011),(174,'Sábado','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-15','2026-08-15',3389186,1000009),(175,'Jueves','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-20','2026-08-20',3389186,182),(176,'Viernes','12:00:00','15:00:00','AMBIENTAL','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-21','2026-08-21',3389186,1000015),(177,'Sábado','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-22','2026-08-22',3389186,1000011),(178,'Martes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-18','2026-08-18',3389186,1000007),(179,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-19','2026-08-19',3389186,1000006),(180,'Jueves','15:00:00','18:00:00','DERECHOS FUNDAMENTALES','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-20','2026-08-20',3389186,1000016),(181,'Viernes','15:00:00','18:00:00','Comunicación','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-21','2026-08-21',3389186,1000014),(182,'Sábado','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-22','2026-08-22',3389186,1000013),(183,'Lunes','12:00:00','15:00:00','ÉTICA','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-24','2026-08-24',3389186,1000010),(184,'Miércoles','12:00:00','15:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-26','2026-08-26',3389186,1000013),(185,'Viernes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-28','2026-08-28',3389186,1000017),(186,'Lunes','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-24','2026-08-24',3389186,1000008),(187,'Martes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-25','2026-08-25',3389186,1000009),(188,'Miércoles','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-26','2026-08-26',3389186,1000011),(189,'Jueves','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-27','2026-08-27',3389186,1000007),(190,'Viernes','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-28','2026-08-28',3389186,1000011),(191,'Sábado','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-29','2026-08-29',3389186,1000006),(192,'Lunes','12:00:00','15:00:00','Comunicación','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-31','2026-08-31',3389186,1000014),(193,'Martes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-01','2026-09-01',3389186,1000011),(194,'Viernes','12:00:00','15:00:00','DERECHOS FUNDAMENTALES','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-04','2026-09-04',3389186,1000016),(195,'Sábado','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-05','2026-09-05',3389186,1000011),(196,'Lunes','15:00:00','18:00:00','SST','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-31','2026-08-31',3389186,1000012),(197,'Martes','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-01','2026-09-01',3389186,1000013),(198,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-02','2026-09-02',3389186,1000007),(199,'Jueves','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-03','2026-09-03',3389186,1000009),(200,'Viernes','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-04','2026-09-04',3389186,1000006),(201,'Sábado','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-05','2026-09-05',3389186,1000013),(202,'Lunes','12:00:00','15:00:00','ÉTICA','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-07','2026-09-07',3389186,1000010),(203,'Martes','12:00:00','15:00:00','AMBIENTAL','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-08','2026-09-08',3389186,1000015),(204,'Jueves','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-10','2026-09-10',3389186,182),(205,'Viernes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-11','2026-09-11',3389186,1000017),(206,'Lunes','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-07','2026-09-07',3389186,1000008),(207,'Martes','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-08','2026-09-08',3389186,1000013),(208,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-09','2026-09-09',3389186,1000007),(209,'Jueves','15:00:00','18:00:00','Comunicación','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-10','2026-09-10',3389186,1000014),(210,'Viernes','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-11','2026-09-11',3389186,1000011),(211,'Sábado','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-12','2026-09-12',3389186,1000006),(212,'Martes','12:00:00','15:00:00','SST','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-15','2026-09-15',3389186,1000012),(213,'Jueves','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-17','2026-09-17',3389186,1000011),(214,'Sábado','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-19','2026-09-19',3389186,1000011),(215,'Lunes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-14','2026-09-14',3389186,1000009),(216,'Martes','15:00:00','18:00:00','DERECHOS FUNDAMENTALES','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-15','2026-09-15',3389186,1000016),(217,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-16','2026-09-16',3389186,1000006),(218,'Jueves','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-17','2026-09-17',3389186,1000013),(219,'Viernes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-18','2026-09-18',3389186,1000007),(220,'Sábado','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-19','2026-09-19',3389186,1000013),(221,'Lunes','12:00:00','15:00:00','ÉTICA','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-21','2026-09-21',3389186,1000010),(222,'Jueves','12:00:00','15:00:00','Comunicación','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-24','2026-09-24',3389186,1000014),(223,'Viernes','12:00:00','15:00:00','SST','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-25','2026-09-25',3389186,1000012),(224,'Lunes','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-21','2026-09-21',3389186,182),(225,'Martes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-22','2026-09-22',3389186,1000007),(226,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-23','2026-09-23',3389186,1000009),(227,'Jueves','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-24','2026-09-24',3389186,1000011),(228,'Viernes','15:00:00','18:00:00','AMBIENTAL','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-25','2026-09-25',3389186,1000015),(229,'Sábado','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-26','2026-09-26',3389186,1000006),(230,'Martes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-29','2026-09-29',3389186,1000011),(231,'Viernes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-02','2026-10-02',3389186,1000017),(232,'Sábado','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-03','2026-10-03',3389186,1000011),(233,'Lunes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-28','2026-09-28',3389186,1000007),(234,'Martes','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-29','2026-09-29',3389186,1000013),(235,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-30','2026-09-30',3389186,1000006),(236,'Jueves','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-01','2026-10-01',3389186,1000009),(237,'Viernes','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-02','2026-10-02',3389186,1000007),(238,'Sábado','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-03','2026-10-03',3389186,1000013),(239,'Lunes','12:00:00','15:00:00','ÉTICA','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-05','2026-10-05',3389186,1000010),(240,'Martes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-06','2026-10-06',3389186,1000011),(241,'Viernes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-09','2026-10-09',3389186,1000009),(242,'Lunes','15:00:00','18:00:00','DERECHOS FUNDAMENTALES','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-05','2026-10-05',3389186,1000016),(243,'Martes','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-06','2026-10-06',3389186,1000013),(244,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-07','2026-10-07',3389186,1000007),(245,'Jueves','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-08','2026-10-08',3389186,1000006),(246,'Viernes','15:00:00','18:00:00','AMBIENTAL','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-09','2026-10-09',3389186,1000015),(247,'Sábado','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-10','2026-10-10',3389186,1000009),(248,'Jueves','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-15','2026-10-15',3389186,182),(249,'Viernes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-16','2026-10-16',3389186,1000017),(250,'Sábado','12:00:00','15:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-17','2026-10-17',3389186,1000013),(251,'Martes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-13','2026-10-13',3389186,1000007),(252,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-14','2026-10-14',3389186,1000007),(253,'Jueves','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-15','2026-10-15',3389186,1000011),(254,'Viernes','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-16','2026-10-16',3389186,1000013),(255,'Sábado','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-17','2026-10-17',3389186,1000011),(256,'Martes','12:00:00','15:00:00','DERECHOS FUNDAMENTALES','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-20','2026-10-20',3389186,1000016),(257,'Jueves','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-22','2026-10-22',3389186,1000011),(258,'Viernes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-23','2026-10-23',3389186,1000009),(259,'Lunes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-19','2026-10-19',3389186,1000007),(260,'Martes','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-20','2026-10-20',3389186,182),(261,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-21','2026-10-21',3389186,1000009),(262,'Jueves','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-22','2026-10-22',3389186,1000013),(263,'Viernes','15:00:00','18:00:00','AMBIENTAL','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-23','2026-10-23',3389186,1000015),(264,'Sábado','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-24','2026-10-24',3389186,1000006),(265,'Martes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-27','2026-10-27',3389186,1000011),(266,'Viernes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-30','2026-10-30',3389186,1000017),(267,'Lunes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-26','2026-10-26',3389186,1000007),(268,'Martes','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-27','2026-10-27',3389186,1000013),(269,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-28','2026-10-28',3389186,1000009),(270,'Jueves','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-29','2026-10-29',3389186,1000006),(271,'Viernes','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-30','2026-10-30',3389186,1000011),(272,'Sábado','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-31','2026-10-31',3389186,1000007),(273,'Miércoles','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-04','2026-11-04',3389186,1000011),(274,'Viernes','12:00:00','15:00:00','SST','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-06','2026-11-06',3389186,1000012),(275,'Martes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-03','2026-11-03',3389186,1000007),(276,'Miércoles','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-04','2026-11-04',3389186,1000013),(277,'Jueves','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-05','2026-11-05',3389186,1000006),(278,'Viernes','15:00:00','18:00:00','DERECHOS FUNDAMENTALES','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-06','2026-11-06',3389186,1000016),(279,'Sábado','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-07','2026-11-07',3389186,1000009),(280,'Martes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-10','2026-11-10',3389186,1000011),(281,'Sábado','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-14','2026-11-14',3389186,1000011),(282,'Lunes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-09','2026-11-09',3389186,1000006),(283,'Martes','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-10','2026-11-10',3389186,1000007),(284,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-11','2026-11-11',3389186,1000009),(285,'Jueves','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-12','2026-11-12',3389186,1000009),(286,'Viernes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-13','2026-11-13',3389186,1000007),(287,'Sábado','15:00:00','18:00:00','MATEMÁTICAS','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-14','2026-11-14',3389186,1000013),(288,'Martes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-17','2026-11-17',3389186,1000011),(289,'Viernes','12:00:00','15:00:00','SST','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-20','2026-11-20',3389186,1000012),(290,'Martes','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-17','2026-11-17',3389186,1000007),(291,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-18','2026-11-18',3389186,1000007),(292,'Jueves','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-19','2026-11-19',3389186,1000009),(293,'Viernes','15:00:00','18:00:00','DERECHOS FUNDAMENTALES','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-20','2026-11-20',3389186,1000016),(294,'Sábado','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-21','2026-11-21',3389186,1000006),(295,'Martes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-24','2026-11-24',3389186,1000011),(296,'Sábado','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-28','2026-11-28',3389186,1000011),(297,'Lunes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-23','2026-11-23',3389186,1000009),(298,'Martes','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-24','2026-11-24',3389186,1000007),(299,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-25','2026-11-25',3389186,1000006),(300,'Jueves','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-26','2026-11-26',3389186,1000009),(301,'Viernes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-27','2026-11-27',3389186,1000007),(302,'Sábado','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-28','2026-11-28',3389186,1000006),(303,'Martes','12:00:00','15:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-01','2026-12-01',3389186,1000011),(304,'Viernes','12:00:00','15:00:00','SST','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-04','2026-12-04',3389186,1000012),(305,'Lunes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-30','2026-11-30',3389186,1000006),(306,'Martes','15:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-01','2026-12-01',3389186,1000007),(307,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-02','2026-12-02',3389186,1000009),(308,'Jueves','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-03','2026-12-03',3389186,1000007),(309,'Viernes','15:00:00','18:00:00','DERECHOS FUNDAMENTALES','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-04','2026-12-04',3389186,1000016),(310,'Sábado','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-05','2026-12-05',3389186,1000009),(311,'Lunes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-07','2026-12-07',3389186,1000009),(312,'Miércoles','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-09','2026-12-09',3389186,1000007),(313,'Jueves','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-10','2026-12-10',3389186,1000006),(314,'Viernes','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-11','2026-12-11',3389186,1000009),(315,'Sábado','12:00:00','18:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-12','2026-12-12',3389186,1000007),(316,'Viernes','06:00:00','09:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-03','2026-07-03',3216504,1000018),(317,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-01','2026-07-01',3216504,1000018),(318,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-02','2026-07-02',3216504,182),(319,'Viernes','09:00:00','12:00:00','SST','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-03','2026-07-03',3216504,1000012),(320,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-04','2026-07-04',3216504,1000018),(321,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-06','2026-07-06',3216504,1000018),(322,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-07','2026-07-07',3216504,182),(323,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-08','2026-07-08',3216504,1000018),(324,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-09','2026-07-09',3216504,182),(325,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-10','2026-07-10',3216504,1000018),(326,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-11','2026-07-11',3216504,1000018),(327,'Lunes','06:00:00','09:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-13','2026-07-13',3216504,182),(328,'Miércoles','06:00:00','09:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-15','2026-07-15',3216504,182),(329,'Lunes','09:00:00','12:00:00','SST','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-13','2026-07-13',3216504,1000012),(330,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-14','2026-07-14',3216504,1000018),(331,'Miércoles','09:00:00','12:00:00','DERECHOS FUNDAMENTALES','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-15','2026-07-15',3216504,1000016),(332,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-16','2026-07-16',3216504,1000018),(333,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-17','2026-07-17',3216504,1000018),(334,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-18','2026-07-18',3216504,1000018),(335,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-21','2026-07-21',3216504,1000018),(336,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-22','2026-07-22',3216504,182),(337,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-23','2026-07-23',3216504,1000018),(338,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-24','2026-07-24',3216504,182),(339,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-25','2026-07-25',3216504,1000018),(340,'Miércoles','06:00:00','09:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-29','2026-07-29',3216504,182),(341,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-27','2026-07-27',3216504,1000018),(342,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-28','2026-07-28',3216504,1000018),(343,'Miércoles','09:00:00','12:00:00','DERECHOS FUNDAMENTALES','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-29','2026-07-29',3216504,1000016),(344,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-30','2026-07-30',3216504,1000018),(345,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-07-31','2026-07-31',3216504,1000018),(346,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-01','2026-08-01',3216504,182),(347,'Sábado','06:00:00','09:00:00','DERECHOS FUNDAMENTALES','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-08','2026-08-08',3216504,1000016),(348,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-03','2026-08-03',3216504,1000018),(349,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-04','2026-08-04',3216504,182),(350,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-05','2026-08-05',3216504,1000018),(351,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-06','2026-08-06',3216504,182),(352,'Sábado','09:00:00','12:00:00','SST','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-08','2026-08-08',3216504,1000012),(353,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-10','2026-08-10',3216504,1000018),(354,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-11','2026-08-11',3216504,182),(355,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-12','2026-08-12',3216504,1000018),(356,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-13','2026-08-13',3216504,1000018),(357,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-14','2026-08-14',3216504,182),(358,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-15','2026-08-15',3216504,1000018),(359,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-18','2026-08-18',3216504,1000018),(360,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-19','2026-08-19',3216504,182),(361,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-20','2026-08-20',3216504,1000018),(362,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-21','2026-08-21',3216504,182),(363,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-22','2026-08-22',3216504,1000018),(364,'Miércoles','06:00:00','09:00:00','DERECHOS FUNDAMENTALES','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-26','2026-08-26',3216504,1000016),(365,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-24','2026-08-24',3216504,182),(366,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-25','2026-08-25',3216504,1000018),(367,'Miércoles','09:00:00','12:00:00','SST','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-26','2026-08-26',3216504,1000012),(368,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-27','2026-08-27',3216504,182),(369,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-28','2026-08-28',3216504,1000018),(370,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-29','2026-08-29',3216504,182),(371,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-08-31','2026-08-31',3216504,1000018),(372,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-01','2026-09-01',3216504,182),(373,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-02','2026-09-02',3216504,1000018),(374,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-03','2026-09-03',3216504,182),(375,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-04','2026-09-04',3216504,1000018),(376,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-05','2026-09-05',3216504,1000018),(377,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-07','2026-09-07',3216504,1000018),(378,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-08','2026-09-08',3216504,182),(379,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-09','2026-09-09',3216504,1000018),(380,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-10','2026-09-10',3216504,182),(381,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-11','2026-09-11',3216504,1000018),(382,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-12','2026-09-12',3216504,1000018),(383,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-14','2026-09-14',3216504,1000018),(384,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-15','2026-09-15',3216504,182),(385,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-16','2026-09-16',3216504,1000018),(386,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-17','2026-09-17',3216504,182),(387,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-18','2026-09-18',3216504,1000018),(388,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-19','2026-09-19',3216504,1000018),(389,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-21','2026-09-21',3216504,1000018),(390,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-22','2026-09-22',3216504,182),(391,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-23','2026-09-23',3216504,1000018),(392,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-24','2026-09-24',3216504,1000018),(393,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-25','2026-09-25',3216504,182),(394,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-26','2026-09-26',3216504,1000018),(395,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-28','2026-09-28',3216504,1000018),(396,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-29','2026-09-29',3216504,182),(397,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-09-30','2026-09-30',3216504,1000018),(398,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-01','2026-10-01',3216504,182),(399,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-02','2026-10-02',3216504,1000018),(400,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-03','2026-10-03',3216504,1000018),(401,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-05','2026-10-05',3216504,1000018),(402,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-06','2026-10-06',3216504,182),(403,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-07','2026-10-07',3216504,1000018),(404,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-08','2026-10-08',3216504,182),(405,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-09','2026-10-09',3216504,1000018),(406,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-10','2026-10-10',3216504,1000018),(407,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-13','2026-10-13',3216504,1000018),(408,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-14','2026-10-14',3216504,182),(409,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-15','2026-10-15',3216504,1000018),(410,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-16','2026-10-16',3216504,182),(411,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-17','2026-10-17',3216504,1000018),(412,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-19','2026-10-19',3216504,1000018),(413,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-20','2026-10-20',3216504,182),(414,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-21','2026-10-21',3216504,1000018),(415,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-22','2026-10-22',3216504,182),(416,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-23','2026-10-23',3216504,1000018),(417,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-24','2026-10-24',3216504,1000018),(418,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-26','2026-10-26',3216504,1000018),(419,'Martes','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-27','2026-10-27',3216504,182),(420,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','COMERCIO Y SERVICIOS 5','Activo','Importado matriz SENA','2026-10-28','2026-10-28',3216504,1000018),(421,'Miércoles','06:00:00','09:00:00','AMBIENTAL','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-02','2026-07-02',3064749,1000015),(422,'Sábado','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-05','2026-07-05',3064749,1000007),(423,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-01','2026-07-01',3064749,1000009),(424,'Miércoles','09:00:00','12:00:00','SST','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-02','2026-07-02',3064749,1000012),(425,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-03','2026-07-03',3064749,1000006),(426,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-04','2026-07-04',3064749,1000009),(427,'Sábado','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-05','2026-07-05',3064749,1000011),(428,'Lunes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-07','2026-07-07',3064749,1000017),(429,'Martes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-08','2026-07-08',3064749,1000019),(430,'Jueves','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-10','2026-07-10',3064749,1000007),(431,'Lunes','09:00:00','12:00:00','DERECHOS FUNDAMENTALES','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-07','2026-07-07',3064749,1000016),(432,'Martes','09:00:00','12:00:00','Comunicación','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-08','2026-07-08',3064749,1000014),(433,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-09','2026-07-09',3064749,1000009),(434,'Jueves','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-10','2026-07-10',3064749,1000011),(435,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-11','2026-07-11',3064749,1000007),(436,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-12','2026-07-12',3064749,1000006),(437,'Martes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-15','2026-07-15',3064749,182),(438,'Jueves','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-17','2026-07-17',3064749,1000007),(439,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-14','2026-07-14',3064749,1000006),(440,'Martes','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-15','2026-07-15',3064749,1000011),(441,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-16','2026-07-16',3064749,1000009),(442,'Jueves','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-17','2026-07-17',3064749,1000011),(443,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-18','2026-07-18',3064749,1000007),(444,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-19','2026-07-19',3064749,1000009),(445,'Martes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-22','2026-07-22',3064749,1000007),(446,'Jueves','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-24','2026-07-24',3064749,1000007),(447,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-21','2026-07-21',3064749,1000009),(448,'Martes','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-22','2026-07-22',3064749,1000011),(449,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-23','2026-07-23',3064749,1000006),(450,'Jueves','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-24','2026-07-24',3064749,1000011),(451,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-25','2026-07-25',3064749,1000009),(452,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-26','2026-07-26',3064749,1000006),(453,'Sábado','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-02','2026-08-02',3064749,1000007),(454,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-28','2026-07-28',3064749,1000009),(455,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-29','2026-07-29',3064749,1000006),(456,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-30','2026-07-30',3064749,1000007),(457,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-07-31','2026-07-31',3064749,1000009),(458,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-01','2026-08-01',3064749,1000006),(459,'Sábado','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-02','2026-08-02',3064749,1000011),(460,'Lunes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-04','2026-08-04',3064749,1000017),(461,'Martes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-05','2026-08-05',3064749,1000011),(462,'Miércoles','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-06','2026-08-06',3064749,1000007),(463,'Lunes','09:00:00','12:00:00','DERECHOS FUNDAMENTALES','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-04','2026-08-04',3064749,1000016),(464,'Martes','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-05','2026-08-05',3064749,182),(465,'Miércoles','09:00:00','12:00:00','SST','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-06','2026-08-06',3064749,1000012),(466,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-08','2026-08-08',3064749,1000006),(467,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-09','2026-08-09',3064749,1000009),(468,'Lunes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-11','2026-08-11',3064749,1000007),(469,'Miércoles','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-13','2026-08-13',3064749,1000007),(470,'Jueves','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-14','2026-08-14',3064749,1000019),(471,'Lunes','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-11','2026-08-11',3064749,1000011),(472,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-12','2026-08-12',3064749,1000006),(473,'Miércoles','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-13','2026-08-13',3064749,1000011),(474,'Jueves','09:00:00','12:00:00','Comunicación','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-14','2026-08-14',3064749,1000014),(475,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-15','2026-08-15',3064749,1000009),(476,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-16','2026-08-16',3064749,1000006),(477,'Miércoles','06:00:00','09:00:00','AMBIENTAL','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-20','2026-08-20',3064749,1000015),(478,'Jueves','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-21','2026-08-21',3064749,1000007),(479,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-19','2026-08-19',3064749,1000009),(480,'Miércoles','09:00:00','12:00:00','ÉTICA','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-20','2026-08-20',3064749,1000010),(481,'Jueves','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-21','2026-08-21',3064749,1000011),(482,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-22','2026-08-22',3064749,1000006),(483,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-23','2026-08-23',3064749,1000009),(484,'Jueves','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-28','2026-08-28',3064749,1000007),(485,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-25','2026-08-25',3064749,1000009),(486,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-26','2026-08-26',3064749,1000007),(487,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-27','2026-08-27',3064749,1000006),(488,'Jueves','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-28','2026-08-28',3064749,1000011),(489,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-29','2026-08-29',3064749,1000009),(490,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-08-30','2026-08-30',3064749,1000006),(491,'Lunes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-01','2026-09-01',3064749,1000017),(492,'Miércoles','06:00:00','09:00:00','ÉTICA','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-03','2026-09-03',3064749,1000010),(493,'Lunes','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-01','2026-09-01',3064749,1000011),(494,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-02','2026-09-02',3064749,1000006),(495,'Miércoles','09:00:00','12:00:00','DERECHOS FUNDAMENTALES','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-03','2026-09-03',3064749,1000016),(496,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-04','2026-09-04',3064749,1000007),(497,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-05','2026-09-05',3064749,1000006),(498,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-06','2026-09-06',3064749,1000009),(499,'Lunes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-08','2026-09-08',3064749,1000011),(500,'Miércoles','06:00:00','09:00:00','SST','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-10','2026-09-10',3064749,1000012),(501,'Jueves','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-11','2026-09-11',3064749,182),(502,'Lunes','09:00:00','12:00:00','Comunicación','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-08','2026-09-08',3064749,1000014),(503,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-09','2026-09-09',3064749,1000009),(504,'Miércoles','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-10','2026-09-10',3064749,1000019),(505,'Jueves','09:00:00','12:00:00','AMBIENTAL','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-11','2026-09-11',3064749,1000015),(506,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-12','2026-09-12',3064749,1000009),(507,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-13','2026-09-13',3064749,1000006),(508,'Lunes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-15','2026-09-15',3064749,1000007),(509,'Jueves','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-18','2026-09-18',3064749,1000007),(510,'Lunes','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-15','2026-09-15',3064749,1000011),(511,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-16','2026-09-16',3064749,1000006),(512,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-17','2026-09-17',3064749,1000009),(513,'Jueves','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-18','2026-09-18',3064749,1000011),(514,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-19','2026-09-19',3064749,1000006),(515,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-20','2026-09-20',3064749,1000009),(516,'Lunes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-22','2026-09-22',3064749,1000007),(517,'Jueves','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-25','2026-09-25',3064749,1000007),(518,'Lunes','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-22','2026-09-22',3064749,1000011),(519,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-23','2026-09-23',3064749,1000009),(520,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-24','2026-09-24',3064749,1000006),(521,'Jueves','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-25','2026-09-25',3064749,1000011),(522,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-26','2026-09-26',3064749,1000009),(523,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-27','2026-09-27',3064749,1000006),(524,'Miércoles','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-01','2026-10-01',3064749,1000007),(525,'Viernes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-03','2026-10-03',3064749,182),(526,'Sábado','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-04','2026-10-04',3064749,1000007),(527,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-29','2026-09-29',3064749,1000006),(528,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-09-30','2026-09-30',3064749,1000009),(529,'Miércoles','09:00:00','12:00:00','DERECHOS FUNDAMENTALES','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-01','2026-10-01',3064749,1000016),(530,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-02','2026-10-02',3064749,1000006),(531,'Viernes','09:00:00','12:00:00','ÉTICA','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-03','2026-10-03',3064749,1000010),(532,'Sábado','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-04','2026-10-04',3064749,1000011),(533,'Lunes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-06','2026-10-06',3064749,1000007),(534,'Miércoles','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-08','2026-10-08',3064749,1000017),(535,'Jueves','06:00:00','09:00:00','Comunicación','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-09','2026-10-09',3064749,1000014),(536,'Lunes','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-06','2026-10-06',3064749,1000011),(537,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-07','2026-10-07',3064749,1000009),(538,'Miércoles','09:00:00','12:00:00','SST','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-08','2026-10-08',3064749,1000012),(539,'Jueves','09:00:00','12:00:00','AMBIENTAL','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-09','2026-10-09',3064749,1000015),(540,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-10','2026-10-10',3064749,1000006),(541,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-11','2026-10-11',3064749,1000009),(542,'Jueves','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-16','2026-10-16',3064749,1000007),(543,'Sábado','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-18','2026-10-18',3064749,1000006),(544,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-14','2026-10-14',3064749,1000009),(545,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-15','2026-10-15',3064749,1000006),(546,'Jueves','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-16','2026-10-16',3064749,1000011),(547,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-17','2026-10-17',3064749,1000009),(548,'Sábado','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-18','2026-10-18',3064749,1000011),(549,'Martes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-21','2026-10-21',3064749,1000011),(550,'Miércoles','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-22','2026-10-22',3064749,1000006),(551,'Viernes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-24','2026-10-24',3064749,1000006),(552,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-20','2026-10-20',3064749,1000009),(553,'Martes','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-21','2026-10-21',3064749,1000019),(554,'Miércoles','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-22','2026-10-22',3064749,1000011),(555,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-23','2026-10-23',3064749,1000009),(556,'Viernes','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-24','2026-10-24',3064749,1000011),(557,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-25','2026-10-25',3064749,1000007),(558,'Lunes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-27','2026-10-27',3064749,1000007),(559,'Jueves','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-30','2026-10-30',3064749,1000006),(560,'Sábado','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-01','2026-11-01',3064749,1000006),(561,'Lunes','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-27','2026-10-27',3064749,1000011),(562,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-28','2026-10-28',3064749,1000009),(563,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-29','2026-10-29',3064749,1000007),(564,'Jueves','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-30','2026-10-30',3064749,1000011),(565,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-10-31','2026-10-31',3064749,1000009),(566,'Sábado','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-01','2026-11-01',3064749,1000011),(567,'Martes','06:00:00','09:00:00','ÉTICA','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-04','2026-11-04',3064749,1000010),(568,'Miércoles','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-05','2026-11-05',3064749,1000017),(569,'Jueves','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-06','2026-11-06',3064749,1000007),(570,'Sábado','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-08','2026-11-08',3064749,1000009),(571,'Martes','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-04','2026-11-04',3064749,182),(572,'Miércoles','09:00:00','12:00:00','DERECHOS FUNDAMENTALES','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-05','2026-11-05',3064749,1000016),(573,'Jueves','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-06','2026-11-06',3064749,1000011),(574,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-07','2026-11-07',3064749,1000006),(575,'Sábado','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-08','2026-11-08',3064749,1000011),(576,'Miércoles','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-12','2026-11-12',3064749,1000019),(577,'Jueves','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-13','2026-11-13',3064749,1000007),(578,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-10','2026-11-10',3064749,1000009),(579,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-11','2026-11-11',3064749,1000006),(580,'Miércoles','09:00:00','12:00:00','SST','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-12','2026-11-12',3064749,1000012),(581,'Jueves','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-13','2026-11-13',3064749,1000011),(582,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-14','2026-11-14',3064749,1000009),(583,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-15','2026-11-15',3064749,1000007),(584,'Martes','06:00:00','09:00:00','AMBIENTAL','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-18','2026-11-18',3064749,1000015),(585,'Jueves','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-20','2026-11-20',3064749,1000007),(586,'Sábado','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-22','2026-11-22',3064749,1000006),(587,'Martes','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-18','2026-11-18',3064749,1000011),(588,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-19','2026-11-19',3064749,1000006),(589,'Jueves','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-20','2026-11-20',3064749,1000011),(590,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-21','2026-11-21',3064749,1000009),(591,'Sábado','09:00:00','12:00:00','Comunicación','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-22','2026-11-22',3064749,1000014),(592,'Jueves','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-27','2026-11-27',3064749,1000007),(593,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-24','2026-11-24',3064749,1000006),(594,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-25','2026-11-25',3064749,1000007),(595,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-26','2026-11-26',3064749,1000009),(596,'Jueves','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-27','2026-11-27',3064749,1000011),(597,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-28','2026-11-28',3064749,1000006),(598,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-11-29','2026-11-29',3064749,1000009),(599,'Lunes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-01','2026-12-01',3064749,1000017),(600,'Sábado','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-06','2026-12-06',3064749,1000007),(601,'Lunes','09:00:00','12:00:00','ÉTICA','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-01','2026-12-01',3064749,1000010),(602,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-02','2026-12-02',3064749,1000006),(603,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-03','2026-12-03',3064749,1000009),(604,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-04','2026-12-04',3064749,1000006),(605,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-05','2026-12-05',3064749,1000006),(606,'Sábado','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-06','2026-12-06',3064749,1000011),(607,'Jueves','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-11','2026-12-11',3064749,1000007),(608,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-09','2026-12-09',3064749,1000009),(609,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-10','2026-12-10',3064749,1000006),(610,'Jueves','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-11','2026-12-11',3064749,1000011),(611,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-12','2026-12-12',3064749,1000009),(612,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-13','2026-12-13',3064749,1000006),(613,'Lunes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-15','2026-12-15',3064749,1000007),(614,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-16','2026-12-16',3064749,1000009),(615,'Miércoles','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-17','2026-12-17',3064749,1000006),(616,'Jueves','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-18','2026-12-18',3064749,1000009),(617,'Viernes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-19','2026-12-19',3064749,1000006),(618,'Sábado','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-20','2026-12-20',3064749,1000009),(619,'Lunes','06:00:00','09:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-22','2026-12-22',3064749,1000019),(620,'Lunes','09:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-22','2026-12-22',3064749,182),(621,'Martes','06:00:00','12:00:00','TÉCNICO','completo','CONVERGENTES 4','Activo','Importado matriz SENA','2026-12-23','2026-12-23',3064749,1000006);
/*!40000 ALTER TABLE `horario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rol`
--

DROP TABLE IF EXISTS `rol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rol` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(45) NOT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre_rol` (`nombre_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rol`
--

LOCK TABLES `rol` WRITE;
/*!40000 ALTER TABLE `rol` DISABLE KEYS */;
INSERT INTO `rol` VALUES (1,'Administrador'),(3,'Aprendiz'),(2,'Instructor');
/*!40000 ALTER TABLE `rol` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sesiones_jornada`
--

DROP TABLE IF EXISTS `sesiones_jornada`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sesiones_jornada` (
  `id_sesion` int(11) NOT NULL AUTO_INCREMENT,
  `instructor_id` int(11) NOT NULL,
  `ficha_id` int(11) NOT NULL,
  `horario_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_apertura_real` datetime NOT NULL,
  `hora_limite_puntualidad` datetime NOT NULL,
  `hora_cierre_real` datetime DEFAULT NULL,
  `competencia` varchar(100) DEFAULT NULL,
  `tipo_bloque` enum('completo','partido') NOT NULL DEFAULT 'completo',
  `ambiente` varchar(100) DEFAULT NULL,
  `estado` enum('abierta','cerrada_manual') NOT NULL DEFAULT 'abierta',
  `creado_at` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_sesion`),
  UNIQUE KEY `uk_ficha_fecha` (`ficha_id`,`fecha`),
  KEY `idx_instructor_fecha` (`instructor_id`,`fecha`),
  KEY `idx_ficha_fecha` (`ficha_id`,`fecha`),
  KEY `fk_sesion_horario` (`horario_id`),
  CONSTRAINT `fk_sesion_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `ficha` (`id_ficha`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sesion_horario` FOREIGN KEY (`horario_id`) REFERENCES `horario` (`id_horario`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sesion_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sesiones_jornada`
--

LOCK TABLES `sesiones_jornada` WRITE;
/*!40000 ALTER TABLE `sesiones_jornada` DISABLE KEYS */;
INSERT INTO `sesiones_jornada` VALUES (2,53,24,116,'2026-10-15','2026-10-15 00:06:19','2026-10-15 00:10:00','2026-10-15 00:22:14',NULL,'completo',NULL,'cerrada_manual','2026-10-15 00:06:19','2026-10-15 00:22:14'),(3,53,24,116,'2026-10-16','2026-10-16 15:14:03','2026-10-16 15:10:00','2026-10-16 15:14:42',NULL,'completo',NULL,'cerrada_manual','2026-10-16 15:14:03','2026-10-16 15:14:42'),(4,53,24,116,'2026-10-19','2026-10-19 08:40:59','2026-10-19 08:50:59','2026-10-19 11:21:20',NULL,'completo',NULL,'cerrada_manual','2026-10-19 08:40:59','2026-10-19 11:21:20');
/*!40000 ALTER TABLE `sesiones_jornada` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_usuario` varchar(45) NOT NULL,
  `contrasena` varchar(400) NOT NULL,
  `nombre` varchar(45) NOT NULL,
  `apellido` varchar(45) NOT NULL,
  `tipo_documento` varchar(10) DEFAULT NULL,
  `identificacion` varchar(45) DEFAULT NULL,
  `telefono` varchar(45) DEFAULT NULL,
  `correo_electronico` varchar(100) DEFAULT NULL,
  `fk_rol` int(11) NOT NULL,
  `estado_invitacion_clave` enum('PENDIENTE','MANTENIDA','CAMBIADA') NOT NULL DEFAULT 'PENDIENTE',
  `fecha_resolucion_invitacion` datetime DEFAULT NULL,
  `fecha_ultimo_cambio_clave` datetime DEFAULT NULL,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `identificacion` (`identificacion`),
  KEY `fk_usuario_rol` (`fk_rol`),
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`fk_rol`) REFERENCES `rol` (`id_rol`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1000044 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (43,'admi_prueba','$2y$10$QyVgdUg5PIgvHNTcj4wdqO3VgYyjv9jBC7O0CLQySMdBDFiXHqfh2','Admin','Prueba',NULL,'DEMO-ADM-001','3000000000',NULL,1,'MANTENIDA',NULL,NULL),(53,'instructor','$2y$10$lfTCYajOEtwb0olBLc19Q./AZ4JAnq0Xx./8FnKbakoip8v215E0u','Camilo','Murgueitio',NULL,'11133445','3004567676',NULL,2,'MANTENIDA',NULL,NULL),(149,'ap_1113860035','$2y$10$AdCzBoV9SPiPCWYXqyQNGebWX.WoCv0BgxRWaKK2Kfc/LIVPzjlFy','ANDRES CAMILO','AGUDELO RAMIREZ',NULL,'1113860035','3147693398',NULL,3,'PENDIENTE',NULL,NULL),(150,'ap_1113862197','$2y$10$JOOY2.lKOR.MYk7rfszUgeSCRfQi4Kc37hvaAzr6nV9QfQJRb.mbi','ANDRES SANTIAGO','PEREZ PATIÑO',NULL,'1113862197','3114271206',NULL,3,'PENDIENTE',NULL,NULL),(151,'ap_1121711282','$2y$10$GxGNv1cRzzhWBxYMCP9UVukY8YwS4RSj7mbyo6lOmdDgwpQsIoE0C','BRAYAN','GIL MORENO',NULL,'1121711282','#N/A',NULL,3,'PENDIENTE',NULL,NULL),(152,'ap_1114151873','$2y$10$76A6wYmuE7nz6Z30cQ3xau2swI.8t9w9rMRdyRV30b75qY1ak5MCq','BRAYAN ESTEBAN','GONZALEZ GONZALEZ',NULL,'1114151873','3145345974',NULL,3,'PENDIENTE',NULL,NULL),(153,'ap_1006293987','$2y$10$1wr46rSxto7xkISoXDgmW.vh8X/B2IERwC5zI5ssYobOe8mM98naa','BRIAN','MARULANDA HENAO',NULL,'1006293987','3215137281',NULL,3,'PENDIENTE',NULL,NULL),(154,'ap_1114152000','$2y$10$ljqCFltsQze.O/iZ2n47Ne/ZgJn7ndj5vfu1Yh7DPPUjoIEq1frqe','Cristian Camilo','Murgueitio','CC','1114152000','3004567676','camilomurgueitio928@gmail.com',3,'MANTENIDA','2026-10-15 11:34:05',NULL),(155,'ap_1117511555','$2y$10$YeykuYohr6uWHDngIyQ.feVCK9E84rZo5FgXXQ8NRQiITgfXKpJve','DARWIN CAMILO','OTALVARO LOPEZ',NULL,'1117511555','3006465479',NULL,3,'MANTENIDA','2026-10-19 11:34:23',NULL),(156,'ap_1006295044','$2y$10$9LXzpf7T/BdRlfKEaVv4f.aXexRpe3gaM.MJqSdWhhxJHQJjeR8.G','DIEGO ALEJANDRO','GUERRERO HERNANDEZ',NULL,'1006295044','3041025607',NULL,3,'PENDIENTE',NULL,NULL),(157,'ap_1005101285','$2y$10$NZ9NGZkCTwoeDbm/0Dwh3erZwLWSx0XbRvE.Cv0z5UHKKNi3Qq4Ai','DONEY','CUELLAR SALAZAR',NULL,'1005101285','3053065692',NULL,3,'PENDIENTE',NULL,NULL),(158,'ap_5684857','$2y$10$OsDMzGXCaMr2bms01WUjsOy3yjEo0yyHgBfzwAYAiaYf8MkS0dGoG','DUNAMER BHUSVNASH','BEHARRY SANTOYO',NULL,'5684857','3003333333',NULL,3,'PENDIENTE',NULL,NULL),(159,'ap_1114154542','$2y$10$DkoU0bLbJc8s16t0XvLtle8hPp6VsTvwBEKRXud/J0BuLuZu7QkM6','ISAAC','BLANCO CASTAÑO',NULL,'1114154542','3003333333',NULL,3,'PENDIENTE',NULL,NULL),(160,'ap_1108564214','$2y$10$pm.fGnPYQW7JUi5Dy8s9ke.BKF6ZWZZKQ7w2e0p.kNmsXiUQMnMBK','JEFERSON','FRANCO RAMIREZ',NULL,'1108564214','3188388803',NULL,3,'PENDIENTE',NULL,NULL),(161,'ap_1094918859','$2y$10$Y3F1pY2OKYkzsA3slB4h5OqDhJh6CQZrLKPFcueaGroJ2XBtm9/8.','JHON SEBASTIAN','VALDES VELEZ',NULL,'1094918859','3114791727',NULL,3,'MANTENIDA','2026-10-17 12:19:23',NULL),(162,'ap_1114150420','$2y$10$bE689a8wvvermKUo2kB4.Opw9XHmKXT5gdo34.qDpaCuwFKWLgKky','JHON SEBASTIAN','SANCHEZ FRANCO',NULL,'1114150420','#N/A',NULL,3,'PENDIENTE',NULL,NULL),(163,'ap_1113859628','$2y$10$8DW1OVF2QtoNG2vkphw7h.wQ2L3ZW5Q3DkOHasVh09CWFuPT3sEIK','JORGE ANDRES','ORTIZ RESTREPO',NULL,'1113859628','#N/A',NULL,3,'PENDIENTE',NULL,NULL),(164,'ap_1114152494','$2y$10$taVZt9gFwK2rxloOaJ2BH.2j2Dk6vpx6oFhzyM9Cb4M5wGFquVBmC','JUAN CAMILO','ARAUJO OSSA',NULL,'1114152494','3118941678',NULL,3,'PENDIENTE',NULL,NULL),(165,'ap_1006320521','$2y$10$w4KO26vTUEDuTPaW9FdWXeEHMZbb4D01/.yhycu3Ywmsar5gn4U7m','JUAN DAVID','VILLEGAS GARCIA',NULL,'1006320521','3225020301',NULL,3,'PENDIENTE',NULL,NULL),(166,'ap_1112779953','$2y$10$de4Lv0.Q0OyOBulJK9aYkuxSzjI8Cu9J2fvF0rDElLFGj/o5/L2sy','JUAN DAVID','SANCHEZ NARVAEZ',NULL,'1112779953','2092174',NULL,3,'PENDIENTE',NULL,NULL),(167,'ap_1113861401','$2y$10$Nsa7mjAk1ID9oxgh9B3tXO574IBWbfmn4sQYKDW8YpaeSi2P/7KSi','JUAN ESTEBAN','MUÑOZ GOMEZ',NULL,'1113861401','3012266997',NULL,3,'PENDIENTE',NULL,NULL),(168,'ap_1114153404','$2y$10$8F67m8MXbM84/l0zQa2fy.YiKq8Ns6/wZprofNVA6r47XmP56VuGi','JUAN ESTEBAN','OSSA GALICIA',NULL,'1114153404','3135996183',NULL,3,'PENDIENTE',NULL,NULL),(170,'ap_1114090963','$2y$10$dWqrJTy18FUq5FUNRr8.YeuzVD06GdOF9.qxaxz/11EcyoooBcB6.','LEVINSON DANIEL','ATEHORTUA LONDOÑO',NULL,'1114090963','3182863021',NULL,3,'PENDIENTE',NULL,NULL),(172,'ap_1114153062','$2y$10$D/Inu48BbbctqVSvCVf9JOogsLWgixthgLo6i30JUjIxCHCvBTZ2K','NICOLAS','CASTRO GUTIERREZ',NULL,'1114153062','3017683672',NULL,3,'PENDIENTE',NULL,NULL),(173,'ap_1114090313','$2y$10$Fvi2cLTcD/OohZiAy3komOlNl5lmLsDFBEfA09a0GgIY4ip6lEU1i','SEBASTIAN','BOBADILLA MUÑOZ',NULL,'1114090313','3234388285',NULL,3,'PENDIENTE',NULL,NULL),(174,'ap_1114152482','$2y$10$Ka7THGZNrEsc6uBwOhgDjO3jcU1pdNJts16Crov/7IJOcxNzKcx/.','SERGIO','HERRERA QUINTERO',NULL,'1114152482','3205001751',NULL,3,'PENDIENTE',NULL,NULL),(175,'ap_1086634644','$2y$10$Hg2d0LwV5Fa2fHnUJWGM6ehEq.YYTV0C/UKlgSO00uGHlvJYx50.6','WILLIAM ALEXIS','SOTO ALZATE',NULL,'1086634644','#N/A',NULL,3,'PENDIENTE',NULL,NULL),(176,'ap_001234567','$2y$10$oOTtJs7str.VuOZ7pi.CFuJrSnJscYvmo3V6LP15vfGnuOBCfnv5W','Carlos','Pérez',NULL,'001234567','3001234567',NULL,3,'PENDIENTE',NULL,NULL),(177,'ap_1098765432','$2y$10$p4jdqBOPMcTYDV.t1lKMFOSN6v8IXiAcDoj2DwfD2cUCGPRJMb4Ou','María','Gómez',NULL,'1098765432',NULL,NULL,3,'PENDIENTE',NULL,NULL),(182,'instructor_edgar','$2y$10$X2FUBIshq93i1mwCVNKbSuY0ZPNuc.AEVEhOfbgNBwPlQssNYfQZu','Edgar Enrique','Melo Díaz',NULL,'9876543210','3109876543','emelo@sena.edu.co',2,'MANTENIDA',NULL,NULL),(1000006,'inst_11425234','$2y$10$Znd/paRB23B6gyGZie1d9OfOGlHX/t9KLNutcZiPrZm4SnzT4iF2q','Juan Camilo','Vanegas González',NULL,'11425234',NULL,'instructor1@gmail.com',2,'PENDIENTE',NULL,NULL),(1000007,'inst_1114152889','$2y$10$kFQYmdoGCwWP8l0HCCYtRe63qYsI53mmzjclFcJ.WYkVOgob0cp3y','Bladimir Silva','Toro',NULL,'1114152889',NULL,'instructor2@gmail.com',2,'PENDIENTE',NULL,NULL),(1000008,'inst_123141','$2y$10$57IrZ6R7eHCWYhRUTMZPrOg9lDx8y9nhWJuSTgRKi2NYB3GzSwLPq','Michel','Monsalve',NULL,'123141',NULL,'instructor3@gmail.com',2,'PENDIENTE',NULL,NULL),(1000009,'inst_45345345','$2y$10$ysdF5kZHDOwRIk.eF/gTlumTL5DClgerLMeo1PFnDeoEII7G450lG','Walter Arias','Aguirre',NULL,'45345345',NULL,'instructor4@gmail.com',2,'PENDIENTE',NULL,NULL),(1000010,'inst_34534534534','$2y$10$WsTnNQ.hxIRiHhYXGpJxC.DOZhiWr3.gzHWyO46WDkwsOWsrlDhI2','Luz Miraim','Hernández Muñoz',NULL,'34534534534',NULL,'instructor5@gmail.com',2,'PENDIENTE',NULL,NULL),(1000011,'inst_345365567','$2y$10$vIhUYlyMo1jZ2bwZIOhr0.aCim7I80uXtDwYIdg.EDKSXkTIvNu0O','Mónica Burgos','Velásquez',NULL,'345365567',NULL,'instructor6@gmail.com',2,'PENDIENTE',NULL,NULL),(1000012,'inst_575757765','$2y$10$KvsQrEqthFMCjq4Z29/Dfe3VjmyuqVQxh12r1c3CDKtRtcTrptr3q','Leyza Fernanda','Hoyos Rivera',NULL,'575757765',NULL,'instructor7@gmail.com',2,'PENDIENTE',NULL,NULL),(1000013,'inst_5675675','$2y$10$MsPoO7NCHdV02LoqbZgrdePVq7TZhyYbseyhd08e3FrQqN5TjTJci','Isabel Cristina','Guzmán López',NULL,'5675675',NULL,'instructor8@gmail.com',2,'PENDIENTE',NULL,NULL),(1000014,'inst_787568768','$2y$10$6BtGBAZ0RFoPGl0/lyfNx.tmpyfdIgzonhZM5sWEkp51.d1WlAE1e','María Isbeire','Ospina Zapata',NULL,'787568768',NULL,'instructor9@gmail.com',2,'PENDIENTE',NULL,NULL),(1000015,'inst_345343','$2y$10$oR2DhU9AzPX2XRhhIajCb.zJZbonATQbaoqWhtM8646pV1hWjXYta','Katherine Batista','Campo',NULL,'345343',NULL,'instructor10@gmail.com',2,'PENDIENTE',NULL,NULL),(1000016,'inst_756685','$2y$10$qWvyqE31pUyQE2jyYwyRpui9bw6wotxUbyPXzPNzFN4/FrNmj36Cy','Kevin Alejandro','Holguín Marttínez',NULL,'756685',NULL,'instructor11@gmail.com',2,'PENDIENTE',NULL,NULL),(1000017,'inst_4564645','$2y$10$0byaRdYX9laUkey.vtmZEeh8tVwXscFxk.9DFTlQ2T3OyVWkrPBOq','Mario Fernando','Moncayo Palacios',NULL,'4564645',NULL,'instructor12@gmail.com',2,'PENDIENTE',NULL,NULL),(1000018,'inst_111323','$2y$10$3DO6zdfE2bmQmTYLPJlQ8uj4bBSNf5iIaV9aFvvh8VR.QRs.ntB0m','Johnny Mauricio','Galvis Escobar',NULL,'111323',NULL,'instructor77@gmail.com',2,'PENDIENTE',NULL,NULL),(1000019,'inst_1243234234','$2y$10$uwkqHIaeKofuMQJM8oYtUeG8qDVlYVNYSWP90m/yQxglhZ3uy9JV6','Carlos Eduardo','Duque Valderruten',NULL,'1243234234',NULL,'instructor776@gmail.com',2,'PENDIENTE',NULL,NULL);
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'control_asistencia'
--
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `generar_inasistencias` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `generar_inasistencias`()
BEGIN
    DECLARE fecha_actual DATE;
    DECLARE hora_actual TIME;
    DECLARE dia_semana_actual VARCHAR(15);
    DECLARE cantidad_registros INT DEFAULT 0;

    SET fecha_actual = CURDATE();
    SET hora_actual = CURTIME();

    SET dia_semana_actual = CASE DAYOFWEEK(fecha_actual)
        WHEN 1 THEN 'Domingo'
        WHEN 2 THEN 'Lunes'
        WHEN 3 THEN 'Martes'
        WHEN 4 THEN 'Miércoles'
        WHEN 5 THEN 'Jueves'
        WHEN 6 THEN 'Viernes'
        WHEN 7 THEN 'Sábado'
    END;

    INSERT INTO asistencia (
        fecha_asistencia,
        entrada,
        salida,
        estado_entrada,
        estado_salida,
        minutos_retardo,
        minutos_salida_anticipada,
        fk_aprendiz
    )
    SELECT
        fecha_actual,
        NULL,
        NULL,
        'INASISTENCIA',
        NULL,
        0,
        0,
        ap.id_aprendiz
    FROM aprendiz ap
    INNER JOIN horario h
        ON h.fk_ficha = ap.fk_ficha
    WHERE h.dia_semana = dia_semana_actual
      AND hora_actual >= ADDTIME(h.entrada, '00:20:00')
      AND NOT EXISTS (
          SELECT 1
          FROM asistencia a
          WHERE a.fk_aprendiz = ap.id_aprendiz
            AND a.fecha_asistencia = fecha_actual
      );

    SET cantidad_registros = ROW_COUNT();

    SELECT cantidad_registros AS inasistencias_generadas;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `generar_sin_salidas` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `generar_sin_salidas`()
BEGIN
    DECLARE fecha_actual DATE;
    DECLARE hora_actual TIME;
    DECLARE dia_semana_actual VARCHAR(15);
    DECLARE cantidad_registros INT DEFAULT 0;

    SET fecha_actual = CURDATE();
    SET hora_actual = CURTIME();

    SET dia_semana_actual = CASE DAYOFWEEK(fecha_actual)
        WHEN 1 THEN 'Domingo'
        WHEN 2 THEN 'Lunes'
        WHEN 3 THEN 'Martes'
        WHEN 4 THEN 'Miércoles'
        WHEN 5 THEN 'Jueves'
        WHEN 6 THEN 'Viernes'
        WHEN 7 THEN 'Sábado'
    END;

    UPDATE asistencia a
    INNER JOIN aprendiz ap
        ON a.fk_aprendiz = ap.id_aprendiz
    INNER JOIN horario h
        ON h.fk_ficha = ap.fk_ficha
    SET a.estado_salida = 'SIN_SALIDA'
    WHERE a.fecha_asistencia = fecha_actual
      AND a.entrada IS NOT NULL
      AND a.salida IS NULL
      AND a.estado_entrada <> 'INASISTENCIA'
      AND h.dia_semana = dia_semana_actual
      AND hora_actual >= h.salida;

    SET cantidad_registros = ROW_COUNT();

    SELECT cantidad_registros AS registros_sin_salida_generados;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `registrar_entrada` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `registrar_entrada`(IN `codigo_rfid_aprendiz` VARCHAR(50))
BEGIN
    DECLARE id_aprendiz_actual INT;
    DECLARE id_ficha_actual INT;
    DECLARE hora_entrada_programada TIME;
    DECLARE hora_salida_programada TIME;
    DECLARE fecha_actual DATE;
    DECLARE hora_actual TIME;
    DECLARE id_asistencia_actual INT DEFAULT NULL;
    DECLARE minutos_retardo INT DEFAULT 0;
    DECLARE dia_semana_actual VARCHAR(15);

    SET fecha_actual = CURDATE();
    SET hora_actual = CURTIME();

    SET dia_semana_actual = CASE DAYOFWEEK(fecha_actual)
        WHEN 1 THEN 'Domingo'
        WHEN 2 THEN 'Lunes'
        WHEN 3 THEN 'Martes'
        WHEN 4 THEN 'Miércoles'
        WHEN 5 THEN 'Jueves'
        WHEN 6 THEN 'Viernes'
        WHEN 7 THEN 'Sábado'
    END;

    SELECT id_aprendiz, fk_ficha
    INTO id_aprendiz_actual, id_ficha_actual
    FROM aprendiz
    WHERE codigo_rfid = codigo_rfid_aprendiz
    LIMIT 1;

    IF id_aprendiz_actual IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'RFID no registrado';
    END IF;

    SELECT entrada, salida
    INTO hora_entrada_programada, hora_salida_programada
    FROM horario
    WHERE fk_ficha = id_ficha_actual
      AND dia_semana = dia_semana_actual
    LIMIT 1;

    IF hora_entrada_programada IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La ficha no tiene horario para el día de hoy';
    END IF;

    SELECT id_asistencia
    INTO id_asistencia_actual
    FROM asistencia
    WHERE fk_aprendiz = id_aprendiz_actual
      AND fecha_asistencia = fecha_actual
    LIMIT 1;

    IF id_asistencia_actual IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El aprendiz ya tiene un registro de asistencia para hoy';
    END IF;

    IF hora_actual < SUBTIME(hora_entrada_programada, '00:15:00') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El registro es demasiado temprano. Aún no está habilitada la entrada';
    END IF;

    IF hora_actual <= hora_entrada_programada THEN
        INSERT INTO asistencia (
            fecha_asistencia,
            entrada,
            salida,
            estado_entrada,
            estado_salida,
            minutos_retardo,
            minutos_salida_anticipada,
            fk_aprendiz
        ) VALUES (
            fecha_actual,
            NOW(),
            NULL,
            'PUNTUAL',
            NULL,
            0,
            0,
            id_aprendiz_actual
        );

    ELSEIF hora_actual <= ADDTIME(hora_entrada_programada, '00:20:00') THEN
        SET minutos_retardo = TIMESTAMPDIFF(
            MINUTE,
            TIMESTAMP(fecha_actual, hora_entrada_programada),
            NOW()
        );

        INSERT INTO asistencia (
            fecha_asistencia,
            entrada,
            salida,
            estado_entrada,
            estado_salida,
            minutos_retardo,
            minutos_salida_anticipada,
            fk_aprendiz
        ) VALUES (
            fecha_actual,
            NOW(),
            NULL,
            'RETARDO',
            NULL,
            minutos_retardo,
            0,
            id_aprendiz_actual
        );

    ELSE
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Tiempo de entrada superado. La entrada no puede registrarse';
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `registrar_excusa` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `registrar_excusa`(IN `id_asistencia_excusa` INT, IN `archivo_excusa` VARCHAR(450), IN `observacion_excusa` VARCHAR(450))
BEGIN
    DECLARE estado_asistencia_actual VARCHAR(45);
    DECLARE id_excusa_actual INT DEFAULT NULL;

    SELECT estado_entrada
    INTO estado_asistencia_actual
    FROM asistencia
    WHERE id_asistencia = id_asistencia_excusa
    LIMIT 1;

    IF estado_asistencia_actual IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La asistencia indicada no existe';
    END IF;

    IF estado_asistencia_actual <> 'INASISTENCIA' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Solo se puede presentar una excusa para una inasistencia';
    END IF;

    SELECT id_excusa
    INTO id_excusa_actual
    FROM excusa
    WHERE fk_asistencia = id_asistencia_excusa
    LIMIT 1;

    IF id_excusa_actual IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Esta inasistencia ya tiene una excusa registrada';
    END IF;

    INSERT INTO excusa (
        archivo,
        observacion,
        estado,
        fecha_subida,
        fecha_revision,
        fk_asistencia,
        fk_usuario_instructor
    ) VALUES (
        archivo_excusa,
        observacion_excusa,
        'PENDIENTE',
        CURDATE(),
        NULL,
        id_asistencia_excusa,
        NULL
    );

    SELECT 'Excusa registrada correctamente' AS mensaje;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `registrar_salida` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `registrar_salida`(IN `codigo_rfid_aprendiz` VARCHAR(50))
BEGIN
    DECLARE id_aprendiz_actual INT;
    DECLARE id_ficha_actual INT;
    DECLARE id_asistencia_actual INT;
    DECLARE hora_salida_programada TIME;
    DECLARE hora_salida_actual TIME;
    DECLARE fecha_actual DATE;
    DECLARE minutos_salida_anticipada INT DEFAULT 0;
    DECLARE dia_semana_actual VARCHAR(15);

    SET fecha_actual = CURDATE();
    SET hora_salida_actual = CURTIME();

    SET dia_semana_actual = CASE DAYOFWEEK(fecha_actual)
        WHEN 1 THEN 'Domingo'
        WHEN 2 THEN 'Lunes'
        WHEN 3 THEN 'Martes'
        WHEN 4 THEN 'Miércoles'
        WHEN 5 THEN 'Jueves'
        WHEN 6 THEN 'Viernes'
        WHEN 7 THEN 'Sábado'
    END;

    SELECT id_aprendiz, fk_ficha
    INTO id_aprendiz_actual, id_ficha_actual
    FROM aprendiz
    WHERE codigo_rfid = codigo_rfid_aprendiz
    LIMIT 1;

    IF id_aprendiz_actual IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'RFID no registrado';
    END IF;

    SELECT salida
    INTO hora_salida_programada
    FROM horario
    WHERE fk_ficha = id_ficha_actual
      AND dia_semana = dia_semana_actual
    LIMIT 1;

    IF hora_salida_programada IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La ficha no tiene horario de salida para el día de hoy';
    END IF;

    SELECT id_asistencia
    INTO id_asistencia_actual
    FROM asistencia
    WHERE fk_aprendiz = id_aprendiz_actual
      AND fecha_asistencia = fecha_actual
      AND entrada IS NOT NULL
    LIMIT 1;

    IF id_asistencia_actual IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El aprendiz no tiene una entrada registrada para hoy';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM asistencia
        WHERE id_asistencia = id_asistencia_actual
          AND salida IS NOT NULL
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El aprendiz ya tiene registrada su salida de hoy';
    END IF;

    IF hora_salida_actual < hora_salida_programada THEN
        SET minutos_salida_anticipada = TIMESTAMPDIFF(
            MINUTE,
            NOW(),
            TIMESTAMP(fecha_actual, hora_salida_programada)
        );

        UPDATE asistencia
        SET salida = NOW(),
            estado_salida = 'SALIDA_ANTICIPADA',
            minutos_salida_anticipada = minutos_salida_anticipada
        WHERE id_asistencia = id_asistencia_actual;
    ELSE
        UPDATE asistencia
        SET salida = NOW(),
            estado_salida = 'SALIDA_NORMAL',
            minutos_salida_anticipada = 0
        WHERE id_asistencia = id_asistencia_actual;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `revisar_excusa` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `revisar_excusa`(IN `id_excusa_revisar` INT, IN `id_usuario_instructor` INT, IN `decision_excusa` VARCHAR(45))
BEGIN
    DECLARE estado_excusa_actual VARCHAR(45);
    DECLARE rol_usuario_actual INT;
    DECLARE nombre_rol_actual VARCHAR(45);

    SELECT estado
    INTO estado_excusa_actual
    FROM excusa
    WHERE id_excusa = id_excusa_revisar
    LIMIT 1;

    IF estado_excusa_actual IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La excusa indicada no existe';
    END IF;

    IF estado_excusa_actual <> 'PENDIENTE' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Esta excusa ya fue revisada';
    END IF;

    SELECT u.fk_rol, r.nombre_rol
    INTO rol_usuario_actual, nombre_rol_actual
    FROM usuario u
    INNER JOIN rol r
        ON u.fk_rol = r.id_rol
    WHERE u.id_usuario = id_usuario_instructor
    LIMIT 1;

    IF rol_usuario_actual IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El usuario indicado no existe';
    END IF;

    IF nombre_rol_actual <> 'Instructor' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El usuario no tiene permisos para revisar excusas';
    END IF;

    IF decision_excusa NOT IN ('APROBADA', 'RECHAZADA') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La decisión debe ser APROBADA o RECHAZADA';
    END IF;

    UPDATE excusa
    SET estado = decision_excusa,
        fecha_revision = CURDATE(),
        fk_usuario_instructor = id_usuario_instructor
    WHERE id_excusa = id_excusa_revisar;

    SELECT
        'Excusa revisada correctamente' AS mensaje,
        decision_excusa AS estado_final;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-23 20:00:38
