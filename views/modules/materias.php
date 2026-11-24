<?php
/**
 * ==============================================================================
 * Módulo de Gestión de Asignaturas / Materias (Solo ADMIN)
 * ==============================================================================
 * Vista en 2 columnas:
 * - Columna Izquierda (4 cols): Formulario de registro / edición de asignatura
 *   (código, nombre, periodo, docente responsable y estado activa).
 * - Columna Derecha (8 cols): Buscador en tiempo real y catálogo de asignaturas.
 * Comunicación con controllers/materiaAjaxController.php (Fetch API + SweetAlert2).
 */
?>

<!-- Encabezado de la página -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-journal-bookmark-fill text-danger me-2"></i>Gestión de Asignaturas
        </h2>
        <p class="text-muted small mb-0">
            Registro y edición del catálogo de materias, periodo académico y docente responsable.
        </p>
    </div>
    <div class="mt-2 mt-md-0">
        <span class="badge bg-light text-dark border px-3 py-2 d-flex align-items-center gap-2 shadow-sm">
            <i class="bi bi-shield-check text-success"></i>
            <span class="fw-semibold">Acceso Administrador</span>
        </span>
    </div>
</div>

<div class="row g-4">

    <!-- COLUMNA IZQUIERDA: FORMULARIO DE REGISTRO / EDICIÓN (4 COLS) -->
    <div class="col-xl-4 col-lg-5 col-12">
        <div class="card card-fisei shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-journal-plus text-danger"></i>
                    <span>Formulario de Asignatura</span>
                </span>
                <span id="badge-modo-materia" class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                    Nuevo Registro
                </span>
            </div>

            <div class="card-body p-3 p-md-4">
                <form id="form-materia" autocomplete="off" onsubmit="return false;">
                    <!-- ID oculto para edición -->
                    <input type="hidden" id="id_materia" name="id" value="">

                    <!-- Código de la asignatura -->
                    <div class="mb-3">
                        <label for="codigo" class="form-label fw-semibold text-secondary small mb-1">
                            Código <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">
                                <i class="bi bi-upc"></i>
                            </span>
                            <input type="text"
                                   class="form-control font-monospace fw-semibold text-uppercase"
                                   id="codigo"
                                   name="codigo"
                                   placeholder="SIS-501"
                                   maxlength="20"
                                   required>
                        </div>
                        <div class="form-text text-muted" style="font-size: 0.75rem;">
                            Letras, números y guion (2 a 20 caracteres).
                        </div>
                    </div>

                    <!-- Nombre de la asignatura -->
                    <div class="mb-3">
                        <label for="nombre" class="form-label fw-semibold text-secondary small mb-1">
                            Nombre de la Asignatura <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="nombre"
                               name="nombre"
                               placeholder="Ej: Ingeniería de Software I"
                               maxlength="150"
                               required>
                    </div>

                    <!-- Periodo académico -->
                    <div class="mb-3">
                        <label for="periodo" class="form-label fw-semibold text-secondary small mb-1">
                            Periodo Académico <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">
                                <i class="bi bi-calendar-range"></i>
                            </span>
                            <input type="text"
                                   class="form-control"
                                   id="periodo"
                                   name="periodo"
                                   placeholder="2026-2027"
                                   value="2026-2027"
                                   maxlength="9"
                                   pattern="[0-9]{4}-[0-9]{4}"
                                   required>
                        </div>
                    </div>

                    <!-- Docente responsable -->
                    <div class="mb-3">
                        <label for="id_docente" class="form-label fw-semibold text-secondary small mb-1">
                            Docente Responsable
                        </label>
                        <select class="form-select" id="id_docente" name="id_docente">
                            <option value="">Cargando docentes...</option>
                        </select>
                    </div>

                    <!-- Estado activa -->
                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="activa" name="activa" checked>
                            <label class="form-check-label small fw-semibold text-secondary" for="activa">
                                Asignatura activa en el periodo
                            </label>
                        </div>
                        <div class="form-text text-muted" style="font-size: 0.75rem;">
                            Las asignaturas inactivas no aparecen en Matriculación.
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                        <button type="button" class="btn btn-outline-secondary flex-grow-1" id="btn-limpiar-materia">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar
                        </button>

                        <button type="button" class="btn btn-danger flex-grow-1" id="btn-guardar-materia">
                            <i class="bi bi-save me-1"></i>Guardar
                        </button>

                        <button type="button" class="btn btn-warning flex-grow-1 d-none" id="btn-modificar-materia">
                            <i class="bi bi-pencil-square me-1"></i>Modificar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- COLUMNA DERECHA: CATÁLOGO Y BUSCADOR EN TIEMPO REAL (8 COLS) -->
    <div class="col-xl-8 col-lg-7 col-12">
        <div class="card card-fisei shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <div class="row g-2 align-items-center justify-content-between">
                    <div class="col-md-5 col-12">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-collection-fill text-danger fs-5"></i>
                            <h5 class="mb-0 fw-bold text-dark">Catálogo de Asignaturas</h5>
                            <span id="badge-total-materias" class="badge bg-danger rounded-pill ms-1">0</span>
                        </div>
                    </div>
                    <div class="col-md-7 col-12">
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text"
                                   class="form-control border-start-0 ps-0"
                                   id="buscar-materia"
                                   placeholder="Buscar por código, nombre, periodo o docente...">
                            <button class="btn btn-outline-secondary" type="button" id="btn-recargar-materias" title="Recargar lista">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 620px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="tabla-materias">
                        <thead class="table-light sticky-top shadow-sm">
                            <tr class="small text-uppercase text-secondary">
                                <th class="ps-3 py-3" style="width: 110px;">Código</th>
                                <th class="py-3">Asignatura</th>
                                <th class="py-3">Docente</th>
                                <th class="text-center py-3" style="width: 100px;">Inscritos</th>
                                <th class="text-center py-3" style="width: 100px;">Estado</th>
                                <th class="text-center pe-3 py-3" style="width: 120px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-materias-body">
                            <!-- Filas inyectadas por JavaScript -->
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <div class="spinner-border text-danger" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <p class="mt-2 mb-0 small">Cargando asignaturas desde la base de datos...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pie de tarjeta con leyenda -->
            <div class="card-footer bg-light small py-2 px-3 text-muted d-flex justify-content-between align-items-center">
                <span><i class="bi bi-shield-check text-success me-1"></i>Sincronizado con Supabase PostgreSQL</span>
                <span id="hora-actualizacion-materias" class="text-secondary" style="font-size: 0.75rem;"></span>
            </div>
        </div>
    </div>

</div>

<!-- Lógica Frontend del Módulo de Materias (Fetch API + SweetAlert2) -->
<script>
document.addEventListener('DOMContentLoaded', () => {

    // URL base del controlador AJAX
    const AJAX_URL = 'controllers/materiaAjaxController.php';

    // Elementos del DOM - Formulario
    const inputIdMateria   = document.getElementById('id_materia');
    const inputCodigo      = document.getElementById('codigo');
    const inputNombre      = document.getElementById('nombre');
    const inputPeriodo     = document.getElementById('periodo');
    const selectDocente    = document.getElementById('id_docente');
    const switchActiva     = document.getElementById('activa');
    const badgeModo        = document.getElementById('badge-modo-materia');
    const btnGuardar       = document.getElementById('btn-guardar-materia');
    const btnModificar     = document.getElementById('btn-modificar-materia');
    const btnLimpiar       = document.getElementById('btn-limpiar-materia');

    // Elementos del DOM - Tabla y Buscador
    const inputBuscar      = document.getElementById('buscar-materia');
    const tablaBody        = document.getElementById('tabla-materias-body');
    const badgeTotal       = document.getElementById('badge-total-materias');
    const btnRecargar      = document.getElementById('btn-recargar-materias');
    const horaActualizacion = document.getElementById('hora-actualizacion-materias');

    const PERIODO_DEFECTO = '2026-2027';

    // Caché en memoria de materias para filtrado instantáneo
    let listaMateriasCache = [];

    // =========================================================================
    // 1. CARGA INICIAL
    // =========================================================================
    cargarDocentes();
    cargarMaterias();
    inputCodigo.focus();

    // =========================================================================
    // 2. CARGA DE DOCENTES (SELECTOR DINÁMICO)
    // =========================================================================
    async function cargarDocentes() {
        try {
            const resultado = await peticion(`${AJAX_URL}?accion=docentes`);

            selectDocente.innerHTML = '<option value="">— Sin docente asignado —</option>';
            (resultado.data || []).forEach(d => {
                const opcion = document.createElement('option');
                opcion.value = d.id;
                opcion.textContent = `${d.nombre_completo} (@${d.username})`;
                selectDocente.appendChild(opcion);
            });
        } catch (error) {
            console.error('Error al cargar docentes:', error);
            selectDocente.innerHTML = '<option value="">No fue posible cargar los docentes</option>';
        }
    }

    // =========================================================================
    // 3. CARGA Y RENDERIZADO DEL CATÁLOGO
    // =========================================================================
    async function cargarMaterias() {
        try {
            const resultado = await peticion(`${AJAX_URL}?accion=listar`);
            listaMateriasCache = Array.isArray(resultado.data) ? resultado.data : [];
            aplicarFiltro();
            horaActualizacion.textContent = 'Actualizado: ' + new Date().toLocaleTimeString('es-EC');
        } catch (error) {
            console.error('Error al cargar materias:', error);
            tablaBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <i class="bi bi-exclamation-triangle text-danger fs-2 d-block mb-2"></i>
                        <p class="fw-semibold text-dark mb-1">No fue posible cargar las asignaturas</p>
                        <p class="small text-muted mb-0">${escapeHtml(error.message || 'Verifique la conexión a Supabase en config/database.php')}</p>
                    </td>
                </tr>
            `;
        }
    }

    function renderizarTablaMaterias(materias) {
        badgeTotal.textContent = materias.length;

        if (materias.length === 0) {
            tablaBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="bi bi-journal-x fs-2 d-block mb-2"></i>
                        <span class="small">No se encontraron asignaturas.</span>
                    </td>
                </tr>
            `;
            return;
        }

        tablaBody.innerHTML = materias.map(m => `
            <tr class="${m.activa ? '' : 'table-light text-muted'}">
                <td class="ps-3 fw-semibold font-monospace text-secondary">${escapeHtml(m.codigo)}</td>
                <td>
                    <div class="fw-bold ${m.activa ? 'text-dark' : ''}">${escapeHtml(m.nombre)}</div>
                    <small class="text-muted"><i class="bi bi-calendar-range me-1"></i>Periodo ${escapeHtml(m.periodo)}</small>
                </td>
                <td>
                    ${m.docente
                        ? `<span class="small"><i class="bi bi-person-workspace text-danger me-1"></i>${escapeHtml(m.docente)}</span>`
                        : `<span class="badge bg-secondary-subtle text-secondary border">Sin asignar</span>`}
                </td>
                <td class="text-center">
                    <span class="badge bg-light text-dark border">${m.total_matriculados}</span>
                </td>
                <td class="text-center">
                    ${m.activa
                        ? '<span class="badge bg-success">Activa</span>'
                        : '<span class="badge bg-secondary">Inactiva</span>'}
                </td>
                <td class="text-center pe-3">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-warning btn-editar" data-id="${m.id}" title="Editar asignatura">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button type="button"
                                class="btn ${m.activa ? 'btn-outline-secondary' : 'btn-outline-success'} btn-estado"
                                data-id="${m.id}"
                                data-activa="${m.activa ? '1' : '0'}"
                                title="${m.activa ? 'Desactivar' : 'Activar'} asignatura">
                            <i class="bi ${m.activa ? 'bi-toggle-on' : 'bi-toggle-off'}"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // Delegación de eventos para los botones de la tabla
    tablaBody.addEventListener('click', (e) => {
        const btnEditar = e.target.closest('.btn-editar');
        const btnEstado = e.target.closest('.btn-estado');

        if (btnEditar) {
            const materia = listaMateriasCache.find(m => m.id === parseInt(btnEditar.dataset.id, 10));
            if (materia) cargarDatosEnFormulario(materia);
        }

        if (btnEstado) {
            const materia = listaMateriasCache.find(m => m.id === parseInt(btnEstado.dataset.id, 10));
            if (materia) confirmarCambioEstado(materia);
        }
    });

    // =========================================================================
    // 4. BUSCADOR EN TIEMPO REAL
    // =========================================================================
    inputBuscar.addEventListener('input', aplicarFiltro);
    btnRecargar.addEventListener('click', cargarMaterias);

    function aplicarFiltro() {
        const termino = inputBuscar.value.trim().toLowerCase();
        if (!termino) {
            renderizarTablaMaterias(listaMateriasCache);
            return;
        }

        const filtradas = listaMateriasCache.filter(m =>
            [m.codigo, m.nombre, m.periodo, m.docente].some(v => (v || '').toLowerCase().includes(termino))
        );
        renderizarTablaMaterias(filtradas);
    }

    // =========================================================================
    // 5. GUARDAR NUEVA ASIGNATURA
    // =========================================================================
    btnGuardar.addEventListener('click', async () => {
        const payload = recopilarDatosFormulario();
        if (!validarFormulario(payload)) return;

        try {
            deshabilitarBotones(true);
            const resultado = await peticion(AJAX_URL, { accion: 'guardar', ...payload });
            deshabilitarBotones(false);

            Swal.fire({
                icon: 'success',
                title: '¡Asignatura Registrada!',
                text: resultado.mensaje,
                confirmButtonColor: '#8B0000'
            });

            limpiarFormulario();
            cargarMaterias();
        } catch (error) {
            deshabilitarBotones(false);
            console.error('Error al guardar:', error);
            mostrarAlerta('error', 'Error al Guardar', error.message);
        }
    });

    // =========================================================================
    // 6. MODIFICAR ASIGNATURA
    // =========================================================================
    btnModificar.addEventListener('click', async () => {
        const id = parseInt(inputIdMateria.value, 10);
        if (!id || id <= 0) {
            mostrarAlerta('warning', 'Atención', 'No se ha seleccionado una asignatura válida para modificar.');
            return;
        }

        const payload = recopilarDatosFormulario();
        if (!validarFormulario(payload)) return;

        try {
            deshabilitarBotones(true);
            const resultado = await peticion(AJAX_URL, { accion: 'modificar', id: id, ...payload });
            deshabilitarBotones(false);

            Swal.fire({
                icon: 'success',
                title: '¡Asignatura Actualizada!',
                text: resultado.mensaje,
                confirmButtonColor: '#8B0000'
            });

            limpiarFormulario();
            cargarMaterias();
        } catch (error) {
            deshabilitarBotones(false);
            console.error('Error al modificar:', error);
            mostrarAlerta('error', 'Error al Modificar', error.message);
        }
    });

    // =========================================================================
    // 7. ACTIVAR / DESACTIVAR ASIGNATURA
    // =========================================================================
    function confirmarCambioEstado(materia) {
        const activar = !materia.activa;

        Swal.fire({
            icon: 'question',
            title: activar ? '¿Activar asignatura?' : '¿Desactivar asignatura?',
            html: activar
                ? `<b>${escapeHtml(materia.codigo)} - ${escapeHtml(materia.nombre)}</b> volverá a estar disponible para matriculación y asistencia.`
                : `<b>${escapeHtml(materia.codigo)} - ${escapeHtml(materia.nombre)}</b> dejará de mostrarse en matriculación. Sus matrículas y asistencias se conservan.`,
            showCancelButton: true,
            confirmButtonText: activar ? 'Sí, activar' : 'Sí, desactivar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#8B0000'
        }).then(async (r) => {
            if (!r.isConfirmed) return;

            try {
                const resultado = await peticion(AJAX_URL, { accion: 'cambiar_estado', id: materia.id, activa: activar });
                mostrarToast('success', resultado.mensaje);

                if (parseInt(inputIdMateria.value, 10) === materia.id) {
                    switchActiva.checked = activar;
                }
                cargarMaterias();
            } catch (error) {
                console.error('Error al cambiar estado:', error);
                mostrarAlerta('error', 'Error', error.message);
            }
        });
    }

    // =========================================================================
    // 8. FORMULARIO: RECOPILAR, VALIDAR, CARGAR Y LIMPIAR
    // =========================================================================
    btnLimpiar.addEventListener('click', () => limpiarFormulario());

    inputCodigo.addEventListener('input', () => {
        inputCodigo.value = inputCodigo.value.toUpperCase();
    });

    function recopilarDatosFormulario() {
        return {
            codigo:     inputCodigo.value.trim().toUpperCase(),
            nombre:     inputNombre.value.trim(),
            periodo:    inputPeriodo.value.trim(),
            id_docente: selectDocente.value ? parseInt(selectDocente.value, 10) : null,
            activa:     switchActiva.checked
        };
    }

    function validarFormulario(datos) {
        if (!/^[A-Z0-9][A-Z0-9-]{1,19}$/.test(datos.codigo)) {
            mostrarAlerta('warning', 'Código inválido', 'Use entre 2 y 20 caracteres: letras, números y guion. Ej: SIS-501.');
            inputCodigo.focus();
            return false;
        }

        if (!datos.nombre) {
            mostrarAlerta('warning', 'Campo requerido', 'Ingrese el nombre de la asignatura.');
            inputNombre.focus();
            return false;
        }

        const periodo = datos.periodo.match(/^(\d{4})-(\d{4})$/);
        if (!periodo || parseInt(periodo[2], 10) !== parseInt(periodo[1], 10) + 1) {
            mostrarAlerta('warning', 'Periodo inválido', 'Use el formato AAAA-AAAA con años consecutivos. Ej: 2026-2027.');
            inputPeriodo.focus();
            return false;
        }

        return true;
    }

    function cargarDatosEnFormulario(materia) {
        inputIdMateria.value = materia.id;
        inputCodigo.value    = materia.codigo;
        inputNombre.value    = materia.nombre;
        inputPeriodo.value   = materia.periodo;
        switchActiva.checked = materia.activa;

        // Si el docente asignado ya no está activo, se muestra igualmente en el selector
        const idDocente = materia.id_docente ? String(materia.id_docente) : '';
        if (idDocente && !selectDocente.querySelector(`option[value="${idDocente}"]`)) {
            const opcion = document.createElement('option');
            opcion.value = idDocente;
            opcion.textContent = `${materia.docente || 'Docente'} (inactivo)`;
            selectDocente.appendChild(opcion);
        }
        selectDocente.value = idDocente;

        badgeModo.textContent = 'Editando ' + materia.codigo;
        badgeModo.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1';
        btnGuardar.classList.add('d-none');
        btnModificar.classList.remove('d-none');

        inputNombre.focus();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function limpiarFormulario() {
        inputIdMateria.value = '';
        inputCodigo.value    = '';
        inputNombre.value    = '';
        inputPeriodo.value   = PERIODO_DEFECTO;
        selectDocente.value  = '';
        switchActiva.checked = true;

        badgeModo.textContent = 'Nuevo Registro';
        badgeModo.className = 'badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1';
        btnGuardar.classList.remove('d-none');
        btnModificar.classList.add('d-none');

        inputCodigo.focus();
    }

    function deshabilitarBotones(deshabilitar) {
        btnGuardar.disabled = deshabilitar;
        btnModificar.disabled = deshabilitar;
        btnLimpiar.disabled = deshabilitar;
    }

    // =========================================================================
    // 9. COMUNICACIÓN CON EL SERVIDOR
    // =========================================================================

    /**
     * Ejecuta una petición al controlador AJAX. Sin cuerpo realiza GET; con
     * cuerpo realiza POST JSON. Si la sesión expiró redirige al login.
     */
    async function peticion(url, cuerpo = null) {
        const opciones = cuerpo
            ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(cuerpo) }
            : {};

        const respuesta = await fetch(url, opciones);

        if (respuesta.status === 401) {
            window.location.href = 'index.php?action=login';
            throw new Error('Su sesión ha expirado.');
        }

        const resultado = await respuesta.json();
        if (resultado.status !== 'ok') {
            throw new Error(resultado.mensaje || 'Error en la operación.');
        }
        return resultado;
    }

    // =========================================================================
    // 10. ALERTAS Y TOASTS DE SWEETALERT2
    // =========================================================================
    function mostrarAlerta(icon, title, text) {
        Swal.fire({
            icon: icon,
            title: title,
            text: text,
            confirmButtonColor: '#8B0000'
        });
    }

    function mostrarToast(icon, title) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true
        });

        Toast.fire({ icon: icon, title: title });
    }

    function escapeHtml(texto) {
        if (texto === null || texto === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(texto);
        return div.innerHTML;
    }
});
</script>
