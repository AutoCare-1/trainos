<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orientações gerais de alimentação pré/pós-treino que o aluno pediu à IA.
 *
 * Fica gravado por dois motivos, nenhum deles técnico:
 *
 * 1. O personal precisa VER o que foi dito ao aluno dele. É ele quem responde
 *    profissionalmente pelo acompanhamento, então não pode haver orientação
 *    circulando no app sem ele saber.
 * 2. Se um dia alguém questionar o que o app orientou, existe o registro do
 *    texto exato — em vez da palavra de um contra a do outro.
 *
 * O que a IA pode dizer aqui é deliberadamente limitado (sem gramas, sem
 * calorias, sem suplemento, sem plano fechado) — ver App\Support\Nutricao.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('momento'); // pre_treino|pos_treino
            $table->text('resposta');
            // Marca quando a IA se recusou e encaminhou pro nutricionista (por
            // condição de saúde na anamnese). Serve pro personal saber que o
            // aluno pediu e ficou sem resposta — é dele a decisão de encaminhar.
            $table->boolean('encaminhou_nutricionista')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'created_at'], 'idx_nutrition_suggestions_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_suggestions');
    }
};
