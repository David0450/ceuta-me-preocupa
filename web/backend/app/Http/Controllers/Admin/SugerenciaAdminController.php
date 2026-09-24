<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sugerencia;

class SugerenciaAdminController extends Controller
{
    /**
     * Listado de sugerencias recibidas. Solo metadatos:
     * no carga el texto original ni el contacto (minimización).
     */
    public function index()
    {
        $sugerencias = Sugerencia::with(['categoria', 'subcategorias'])
            ->withCount('contacto')           // contacto_count: 0 o 1, sin cargar el dato
            ->latest('fecha_recepcion')
            ->paginate(20);

        return view('admin.sugerencias.index', compact('sugerencias'));
    }

    /**
     * Ficha de una sugerencia, ya con los datos sensibles.
     */
    public function show(Sugerencia $sugerencia)
    {
        $sugerencia->load([
            'categoria',
            'subcategorias',
            'organismo',
            'datoOriginal',
            'contacto',
        ]);

        return view('admin.sugerencias.show', compact('sugerencia'));
    }
}
