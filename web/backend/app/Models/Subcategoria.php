<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subcategoria extends Model
{
    protected $table = 'subcategorias';

    protected $fillable = ['categoria_id', 'nombre'];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function sugerencias(): BelongsToMany
    {
        return $this->belongsToMany(Sugerencia::class, 'sugerencia_subcategoria')
                    ->withTimestamps();
    }
}
