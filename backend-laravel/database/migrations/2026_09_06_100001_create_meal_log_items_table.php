<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Os alimentos escolhidos numa refeição do diário.
 *
 * Tabela separada (e não uma coluna em meal_logs) porque uma refeição tem
 * vários alimentos — "arroz, feijão e frango" são três linhas, e é isso que
 * permite ao personal perguntar "quantos dias ele almoçou sem proteína?".
 * Com texto livre isso não dá pra responder.
 *
 * meal_logs.descricao continua existindo e continua valendo: nem tudo que o
 * aluno come está na TACO (marmita da mãe, prato de restaurante), e obrigar a
 * encaixar na tabela faria ele parar de registrar. Os dois convivem.
 *
 * quantidade_g é OPCIONAL de propósito. O valor do diário está em saber O QUE
 * foi comido, não em quanto — ninguém acerta grama no olho, e transformar isso
 * em análise de consumo com meta entraria no campo do nutricionista.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_log_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('meal_log_id')->constrained('meal_logs')->cascadeOnDelete();
            // restrictOnDelete: um alimento da TACO nunca é apagado pelo app,
            // e se um dia for, é melhor a migration falhar alto do que sumir
            // silenciosamente com o registro que o aluno fez.
            $table->foreignUuid('food_id')->constrained('foods')->restrictOnDelete();
            $table->unsignedSmallInteger('quantidade_g')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('meal_log_id', 'idx_meal_log_items_refeicao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_log_items');
    }
};
