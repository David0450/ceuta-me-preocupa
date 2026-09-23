<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sugerencia extends Model
{
    protected $table = 'sugerencias';

    protected $fillable = [
        'fecha_recepcion',
        'categoria_id',
        'organismo_id',
        'estado',
        'texto_desidentificado',
        'resultado_esperado_desidentificado',
        'version_aviso_privacidad',
        'fecha_aceptacion',
    ];

    protected $casts = [
        'fecha_recepcion'  => 'datetime',
        'fecha_aceptacion' => 'datetime',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
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
}
