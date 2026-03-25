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
DROP PROCEDURE IF EXISTS `sp_resumen_admin_dashboard`;
DROP PROCEDURE IF EXISTS `sp_admin_list_alumnos`;
DROP PROCEDURE IF EXISTS `sp_admin_list_maestros`;
DROP PROCEDURE IF EXISTS `sp_admin_upsert_alumno`;
DROP PROCEDURE IF EXISTS `sp_admin_upsert_maestro`;
DROP PROCEDURE IF EXISTS `sp_admin_delete_alumno`;
DROP PROCEDURE IF EXISTS `sp_admin_delete_maestro`;
DROP PROCEDURE IF EXISTS `sp_admin_list_usuarios`;
DROP PROCEDURE IF EXISTS `sp_admin_crear_usuario_admin`;
DROP PROCEDURE IF EXISTS `sp_admin_list_bicicletas`;
DROP PROCEDURE IF EXISTS `sp_admin_create_bicicleta`;
DROP PROCEDURE IF EXISTS `sp_admin_update_bicicleta`;
DROP PROCEDURE IF EXISTS `sp_admin_delete_bicicleta`;
DROP PROCEDURE IF EXISTS `sp_admin_historial_viajes`;
DROP PROCEDURE IF EXISTS `sp_prestamo_activo_usuario`;
DROP PROCEDURE IF EXISTS `sp_finalizar_viaje_usuario`;
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
  SELECT `UsuarioID`, `Nombre`, `Email`, `PasswordHash`, `Activo`, `Rol`
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

  INSERT INTO `usuarios` (`Nombre`, `Email`, `PasswordHash`, `Rol`, `Activo`, `TiempoUsuario`, `UltimoUso`)
  VALUES (p_nombre, p_email, p_password_hash, IF(v_tipo = 'maestro', 'administrador', 'miembro'), 1, CURDATE(), CURDATE());

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

CREATE PROCEDURE `sp_resumen_admin_dashboard`()
BEGIN
  SELECT
    (SELECT COUNT(*) FROM `usuarios`) AS `TotalUsuarios`,
    (SELECT COUNT(*) FROM `usuarios` WHERE `Rol` = 'administrador') AS `TotalAdministradores`,
    (SELECT COUNT(*) FROM `usuarios` WHERE `Rol` = 'miembro') AS `TotalMiembros`,
    (SELECT COUNT(*) FROM `usuarios` WHERE `Activo` = 1) AS `UsuariosActivos`,
    (SELECT COUNT(*) FROM `prestamo` WHERE `EstadoViaje` = 0) AS `PrestamosActivos`,
    (SELECT COUNT(*) FROM `bicicletas` WHERE `Estado` = 'Disponible') AS `BicicletasDisponibles`,
    (SELECT COUNT(*) FROM `bicicletas`) AS `BicicletasTotales`;
END //

CREATE PROCEDURE `sp_admin_list_alumnos`()
BEGIN
  SELECT `AlumnoID`, `Nombre`, `Carrera`, `UsuarioID`
  FROM `alumnos`
  ORDER BY `AlumnoID` ASC;
END //

CREATE PROCEDURE `sp_admin_list_maestros`()
BEGIN
  SELECT `MaestroID`, `Nombre`, `Departamento`, `UsuarioID`
  FROM `maestros`
  ORDER BY `MaestroID` ASC;
END //

CREATE PROCEDURE `sp_admin_upsert_alumno`(
  IN p_alumno_id INT,
  IN p_nombre VARCHAR(50),
  IN p_carrera VARCHAR(50)
)
BEGIN
  IF EXISTS (SELECT 1 FROM `alumnos` WHERE `AlumnoID` = p_alumno_id) THEN
    UPDATE `alumnos`
    SET
      `Nombre` = p_nombre,
      `Carrera` = p_carrera
    WHERE `AlumnoID` = p_alumno_id;

    SELECT 'actualizado' AS `Status`;
  ELSE
    INSERT INTO `alumnos` (`AlumnoID`, `Nombre`, `Carrera`, `UsuarioID`)
    VALUES (p_alumno_id, p_nombre, p_carrera, NULL);

    SELECT 'insertado' AS `Status`;
  END IF;
END //

CREATE PROCEDURE `sp_admin_upsert_maestro`(
  IN p_maestro_id INT,
  IN p_nombre VARCHAR(60),
  IN p_departamento VARCHAR(50)
)
BEGIN
  IF EXISTS (SELECT 1 FROM `maestros` WHERE `MaestroID` = p_maestro_id) THEN
    UPDATE `maestros`
    SET
      `Nombre` = p_nombre,
      `Departamento` = p_departamento
    WHERE `MaestroID` = p_maestro_id;

    SELECT 'actualizado' AS `Status`;
  ELSE
    INSERT INTO `maestros` (`MaestroID`, `Nombre`, `Departamento`, `UsuarioID`)
    VALUES (p_maestro_id, p_nombre, p_departamento, NULL);

    SELECT 'insertado' AS `Status`;
  END IF;
END //

CREATE PROCEDURE `sp_admin_delete_alumno`(
  IN p_alumno_id INT
)
BEGIN
  DECLARE v_usuario_id INT;

  SELECT `UsuarioID` INTO v_usuario_id
  FROM `alumnos`
  WHERE `AlumnoID` = p_alumno_id
  LIMIT 1;

  IF v_usuario_id IS NULL THEN
    DELETE FROM `alumnos`
    WHERE `AlumnoID` = p_alumno_id;

    IF ROW_COUNT() > 0 THEN
      SELECT 'eliminado' AS `Status`;
    ELSE
      SELECT 'no_encontrado' AS `Status`;
    END IF;
  ELSE
    SELECT 'vinculado_usuario' AS `Status`;
  END IF;
END //

CREATE PROCEDURE `sp_admin_delete_maestro`(
  IN p_maestro_id INT
)
BEGIN
  DECLARE v_usuario_id INT;

  SELECT `UsuarioID` INTO v_usuario_id
  FROM `maestros`
  WHERE `MaestroID` = p_maestro_id
  LIMIT 1;

  IF v_usuario_id IS NULL THEN
    DELETE FROM `maestros`
    WHERE `MaestroID` = p_maestro_id;

    IF ROW_COUNT() > 0 THEN
      SELECT 'eliminado' AS `Status`;
    ELSE
      SELECT 'no_encontrado' AS `Status`;
    END IF;
  ELSE
    SELECT 'vinculado_usuario' AS `Status`;
  END IF;
END //

CREATE PROCEDURE `sp_admin_list_usuarios`()
BEGIN
  SELECT
    `UsuarioID`,
    `Nombre`,
    `Email`,
    `Rol`,
    `Activo`,
    `UltimoUso`
  FROM `usuarios`
  ORDER BY `UsuarioID` ASC;
END //

CREATE PROCEDURE `sp_admin_crear_usuario_admin`(
  IN p_nombre VARCHAR(80),
  IN p_email VARCHAR(120),
  IN p_password_hash VARCHAR(255)
)
BEGIN
  IF EXISTS (SELECT 1 FROM `usuarios` WHERE `Email` = (p_email COLLATE utf8mb4_general_ci)) THEN
    SELECT 'email_duplicado' AS `Status`;
  ELSE
    INSERT INTO `usuarios` (`Nombre`, `Email`, `PasswordHash`, `Rol`, `Activo`, `TiempoUsuario`, `UltimoUso`)
    VALUES (p_nombre, p_email, p_password_hash, 'administrador', 1, CURDATE(), CURDATE());

    SELECT 'ok' AS `Status`, LAST_INSERT_ID() AS `UsuarioID`;
  END IF;
END //

CREATE PROCEDURE `sp_admin_list_bicicletas`()
BEGIN
  SELECT
    `BicicletaID`,
    `Modelo`,
    `Estado`,
    `ImagenURL`,
    `Comentarios`,
    `UltimoUso`
  FROM `bicicletas`
  ORDER BY `BicicletaID` ASC;
END //

CREATE PROCEDURE `sp_admin_create_bicicleta`(
  IN p_modelo VARCHAR(50),
  IN p_imagen_url VARCHAR(500),
  IN p_comentarios VARCHAR(255)
)
BEGIN
  INSERT INTO `bicicletas` (`Estado`, `TiempoUso`, `UltimoUso`, `Modelo`, `ImagenURL`, `Comentarios`)
  VALUES ('Disponible', CURDATE(), CURDATE(), p_modelo, p_imagen_url, p_comentarios);

  SELECT 'insertado' AS `Status`, LAST_INSERT_ID() AS `BicicletaID`;
END //

CREATE PROCEDURE `sp_admin_update_bicicleta`(
  IN p_bicicleta_id INT,
  IN p_modelo VARCHAR(50),
  IN p_estado VARCHAR(20),
  IN p_imagen_url VARCHAR(500),
  IN p_comentarios VARCHAR(255)
)
BEGIN
  IF EXISTS (SELECT 1 FROM `bicicletas` WHERE `BicicletaID` = p_bicicleta_id) THEN
    UPDATE `bicicletas`
    SET
      `Modelo` = p_modelo,
      `Estado` = CASE
        WHEN p_estado IN ('Disponible', 'Ocupada', 'En Mantenimiento') THEN p_estado
        ELSE `Estado`
      END,
      `ImagenURL` = p_imagen_url,
      `Comentarios` = p_comentarios
    WHERE `BicicletaID` = p_bicicleta_id;

    SELECT 'actualizado' AS `Status`;
  ELSE
    SELECT 'no_encontrado' AS `Status`;
  END IF;
END //

CREATE PROCEDURE `sp_admin_delete_bicicleta`(
  IN p_bicicleta_id INT
)
BEGIN
  IF EXISTS (SELECT 1 FROM `prestamo` WHERE `BicicletaID` = p_bicicleta_id) THEN
    SELECT 'con_historial' AS `Status`;
  ELSE
    DELETE FROM `bicicletas`
    WHERE `BicicletaID` = p_bicicleta_id;

    IF ROW_COUNT() > 0 THEN
      SELECT 'eliminado' AS `Status`;
    ELSE
      SELECT 'no_encontrado' AS `Status`;
    END IF;
  END IF;
END //

CREATE PROCEDURE `sp_admin_historial_viajes`()
BEGIN
  SELECT
    p.`PrestamoID`,
    p.`UsuarioID`,
    COALESCE(u.`Nombre`, 'Usuario no encontrado') AS `NombreUsuario`,
    b.`BicicletaID`,
    COALESCE(b.`Modelo`, 'Sin modelo') AS `Modelo`,
    p.`DiaUso`,
    p.`EstadoViaje`
  FROM `prestamo` p
  LEFT JOIN `usuarios` u ON u.`UsuarioID` = p.`UsuarioID`
  LEFT JOIN `bicicletas` b ON b.`BicicletaID` = p.`BicicletaID`
  ORDER BY p.`PrestamoID` DESC;
END //

CREATE PROCEDURE `sp_prestamo_activo_usuario`(
  IN p_usuario_id INT
)
BEGIN
  SELECT
    p.`PrestamoID`,
    p.`BicicletaID`,
    b.`Modelo`,
    p.`DiaUso`
  FROM `prestamo` p
  LEFT JOIN `bicicletas` b ON b.`BicicletaID` = p.`BicicletaID`
  WHERE p.`UsuarioID` = p_usuario_id
    AND p.`EstadoViaje` = 0
  ORDER BY p.`PrestamoID` DESC
  LIMIT 1;
END //

CREATE PROCEDURE `sp_finalizar_viaje_usuario`(
  IN p_usuario_id INT
)
BEGIN
  DECLARE v_prestamo_id INT;

  SELECT `PrestamoID` INTO v_prestamo_id
  FROM `prestamo`
  WHERE `UsuarioID` = p_usuario_id
    AND `EstadoViaje` = 0
  ORDER BY `PrestamoID` DESC
  LIMIT 1;

  IF v_prestamo_id IS NULL THEN
    SELECT 'sin_viaje_activo' AS `Status`;
  ELSE
    UPDATE `prestamo`
    SET `EstadoViaje` = 1
    WHERE `PrestamoID` = v_prestamo_id;

    SELECT 'finalizado' AS `Status`, v_prestamo_id AS `PrestamoID`;
  END IF;
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
