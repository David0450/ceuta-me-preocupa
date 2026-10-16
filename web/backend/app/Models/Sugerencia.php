<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sugerencia extends Model
{
    use SoftDeletes;

    protected $table = 'sugerencias';

    protected $fillable = [
        'fecha_recepcion',
        'categoria_id',
        'categoria_ciudadano_id',
        'subcategorias_ciudadano',
        'organismo_id',
        'competencia_por_confirmar',
        'estado',
        'nivel_urgencia',
        'texto_desidentificado',
        'resultado_esperado_desidentificado',
        'version_aviso_privacidad',
        'fecha_aceptacion',
        'privacidad_revisada_por',
        'privacidad_revisada_at',
        'clasificacion_revisada_por',
        'clasificacion_revisada_at',
        'duplicado_de_id',
        'accion_adoptada',
        'motivo_eliminacion',
        'eliminada_por',
    ];

    protected $casts = [
        'fecha_recepcion' => 'datetime',
        'fecha_aceptacion' => 'datetime',
        'ia_procesada_at' => 'datetime',
        'privacidad_revisada_at' => 'datetime',
        'clasificacion_revisada_at' => 'datetime',
        'competencia_por_confirmar' => 'boolean',
        'subcategorias_ciudadano' => 'array',
    ];

    /**
     * Estados del flujo, en orden. Deben coincidir con el enum de la migración.
     *
     * @var array<string, string>
     */
    public const ESTADOS = [
        'recibido' => 'Recibido',
        'en_revision' => 'En revisión',
        'clasificado' => 'Clasificado',
        'derivado' => 'Derivado',
        'cerrado' => 'Cerrado',
    ];

    /**
     * @var array<string, string>
     */
    public const URGENCIAS = [
        'ordinaria' => 'Ordinaria',
        'prioritaria' => 'Prioritaria',
        'urgente' => 'Urgente',
    ];

    /**
     * @var array<string, int>
     */
    public const RANGO_URGENCIA = ['urgente' => 0, 'prioritaria' => 1, 'ordinaria' => 2];

    /**
     * Motivos para enviar una sugerencia a la papelera. Los duplicados NO se
     * eliminan: se marcan con duplicado_de_id para no perder el recuento.
     *
     * @var array<string, string>
     */
    public const MOTIVOS_ELIMINACION = [
        'spam' => 'Spam o envío automatizado',
        'ofensivo' => 'Contenido ofensivo o discriminatorio',
        'fuera_ambito' => 'Fuera del ámbito del buzón',
        'datos_terceros' => 'Expone datos de terceras personas',
        'prueba' => 'Envío de prueba',
        'otro' => 'Otro motivo',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /** Categoría que eligió la persona al enviar (no cambia al revisar). */
    public function categoriaCiudadano(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_ciudadano_id');
    }

    /**
     * Subcategorías que eligió la persona al enviar.
     *
     * @return \Illuminate\Support\Collection<int, Subcategoria>
     */
    public function subcategoriasCiudadano()
    {
        return Subcategoria::whereIn('id', $this->subcategorias_ciudadano ?? [])->orderBy('nombre')->get();
    }

    public function organismo(): BelongsTo
    {
        return $this->belongsTo(Organismo::class);
    }

    public function subcategorias(): BelongsToMany
    {
        return $this->belongsToMany(Subcategoria::class, 'sugerencia_subcategoria')
            ->withTimestamps();
    }

    public function datoOriginal(): HasOne
    {
        return $this->hasOne(DatoOriginal::class);
    }

    public function contacto(): HasOne
    {
        return $this->hasOne(Contacto::class);
    }
    /** Propuesta más reciente de la IA (puede haber varias si se reprocesa). */
    public function propuestaIa(): HasOne
    {
        return $this->hasOne(PropuestaIa::class)->latestOfMany();
    }

    public function propuestasIa(): HasMany
    {
        return $this->hasMany(PropuestaIa::class);
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialSugerencia::class)->latest('created_at')->latest('id');
    }

    public function duplicadoDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicado_de_id')->withTrashed();
    }

    public function duplicados(): HasMany
    {
        return $this->hasMany(self::class, 'duplicado_de_id');
    }

    public function revisorPrivacidad(): BelongsTo
    {
        return $this->belongsTo(User::class, 'privacidad_revisada_por');
    }

    public function revisorClasificacion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clasificacion_revisada_por');
    }

    public function eliminadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'eliminada_por');
    }

    public function privacidadRevisada(): bool
    {
        return $this->privacidad_revisada_at !== null;
    }

    public function clasificacionRevisada(): bool
    {
        return $this->clasificacion_revisada_at !== null;
    }

    /**
     * Urgencia con la que hay que tratar la sugerencia ahora mismo. Mientras
     * nadie haya confirmado la clasificación, si la IA la considera más urgente
     * manda la IA: así una urgencia no se queda escondida esperando revisión.
     */
    public function urgenciaEfectiva(): string
    {
        return $this->urgenciaPorIa() ? $this->propuestaIa->nivel_urgencia : ($this->nivel_urgencia ?? 'ordinaria');
    }

    /** La urgencia efectiva viene de la IA y nadie la ha confirmado todavía. */
    public function urgenciaPorIa(): bool
    {
        $ia = $this->propuestaIa?->nivel_urgencia;

        return ! $this->clasificacionRevisada()
            && isset(self::RANGO_URGENCIA[$ia])
            && self::RANGO_URGENCIA[$ia] < (self::RANGO_URGENCIA[$this->nivel_urgencia] ?? 2);
    }

    /**
     * Expresión SQL con el rango de la urgencia efectiva (0 urgente, 1 prioritaria,
     * 2 ordinaria). Misma regla que urgenciaEfectiva(), para ordenar y filtrar.
     */
    public static function sqlRangoUrgencia(): string
    {
        $rango = fn (string $columna) => "(case {$columna} when 'urgente' then 0 when 'prioritaria' then 1 else 2 end)";
        $ia = '(select p.nivel_urgencia from propuestas_ia p where p.sugerencia_id = sugerencias.id order by p.id desc limit 1)';

        return "(case when sugerencias.clasificacion_revisada_at is null and {$rango($ia)} < {$rango('sugerencias.nivel_urgencia')}"
            ." then {$rango($ia)} else {$rango('sugerencias.nivel_urgencia')} end)";
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * Requisitos pendientes para pasar al estado indicado. Vacío = se puede.
     * Retroceder de estado siempre está permitido.
     *
     * @return list<string>
     */
    public function requisitosPendientesPara(string $estado): array
    {
        $orden = array_keys(self::ESTADOS);
        $destino = array_search($estado, $orden, true);
        $pendientes = [];

        if ($destino === false || $destino <= array_search('en_revision', $orden, true)) {
            return [];
        }

        if (blank($this->texto_desidentificado)) {
            $pendientes[] = 'Falta el texto desidentificado.';
        }
        if (! $this->privacidadRevisada()) {
            $pendientes[] = 'Falta la revisión humana de privacidad.';
        }
        if (! $this->clasificacionRevisada()) {
            $pendientes[] = 'Falta confirmar la clasificación.';
        }
        if ($this->organismo_id === null && ! $this->competencia_por_confirmar) {
            $pendientes[] = 'Asigna un organismo o marca «competencia por confirmar».';
        }

        if ($destino >= array_search('derivado', $orden, true)) {
            if ($this->organismo_id === null || $this->competencia_por_confirmar) {
                $pendientes[] = 'Para derivar hace falta un organismo confirmado.';
            }
        }

        if ($estado === 'cerrado' && blank($this->accion_adoptada)) {
            $pendientes[] = 'Indica la acción adoptada antes de cerrar.';
        }

        return array_values(array_unique($pendientes));
    }

    /**
     * Anota una acción en el historial de auditoría.
     *
     * @param  array<string, mixed>  $cambios
     */
    public function registrar(string $accion, array $cambios = [], ?string $motivo = null): HistorialSugerencia
    {
        return HistorialSugerencia::create([
            'sugerencia_id' => $this->id,
            'user_id' => auth()->id(),
            'accion' => $accion,
            'cambios' => $cambios ?: null,
            'motivo' => $motivo,
        ]);
    }
}
