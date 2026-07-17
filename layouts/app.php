<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Workspace Faret</title>

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

<link rel="stylesheet"
href="/assets/css/main.css">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

</head>

<body>

<div class="app">

    <?php include $_SERVER['DOCUMENT_ROOT'].'/includes/sidebar.php'; ?>

    <div class="main">

        <?php include $_SERVER['DOCUMENT_ROOT'].'/includes/topbar.php'; ?>

        <div class="page">

            <?= $contenido ?>

        </div>

    </div>

</div>

<script src="/assets/js/theme.js"></script>

</body>
</html>
