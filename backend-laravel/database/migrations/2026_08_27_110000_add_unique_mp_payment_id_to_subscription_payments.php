<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice único em mp_payment_id: é a trava de idempotência do webhook do
 * Mercado Pago, que reenvia o mesmo evento por conta própria (retry normal
 * do protocolo deles, não erro). Sem isso, cada reentrega de um pagamento
 * aprovado gravava outra linha aqui E empurrava proxima_cobranca_em mais um
 * mês — duas reentregas viravam dois meses de assinatura de graça.
 *
 * A checagem em AssinaturaWebhookController::tratarPagamento é a primeira
 * linha de defesa; este índice é a segunda, pra corrida entre duas entregas
 * simultâneas que passem pela checagem ao mesmo tempo.
 *
 * Continua nullable (pagamento local sem id do MP é possível), e tanto MySQL
 * quanto SQLite permitem vários NULL num índice único — só os valores
 * preenchidos são comparados entre si.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_subscription_payments', function (Blueprint $table) {
            $table->unique('mp_payment_id', 'uq_subscription_payments_mp_payment');
        });
    }

    public function down(): void
    {
        Schema::table('professional_subscription_payments', function (Blueprint $table) {
            $table->dropUnique('uq_subscription_payments_mp_payment');
        });
    }
};
