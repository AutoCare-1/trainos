<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Leaderboard do desafio é sempre calculado na hora (DesafioController::show),
    // sem posição persistida — esse snapshot existe só pra MudancaRankingDesafioRule
    // saber "qual era a posição da última vez que eu olhei" e comparar.
    public function up(): void
    {
        Schema::create('challenge_rank_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedInteger('posicao');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['challenge_id', 'student_id'], 'idx_rank_snapshot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_rank_snapshots');
    }
};
