<?php

namespace App\Notifications\Rules;

use App\Models\Professional;
use App\Models\Student;

/**
 * Um disparo candidato encontrado por uma Rule: quem recebe, quem é o personal
 * dono da preferência (mesmo quando o destinatário é o próprio aluno — quem liga
 * /desliga o tipo é sempre o personal), a chave de deduplicação já pronta (a Rule
 * decide sozinha se ela é por dia, por marco único, etc.) e o conteúdo da notificação.
 */
readonly class NotificacaoCandidato
{
    public function __construct(
        public Student|Professional $recipient,
        public string $professionalId,
        public ?string $studentId,
        public string $dedupKey,
        public ?string $contexto,
        public string $titulo,
        public string $corpo,
        public ?string $url,
    ) {
    }
}
