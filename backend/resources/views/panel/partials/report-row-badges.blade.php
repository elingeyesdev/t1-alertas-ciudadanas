{!! \App\Support\PanelLabels::badge(\App\Support\PanelLabels::REPORT_STATUS, $report->status?->value) !!}
@if ($report->verified)
    <span class="badge bg-teal-lt"><i class="ti ti-rosette-discount-check me-1"></i>Verificada</span>
@else
    <span class="badge bg-secondary-lt">Sin verificar</span>
@endif
