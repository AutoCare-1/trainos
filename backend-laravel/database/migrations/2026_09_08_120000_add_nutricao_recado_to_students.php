<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recado do professor sobre alimentação, na ficha do aluno.
 *
 * O diário sempre foi só leitura pro personal (ver AlunoNutricaoController): ele
 * vê o padrão e orienta. Só que "orientar" não tinha canal aqui — era ir no
 * chat. Um campo de texto que o aluno vê fixado no topo da aba Alimentação
 * fecha esse buraco sem virar prescrição: é orientação geral, escrita pelo
 * profissional que responde pelo aluno.
 *
 * `_em` pra a tela poder dizer "atualizado há 3 dias" e o aluno saber se o
 * recado é recente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->text('nutricao_recado')->nullable()->after('health_notes');
            $table->timestamp('nutricao_recado_em')->nullable()->after('nutricao_recado');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['nutricao_recado', 'nutricao_recado_em']);
        });
    }
};
