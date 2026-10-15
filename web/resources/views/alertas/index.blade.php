<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel de alertas recibidas</title>
    <style>
        :root {
            --bg: #f4f5f7; --card: #ffffff; --text: #1f2933; --muted: #6b7280;
            --border: #e5e7eb; --primary: #1d4ed8;
            --pend-bg: #fef3c7; --pend-tx: #92400e;
            --aten-bg: #dbeafe; --aten-tx: #1e40af;
            --ok-bg: #dcfce7;  --ok-tx: #166534;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); }
        .contenedor { max-width: 1000px; margin: 0 auto; padding: 24px 16px; }
        h1 { font-size: 1.5rem; margin: 0 0 4px; }
        .subtitulo { color: var(--muted); margin: 0 0 20px; }
        .tarjeta { background: var(--card); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
        .filtros { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--border); }
        .filtros label { font-weight: 600; font-size: .9rem; }
        .filtros select { padding: 7px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: .9rem; background: #fff; }
        .btn { padding: 7px 14px; border: 0; border-radius: 6px; background: var(--primary); color: #fff; font-size: .9rem; cursor: pointer; text-decoration: none; }
        .btn-sec { background: transparent; color: var(--primary); }
        .total { margin-left: auto; color: var(--muted); font-size: .85rem; }
        .tabla-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        th, td { text-align: left; padding: 11px 16px; border-bottom: 1px solid var(--border); white-space: nowrap; }
        th { background: #f9fafb; font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
        td.desc { white-space: normal; color: var(--muted); min-width: 220px; }
        .estado { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: .8rem; font-weight: 600; }
        .estado-pendiente { background: var(--pend-bg); color: var(--pend-tx); }
        .estado-en_atencion { background: var(--aten-bg); color: var(--aten-tx); }
        .estado-atendida { background: var(--ok-bg); color: var(--ok-tx); }
        .vacio { padding: 32px 16px; text-align: center; color: var(--muted); }
        .paginacion { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; font-size: .88rem; color: var(--muted); }
        .paginacion a { color: var(--primary); text-decoration: none; font-weight: 600; }
        .paginacion .off { color: #c0c4cc; }
    </style>
</head>
<body>
<main class="contenedor">
    <h1>Panel de alertas recibidas</h1>
    <p class="subtitulo">Alertas ordenadas de la más reciente a la más antigua.</p>

    <section class="tarjeta">
        <form method="GET" action="{{ route('alertas.index') }}" class="filtros">
            <label for="tipo">Tipo de alerta</label>
            <select id="tipo" name="tipo" onchange="this.form.submit()">
                <option value="">Todos los tipos</option>
                @foreach ($tipos as $clave => $etiqueta)
                    <option value="{{ $clave }}" @selected($tipoSeleccionado === $clave)>{{ $etiqueta }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="btn">Filtrar</button></noscript>
            @if ($tipoSeleccionado)
                <a href="{{ route('alertas.index') }}" class="btn btn-sec">Quitar filtro</a>
            @endif
            <span class="total">{{ $alertas->total() }} {{ $alertas->total() === 1 ? 'alerta' : 'alertas' }}</span>
        </form>

        @if ($alertas->isEmpty())
            <p class="vacio">No hay alertas{{ $tipoSeleccionado ? ' de tipo "'.$tipos[$tipoSeleccionado].'"' : '' }}.</p>
        @else
            <div class="tabla-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($alertas as $alerta)
                            <tr>
                                <td>{{ $alerta->fecha->format('d/m/Y H:i') }}</td>
                                <td>{{ $alerta->tipo_etiqueta }}</td>
                                <td><span class="estado estado-{{ $alerta->estado }}">{{ $alerta->estado_etiqueta }}</span></td>
                                <td class="desc">{{ $alerta->descripcion }}@if ($alerta->ubicacion) — {{ $alerta->ubicacion }}@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($alertas->hasPages())
                <nav class="paginacion">
                    @if ($alertas->onFirstPage())
                        <span class="off">« Anterior</span>
                    @else
                        <a href="{{ $alertas->previousPageUrl() }}">« Anterior</a>
                    @endif
                    <span>Página {{ $alertas->currentPage() }} de {{ $alertas->lastPage() }}</span>
                    @if ($alertas->hasMorePages())
                        <a href="{{ $alertas->nextPageUrl() }}">Siguiente »</a>
                    @else
                        <span class="off">Siguiente »</span>
                    @endif
                </nav>
            @endif
        @endif
    </section>
</main>
</body>
</html>
