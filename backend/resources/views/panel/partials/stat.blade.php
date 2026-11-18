{{-- Tarjeta de estadística: $label, $value, $icon, $color, $href (opcional) --}}
<div class="card card-sm">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-auto">
                <span class="bg-{{ $color }} text-white avatar"><i class="ti {{ $icon }} fs-2"></i></span>
            </div>
            <div class="col">
                <div class="font-weight-medium fs-3">{{ $value }}</div>
                <div class="text-secondary">
                    @isset($href)<a href="{{ $href }}" class="text-reset">{{ $label }}</a>@else{{ $label }}@endisset
                </div>
            </div>
        </div>
    </div>
</div>
