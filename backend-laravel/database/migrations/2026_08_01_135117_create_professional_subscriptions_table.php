<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assinatura do PERSONAL com o TrainOS (cobrança recorrente via Mercado
     * Pago, em faixas por número de alunos — ver config/planos_assinatura.php).
     * Não confundir com student_billing_plans, que é a cobrança do aluno pelo
     * próprio personal, dentro do negócio dele — sistema totalmente separado.
     * Um personal tem no máximo uma assinatura por vez (unique em
     * professional_id): trocar de plano fecha/reaproveita a mesma linha em vez
     * de criar histórico de assinaturas concorrentes.
     */
    public function up(): void
    {
        Schema::create('professional_subscriptions', function (Blueprint $table) {
            $table->uuid('id');
            $table->primary('id');
            $table->foreignUuid('professional_id')->unique()->constrained('professionals')->cascadeOnDelete();
            $table->string('plano_chave');
            $table->string('status'); // pendente|ativa|atrasada|bloqueada|cancelada
            $table->string('mp_preapproval_id')->nullable()->unique();
            $table->date('proxima_cobranca_em')->nullable();
            $table->date('atraso_desde')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_subscriptions');
    }
};
