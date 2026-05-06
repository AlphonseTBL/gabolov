# GabicPro — Sistema de Préstamo de Bicicletas UTN

Aplicación web para la gestión del préstamo de bicicletas en la **Universidad Tecnológica de Nogales (UTN Nogales)**. Permite a alumnos y maestros registrarse con su ID escolar, reservar bicicletas y consultar su historial de préstamos. Incluye un panel de administración completo para gestionar usuarios, flota y reportes.

---

## Tabla de contenidos

- [Características](#características)
- [Tecnologías](#tecnologías)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Base de datos](#base-de-datos)
- [Instalación](#instalación)
- [Variables de entorno](#variables-de-entorno)
- [Cuenta de administrador por defecto](#cuenta-de-administrador-por-defecto)
- [Flujos principales](#flujos-principales)
- [Panel de administración](#panel-de-administración)

---

## Características

- **Registro de usuarios** validado contra el padrón de alumnos y maestros.
- **Inicio de sesión** seguro con contraseña hasheada (`password_hash`).
- **Catálogo de bicicletas** con disponibilidad en tiempo real.
- **Reserva y devolución** de bicicletas con un clic.
- **Historial de préstamos** por usuario (modal de historial).
- **Panel de administración** con módulos independientes:
  - Dashboard con métricas generales.
  - Gestión de usuarios (crear administradores, listar, activar/desactivar).
  - Gestión de alumnos y maestros (alta, modificación, baja, carga masiva).
  - Gestión de bicicletas (crear, editar, eliminar).
  - Historial de viajes completo.
  - Reportes con gráficas, filtro por mes y exportación a PDF.
- **Registro de diagnóstico** en `logs/runtime.log` para depuración.

---

## Tecnologías

| Capa | Tecnología |
|---|---|
| Backend | PHP 8+ con MySQLi |
| Base de datos | MySQL 8 |
| Frontend | Bootstrap 5.3, Font Awesome 6, Montserrat |
| Animaciones | AOS 2.3, Particles.js 2 |
| Gráficas | Chart.js 4.4 |
| Exportación PDF | jsPDF 2.5, html2canvas 1.4 |

---

## Estructura del proyecto

```
gabolov/
├── index.php                  # Punto de entrada único: lógica PHP y enrutamiento
├── diag_register.php          # Script de diagnóstico para verificar la conexión y el SP de registro
│
├── config/
│   └── database.php           # Función createDatabaseConnection() — lee variables de entorno
│
├── templates/
│   └── page.php               # Plantilla HTML base (head, includes de componentes, scripts)
│
├── components/
│   ├── navbar.php             # Barra de navegación con estado de sesión
│   ├── hero.php               # Sección hero con partículas
│   ├── catalog.php            # Catálogo de bicicletas y modal de reserva
│   ├── admin_panel.php        # Panel de administración completo
│   ├── login_modal.php        # Modal de inicio de sesión
│   ├── register_modal.php     # Modal de registro con validación de ID escolar
│   ├── history_modal.php      # Modal de historial de préstamos del usuario
│   ├── footer.php             # Pie de página
│   └── loader.php             # Pantalla de carga inicial
│
├── assets/
│   ├── css/styles.css         # Estilos personalizados
│   └── js/main.js             # Lógica JavaScript del cliente
│
├── logs/
│   └── runtime.log            # Log JSON de errores en tiempo de ejecución (generado automáticamente)
│
├── DataBase.sql               # Script DDL + datos base (tablas, inserts iniciales)
└── SQL_Programabilidad.sql    # Procedimientos almacenados y triggers
```

---

## Base de datos

El nombre de la base de datos es **`gabicpro`**. Se compone de cuatro tablas principales:

| Tabla | Descripción |
|---|---|
| `usuarios` | Cuentas de acceso al sistema. Rol: `miembro` o `administrador`. |
| `alumnos` | Padrón de alumnos con ID escolar y carrera. Vinculado opcionalmente a `usuarios`. |
| `maestros` | Padrón de maestros con ID escolar y departamento. Vinculado opcionalmente a `usuarios`. |
| `bicicletas` | Flota de bicicletas con modelo, imagen, estado y comentarios. |
| `prestamo` | Registro de préstamos activos e históricos. |

### Procedimientos almacenados principales

| Procedimiento | Función |
|---|---|
| `sp_validar_id_escolar` | Verifica si un ID pertenece a alumno o maestro y si ya tiene cuenta. |
| `sp_registrar_usuario_con_id` | Crea una cuenta nueva vinculando al padrón. |
| `sp_login_usuario` | Obtiene los datos del usuario por email para verificar credenciales. |
| `sp_catalogo_bicicletas_resumen` | Devuelve el catálogo agrupado por modelo con disponibilidad. |
| `sp_crear_prestamo_por_modelo` | Asigna una bicicleta disponible del modelo solicitado al usuario. |
| `sp_prestamo_activo_usuario` | Devuelve el préstamo activo del usuario, si existe. |
| `sp_finalizar_viaje_usuario` | Marca el viaje activo como finalizado y libera la bicicleta. |
| `sp_historial_prestamos_usuario` | Lista el historial de préstamos de un usuario. |
| `sp_resumen_admin_dashboard` | Métricas globales para el dashboard del administrador. |
| `sp_admin_historial_viajes` | Historial completo de todos los viajes para administradores. |

---

## Instalación

### Requisitos previos

- PHP 8.0 o superior con extensión `mysqli` habilitada.
- MySQL 8.0 o superior.
- Servidor web (Apache, Nginx o PHP built-in server).

### Pasos

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/AlphonseTBL/gabolov.git
   cd gabolov
   ```

2. **Crear la base de datos:**
   ```bash
   mysql -u root -p < DataBase.sql
   mysql -u root -p < SQL_Programabilidad.sql
   ```

3. **Configurar las variables de entorno** (ver sección siguiente).

4. **Iniciar el servidor** (desarrollo):
   ```bash
   php -S localhost:8000
   ```

5. Abrir `http://localhost:8000` en el navegador.

---

## Variables de entorno

La conexión a la base de datos se configura mediante variables de entorno leídas en `config/database.php`. Si no se definen, se usan los valores por defecto.

| Variable | Valor por defecto | Descripción |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | Host del servidor MySQL |
| `DB_USER` | `root` | Usuario de MySQL |
| `DB_PASS` | *(vacío)* | Contraseña de MySQL |
| `DB_NAME` | `gabicpro` | Nombre de la base de datos |
| `DB_PORT` | `3306` | Puerto de MySQL |

Ejemplo en Linux/macOS:
```bash
export DB_HOST=127.0.0.1
export DB_USER=myuser
export DB_PASS=mypassword
export DB_NAME=gabicpro
```

---

## Cuenta de administrador por defecto

El script `DataBase.sql` crea un usuario administrador inicial:

| Campo | Valor |
|---|---|
| Email | `admin@utnogales.edu.mx` |
| Contraseña | `admin` |
| Rol | `administrador` |

> **Importante:** Cambia la contraseña del administrador inmediatamente después de la primera instalación.

---

## Flujos principales

### Registro de usuario

1. El usuario introduce su **ID escolar** (alumno o maestro).
2. El sistema valida el ID contra el padrón (`sp_validar_id_escolar`).
3. Si el ID existe y no tiene cuenta vinculada, el usuario completa nombre, email y contraseña.
4. Se crea la cuenta con `sp_registrar_usuario_con_id` y se inicia sesión automáticamente.

### Inicio de sesión

1. El usuario introduce email y contraseña.
2. Se consulta el usuario con `sp_login_usuario`.
3. Se verifica el hash de la contraseña con `password_verify`.
4. Si es válido, los datos del usuario se almacenan en sesión PHP (30 días).

### Reserva de bicicleta

1. El catálogo muestra los modelos disponibles agrupados.
2. El usuario selecciona un modelo y confirma la reserva en el modal.
3. Se ejecuta `sp_crear_prestamo_por_modelo`; si el usuario ya tiene un préstamo activo, se muestra un aviso.

### Devolución

1. Si el usuario tiene un préstamo activo, el navbar muestra la opción **Devolver**.
2. Al confirmar, se llama a `sp_finalizar_viaje_usuario`, que libera la bicicleta y cierra el préstamo.

---

## Panel de administración

Accesible solo para usuarios con rol `administrador` mediante `?action=admin`. Contiene los siguientes módulos:

| Módulo | Descripción |
|---|---|
| **Dashboard** | Totales de usuarios, miembros, administradores, préstamos activos y disponibilidad de flota. |
| **Usuarios** | Lista de todos los usuarios. Permite crear nuevos administradores. |
| **Alumnos** | Alta, edición, baja individual y carga masiva (formato `ID\|Nombre\|Carrera`). |
| **Maestros** | Alta, edición, baja individual y carga masiva (formato `ID\|Nombre\|Departamento`). |
| **Bicicletas** | Alta, edición de modelo/estado/imagen/comentarios y baja de unidades. |
| **Viajes** | Historial completo de todos los préstamos con filtro por mes. |
| **Reportes** | Métricas de uso, top modelos, top usuarios, porcentaje de flotilla. Exportación a PDF. |
