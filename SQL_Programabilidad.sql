-- --------------------------------------------------------
-- Script de programabilidad para gabicpro
-- Incluye: procedimientos almacenados y triggers
-- Ejecutar despues de DataBase.sql
-- --------------------------------------------------------

USE `gabicpro`;

DROP PROCEDURE IF EXISTS `sp_validar_id_escolar`;
DROP PROCEDURE IF EXISTS `sp_login_usuario`;
DROP PROCEDURE IF EXISTS `sp_historial_prestamos_usuario`;
DROP PROCEDURE IF EXISTS `sp_catalogo_bicicletas_resumen`;
DROP PROCEDURE IF EXISTS `sp_registrar_usuario_con_id`;
DROP PROCEDURE IF EXISTS `sp_crear_prestamo_por_modelo`;
DELIMITER //
CREATE PROCEDURE `sp_validar_id_escolar`(
  IN p_id_escolar INT,
  OUT p_tipo VARCHAR(20),
  OUT p_encontrado TINYINT,
  OUT p_ya_registrado TINYINT
)
BEGIN
  DECLARE v_usuario_id INT;

  SET p_tipo = NULL;
  SET p_encontrado = 0;
  SET p_ya_registrado = 0;

  IF EXISTS (SELECT 1 FROM `alumnos` WHERE `AlumnoID` = p_id_escolar) THEN
    SELECT `UsuarioID` INTO v_usuario_id
    FROM `alumnos`
    WHERE `AlumnoID` = p_id_escolar
    LIMIT 1;

    SET p_tipo = 'alumno';
    SET p_encontrado = 1;
    SET p_ya_registrado = IF(v_usuario_id IS NULL, 0, 1);
  ELSEIF EXISTS (SELECT 1 FROM `maestros` WHERE `MaestroID` = p_id_escolar) THEN
    SELECT `UsuarioID` INTO v_usuario_id
    FROM `maestros`
    WHERE `MaestroID` = p_id_escolar
    LIMIT 1;

    SET p_tipo = 'maestro';
    SET p_encontrado = 1;
    SET p_ya_registrado = IF(v_usuario_id IS NULL, 0, 1);
  END IF;
END //

CREATE PROCEDURE `sp_login_usuario`(
  IN p_email VARCHAR(120)
)
BEGIN
  SELECT `UsuarioID`, `Nombre`, `Email`, `PasswordHash`, `Activo`
  FROM `usuarios`
  WHERE `Email` = (p_email COLLATE utf8mb4_general_ci)
  LIMIT 1;
END //

CREATE PROCEDURE `sp_historial_prestamos_usuario`(
  IN p_usuario_id INT
)
BEGIN
  SELECT
    p.`PrestamoID`,
    p.`DiaUso`,
    p.`EstadoViaje`,
    b.`Modelo`
  FROM `prestamo` p
  LEFT JOIN `bicicletas` b ON b.`BicicletaID` = p.`BicicletaID`
  WHERE p.`UsuarioID` = p_usuario_id
  ORDER BY p.`DiaUso` DESC, p.`PrestamoID` DESC;
END //

CREATE PROCEDURE `sp_catalogo_bicicletas_resumen`()
BEGIN
  SELECT
    `Modelo`,
    COALESCE(NULLIF(MAX(`ImagenURL`), ''), 'https://images.pexels.com/photos/100582/pexels-photo-100582.jpeg?auto=compress&cs=tinysrgb&w=400') AS `ImagenURL`,
    COUNT(*) AS `TotalUnidades`,
    SUM(CASE WHEN `Estado` = 'Disponible' THEN 1 ELSE 0 END) AS `Disponibles`,
    CASE
      WHEN SUM(CASE WHEN `Estado` = 'Disponible' THEN 1 ELSE 0 END) > 0 THEN 'Disponible'
      ELSE 'Ocupada'
    END AS `EstadoModelo`
  FROM `bicicletas`
  GROUP BY `Modelo`
  ORDER BY `Modelo` ASC;
END //

CREATE PROCEDURE `sp_registrar_usuario_con_id`(
  IN p_id_escolar INT,
  IN p_nombre VARCHAR(80),
  IN p_email VARCHAR(120),
  IN p_password_hash VARCHAR(255)
)
proc: BEGIN
  DECLARE v_tipo VARCHAR(20);
  DECLARE v_encontrado TINYINT;
  DECLARE v_ya_registrado TINYINT;
  DECLARE v_user_id INT;

  CALL `sp_validar_id_escolar`(p_id_escolar, v_tipo, v_encontrado, v_ya_registrado);

  IF v_encontrado <> 1 THEN
    SELECT 'id_no_encontrado' AS `Status`, NULL AS `UsuarioID`;
    LEAVE proc;
  END IF;

  IF v_ya_registrado = 1 THEN
    SELECT 'id_ya_registrado' AS `Status`, NULL AS `UsuarioID`;
    LEAVE proc;
  END IF;

  IF EXISTS (SELECT 1 FROM `usuarios` WHERE `Email` = (p_email COLLATE utf8mb4_general_ci)) THEN
    SELECT 'email_duplicado' AS `Status`, NULL AS `UsuarioID`;
    LEAVE proc;
  END IF;

  START TRANSACTION;

  INSERT INTO `usuarios` (`Nombre`, `Email`, `PasswordHash`, `Activo`, `TiempoUsuario`, `UltimoUso`)
  VALUES (p_nombre, p_email, p_password_hash, 1, CURDATE(), CURDATE());

  SET v_user_id = LAST_INSERT_ID();

  IF v_tipo = 'alumno' THEN
    UPDATE `alumnos`
    SET `UsuarioID` = v_user_id
    WHERE `AlumnoID` = p_id_escolar
      AND `UsuarioID` IS NULL;
  ELSEIF v_tipo = 'maestro' THEN
    UPDATE `maestros`
    SET `UsuarioID` = v_user_id
    WHERE `MaestroID` = p_id_escolar
      AND `UsuarioID` IS NULL;
  END IF;

  IF ROW_COUNT() < 1 THEN
    ROLLBACK;
    SELECT 'id_ya_registrado' AS `Status`, NULL AS `UsuarioID`;
    LEAVE proc;
  END IF;

  COMMIT;

  SELECT 'ok' AS `Status`, v_user_id AS `UsuarioID`;
END //

CREATE PROCEDURE `sp_crear_prestamo_por_modelo`(
  IN p_usuario_id INT,
  IN p_modelo VARCHAR(50)
)
BEGIN
  DECLARE v_bicicleta_id INT;

  SELECT `BicicletaID` INTO v_bicicleta_id
  FROM `bicicletas`
  WHERE `Modelo` = (p_modelo COLLATE utf8mb4_general_ci)
    AND `Estado` = 'Disponible'
  ORDER BY `BicicletaID` ASC
  LIMIT 1;

  IF v_bicicleta_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'no_disponible';
  END IF;

  INSERT INTO `prestamo` (`UsuarioID`, `BicicletaID`, `DiaUso`, `EstadoViaje`)
  VALUES (p_usuario_id, v_bicicleta_id, CURDATE(), 0);

  SELECT 'ok' AS `Status`;
END //
DELIMITER ;

DROP TRIGGER IF EXISTS `tr_prestamo_before_insert_validar`;
DROP TRIGGER IF EXISTS `tr_prestamo_after_insert_ocupar_bici`;
DROP TRIGGER IF EXISTS `tr_prestamo_after_update_liberar_bici`;

DELIMITER //
CREATE TRIGGER `tr_prestamo_before_insert_validar`
BEFORE INSERT ON `prestamo`
FOR EACH ROW
BEGIN
  IF NEW.UsuarioID IS NULL OR NEW.BicicletaID IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'UsuarioID y BicicletaID son obligatorios.';
  END IF;

  IF EXISTS (
    SELECT 1
    FROM `prestamo`
    WHERE `UsuarioID` = NEW.UsuarioID
      AND `EstadoViaje` = 0
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El usuario ya tiene un prestamo activo.';
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM `bicicletas`
    WHERE `BicicletaID` = NEW.BicicletaID
      AND `Estado` = 'Disponible'
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bicicleta no esta disponible.';
  END IF;

  IF NEW.DiaUso IS NULL THEN
    SET NEW.DiaUso = CURDATE();
  END IF;

  IF NEW.EstadoViaje IS NULL THEN
    SET NEW.EstadoViaje = 0;
  END IF;
END //

CREATE TRIGGER `tr_prestamo_after_insert_ocupar_bici`
AFTER INSERT ON `prestamo`
FOR EACH ROW
BEGIN
  UPDATE `bicicletas`
  SET
    `Estado` = 'Ocupada',
    `UltimoUso` = CURDATE()
  WHERE `BicicletaID` = NEW.BicicletaID;
END //

CREATE TRIGGER `tr_prestamo_after_update_liberar_bici`
AFTER UPDATE ON `prestamo`
FOR EACH ROW
BEGIN
  IF OLD.EstadoViaje = 0 AND NEW.EstadoViaje = 1 THEN
    UPDATE `bicicletas`
    SET `Estado` = 'Disponible'
    WHERE `BicicletaID` = NEW.BicicletaID;
  END IF;
END //
DELIMITER ;
