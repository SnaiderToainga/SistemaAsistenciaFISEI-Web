<?php
/**
 * ==============================================================================
 * Módulo de Matriculación y Registro de Estudiantes
 * ==============================================================================
 * Vista en 2 columnas:
 * - Columna Izquierda (4 cols): Formulario con lector RFID, Cédula, Nombres,
 *   Apellidos, Carrera, Checkboxes dinámicos de materias y botones de acción.
 * - Columna Derecha (8 cols): Buscador en tiempo real y tabla responsiva.
 */
?>

<!-- Encabezado de la página -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-person-vcard text-danger me-2"></i>Gestión de Estudiantes y Matriculación
        </h2>
        <p class="text-muted small mb-0">
            Registro con lector RFID USB, validación de cédula y asignación a materias vigentes de FISEI.
        </p>
    </div>
    <div class="mt-2 mt-md-0 d-flex gap-2">
        <span class="badge bg-light text-dark border px-3 py-2 d-flex align-items-center gap-2 shadow-sm">
            <span class="rfid-pulse"></span>
            <span class="fw-semibold">Lector RFID: Activo</span>
        </span>
    </div>
</div>

<div class="row g-4">

    <!-- COLUMNA IZQUIERDA: FORMULARIO DE REGISTRO / EDICIÓN (4 COLS) -->
    <div class="col-xl-4 col-lg-5 col-12">
        <div class="card card-fisei shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-person-plus-fill text-danger"></i>
                    <span>Formulario de Estudiante</span>
                </span>
                <span id="badge-modo" class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                    Nuevo Registro
                </span>
            </div>

            <div class="card-body p-3 p-md-4">
                <form id="form-estudiante" autocomplete="off" onsubmit="return false;">
                    <!-- ID oculto para edición -->
                    <input type="hidden" id="id_estudiante" name="id" value="">

                    <!-- Campo Llavero RFID con autofocus -->
                    <div class="mb-3">
                        <label for="uid_rfid" class="form-label fw-semibold text-secondary small mb-1">
                            Llavero / Tarjeta RFID <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">
                                <i class="bi bi-broadcast-pin text-danger"></i>
                            </span>
                            <input type="text" 
                                   class="form-control font-monospace fw-semibold" 
                                   id="uid_rfid" 
                                   name="uid_rfid" 
                                   placeholder="Acerque el llavero al lector..." 
                                   autofocus 
                                   required 
                                   autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="btn-focus-rfid" title="Enfocar para lectura rápida">
                                <i class="bi bi-crosshair"></i>
                            </button>
                        </div>
                        <div class="form-text text-muted" style="font-size: 0.75rem;">
                            <i class="bi bi-info-circle text-primary me-1"></i>El lector USB emula la tecla <code>Enter</code> al detectar el llavero.
                        </div>
                    </div>

                    <!-- Campo Cédula de Identidad (10 dígitos) -->
                    <div class="mb-3">
                        <label for="cedula" class="form-label fw-semibold text-secondary small mb-1">
                            Cédula de Identidad <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">
                                <i class="bi bi-card-heading"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="cedula" 
                                   name="cedula" 
                                   placeholder="1801234567" 
                                   maxlength="10" 
                                   inputmode="numeric" 
                                   pattern="[0-9]{10}" 
                                   required>
                        </div>
                        <div class="form-text text-muted" style="font-size: 0.75rem;">
                            Exactamente 10 dígitos numéricos sin guion.
                        </div>
                    </div>

                    <!-- Nombres y Apellidos -->
                    <div class="row g-2 mb-3">
                        <div class="col-sm-6 col-12">
                            <label for="nombres" class="form-label fw-semibold text-secondary small mb-1">
                                Nombres <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="nombres" name="nombres" placeholder="Ej: Juan Carlos" required>
                        </div>
                        <div class="col-sm-6 col-12">
                            <label for="apellidos" class="form-label fw-semibold text-secondary small mb-1">
                                Apellidos <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="apellidos" name="apellidos" placeholder="Ej: Pérez López" required>
                        </div>
                    </div>

                    <!-- Carrera Institucional -->
                    <div class="mb-3">
                        <label for="carrera" class="form-label fw-semibold text-secondary small mb-1">
                            Carrera Universitaria
                        </label>
                        <select class="form-select" id="carrera" name="carrera">
                            <option value="Software" selected>Ingeniería de Software</option>
                            <option value="Tecnologías de la Información">Tecnologías de la Información</option>
                            <option value="Sistemas Computacionales">Sistemas Computacionales</option>
                            <option value="Telecomunicaciones">Telecomunicaciones</option>
                            <option value="Industrial">Ingeniería Industrial</option>
                            <option value="Robótica y Automatización">Robótica y Automatización</option>
                        </select>
                    </div>

                    <!-- Contenedor Dinámico de Asignaturas Activas -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold text-secondary small mb-0">
                                Asignaturas a Matricular
                            </label>
                            <span id="badge-contador-materias" class="badge bg-secondary-subtle text-secondary small">
                                0 seleccionadas
                            </span>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="btn-marcar-todas" style="font-size: 0.75rem;">
                                Seleccionar todas
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="btn-desmarcar-todas" style="font-size: 0.75rem;">
                                Desmarcar
                            </button>
                        </div>
                        <div id="contenedor-materias" class="border rounded p-2 bg-light" style="max-height: 190px; overflow-y: auto;">
                            <!-- Cargado dinámicamente mediante estudianteAjaxController.php?accion=materias -->
                            <div class="text-center py-3 text-muted small">
                                <div class="spinner-border spinner-border-sm text-danger me-1" role="status"></div>
                                Cargando materias activas...
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1" id="btn-limpiar">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar
                        </button>
                        
                        <button type="button" class="btn btn-danger flex-grow-1" id="btn-guardar">
                            <i class="bi bi-save me-1"></i>Guardar
                        </button>

                        <button type="button" class="btn btn-warning flex-grow-1 d-none" id="btn-modificar">
                            <i class="bi bi-pencil-square me-1"></i>Modificar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- COLUMNA DERECHA: LISTADO Y BUSCADOR EN TIEMPO REAL (8 COLS) -->
    <div class="col-xl-8 col-lg-7 col-12">
        <div class="card card-fisei shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <div class="row g-2 align-items-center justify-content-between">
                    <div class="col-md-5 col-12">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-people-fill text-danger fs-5"></i>
                            <h5 class="mb-0 fw-bold text-dark">Nómina de Estudiantes</h5>
                            <span id="badge-total-estudiantes" class="badge bg-danger rounded-pill ms-1">0</span>
                        </div>
                    </div>
                    <div class="col-md-7 col-12">
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" 
                                   class="form-control border-start-0 ps-0" 
                                   id="buscar-estudiante" 
                                   placeholder="Buscar por cédula, nombre, rfid o materia...">
                            <button class="btn btn-outline-secondary" type="button" id="btn-recargar" title="Recargar lista">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 620px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="tabla-estudiantes">
                        <thead class="table-light sticky-top shadow-sm">
                            <tr class="small text-uppercase text-secondary">
                                <th class="ps-3 py-3" style="width: 110px;">Cédula</th>
                                <th class="py-3">Estudiante</th>
                                <th class="py-3" style="width: 140px;">UID Llavero</th>
                                <th class="py-3">Materias Inscritas</th>
                                <th class="text-center pe-3 py-3" style="width: 120px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-estudiantes-body">
                            <!-- Filas inyectadas por JavaScript -->
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <div class="spinner-border text-danger" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <p class="mt-2 mb-0 small">Cargando estudiantes desde la base de datos...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pie de tarjeta con leyenda -->
            <div class="card-footer bg-light small py-2 px-3 text-muted d-flex justify-content-between align-items-center">
                <span><i class="bi bi-shield-check text-success me-1"></i>Sincronizado con Supabase PostgreSQL</span>
                <span id="hora-actualizacion" class="text-secondary" style="font-size: 0.75rem;"></span>
            </div>
        </div>
    </div>

</div>

<!-- Script específico de la vista de matriculación -->
<script src="assets/js/matriculacion.js"></script>

