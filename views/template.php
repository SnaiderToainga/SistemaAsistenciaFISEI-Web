<?php
/**
 * ==============================================================================
 * Plantilla Base del Sistema (Template)
 * ==============================================================================
 * Sistema de Control de Asistencia FISEI - Web
 * Bootstrap 5.3 + Bootstrap Icons + SweetAlert2
 */

$currentAction = $_GET['action'] ?? 'matriculacion';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Asistencia FISEI | Universidad Técnica de Ambato</title>

    <!-- Favicon institucional SVG -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%238B0000'><path d='M12 2L1 7l11 5 9-4.09V17h2V7L12 2zm0 13l-7-3.18v4.5C5 19.5 8 22 12 22s7-2.5 7-5.68v-4.5L12 15z'/></svg>">

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Bootstrap Icons 1.11.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Estilos Propios Institucionales -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- BARRA DE NAVEGACIÓN INSTITUCIONAL FISEI - UTA -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-uta sticky-top shadow-sm">
        <div class="container-fluid px-md-4">
            <!-- Marca y Logo FISEI -->
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php?action=inicio">
                <i class="bi bi-mortarboard-fill fs-3 text-warning"></i>
                <div class="d-flex flex-column lh-sm">
                    <span class="fw-bold">FISEI &bull; UTA</span>
                    <small style="font-size: 0.72rem; opacity: 0.9;" class="text-white-50">Control de Asistencia RFID</small>
                </div>
            </a>

            <!-- Botón menú móvil -->
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSistema" aria-controls="navbarSistema" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Menú de Enlaces -->
            <div class="collapse navbar-collapse" id="navbarSistema">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3 gap-1">
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentAction === 'inicio') ? 'active' : '' ?>" href="index.php?action=inicio">
                            <i class="bi bi-house-door me-1"></i> Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentAction === 'materias') ? 'active' : '' ?>" href="index.php?action=materias">
                            <i class="bi bi-journal-bookmark me-1"></i> Materias
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentAction === 'matriculacion') ? 'active' : '' ?>" href="index.php?action=matriculacion">
                            <i class="bi bi-person-badge me-1"></i> Matriculación
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentAction === 'asistencia') ? 'active' : '' ?>" href="index.php?action=asistencia">
                            <i class="bi bi-calendar-check me-1"></i> Asistencia
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentAction === 'reportes') ? 'active' : '' ?>" href="index.php?action=reportes">
                            <i class="bi bi-bar-chart-line me-1"></i> Reportes
                        </a>
                    </li>
                </ul>

                <!-- Acciones de Usuario y Salir -->
                <div class="d-flex align-items-center gap-3">
                    <div class="text-white text-end d-none d-md-block lh-1">
                        <small class="fw-semibold d-block">Docente / Admin FISEI</small>
                        <small class="text-white-50" style="font-size: 0.75rem;">Periodo 2026 - 2027</small>
                    </div>
                    <a href="index.php?action=salir" class="btn btn-sm btn-outline-light d-flex align-items-center gap-1 shadow-sm">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Cerrar Sesión</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- CONTENEDOR PRINCIPAL DINÁMICO (MVC) -->
    <main class="container-fluid py-4 px-md-4 flex-grow-1">
        <?php
            // El controlador despacha dinámicamente la vista correspondiente
            if (!isset($mvc) || !($mvc instanceof controllersMvc)) {
                $mvc = new controllersMvc();
            }
            $mvc->enlacesPaginasControllers();
        ?>
    </main>

    <!-- PIE DE PÁGINA INSTITUCIONAL -->
    <footer class="footer-uta text-center mt-auto">
        <div class="container-fluid px-4">
            <p class="mb-1 text-white-50">
                &copy; <?= date('Y') ?> <strong>Facultad de Ingeniería en Sistemas, Electrónica e Industrial (FISEI)</strong> &bull; Universidad Técnica de Ambato
            </p>
            <small class="text-white-50" style="font-size: 0.78rem;">
                Campus Huachi, Av. Los Chasquis y Río Guayllabamba &bull; Ambato, Ecuador &bull; Sistema de Control Biométrico y RFID
            </small>
        </div>
    </footer>

    <!-- Bootstrap 5.3.3 Bundle JS con Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- SweetAlert2 v11 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
