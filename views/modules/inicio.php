<?php
/**
 * Módulo de Inicio / Dashboard Principal
 */

$esAdmin = controllersMvc::esAdmin();
$nombre  = htmlspecialchars($_SESSION['nombre_completo'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
?>

<div class="row g-4">
    <!-- Banner de Bienvenida -->
    <div class="col-12">
        <div class="card card-fisei border-0 shadow-sm" style="background: linear-gradient(135deg, #8B0000 0%, #1e293b 100%);">
            <div class="card-body p-4 text-white">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <span class="badge bg-warning text-dark fw-bold mb-2">Periodo Académico 2026 - 2027</span>
                        <h2 class="h3 fw-bold mb-1">Bienvenido(a), <?= $nombre ?></h2>
                        <p class="mb-0 text-white-50 small">
                            Facultad de Ingeniería en Sistemas, Electrónica e Industrial &bull; Universidad Técnica de Ambato.
                        </p>
                    </div>
                    <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                        <?php if ($esAdmin): ?>
                        <a href="index.php?action=matriculacion" class="btn btn-light px-4 shadow-sm text-danger fw-bold">
                            <i class="bi bi-person-badge me-2"></i>Ir a Matriculación
                        </a>
                        <?php else: ?>
                        <a href="index.php?action=asistencia" class="btn btn-light px-4 shadow-sm text-danger fw-bold">
                            <i class="bi bi-calendar-check me-2"></i>Tomar Asistencia
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjetas de Contadores en Tiempo Real -->
    <div class="col-md-4 col-12">
        <div class="card card-fisei shadow-sm h-100 border-start border-4 border-danger">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted text-uppercase fw-semibold d-block">Alumnos Matriculados</small>
                    <h2 id="kpi-estudiantes" class="fw-bold mb-0 text-dark">0</h2>
                </div>
                <i class="bi bi-people-fill fs-1 text-danger"></i>
            </div>
        </div>
    </div>

    <div class="col-md-4 col-12">
        <div class="card card-fisei shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted text-uppercase fw-semibold d-block">Materias Activas</small>
                    <h2 id="kpi-materias" class="fw-bold mb-0 text-dark">0</h2>
                </div>
                <i class="bi bi-journal-bookmark-fill fs-1 text-success"></i>
            </div>
        </div>
    </div>

    <div class="col-md-4 col-12">
        <div class="card card-fisei shadow-sm h-100 border-start border-4 border-primary">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted text-uppercase fw-semibold d-block">Asistencias Registradas Hoy</small>
                    <h2 id="kpi-hoy" class="fw-bold mb-0 text-dark">0</h2>
                </div>
                <i class="bi bi-calendar-check-fill fs-1 text-primary"></i>
            </div>
        </div>
    </div>

    <!-- Tarjetas de Acceso Rápido -->
    <?php if ($esAdmin): ?>
    <div class="col-md-3 col-sm-6 col-12">
        <div class="card card-fisei shadow-sm text-center p-3 h-100">
            <div class="card-body">
                <i class="bi bi-mortarboard fs-1 text-danger mb-2 d-block"></i>
                <h5 class="fw-bold mb-1">Matriculación</h5>
                <p class="text-muted small mb-3">Registro de alumnos y llaveros RFID.</p>
                <a href="index.php?action=matriculacion" class="btn btn-sm btn-outline-danger">Acceder</a>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 col-12">
        <div class="card card-fisei shadow-sm text-center p-3 h-100">
            <div class="card-body">
                <i class="bi bi-journal-bookmark fs-1 text-success mb-2 d-block"></i>
                <h5 class="fw-bold mb-1">Materias</h5>
                <p class="text-muted small mb-3">Catálogo y asignación por docente.</p>
                <a href="index.php?action=materias" class="btn btn-sm btn-outline-success">Acceder</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?= $esAdmin ? 'col-md-3 col-sm-6' : 'col-md-6' ?> col-12">
        <div class="card card-fisei shadow-sm text-center p-3 h-100">
            <div class="card-body">
                <i class="bi bi-calendar-check fs-1 text-primary mb-2 d-block"></i>
                <h5 class="fw-bold mb-1">Pase de Asistencia</h5>
                <p class="text-muted small mb-3">Registro de ingresos mediante lector RFID.</p>
                <a href="index.php?action=asistencia" class="btn btn-sm btn-outline-primary">Acceder</a>
            </div>
        </div>
    </div>

    <div class="<?= $esAdmin ? 'col-md-3 col-sm-6' : 'col-md-6' ?> col-12">
        <div class="card card-fisei shadow-sm text-center p-3 h-100">
            <div class="card-body">
                <i class="bi bi-file-earmark-bar-graph fs-1 text-warning mb-2 d-block"></i>
                <h5 class="fw-bold mb-1">Reportes PDF</h5>
                <p class="text-muted small mb-3">Consolidado FISEI y actas oficiales en PDF.</p>
                <a href="index.php?action=reportes" class="btn btn-sm btn-outline-warning text-dark">Acceder</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    fetch('controllers/reporteAjaxController.php?accion=dashboard')
        .then(function (resp) { return resp.json(); })
        .then(function (res) {
            if (res.status === 'ok' && res.data) {
                document.getElementById('kpi-estudiantes').textContent = res.data.total_estudiantes;
                document.getElementById('kpi-materias').textContent    = res.data.total_materias;
                document.getElementById('kpi-hoy').textContent         = res.data.asistencias_hoy;
            }
        })
        .catch(function (err) {
            console.error('Error al cargar dashboard:', err);
        });
});
</script>
