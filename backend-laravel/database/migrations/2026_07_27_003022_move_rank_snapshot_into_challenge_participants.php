<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Item 12 de uma revisão externa: challenge_rank_snapshots era uma tabela
    // nova só pra guardar "qual foi a última posição notificada" — já existe
    // challenge_participants (pivot aluno-desafio), então essa informação cabe
    // ali como uma coluna a mais, sem justificar uma tabela dedicada.
    public function up(): void
    {
        Schema::table('challenge_participants', function (Blueprint $table) {
            $table->unsignedInteger('ultima_posicao_notificada')->nullable()->after('student_id');
        });

        Schema::dropIfExists('challenge_rank_snapshots');
    }

    public function down(): void
    {
        Schema::create('challenge_rank_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedInteger('posicao');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['challenge_id', 'student_id'], 'idx_rank_snapshot_unique');
        });

        Schema::table('challenge_participants', function (Blueprint $table) {
            $table->dropColumn('ultima_posicao_notificada');
        });
    }
};
