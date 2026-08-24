<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('documentacion');

$pdo = getAuthPdo();

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('
    SELECT d.*, u.nombre AS autor
    FROM documentos_tecnicos d
    LEFT JOIN usuarios u ON u.id = d.creado_por
    WHERE d.id = ? AND d.activo = 1
');
$stmt->execute([$id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Documento no encontrado — Workspace Faret</title>
        <link rel="stylesheet" href="/assets/css/documentacion/doc-template.css">
    </head>
    <body class="doc-page">
        <div class="doc-header">
            <h1>Documento no encontrado</h1>
            <p><a href="/modules/documentacion/">Volver a Documentación Técnica</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= htmlspecialchars($doc['titulo']) ?> — Documentación Técnica</title>

<link rel="icon" type="image/png" href="/assets/img/welcome/logo-workspace-faret.png">

<script>
(function () {
    try {
        var tema = localStorage.getItem('workspace-theme');
        if (tema === 'light' || tema === 'dark') {
            document.documentElement.setAttribute('data-theme', tema);
        }
    } catch (e) {}
})();
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/assets/css/documentacion/doc-template.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

</head>

<body class="doc-page">

<div class="doc-topbar">
    <a class="doc-back" href="/modules/documentacion/">
        <i class="bi bi-arrow-left"></i>
        Documentación Técnica
    </a>
    <button type="button" class="doc-theme-toggle" id="themeToggle" aria-label="Cambiar tema">
        <i class="bi bi-moon-stars-fill"></i>
    </button>
</div>

<div class="doc-shell">

    <header class="doc-header">
        <span class="doc-kicker"><?= htmlspecialchars($doc['sistema']) ?> · Documentación técnica</span>
        <h1><?= htmlspecialchars($doc['titulo']) ?></h1>
        <div class="doc-meta">
            <span><strong><?= htmlspecialchars($doc['autor'] ?? 'Autor desconocido') ?></strong></span>
            <span>Actualizado <?= htmlspecialchars($doc['actualizado_en'] ?? $doc['creado_en']) ?></span>
        </div>
    </header>

    <nav class="doc-toc" aria-label="Tabla de contenidos">
        <span class="toc-label">Contenidos</span>
        <ol></ol>
    </nav>

    <main class="doc-main">
        <?= $doc['contenido'] ?>
    </main>

    <footer class="doc-footer">
        <span>Workspace Faret — Documentación técnica interna</span>
        <span><?= htmlspecialchars($doc['sistema']) ?></span>
    </footer>

</div>

<script src="/assets/js/theme.js"></script>
<script src="/assets/js/documentacion/doc-template.js"></script>

</body>
</html>
