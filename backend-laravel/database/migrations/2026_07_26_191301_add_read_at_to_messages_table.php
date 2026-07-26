<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Antes disso, "mensagem lida" só existia no localStorage do navegador (por
    // aluno, cliente) — sem verdade nenhuma no servidor. Necessário pra
    // mensagem_nao_lida saber, de fato, se ninguém abriu o chat há N horas.
    // null = não lida. sender indica quem mandou; read_at é sempre "quando o outro
    // lado (personal, se sender=aluno; aluno, se sender=personal/ia) abriu o chat".
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }
};
