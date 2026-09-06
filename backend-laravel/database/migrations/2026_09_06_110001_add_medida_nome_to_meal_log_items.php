<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda o rótulo da medida que o aluno escolheu ("2 conchas"), além da grama.
 *
 * A grama sozinha não diz nada pra ele quando reler — e o personal também lê
 * melhor "2 conchas de feijão" que "280 g". Guardar o texto (em vez de
 * recalcular a partir da medida) preserva o registro mesmo se a tabela de
 * medidas mudar depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_log_items', function (Blueprint $table) {
            $table->string('medida_nome')->nullable()->after('quantidade_g');
        });
    }

    public function down(): void
    {
        Schema::table('meal_log_items', function (Blueprint $table) {
            $table->dropColumn('medida_nome');
        });
    }
};
