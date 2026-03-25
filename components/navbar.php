<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold text-white" href="#">
            <i class="fas fa-microchip me-2" style="color:var(--verde-neon)"></i>UTN <span style="color:var(--verde-neon)">ENGINEERING</span>
        </a>

        <?php if (!empty($currentUser)): ?>
            <div class="d-flex gap-2 align-items-center">
                <?php if (!empty($isAdminUser)): ?>
                    <a href="index.php?action=admin" class="btn btn-sm btn-warning rounded-pill px-3">Panel admin</a>
                <?php endif; ?>
                <?php if (!empty($activeLoan)): ?>
                    <form method="post" action="index.php" class="m-0">
                        <button type="submit" class="btn btn-sm btn-success rounded-pill px-3" name="finish_trip" value="1">
                            Finalizar viaje #<?php echo (int) $activeLoan['prestamo_id']; ?>
                        </button>
                    </form>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 js-history-open">
                    <?php echo htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8'); ?>
                    | <?php echo htmlspecialchars(ucfirst((string) ($currentUser['role'] ?? 'miembro')), ENT_QUOTES, 'UTF-8'); ?>
                    | ID <?php echo (int) $currentUser['id']; ?>
                </button>
                <form method="post" action="index.php" class="m-0">
                    <button type="submit" class="btn btn-sm btn-light rounded-pill px-3" name="logout_user" value="1">Cerrar sesión</button>
                </form>
            </div>
        <?php else: ?>
            <div class="d-flex gap-2 align-items-center">
                <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 js-login-open">Iniciar sesión</button>
                <button type="button" class="btn btn-sm btn-light rounded-pill px-3 js-register-open">Registrarte</button>
            </div>
        <?php endif; ?>
    </div>
</nav>
