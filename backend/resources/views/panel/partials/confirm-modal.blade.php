{{-- Modal genérico: cualquier <form data-confirm="..."> pide confirmación antes de enviarse --}}
<div class="modal modal-blur fade" id="confirm-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            <div class="modal-status bg-danger" data-confirm-status></div>
            <div class="modal-body text-center py-4">
                <i class="ti ti-alert-triangle text-danger fs-1 mb-2 d-block" data-confirm-icon></i>
                <h3 data-confirm-title>¿Estás seguro?</h3>
                <div class="text-secondary" data-confirm-text></div>
            </div>
            <div class="modal-footer">
                <div class="w-100 row g-2">
                    <div class="col"><button type="button" class="btn w-100" data-bs-dismiss="modal">Cancelar</button></div>
                    <div class="col"><button type="button" class="btn btn-danger w-100" data-confirm-ok>Confirmar</button></div>
                </div>
            </div>
        </div>
    </div>
</div>
