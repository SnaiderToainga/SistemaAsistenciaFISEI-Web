/**
 * ==============================================================================
 * Sistema de Control de Asistencia FISEI - Web
 * Lógica Frontend del Módulo de Matriculación (Fetch API + SweetAlert2)
 * ==============================================================================
 */

document.addEventListener('DOMContentLoaded', () => {

    // URL base del controlador AJAX
    const AJAX_URL = 'controllers/estudianteAjaxController.php';

    // Elementos del DOM - Formulario
    const formEstudiante        = document.getElementById('form-estudiante');
    const inputIdEstudiante     = document.getElementById('id_estudiante');
    const inputUidRfid          = document.getElementById('uid_rfid');
    const inputCedula           = document.getElementById('cedula');
    const inputNombres          = document.getElementById('nombres');
    const inputApellidos        = document.getElementById('apellidos');
    const selectCarrera         = document.getElementById('carrera');
    const contenedorMaterias    = document.getElementById('contenedor-materias');
    const badgeContadorMaterias = document.getElementById('badge-contador-materias');
    const badgeModo             = document.getElementById('badge-modo');
    const btnGuardar            = document.getElementById('btn-guardar');
    const btnModificar          = document.getElementById('btn-modificar');
    const btnLimpiar            = document.getElementById('btn-limpiar');
    const btnFocusRfid          = document.getElementById('btn-focus-rfid');
    const btnMarcarTodas        = document.getElementById('btn-marcar-todas');
    const btnDesmarcarTodas     = document.getElementById('btn-desmarcar-todas');

    // Elementos del DOM - Tabla y Buscador
    const inputBuscar           = document.getElementById('buscar-estudiante');
    const tablaBody             = document.getElementById('tabla-estudiantes-body');
    const badgeTotalEstudiantes = document.getElementById('badge-total-estudiantes');
    const btnRecargar           = document.getElementById('btn-recargar');
    const horaActualizacion     = document.getElementById('hora-actualizacion');

    // Caché en memoria de estudiantes para filtrado instantáneo
    let listaEstudiantesCache = [];

    // =========================================================================
    // 1. CARGA INICIAL
    // =========================================================================
    cargarMateriasActivas();
    cargarEstudiantes();

    // Re-enfocar automáticamente el campo RFID
    if (inputUidRfid) {
        inputUidRfid.focus();
    }

    // =========================================================================
    // 2. LECTURA RFID (USB emula Enter automáticamente)
    // =========================================================================
    inputUidRfid.addEventListener('keydown', async (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const uid = inputUidRfid.value.trim();

            if (!uid) {
                mostrarToast('warning', 'Ingrese o escanee un llavero RFID.');
                return;
            }

            await procesarLecturaRfid(uid);
        }
    });

    if (btnFocusRfid) {
        btnFocusRfid.addEventListener('click', () => {
            inputUidRfid.focus();
            inputUidRfid.select();
        });
    }

    /**
     * Consulta al backend si el UID escaneado ya pertenece a un alumno.
     */
    async function procesarLecturaRfid(uid) {
        try {
            mostrarCargandoBoton(true);

            const respuesta = await fetch(`${AJAX_URL}?accion=buscar_rfid&uid_rfid=${encodeURIComponent(uid)}`);
            const resultado = await respuesta.json();

            mostrarCargandoBoton(false);

            if (resultado.status === 'ok' && resultado.data) {
                // Estudiante registrado -> Cargar datos en el formulario para modificación
                const est = resultado.data;
                cargarDatosEnFormulario(est);

                Swal.fire({
                    icon: 'info',
                    title: 'Estudiante Encontrado',
                    html: `<b>${est.nombres} ${est.apellidos}</b><br><small class="text-muted">Cédula: ${est.cedula}</small>`,
                    timer: 2000,
                    showConfirmButton: false,
                    timerProgressBar: true
                });

            } else if (resultado.status === 'not_found') {
                // Llavero nuevo -> Preparar para registro
                const uidActual = inputUidRfid.value.trim();
                limpiarFormulario(false); // Mantener el UID escaneado
                inputUidRfid.value = uidActual;

                mostrarToast('info', 'Llavero nuevo detectado. Complete los datos.');
                inputCedula.focus();

            } else {
                throw new Error(resultado.mensaje || 'Error al buscar el llavero.');
            }

        } catch (error) {
            mostrarCargandoBoton(false);
            console.error('Error RFID:', error);
            mostrarAlerta('error', 'Error al consultar RFID', error.message || 'No se pudo comunicar con el servidor.');
        }
    }

    // =========================================================================
    // 3. CARGA DE ASIGNATURAS ACTIVAS (CHECKBOXES DINÁMICOS)
    // =========================================================================
    async function cargarMateriasActivas() {
        try {
            const respuesta = await fetch(`${AJAX_URL}?accion=materias`);
            const resultado = await respuesta.json();

            if (resultado.status === 'ok' && Array.isArray(resultado.data)) {
                renderizarMateriasCheckboxes(resultado.data);
            } else {
                contenedorMaterias.innerHTML = `
                    <div class="text-danger small p-2">
                        <i class="bi bi-exclamation-triangle me-1"></i>No fue posible cargar las asignaturas.
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error al cargar materias:', error);
            contenedorMaterias.innerHTML = `
                <div class="text-danger small p-2">
                    <i class="bi bi-exclamation-circle me-1"></i>Error de conexión con la base de datos de materias.
                </div>
            `;
        }
    }

    function renderizarMateriasCheckboxes(materias) {
        if (materias.length === 0) {
            contenedorMaterias.innerHTML = `
                <div class="text-muted small p-2 text-center">
                    No hay asignaturas activas registradas para este periodo.
                </div>
            `;
            return;
        }

        let html = '';
        materias.forEach(m => {
            html += `
                <div class="form-check py-1 border-bottom border-light">
                    <input class="form-check-input check-materia" 
                           type="checkbox" 
                           value="${m.id}" 
                           id="materia_${m.id}">
                    <label class="form-check-label small" for="materia_${m.id}">
                        <strong class="text-dark">${escapeHtml(m.codigo)}</strong> - ${escapeHtml(m.nombre)}
                        <span class="badge bg-light text-secondary border ms-1" style="font-size: 0.68rem;">${escapeHtml(m.periodo || '2026')}</span>
                    </label>
                </div>
            `;
        });

        contenedorMaterias.innerHTML = html;

        // Escuchar cambios para actualizar el contador
        document.querySelectorAll('.check-materia').forEach(cb => {
            cb.addEventListener('change', actualizarContadorMaterias);
        });

        actualizarContadorMaterias();
    }

    function actualizarContadorMaterias() {
        const seleccionadas = document.querySelectorAll('.check-materia:checked').length;
        badgeContadorMaterias.textContent = `${seleccionadas} seleccionada${seleccionadas === 1 ? '' : 's'}`;
        badgeContadorMaterias.className = seleccionadas > 0 
            ? 'badge bg-danger-subtle text-danger border border-danger-subtle small' 
            : 'badge bg-secondary-subtle text-secondary small';
    }

    // Botones de selección masiva de materias
    if (btnMarcarTodas) {
        btnMarcarTodas.addEventListener('click', () => {
            document.querySelectorAll('.check-materia').forEach(cb => cb.checked = true);
            actualizarContadorMaterias();
        });
    }

    if (btnDesmarcarTodas) {
        btnDesmarcarTodas.addEventListener('click', () => {
            document.querySelectorAll('.check-materia').forEach(cb => cb.checked = false);
            actualizarContadorMaterias();
        });
    }

    // =========================================================================
    // 4. CARGA Y RENDERIZADO DEL LISTADO DE ESTUDIANTES
    // =========================================================================
    async function cargarEstudiantes() {
        try {
            tablaBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-5 text-muted">
                        <div class="spinner-border spinner-border-sm text-danger me-2" role="status"></div>
                        Consultando estudiantes en Supabase...
                    </td>
                </tr>
            `;

            const respuesta = await fetch(`${AJAX_URL}?accion=listar`);
            const resultado = await respuesta.json();

            if (resultado.status === 'ok' && Array.isArray(resultado.data)) {
                listaEstudiantesCache = resultado.data;
                renderizarTablaEstudiantes(listaEstudiantesCache);
                if (horaActualizacion) {
                    horaActualizacion.textContent = `Actualizado: ${new Date().toLocaleTimeString()}`;
                }
            } else {
                throw new Error(resultado.mensaje || 'Error al listar estudiantes.');
            }
        } catch (error) {
            console.error('Error al listar estudiantes:', error);
            tablaBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-danger">
                        <i class="bi bi-exclamation-triangle fs-4 d-block mb-2"></i>
                        <strong>No se pudo cargar la nómina de estudiantes</strong>
                        <p class="small text-muted mb-0">${escapeHtml(error.message || 'Verifique la conexión a Supabase en config/database.php')}</p>
                    </td>
                </tr>
            `;
            badgeTotalEstudiantes.textContent = '0';
        }
    }

    function renderizarTablaEstudiantes(estudiantes) {
        badgeTotalEstudiantes.textContent = estudiantes.length;

        if (estudiantes.length === 0) {
            tablaBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-5 text-muted">
                        <i class="bi bi-people fs-1 text-secondary opacity-50 d-block mb-2"></i>
                        No hay estudiantes registrados aún. Acerque un llavero para comenzar.
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        estudiantes.forEach(est => {
            // Badges para materias asignadas
            let materiasHtml = '';
            if (est.materias && est.materias.trim() !== '') {
                const listaMaterias = est.materias.split(', ');
                materiasHtml = listaMaterias.map(m => 
                    `<span class="badge bg-secondary-subtle text-dark border me-1 mb-1" style="font-size: 0.72rem;">${escapeHtml(m)}</span>`
                ).join('');
            } else {
                materiasHtml = `<span class="badge bg-warning-subtle text-warning-emphasis" style="font-size: 0.72rem;">Sin materias asignadas</span>`;
            }

            html += `
                <tr id="fila-estudiante-${est.id}">
                    <td class="ps-3 fw-semibold font-monospace text-secondary">${escapeHtml(est.cedula)}</td>
                    <td>
                        <div class="fw-bold text-dark">${escapeHtml(est.apellidos)} ${escapeHtml(est.nombres)}</div>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            <i class="bi bi-mortarboard me-1"></i>${escapeHtml(est.carrera || 'Software')}
                        </small>
                    </td>
                    <td>
                        <span class="badge badge-rfid rounded-pill">
                            <i class="bi bi-broadcast me-1 text-danger"></i>${escapeHtml(est.uid_rfid)}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex flex-wrap align-items-center">
                            ${materiasHtml}
                        </div>
                    </td>
                    <td class="text-center pe-3">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" 
                                    class="btn btn-outline-primary btn-accion-editar" 
                                    data-id="${est.id}" 
                                    title="Editar estudiante y materias">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" 
                                    class="btn btn-outline-danger btn-accion-eliminar" 
                                    data-id="${est.id}" 
                                    data-nombre="${escapeHtml(est.nombres + ' ' + est.apellidos)}" 
                                    title="Eliminar registro">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        tablaBody.innerHTML = html;

        // Eventos a los botones de Editar
        document.querySelectorAll('.btn-accion-editar').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                const estudiante = listaEstudiantesCache.find(e => parseInt(e.id, 10) === id);
                if (estudiante) {
                    cargarDatosEnFormulario(estudiante);
                    mostrarToast('info', 'Modo edición activado.');
                }
            });
        });

        // Eventos a los botones de Eliminar
        document.querySelectorAll('.btn-accion-eliminar').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                const nombre = btn.getAttribute('data-nombre');
                confirmarEliminacion(id, nombre);
            });
        });
    }

    // Buscador en tiempo real
    if (inputBuscar) {
        inputBuscar.addEventListener('input', (e) => {
            const termino = e.target.value.toLowerCase().trim();

            if (!termino) {
                renderizarTablaEstudiantes(listaEstudiantesCache);
                return;
            }

            const filtrados = listaEstudiantesCache.filter(est => {
                const cedula    = (est.cedula || '').toLowerCase();
                const nombres   = (est.nombres || '').toLowerCase();
                const apellidos = (est.apellidos || '').toLowerCase();
                const rfid      = (est.uid_rfid || '').toLowerCase();
                const materias  = (est.materias || '').toLowerCase();
                const carrera   = (est.carrera || '').toLowerCase();

                return cedula.includes(termino) ||
                       nombres.includes(termino) ||
                       apellidos.includes(termino) ||
                       rfid.includes(termino) ||
                       materias.includes(termino) ||
                       carrera.includes(termino);
            });

            renderizarTablaEstudiantes(filtrados);
        });
    }

    if (btnRecargar) {
        btnRecargar.addEventListener('click', () => {
            cargarEstudiantes();
            mostrarToast('success', 'Nómina actualizada.');
        });
    }

    // =========================================================================
    // 5. REGISTRAR ESTUDIANTE (GUARDAR)
    // =========================================================================
    btnGuardar.addEventListener('click', async () => {
        const payload = recopilarDatosFormulario();
        if (!validarFormulario(payload)) return;

        try {
            deshabilitarBotones(true);

            const respuesta = await fetch(AJAX_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    accion: 'guardar',
                    ...payload
                })
            });

            const resultado = await respuesta.json();
            deshabilitarBotones(false);

            if (resultado.status === 'ok') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Estudiante Registrado!',
                    text: resultado.mensaje || 'Estudiante matriculado satisfactoriamente.',
                    confirmButtonColor: '#8B0000'
                });

                limpiarFormulario();
                cargarEstudiantes();
            } else {
                throw new Error(resultado.mensaje || 'No se pudo guardar el registro.');
            }

        } catch (error) {
            deshabilitarBotones(false);
            console.error('Error al guardar:', error);
            mostrarAlerta('error', 'Error al Guardar', error.message);
        }
    });

    // =========================================================================
    // 6. MODIFICAR ESTUDIANTE
    // =========================================================================
    btnModificar.addEventListener('click', async () => {
        const id = parseInt(inputIdEstudiante.value, 10);
        if (!id || id <= 0) {
            mostrarAlerta('warning', 'Atención', 'No se ha seleccionado un estudiante válido para modificar.');
            return;
        }

        const payload = recopilarDatosFormulario();
        if (!validarFormulario(payload)) return;

        try {
            deshabilitarBotones(true);

            const respuesta = await fetch(AJAX_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    accion: 'modificar',
                    id: id,
                    ...payload
                })
            });

            const resultado = await respuesta.json();
            deshabilitarBotones(false);

            if (resultado.status === 'ok') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Actualización Exitosa!',
                    text: resultado.mensaje || 'Datos y matrículas modificados correctamente.',
                    confirmButtonColor: '#8B0000'
                });

                limpiarFormulario();
                cargarEstudiantes();
            } else {
                throw new Error(resultado.mensaje || 'No se pudo modificar el registro.');
            }

        } catch (error) {
            deshabilitarBotones(false);
            console.error('Error al modificar:', error);
            mostrarAlerta('error', 'Error al Modificar', error.message);
        }
    });

    // =========================================================================
    // 7. ELIMINAR ESTUDIANTE
    // =========================================================================
    function confirmarEliminacion(id, nombre) {
        Swal.fire({
            title: '¿Eliminar a este estudiante?',
            html: `Se removerá permanentemente a <b>${escapeHtml(nombre)}</b> junto con todas sus matrículas y asistencias.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-trash3 me-1"></i>Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const respuesta = await fetch(AJAX_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            accion: 'eliminar',
                            id: id
                        })
                    });

                    const resultado = await respuesta.json();

                    if (resultado.status === 'ok') {
                        mostrarToast('success', 'Estudiante eliminado.');
                        // Si el estudiante eliminado estaba cargado en el formulario, limpiarlo
                        if (parseInt(inputIdEstudiante.value, 10) === id) {
                            limpiarFormulario();
                        }
                        cargarEstudiantes();
                    } else {
                        throw new Error(resultado.mensaje || 'Error al eliminar el estudiante.');
                    }
                } catch (error) {
                    console.error('Error al eliminar:', error);
                    mostrarAlerta('error', 'Error al Eliminar', error.message);
                }
            }
        });
    }

    // =========================================================================
    // 8. BOTÓN LIMPIAR / NUEVO
    // =========================================================================
    btnLimpiar.addEventListener('click', () => {
        limpiarFormulario(true);
        mostrarToast('info', 'Formulario restablecido.');
    });

    // =========================================================================
    // 9. FUNCIONES AUXILIARES DE FORMULARIO
    // =========================================================================
    function recopilarDatosFormulario() {
        const materiasSeleccionadas = Array.from(document.querySelectorAll('.check-materia:checked'))
            .map(cb => parseInt(cb.value, 10));

        return {
            uid_rfid: inputUidRfid.value.trim(),
            cedula: inputCedula.value.trim(),
            nombres: inputNombres.value.trim(),
            apellidos: inputApellidos.value.trim(),
            carrera: selectCarrera.value,
            materias: materiasSeleccionadas
        };
    }

    function validarFormulario(datos) {
        if (!datos.uid_rfid) {
            mostrarAlerta('warning', 'Campo Obligatorio', 'Debe acercar el llavero RFID al lector o ingresarlo manualmente.');
            inputUidRfid.focus();
            return false;
        }

        if (!datos.cedula) {
            mostrarAlerta('warning', 'Campo Obligatorio', 'Ingrese el número de cédula del estudiante.');
            inputCedula.focus();
            return false;
        }

        // Validación estricta de 10 dígitos numéricos
        const regexCedula = /^[0-9]{10}$/;
        if (!regexCedula.test(datos.cedula)) {
            mostrarAlerta('warning', 'Cédula Inválida', 'La cédula debe constar de exactamente 10 dígitos numéricos.');
            inputCedula.focus();
            return false;
        }

        if (!datos.nombres) {
            mostrarAlerta('warning', 'Campo Obligatorio', 'Ingrese los nombres del estudiante.');
            inputNombres.focus();
            return false;
        }

        if (!datos.apellidos) {
            mostrarAlerta('warning', 'Campo Obligatorio', 'Ingrese los apellidos del estudiante.');
            inputApellidos.focus();
            return false;
        }

        return true;
    }

    function cargarDatosEnFormulario(est) {
        inputIdEstudiante.value = est.id;
        inputUidRfid.value      = est.uid_rfid || '';
        inputCedula.value       = est.cedula || '';
        inputNombres.value      = est.nombres || '';
        inputApellidos.value    = est.apellidos || '';
        selectCarrera.value     = est.carrera || 'Software';

        // Marcar asignaturas inscritas
        const idsMatriculadas = Array.isArray(est.materias_ids) ? est.materias_ids.map(Number) : [];
        document.querySelectorAll('.check-materia').forEach(cb => {
            const val = parseInt(cb.value, 10);
            cb.checked = idsMatriculadas.includes(val);
        });

        actualizarContadorMaterias();

        // Cambiar a Modo Edición
        badgeModo.textContent = `Modo: Edición (ID: ${est.id})`;
        badgeModo.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1';

        btnGuardar.classList.add('d-none');
        btnModificar.classList.remove('d-none');

        // Scroll suave al formulario en dispositivos pequeños
        if (window.innerWidth < 992) {
            formEstudiante.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function limpiarFormulario(limpiarRfid = true) {
        inputIdEstudiante.value = '';
        if (limpiarRfid) {
            inputUidRfid.value = '';
        }
        inputCedula.value       = '';
        inputNombres.value      = '';
        inputApellidos.value    = '';
        selectCarrera.selectedIndex = 0;

        // Desmarcar todas las materias
        document.querySelectorAll('.check-materia').forEach(cb => cb.checked = false);
        actualizarContadorMaterias();

        // Restablecer modo
        badgeModo.textContent = 'Nuevo Registro';
        badgeModo.className = 'badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1';

        btnGuardar.classList.remove('d-none');
        btnModificar.classList.add('d-none');

        // Enfocar RFID
        inputUidRfid.focus();
    }

    function deshabilitarBotones(deshabilitar) {
        btnGuardar.disabled = deshabilitar;
        btnModificar.disabled = deshabilitar;
        btnLimpiar.disabled = deshabilitar;
    }

    function mostrarCargandoBoton(cargando) {
        if (cargando) {
            btnGuardar.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status"></span>Procesando...`;
            btnModificar.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status"></span>Actualizando...`;
        } else {
            btnGuardar.innerHTML = `<i class="bi bi-save me-1"></i>Guardar`;
            btnModificar.innerHTML = `<i class="bi bi-pencil-square me-1"></i>Modificar`;
        }
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
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        Toast.fire({
            icon: icon,
            title: title
        });
    }

    function escapeHtml(texto) {
        if (!texto) return '';
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }
});
