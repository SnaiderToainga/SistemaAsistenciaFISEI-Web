<?php
/**
 * ==============================================================================
 * Módulo de Inicio de Sesión (Login)
 * ==============================================================================
 * Sistema de Control de Asistencia FISEI - Web
 * Valida las credenciales contra la tabla 'usuarios' mediante
 * controllers/loginController.php (Fetch API + SweetAlert2).
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
                <form id="form-login" autocomplete="off" novalidate>
                    <div class="mb-3">
                        <label for="username" class="form-label small fw-semibold text-secondary">Usuario Institucional</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text"
                                   class="form-control"
                                   id="username"
                                   name="username"
                                   placeholder="usuario.fisei"
                                   maxlength="50"
                                   autocomplete="username"
                                   autofocus
                                   required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label small fw-semibold text-secondary">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password"
                                   class="form-control"
                                   id="password"
                                   name="password"
                                   placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                                   autocomplete="current-password"
                                   required>
                            <button class="btn btn-outline-secondary" type="button" id="btn-ver-password" title="Mostrar / ocultar contraseña">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold" id="btn-ingresar">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Ingresar al Sistema
                    </button>
                </form>
            </div>
            <div class="card-footer bg-light text-center py-2 small text-muted">
                Credenciales administradas por la Dirección de TI FISEI
            </div>
        </div>
    </div>
</div>

<!-- Lógica del formulario de acceso -->
<script>
document.addEventListener('DOMContentLoaded', () => {

    // URL del controlador AJAX de autenticación
    const LOGIN_URL = 'controllers/loginController.php';

    const formLogin      = document.getElementById('form-login');
    const inputUsername  = document.getElementById('username');
    const inputPassword  = document.getElementById('password');
    const btnIngresar    = document.getElementById('btn-ingresar');
    const btnVerPassword = document.getElementById('btn-ver-password');

    // Mostrar / ocultar contraseña
    btnVerPassword.addEventListener('click', () => {
        const oculto = inputPassword.type === 'password';
        inputPassword.type = oculto ? 'text' : 'password';
        btnVerPassword.innerHTML = oculto ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    });

    // Envío del formulario de acceso
    formLogin.addEventListener('submit', async (e) => {
        e.preventDefault();

        const username = inputUsername.value.trim();
        const password = inputPassword.value;

        if (!username || !password) {
            Swal.fire({
                icon: 'warning',
                title: 'Campos incompletos',
                text: 'Ingrese su usuario y contraseña.',
                confirmButtonColor: '#8B0000'
            });
            return;
        }

        try {
            cargando(true);

            const respuesta = await fetch(LOGIN_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    accion: 'ingresar',
                    username: username,
                    password: password
                })
            });

            const resultado = await respuesta.json();

            if (resultado.status === 'ok') {
                await Swal.fire({
                    icon: 'success',
                    title: '¡Acceso Concedido!',
                    text: resultado.mensaje,
                    timer: 1200,
                    showConfirmButton: false,
                    timerProgressBar: true
                });
                window.location.href = resultado.data.redireccion || 'index.php?action=inicio';
            } else {
                throw new Error(resultado.mensaje || 'No fue posible iniciar sesión.');
            }

        } catch (error) {
            cargando(false);
            console.error('Error de autenticación:', error);
            Swal.fire({
                icon: 'error',
                title: 'Acceso Denegado',
                text: error.message || 'No se pudo comunicar con el servidor.',
                confirmButtonColor: '#8B0000'
            }).then(() => {
                inputPassword.value = '';
                inputPassword.focus();
            });
        }
    });

    function cargando(activo) {
        btnIngresar.disabled = activo;
        btnIngresar.innerHTML = activo
            ? '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Verificando...'
            : '<i class="bi bi-box-arrow-in-right me-1"></i>Ingresar al Sistema';
    }
});
</script>
