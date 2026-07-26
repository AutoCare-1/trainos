<?php

namespace App\Support;

/**
 * Helper pra manipular valores monetários sem acumular erro de ponto flutuante.
 *
 * Todo cálculo interno (somas de receita/despesa, comparação "o valor mudou?")
 * deve ser feito em centavos (inteiro) usando toCents()/fromCents() — nunca somar
 * ou comparar decimal(10,2) direto como float em PHP. A única multiplicação por
 * 100 que existe é feita com round() logo antes do cast pra int, exatamente pra
 * evitar o erro clássico de ponto flutuante (ex: 19.99 * 100 vira 1998.99999...
 * sem o round). Daí em diante tudo é aritmética de inteiro.
 */
class Money
{
    /** Aceita string decimal ("150.00", vindo do cast do Eloquent) ou int/float (vindo de JSON). */
    public static function toCents(string|int|float $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    /** Única divisão, no fim do cálculo — não acumula erro porque não repete. */
    public static function fromCents(int $cents): float
    {
        return $cents / 100;
    }
}
