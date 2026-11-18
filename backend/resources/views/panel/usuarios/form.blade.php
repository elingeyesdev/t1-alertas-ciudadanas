@extends('layouts.panel')
@php
    use App\Support\PanelLabels;
    $editing = (bool) $editUser;
    $role = old('role', $editing ? $editUser->role?->name : $defaultRole);
    $profile = $editUser?->authorityProfile;
    $showProfile = $editing ? (bool) $profile : true;
@endphp

@section('title', $editing ? 'Editar usuario' : 'Nuevo usuario')
@section('pretitle')
    <a href="{{ route('panel.usuarios.index') }}" class="text-reset"><i class="ti ti-arrow-left"></i> Usuarios</a>
@endsection

@section('content')
    <form method="POST" action="{{ $editing ? route('panel.usuarios.update', $editUser->id) : route('panel.usuarios.store') }}" class="card" style="max-width: 760px" data-user-form>
        @csrf
        @if ($editing) @method('PATCH') @endif
        <div class="card-body">
            @unless ($editing)
                <div class="mb-4">
                    <label class="form-label required">Tipo de cuenta</label>
                    <div class="form-selectgroup">
                        @foreach (PanelLabels::ROLES as $value => [$label])
                            <label class="form-selectgroup-item">
                                <input type="radio" name="role" value="{{ $value }}" class="form-selectgroup-input" @checked($role === $value) data-role-input>
                                <span class="form-selectgroup-label">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="form-hint">A las autoridades se les envían sus credenciales por correo.</div>
                </div>
            @else
                <p class="mb-4">Rol: {!! PanelLabels::badge(PanelLabels::ROLES, $editUser->role?->name) !!}</p>
            @endunless

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required" for="first_name">Nombre</label>
                    <input id="first_name" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $editUser?->first_name) }}" required maxlength="255">
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="last_name">Apellido</label>
                    <input id="last_name" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $editUser?->last_name) }}" required maxlength="255">
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="phone">Teléfono</label>
                    <input id="phone" name="phone" type="tel" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $editUser?->phone) }}" required>
                    <div class="form-hint">8 dígitos. Es con lo que inicia sesión.</div>
                </div>
                @unless ($editing)
                    <div class="col-md-6">
                        <label class="form-label required" for="password">Contraseña</label>
                        <input id="password" name="password" type="text" autocomplete="new-password" minlength="6" maxlength="20" class="form-control @error('password') is-invalid @enderror" required>
                        <div class="form-hint">Entre 6 y 20 caracteres, sin espacios.</div>
                    </div>
                @endunless
            </div>

            @if ($showProfile)
                <fieldset class="mt-4" data-authority-fields @if (! $editing && $role !== 'autoridad') hidden @endif>
                    <legend class="h3">Perfil de autoridad</legend>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label required" for="profile_type">Cargo</label>
                            <select id="profile_type" name="profile_type" class="form-select @error('profile_type') is-invalid @enderror" data-authority-required>
                                <option value="">Elige…</option>
                                @foreach (PanelLabels::PROFILE_TYPES as $value => $label)
                                    <option value="{{ $value }}" @selected(old('profile_type', $profile?->profile_type?->value) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="ci">CI</label>
                            <input id="ci" name="ci" class="form-control @error('ci') is-invalid @enderror" value="{{ old('ci', $profile?->ci) }}" maxlength="255" data-authority-required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="gmail">Correo</label>
                            <input id="gmail" name="gmail" type="email" class="form-control @error('gmail') is-invalid @enderror" value="{{ old('gmail', $profile?->gmail) }}" maxlength="255" data-authority-required>
                        </div>
                    </div>
                </fieldset>
            @endif
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('panel.usuarios.index') }}" class="btn">Cancelar</a>
            <button type="submit" class="btn btn-primary">{{ $editing ? 'Guardar cambios' : 'Crear cuenta' }}</button>
        </div>
    </form>
@endsection
