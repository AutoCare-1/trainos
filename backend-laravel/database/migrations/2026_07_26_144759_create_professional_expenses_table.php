<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->boolean('is_recurring')->default(false);
            $table->date('starts_on');
            // Recorrente: null = ainda ativa (repete todo mês). Avulsa: só conta no
            // mês de starts_on, ends_on não é usado pra avulsa.
            $table->date('ends_on')->nullable();
            // Auto-referência: quando o valor de uma despesa recorrente muda, fecha
            // esta linha (ends_on = hoje) e cria uma nova apontando previous_expense_id
            // pra esta. Sem isso não dá pra saber, na agregação do mês de transição,
            // qual linha "substitui" qual — despesas não têm uma chave natural como
            // student_id pra resolver esse empate.
            $table->foreignUuid('previous_expense_id')->nullable()->constrained('professional_expenses')->nullOnDelete();
            $table->timestamps();

            $table->index(['professional_id', 'is_recurring'], 'idx_expenses_professional_recurring');
            $table->index('ends_on', 'idx_expenses_ends_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_expenses');
    }
};
