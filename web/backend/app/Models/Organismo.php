<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organismo extends Model
{
    protected $table = 'organismos';

    protected $fillable = ['nombre', 'tipo', 'ambito', 'punto_contacto'];

    // Sugerencias asignadas a este organismo (columna organismo_id).
    public function sugerencias(): HasMany
    {
        return $this->hasMany(Sugerencia::class);
    }
}
