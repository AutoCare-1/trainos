<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diário alimentar do aluno: ele REGISTRA o que comeu, o personal VÊ.
 *
 * A distinção não é estilística. No Brasil, prescrição dietética é privativa
 * do nutricionista (Lei 8.234/91) — um personal com CREF não pode montar
 * cardápio. Por isso aqui não existe "plano alimentar", "meta de calorias"
 * nem quantidade prescrita: só o registro do próprio aluno, que o personal
 * usa pra orientar de forma geral e pra saber quando encaminhar a um nutri.
 *
 * Mesma forma do check-in por foto (que já funciona bem no app), mas sem o
 * unique por dia: são várias refeições no mesmo dia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('data');
            // cafe|lanche|almoco|jantar|pre_treino|pos_treino — string e não enum
            // porque a lista tende a mudar com o uso, e migration de enum no
            // MySQL trava a tabela.
            $table->string('momento');
            // Foto é opcional: registrar só com texto tem que continuar sendo
            // possível (o aluno nem sempre lembra de fotografar antes de comer).
            $table->text('file_path')->nullable();
            $table->text('descricao')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'data'], 'idx_meal_logs_student_data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_logs');
    }
};
