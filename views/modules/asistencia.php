<?php

date_default_timezone_set('America/Guayaquil');
$fechaHoy = date('Y-m-d');
?>

<div class="mb-4 pb-2 border-bottom">
    <h1 class="h4 fw-bold text-dark mb-0">Asistencia</h1>
</div>

<div class="row g-4">

    <div class="col-xl-4 col-lg-5 col-12">
        <div class="card card-fisei shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-semibold text-dark">Registro</span>
                <span id="badge-parcial" class="badge bg-light text-secondary border px-2 py-1">
                    Parcial 1
                </span>
            </div>

            <div class="card-body p-3 p-md-4">
                <form id="form-asistencia" autocomplete="off" onsubmit="return false;">

                    <div class="mb-3">
                        <label for="select-materia" class="form-label fw-semibold text-secondary small mb-1">
                            Asignatura
                        </label>
                        <select class="form-select" id="select-materia" name="id_materia">
                            <option value="">Cargando...</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-6 col-12">
                            <label for="select-parcial" class="form-label fw-semibold text-secondary small mb-1">
                                Parcial
                            </label>
                            <select class="form-select" id="select-parcial" name="parcial">
                                <option value="Parcial 1" selected>Parcial 1</option>
                                <option value="Parcial 2">Parcial 2</option>
                            </select>
                        </div>
                        <div class="col-sm-6 col-12">
                            <label for="input-fecha" class="form-label fw-semibold text-secondary small mb-1">
                                Fecha
                            </label>
                            <input type="date"
                                   class="form-control"
                                   id="input-fecha"
                                   name="fecha"
                                   value="<?= htmlspecialchars($fechaHoy, ENT_QUOTES, 'UTF-8') ?>"
                                   required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="select-fecha-historial" class="form-label fw-semibold text-secondary small mb-1">
                            Historial de clases
                        </label>
                        <select class="form-select form-select-sm" id="select-fecha-historial" disabled>
                            <option value="">Sin clases registradas</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="uid_rfid" class="form-label fw-semibold text-secondary small mb-1">
                            Código RFID
                        </label>
                        <input type="text"
                               class="form-control font-monospace"
                               id="uid_rfid"
                               name="uid_rfid"
                               placeholder="Escanear código..."
                               autofocus
                               autocomplete="off">
                    </div>

                    <div class="border-top border-bottom py-3 my-3">
                        <div class="row g-2 text-center">
                            <div class="col-3">
                                <span id="resumen-presentes" class="fs-4 fw-bold lh-1 d-block mb-1 text-success" style="font-variant-numeric: tabular-nums;">0</span>
                                <small class="text-muted d-block" style="font-size: 0.72rem;">Presentes</small>
                            </div>
                            <div class="col-3 border-start">
                                <span id="resumen-atrasos" class="fs-4 fw-bold lh-1 d-block mb-1 text-warning-emphasis" style="font-variant-numeric: tabular-nums;">0</span>
                                <small class="text-muted d-block" style="font-size: 0.72rem;">Atrasos</small>
                            </div>
                            <div class="col-3 border-start">
                                <span id="resumen-faltas" class="fs-4 fw-bold lh-1 d-block mb-1 text-danger" style="font-variant-numeric: tabular-nums;">0</span>
                                <small class="text-muted d-block" style="font-size: 0.72rem;">Faltas</small>
                            </div>
                            <div class="col-3 border-start">
                                <span id="resumen-justificados" class="fs-4 fw-bold lh-1 d-block mb-1 text-primary" style="font-variant-numeric: tabular-nums;">0</span>
                                <small class="text-muted d-block" style="font-size: 0.72rem;">Justificados</small>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-outline-danger w-100" id="btn-marcar-faltas">
                        <i class="bi bi-person-x me-1"></i>Marcar faltas pendientes
                    </button>

                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8 col-lg-7 col-12">
        <div class="card card-fisei shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <div class="row g-2 align-items-center justify-content-between">
                    <div class="col-md-5 col-12">
                        <div class="d-flex align-items-center gap-2">
                            <h2 class="h6 mb-0 fw-semibold text-dark">Estudiantes</h2>
                            <span id="badge-total-asistencia" class="badge rounded-pill bg-secondary-subtle text-dark" style="font-variant-numeric: tabular-nums;">0</span>
                        </div>
                    </div>
                    <div class="col-md-7 col-12">
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text"
                                   class="form-control border-start-0 ps-0"
                                   id="buscar-asistencia"
                                   placeholder="Buscar por cédula, nombre o código...">
                            <button class="btn btn-outline-secondary" type="button" id="btn-recargar" title="Actualizar">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 620px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="tabla-asistencia">
                        <thead class="table-light sticky-top shadow-sm">
                            <tr class="small text-uppercase text-secondary">
                                <th class="ps-3 py-3" style="width: 110px;">Cédula</th>
                                <th class="py-3">Estudiante</th>
                                <th class="py-3" style="width: 105px;">Hora</th>
                                <th class="text-center py-3" style="width: 120px;">Estado</th>
                                <th class="py-3">Observación</th>
                                <th class="text-center pe-3 py-3" style="width: 80px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-asistencia-body">
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                                    <span class="small">Cargando...</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-light py-2 px-3 d-flex justify-content-end">
                <span id="hora-actualizacion" class="text-muted small" style="font-variant-numeric: tabular-nums;"></span>
            </div>
        </div>
    </div>

</div>

<script src="assets/js/asistencia.js"></script>
