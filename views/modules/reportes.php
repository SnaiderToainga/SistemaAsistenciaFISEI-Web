<?php
/**
 * Módulo de Reportes, Consolidado FISEI y Exportación PDF
 */
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-file-earmark-bar-graph-fill text-danger me-2"></i>Reportes y Consolidado FISEI
        </h2>
        <p class="text-muted small mb-0">
            Cálculo de porcentaje de asistencia por parcial y exportación del Acta Oficial en PDF.
        </p>
    </div>
    <button type="button" class="btn btn-danger fw-semibold" id="btn-exportar-pdf" disabled>
        <i class="bi bi-file-earmark-pdf-fill me-1"></i>Exportar Acta PDF
    </button>
</div>

<!-- Filtros -->
<div class="card card-fisei shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-5 col-12">
                <label for="select-materia-reporte" class="form-label small fw-semibold text-secondary mb-1">Asignatura</label>
                <select class="form-select" id="select-materia-reporte">
                    <option value="">Cargando asignaturas...</option>
                </select>
            </div>
            <div class="col-md-3 col-12">
                <label for="select-parcial-reporte" class="form-label small fw-semibold text-secondary mb-1">Parcial</label>
                <select class="form-select" id="select-parcial-reporte">
                    <option value="Parcial 1" selected>Parcial 1</option>
                    <option value="Parcial 2">Parcial 2</option>
                </select>
            </div>
            <div class="col-md-4 col-12">
                <label for="buscar-reporte" class="form-label small fw-semibold text-secondary mb-1">Buscar estudiante</label>
                <input type="text" class="form-control" id="buscar-reporte" placeholder="Cédula o nombre...">
            </div>
        </div>
    </div>
</div>

<!-- Resumen Rápido -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card card-fisei shadow-sm border-start border-4 border-secondary">
            <div class="card-body py-3">
                <small class="text-muted d-block">Clases Impartidas</small>
                <h4 id="rep-total-clases" class="fw-bold mb-0">0</h4>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card card-fisei shadow-sm border-start border-4 border-success">
            <div class="card-body py-3">
                <small class="text-muted d-block">Aprobados</small>
                <h4 id="rep-aprobados" class="fw-bold text-success mb-0">0</h4>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card card-fisei shadow-sm border-start border-4 border-warning">
            <div class="card-body py-3">
                <small class="text-muted d-block">En Riesgo</small>
                <h4 id="rep-en-riesgo" class="fw-bold text-warning-emphasis mb-0">0</h4>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card card-fisei shadow-sm border-start border-4 border-danger">
            <div class="card-body py-3">
                <small class="text-muted d-block">Reprobados</small>
                <h4 id="rep-reprobados" class="fw-bold text-danger mb-0">0</h4>
            </div>
        </div>
    </div>
</div>

<!-- Tabla Consolidada -->
<div class="card card-fisei shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-dark" id="titulo-consolidado">Consolidado de Asistencia</h5>
        <span id="badge-total-reporte" class="badge bg-danger rounded-pill">0</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="small text-uppercase text-secondary">
                        <th class="ps-3 py-3">Cédula</th>
                        <th class="py-3">Estudiante</th>
                        <th class="text-center py-3">Presentes</th>
                        <th class="text-center py-3">Atrasos</th>
                        <th class="text-center py-3">Faltas</th>
                        <th class="text-center py-3">Justif.</th>
                        <th class="text-center py-3">Válidas</th>
                        <th class="text-center py-3">Porcentaje</th>
                        <th class="text-center pe-3 py-3">Estado</th>
                    </tr>
                </thead>
                <tbody id="tabla-reporte-body">
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">Cargando datos...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="assets/js/reportes.js"></script>
