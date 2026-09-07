<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medidas caseiras por alimento (IBGE/POF 2008-2009).
 *
 * "1 concha de feijão" é o que a pessoa entende; "140 g" não. Sem isso o
 * aluno tem que estimar grama no olho — e não estima, ele desiste de
 * registrar a quantidade.
 *
 * Nem todo alimento tem medida, e isso é de propósito: casar a TACO com a
 * tabela do IBGE não é trivial (taxonomias diferentes), e alimento com medida
 * ERRADA é pior que alimento sem medida. Quem não tem cai no campo de gramas,
 * que continua existindo. Ver database/medidas_pof.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_measures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('food_id')->constrained('foods')->cascadeOnDelete();
            $table->string('nome');
            // Peso da medida. decimal porque tem medida de fração de grama
            // (colher de café de linhaça = 0,4 g).
            $table->decimal('gramas', 7, 2);
            $table->timestamp('created_at')->useCurrent();

            // Reimportar não pode duplicar a mesma medida do mesmo alimento.
            $table->unique(['food_id', 'nome'], 'uq_food_measures_alimento_nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_measures');
    }
};
