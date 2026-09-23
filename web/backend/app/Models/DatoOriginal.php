<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatoOriginal extends Model
{
    protected $table = 'datos_originales';

    protected $fillable = [
        'sugerencia_id',
        'texto_original',
        'resultado_esperado_original',
        'organismo_sugerido_ciudadano',
        'zona_origen',
    ];

    public function sugerencia(): BelongsTo
    {
        return $this->belongsTo(Sugerencia::class);
    }
}
