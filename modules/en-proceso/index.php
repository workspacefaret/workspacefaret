<?php
ob_start();

$modulo = $_GET['modulo'] ?? 'Módulo';
$moduloSeguro = htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8');
?>

<style>
    .module-placeholder {
        position: relative;
        overflow: hidden;
        border-radius: 24px;
        padding: 56px 32px;
        min-height: 520px;
        display: flex;
        align-items: center;
        justify-content: center;
        background:
            radial-gradient(circle at top left, rgba(59, 130, 246, 0.20), transparent 34%),
            radial-gradient(circle at bottom right, rgba(14, 165, 233, 0.14), transparent 36%),
            linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(15, 23, 42, 0.88));
        border: 1px solid rgba(148, 163, 184, 0.18);
        box-shadow: 0 24px 70px rgba(0, 0, 0, 0.28);
    }

    .module-placeholder::before {
        content: "";
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(255, 255, 255, .035) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, .035) 1px, transparent 1px);
        background-size: 42px 42px;
        mask-image: radial-gradient(circle at center, black 0%, transparent 70%);
        pointer-events: none;
    }

    .module-placeholder-content {
        position: relative;
        z-index: 1;
        max-width: 760px;
        text-align: center;
    }

    .module-status {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        margin-bottom: 24px;
        border-radius: 999px;
        color: #bfdbfe;
        background: rgba(37, 99, 235, 0.14);
        border: 1px solid rgba(96, 165, 250, 0.28);
        font-size: 13px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .module-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #38bdf8;
        box-shadow: 0 0 0 6px rgba(56, 189, 248, 0.12);
    }

    .module-icon {
        width: 96px;
        height: 96px;
        margin: 0 auto 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 28px;
        color: #e0f2fe;
        background:
            linear-gradient(135deg, rgba(59, 130, 246, 0.34), rgba(14, 165, 233, 0.18));
        border: 1px solid rgba(125, 211, 252, 0.25);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.12),
            0 18px 40px rgba(14, 165, 233, 0.18);
    }

    .module-icon svg {
        width: 46px;
        height: 46px;
    }

    .module-placeholder h1 {
        margin: 0 0 14px;
        color: #ffffff;
        font-size: clamp(34px, 5vw, 56px);
        line-height: 1.05;
        letter-spacing: -0.04em;
    }

    .module-placeholder p {
        margin: 0 auto;
        max-width: 640px;
        color: #94a3b8;
        font-size: 17px;
        line-height: 1.75;
    }

    .module-footer-note {
        margin-top: 34px;
        padding-top: 24px;
        border-top: 1px solid rgba(148, 163, 184, 0.16);
        color: #64748b;
        font-size: 14px;
    }

    @media (max-width: 768px) {
        .module-placeholder {
            padding: 42px 20px;
            min-height: 460px;
            border-radius: 20px;
        }

        .module-icon {
            width: 82px;
            height: 82px;
            border-radius: 24px;
        }

        .module-placeholder p {
            font-size: 15px;
        }
    }
</style>

<div class="hero">
    <div>
        <h1><?= $moduloSeguro ?></h1>
        <p>Estado del módulo dentro del portal operativo.</p>
    </div>

    <span class="badge badge-primary">En desarrollo</span>
</div>

<div class="table-card">
    <div class="module-placeholder">
        <div class="module-placeholder-content">

            <div class="module-status">
                <span class="module-status-dot"></span>
                Módulo en implementación
            </div>

            <div class="module-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z" stroke="currentColor" stroke-width="1.8" />
                    <path d="M19.4 15a1.8 1.8 0 0 0 .36 1.98l.04.04a2.1 2.1 0 0 1-2.97 2.97l-.04-.04A1.8 1.8 0 0 0 14.8 19.6a1.8 1.8 0 0 0-1.08 1.65V21.4a2.1 2.1 0 0 1-4.2 0v-.06A1.8 1.8 0 0 0 8.4 19.7a1.8 1.8 0 0 0-1.98.36l-.04.04a2.1 2.1 0 0 1-2.97-2.97l.04-.04A1.8 1.8 0 0 0 3.8 15.1a1.8 1.8 0 0 0-1.65-1.08H2.1a2.1 2.1 0 0 1 0-4.2h.06A1.8 1.8 0 0 0 3.8 8.7a1.8 1.8 0 0 0-.36-1.98l-.04-.04A2.1 2.1 0 0 1 6.37 3.7l.04.04A1.8 1.8 0 0 0 8.4 4.1a1.8 1.8 0 0 0 1.08-1.65V2.4a2.1 2.1 0 0 1 4.2 0v.06A1.8 1.8 0 0 0 14.8 4.1a1.8 1.8 0 0 0 1.98-.36l.04-.04a2.1 2.1 0 0 1 2.97 2.97l-.04.04A1.8 1.8 0 0 0 19.4 8.7a1.8 1.8 0 0 0 1.65 1.08h.06a2.1 2.1 0 0 1 0 4.2h-.06A1.8 1.8 0 0 0 19.4 15Z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                </svg>
            </div>

            <h1><?= $moduloSeguro ?></h1>

            <p>
                Este módulo se encuentra actualmente en proceso de desarrollo e integración.
                Estamos preparando su estructura, validaciones, permisos y flujo operativo
                para asegurar una implementación estable, segura y alineada con el portal.
            </p>

            <div class="module-footer-note">
                El acceso será habilitado una vez finalizada la etapa de validación interna.
            </div>

        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
