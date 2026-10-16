<?php

namespace App\View\Components;

use App\Models\Categoria;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Métrica pública: sugerencias por categoría definitiva.
 *
 * "Definitiva" = la clasificación ya la ha confirmado o corregido una persona
 * (clasificacion_revisada_at no nulo). Lo que solo ha elegido el ciudadano o
 * propuesto la IA no se publica.
 *
 * - Las sugerencias en la papelera quedan fuera solas (SoftDeletes).
 * - Los duplicados SÍ cuentan: se marcaron sin borrar precisamente para
 *   conservar el recuento de envíos. Para excluirlos, descomenta la línea.
 */
class MetricasCategorias extends Component
{
    /** Minutos que se cachea el cálculo (la landing es pública). */
    private const CACHE_MINUTOS = 10;

    public array $categorias;
    public int $total;
    public ?string $actualizado;

    public function __construct()
    {
        $datos = Cache::remember('metricas.categorias_definitivas', now()->addMinutes(self::CACHE_MINUTOS), function () {
            $categorias = Categoria::query()
                ->withCount(['sugerencias as total' => fn ($q) => $q
                    ->whereNotNull('clasificacion_revisada_at')
                    // ->whereNull('duplicado_de_id')
                ])
                ->orderByDesc('total')
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre, 'total' => (int) $c->total])
                ->all();

            return [
                'categorias' => $categorias,
                'total' => array_sum(array_column($categorias, 'total')),
                'actualizado' => now()->format('d/m/Y H:i'),
            ];
        });

        $this->categorias = $datos['categorias'];
        $this->total = $datos['total'];
        $this->actualizado = $datos['actualizado'];
    }

    public function render(): View
    {
        return view('components.metricas-categorias');
    }
}
