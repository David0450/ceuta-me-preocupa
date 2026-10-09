@php
    // Paleta fija: cada categoría conserva su color aunque cambie el orden.
    $paleta = ['#1e40af', '#0d9488', '#d97706', '#be185d', '#7c3aed', '#16a34a', '#dc2626', '#0891b2', '#64748b', '#ca8a04'];
    $color = fn ($id) => $paleta[($id - 1) % count($paleta)];

    // Segmentos del donut. Circunferencia normalizada a 100 (r = 15.9155).
    $segmentos = [];
    $acumulado = 0;
    foreach ($categorias as $categoria) {
        if ($categoria['total'] === 0 || $total === 0) {
            continue;
        }
        $valor = $categoria['total'] / $total * 100;
        $segmentos[] = [
            'color' => $color($categoria['id']),
            'valor' => $valor,
            'offset' => 25 - $acumulado, // 25 = empezar arriba (las 12 en punto)
            'nombre' => $categoria['nombre'],
            'total' => $categoria['total'],
        ];
        $acumulado += $valor;
    }

    $resumen = collect($segmentos)
        ->map(fn ($s) => $s['nombre'].': '.$s['total'])
        ->implode(', ');
@endphp

@once
<style>
    .metricas { padding: 4rem 0; }
    .metricas__contenedor { max-width: 56rem; margin: 0 auto; padding: 0 1rem; }
    .metricas__intro { margin-top: .5rem; color: #4b5563; }
    .metricas__cuerpo { margin-top: 2rem; display: flex; flex-wrap: wrap; align-items: center; gap: 2.5rem; }
    .metricas__donut { position: relative; width: 16rem; max-width: 100%; aspect-ratio: 1; flex-shrink: 0; margin: 0 auto; }
    .metricas__donut svg { width: 100%; height: 100%; display: block; }
    .metricas__segmento { transition: stroke-width .15s; }
    .metricas__segmento:hover { stroke-width: 7; }
    .metricas__centro { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; pointer-events: none; }
    .metricas__centro strong { font-size: 2.5rem; line-height: 1; color: #111827; }
    .metricas__centro span { margin-top: .25rem; font-size: .8rem; color: #6b7280; max-width: 7rem; }
    .metricas__leyenda { flex: 1 1 16rem; list-style: none; margin: 0; padding: 0; display: grid; gap: .55rem; }
    .metricas__item { display: flex; align-items: center; gap: .6rem; font-size: .95rem; }
    .metricas__punto { width: .8rem; height: .8rem; border-radius: 50%; flex-shrink: 0; }
    .metricas__nombre { flex: 1; font-weight: 600; }
    .metricas__cifra { font-variant-numeric: tabular-nums; color: #4b5563; white-space: nowrap; }
    .metricas__cifra span { color: #9ca3af; }
    .metricas__item--vacio .metricas__nombre,
    .metricas__item--vacio .metricas__cifra { color: #9ca3af; font-weight: 400; }
    .metricas__item--vacio .metricas__punto { opacity: .3; }
    .metricas__vacio { margin-top: 2rem; padding: 1.5rem; border: 1px dashed #d1d5db; border-radius: .5rem; text-align: center; color: #6b7280; }
    .metricas__pie { margin-top: 1.5rem; font-size: .75rem; color: #9ca3af; }
</style>
@endonce

<section id="metricas" class="metricas" aria-labelledby="metricas-titulo">
    <div class="metricas__contenedor">
        <h2 id="metricas-titulo">¿Qué preocupa en Ceuta?</h2>
        <p class="metricas__intro">
            Sugerencias recibidas por categoría. Solo se cuentan las que el equipo ya ha revisado
            y clasificado de forma definitiva.
        </p>

        @if ($total === 0)
            <p class="metricas__vacio">
                Todavía no hay sugerencias revisadas. Los datos aparecerán aquí en cuanto se clasifiquen las primeras.
            </p>
        @else
            <div class="metricas__cuerpo">
                <div class="metricas__donut">
                    <svg viewBox="0 0 42 42" role="img" aria-label="Gráfico de sugerencias por categoría. {{ $resumen }}.">
                        <circle cx="21" cy="21" r="15.9155" fill="none" stroke="#e5e7eb" stroke-width="6" />
                        @foreach ($segmentos as $s)
                            <circle class="metricas__segmento"
                                    cx="21" cy="21" r="15.9155" fill="none"
                                    stroke="{{ $s['color'] }}" stroke-width="6"
                                    stroke-dasharray="{{ $s['valor'] }} {{ 100 - $s['valor'] }}"
                                    stroke-dashoffset="{{ $s['offset'] }}">
                                <title>{{ $s['nombre'] }}: {{ $s['total'] }} ({{ round($s['valor']) }} %)</title>
                            </circle>
                        @endforeach
                    </svg>
                    <div class="metricas__centro" aria-hidden="true">
                        <strong>{{ number_format($total, 0, ',', '.') }}</strong>
                        <span>{{ $total === 1 ? 'sugerencia clasificada' : 'sugerencias clasificadas' }}</span>
                    </div>
                </div>

                <ul class="metricas__leyenda">
                    @foreach ($categorias as $categoria)
                        <li class="metricas__item {{ $categoria['total'] === 0 ? 'metricas__item--vacio' : '' }}">
                            <span class="metricas__punto" style="background: {{ $color($categoria['id']) }}"></span>
                            <span class="metricas__nombre">{{ $categoria['nombre'] }}</span>
                            <span class="metricas__cifra">
                                {{ $categoria['total'] }} <span>({{ round($categoria['total'] / $total * 100) }} %)</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="metricas__pie">Actualizado: {{ $actualizado }}</p>
        @endif
    </div>
</section>
