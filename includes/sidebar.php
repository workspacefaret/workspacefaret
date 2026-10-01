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

            <?php if (hasModuleAccess('portal_sap') || hasModuleAccess('portal_sap_ventas') || hasModuleAccess('portal_sap_compras') || hasModuleAccess('portal_sap_logistica') || hasModuleAccess('portal_sap_calidad') || hasModuleAccess('portal_sap_precios')): ?>
                <a href="/modules/sap/">
                    <i class="bi bi-diagram-2"></i>
                    Portal SAP
                </a>
                <div class="menu-submenu">
                    <?php if (hasModuleAccess('portal_sap')): ?>
                        <a href="/modules/sap/inventario/">
                            <i class="bi bi-boxes"></i>
                            Inventario
                        </a>
                        <a href="/modules/sap/clientes/">
                            <i class="bi bi-people"></i>
                            Clientes
                        </a>
                        <a href="/modules/sap/proveedores/">
                            <i class="bi bi-truck"></i>
                            Proveedores
                        </a>
                        <a href="/modules/sap/almacenes/">
                            <i class="bi bi-building"></i>
                            Almacenes
                        </a>
                    <?php endif; ?>

                    <?php if (hasModuleAccess('portal_sap_ventas') || hasModuleAccess('portal_sap_compras')): ?>
                        <a href="/modules/sap/pendientes/">
                            <i class="bi bi-exclamation-triangle"></i>
                            Pendientes
                        </a>
                    <?php endif; ?>

                    <?php if (hasModuleAccess('portal_sap_ventas')): ?>
                        <a href="/modules/sap/ventas/">
                            <i class="bi bi-graph-up"></i>
                            Ventas
                        </a>
                    <?php endif; ?>

                    <?php if (hasModuleAccess('portal_sap_compras')): ?>
                        <a href="/modules/sap/compras/">
                            <i class="bi bi-cart"></i>
                            Compras
                        </a>
                    <?php endif; ?>

                    <?php if (hasModuleAccess('portal_sap_logistica')): ?>
                        <a href="/modules/sap/logistica/">
                            <i class="bi bi-signpost-split"></i>
                            Logística
                        </a>
                    <?php endif; ?>

                    <?php if (hasModuleAccess('portal_sap_calidad')): ?>
                        <a href="/modules/sap/calidad/">
                            <i class="bi bi-patch-check"></i>
                            Calidad
                        </a>
                    <?php endif; ?>

                    <?php if (hasModuleAccess('portal_sap_precios')): ?>
                        <a href="/modules/sap/precios/">
                            <i class="bi bi-tag"></i>
                            Precios
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

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
                <div class="menu-submenu">
                    <a href="https://solicitudes.faret.cl/app/formularios/" target="_blank">
                        <i class="bi bi-clipboard2-check-fill"></i>
                        Formularios Logística
                    </a>
                    <a href="/modules/operacion/logistica/salida-mercaderia/">
                        <i class="bi bi-box-arrow-up-right"></i>
                        Salida de Mercadería
                    </a>
                </div>
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

            <?php if (hasModuleAccess('documentacion')): ?>
                <a href="/modules/documentacion/">
                    <i class="bi bi-journal-text"></i>
                    Documentación Técnica
                </a>
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

                <a href="/modules/admin/documentacion/">
                    <i class="bi bi-journals"></i>
                    Documentación Técnica
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
