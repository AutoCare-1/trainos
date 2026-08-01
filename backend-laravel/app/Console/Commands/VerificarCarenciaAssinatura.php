<?php

namespace App\Console\Commands;

use App\Models\ProfessionalSubscription;
use Illuminate\Console\Command;

/**
 * Roda 1x/dia (routes/console.php): transiciona assinaturas atrasada ->
 * bloqueada quando a carência (config('planos_assinatura.dias_carencia'))
 * já passou. Teste grátis expirado não precisa de transição nenhuma aqui —
 * é calculado on-the-fly a partir de Professional.created_at (ver
 * App\Support\Assinatura::status).
 */
class VerificarCarenciaAssinatura extends Command
{
    protected $signature = 'assinatura:verificar-carencia';

    protected $description = 'Bloqueia assinaturas atrasadas cuja carência já esgotou';

    public function handle(): int
    {
        $diasCarencia = (int) config('planos_assinatura.dias_carencia');
        $limite = now()->subDays($diasCarencia)->toDateString();

        $bloqueadas = ProfessionalSubscription::where('status', ProfessionalSubscription::STATUS_ATRASADA)
            ->whereNotNull('atraso_desde')
            ->where('atraso_desde', '<=', $limite)
            ->update(['status' => ProfessionalSubscription::STATUS_BLOQUEADA]);

        $this->info("{$bloqueadas} assinatura(s) bloqueada(s) por carência esgotada.");

        return self::SUCCESS;
    }
}
