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
     * Pantalla de revisión. El componente Livewire carga los datos que necesita;
     * el texto original y el contacto solo se muestran a petición (y se registra).
     */
    public function show(Sugerencia $sugerencia)
    {
        return view('admin.sugerencias.show', compact('sugerencia'));
    }
}
