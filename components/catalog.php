<div class="container my-5" id="catalogo">
    <?php if (!empty($flashMessage)): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8'); ?>" role="alert">
            <?php echo htmlspecialchars($flashMessage['text'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">
        <?php foreach ($bicicletas as $index => $b): ?>
            <?php $delay = $index * 150; ?>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                <div class="card-pro text-center">
                    <div class="img-wrapper">
                        <img src="<?php echo htmlspecialchars($b['img'], ENT_QUOTES, 'UTF-8'); ?>" alt="Bici UTN">
                    </div>
                    <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($b['n'], ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p class="small opacity-75 mb-1">Unidades: <?php echo (int) $b['total']; ?></p>
                    <p class="small opacity-75 mb-1">Disponibles: <?php echo (int) $b['disponibles']; ?></p>
                    <p class="small mb-3 <?php echo ($b['estado'] === 'Disponible') ? 'text-success' : 'text-warning'; ?>">
                        Estado: <?php echo htmlspecialchars($b['estado'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <button
                        type="button"
                        class="btn btn-utn js-bike-trigger"
                        data-bike-name="<?php echo htmlspecialchars($b['n'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-bike-model="<?php echo htmlspecialchars($b['n'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-bike-state="<?php echo htmlspecialchars($b['estado'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-bike-available="<?php echo (int) $b['disponibles']; ?>"
                        data-bike-total="<?php echo (int) $b['total']; ?>"
                        data-bike-image="<?php echo htmlspecialchars($b['img'], ENT_QUOTES, 'UTF-8'); ?>"
                        <?php echo ($b['estado'] !== 'Disponible') ? 'disabled' : ''; ?>
                    >
                        <?php echo ($b['estado'] === 'Disponible') ? 'RESERVAR' : 'OCUPADA'; ?>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="bike-modal" id="bikeInfoModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="bikeModalTitle" hidden>
    <div class="bike-modal-card">
        <div class="bike-modal-header">
            <h5 class="fw-bold m-0" id="bikeModalTitle">Detalle de bicicleta</h5>
            <button type="button" class="bike-modal-close js-bike-close" aria-label="Cerrar">&times;</button>
        </div>
        <div class="bike-modal-body">
            <div class="mb-3 rounded overflow-hidden">
                <img id="bikeModalImage" src="" alt="Imagen de bicicleta" class="w-100" style="max-height: 220px; object-fit: cover;">
            </div>
            <p class="text-white-50 mb-2" id="bikeModalUnit">Unidades disponibles:</p>
            <p class="text-white-50 mb-3" id="bikeModalState">Estado:</p>

            <form method="post" action="index.php" class="d-grid gap-2">
                <input type="hidden" name="bike_model" id="bikeModalBikeModel" value="">
                <button type="submit" class="btn btn-utn" name="reserve_bike" value="1" id="bikeModalReserveButton">Confirmar reserva</button>
            </form>
        </div>
    </div>
</div>
