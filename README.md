# Sistema de Control de Asistencia SENA

Aplicación web para gestionar aprendices, fichas, horarios, asistencia por RFID, excusas y reportes. Está desarrollada en PHP con PDO, MySQL/MariaDB, AdminLTE y PhpSpreadsheet.

## Qué resuelve

El sistema separa las funciones por rol:

| Rol | Funciones principales |
| --- | --- |
| Administrador | Dashboard, CRUD de aprendices, instructores, fichas y horarios; importación XLSX; consulta de asistencias; excusas y reportes. |
| Instructor | Dashboard de sus fichas, apertura y cierre de jornadas, gestión de aprendices, asignación RFID, consulta de asistencias, revisión de excusas y reportes. |
| Aprendiz | Dashboard personal, historial, perfil, carga de excusas y descarga de sus archivos. |

## Flujo de negocio

1. El administrador crea el instructor, la ficha y sus horarios.
2. El instructor registra aprendices manualmente o mediante un archivo Excel.
3. Los aprendices importados reciben un RFID temporal (`RFID001`, por ejemplo).
4. El instructor asigna el RFID físico una sola vez desde su módulo de aprendices.
5. El instructor abre una jornada para una ficha según el horario del día.
6. El lector RFID registra la entrada o salida del aprendiz.
7. Al cerrar la jornada, el sistema genera inasistencias para quienes no registraron entrada.
8. El aprendiz puede subir una excusa sobre una inasistencia y el instructor de la ficha decide si la aprueba o rechaza.

## Tecnologías

- PHP 8.1 o superior
- MySQL/MariaDB mediante PDO
- Apache y XAMPP
- AdminLTE, Bootstrap, jQuery, DataTables y Chart.js
- PhpSpreadsheet para lectura y creación de plantillas XLSX

## Instalación local

### 1. Requisitos

Instala XAMPP con Apache, MySQL/MariaDB y PHP. En `php.ini` deben estar habilitadas las extensiones `pdo_mysql`, `mbstring`, `zip`, `xml` y `gd` para trabajar con base de datos y archivos XLSX.

### 2. Ubicación del proyecto

Clona o copia el repositorio dentro de `htdocs`:

```text
C:\xampp\htdocs\control_asistencia
```

Inicia Apache y MySQL desde el panel de XAMPP. Después abre:

```text
http://localhost/control_asistencia/
```

### 3. Dependencia de Excel

El proyecto utiliza PhpSpreadsheet. Si la carpeta `vendor/` no está disponible, ejecuta en la raíz del proyecto:

```bash
composer install
```

El ejecutable `composer.phar` no se conserva en el repositorio. Para una entrega más limpia, `vendor/` debe reconstruirse con Composer en cada entorno en lugar de versionarse.

### 4. Configuración de conexión

Revisa [config/conexion.php](config/conexion.php). Para XAMPP local se usa por defecto:

```php
host: localhost
base: control_asistencia
usuario: root
contraseña: vacía
```

No uses esas credenciales en producción.

### 5. Base de datos

La base se llama `control_asistencia`. Debe importarse mediante phpMyAdmin o la consola de MySQL antes de iniciar el sistema.

La estructura actual incluye, como mínimo, las tablas:

```text
rol, usuario, ficha, aprendiz, horario, sesiones_jornada, asistencia y excusa
```

También requiere los procedimientos almacenados de asistencia y excusas que tenga el respaldo de la base de datos del equipo.

> Importante: antes de una entrega, exporten desde phpMyAdmin un respaldo completo con estructura, datos y procedimientos, guárdenlo como `database/control_asistencia.sql` y actualicen este README con su nombre definitivo. El archivo `alter_horario.sql` es solo una alteración parcial, no reconstruye la aplicación completa.

### 6. Usuarios de prueba

Los usuarios se administran directamente desde la base de datos. No se versionan contraseñas en este documento.

- Los aprendices creados/importados reciben inicialmente como contraseña su número de documento.
- Para demostrar los tres roles, creen o conserven una cuenta de administrador, una de instructor con ficha asignada y una de aprendiz vinculado a esa ficha.

## Estructura del proyecto

```text
config/       Conexión PDO.
controllers/  Operaciones POST, importaciones, RFID y jornadas.
includes/     Autenticación, constantes y lectores/validadores XLSX.
models/       Lógica reutilizable de aprendices.
views/        Vistas por rol y componentes AdminLTE.
assets/       CSS, JavaScript, imágenes y librerías de interfaz.
uploads/      Adjuntos de excusas; no debe contener archivos reales en Git.
```

## Módulos relevantes

### Administración

- Gestión de aprendices, instructores, fichas y horarios.
- Importación de aprendices y horarios desde Excel con previsualización.
- Consulta de asistencias mediante filtros.
- Consulta y descarga de excusas.
- Reportes filtrados y exportación CSV compatible con Excel.

### Instructor

- Consulta de fichas y aprendices asignados.
- Registro manual o masivo de aprendices.
- Asignación permanente de RFID físico a aprendices importados.
- Apertura/cierre de jornadas y generación de inasistencias.
- Consulta de asistencias, revisión de excusas y reportes CSV.

### Aprendiz

- Consulta de información personal e historial.
- Carga de excusas en formatos permitidos.
- Consulta y descarga de sus adjuntos.

## Seguridad aplicada

- Contraseñas con `password_hash()` y `password_verify()`.
- Sesiones por rol y protección de vistas con `exigirRol()`.
- Tokens CSRF en formularios sensibles.
- Consultas preparadas PDO.
- Descarga de excusas validada por rol y ubicación física del archivo.
- Cabeceras anti-caché al cerrar sesión y en vistas protegidas.

## Demostración sugerida: menos de 30 minutos

| Tiempo | Demostración | Qué explicar |
| ---: | --- | --- |
| 0-3 min | Login y roles | Un mismo sistema muestra funciones distintas según administrador, instructor o aprendiz. |
| 3-8 min | Administrador | Crear o mostrar instructor, ficha, horario y aprendiz; explicar que la ficha relaciona instructor y aprendices. |
| 8-13 min | Importación | Mostrar plantilla XLSX, validación/previsualización y control de duplicados. |
| 13-19 min | Instructor | Abrir una jornada de su ficha y explicar el límite de puntualidad. |
| 19-22 min | RFID | Registrar una entrada con lector o código de prueba y mostrar el resultado en asistencias. |
| 22-24 min | Cierre | Cerrar jornada y explicar la generación automática de inasistencias. |
| 24-27 min | Aprendiz y excusas | Mostrar historial, subida de adjunto y revisión exclusivamente por el instructor. |
| 27-30 min | Reportes | Aplicar filtros y descargar el CSV o guardar la vista de impresión como PDF. |

## Guion breve para explicar el modelo de datos

- `usuario` guarda las credenciales y el rol.
- `ficha` pertenece a un instructor.
- `aprendiz` vincula un usuario con una ficha y un código RFID.
- `horario` define los bloques semanales de una ficha.
- `sesiones_jornada` registra cuándo un instructor abre y cierra una jornada concreta.
- `asistencia` registra entradas, salidas, retardos e inasistencias.
- `excusa` se vincula a una asistencia y conserva el archivo, estado y revisión.

## Verificaciones antes de entregar

1. Importar una base de datos limpia y comprobar que inicia sin errores.
2. Crear instructor → ficha → horario → aprendiz.
3. Probar entrada, salida y cierre de jornada.
4. Comprobar que un instructor no accede a fichas ajenas.
5. Probar carga, descarga y revisión de una excusa.
6. Probar filtros y exportaciones de reportes.
7. Ejecutar la validación PHP básica:

```bash
php -l controllers/login.php
php -l controllers/abrir_jornada.php
php -l controllers/cerrar_jornada.php
php -l controllers/registrar_asistencia_rfid.php
```

## Pendientes técnicos conocidos

- Crear y versionar una migración completa de la base de datos.
- Revisar la política de una asistencia por aprendiz y fecha frente a múltiples sesiones en un mismo día.
- Proteger el endpoint de registro RFID según el modo de despliegue del lector.
- Unificar los nombres de días con tilde (`Miércoles`, `Sábado`) en jornadas y horarios.
