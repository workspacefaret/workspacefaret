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

            <?php if (hasModuleAccess('planificacion') || hasModuleAccess('control_moldes') || hasModuleAccess('stock_moldes')): ?>
                <a href="/modules/planificacion/moldes/">
                    <i class="bi bi-box-seam"></i>
                    Moldes
                </a>
                <div class="menu-submenu">
                    <?php if (hasModuleAccess('planificacion')): ?>
                        <a href="/modules/planificacion/">
                            <i class="bi bi-rulers"></i>
                            Perfiles y Moldes
                        </a>
                        <a href="/modules/planificacion/registro-molde/">
                            <i class="bi bi-clipboard-check"></i>
                            Registro de Molde
                        </a>
                    <?php endif; ?>

                    <?php if (hasModuleAccess('control_moldes')): ?>
                        <a href="/modules/planificacion/control-moldes/">
                            <i class="bi bi-diagram-3"></i>
                            Control de Moldes
                        </a>
                    <?php endif; ?>

                    <?php if (hasModuleAccess('stock_moldes')): ?>
                        <a href="/modules/planificacion/stock-moldes/">
                            <i class="bi bi-archive"></i>
                            Stock de Moldes
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

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
                <div class="menu-submenu">
                    <a href="/modules/formularios/desarrollo/solicitud-grafica/">
                        <i class="bi bi-file-earmark-plus-fill"></i>
                        Solicitud Gráfica
                    </a>
                    <a href="/modules/formularios/desarrollo/solicitud-estructural/">
                        <i class="bi bi-bounding-box-circles"></i>
                        Solicitud Estructural
                    </a>
                    <a href="/modules/formularios/desarrollo/admin/">
                        <i class="bi bi-table"></i>
                        Registros Gráfico
                    </a>
                    <a href="/modules/formularios/desarrollo/solicitud-estructural/admin/">
                        <i class="bi bi-table"></i>
                        Registros Estructural
                    </a>
                </div>
            <?php endif; ?>

            <?php if (hasModuleAccess('rrhh')): ?>
                <a href="/modules/rrhh/">
                    <i class="bi bi-people-fill"></i>
                    RRHH
                </a>
                <div class="menu-submenu">
                    <a href="/modules/rrhh/guardias/registros/">
                        <i class="bi bi-clipboard-data"></i>
                        Recorridos Guardias
                    </a>
                    <a href="/modules/rrhh/guardias/usuarios/">
                        <i class="bi bi-person-gear"></i>
                        Usuarios Guardias
                    </a>
                    <a href="/modules/rrhh/desgaje/registro/">
                        <i class="bi bi-scissors"></i>
                        Registro de Desgaje
                    </a>
                    <a href="/modules/rrhh/desgaje/admin/">
                        <i class="bi bi-clipboard-data"></i>
                        Panel Desgaje
                    </a>
                </div>
            <?php endif; ?>

            <?php if (currentUser()['rol'] === 'admin_ti'): ?>
                <div class="menu-section">Administración</div>

                <a href="/modules/admin/usuarios/">
                    <i class="bi bi-person-gear"></i>
                    Usuarios
                </a>

                <a href="/modules/admin/novedades/">
                    <i class="bi bi-megaphone-fill"></i>
                    Novedades
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
