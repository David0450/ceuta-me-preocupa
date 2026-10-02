<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PrediccionIa extends Model
{
    protected $table = 'predicciones_ia'; // Laravel inferiría "prediccion_ias"

    protected $fillable = [
        'sugerencia_id', 'predecible_type', 'predecible_id',
        'confianza', 'modelo_version', 'estado',
        'revisado_por', 'revisado_at',
    ];

    protected $casts = [
        'confianza'   => 'decimal:4',
        'revisado_at' => 'datetime',
    ];

    public function sugerencia(): BelongsTo
    {
        return $this->belongsTo(Sugerencia::class);
    }

    public function predecible(): MorphTo
    {
        return $this->morphTo();
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }
}
