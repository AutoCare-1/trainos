<?php

namespace App\Notifications\Rules;

interface NotificacaoRule
{
    /** Precisa bater com uma chave seedada em tipos_notificacao (NotificationTypesSeeder). */
    public function chave(): string;

    /** @return NotificacaoCandidato[] */
    public function avaliar(): array;
}
