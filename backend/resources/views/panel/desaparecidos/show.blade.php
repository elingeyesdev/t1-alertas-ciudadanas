@extends('layouts.panel')
@php use App\Models\MissingCase; @endphp

@section('title', $case->name)
@section('pretitle')
    <a href="{{ route('panel.desaparecidos.index') }}" class="text-reset"><i class="ti ti-arrow-left"></i> Desaparecidos</a>
@endsection
@if ($case->status === 'pending')
    @push('scripts')
        @vite('resources/js/panel-picker.js')
    @endpush
@endif

@section('content')
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">@include('panel.desaparecidos.status')</div>
                    <dl class="row mb-3">
                        <dt class="col-sm-5">Tipo</dt><dd class="col-sm-7">{{ MissingCase::KINDS[$case->kind] }}</dd>
                        <dt class="col-sm-5">Lugar</dt><dd class="col-sm-7">{{ $case->place }}</dd>
                        <dt class="col-sm-5">Fecha de desaparición</dt><dd class="col-sm-7">{{ $case->missing_on->format('d/m/Y') }}</dd>
                        <dt class="col-sm-5">Teléfono de referencia</dt><dd class="col-sm-7">{{ $case->reference_phone }}</dd>
                        @if ($case->submitter)
                            <dt class="col-sm-5">Sugerido por</dt><dd class="col-sm-7">{{ $case->submitter->first_name }} {{ $case->submitter->last_name }}</dd>
                        @endif
                        @if ($case->publisher)
                            <dt class="col-sm-5">Publicado por</dt><dd class="col-sm-7">{{ $case->publisher->first_name }} {{ $case->publisher->last_name }}</dd>
                        @endif
                    </dl>
                    <p>{!! nl2br(e($case->description)) !!}</p>
                    <div class="row g-2">
                        @foreach ($case->photos as $index => $path)
                            <div class="col-6">
                                <a href="{{ route('panel.desaparecidos.photo', [$case, $index]) }}" aria-label="Ver foto {{ $loop->iteration }}">
                                    <img class="rounded border w-100" src="{{ route('panel.desaparecidos.photo-image', [$case, $index]) }}" alt="Foto {{ $loop->iteration }} de {{ $case->name }}">
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
                @if ($case->status === 'published')
                    <form class="card-footer" method="POST" action="{{ route('panel.desaparecidos.found', $case) }}">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-success" type="submit">Marcar como encontrado</button>
                    </form>
                @endif
            </div>
        </div>
        @if ($case->status === 'pending')
            <div class="col-lg-6">
                <div class="card">
                    <form method="POST" action="{{ route('panel.desaparecidos.approve', $case) }}">
                        @csrf
                        @method('PATCH')
                        <div class="card-body">
                            <h2 class="card-title">Revisar sugerencia</h2>
                            @include('panel.desaparecidos.zone', ['lat' => $case->latitude, 'lng' => $case->longitude])
                        </div>
                        <div class="card-footer d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit">Aprobar y publicar</button>
                            <button class="btn btn-outline-danger" type="submit" form="reject-case">Rechazar</button>
                        </div>
                    </form>
                    <form id="reject-case" method="POST" action="{{ route('panel.desaparecidos.reject', $case) }}">
                        @csrf
                        @method('PATCH')
                    </form>
                </div>
            </div>
        @endif
    </div>
    @if (in_array($case->status, ['published', 'found'], true))
        <div class="card mt-3">
            <div class="card-header"><h2 class="card-title">Aportes</h2></div>
            <div class="list-group list-group-flush">
                @forelse ($contributions as $contribution)
                    <div class="list-group-item">
                        <strong>{{ $contribution->author?->first_name ?? 'Usuario' }}</strong>
                        <span class="text-secondary ms-2">{{ $contribution->created_at->timezone('America/La_Paz')->format('d/m/Y H:i') }}</span>
                        @if ($contribution->message)
                            <p class="my-2">{!! nl2br(e($contribution->message)) !!}</p>
                        @endif
                        @if ($contribution->video_path)
                            <div class="my-2">
                                <video class="mw-100 rounded" width="320" controls preload="none" aria-label="Video del aporte">
                                    <source src="{{ route('panel.desaparecidos.contribution-video', [$case, $contribution]) }}" type="video/mp4">
                                </video>
                            </div>
                        @endif
                        @foreach ($contribution->photos as $index => $path)
                            <a class="d-inline-block mt-2 me-2" href="{{ route('panel.desaparecidos.contribution-photo', [$case, $contribution, $index]) }}">
                                <img class="rounded border" width="120" loading="lazy" src="{{ route('panel.desaparecidos.contribution-photo-image', [$case, $contribution, $index]) }}" alt="Foto del aporte {{ $loop->iteration }}">
                            </a>
                        @endforeach
                    </div>
                @empty
                    <div class="list-group-item text-secondary">Todavía no hay aportes.</div>
                @endforelse
            </div>
            @if ($contributions->hasPages())
                <div class="card-footer">{{ $contributions->links() }}</div>
            @endif
        </div>
    @endif
@endsection
