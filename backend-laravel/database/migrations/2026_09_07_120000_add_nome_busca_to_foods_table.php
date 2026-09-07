<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nome do alimento sem acento e em minúscula, só pra busca.
 *
 * Existe porque a TACO escreve "Macarrão" e metade das pessoas digita
 * "macarrao" no teclado do celular. Sem esta coluna, achar ou não achar
 * dependia da collation do banco: o MySQL daqui ignora acento e encontrava, o
 * SQLite não encontrava nada. Isso é frágil demais pra uma busca que, quando
 * falha, faz o aluno concluir que o alimento não existe no app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->string('nome_busca')->nullable()->after('nome')->index();
        });
    }

    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->dropColumn('nome_busca');
        });
    }
};
