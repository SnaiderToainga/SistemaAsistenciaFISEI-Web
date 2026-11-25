document.addEventListener('DOMContentLoaded', () => {
    const AJAX_URL = 'controllers/asistenciaAjaxController.php';

    const selectMateria        = document.getElementById('select-materia');
    const selectParcial        = document.getElementById('select-parcial');
    const inputFecha           = document.getElementById('input-fecha');
    const selectFechaHistorial = document.getElementById('select-fecha-historial');
    const inputUidRfid         = document.getElementById('uid_rfid');
    const badgeParcial        = document.getElementById('badge-parcial');
    const resumenPresentes    = document.getElementById('resumen-presentes');
    const resumenAtrasos      = document.getElementById('resumen-atrasos');
    const resumenFaltas       = document.getElementById('resumen-faltas');
    const resumenJustificados = document.getElementById('resumen-justificados');
    const btnMarcarFaltas     = document.getElementById('btn-marcar-faltas');
    const badgeTotal          = document.getElementById('badge-total-asistencia');
    const inputBuscar         = document.getElementById('buscar-asistencia');
    const btnRecargar         = document.getElementById('btn-recargar');
    const tablaBody           = document.getElementById('tabla-asistencia-body');
    const horaActualizacion   = document.getElementById('hora-actualizacion');

    let listaAsistencia = [];
    let procesando = false;
    let resaltado = null;
    let timerResaltado = null;

    cargarMaterias();

    function enfocarRfid() {
        if (!inputUidRfid || inputUidRfid.disabled) return;
        if (typeof Swal !== 'undefined' && Swal.isVisible()) return;
        inputUidRfid.focus({ preventScroll: true });
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('input, select, textarea, button, a, label, .swal2-container')) {
            enfocarRfid();
        }
    });

    async function cargarMaterias() {
        try {
            const res = await api(`${AJAX_URL}?accion=materias`);
            if (!res) return;
            if (res.status !== 'ok' || !Array.isArray(res.data)) {
                throw new Error(res.mensaje || 'No se pudieron cargar las asignaturas.');
            }

            if (res.data.length === 0) {
                selectMateria.innerHTML = '<option value="">Sin asignaturas asignadas</option>';
                selectMateria.disabled = true;
                inputUidRfid.disabled = true;
                btnMarcarFaltas.disabled = true;
                actualizarResumen([]);
                mostrarTablaMensaje('No tiene asignaturas activas asignadas.');
                return;
            }

            selectMateria.disabled = false;
            inputUidRfid.disabled = false;
            btnMarcarFaltas.disabled = false;

            selectMateria.innerHTML = res.data
                .map(m => `<option value="${m.id}">${escapeHtml(m.codigo)} - ${escapeHtml(m.nombre)}</option>`)
                .join('');

            selectMateria.value = String(res.data[0].id);
            await Promise.all([cargarAsistencias(), cargarFechasRegistradas()]);
            enfocarRfid();
        } catch (error) {
            selectMateria.innerHTML = '<option value="">Error al cargar</option>';
            mostrarTablaMensaje(error.message, true);
        }
    }

    selectMateria.addEventListener('change', async () => {
        await Promise.all([cargarAsistencias(), cargarFechasRegistradas()]);
        enfocarRfid();
    });

    inputFecha.addEventListener('change', async () => {
        sincronizarFechaHistorial();
        await cargarAsistencias();
        enfocarRfid();
    });

    selectFechaHistorial.addEventListener('change', async () => {
        if (!selectFechaHistorial.value) return;
        inputFecha.value = selectFechaHistorial.value;
        await cargarAsistencias();
        enfocarRfid();
    });

    selectParcial.addEventListener('change', async () => {
        badgeParcial.textContent = selectParcial.value;
        await Promise.all([cargarAsistencias(), cargarFechasRegistradas()]);
        enfocarRfid();
    });

    btnRecargar.addEventListener('click', async () => {
        await Promise.all([cargarAsistencias(), cargarFechasRegistradas()]);
        mostrarToast('success', 'Lista actualizada.');
        enfocarRfid();
    });

    inputUidRfid.addEventListener('keydown', async (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        if (procesando) return;

        const uid = inputUidRfid.value.trim();
        inputUidRfid.value = '';
        if (!uid) {
            enfocarRfid();
            return;
        }

        const idMateria = parseInt(selectMateria.value, 10);
        const fecha     = inputFecha.value;
        const parcial   = selectParcial.value;

        if (!idMateria || !fecha) {
            Swal.fire({
                icon: 'warning',
                title: 'Datos incompletos',
                text: 'Seleccione una asignatura y fecha válida.',
                confirmButtonColor: '#8B0000',
                returnFocus: false,
                didClose: enfocarRfid
            });
            return;
        }

        procesando = true;
        inputUidRfid.readOnly = true;

        try {
            const res = await api(AJAX_URL, {
                accion:     'marcar_rfid',
                uid_rfid:   uid,
                id_materia: idMateria,
                fecha:      fecha,
                parcial:    parcial
            });
            if (!res) return;

            const reg = res.data || null;

            if (res.status === 'ok' && reg) {
                actualizarEnCache(reg, true);
                resaltarFila(reg.id_estudiante, 'table-success');
                cargarFechasRegistradas();

                Swal.fire({
                    icon: 'success',
                    title: 'Presente registrado',
                    html: `<b>${escapeHtml(reg.nombres)} ${escapeHtml(reg.apellidos)}</b><br><small class="text-muted">${escapeHtml(reg.cedula)} &bull; ${escapeHtml(reg.hora || '')}</small>`,
                    timer: 1800,
                    showConfirmButton: false,
                    timerProgressBar: true,
                    returnFocus: false,
                    didClose: enfocarRfid
                });
            } else if (res.status === 'duplicado') {
                if (reg && reg.parcial === parcial) {
                    actualizarEnCache(reg, false);
                    resaltarFila(reg.id_estudiante, 'table-warning');
                }
                Swal.fire({
                    icon: 'warning',
                    title: 'Asistencia ya registrada',
                    text: res.mensaje,
                    timer: 2200,
                    showConfirmButton: false,
                    timerProgressBar: true,
                    returnFocus: false,
                    didClose: enfocarRfid
                });
            } else if (res.status === 'no_matriculado' || res.status === 'not_found') {
                Swal.fire({
                    icon: 'error',
                    title: res.status === 'no_matriculado' ? 'No matriculado' : 'Código no registrado',
                    text: res.mensaje,
                    timer: 2500,
                    showConfirmButton: false,
                    timerProgressBar: true,
                    returnFocus: false,
                    didClose: enfocarRfid
                });
            } else {
                throw new Error(res.mensaje || 'Error al registrar asistencia.');
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'No se pudo procesar el registro.',
                confirmButtonColor: '#8B0000',
                returnFocus: false,
                didClose: enfocarRfid
            });
        } finally {
            procesando = false;
            inputUidRfid.readOnly = false;
            enfocarRfid();
        }
    });

    async function cargarAsistencias() {
        const idMateria = parseInt(selectMateria.value, 10);
        const fecha     = inputFecha.value;
        const parcial   = selectParcial.value;

        if (!idMateria || !fecha) {
            listaAsistencia = [];
            actualizarResumen([]);
            mostrarTablaMensaje('Seleccione asignatura y fecha.');
            return;
        }

        try {
            tablaBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                        <span class="small">Cargando...</span>
                    </td>
                </tr>
            `;

            const res = await api(`${AJAX_URL}?accion=listar&id_materia=${encodeURIComponent(idMateria)}&fecha=${encodeURIComponent(fecha)}&parcial=${encodeURIComponent(parcial)}`);
            if (!res) return;
            if (res.status !== 'ok' || !Array.isArray(res.data)) {
                throw new Error(res.mensaje || 'No se pudo cargar la lista.');
            }

            listaAsistencia = res.data;
            actualizarResumen(listaAsistencia);
            aplicarFiltro();
            actualizarHora();
        } catch (error) {
            listaAsistencia = [];
            actualizarResumen([]);
            mostrarTablaMensaje(error.message, true);
        }
    }

    function aplicarFiltro() {
        const termino = (inputBuscar.value || '').toLowerCase().trim();
        if (!termino) {
            renderizarTabla(listaAsistencia);
            return;
        }

        const filtrados = listaAsistencia.filter(r => {
            const estado = (r.estado || 'pendiente').toLowerCase();
            return (r.cedula || '').toLowerCase().includes(termino) ||
                   (r.nombres || '').toLowerCase().includes(termino) ||
                   (r.apellidos || '').toLowerCase().includes(termino) ||
                   (r.uid_rfid || '').toLowerCase().includes(termino) ||
                   estado.includes(termino) ||
                   (r.observacion || '').toLowerCase().includes(termino);
        });

        renderizarTabla(filtrados);
    }

    inputBuscar.addEventListener('input', aplicarFiltro);

    function renderizarTabla(registros) {
        badgeTotal.textContent = registros.length;

        if (registros.length === 0) {
            mostrarTablaMensaje(listaAsistencia.length === 0 ? 'Sin estudiantes matriculados.' : 'Sin coincidencias.');
            return;
        }

        tablaBody.innerHTML = registros.map(r => {
            const claseFila = (resaltado && resaltado.id === r.id_estudiante) ? resaltado.clase : '';
            const horaHtml  = r.hora
                ? `<div class="font-monospace small" style="font-variant-numeric: tabular-nums;">${escapeHtml(r.hora)}</div>
                   <small class="text-muted" style="font-size: 0.72rem;">${escapeHtml(r.parcial || '')}</small>`
                : '<span class="text-muted">—</span>';
            const obsHtml   = r.observacion ? `<span class="small">${escapeHtml(r.observacion)}</span>` : '<span class="text-muted small">—</span>';

            return `
                <tr id="fila-asistencia-${r.id_estudiante}" class="${claseFila}">
                    <td class="ps-3 font-monospace text-secondary small" style="font-variant-numeric: tabular-nums;">${escapeHtml(r.cedula)}</td>
                    <td>
                        <div class="fw-semibold text-dark">${escapeHtml(r.apellidos)} ${escapeHtml(r.nombres)}</div>
                        <small class="text-muted font-monospace" style="font-size: 0.75rem;">${escapeHtml(r.uid_rfid)}</small>
                    </td>
                    <td>${horaHtml}</td>
                    <td class="text-center">${badgeEstado(r.estado)}</td>
                    <td>${obsHtml}</td>
                    <td class="text-center pe-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-editar-estado" data-id="${r.id_estudiante}" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function badgeEstado(estado) {
        switch (estado) {
            case 'PRESENTE':    return '<span class="badge bg-success-subtle text-success border border-success-subtle">PRESENTE</span>';
            case 'ATRASO':      return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">ATRASO</span>';
            case 'FALTA':       return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">FALTA</span>';
            case 'JUSTIFICADO': return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">JUSTIFICADO</span>';
            default:            return '<span class="badge bg-light text-secondary border">Pendiente</span>';
        }
    }

    function actualizarResumen(registros) {
        let p = 0, a = 0, f = 0, j = 0;
        registros.forEach(r => {
            if (r.estado === 'PRESENTE') p++;
            else if (r.estado === 'ATRASO') a++;
            else if (r.estado === 'FALTA') f++;
            else if (r.estado === 'JUSTIFICADO') j++;
        });
        resumenPresentes.textContent    = p;
        resumenAtrasos.textContent      = a;
        resumenFaltas.textContent       = f;
        resumenJustificados.textContent = j;
    }

    tablaBody.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-editar-estado');
        if (!btn) return;
        const reg = listaAsistencia.find(r => r.id_estudiante === parseInt(btn.dataset.id, 10));
        if (reg) abrirModalEstado(reg);
    });

    function abrirModalEstado(reg) {
        const actual = reg.estado || 'PRESENTE';

        Swal.fire({
            title: 'Actualizar estado',
            html: `
                <div class="text-start">
                    <p class="fw-semibold text-dark mb-3">${escapeHtml(reg.apellidos)} ${escapeHtml(reg.nombres)}</p>
                    <div class="mb-3">
                        <label for="swal-estado" class="form-label small fw-semibold text-secondary mb-1">Estado</label>
                        <select id="swal-estado" class="form-select">
                            <option value="PRESENTE" ${actual === 'PRESENTE' ? 'selected' : ''}>PRESENTE</option>
                            <option value="ATRASO" ${actual === 'ATRASO' ? 'selected' : ''}>ATRASO</option>
                            <option value="FALTA" ${actual === 'FALTA' ? 'selected' : ''}>FALTA</option>
                            <option value="JUSTIFICADO" ${actual === 'JUSTIFICADO' ? 'selected' : ''}>JUSTIFICADO</option>
                        </select>
                    </div>
                    <div>
                        <label for="swal-observacion" class="form-label small fw-semibold text-secondary mb-1">Observación (opcional)</label>
                        <textarea id="swal-observacion" class="form-control" rows="3" maxlength="500" placeholder="Motivo o detalle...">${escapeHtml(reg.observacion || '')}</textarea>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#8B0000',
            returnFocus: false,
            preConfirm: () => ({
                estado:      document.getElementById('swal-estado').value,
                observacion: document.getElementById('swal-observacion').value.trim()
            }),
            didClose: enfocarRfid
        }).then(async (r) => {
            if (!r.isConfirmed || !r.value) return;

            try {
                const res = await api(AJAX_URL, {
                    accion:        'actualizar_estado',
                    id_estudiante: reg.id_estudiante,
                    id_materia:    parseInt(selectMateria.value, 10),
                    fecha:         inputFecha.value,
                    parcial:       selectParcial.value,
                    estado:        r.value.estado,
                    observacion:   r.value.observacion
                });
                if (!res) return;
                if (res.status !== 'ok' || !res.data) {
                    throw new Error(res.mensaje || 'No se pudo actualizar.');
                }

                actualizarEnCache(res.data, false);
                resaltarFila(res.data.id_estudiante, 'table-info');
                cargarFechasRegistradas();
                mostrarToast('success', res.mensaje);
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message,
                    confirmButtonColor: '#8B0000',
                    returnFocus: false,
                    didClose: enfocarRfid
                });
            }
        });
    }

    btnMarcarFaltas.addEventListener('click', () => {
        const idMateria = parseInt(selectMateria.value, 10);
        const fecha     = inputFecha.value;
        const parcial   = selectParcial.value;
        if (!idMateria || !fecha) return;

        const pendientes = listaAsistencia.filter(r => !r.id_asistencia).length;
        if (pendientes === 0) {
            mostrarToast('info', 'No hay estudiantes pendientes.');
            enfocarRfid();
            return;
        }

        Swal.fire({
            icon: 'question',
            title: '¿Marcar faltas pendientes?',
            html: `Se registrará <b>FALTA</b> para <b>${pendientes}</b> estudiante(s) el <b>${escapeHtml(fecha)}</b>.`,
            showCancelButton: true,
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            returnFocus: false,
            didClose: enfocarRfid
        }).then(async (r) => {
            if (!r.isConfirmed) return;

            try {
                const res = await api(AJAX_URL, {
                    accion:     'marcar_faltas',
                    id_materia: idMateria,
                    fecha:      fecha,
                    parcial:    parcial
                });
                if (!res) return;
                if (res.status !== 'ok') {
                    throw new Error(res.mensaje || 'No se pudieron marcar las faltas.');
                }

                await Promise.all([cargarAsistencias(), cargarFechasRegistradas()]);
                mostrarToast('success', res.mensaje);
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message,
                    confirmButtonColor: '#8B0000',
                    returnFocus: false,
                    didClose: enfocarRfid
                });
            }
        });
    });

    async function cargarFechasRegistradas() {
        const idMateria = parseInt(selectMateria.value, 10);
        const parcial   = selectParcial.value;

        if (!idMateria) {
            selectFechaHistorial.innerHTML = '<option value="">Sin clases registradas</option>';
            selectFechaHistorial.disabled = true;
            return;
        }

        try {
            const res = await api(`${AJAX_URL}?accion=fechas&id_materia=${encodeURIComponent(idMateria)}&parcial=${encodeURIComponent(parcial)}`);
            if (!res || res.status !== 'ok' || !Array.isArray(res.data) || res.data.length === 0) {
                selectFechaHistorial.innerHTML = '<option value="">Sin clases registradas</option>';
                selectFechaHistorial.disabled = true;
                return;
            }

            const totalClases = res.data.length;
            selectFechaHistorial.disabled = false;
            selectFechaHistorial.innerHTML =
                '<option value="">Seleccionar sesión...</option>' +
                res.data.map((f, idx) => {
                    const numSesion = totalClases - idx;
                    return `<option value="${escapeHtml(f.fecha)}">Sesión ${numSesion} — ${escapeHtml(formatearFechaCorta(f.fecha))} (${f.total} reg.)</option>`;
                }).join('');

            sincronizarFechaHistorial();
        } catch {
            selectFechaHistorial.innerHTML = '<option value="">Sin clases registradas</option>';
            selectFechaHistorial.disabled = true;
        }
    }

    function sincronizarFechaHistorial() {
        if (!selectFechaHistorial || selectFechaHistorial.disabled) return;
        const existe = Array.from(selectFechaHistorial.options).some(opt => opt.value === inputFecha.value);
        selectFechaHistorial.value = existe ? inputFecha.value : '';
    }

    function formatearFechaCorta(fechaIso) {
        const partes = String(fechaIso || '').split('-');
        return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : fechaIso;
    }

    async function api(url, body = null) {
        const opciones = body
            ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }
            : {};
        const respuesta = await fetch(url, opciones);
        if (respuesta.status === 401) {
            window.location.href = 'index.php?action=login';
            return null;
        }
        return await respuesta.json();
    }

    function actualizarEnCache(registro, moverAlInicio) {
        const idx = listaAsistencia.findIndex(r => r.id_estudiante === registro.id_estudiante);
        if (idx >= 0) {
            if (moverAlInicio) {
                listaAsistencia.splice(idx, 1);
                listaAsistencia.unshift(registro);
            } else {
                listaAsistencia[idx] = registro;
            }
        } else {
            listaAsistencia.unshift(registro);
        }

        actualizarResumen(listaAsistencia);
        aplicarFiltro();
        actualizarHora();
    }

    function resaltarFila(id, clase) {
        resaltado = { id, clase };
        aplicarFiltro();

        if (timerResaltado) clearTimeout(timerResaltado);
        timerResaltado = setTimeout(() => {
            const fila = document.getElementById(`fila-asistencia-${id}`);
            if (fila) fila.classList.remove(clase);
            resaltado = null;
        }, 2500);
    }

    function actualizarHora() {
        horaActualizacion.textContent = `Actualizado: ${new Date().toLocaleTimeString()}`;
    }

    function mostrarTablaMensaje(mensaje, esError = false) {
        badgeTotal.textContent = '0';
        tablaBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-5 ${esError ? 'text-danger' : 'text-muted'} small">
                    ${escapeHtml(mensaje)}
                </td>
            </tr>
        `;
    }

    function mostrarToast(icon, title) {
        Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true
        }).fire({ icon, title });
    }

    function escapeHtml(texto) {
        if (texto === null || texto === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(texto);
        return div.innerHTML;
    }
});
