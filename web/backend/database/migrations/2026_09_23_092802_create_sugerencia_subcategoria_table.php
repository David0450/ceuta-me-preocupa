<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sugerencia_subcategoria', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sugerencia_id')
                  ->constrained('sugerencias')
                  ->cascadeOnDelete();

            $table->foreignId('subcategoria_id')
                  ->constrained('subcategorias')
                  ->cascadeOnDelete();

            $table->unique(['sugerencia_id', 'subcategoria_id'], 'sug_subcat_unique');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sugerencia_subcategoria');
    }
};
