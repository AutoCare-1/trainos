<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            // Acesso ao CRM interno (/admin) — dono do produto, não é papel de
            // personal. Deliberadamente uma coluna e não uma tabela de papéis:
            // só existem dois estados (é dono ou não é) e o middleware AdminOnly
            // é o único lugar que lê isso.
            $table->boolean('is_admin')->default(false)->after('password_hash');
        });
    }

    public function down(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
