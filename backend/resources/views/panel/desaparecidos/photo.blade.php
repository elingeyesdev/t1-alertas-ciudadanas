@extends('layouts.panel')

@section('title', $title)
@section('pretitle', $case->name)

@section('actions')
    <a href="{{ route('panel.desaparecidos.show', $case) }}" class="btn">
        <i class="ti ti-arrow-left me-1" aria-hidden="true"></i> Volver al caso
    </a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body d-flex justify-content-center">
            <img class="img-fluid rounded" style="max-height: 75vh; object-fit: contain" src="{{ $imageUrl }}" alt="{{ $title }} de {{ $case->name }}">
        </div>
    </div>
@endsection
