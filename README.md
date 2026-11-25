# 🎓 Sistema de Control de Asistencia FISEI - Web
### Facultad de Ingeniería en Sistemas, Electrónica e Industrial &bull; Universidad Técnica de Ambato (UTA)

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Supabase%20Cloud-336791?logo=postgresql&logoColor=white)](https://supabase.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3.3-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?logo=docker&logoColor=white)](https://www.docker.com/)
[![Render](https://img.shields.io/badge/Deploy-Render%20PaaS-46E3B7?logo=render&logoColor=black)](https://render.com/)
[![GoDaddy](https://img.shields.io/badge/DNS%20%26%20Host-GoDaddy-1BDBDB?logo=godaddy&logoColor=black)](https://www.godaddy.com/)

Plataforma web institucional desarrollada bajo arquitectura **MVC Pura en PHP 8** (sin frameworks pesados), diseñada para gestionar la matriculación de alumnos, el catálogo de asignaturas por docente y el **control de asistencia biométrico/físico mediante lectores RFID USB en tiempo real**.

---

## 📑 Tabla de Contenidos
1. [Arquitectura de Software (MVC Puro en PHP 8)](#-1-arquitectura-de-software-mvc-puro-en-php-8)
2. [Despliegue en la Nube: Render + GoDaddy + Supabase](#-2-despliegue-en-la-nube-render--godaddy--supabase)
3. [¿Por qué Supabase (Cloud PostgreSQL) y NO XAMPP Local?](#-3-por-qu-supabase-cloud-postgresql-y-no-xampp-local)
4. [Estructura del Proyecto](#-4-estructura-del-proyecto)
5. [Seguridad y Control de Acceso por Roles (RBAC)](#-5-seguridad-y-control-de-acceso-por-roles-rbac)
6. [Instalación y Configuración Local](#-6-instalacin-y-configuracin-local)
7. [Datos Semilla y Llaveros Físicos](#-7-datos-semilla-y-llaveros-fscos-de-laboratorio)
8. [Equipo de Desarrollo](#-8-equipo-de-desarrollo)

---

## 🏛️ 1. Arquitectura de Software (MVC Puro en PHP 8)

El sistema fue diseñado desde cero implementando el patrón **Modelo-Vista-Controlador (MVC) puro**, logrando una separación estricta de responsabilidades, alta mantenibilidad y desacoplamiento sin sobrecargar el servidor con frameworks voluminosos.

```
                  ┌──────────────────────────────────────────────┐
                  │                 CLIENTE                      │
                  │   Navegador Web + Lector RFID USB (Enter)    │
                  └───────────────▲───────────────┬──────────────┘
                                  │               │
                     Petición HTTP│               │ Fetch AJAX (JSON)
                                  │               │
                                  ▼               ▼
┌───────────────────────────────────────┐   ┌─────────────────────────────────────────┐
│     FRONT CONTROLLER: index.php       │   │           CONTROLADORES AJAX            │
│   Inicia sesión segura + Sesión check │   │  controllers/*AjaxController.php        │
│       controllersMvc->gettemplate()   │   │  JSON response {status, mensaje, data}  │
└───────────────────┬───────────────────┘   └────────────────────┬────────────────────┘
                    │                                            │
                    ▼                                            ▼
┌───────────────────────────────────────┐   ┌─────────────────────────────────────────┐
│        CONTROLADOR PRINCIPAL          │   │           MODELOS DE NEGOCIO            │
│       controllers/controller.php      │──►│            models/*Model.php            │
│  - Middleware de Sesión y Rutas       │   │  - Validaciones y reglas de negocio     │
│  - Control de Roles (ADMIN / DOCENTE) │   │  - Manejo de Entidades (entities/*.php) │
│  - Whitelist de Vistas (Anti-LFI)     │   └────────────────────┬────────────────────┘
└───────────────────┬───────────────────┘                        │
                    │                                            ▼
                    │                       ┌─────────────────────────────────────────┐
                    │                       │        CAPA DE DATOS (dao/*DAO.php)     │
                    │                       │  - Consultas preparadas PDO (SQL)       │
                    │                       │  - Transacciones atómicas (BEGIN/COMMIT)│
                    │                       │  - Mapeo a Entidades (entities/*.php)   │
                    │                       └────────────────────┬────────────────────┘
                    │                                            │
                    ▼                                            ▼
┌───────────────────────────────────────┐   ┌─────────────────────────────────────────┐
│         VISTA BASE (Plantilla)        │   │          BASE DE DATOS NUBE             │
│           views/template.php          │   │           Supabase PostgreSQL           │
│  - Navbar dinámica + SweetAlert2      │   │  - Puerto 6543 (Transaction Pooler)    │
│  - Renderizado dinámico de módulos    │   │  - Conexión cifrada (sslmode=require)   │
└───────────────────────────────────────┘   └─────────────────────────────────────────┘
```

### Componentes de la Arquitectura:

1. **Front Controller (`index.php`):**
   - Es el único punto de entrada de la aplicación para navegación.
   - Inicializa el ciclo de vida de la sesión PHP de forma segura (`session_start()`).
   - Carga el controlador principal e invoca la plantilla institucional.

2. **Controlador Central y Middleware (`controllers/controller.php`):**
   - **Enrutamiento Seguro:** Lee el parámetro `$_GET['action']` y consulta a `models/model.php` para resolver la vista solicitada.
   - **Prevención de Local File Inclusion (LFI):** Valida las rutas contra una lista blanca cerrada (`login`, `inicio`, `materias`, `matriculacion`, `asistencia`, `reportes`, `salir`).
   - **Middleware de Sesión:**
     - Si un usuario no autenticado intenta ingresar a cualquier módulo privado, es redirigido de inmediato a `?action=login`.
     - Si un usuario con rol `DOCENTE` intenta acceder por URL directa a módulos administrativos como `materias` o `matriculacion`, el middleware bloquea el acceso y lo envía a `inicio` con una alerta visual de acceso denegado.

3. **Controladores de API / AJAX (`controllers/*AjaxController.php`):**
   - Responden de forma exclusiva en formato `application/json; charset=UTF-8`.
   - No generan código HTML; procesan peticiones asíncronas vía `fetch()` de JavaScript tanto en formato `FormData` como en cuerpo JSON (`php://input`).
   - Formato estándar de respuesta:
     ```json
     {
       "status": "ok" | "error" | "not_found",
       "mensaje": "Descripción de la operación",
       "data": { ... }
     }
     ```

4. **Modelos de Dominio, Entidades y Capa DAO (`models/`, `entities/`, `dao/`):**
   - **Entidades (`entities/`):** Clases orientadas a objetos (`Usuario`, `Estudiante`, `Materia`, `Asistencia`) con propiedades tipadas, getters/setters y métodos `fromArray()` / `toArray()`.
   - **Data Access Objects (`dao/`):** Concentran el 100% de las sentencias SQL y operaciones **PDO con sentencias preparadas**, transacciones atómicas (`beginTransaction()`, `commit()`, `rollBack()`) y preservación estricta de los UID RFID como `string` (`'0006047325'`).
   - **Modelos (`models/`):** Contienen las reglas de negocio, validaciones (cédula de 10 dígitos, porcentajes FISEI) y orquestación, delegando las consultas SQL a la capa DAO.

5. **Vistas Modulares y Frontend Desacoplado (`views/` y `assets/`):**
   - `views/template.php` provee el layout base institucional (paleta de la FISEI-UTA, Bootstrap 5.3, Bootstrap Icons y CDN de SweetAlert2).
   - Los módulos dentro de `views/modules/` no recargan la página completa para operar; interactúan de forma reactiva con el backend usando la **Fetch API**, actualizando tablas, contadores e inputs en tiempo real.

---

## ☁️ 2. Despliegue en la Nube: Render + GoDaddy + Supabase

Para garantizar alta disponibilidad, despliegue continuo y acceso global para los docentes, el sistema se desacopló en una infraestructura en la nube de tres niveles:

```
[ Usuario Final ] 
       │
       ▼
 [ GoDaddy ] ───(Gestión de Dominio DNS / Hosting)
       │
       ▼
  [ Render ] ────(Web Service PaaS: Contenedor Docker PHP 8.2 + Apache)
       │
       ▼ (SSL Port 6543 - Transaction Pooler)
[ Supabase ] ───(Base de Datos PostgreSQL Serverless en la Nube)
```

### A. Render (PaaS - Servidor de Aplicaciones)
- El código se aloja en un repositorio GitHub y se conecta a **Render** como un *Web Service*.
- El despliegue está automatizado mediante el archivo [Dockerfile](file:///c:/xampp/htdocs/SistemaAsistenciaFISEI-Web/Dockerfile):
  ```dockerfile
  FROM php:8.2-apache
  RUN apt-get update && apt-get install -y libpq-dev \
      && docker-php-ext-install pdo pdo_pgsql pgsql
  RUN a2enmod rewrite
  COPY . /var/www/html/
  RUN chown -R www-data:www-data /var/www/html/
  EXPOSE 80
  ```
- **Ventaja:** Cada `git push origin main` compila e instala las extensiones de PostgreSQL y despliega la última versión estable sin intervención manual, con certificado SSL/HTTPS automático.

### B. GoDaddy (Dominio y DNS)
- **Gestión de Dominio:** Apunta las zonas DNS institucionales (registros `CNAME` o `A`) hacia la URL generada por Render o hacia el hosting cPanel de la institución.
- **Seguridad Apache:** Se incluyó un archivo [.htaccess](file:///c:/xampp/htdocs/SistemaAsistenciaFISEI-Web/.htaccess) con directivas `Options -Indexes` y reglas de denegación para proteger archivos sensibles (`.sql`, `.log`, `.env`) de accesos directos por navegador.

### C. Supabase (BaaS - Base de Datos PostgreSQL)
- Instancia cloud de PostgreSQL hospedada en infraestructura global de AWS.
- Configurada con **Transaction Pooler (puerto 6543)** y modo seguro `sslmode=require`.

---

## 💡 3. ¿Por qué Supabase (Cloud PostgreSQL) y NO XAMPP Local?

Durante el diseño de la arquitectura se evaluó el uso tradicional de XAMPP (Apache + MariaDB/MySQL local). Sin embargo, fue descartado categóricamente debido a los siguientes factores técnicos determinantes:

| Criterio Técnico | XAMPP Local (MySQL / MariaDB) | Supabase (Cloud PostgreSQL) | Impacto en el Proyecto FISEI |
| :--- | :--- | :--- | :--- |
| **Colaboración del Equipo** | ❌ Aislado en la máquina de 1 desarrollador. Requiere exportar/importar `.sql` constantemente. | ✅ Base de datos centralizada en la nube con acceso concurrente para todo el equipo. | Cristian, César, Joel y Snaider pudieron programar en simultáneo sobre la misma data real. |
| **Despliegue en la Nube (Render / GoDaddy)** | ❌ **Imposible conectar:** Un servidor en la nube no puede acceder a `localhost:3306` dentro de una laptop privada. | ✅ **Accesible 24/7:** Endpoint público accesible mediante conexión cifrada TLS/SSL. | Permite que Render ejecute la aplicación en producción sin importar si las computadoras locales están apagadas. |
| **Agregación Avanzada de Datos** | ⚠️ `GROUP_CONCAT` limitado y engorroso de formatear en PHP. | ✅ `STRING_AGG()` y `ARRAY_TO_JSON(ARRAY_AGG())`. | Agrupa asignaturas e IDs directamente en SQL en una sola consulta $O(1)$, evitando múltiples consultas en bucles PHP. |
| **Inserciones Atómicas** | ⚠️ Requiere ejecutar un `INSERT` y luego llamar a `LAST_INSERT_ID()`, vulnerable a condiciones de carrera. | ✅ Cláusula `RETURNING id`. | Al insertar el estudiante, PostgreSQL retorna inmediatamente el ID generado para vincular las matrículas en la misma transacción. |
| **Integridad Diaria de Asistencia** | ⚠️ Difícil de asegurar si se cae la red durante la marcación. | ✅ `CONSTRAINT uq_asistencia_diaria UNIQUE (id_estudiante, id_materia, fecha)`. | La base de datos rechaza a nivel de motor si un alumno intenta registrar dos veces asistencia en la misma materia el mismo día. |
| **Gestión de Conexiones Web** | ❌ MySQL se satura rápidamente al recibir lecturas continuas de RFID. | ✅ **Transaction Pooler (PgBouncer en puerto 6543)**. | Multiplexa cientos de conexiones web concurrentes de forma eficiente y sin agotar memoria. |
| **Preservación de Ceros a la Izquierda** | ⚠️ MariaDB suele convertir cadenas numéricas a enteros si no se definen tipos estrictos. | ✅ Tipado estricto `VARCHAR(50)` con comparaciones de texto. | Llaveros como `'0006047325'` conservan exactamente sus ceros para el hardware USB. |

---

## 📁 4. Estructura del Proyecto

```text
SistemaAsistenciaFISEI-Web/
├── .dockerignore                       # Exclusión de archivos innecesarios en imagen Docker
├── .gitignore                          # Exclusión de credenciales locales, logs y temporales
├── .htaccess                           # Seguridad Apache (bloqueo de índices y archivos .sql/.env)
├── Dockerfile                          # Contenedor PHP 8.2 + Apache + pdo_pgsql para Render
├── index.php                           # Front Controller principal de la aplicación
├── README.md                           # Documentación técnica completa de arquitectura
│
├── assets/                             # Recursos estáticos del cliente
│   ├── css/
│   │   └── style.css                   # Estilos institucionales UTA (granate, badges, rfid-pulse)
│   └── js/
│       ├── asistencia.js               # Lógica AJAX del pase de asistencia RFID en vivo
│       ├── matriculacion.js            # Lógica AJAX, captura de tecla Enter del lector RFID y SweetAlert2
│       └── reportes.js                 # Lógica AJAX de consolidado FISEI y descarga de actas PDF
│
├── config/                             # Conexión y credenciales
│   ├── database.php                    # Conexión PDO con Transaction Pooler de Supabase (puerto 6543)
│   └── db_credentials.example.php     # Plantilla para credenciales seguras no versionadas
│
├── controllers/                        # Controladores MVC y Endpoints AJAX
│   ├── asistenciaAjaxController.php    # Endpoint AJAX: materias, fechas, listar, marcar_rfid, actualizar_estado
│   ├── controller.php                  # Controlador central, Router y Middleware de Roles
│   ├── estudianteAjaxController.php    # Endpoint AJAX: listar, materias, guardar, modificar, eliminar, buscar_rfid
│   ├── loginController.php             # Endpoint AJAX: autenticación, verificación de hash y sesiones
│   ├── materiaAjaxController.php       # Endpoint AJAX: CRUD de materias y asignación docente (Solo ADMIN)
│   └── reporteAjaxController.php       # Endpoint AJAX: dashboard, materias y consolidado por parcial
│
├── dao/                                # Capa de Acceso a Datos (SQL + PDO)
│   ├── AsistenciaDAO.php               # Consultas y marcaciones RFID/manuales sobre `asistencias`
│   ├── EstudianteDAO.php               # CRUD transaccional sobre `estudiantes` y `matriculas`
│   ├── MateriaDAO.php                  # CRUD y cambio de estado sobre `materias`
│   ├── ReporteDAO.php                  # Consultas de dashboard y consolidado de asistencias
│   └── UsuarioDAO.php                  # Consultas y validación de usuarios/docentes en `usuarios`
│
├── database/                           # Scripts de base de datos
│   ├── fisei_supabase.sql              # DDL oficial con 5 tablas, llaves foráneas, índices y semillas
│   └── fix_claves_semilla.sql          # Utilidad para corrección y cifrado de claves en desarrollo
│
├── entities/                           # Entidades de Dominio (Objetos PHP)
│   ├── Asistencia.php                  # Entidad Asistencia
│   ├── Estudiante.php                  # Entidad Estudiante
│   ├── Materia.php                     # Entidad Materia
│   └── Usuario.php                     # Entidad Usuario (Admin / Docente)
│
├── libs/fpdf/                          # Librería FPDF para generación de actas en PDF
│
├── models/                             # Lógica de Negocio y Validaciones
│   ├── asistenciaModel.php             # Reglas de marcación RFID, fechas y parciales
│   ├── estudianteModel.php             # Validaciones de cédula, duplicados y matrículas
│   ├── loginModel.php                  # Verificación de credenciales con password_verify()
│   ├── materiaModel.php                # Validaciones de código, periodo y docente
│   ├── model.php                       # Lista blanca de enlaces y despacho de módulos
│   └── reporteModel.php                # Regla de cálculo FISEI (80% / APROBADO / EN RIESGO / REPROBADO)
│
├── reports/
│   └── generar_acta_pdf.php            # Generador de Acta Oficial de Asistencia FISEI en PDF
│
└── views/                              # Capa de Presentación
    ├── template.php                    # Plantilla maestra (Layout con Navbar dinámica institucional)
    └── modules/                        # Subvistas modulares
        ├── asistencia.php              # Registro de asistencia por aula y llavero RFID
        ├── inicio.php                  # Dashboard principal con métricas y accesos rápidos
        ├── login.php                   # Formulario interactivo de inicio de sesión con SweetAlert2
        ├── materias.php                # Catálogo interactivo de asignaturas (ADMIN)
        ├── matriculacion.php           # Formulario de registro RFID y nómina en tiempo real (ADMIN)
        ├── reportes.php                # Generación de reportes y porcentajes de asistencia
        └── salir.php                   # Cierre seguro y destrucción de sesión
```

---

## 🔒 5. Seguridad y Control de Acceso por Roles (RBAC)

El sistema cuenta con un modelo de seguridad por capas:

1. **Cifrado de Contraseñas:** Se utiliza el algoritmo estándar de la industria `password_hash($clave, PASSWORD_DEFAULT)` (bcrypt con sal aleatoria) verificado mediante `password_verify()`.
2. **Control de Acceso Basado en Roles:**
   - **Rol `ADMIN`:** Acceso total a todos los módulos: Catálogo de Materias, Matriculación de Estudiantes con RFID, Registro de Asistencias y Reportes.
   - **Rol `DOCENTE`:** Acceso enfocado a la docencia: Pase de Asistencia en el aula de sus materias y generación de reportes oficiales. Las opciones administrativas se ocultan visualmente del menú y se bloquean en el middleware ante intentos de navegación manual por URL.
3. **Protección AJAX:** Los controladores REST/AJAX validan la sesión mediante `controllersMvc::verificarSesionAjax()`, respondiendo códigos HTTP `401 Unauthorized` o `403 Forbidden` si la petición carece de permisos.

---

## 🚀 6. Instalación y Configuración Local

### Opción A: Ejecución con Docker (Recomendada)
1. Clonar el repositorio:
   ```bash
   git clone https://github.com/SnaiderToainga/SistemaAsistenciaFISEI-Web.git
   cd SistemaAsistenciaFISEI-Web
   ```
2. Construir y levantar el contenedor:
   ```bash
   docker build -t sistema-asistencia-fisei .
   docker run -d -p 8080:80 --name fisei-app sistema-asistencia-fisei
   ```
3. Abrir en el navegador: `http://localhost:8080`

### Opción B: Ejecución con XAMPP (Windows)
1. Clonar o mover la carpeta a `C:\xampp\htdocs\SistemaAsistenciaFISEI-Web`.
2. Habilitar la extensión de PostgreSQL en PHP:
   - Abrir `C:\xampp\php\php.ini`.
   - Quitar el punto y coma `;` de la línea: `extension=pdo_pgsql` y `extension=pgsql`.
   - Reiniciar Apache desde el panel de XAMPP.
3. Configurar credenciales de Supabase:
   - Copiar `config/db_credentials.example.php` como `config/db_credentials.php`.
   - Colocar el host, usuario y password proporcionados para la base de datos de Supabase.
4. Abrir en el navegador: `http://localhost/SistemaAsistenciaFISEI-Web`

---

## 🏷️ 7. Datos Semilla y Llaveros Físicos de Laboratorio

El script de base de datos [fisei_supabase.sql](file:///c:/xampp/htdocs/SistemaAsistenciaFISEI-Web/database/fisei_supabase.sql) contiene las cuentas y llaveros físicos del laboratorio listos para demostración:

### Cuentas de Acceso al Sistema:
| Usuario | Rol | Contraseña | Nombre Completo |
| :--- | :--- | :--- | :--- |
| `admin.fisei` | `ADMIN` | `admin123` | Administrador FISEI |
| `docente.fisei` | `DOCENTE` | `admin123` | Ing. Docente Titular FISEI |

### Llaveros RFID Registrados:
| UID RFID | Cédula | Estudiante | Carrera | Materias Asignadas |
| :--- | :--- | :--- | :--- | :--- |
| `0006047325` | `1809876543` | María Belén Gómez Díaz | Software | SIS-501, SIS-502 |
| `0007081242` | `1805544332` | Carlos Andrés López Vega | Software | SIS-501 |
| `0002553129` | `1805095690` | Axel Jair Lopez Perez | Software | SIS-401 |

> ℹ️ **Uso del Lector RFID USB:** Al conectar el lector físico por USB, este emula la entrada de un teclado numérico enviando el UID seguido automáticamente del código de tecla `Enter`. El frontend captura este evento sin recargar la página, realizando la búsqueda y marcación instantánea.

---

## 👥 8. Equipo de Desarrollo

Proyecto desarrollado por estudiantes de la **Facultad de Ingeniería en Sistemas, Electrónica e Industrial (FISEI)** de la **Universidad Técnica de Ambato (UTA)**:

- **Snaider Alexander Toainga** — *Team Leader & Database Architect* (MVC Core, Modelo Relacional Supabase y Matriculación RFID)
- **Cristian Fernando Sivinta** — *Security & Academic Management* (Autenticación bcrypt, Sesiones RBAC, Middleware y Catálogo de Materias)
- **César** — *Hardware & Classroom Attendance* (Operación en Aula con Lector RFID USB y Control de Estados)
- **Joel** — *Analytics & Official Reports* (Dashboard Estadístico, Consolidado FISEI 80% y Exportación de Actas en PDF)

---
&copy; 2026 Universidad Técnica de Ambato &bull; FISEI &bull; Todos los derechos reservados.
