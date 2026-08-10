<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Custos do PRODUTO (hospedagem, banco, domínio, ferramentas) — não
        // confundir com professional_expenses, que são as despesas do negócio de
        // um personal. Mesmo padrão de histórico daquela tabela: valor nunca é
        // editado in-place, fecha a linha vigente e cria outra.
        Schema::create('platform_costs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->boolean('is_recurring')->default(true);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->foreignUuid('previous_cost_id')->nullable()->constrained('platform_costs')->nullOnDelete();
            $table->timestamps();

            $table->index('is_recurring', 'idx_platform_costs_recurring');
            $table->index('ends_on', 'idx_platform_costs_ends_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_costs');
    }
};
