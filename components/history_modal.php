<div class="bike-modal" id="historyModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="historyModalTitle" hidden>
    <div class="bike-modal-card">
        <div class="bike-modal-header">
            <h5 class="fw-bold m-0" id="historyModalTitle">Historial de préstamos</h5>
            <button type="button" class="bike-modal-close js-history-close" aria-label="Cerrar">&times;</button>
        </div>
        <div class="bike-modal-body">
            <?php if (empty($loanHistory)): ?>
                <p class="text-white-50 mb-0">Aún no hay préstamos registrados para este usuario.</p>
            <?php else: ?>
                <div class="history-list">
                    <?php foreach ($loanHistory as $item): ?>
                        <div class="history-item">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong><?php echo htmlspecialchars($item['modelo'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span class="small opacity-75">#<?php echo (int) $item['prestamo_id']; ?></span>
                            </div>
                            <div class="small opacity-75">Fecha: <?php echo htmlspecialchars($item['dia_uso'] !== '' ? $item['dia_uso'] : 'N/D', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="small <?php echo ($item['estado_viaje'] === 1) ? 'text-success' : 'text-warning'; ?>">
                                Estado viaje: <?php echo ($item['estado_viaje'] === 1) ? 'Finalizado' : 'En curso'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
