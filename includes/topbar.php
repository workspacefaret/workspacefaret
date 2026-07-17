<?php $usuarioActual = currentUser(); ?>

<div class="topbar">

    <div>
        Portal Operacional Faret
    </div>

    <div class="topbar-actions">
        <button type="button" id="themeToggle" class="theme-toggle" title="Cambiar tema" aria-label="Cambiar tema">
            <i class="bi bi-moon-stars-fill"></i>
        </button>

        <?php if ($usuarioActual): ?>
            <span><?= htmlspecialchars($usuarioActual['nombre']) ?></span>
            <a href="/auth/logout.php" class="btn-secondary">
                <i class="bi bi-box-arrow-right"></i>
                Salir
            </a>
        <?php else: ?>
            <a href="/auth/login.php" class="btn-secondary">
                <i class="bi bi-box-arrow-in-right"></i>
                Iniciar sesión
            </a>
        <?php endif; ?>

        <span>v1.0</span>
    </div>

</div>
