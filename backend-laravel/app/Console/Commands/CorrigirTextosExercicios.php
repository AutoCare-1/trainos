<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use Illuminate\Console\Command;

/**
 * Aplica as correções de equipamento/instrução de database/textos_revisao_hugo.php.
 *
 * Existe porque os seeders da biblioteca complementar só inserem nome que ainda
 * não existe: mudar o texto lá não chega na produção. Idempotente: no deploy
 * seguinte relata "já correto".
 */
class CorrigirTextosExercicios extends Command
{
    protected $signature = 'exercicios:corrigir-textos
        {--arquivo= : Caminho de outro mapa (o teste usa; no dia a dia, omita)}';

    protected $description = 'Corrige equipamento e instrução de exercícios a partir do mapa versionado.';

    private const CAMPOS = ['equipment', 'instructions'];

    public function handle(): int
    {
        $mapa = require $this->option('arquivo') ?: database_path('textos_revisao_hugo.php');

        foreach ($mapa as $nome => $campos) {
            $campos = array_intersect_key($campos, array_flip(self::CAMPOS));
            $ex = Exercise::where('name', $nome)->first();

            if (! $ex) {
                $this->warn("não encontrado: {$nome}");

                continue;
            }

            $mudou = array_filter($campos, fn ($valor, $campo) => $ex->{$campo} !== $valor, ARRAY_FILTER_USE_BOTH);

            if ($mudou === []) {
                $this->line("já correto: {$nome}");

                continue;
            }

            $ex->update($mudou);
            $this->info("corrigido: {$nome} (".implode(', ', array_keys($mudou)).')');
        }

        return self::SUCCESS;
    }
}
