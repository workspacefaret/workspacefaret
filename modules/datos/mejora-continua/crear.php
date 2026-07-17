<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('mejora_continua');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiClient.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $payload = [
        'tipo' => $_POST['tipo'] ?? '',
        'origen' => $_POST['origen'] ?? '',
        'titulo' => $_POST['titulo'] ?? '',
        'descripcion' => $_POST['descripcion'] ?? '',
        'severidad' => $_POST['severidad'] ?? '',
        'proceso' => $_POST['proceso'] ?? '',
        'norma' => $_POST['norma'] ?? '',
        'reportadoPor' => $_POST['reportado_por'] ?? '',
        'responsable' => $_POST['responsable'] ?? '',
        'fechaDeteccion' => ($_POST['fecha_deteccion'] ?? date('Y-m-d')) . 'T00:00:00'
    ];

    $respuesta = ApiClient::postMejoraContinua('no-conformidades', $payload);

    if ($respuesta['ok']) {
        header('Location: /modules/datos/mejora-continua/');
        exit;
    }

    $error = $respuesta['error'] ?? 'No se pudo crear la no conformidad.';
}

ob_start();

?>

<div class="hero">
    <h1>Nueva No Conformidad</h1>
    <p>Registro inicial de una no conformidad.</p>
</div>

<?php if ($error): ?>
    <div class="card">
        <h2>Error</h2>
        <p><?= htmlspecialchars($error) ?></p>
    </div>
<?php endif; ?>

<div class="table-card">

    <div class="table-header">
        <div>
            <h2>Datos de la NC</h2>
            <p>Completa la información principal.</p>
        </div>

        <a href="/modules/datos/mejora-continua/" class="btn-secondary">
            Volver
        </a>
    </div>

    <form method="POST" class="filter-card">

        <div class="filter-group">
            <label>Tipo</label>
            <select name="tipo" required>
                <option value="INTERNA">Interna</option>
                <option value="EXTERNA">Externa</option>
            </select>
        </div>

        <div class="filter-group">
            <label>Origen</label>
            <select name="origen" required>
                <option value="AUDITORIA_INTERNA">Auditoría interna</option>
                <option value="AUDITORIA_EXTERNA">Auditoría externa</option>
                <option value="CLIENTE">Cliente</option>
                <option value="PROVEEDOR">Proveedor</option>
                <option value="PROCESO_INTERNO">Proceso interno</option>
                <option value="ACCIDENTE">Accidente</option>
                <option value="OTRO">Otro</option>
            </select>
        </div>

        <div class="filter-group">
            <label>Severidad</label>
            <select name="severidad" required>
                <option value="BAJA">Baja</option>
                <option value="MEDIA">Media</option>
                <option value="ALTA">Alta</option>
                <option value="CRITICA">Crítica</option>
            </select>
        </div>

        <div class="filter-group">
            <label>Fecha detección</label>
            <input type="date" name="fecha_deteccion" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="filter-group">
            <label>Proceso</label>
            <input type="text" name="proceso" placeholder="Ej: Producción">
        </div>

        <div class="filter-group">
            <label>Norma</label>
            <input type="text" name="norma" placeholder="Ej: ISO 9001">
        </div>

        <div class="filter-group">
            <label>Reportado por</label>
            <input type="text" name="reportado_por" placeholder="Ej: dcarrasco">
        </div>

        <div class="filter-group">
            <label>Responsable</label>
            <input type="text" name="responsable" placeholder="Ej: jefe_calidad">
        </div>

        <div class="filter-group" style="grid-column: 1 / -1;">
            <label>Título</label>
            <input type="text" name="titulo" required>
        </div>

        <div class="filter-group" style="grid-column: 1 / -1;">
            <label>Descripción</label>
            <textarea name="descripcion" rows="5" required></textarea>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn-primary">
                <i class="bi bi-save-fill"></i>
                Guardar
            </button>

            <a href="/modules/datos/mejora-continua/" class="btn-secondary">
                Cancelar
            </a>
        </div>

    </form>

</div>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
