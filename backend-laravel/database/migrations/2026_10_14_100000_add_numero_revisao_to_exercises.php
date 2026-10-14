<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Número congelado de cada exercício na tela de vídeos.
 *
 * O número era recalculado a cada carga (grupo + ordem alfabética), então
 * renomear ou retirar um exercício deslocava todos os seguintes. Enquanto o
 * Hugo corrige pela lista que ele mesmo mandou (13/10/2026, 509 itens), o
 * número tem de ficar parado: se o 400 sai, o 401 continua 401. Quem preenche
 * é `exercicios:congelar-numeracao`; renumerar só depois que a lista inteira
 * estiver certa (decisão do Filipe, 14/10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->unsignedSmallInteger('numero_revisao')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropIndex(['numero_revisao']);
            $table->dropColumn('numero_revisao');
        });
    }
};
