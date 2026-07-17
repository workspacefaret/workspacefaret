<div class="sidebar">

    <div class="sidebar-logo">

        <img
            src="/assets/img/logo-faret.png"
            class="logo-company"
            alt="Faret">

        <div class="logo-divider"></div>

        <img
            src="/assets/img/logo-innpack.png"
            class="logo-company"
            alt="Innpack">

    </div>

    <div class="menu">

        <?php if (currentUser()): ?>

            <div class="menu-section">Principal</div>

            <a href="/modules/operacion/">
                <i class="bi bi-diagram-3-fill"></i>
                Operación
            </a>

            <div class="menu-section">Áreas de trabajo</div>

            <?php if (hasModuleAccess('logistica')): ?>
                <a href="/modules/operacion/logistica/">
                    <i class="bi bi-truck"></i>
                    Logística
                </a>
            <?php endif; ?>

            <?php if (hasModuleAccess('desarrollo')): ?>
                <a href="/modules/formularios/desarrollo/">
                    <i class="bi bi-palette-fill"></i>
                    Desarrollo
                </a>
            <?php endif; ?>

            <?php if (hasModuleAccess('rrhh')): ?>
                <a href="/modules/rrhh/">
                    <i class="bi bi-people-fill"></i>
                    RRHH
                </a>
            <?php endif; ?>

            <div class="menu-section">Formularios y datos</div>

            <?php if (hasModuleAccess('desarrollo')): ?>
                <a href="/modules/formularios/">
                    <i class="bi bi-ui-checks-grid"></i>
                    Formularios
                </a>
            <?php endif; ?>

            <?php if (hasModuleAccess('datos')): ?>
                <a href="/modules/datos/">
                    <i class="bi bi-database-fill"></i>
                    Centro de Control
                </a>
            <?php endif; ?>

            <?php if (hasModuleAccess('mejora_continua')): ?>
                <a href="/modules/datos/mejora-continua/">
                    <i class="bi bi-clipboard-check-fill"></i>
                    Mejora Continua
                </a>
            <?php endif; ?>

            <div class="menu-section">Sistema</div>

            <?php if (hasModuleAccess('exportaciones')): ?>
                <a href="/modules/datos/exportaciones/">
                    <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                    Exportaciones
                </a>
            <?php endif; ?>

            <?php if (hasModuleAccess('reportes')): ?>
                <a href="/modules/datos/dashboard/">
                    <i class="bi bi-bar-chart-fill"></i>
                    Reportes
                </a>
            <?php endif; ?>

            <?php if (currentUser()['rol'] === 'admin_ti'): ?>
                <div class="menu-section">Administración</div>

                <a href="/modules/admin/usuarios/">
                    <i class="bi bi-person-gear"></i>
                    Usuarios
                </a>
            <?php endif; ?>

        <?php else: ?>

            <div class="menu-section">Acceso</div>

            <a href="/modules/welcome/">
                <i class="bi bi-house-fill"></i>
                Inicio
            </a>

        <?php endif; ?>

    </div>

</div>
