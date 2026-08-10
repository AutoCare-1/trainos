<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Divisão do lucro entre os sócios. Histórico, mesmo padrão das outras
        // tabelas de dinheiro do projeto: mudar o percentual de alguém fecha a
        // linha vigente e cria outra, pra que o rateio de um mês passado continue
        // refletindo o acordo que valia naquele mês.
        Schema::create('profit_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome');
            // 0 a 100, com 2 casas — permite divisões como 33,33%.
            $table->decimal('percentual', 5, 2);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->foreignUuid('previous_share_id')->nullable()->constrained('profit_shares')->nullOnDelete();
            $table->timestamps();

            $table->index('ends_on', 'idx_profit_shares_ends_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_shares');
    }
};
