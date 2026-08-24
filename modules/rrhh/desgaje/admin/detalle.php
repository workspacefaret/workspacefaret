<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('rrhh');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

ob_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero admin-hero">
    <div>
        <h1 id="detalleTitulo">Detalle Registro de Desgaje</h1>
        <p>Información completa, trabajos registrados, firma y acciones de validación.</p>
    </div>

    <div class="admin-hero-actions">
        <a class="admin-btn admin-btn-secondary" href="/modules/rrhh/desgaje/admin/">
            <i class="bi bi-arrow-left"></i>
            Volver
        </a>

        <a class="admin-btn admin-btn-primary" id="btnPdfDetalle" href="#" target="_blank" style="display:none;">
            <i class="bi bi-file-earmark-pdf"></i>
            PDF
        </a>
    </div>
</section>

<section class="section">
    <div class="admin-detail-layout">

        <div class="panel admin-panel admin-detail-main">
            <div class="section-header">
                <div class="section-title">
                    <h2>Información General</h2>
                    <p id="detalleSubtitulo">Cargando información...</p>
                </div>
                <span class="badge badge-primary" id="detalleEstado">-</span>
            </div>

            <div class="admin-detail-list" id="detalleContenido"></div>
        </div>

        <div class="panel admin-panel admin-detail-side">
            <div class="section-header">
                <div class="section-title">
                    <h2>Acciones</h2>
                    <p id="detalleAccionesSubtitulo">Validación y anulación del registro.</p>
                </div>
            </div>

            <div class="admin-change-state" id="accionesContenido">
                <p class="admin-empty-box">Cargando...</p>
            </div>
        </div>

    </div>
</section>

<section class="section">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Trabajos registrados</h2>
                <p>Detalle de cada trabajo de desgaje incluido en este registro.</p>
            </div>
            <button class="admin-btn admin-btn-secondary" id="btnExportarTrabajos" disabled>
                <i class="bi bi-file-earmark-excel"></i>
                Exportar Excel
            </button>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead id="detalleTrabajosHead">
                    <tr>
                        <th>NP</th>
                        <th>Cliente</th>
                        <th>N&deg; Pliego</th>
                        <th>Tipo</th>
                        <th>Pliegos</th>
                        <th>Moldes</th>
                        <th>Estuches</th>
                        <th>Precio</th>
                        <th>Valor</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody id="detalleTrabajosBody">
                    <tr>
                        <td colspan="10" class="admin-empty">Cargando trabajos...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="section">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Firma</h2>
                <p>Firma del operador capturada al finalizar el registro.</p>
            </div>
        </div>

        <div id="detalleFirma" class="admin-empty-box">Cargando firma...</div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const registroId = <?= $id ?>;
    const apiBaseUrl = '<?= htmlspecialchars(API_FORMULARIOS) ?>';
    const appRoot = apiBaseUrl.replace(/api\/?$/, '');

    let trabajosActuales = [];
    let modoManualActual = false;
    let codigoActual = '';

    document.getElementById('btnExportarTrabajos').addEventListener('click', exportarTrabajosExcel);

    cargarDetalle();

    async function cargarDetalle() {
        if (!registroId) {
            document.getElementById('detalleContenido').innerHTML = '<div class="admin-empty">ID de registro no válido.</div>';
            document.getElementById('accionesContenido').innerHTML = '';
            return;
        }

        try {
            const response = await fetch(`${apiBaseUrl}desgaje/registros/${registroId}`);
            if (!response.ok) throw new Error('No fue posible cargar el registro.');

            const item = await response.json();

            codigoActual = item.codigo;
            document.getElementById('detalleTitulo').textContent = item.codigo;
            document.getElementById('detalleSubtitulo').textContent = `${item.tallerNombreSnapshot} · ${item.operadorNombreSnapshot}`;
            document.getElementById('detalleEstado').textContent = textoEstado(item.estado);
            document.getElementById('detalleEstado').className = `badge ${claseBadgeEstado(item.estado)}`;

            const btnPdf = document.getElementById('btnPdfDetalle');
            if (item.pdfRuta) {
                btnPdf.href = `${apiBaseUrl}desgaje/registros/${item.id}/pdf`;
                btnPdf.style.display = '';
            }

            document.getElementById('detalleContenido').innerHTML = `
                ${campo('Código', item.codigo)}
                ${campo('Estado', textoEstado(item.estado))}
                ${campo('Fecha de registro', formatearFecha(item.fechaRegistro, true))}
                ${campo('Taller', item.tallerNombreSnapshot)}
                ${campo('Operador', item.operadorNombreSnapshot)}
                ${campo('NP', [...new Set(item.detalles.map(d => d.np))].join(', '))}
                ${campo('Trabajos registrados', item.detalles.length)}
                ${campo('Estuches totales', item.detalles.reduce((a, d) => a + d.cantidadEstuches, 0))}
                ${campo('Valor total', formatearMoneda(item.detalles.reduce((a, d) => a + d.valorCalculado, 0)))}
                ${campo('Observaciones generales', item.observacionesGenerales, true)}
                ${campo('Checklist entrada', textoChecklist(item.checklistEntradaCumple, item.checklistEntradaObservacion), true)}
                ${campo('Checklist inicio de producción', textoChecklist(item.checklistInicioCumple, item.checklistInicioObservacion), true)}
                ${campo('Checklist salida', textoChecklist(item.checklistSalidaCumple, item.checklistSalidaObservacion), true)}
                ${campo('Creado por', item.usuarioCreador)}
                ${campo('Fecha creación', formatearFecha(item.fechaCreacion))}
                ${campo('Fecha firma', formatearFecha(item.fechaFirma))}
                ${item.usuarioValida ? campo('Validado por', `${item.usuarioValida} (${formatearFecha(item.fechaValidacion)})`) : ''}
                ${item.usuarioAnula ? campo('Anulado por', `${item.usuarioAnula} (${formatearFecha(item.fechaAnulacion)})`) : ''}
                ${item.motivoAnulacion ? campo('Motivo anulación', item.motivoAnulacion, true) : ''}
            `;

            renderAcciones(item);
            renderTrabajos(item.detalles);
            renderFirma(item.firmaRuta);

        } catch (error) {
            document.getElementById('detalleContenido').innerHTML = `<div class="admin-empty">${escapeHtml(error.message)}</div>`;
            document.getElementById('accionesContenido').innerHTML = '';
        }
    }

    function renderAcciones(item) {
        const contenedor = document.getElementById('accionesContenido');

        if (item.estado === 'FIRMADO') {
            contenedor.innerHTML = `
                <button class="admin-btn admin-btn-primary" id="btnValidar">
                    <i class="bi bi-check-circle"></i>
                    Validar registro
                </button>

                <label for="motivoAnulacion" style="margin-top:16px;">Motivo de anulación</label>
                <textarea id="motivoAnulacion" rows="4" placeholder="Obligatorio para anular"></textarea>

                <button class="admin-btn admin-btn-secondary" id="btnAnular">
                    <i class="bi bi-x-circle"></i>
                    Anular registro
                </button>
            `;

            document.getElementById('btnValidar').addEventListener('click', () => validar());
            document.getElementById('btnAnular').addEventListener('click', () => anular());

        } else if (item.estado === 'VALIDADO') {
            contenedor.innerHTML = `
                <label for="motivoAnulacion">Motivo de anulación</label>
                <textarea id="motivoAnulacion" rows="4" placeholder="Obligatorio para anular"></textarea>

                <button class="admin-btn admin-btn-secondary" id="btnAnular">
                    <i class="bi bi-x-circle"></i>
                    Anular registro
                </button>
            `;

            document.getElementById('btnAnular').addEventListener('click', () => anular());

        } else if (item.estado === 'BORRADOR') {
            contenedor.innerHTML = '<p class="admin-empty-box">Este registro aún no ha sido firmado desde el formulario móvil. No tiene acciones disponibles desde el panel administrativo.</p>';

        } else {
            contenedor.innerHTML = '<p class="admin-empty-box">Este registro está anulado. No tiene más acciones disponibles.</p>';
        }
    }

    async function validar() {
        const boton = document.getElementById('btnValidar');
        boton.disabled = true;

        try {
            const response = await fetch(`${apiBaseUrl}desgaje/registros/${registroId}/validar`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ usuarioValida: 'Administrador Workspace' })
            });

            if (!response.ok) {
                const texto = await response.text().catch(() => '');
                throw new Error(texto || 'No se pudo validar el registro.');
            }

            alert('Registro validado correctamente.');
            location.reload();

        } catch (error) {
            alert(error.message);
            boton.disabled = false;
        }
    }

    async function anular() {
        const motivo = document.getElementById('motivoAnulacion').value.trim();

        if (!motivo) {
            alert('El motivo de anulación es obligatorio.');
            return;
        }

        const boton = document.getElementById('btnAnular');
        boton.disabled = true;

        try {
            const response = await fetch(`${apiBaseUrl}desgaje/registros/${registroId}/anular`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ usuarioAnula: 'Administrador Workspace', motivoAnulacion: motivo })
            });

            if (!response.ok) {
                const texto = await response.text().catch(() => '');
                throw new Error(texto || 'No se pudo anular el registro.');
            }

            alert('Registro anulado correctamente.');
            location.reload();

        } catch (error) {
            alert(error.message);
            boton.disabled = false;
        }
    }

    function renderTrabajos(detalles) {
        const contenedor = document.getElementById('detalleTrabajosBody');
        const encabezado = document.getElementById('detalleTrabajosHead');
        const modoManual = detalles && detalles.length > 0 && !detalles[0].tipoDesgajeId;

        trabajosActuales = detalles || [];
        modoManualActual = modoManual;
        document.getElementById('btnExportarTrabajos').disabled = trabajosActuales.length === 0;

        if (!detalles || !detalles.length) {
            encabezado.innerHTML = `
                <tr>
                    <th>NP</th><th>Cliente</th><th>N&deg; Pliego</th><th>Tipo</th><th>Pliegos</th>
                    <th>Moldes</th><th>Estuches</th><th>Precio</th><th>Valor</th><th>Observaciones</th>
                </tr>
            `;
            contenedor.innerHTML = '<tr><td colspan="10" class="admin-empty">Sin trabajos registrados.</td></tr>';
            return;
        }

        if (modoManual) {
            encabezado.innerHTML = `
                <tr>
                    <th>NP</th><th>Cliente</th><th>Descripción producto</th>
                    <th>Cantidad buena</th><th>Cantidad mala</th><th>Total procesado</th>
                    <th>Precio</th><th>Valor</th><th>Descripción trabajo</th>
                </tr>
            `;

            contenedor.innerHTML = detalles.map(d => `
                <tr>
                    <td>${escapeHtml(d.np)}</td>
                    <td>${escapeHtml(d.clienteNombreSnapshot)}</td>
                    <td>${escapeHtml(d.descripcionProducto || '-')}</td>
                    <td>${d.cantidadBuena ?? '-'}</td>
                    <td>${d.cantidadMala ?? '-'}</td>
                    <td>${d.cantidadEstuches}</td>
                    <td>${formatearMoneda(d.precioAplicado)}</td>
                    <td>${formatearMoneda(d.valorCalculado)}</td>
                    <td>${escapeHtml(d.descripcionTrabajo || '-')}</td>
                </tr>
            `).join('');
            return;
        }

        encabezado.innerHTML = `
            <tr>
                <th>NP</th><th>Cliente</th><th>N&deg; Pliego</th><th>Tipo</th><th>Pliegos</th>
                <th>Moldes</th><th>Estuches</th><th>Precio</th><th>Valor</th><th>Observaciones</th>
            </tr>
        `;

        contenedor.innerHTML = detalles.map(d => `
            <tr>
                <td>${escapeHtml(d.np)}</td>
                <td>${escapeHtml(d.clienteNombreSnapshot)}</td>
                <td>${escapeHtml(d.numeroPliego)}</td>
                <td>${escapeHtml(d.tipoDesgajeNombreSnapshot)}</td>
                <td>${d.cantidadPliegos}</td>
                <td>${d.numeroMoldes}</td>
                <td>${d.cantidadEstuches}</td>
                <td>${formatearMoneda(d.precioAplicado)}</td>
                <td>${formatearMoneda(d.valorCalculado)}</td>
                <td>${escapeHtml(d.observaciones || '-')}</td>
            </tr>
        `).join('');
    }

    function exportarTrabajosExcel() {
        if (!trabajosActuales.length) return;

        const columnas = modoManualActual
            ? ['NP', 'Cliente', 'Descripción producto', 'Cantidad buena', 'Cantidad mala', 'Total procesado', 'Precio', 'Valor', 'Descripción trabajo']
            : ['NP', 'Cliente', 'N° Pliego', 'Tipo', 'Pliegos', 'Moldes', 'Estuches', 'Precio', 'Valor', 'Observaciones'];

        const filas = trabajosActuales.map(d => {
            const celdas = modoManualActual
                ? [
                    d.np,
                    d.clienteNombreSnapshot,
                    d.descripcionProducto || '-',
                    d.cantidadBuena ?? '-',
                    d.cantidadMala ?? '-',
                    d.cantidadEstuches,
                    formatearMoneda(d.precioAplicado),
                    formatearMoneda(d.valorCalculado),
                    d.descripcionTrabajo || '-'
                ]
                : [
                    d.np,
                    d.clienteNombreSnapshot,
                    d.numeroPliego,
                    d.tipoDesgajeNombreSnapshot,
                    d.cantidadPliegos,
                    d.numeroMoldes,
                    d.cantidadEstuches,
                    formatearMoneda(d.precioAplicado),
                    formatearMoneda(d.valorCalculado),
                    d.observaciones || '-'
                ];

            return `<tr>${celdas.map(c => `<td>${escapeHtml(c)}</td>`).join('')}</tr>`;
        }).join('');

        const html = `
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    table { border-collapse: collapse; font-family: Arial; font-size: 11px; }
                    th { background: #0f172a; color: #ffffff; font-weight: bold; border: 1px solid #334155; padding: 8px; }
                    td { border: 1px solid #cbd5e1; padding: 7px; vertical-align: top; }
                    tr:nth-child(even) td { background: #f8fafc; }
                    .title { font-size: 20px; font-weight: bold; color: #0f172a; }
                    .subtitle { color: #475569; }
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="${columnas.length}" class="title">Trabajos registrados - ${escapeHtml(codigoActual)}</td></tr>
                    <tr><td colspan="${columnas.length}" class="subtitle">Workspace Faret - ${new Date().toLocaleString('es-CL')}</td></tr>
                    <tr></tr>
                    <tr>${columnas.map(c => `<th>${escapeHtml(c.toUpperCase())}</th>`).join('')}</tr>
                    ${filas}
                </table>
            </body>
            </html>
        `;

        const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download = `trabajos-${codigoActual}.xls`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        URL.revokeObjectURL(url);
    }

    function renderFirma(firmaRuta) {
        const contenedor = document.getElementById('detalleFirma');

        if (!firmaRuta) {
            contenedor.innerHTML = 'Este registro aún no tiene firma.';
            return;
        }

        contenedor.outerHTML = `<div id="detalleFirma"><img src="${appRoot}${firmaRuta.replace(/^\//, '')}" alt="Firma del operador" style="max-width:320px;background:#fff;border-radius:12px;padding:8px;"></div>`;
    }

    function campo(label, valor, full = false) {
        return `
            <div class="admin-detail-item ${full ? 'admin-detail-item-full' : ''}">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(valor ?? '-')}</strong>
            </div>
        `;
    }

    function textoChecklist(cumple, observacion) {
        const base = cumple ? 'Cumple' : 'No cumple';
        return observacion ? `${base} - ${observacion}` : base;
    }

    function textoEstado(estado) {
        const textos = { BORRADOR: 'Borrador', FIRMADO: 'Firmado', VALIDADO: 'Validado', ANULADO: 'Anulado' };
        return textos[estado] || estado;
    }

    function claseBadgeEstado(estado) {
        const clases = { BORRADOR: '', FIRMADO: 'badge-warning', VALIDADO: 'badge-success', ANULADO: 'badge-danger' };
        return clases[estado] || 'badge-primary';
    }

    function formatearFecha(valor, soloFecha = false) {
        if (!valor) return '-';
        const fecha = new Date(valor);
        if (Number.isNaN(fecha.getTime())) return valor;

        if (soloFecha) {
            return fecha.toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' });
        }

        return fecha.toLocaleString('es-CL', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatearMoneda(valor) {
        const numero = Number(valor || 0);
        return `$${numero.toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function escapeHtml(texto) {
        return String(texto ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
});
</script>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
