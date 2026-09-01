<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Água passa a ser medida em mililitros, não em "copos".
 *
 * "Copo" não é unidade: copo americano é 200 ml, um copo comum passa de 300 —
 * então "8 copos" não vira volume nenhum, e o aluno não consegue comparar com
 * a referência que ele de fato conhece ("2 litros por dia").
 *
 * O toque continua sendo o gesto (ninguém vai digitar volume), só que cada
 * toque agora soma um volume real: copo (200) ou garrafa (500).
 *
 * Migration separada em vez de editar a que criou a tabela porque o
 * colaborador já pode ter rodado a original na máquina dele — reescrever
 * deixaria o banco dele em silêncio fora de sintonia com o código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hydration_logs', function (Blueprint $table) {
            $table->unsignedInteger('ml')->default(0)->after('data');
        });

        // A tabela nasceu hoje e o app não está em produção, mas converter é
        // barato e evita zerar o registro de quem já testou: 250 ml é a média
        // entre o copo americano e o copo de casa.
        \Illuminate\Support\Facades\DB::table('hydration_logs')->update([
            'ml' => \Illuminate\Support\Facades\DB::raw('copos * 250'),
        ]);

        Schema::table('hydration_logs', function (Blueprint $table) {
            $table->dropColumn('copos');
        });
    }

    public function down(): void
    {
        Schema::table('hydration_logs', function (Blueprint $table) {
            $table->unsignedSmallInteger('copos')->default(0)->after('data');
        });

        \Illuminate\Support\Facades\DB::table('hydration_logs')->update([
            'copos' => \Illuminate\Support\Facades\DB::raw('ml / 250'),
        ]);

        Schema::table('hydration_logs', function (Blueprint $table) {
            $table->dropColumn('ml');
        });
    }
};
