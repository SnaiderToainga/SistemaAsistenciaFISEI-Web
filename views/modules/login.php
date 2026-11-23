<?php
/**
 * ==============================================================================
 * Módulo de Inicio de Sesión (Login)
 * ==============================================================================
 * Sistema de Control de Asistencia FISEI - Web
 */
?>

<div class="row justify-content-center py-5">
    <div class="col-md-5 col-lg-4 col-12">
        <div class="card card-fisei shadow border-0">
            <div class="card-header header-crimson text-center py-4">
                <i class="bi bi-shield-lock-fill fs-1 d-block mb-2"></i>
                <h4 class="mb-0 fw-bold">Acceso al Sistema</h4>
                <small class="text-white-50">FISEI &bull; Universidad Técnica de Ambato</small>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info py-2 small d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-info-circle-fill fs-5"></i>
                    <div>
                        <strong>Módulo en construcción:</strong> Asignado al equipo. Para pruebas directas se ha habilitado el acceso a <code>matriculacion</code>.
                    </div>
                </div>

                <form onsubmit="return false;">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Usuario Institucional</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" placeholder="docente@uta.edu.ec" disabled>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-secondary">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" class="form-control" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" disabled>
                        </div>
                    </div>
                    <a href="index.php?action=matriculacion" class="btn btn-danger w-100 py-2 fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Continuar al Sistema
                    </a>
                </form>
            </div>
            <div class="card-footer bg-light text-center py-2 small text-muted">
                Credenciales administradas por la Dirección de TI FISEI
            </div>
        </div>
    </div>
</div>

