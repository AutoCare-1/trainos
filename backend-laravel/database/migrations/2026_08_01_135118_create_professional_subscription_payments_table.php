<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Histórico de cobranças da assinatura (aba "Faturas" da tela Meu Plano).
     * Uma linha por tentativa de cobrança recorrente que o Mercado Pago avisa
     * via webhook, aprovada ou recusada — não é o mesmo que professional_expenses
     * (gastos do próprio negócio do personal, outro assunto).
     */
    public function up(): void
    {
        Schema::create('professional_subscription_payments', function (Blueprint $table) {
            $table->uuid('id');
            $table->primary('id');
            $table->foreignUuid('subscription_id')->constrained('professional_subscriptions')->cascadeOnDelete();
            $table->string('mp_payment_id')->nullable();
            $table->decimal('valor', 10, 2);
            $table->string('status'); // aprovado|recusado
            $table->date('pago_em')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('subscription_id', 'idx_subscription_payments_subscription');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_subscription_payments');
    }
};
