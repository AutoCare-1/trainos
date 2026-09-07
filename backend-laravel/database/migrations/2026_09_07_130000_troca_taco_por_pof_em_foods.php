<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Troca o catálogo de alimentos: sai a TACO, entra a tabela da POF/IBGE.
 *
 * A TACO tem 597 ingredientes analisados em laboratório e serve pra isso. Só
 * que quem registra o almoço não comeu ingrediente: comeu macarrão cozido, e
 * "Macarrão cozido" não existe na TACO — só "Macarrão, trigo, cru". Pizza e
 * lasanha também não existiam. Busca que não acha comida básica faz o aluno
 * (com razão) achar que o app é mal feito.
 *
 * A POF 2008-2009 mapeou justamente os alimentos COMO CONSUMIDOS: 1.971
 * combinações de alimento e preparo. E, por serem da mesma pesquisa, a tabela
 * de composição e a de medidas caseiras compartilham a chave — o que faz a
 * cobertura de medida saltar de 87 alimentos para 1.969.
 *
 * A chave passa a ser (codigo_pof, codigo_preparo). `categoria` sai junto: veio
 * da TACO, a POF não tem equivalente e nenhuma tela mostrava o campo.
 */
return new class extends Migration
{
    public function up(): void
    {
        // O catálogo inteiro é substituído, então o que apontava pro catálogo
        // antigo não tem pra onde apontar. Registros de refeição guardam o nome
        // do alimento e a quantidade em colunas próprias — o histórico do aluno
        // continua legível mesmo sem o vínculo.
        Schema::disableForeignKeyConstraints();
        DB::table('food_measures')->delete();
        DB::table('meal_log_items')->delete();
        DB::table('foods')->delete();
        Schema::enableForeignKeyConstraints();

        Schema::table('foods', function (Blueprint $table) {
            // O índice sai antes da coluna: no SQLite, largar a coluna com o
            // índice ainda apontando pra ela derruba a migration.
            $table->dropIndex('idx_foods_categoria');
            $table->dropUnique(['codigo_taco']);
        });

        Schema::table('foods', function (Blueprint $table) {
            $table->dropColumn(['codigo_taco', 'categoria']);
        });

        Schema::table('foods', function (Blueprint $table) {
            $table->unsignedInteger('codigo_pof')->after('id');
            $table->unsignedSmallInteger('codigo_preparo')->after('codigo_pof');
            $table->unique(['codigo_pof', 'codigo_preparo'], 'uq_foods_pof');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('food_measures')->delete();
        DB::table('meal_log_items')->delete();
        DB::table('foods')->delete();
        Schema::enableForeignKeyConstraints();

        Schema::table('foods', function (Blueprint $table) {
            $table->dropUnique('uq_foods_pof');
            $table->dropColumn(['codigo_pof', 'codigo_preparo']);
        });

        Schema::table('foods', function (Blueprint $table) {
            $table->unsignedSmallInteger('codigo_taco')->after('id')->unique();
            $table->string('categoria')->after('nome');
        });

        Schema::table('foods', function (Blueprint $table) {
            $table->index('categoria', 'idx_foods_categoria');
        });
    }
};
