<div class="bike-modal" id="loginModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle" hidden>
    <div class="bike-modal-card">
        <div class="bike-modal-header">
            <h5 class="fw-bold m-0" id="loginModalTitle">Iniciar sesión</h5>
            <button type="button" class="bike-modal-close js-login-close" aria-label="Cerrar">&times;</button>
        </div>
        <div class="bike-modal-body">
            <form method="post" action="index.php" class="register-form d-grid gap-3">
                <div>
                    <label for="loginEmail" class="form-label">Correo</label>
                    <input type="email" id="loginEmail" name="login_email" class="form-control" required maxlength="120" autocomplete="email">
                </div>
                <div>
                    <label for="loginPassword" class="form-label">Contraseña</label>
                    <input type="password" id="loginPassword" name="login_password" class="form-control" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-utn" name="login_user" value="1">Entrar</button>
            </form>
        </div>
    </div>
</div>
