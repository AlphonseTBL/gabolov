<div class="bike-modal" id="registerModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="registerModalTitle" hidden>
    <div class="bike-modal-card">
        <div class="bike-modal-header">
            <h5 class="fw-bold m-0" id="registerModalTitle">Registro de usuario</h5>
            <button type="button" class="bike-modal-close js-register-close" aria-label="Cerrar">&times;</button>
        </div>
        <div class="bike-modal-body">
            <form method="post" action="index.php" class="register-form d-grid gap-3">
                <div>
                    <label for="registerSchoolId" class="form-label">ID escolar</label>
                    <input type="number" id="registerSchoolId" name="register_school_id" class="form-control" required min="1" step="1" autocomplete="off">
                </div>
                <div>
                    <label for="registerName" class="form-label">Nombre completo</label>
                    <input type="text" id="registerName" name="register_name" class="form-control" required maxlength="80" autocomplete="name">
                </div>
                <div>
                    <label for="registerEmail" class="form-label">Correo institucional</label>
                    <input type="email" id="registerEmail" name="register_email" class="form-control" required maxlength="120" autocomplete="email">
                </div>
                <div>
                    <label for="registerPassword" class="form-label">Contraseña</label>
                    <input type="password" id="registerPassword" name="register_password" class="form-control" required minlength="6" autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-utn" name="register_user" value="1">Crear cuenta</button>
            </form>
        </div>
    </div>
</div>
