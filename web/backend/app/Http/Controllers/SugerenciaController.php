<?php

namespace App\Http\Controllers;

use App\Models\Sugerencia;
use App\Models\Categoria;
use App\Http\Requests\GuardarSugerenciaRequest;
use Illuminate\Http\Request;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;

class SugerenciaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(GuardarSugerenciaRequest $request)
    {
        if ($this->pareceSpam($request)) {
            // Respuesta falsa de éxito: el bot no sabe que ha sido descartado
            return redirect()->back()->with('success', '¡Gracias! Hemos recibido tu sugerencia.');
        }

        $datos = $request->validated();

        DB::transaction(function () use ($datos) {
            // 1. La sugerencia: registro de trabajo, SIN datos personales.
            $sugerencia = Sugerencia::create([
                'fecha_recepcion'          => now(),
                'categoria_id'             => $datos['categoria_id'],
                'estado'                   => 'recibido',
                'version_aviso_privacidad' => 'v1', // llévalo a config cuando cambie el aviso
                'fecha_aceptacion'         => now(),
                // texto_desidentificado y organismo_id quedan a null:
                // se rellenan después, en los pasos de anonimización y clasificación.
            ]);

            // 2. Subcategorías (1 o 2) en la tabla pivote.
            $sugerencia->subcategorias()->sync($datos['subcategorias']);

            // 3. Datos originales: tabla sensible, acceso restringido.
            $sugerencia->datoOriginal()->create([
                'texto_original'               => $datos['descripcion'],
                'resultado_esperado_original'  => $datos['resultado_esperado'] ?? null,
                'organismo_sugerido_ciudadano' => $datos['organismo_sugerido_ciudadano'] ?? null,
                'zona_origen'                  => $datos['zona'] ?? null,
            ]);

            // 4. Contacto: solo si la persona lo dejó (implica que quiere respuesta).
            //    El canal se cifra solo, por el cast 'encrypted' del modelo Contacto.
            if (! empty($datos['contacto'])) {
                $sugerencia->contacto()->create([
                    'canal'                => $datos['contacto'],
                    'consiente_respuesta'  => true,
                    'fecha_consentimiento' => now(),
                ]);
            }
        });

        // Formulario clásico (vista renderizada por Laravel): volvemos con un aviso.
        // Si el envío fuera por fetch/AJAX, cambia esto por:
        //   return response()->json(['mensaje' => '...'], 201);
        return back()->with('exito', 'Tu sugerencia se ha registrado correctamente. Gracias por participar.');
    }


    /**
     * Display the specified resource.
     */
    public function show(Sugerencia $sugerencia)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Sugerencia $sugerencia)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sugerencia $sugerencia)
    {
        //
    }

    public function create()
    {
        $categorias = Categoria::with('subcategorias')->orderBy('nombre')->get();

        $subcategoriasPorCategoria = $categorias->mapWithKeys(fn ($c) => [
            $c->id => $c->subcategorias->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre])->values(),
        ]);

        return view('index', compact('categorias', 'subcategoriasPorCategoria'));
    }

    private function pareceSpam($request): bool
    {
        if (filled($request->input('website'))) {
            return true;
        }

        try {
            $inicio = (int) decrypt($request->input('_t'));
        } catch (DecryptException $e) {
            return true;
        }

        // Menos de 3 s rellenando el formulario = casi seguro un bot
        return (now()->timestamp - $inicio) < 3;
    }
}
