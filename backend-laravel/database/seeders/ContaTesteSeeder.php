<?php

namespace Database\Seeders;

use App\Models\Professional;
use App\Models\ProfessionalSubscription;
use App\Support\Assinatura;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Conta de teste do Filipe pra usar o app em produção sem pagar plano.
 *
 * Assinatura de "cortesia": status ativa, plano_chave = 'cortesia', teto de
 * alunos vindo de config('planos_assinatura.cortesia_limite_alunos') (8 por
 * padrão). Não passa por Mercado Pago — Assinatura::status só olha o status da
 * subscription, não os pagamentos.
 *
 * NÃO está no DatabaseSeeder de propósito: roda só quando alguém chama
 * explicitamente
 *
 *     php artisan db:seed --class="Database\\Seeders\\ContaTesteSeeder" --force
 *
 * É idempotente (updateOrCreate pelo e-mail) — rodar de novo reaplica a senha
 * e reativa a cortesia, não duplica.
 */
class ContaTesteSeeder extends Seeder
{
    private const EMAIL = 'personal.teste@clubemais.app';

    private const SENHA = 'ClubeMaisTeste2026';

    public function run(): void
    {
        $professional = Professional::updateOrCreate(
            ['email' => self::EMAIL],
            ['name' => 'Conta de Teste', 'password_hash' => Hash::make(self::SENHA)],
        );

        ProfessionalSubscription::updateOrCreate(
            ['professional_id' => $professional->id],
            [
                'plano_chave' => Assinatura::CHAVE_CORTESIA,
                'status' => ProfessionalSubscription::STATUS_ATIVA,
                'mp_preapproval_id' => null,
                'proxima_cobranca_em' => null,
                'atraso_desde' => null,
            ],
        );

        $this->command?->info('Conta de teste pronta: '.self::EMAIL.' / '.self::SENHA
            .' (cortesia, limite '.config('planos_assinatura.cortesia_limite_alunos').' alunos)');
    }
}
