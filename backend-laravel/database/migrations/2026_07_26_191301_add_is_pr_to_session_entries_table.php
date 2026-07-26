<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // PortalController::registrarSerie já calcula se a série é um recorde pessoal
    // (isPr) comparando com a carga máxima anterior, mas só devolvia no response —
    // sem persistir não dá pra achar "recordes batidos" depois, via poll do job de
    // notificação.
    public function up(): void
    {
        Schema::table('session_entries', function (Blueprint $table) {
            $table->boolean('is_pr')->default(false)->after('load_kg_done');
        });
    }

    public function down(): void
    {
        Schema::table('session_entries', function (Blueprint $table) {
            $table->dropColumn('is_pr');
        });
    }
};
