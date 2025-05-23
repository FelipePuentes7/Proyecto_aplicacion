-- MySQL dump 10.13  Distrib 8.0.34, for Win64 (x86_64)
--
-- Host: localhost    Database: transporte
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `buses`
--

DROP TABLE IF EXISTS `buses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `buses` (
  `bus_id` int(11) NOT NULL AUTO_INCREMENT,
  `placa` varchar(20) DEFAULT NULL,
  `modelo` varchar(50) DEFAULT NULL,
  `capacidad` int(11) DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`bus_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `buses`
--

LOCK TABLES `buses` WRITE;
/*!40000 ALTER TABLE `buses` DISABLE KEYS */;
INSERT INTO `buses` VALUES (1,'ABC123','Volvo 2020',45,'Operativo'),(2,'DEF456','Mercedes 2019',50,'Operativo'),(3,'GHI789','Scania 2021',55,'En mantenimiento'),(4,'JKL012','Volvo 2018',40,'Operativo'),(5,'MNO345','Mercedes 2020',60,'Operativo'),(6,'PQR678','Scania 2019',50,'Operativo'),(7,'STU901','Volvo 2021',45,'Operativo'),(8,'VWX234','Mercedes 2018',50,'En mantenimiento'),(9,'YZA567','Scania 2020',55,'Operativo'),(10,'BCD890','Volvo 2019',45,'Operativo');
/*!40000 ALTER TABLE `buses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pasajeros`
--

DROP TABLE IF EXISTS `pasajeros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pasajeros` (
  `pasajero_id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) DEFAULT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`pasajero_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pasajeros`
--

LOCK TABLES `pasajeros` WRITE;
/*!40000 ALTER TABLE `pasajeros` DISABLE KEYS */;
INSERT INTO `pasajeros` VALUES (1,'Juan','Pérez','3111111111','juan.perez@example.com'),(2,'María','López','3222222222','maria.lopez@example.com'),(3,'Carlos','García','3333333333','carlos.garcia@example.com'),(4,'Ana','Martínez','3444444444','ana.martinez@example.com'),(5,'Luis','Rodríguez','3555555555','luis.rodriguez@example.com'),(6,'Sofía','González','3666666666','sofia.gonzalez@example.com'),(7,'Diego','Ramírez','3777777777','diego.ramirez@example.com'),(8,'Laura','Torres','3888888888','laura.torres@example.com'),(9,'Jorge','Hernández','3999999999','jorge.hernandez@example.com'),(10,'Camila','Cruz','4000000000','camila.cruz@example.com'),(11,'Gabriel','Mendoza','3112345678','gabriel.mendoza@example.com'),(12,'Natalia','Ríos','3223456789','natalia.rios@example.com'),(13,'Alejandro','Vargas','3334567890','alejandro.vargas@example.com'),(14,'Mónica','Flores','3445678901','monica.flores@example.com'),(15,'Fernando','Castillo','3556789012','fernando.castillo@example.com');
/*!40000 ALTER TABLE `pasajeros` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rutas`
--

DROP TABLE IF EXISTS `rutas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rutas` (
  `ruta_id` int(11) NOT NULL AUTO_INCREMENT,
  `origen` varchar(100) DEFAULT NULL,
  `destino` varchar(100) DEFAULT NULL,
  `distancia_km` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`ruta_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rutas`
--

LOCK TABLES `rutas` WRITE;
/*!40000 ALTER TABLE `rutas` DISABLE KEYS */;
INSERT INTO `rutas` VALUES (1,'Neiva','Bogotá',325.50),(2,'Neiva','Cali',280.00),(3,'Neiva','Medellín',550.00),(4,'Neiva','Cartagena',800.00),(5,'Neiva','Santa Marta',870.00),(6,'Neiva','Bucaramanga',640.00),(7,'Neiva','Pereira',410.00),(8,'Neiva','Armenia',380.00),(9,'Neiva','Manizales',420.00),(10,'Neiva','Ibague',200.00),(11,'Neiva','Villavicencio',460.00),(12,'Neiva','Barranquilla',850.00),(13,'Neiva','Cúcuta',750.00);
/*!40000 ALTER TABLE `rutas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tickets` (
  `ticket_id` int(11) NOT NULL AUTO_INCREMENT,
  `pasajero_id` int(11) DEFAULT NULL,
  `viaje_id` int(11) DEFAULT NULL,
  `numero_asiento` int(11) DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`ticket_id`),
  KEY `pasajero_id` (`pasajero_id`),
  KEY `viaje_id` (`viaje_id`),
  CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`pasajero_id`) REFERENCES `pasajeros` (`pasajero_id`),
  CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`viaje_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tickets`
--

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
INSERT INTO `tickets` VALUES (1,1,1,1,50000.00),(2,2,2,2,45000.00),(3,3,3,3,60000.00),(4,4,4,4,55000.00),(5,5,5,5,70000.00),(6,6,6,6,65000.00),(7,7,7,7,75000.00),(8,8,8,8,80000.00),(9,9,9,9,85000.00),(10,10,10,10,90000.00);
/*!40000 ALTER TABLE `tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `viajes`
--

DROP TABLE IF EXISTS `viajes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `viajes` (
  `viaje_id` int(11) NOT NULL AUTO_INCREMENT,
  `ruta_id` int(11) DEFAULT NULL,
  `bus_id` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `hora` time DEFAULT NULL,
  PRIMARY KEY (`viaje_id`),
  KEY `ruta_id` (`ruta_id`),
  KEY `bus_id` (`bus_id`),
  CONSTRAINT `viajes_ibfk_1` FOREIGN KEY (`ruta_id`) REFERENCES `rutas` (`ruta_id`),
  CONSTRAINT `viajes_ibfk_2` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`bus_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `viajes`
--

LOCK TABLES `viajes` WRITE;
/*!40000 ALTER TABLE `viajes` DISABLE KEYS */;
INSERT INTO `viajes` VALUES (1,1,1,'2025-02-15','08:00:00'),(2,2,2,'2025-02-16','09:00:00'),(3,3,3,'2025-02-17','10:00:00'),(4,4,4,'2025-02-18','11:00:00'),(5,5,5,'2025-02-19','12:00:00'),(6,6,6,'2025-02-20','13:00:00'),(7,7,7,'2025-02-21','14:00:00'),(8,8,8,'2025-02-22','15:00:00'),(9,9,9,'2025-02-23','16:00:00'),(10,10,10,'2025-02-24','17:00:00');
/*!40000 ALTER TABLE `viajes` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-02-12 20:35:06
