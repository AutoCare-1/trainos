<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ID gerado no cliente (UUID) pra cada série registrada, usado pela fila
     * offline do portal do aluno: sem rede, a série entra numa fila local e é
     * despachada quando a conexão volta — e um mesmo despacho pode chegar duas
     * vezes (timeout onde a requisição na verdade completou, ou o aluno reabre
     * o app antes do primeiro envio terminar).
     *
     * Nullable de propósito: registro feito online continua mandando sem esse
     * campo, e a constraint única em (sessão, exercício, série) já existente
     * segue protegendo esse caminho. O índice único aqui é o que garante a
     * idempotência do caminho offline mesmo sob duas requisições simultâneas.
     */
    public function up(): void
    {
        Schema::table('session_entries', function (Blueprint $table) {
            $table->uuid('client_entry_id')->nullable()->after('id');
            $table->unique('client_entry_id', 'uq_session_entries_client_entry');
        });
    }

    public function down(): void
    {
        Schema::table('session_entries', function (Blueprint $table) {
            $table->dropUnique('uq_session_entries_client_entry');
            $table->dropColumn('client_entry_id');
        });
    }
};
