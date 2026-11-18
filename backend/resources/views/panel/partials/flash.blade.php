@foreach (['success' => ['success', 'ti-circle-check'], 'error' => ['danger', 'ti-alert-circle']] as $key => [$color, $icon])
    @if (session($key))
        <div class="alert alert-{{ $color }} alert-dismissible" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="ti {{ $icon }} fs-2"></i><div>{{ session($key) }}</div>
            </div>
            <a class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></a>
        </div>
    @endif
@endforeach
@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <div class="d-flex gap-2">
            <i class="ti ti-alert-circle fs-2"></i>
            <div>
                <h4 class="alert-title">Revisa los datos ingresados</h4>
                <ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        </div>
    </div>
@endif
