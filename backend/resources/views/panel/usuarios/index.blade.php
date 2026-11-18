@extends('layouts.panel')
@php use App\Support\PanelLabels; @endphp

@section('title', 'Usuarios')
@section('pretitle', 'Ciudadanos, autoridades y administradores')

@section('actions')
    <a href="{{ route('panel.usuarios.create', ['rol' => 'usuario']) }}" class="btn"><i class="ti ti-user-plus me-1"></i> Nuevo usuario</a>
    <a href="{{ route('panel.usuarios.create') }}" class="btn btn-primary"><i class="ti ti-shield-plus me-1"></i> Nueva autoridad</a>
@endsection

@section('content')
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Total de usuarios', 'value' => $stats['total'], 'icon' => 'ti-users', 'color' => 'blue'])</div>
        <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Ciudadanos', 'value' => $stats['ciudadanos'], 'icon' => 'ti-user', 'color' => 'purple', 'href' => route('panel.usuarios.index', ['rol' => 'usuario'])])</div>
        <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Autoridades', 'value' => $stats['autoridades'], 'icon' => 'ti-shield', 'color' => 'green', 'href' => route('panel.usuarios.index', ['rol' => 'autoridad'])])</div>
        <div class="col-sm-6 col-lg-3">@include('panel.partials.stat', ['label' => 'Administradores', 'value' => $stats['admins'], 'icon' => 'ti-user-shield', 'color' => 'azure', 'href' => route('panel.usuarios.index', ['rol' => 'admin'])])</div>
    </div>

    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="q">Buscar</label>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Nombre o teléfono">
                    </div>
                </div>
                <div class="col-8 col-md-4">
                    <label class="form-label" for="rol">Rol</label>
                    <select id="rol" name="rol" class="form-select">
                        <option value="">Todos</option>
                        @foreach (PanelLabels::ROLES as $value => [$label])
                            <option value="{{ $value }}" @selected(($filters['rol'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-4 col-md-2 d-flex gap-1">
                    <button class="btn btn-primary w-100" aria-label="Filtrar"><i class="ti ti-filter"></i></button>
                    @if (array_filter($filters))
                        <a href="{{ route('panel.usuarios.index') }}" class="btn btn-icon" aria-label="Quitar filtros"><i class="ti ti-x"></i></a>
                    @endif
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th>Nombre</th><th>Teléfono</th><th>Rol</th><th>Perfil de autoridad</th><th class="w-1"></th></tr></thead>
                <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar avatar-sm">{{ mb_substr($u->first_name, 0, 1) }}{{ mb_substr($u->last_name, 0, 1) }}</span>
                                <span>{{ $u->first_name }} {{ $u->last_name }}</span>
                            </div>
                        </td>
                        <td class="text-secondary">{{ $u->phone }}</td>
                        <td>{!! PanelLabels::badge(PanelLabels::ROLES, $u->role?->name) !!}</td>
                        <td class="text-secondary">
                            @if ($u->authorityProfile)
                                {{ PanelLabels::label(PanelLabels::PROFILE_TYPES, $u->authorityProfile->profile_type?->value) }}
                                · CI {{ $u->authorityProfile->ci }}<br><span class="small">{{ $u->authorityProfile->gmail }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Acciones</button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('panel.usuarios.edit', $u->id) }}"><i class="ti ti-edit me-2"></i>Editar</a>
                                    @if ($u->authorityProfile)
                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#mail-modal"
                                                data-mail-action="{{ route('panel.usuarios.mail', $u->id) }}" data-mail-to="{{ $u->authorityProfile->gmail }}">
                                            <i class="ti ti-mail me-2"></i>Enviar correo
                                        </button>
                                        <form method="POST" action="{{ route('panel.usuarios.credentials', $u->id) }}"
                                              data-confirm="Se generará una contraseña nueva y se enviará a {{ $u->authorityProfile->gmail }}." data-confirm-title="¿Reenviar credenciales?" data-confirm-color="primary">
                                            @csrf <button class="dropdown-item"><i class="ti ti-key me-2"></i>Reenviar credenciales</button>
                                        </form>
                                    @endif
                                    @if ($u->id !== auth('web')->id())
                                        <div class="dropdown-divider"></div>
                                        <form method="POST" action="{{ route('panel.usuarios.destroy', $u->id) }}"
                                              data-confirm="{{ $u->first_name }} {{ $u->last_name }} ya no podrá iniciar sesión." data-confirm-title="¿Eliminar usuario?">
                                            @csrf @method('DELETE') <button class="dropdown-item text-danger"><i class="ti ti-trash me-2"></i>Eliminar</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-5">No hay usuarios con esos filtros.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="card-footer d-flex align-items-center">{{ $users->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>

    <div class="modal modal-blur fade" id="mail-modal" tabindex="-1" aria-labelledby="mail-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <form method="POST" class="modal-content" data-mail-form>
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title" id="mail-modal-title">Enviar correo</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary">Para: <strong data-mail-to></strong></p>
                    <div class="mb-3">
                        <label class="form-label required" for="subject">Asunto</label>
                        <input id="subject" name="subject" class="form-control" maxlength="150" required>
                    </div>
                    <div>
                        <label class="form-label required" for="content">Mensaje</label>
                        <textarea id="content" name="content" rows="5" class="form-control" maxlength="5000" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="ti ti-send me-1"></i> Enviar</button>
                </div>
            </form>
        </div>
    </div>
@endsection
