<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Impede duas séries iguais (mesma sessão + exercício + número de série) —
     * protege contra double-tap no botão de registrar série, que sem essa
     * constraint duplicava a linha (e podia disparar "novo recorde" duas vezes).
     */
    public function up(): void
    {
        Schema::table('session_entries', function (Blueprint $table) {
            $table->unique(
                ['training_session_id', 'workout_exercise_id', 'set_number'],
                'uq_session_entries_serie'
            );
        });
    }

    public function down(): void
    {
        Schema::table('session_entries', function (Blueprint $table) {
            $table->dropUnique('uq_session_entries_serie');
        });
    }
};
