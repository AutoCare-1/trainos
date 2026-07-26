<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Catálogo de tipos de notificação, seedado em código (NotificationTypesSeeder) —
    // guardado como dado (não como enum/constante) pra permitir listar tudo numa tela
    // de configuração sem precisar mexer em código pra cada toggle novo.
    public function up(): void
    {
        Schema::create('tipos_notificacao', function (Blueprint $table) {
            // chave é a PK de propósito: é como o resto do código (rule classes, log,
            // preferências) referencia o tipo — evita join só pra saber qual é qual.
            $table->string('chave')->primary();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('categoria');
            $table->string('publico');
            $table->boolean('ativo_por_padrao')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_notificacao');
    }
};
