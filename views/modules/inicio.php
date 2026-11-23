<?php
/**
 * ==============================================================================
 * Módulo de Inicio / Panel Principal
 * ==============================================================================
 * Sistema de Control de Asistencia FISEI - Web
 */
?>

<div class="row g-4">
    <!-- Banner de Bienvenida Institucional -->
    <div class="col-12">
        <div class="card card-fisei border-0 shadow-sm overflow-hidden" style="background: linear-gradient(135deg, #8B0000 0%, #1e293b 100%);">
            <div class="card-body p-4 text-white">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <span class="badge bg-warning text-dark fw-bold mb-2">Periodo Académico 2026 - 2027</span>
                        <h2 class="display-6 fw-bold mb-2">Sistema de Control de Asistencia FISEI</h2>
                        <p class="lead mb-0 text-white-50 fs-6">
                            Facultad de Ingeniería en Sistemas, Electrónica e Industrial &bull; Universidad Técnica de Ambato.
                            Gestione matrículas, registro por lector RFID USB y control biométrico en tiempo real.
                        </p>
                    </div>
                    <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                        <a href="index.php?action=matriculacion" class="btn btn-light btn-lg px-4 shadow-sm text-danger fw-bold">
                            <i class="bi bi-person-badge me-2"></i>Ir a Matriculación
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjetas de Acceso Rápido -->
    <div class="col-md-3 col-sm-6 col-12">
        <div class="card card-fisei shadow-sm text-center p-3 h-100 border-start border-4 border-danger">
            <div class="card-body">
                <i class="bi bi-mortarboard fs-1 text-danger mb-2 d-block"></i>
                <h5 class="fw-bold mb-1">Matriculación</h5>
                <p class="text-muted small mb-3">Registro de alumnos y asignación de llaveros RFID.</p>
                <a href="index.php?action=matriculacion" class="btn btn-sm btn-outline-danger">Acceder</a>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 col-12">
        <div class="card card-fisei shadow-sm text-center p-3 h-100 border-start border-4 border-primary">
            <div class="card-body">
                <i class="bi bi-calendar-check fs-1 text-primary mb-2 d-block"></i>
                <h5 class="fw-bold mb-1">Pase de Asistencia</h5>
                <p class="text-muted small mb-3">Registro de ingresos mediante lector RFID en el aula.</p>
                <a href="index.php?action=asistencia" class="btn btn-sm btn-outline-primary">Acceder</a>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 col-12">
        <div class="card card-fisei shadow-sm text-center p-3 h-100 border-start border-4 border-success">
            <div class="card-body">
                <i class="bi bi-journal-bookmark fs-1 text-success mb-2 d-block"></i>
                <h5 class="fw-bold mb-1">Materias</h5>
                <p class="text-muted small mb-3">Catálogo y asignación de materias por docente.</p>
                <a href="index.php?action=materias" class="btn btn-sm btn-outline-success">Acceder</a>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 col-12">
        <div class="card card-fisei shadow-sm text-center p-3 h-100 border-start border-4 border-warning">
            <div class="card-body">
                <i class="bi bi-file-earmark-bar-graph fs-1 text-warning mb-2 d-block"></i>
                <h5 class="fw-bold mb-1">Reportes</h5>
                <p class="text-muted small mb-3">Estadísticas de asistencia, faltas y reportes PDF/Excel.</p>
                <a href="index.php?action=reportes" class="btn btn-sm btn-outline-warning">Acceder</a>
            </div>
        </div>
    </div>
</div>

