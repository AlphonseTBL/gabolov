-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         8.4.3 - MySQL Community Server - GPL
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Volcando estructura de base de datos para gabicpro
CREATE DATABASE IF NOT EXISTS `gabicpro` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `gabicpro`;

-- Volcando estructura para tabla gabicpro.usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `UsuarioID` int NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Email` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `PasswordHash` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `TiempoUsuario` date NOT NULL,
  `UltimoUso` date NOT NULL,
  PRIMARY KEY (`UsuarioID`),
  UNIQUE KEY `UK_Usuarios_Email` (`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla gabicpro.alumnos
CREATE TABLE IF NOT EXISTS `alumnos` (
  `AlumnoID` int NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `Carrera` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `UsuarioID` int DEFAULT NULL,
  PRIMARY KEY (`AlumnoID`),
  KEY `UsuarioID` (`UsuarioID`),
  CONSTRAINT `alumnos_ibfk_1` FOREIGN KEY (`UsuarioID`) REFERENCES `usuarios` (`UsuarioID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La exportación de datos fue deseleccionada.

-- Datos base para validacion de registro por ID escolar (sin usuario vinculado).
INSERT INTO `alumnos` (`AlumnoID`, `Nombre`, `Carrera`, `UsuarioID`)
SELECT 1001, 'Ana Torres', 'Ingenieria en Sistemas', NULL
WHERE NOT EXISTS (SELECT 1 FROM `alumnos` WHERE `AlumnoID` = 1001);

INSERT INTO `alumnos` (`AlumnoID`, `Nombre`, `Carrera`, `UsuarioID`)
SELECT 1002, 'Luis Mendoza', 'Ingenieria Industrial', NULL
WHERE NOT EXISTS (SELECT 1 FROM `alumnos` WHERE `AlumnoID` = 1002);

-- Volcando estructura para tabla gabicpro.maestros
CREATE TABLE IF NOT EXISTS `maestros` (
  `MaestroID` int NOT NULL,
  `Nombre` varchar(60) COLLATE utf8mb4_general_ci NOT NULL,
  `Departamento` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `UsuarioID` int DEFAULT NULL,
  PRIMARY KEY (`MaestroID`),
  KEY `UsuarioID` (`UsuarioID`),
  CONSTRAINT `maestros_ibfk_1` FOREIGN KEY (`UsuarioID`) REFERENCES `usuarios` (`UsuarioID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La exportación de datos fue deseleccionada.

INSERT INTO `maestros` (`MaestroID`, `Nombre`, `Departamento`, `UsuarioID`)
SELECT 5001, 'Maria Ortega', 'Ciencias Basicas', NULL
WHERE NOT EXISTS (SELECT 1 FROM `maestros` WHERE `MaestroID` = 5001);

INSERT INTO `maestros` (`MaestroID`, `Nombre`, `Departamento`, `UsuarioID`)
SELECT 5002, 'Carlos Vega', 'Ingenieria', NULL
WHERE NOT EXISTS (SELECT 1 FROM `maestros` WHERE `MaestroID` = 5002);

-- Volcando estructura para tabla gabicpro.bicicletas
CREATE TABLE IF NOT EXISTS `bicicletas` (
  `BicicletaID` int NOT NULL AUTO_INCREMENT,
  `Estado` enum('Disponible','Ocupada','En Mantenimiento') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Disponible',
  `TiempoUso` date NOT NULL,
  `UltimoUso` date NOT NULL,
  `Modelo` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `ImagenURL` varchar(500) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `Comentarios` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '"Sin comentarios"',
  PRIMARY KEY (`BicicletaID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla gabicpro.prestamo
CREATE TABLE IF NOT EXISTS `prestamo` (
  `PrestamoID` int NOT NULL AUTO_INCREMENT,
  `UsuarioID` int DEFAULT NULL,
  `BicicletaID` int DEFAULT NULL,
  `DiaUso` date DEFAULT NULL,
  `EstadoViaje` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`PrestamoID`),
  KEY `UsuarioID` (`UsuarioID`),
  KEY `BicicletaID` (`BicicletaID`),
  KEY `IDX_Prestamo_DiaUso` (`DiaUso`),
  CONSTRAINT `prestamo_ibfk_1` FOREIGN KEY (`UsuarioID`) REFERENCES `usuarios` (`UsuarioID`),
  CONSTRAINT `prestamo_ibfk_2` FOREIGN KEY (`BicicletaID`) REFERENCES `bicicletas` (`BicicletaID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La exportación de datos fue deseleccionada.

-- Carga inicial de bicicletas del catalogo web: 3 unidades por modelo.
INSERT INTO `bicicletas` (`Estado`, `TiempoUso`, `UltimoUso`, `Modelo`, `ImagenURL`, `Comentarios`)
SELECT 'Disponible', CURDATE(), CURDATE(), 'Montaña Pro XT', 'https://images.pexels.com/photos/100582/pexels-photo-100582.jpeg?auto=compress&cs=tinysrgb&w=400', 'Unidad 1'
WHERE NOT EXISTS (SELECT 1 FROM `bicicletas` WHERE `Modelo` = 'Montaña Pro XT' AND `Comentarios` = 'Unidad 1')
UNION ALL
SELECT 'Disponible', CURDATE(), CURDATE(), 'Montaña Pro XT', 'https://images.pexels.com/photos/100582/pexels-photo-100582.jpeg?auto=compress&cs=tinysrgb&w=400', 'Unidad 2'
WHERE NOT EXISTS (SELECT 1 FROM `bicicletas` WHERE `Modelo` = 'Montaña Pro XT' AND `Comentarios` = 'Unidad 2')
UNION ALL
SELECT 'Disponible', CURDATE(), CURDATE(), 'Montaña Pro XT', 'https://images.pexels.com/photos/100582/pexels-photo-100582.jpeg?auto=compress&cs=tinysrgb&w=400', 'Unidad 3'
WHERE NOT EXISTS (SELECT 1 FROM `bicicletas` WHERE `Modelo` = 'Montaña Pro XT' AND `Comentarios` = 'Unidad 3')
UNION ALL
SELECT 'Disponible', CURDATE(), CURDATE(), 'Urban Glass V2', 'https://images.pexels.com/photos/276517/pexels-photo-276517.jpeg?auto=compress&cs=tinysrgb&w=400', 'Unidad 1'
WHERE NOT EXISTS (SELECT 1 FROM `bicicletas` WHERE `Modelo` = 'Urban Glass V2' AND `Comentarios` = 'Unidad 1')
UNION ALL
SELECT 'Disponible', CURDATE(), CURDATE(), 'Urban Glass V2', 'https://images.pexels.com/photos/276517/pexels-photo-276517.jpeg?auto=compress&cs=tinysrgb&w=400', 'Unidad 2'
WHERE NOT EXISTS (SELECT 1 FROM `bicicletas` WHERE `Modelo` = 'Urban Glass V2' AND `Comentarios` = 'Unidad 2')
UNION ALL
SELECT 'Disponible', CURDATE(), CURDATE(), 'Urban Glass V2', 'https://images.pexels.com/photos/276517/pexels-photo-276517.jpeg?auto=compress&cs=tinysrgb&w=400', 'Unidad 3'
WHERE NOT EXISTS (SELECT 1 FROM `bicicletas` WHERE `Modelo` = 'Urban Glass V2' AND `Comentarios` = 'Unidad 3')
UNION ALL
SELECT 'Disponible', CURDATE(), CURDATE(), 'E-Volución UTN', 'https://images.pexels.com/photos/1595483/pexels-photo-1595483.jpeg?auto=compress&cs=tinysrgb&w=400', 'Unidad 1'
WHERE NOT EXISTS (SELECT 1 FROM `bicicletas` WHERE `Modelo` = 'E-Volución UTN' AND `Comentarios` = 'Unidad 1')
UNION ALL
SELECT 'Disponible', CURDATE(), CURDATE(), 'E-Volución UTN', 'https://images.pexels.com/photos/1595483/pexels-photo-1595483.jpeg?auto=compress&cs=tinysrgb&w=400', 'Unidad 2'
WHERE NOT EXISTS (SELECT 1 FROM `bicicletas` WHERE `Modelo` = 'E-Volución UTN' AND `Comentarios` = 'Unidad 2')
UNION ALL
SELECT 'Disponible', CURDATE(), CURDATE(), 'E-Volución UTN', 'https://images.pexels.com/photos/1595483/pexels-photo-1595483.jpeg?auto=compress&cs=tinysrgb&w=400', 'Unidad 3'
WHERE NOT EXISTS (SELECT 1 FROM `bicicletas` WHERE `Modelo` = 'E-Volución UTN' AND `Comentarios` = 'Unidad 3')
UNION ALL
SELECT 'Disponible', CURDATE(), CURDATE(), 'Ruta Halcón', 'https://images.pexels.com/photos/5449212/pexels-photo-5449212.jpeg?auto=compress&cs=tinysrgb&w=400', 'Unidad 1'
WHERE NOT EXISTS (SELECT 1 FROM `bicicletas` WHERE `Modelo` = 'Ruta Halcón' AND `Comentarios` = 'Unidad 1')
UNION ALL
SELECT 'Disponible', CURDATE(), CURDATE(), 'Ruta Halcón', 'https://images.pexels.com/photos/5449212/pexels-photo-5449212.jpeg?auto=compress&cs=tinysrgb&w=400', 'Unidad 2'
WHERE NOT EXISTS (SELECT 1 FROM `bicicletas` WHERE `Modelo` = 'Ruta Halcón' AND `Comentarios` = 'Unidad 2')
UNION ALL
SELECT 'Disponible', CURDATE(), CURDATE(), 'Ruta Halcón', 'https://images.pexels.com/photos/5449212/pexels-photo-5449212.jpeg?auto=compress&cs=tinysrgb&w=400', 'Unidad 3'
WHERE NOT EXISTS (SELECT 1 FROM `bicicletas` WHERE `Modelo` = 'Ruta Halcón' AND `Comentarios` = 'Unidad 3');

-- Procedimientos almacenados y triggers fueron movidos a SQL_Programabilidad.sql

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
