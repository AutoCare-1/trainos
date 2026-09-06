<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela de alimentos — base da TACO (NEPA/UNICAMP, 4a ed., 2011).
 *
 * Existe pra o aluno ESCOLHER o que comeu em vez de digitar texto solto.
 * Digitar é o gargalo do diário: quem escreve "arroz feijão frango" hoje
 * abandona em duas semanas, e o personal recebe texto que não dá pra comparar
 * entre dias.
 *
 * Os valores são por 100 g, como na fonte. Nulo significa "não analisado"
 * (o NA da TACO) e NÃO zero — a diferença importa, porque mostrar 0 g de
 * proteína pra um alimento não analisado é dado falso.
 *
 * Atenção ao escopo: esta tabela serve pro aluno REGISTRAR e pro personal VER
 * o padrão. Ela não vira meta nem prescrição — quem monta plano alimentar é
 * nutricionista (ver App\Support\Nutricao).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Código da TACO: é a chave estável pra reimportar a tabela sem
            // duplicar nem perder o vínculo com o que o aluno já registrou.
            $table->unsignedSmallInteger('codigo_taco')->unique();
            $table->string('categoria');
            $table->string('nome');
            // Por 100 g. decimal e não float: valor nutricional aparece na
            // tela e soma entre alimentos, então erro de ponto flutuante
            // viraria "10,000000001 g" pro usuário.
            $table->decimal('kcal', 7, 2)->nullable();
            $table->decimal('proteina_g', 6, 2)->nullable();
            $table->decimal('carboidrato_g', 6, 2)->nullable();
            $table->decimal('lipideos_g', 6, 2)->nullable();
            $table->decimal('fibra_g', 6, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // A busca por nome é o acesso quente: o aluno digita "fran" e
            // espera ver frango antes de terminar a palavra.
            $table->index('nome', 'idx_foods_nome');
            $table->index('categoria', 'idx_foods_categoria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foods');
    }
};
