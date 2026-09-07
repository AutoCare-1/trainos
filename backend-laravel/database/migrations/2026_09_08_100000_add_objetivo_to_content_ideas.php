<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De qual botão a ideia veio: 'engajar' (conteúdo pra quem já segue, o
 * comportamento que a ferramenta sempre teve) ou 'atrair_alunos' (post de
 * captação). Linha só pra rotular/filtrar o histórico — a lógica de geração
 * lê o objetivo do request, não desta coluna.
 *
 * NOT NULL com default: as linhas antigas eram todas do modo antigo, então
 * herdam 'engajar' sem backfill manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->string('objetivo')->default('engajar')->after('batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('content_ideas', function (Blueprint $table) {
            $table->dropColumn('objetivo');
        });
    }
};
