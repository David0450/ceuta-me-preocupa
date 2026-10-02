<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sugerencias', function (Blueprint $table) {
            // pendiente | procesando | procesada | error
            $table->string('ia_estado')->default('pendiente')->index()->after('estado');
            $table->timestamp('ia_procesada_at')->nullable()->after('ia_estado');
            $table->text('ia_error')->nullable()->after('ia_procesada_at');
        });
    }

    public function down(): void
    {
        Schema::table('sugerencias', function (Blueprint $table) {
            $table->dropColumn(['ia_estado', 'ia_procesada_at', 'ia_error']);
        });
    }
};
