<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Traza de auditoría de la revisión. Nunca guarda textos ni datos personales.
 */
class HistorialSugerencia extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'historial_sugerencias';

    protected $fillable = [
        'sugerencia_id',
        'user_id',
        'accion',
        'cambios',
        'motivo',
    ];

    /**
     * @var array<string, string>
     */
    public const ACCIONES = [
        'anonimizacion_guardada' => 'Anonimización guardada',
        'privacidad_revisada' => 'Privacidad marcada como revisada',
        'privacidad_reabierta' => 'Revisión de privacidad reabierta',
        'clasificacion_confirmada' => 'Clasificación confirmada',
        'organismo_reasignado' => 'Organismo reasignado',
        'estado_cambiado' => 'Cambio de estado',
        'seguimiento_guardado' => 'Seguimiento actualizado',
        'original_consultado' => 'Texto original consultado',
        'contacto_consultado' => 'Contacto consultado',
        'contacto_respondido' => 'Contacto marcado como respondido',
        'eliminada' => 'Enviada a la papelera',
        'restaurada' => 'Restaurada desde la papelera',
        'eliminada_definitivamente' => 'Eliminada definitivamente',
    ];

    protected function casts(): array
    {
        return [
            'cambios' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function etiqueta(): string
    {
        return self::ACCIONES[$this->accion] ?? $this->accion;
    }
}
