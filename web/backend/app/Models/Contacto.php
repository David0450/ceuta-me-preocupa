<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contacto extends Model
{
    protected $table = 'contactos';

    protected $fillable = [
        'sugerencia_id',
        'canal',
        'consiente_respuesta',
        'fecha_consentimiento',
        'respondido',
        'fecha_respuesta',
    ];

    protected $casts = [
        // Cifra al guardar y descifra al leer de forma transparente.
        'canal'                => 'encrypted',
        'consiente_respuesta'  => 'boolean',
        'respondido'           => 'boolean',
        'fecha_consentimiento' => 'datetime',
        'fecha_respuesta'      => 'datetime',
    ];

    public function sugerencia(): BelongsTo
    {
        return $this->belongsTo(Sugerencia::class);
    }
}
