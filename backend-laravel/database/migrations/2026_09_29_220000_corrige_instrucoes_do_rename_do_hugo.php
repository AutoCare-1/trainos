<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige as instruções que ficaram descrevendo o exercício ANTIGO.
 *
 * O rename da revisão do Hugo trocou o nome de 60 exercícios e não tocou na
 * coluna `instructions`. Onde o rename mudou o exercício de verdade — e não só
 * o nome — sobrou uma instrução de outro movimento. Isso aparece na tela: é o
 * texto que o personal e o aluno leem ao lado do vídeo.
 *
 * Por que migration e não seeder: o ExercicioBibliotecaAmpliadaSeeder usa
 * Exercise::insert() e só grava nome INÉDITO — ele nunca atualiza quem já
 * existe. Corrigir o texto lá arruma só instalação nova; em produção não muda
 * nada. Foi exatamente o que aconteceu quando tentei.
 *
 * O UPDATE é condicionado ao texto antigo. Se alguém já tiver corrigido à mão,
 * a migration não sobrescreve — e rodar de novo não faz nada.
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string}> nome => [instrução antiga, nova] */
    private const CORRECOES = [
        'Crucifixo' => [
            'Costas apoiadas, empurre os pegadores à frente até quase estender os cotovelos.',
            'Costas apoiadas, abra os braços para os lados e feche em arco até a frente do peito.',
        ],
        'Crucifixo unilateral' => [
            'Empurre um braço de cada vez, evitando girar o tronco para compensar.',
            'Um braço de cada vez: abra para o lado e feche em arco até a frente do peito, sem girar o tronco.',
        ],
        'Crucifixo inverso com halteres' => [
            'Tronco inclinado, estenda os dois braços para trás simultaneamente.',
            'Tronco inclinado, abra os braços em arco para os lados até a altura dos ombros, cotovelos levemente flexionados.',
        ],
        'Remada com argola' => [
            'Puxe a corda até o abdômen separando as pontas ao final.',
            'Sentado na remada baixa, segure a argola com as duas mãos e puxe até encostar no abdômen, cotovelos para trás.',
        ],
    ];

    public function up(): void
    {
        foreach (self::CORRECOES as $nome => [$antiga, $nova]) {
            DB::table('exercises')->where('name', $nome)->where('instructions', $antiga)
                ->update(['instructions' => $nova]);
        }
    }

    public function down(): void
    {
        foreach (self::CORRECOES as $nome => [$antiga, $nova]) {
            DB::table('exercises')->where('name', $nome)->where('instructions', $nova)
                ->update(['instructions' => $antiga]);
        }
    }
};
