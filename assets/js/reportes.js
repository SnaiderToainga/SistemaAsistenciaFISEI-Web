/**
 * Lógica del Módulo de Reportes y Consolidado FISEI
 */

document.addEventListener('DOMContentLoaded', function () {
    const AJAX_URL = 'controllers/reporteAjaxController.php';

    const selectMateria  = document.getElementById('select-materia-reporte');
    const selectParcial  = document.getElementById('select-parcial-reporte');
    const inputBuscar    = document.getElementById('buscar-reporte');
    const btnExportarPdf = document.getElementById('btn-exportar-pdf');
    const tablaBody      = document.getElementById('tabla-reporte-body');

    let listaEstudiantes = [];

    cargarMaterias();

    function cargarMaterias() {
        fetch(AJAX_URL + '?accion=materias')
            .then(function (resp) { return resp.json(); })
            .then(function (res) {
                if (res.status !== 'ok' || !res.data || res.data.length === 0) {
                    selectMateria.innerHTML = '<option value="">Sin asignaturas disponibles</option>';
                    btnExportarPdf.disabled = true;
                    tablaBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No hay asignaturas asignadas.</td></tr>';
                    return;
                }

                let opciones = '';
                res.data.forEach(function (m) {
                    opciones += '<option value="' + m.id + '">' + escapeHtml(m.codigo + ' - ' + m.nombre) + '</option>';
                });
                selectMateria.innerHTML = opciones;
                cargarConsolidado();
            })
            .catch(function (err) {
                console.error(err);
                tablaBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">Error al cargar asignaturas.</td></tr>';
            });
    }

    selectMateria.addEventListener('change', cargarConsolidado);
    selectParcial.addEventListener('change', cargarConsolidado);
    inputBuscar.addEventListener('input', filtrarTabla);

    btnExportarPdf.addEventListener('click', function () {
        const idMateria = selectMateria.value;
        const parcial   = selectParcial.value;
        if (!idMateria) return;

        const url = 'reports/generar_acta_pdf.php?id_materia=' + encodeURIComponent(idMateria) + '&parcial=' + encodeURIComponent(parcial);
        window.open(url, '_blank');
    });

    function cargarConsolidado() {
        const idMateria = selectMateria.value;
        const parcial   = selectParcial.value;
        if (!idMateria) return;

        tablaBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Calculando consolidado...</td></tr>';

        const url = AJAX_URL + '?accion=consolidado&id_materia=' + encodeURIComponent(idMateria) + '&parcial=' + encodeURIComponent(parcial);

        fetch(url)
            .then(function (resp) { return resp.json(); })
            .then(function (res) {
                if (res.status !== 'ok') {
                    throw new Error(res.mensaje || 'Error al cargar el reporte.');
                }

                const datos = res.data;
                listaEstudiantes = datos.estudiantes;
                btnExportarPdf.disabled = false;

                document.getElementById('titulo-consolidado').textContent = datos.materia.codigo + ' - ' + datos.materia.nombre + ' (' + datos.parcial + ')';
                document.getElementById('rep-total-clases').textContent   = datos.resumen.total_clases;
                document.getElementById('rep-aprobados').textContent      = datos.resumen.aprobados;
                document.getElementById('rep-en-riesgo').textContent      = datos.resumen.en_riesgo;
                document.getElementById('rep-reprobados').textContent     = datos.resumen.reprobados;

                filtrarTabla();
            })
            .catch(function (err) {
                btnExportarPdf.disabled = true;
                tablaBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">' + escapeHtml(err.message) + '</td></tr>';
            });
    }

    function filtrarTabla() {
        const texto = inputBuscar.value.trim().toLowerCase();
        const filtrados = [];

        listaEstudiantes.forEach(function (e) {
            const nombreCompleto = (e.apellidos + ' ' + e.nombres).toLowerCase();
            if (!texto || e.cedula.toLowerCase().includes(texto) || nombreCompleto.includes(texto)) {
                filtrados.push(e);
            }
        });

        mostrarTabla(filtrados);
    }

    function mostrarTabla(estudiantes) {
        document.getElementById('badge-total-reporte').textContent = estudiantes.length;

        if (estudiantes.length === 0) {
            tablaBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No existen estudiantes para mostrar.</td></tr>';
            return;
        }

        let html = '';
        estudiantes.forEach(function (e) {
            let badge = '<span class="badge bg-secondary">SIN REGISTROS</span>';
            if (e.estado_academico === 'APROBADO') {
                badge = '<span class="badge bg-success">APROBADO</span>';
            } else if (e.estado_academico === 'EN RIESGO') {
                badge = '<span class="badge bg-warning text-dark">EN RIESGO</span>';
            } else if (e.estado_academico === 'REPROBADO') {
                badge = '<span class="badge bg-danger">REPROBADO</span>';
            }

            html += '<tr>' +
                '<td class="ps-3 font-monospace">' + escapeHtml(e.cedula) + '</td>' +
                '<td><strong>' + escapeHtml(e.apellidos + ' ' + e.nombres) + '</strong><br><small class="text-muted">' + escapeHtml(e.carrera) + '</small></td>' +
                '<td class="text-center text-success fw-semibold">' + e.presentes + '</td>' +
                '<td class="text-center text-warning-emphasis fw-semibold">' + e.atrasos + '</td>' +
                '<td class="text-center text-danger fw-semibold">' + e.faltas + '</td>' +
                '<td class="text-center text-primary fw-semibold">' + e.justificados + '</td>' +
                '<td class="text-center">' + e.asistencias_validas + ' / ' + e.total_clases + '</td>' +
                '<td class="text-center fw-bold">' + Number(e.porcentaje).toFixed(1) + '%</td>' +
                '<td class="text-center pe-3">' + badge + '</td>' +
                '</tr>';
        });

        tablaBody.innerHTML = html;
    }

    function escapeHtml(texto) {
        if (!texto) return '';
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }
});
