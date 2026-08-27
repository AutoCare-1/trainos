<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca d'água de revogação de sessão: todo JWT emitido ANTES deste instante
 * para de valer pro personal.
 *
 * Sem isso não havia como invalidar sessão nenhuma — com JWT_TTL de 7 dias e
 * blacklist desligada, "Sair" era só apagar o localStorage do navegador e um
 * token vazado valia a semana inteira, mesmo trocando a senha.
 *
 * Nullable de propósito: null = nunca revogou nada, que é o estado de todo
 * personal já cadastrado (nenhuma sessão existente é derrubada pela migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->timestamp('tokens_valid_after')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->dropColumn('tokens_valid_after');
        });
    }
};
