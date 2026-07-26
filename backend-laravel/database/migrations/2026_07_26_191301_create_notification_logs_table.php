<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Log de notificações enviadas — garante idempotência. dedup_key é montado por
    // cada Rule (ex: "sem_treinar_hoje:{student_id}:2026-07-26" ou
    // "medalha_conquistada:{student_id}:sequencia_7", sem data pra tipos que só
    // acontecem uma vez na vida) e tem UNIQUE de verdade: o comando notifications:process
    // tenta criar a linha ANTES de despachar o job, e se colidir (já existe) pula —
    // student_id/professional_id/contexto ficam só pra consulta/debug, quem garante
    // "não duplica" é o dedup_key.
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tipo_chave');
            $table->foreign('tipo_chave')->references('chave')->on('tipos_notificacao')->cascadeOnDelete();
            $table->foreignUuid('student_id')->nullable()->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('professional_id')->nullable()->constrained('professionals')->cascadeOnDelete();
            $table->string('contexto')->nullable();
            $table->string('dedup_key')->unique();
            $table->timestamp('enviado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
