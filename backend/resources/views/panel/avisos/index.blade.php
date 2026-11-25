@extends('layouts.panel')
@php
    use App\Support\PanelLabels;
    $tz = config('alertas.display_timezone');
@endphp

@section('title', 'Avisos preventivos')
@section('pretitle', 'Salud, viaje y desastres naturales')

@section('actions')
    <a href="{{ route('panel.avisos.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i> Nuevo aviso</a>
@endsection

@section('content')
    <div class="row row-cards mb-3">
        <div class="col-sm-4">@include('panel.partials.stat', ['label' => 'Vigentes', 'value' => $stats['vigentes'], 'icon' => 'ti-speakerphone', 'color' => 'green', 'href' => route('panel.avisos.index', ['estado' => 'vigentes'])])</div>
        <div class="col-sm-4">@include('panel.partials.stat', ['label' => 'Publicados en total', 'value' => $stats['total'], 'icon' => 'ti-list', 'color' => 'secondary'])</div>
        <div class="col-sm-4">@include('panel.partials.stat', ['label' => 'Ciudadanos notificados', 'value' => $stats['notificados'], 'icon' => 'ti-bell-ringing', 'color' => 'azure'])</div>
    </div>

    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-6 col-md-5">
                    <label class="form-label" for="estado">Estado</label>
                    <select id="estado" name="estado" class="form-select">
                        <option value="">Todos</option>
                        <option value="vigentes" @selected(($filters['estado'] ?? null) === 'vigentes')>Vigentes</option>
                        <option value="finalizados" @selected(($filters['estado'] ?? null) === 'finalizados')>Finalizados</option>
                    </select>
                </div>
                <div class="col-6 col-md-5">
                    <label class="form-label" for="categoria">Tipo</label>
                    <select id="categoria" name="categoria" class="form-select">
                        <option value="">Todos</option>
                        @foreach (PanelLabels::PUBLIC_ALERT_CATEGORIES as $value => [$label])
                            <option value="{{ $value }}" @selected(($filters['categoria'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-1">
                    <button class="btn btn-primary w-100" aria-label="Filtrar"><i class="ti ti-filter"></i></button>
                    @if (array_filter($filters))
                        <a href="{{ route('panel.avisos.index') }}" class="btn btn-icon" aria-label="Quitar filtros"><i class="ti ti-x"></i></a>
                    @endif
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th>Aviso</th><th>Tipo</th><th>Zona</th><th>Vigente hasta</th><th>Notificados</th><th>Estado</th><th class="w-1"></th></tr></thead>
                <tbody>
                @forelse ($alerts as $alert)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $alert->title }}</div>
                            <div class="text-secondary small text-truncate" style="max-width: 22rem">{{ $alert->message }}</div>
                        </td>
                        <td>{!! PanelLabels::badge(PanelLabels::PUBLIC_ALERT_CATEGORIES, $alert->category->value) !!}</td>
                        <td class="text-secondary small">
                            {{ $alert->radius_m >= 1000 ? ($alert->radius_m / 1000).' km' : $alert->radius_m.' m' }} alrededor de
                            {{ number_format($alert->latitude, 4) }}, {{ number_format($alert->longitude, 4) }}
                        </td>
                        <td class="text-secondary text-nowrap">{{ $alert->expires_at->timezone($tz)->format('d/m/Y H:i') }}</td>
                        <td class="text-secondary">{{ $alert->notified_count }}</td>
                        <td>
                            @if ($alert->isActive())
                                <span class="badge bg-green-lt">Vigente</span>
                            @else
                                <span class="badge bg-secondary-lt">Finalizado</span>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            @if ($alert->isActive())
                                <form method="POST" action="{{ route('panel.avisos.finish', $alert->id) }}" class="d-inline"
                                      data-confirm="«{{ $alert->title }}» dejará de verse en los mapas." data-confirm-title="¿Finalizar aviso?">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm"><i class="ti ti-player-stop me-1"></i>Finalizar</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('panel.avisos.destroy', $alert->id) }}" class="d-inline"
                                  data-confirm="Se eliminará «{{ $alert->title }}»." data-confirm-title="¿Eliminar aviso?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-ghost-danger" aria-label="Eliminar {{ $alert->title }}"><i class="ti ti-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-5">No hay avisos con esos filtros.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($alerts->hasPages())
            <div class="card-footer d-flex align-items-center">{{ $alerts->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
@endsection
