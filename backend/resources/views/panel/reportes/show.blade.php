@extends('layouts.panel')
@php
    use App\Support\PanelLabels;
    $tz = config('alertas.display_timezone');
    $isActive = $report->status?->value === 'activo' && ! $report->trashed();
@endphp

@section('title', 'Alerta #'.$report->id)
@section('pretitle')
    <a href="{{ route('panel.alertas.index') }}" class="text-reset"><i class="ti ti-arrow-left"></i> Alertas</a>
@endsection

@push('scripts')
    @vite('resources/js/panel-picker.js')
@endpush

@section('actions')
    <span class="align-self-center text-secondary">
        Lo vi: {{ $voting['seen'] }} · Es falso: {{ $voting['false'] }} · Peso: {{ $voting['weight'] }}
    </span>
    @if ($isActive && ! $report->verified)
        <form method="POST" action="{{ route('panel.alertas.verify', $report->id) }}"
              data-confirm="Se marcará como verificada y se avisará a quien la reportó." data-confirm-title="¿Verificar alerta?" data-confirm-color="success">
            @csrf @method('PATCH')
            <button class="btn btn-success"><i class="ti ti-rosette-discount-check me-1"></i> Verificar</button>
        </form>
    @endif
    @if ($isActive)
        <form method="POST" action="{{ route('panel.alertas.resolve', $report->id) }}"
              data-confirm="La alerta saldrá del mapa como atendida." data-confirm-title="¿Marcar como resuelta?" data-confirm-color="primary">
            @csrf @method('PATCH')
            <button class="btn btn-primary"><i class="ti ti-circle-check me-1"></i> Resolver</button>
        </form>
        <form method="POST" action="{{ route('panel.alertas.destroy', $report->id) }}"
              data-confirm="Úsalo si la alerta es falsa. Saldrá del mapa." data-confirm-title="¿Eliminar alerta?">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger"><i class="ti ti-trash me-1"></i> Eliminar</button>
        </form>
    @else
        <form method="POST" action="{{ route('panel.alertas.reactivate', $report->id) }}"
              data-confirm="Volverá a estar activa en el mapa por unas horas más." data-confirm-title="¿Reactivar alerta?" data-confirm-color="warning">
            @csrf @method('PATCH')
            <button class="btn btn-warning"><i class="ti ti-refresh me-1"></i> Reactivar</button>
        </form>
    @endif
@endsection

@section('content')
    <div class="row row-cards">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">{{ $report->type?->name ?? 'Sin tipo' }}</h2>
                        <div class="text-secondary small">{{ $report->zone }}</div>
                    </div>
                    <div class="card-actions">@include('panel.partials.report-row-badges')</div>
                </div>
                <div class="card-body">
                    <p class="fs-3 mb-4">{{ $report->description }}</p>
                    <dl class="row mb-0">
                        <dt class="col-5 col-md-4">Reportada por</dt>
                        <dd class="col-7 col-md-8">{{ $report->creator ? $report->creator->first_name.' '.$report->creator->last_name : 'Ciudadano' }}
                            @if ($report->creator)<span class="text-secondary">· {{ $report->creator->phone }}</span>@endif</dd>
                        <dt class="col-5 col-md-4">Fecha</dt>
                        <dd class="col-7 col-md-8">{{ $report->created_at?->timezone($tz)->format('d/m/Y H:i') }} <span class="text-secondary">({{ $report->created_at?->diffForHumans() }})</span></dd>
                        <dt class="col-5 col-md-4">Vence</dt>
                        <dd class="col-7 col-md-8">{{ $report->expires_at?->timezone($tz)->format('d/m/Y H:i') ?? '—' }}</dd>
                        <dt class="col-5 col-md-4">Peso</dt>
                        <dd class="col-7 col-md-8">{{ $report->weight }}</dd>
                        <dt class="col-5 col-md-4">Coordenadas</dt>
                        <dd class="col-7 col-md-8 text-secondary">{{ number_format($report->latitude, 5) }}, {{ number_format($report->longitude, 5) }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title">Evidencia fotográfica ({{ $report->images->count() }})</h2></div>
                <div class="card-body">
                    @if ($report->images->isEmpty())
                        <p class="text-secondary mb-0">No se adjuntaron fotos a esta alerta.</p>
                    @else
                        <div class="row g-2">
                            @foreach ($report->images as $image)
                                <div class="col-6 col-md-4">
                                    <a href="{{ $image->url }}" target="_blank" rel="noopener" class="d-block ratio ratio-1x1 rounded overflow-hidden border">
                                        <img src="{{ $image->url }}" alt="Foto de la alerta #{{ $report->id }}" class="object-cover w-100 h-100" loading="lazy">
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Comentarios ({{ $report->comments->count() }})</h2></div>
                <div class="list-group list-group-flush">
                    @forelse ($report->comments as $comment)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $comment->creator?->first_name }} {{ $comment->creator?->last_name }}</strong>
                                <span class="text-secondary small">{{ $comment->created_at?->diffForHumans() }}</span>
                            </div>
                            <div>{{ $comment->text }}</div>
                            @foreach ($comment->responses as $reply)
                                <div class="ms-4 mt-2 ps-3 border-start">
                                    <strong class="small">{{ $reply->creator?->first_name }} {{ $reply->creator?->last_name }}</strong>
                                    <span class="text-secondary small">· {{ $reply->created_at?->diffForHumans() }}</span>
                                    <div class="small">{{ $reply->text }}</div>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="list-group-item text-secondary">Nadie comentó esta alerta todavía.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            @include('panel.partials.report-weight-guide', ['currentWeight' => $report->weight])
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title">Ubicación</h2></div>
                <div class="card-body p-0">
                    <div class="mini-map" data-static-map data-lat="{{ $report->latitude }}" data-lng="{{ $report->longitude }}"
                         data-label="{{ $report->type?->name }}" role="img" aria-label="Ubicación de la alerta en el mapa"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Personal de respuesta ({{ $report->dispatches->count() }})</h2></div>
                <div class="list-group list-group-flush">
                    @forelse ($report->dispatches as $dispatch)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <strong>{{ $dispatch->attendedBy?->first_name }} {{ $dispatch->attendedBy?->last_name }}</strong>
                                    <div class="text-secondary small">
                                        {{ PanelLabels::label(PanelLabels::PROFILE_TYPES, $dispatch->attendedBy?->authorityProfile?->profile_type?->value) }}
                                        · {{ $dispatch->destination?->name }}
                                    </div>
                                    <div class="text-secondary small">{{ $dispatch->recorded_at?->timezone($tz)->format('d/m/Y H:i') }}</div>
                                </div>
                                {!! PanelLabels::badge(PanelLabels::DISPATCH_STATE, $dispatch->state?->value) !!}
                            </div>
                            @if ($dispatch->state?->value === 'en_curso')
                                <div class="btn-list mt-2">
                                    <form method="POST" action="{{ route('panel.alertas.dispatch.update', $dispatch->id) }}">
                                        @csrf @method('PATCH')<input type="hidden" name="state" value="completado">
                                        <button class="btn btn-sm btn-success">Completado</button>
                                    </form>
                                    <form method="POST" action="{{ route('panel.alertas.dispatch.update', $dispatch->id) }}" data-confirm="La unidad dejará de atender esta alerta." data-confirm-title="¿Cancelar despacho?">
                                        @csrf @method('PATCH')<input type="hidden" name="state" value="cancelado">
                                        <button class="btn btn-sm">Cancelar</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-secondary">Todavía no se envió ninguna unidad.</div>
                    @endforelse
                </div>
                @if ($isActive)
                    <div class="card-body border-top">
                        <h3 class="card-title mb-2">Despachar unidad</h3>
                        @if ($authorities->isEmpty() || $stations->isEmpty())
                            <p class="text-secondary mb-0">Primero registra al menos una <a href="{{ route('panel.usuarios.create') }}">autoridad</a> y una <a href="{{ route('panel.estaciones.create') }}">estación</a>.</p>
                        @else
                            <form method="POST" action="{{ route('panel.alertas.dispatch', $report->id) }}" class="row g-2">
                                @csrf
                                <div class="col-12">
                                    <label class="form-label" for="station_id">Estación que atiende</label>
                                    <select id="station_id" name="station_id" class="form-select" required>
                                        <option value="">Elige una estación…</option>
                                        @foreach ($stations as $station)
                                            <option value="{{ $station->id }}" @selected(old('station_id') == $station->id)>{{ $station->name }} ({{ PanelLabels::label(PanelLabels::STATION_TYPES, $station->installation_type?->value) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="authority_id">Autoridad asignada</label>
                                    <select id="authority_id" name="authority_id" class="form-select" required>
                                        <option value="">Elige una autoridad…</option>
                                        @foreach ($authorities as $authority)
                                            <option value="{{ $authority->id }}" @selected(old('authority_id') == $authority->id)>{{ $authority->first_name }} {{ $authority->last_name }} — {{ PanelLabels::label(PanelLabels::PROFILE_TYPES, $authority->authorityProfile?->profile_type?->value) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12"><button class="btn btn-warning w-100"><i class="ti ti-ambulance me-1"></i> Despachar</button></div>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
