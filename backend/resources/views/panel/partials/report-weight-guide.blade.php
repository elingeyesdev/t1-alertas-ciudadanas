<div class="d-flex align-items-center gap-2 mb-3">
    <button type="button" class="btn btn-icon weight-info-trigger" data-weight-guide
            aria-label="Cómo funciona el peso" title="Cómo funciona el peso"
            aria-haspopup="dialog" aria-controls="report-weight-dialog">
        <i class="ti ti-info-circle fs-2" aria-hidden="true"></i>
    </button>
    <span class="fw-semibold">{{ isset($currentWeight) ? 'Peso de la alerta' : 'Peso de las alertas' }}</span>
    @isset($currentWeight)
        <span class="badge bg-blue-lt">{{ $currentWeight }} pts</span>
    @endisset
</div>
<dialog id="report-weight-dialog" class="weight-dialog card" aria-labelledby="report-weight-title">
    <div class="card-header gap-2">
        <h2 id="report-weight-title" class="card-title">Cómo funciona el peso</h2>
        @isset($currentWeight)
            <span class="badge bg-blue-lt">{{ $currentWeight }} pts</span>
        @endisset
    </div>
    <div class="card-body">
        <p class="text-secondary mb-3">El peso indica cuánto aporta una alerta al cálculo de riesgo de su zona.</p>
        <dl class="row g-2 mb-3">
            <dt class="col-8">Peso inicial según el tipo</dt>
            <dd class="col-4 mb-0 text-end">1–10 pts</dd>
            <dt class="col-8">Descripción de más de 100 caracteres</dt>
            <dd class="col-4 mb-0 text-end">+2 pts</dd>
            <dt class="col-8">Cada foto adicional</dt>
            <dd class="col-4 mb-0 text-end">+1 pt</dd>
            <dt class="col-8">Voto «Lo vi»</dt>
            <dd class="col-4 mb-0 text-end">+1 pt</dd>
            <dt class="col-8">Voto «Es falso»</dt>
            <dd class="col-4 mb-0 text-end">−1 pt</dd>
        </dl>
        <p class="small mb-2">Cada vecino tiene un voto. Cambiarlo ajusta el peso en 2 puntos hacia la nueva opción, con un mínimo de 0.</p>
        <p class="small mb-0">Una autoridad verifica la alerta manualmente. En el mapa móvil, una alerta verificada aporta un 25 % más al índice de riesgo.</p>
    </div>
    <form method="dialog" class="card-footer d-flex justify-content-end">
        <button class="btn btn-primary" autofocus>Entendido</button>
    </form>
</dialog>
