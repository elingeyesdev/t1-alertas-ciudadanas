@extends('layouts.panel')
@php use App\Models\MissingCase; @endphp

@section('title', 'Desaparecidos')
@section('actions')
    <a href="{{ route('panel.desaparecidos.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Publicar caso</a>
@endsection

@section('content')
    <div class="card">
        <form method="GET" class="card-body d-flex flex-wrap align-items-end gap-3">
            <div>
                <label class="form-label" for="kind">Tipo</label>
                <select name="kind" id="kind" class="form-select">
                    <option value="">Todos</option>
                    @foreach (MissingCase::KINDS as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['kind'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="status">Estado</label>
                <select name="status" id="status" class="form-select">
                    <option value="">Todos</option>
                    @foreach (MissingCase::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn" type="submit">Filtrar</button>
        </form>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>Nombre</th><th>Tipo</th><th>Lugar</th><th>Desaparición</th><th>Estado</th></tr></thead>
                <tbody>
                    @forelse ($cases as $case)
                        <tr>
                            <td><a href="{{ route('panel.desaparecidos.show', $case) }}">{{ $case->name }}</a></td>
                            <td>{{ MissingCase::KINDS[$case->kind] }}</td>
                            <td>{{ $case->place }}</td>
                            <td>{{ $case->missing_on->format('d/m/Y') }}</td>
                            <td>@include('panel.desaparecidos.status')</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">No hay casos para estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($cases->hasPages())
            <div class="card-footer">{{ $cases->links() }}</div>
        @endif
    </div>
@endsection
