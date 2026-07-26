<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_billing_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            // Redundante com student_id de propósito — evita join em toda agregação de receita.
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            // String em vez de enum do MySQL — lista de tipos válidos vive em
            // StudentBillingPlan::TIPOS_VALIDOS, extensível sem migration nova.
            $table->string('billing_type');
            $table->decimal('monthly_value', 10, 2);
            $table->date('starts_on');
            // null = plano vigente. Editar valor/tipo nunca faz UPDATE aqui — fecha
            // este registro (ends_on = hoje) e cria um novo, preservando o histórico
            // de receita dos meses anteriores.
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->index(['professional_id', 'student_id'], 'idx_billing_plans_professional_student');
            $table->index('ends_on', 'idx_billing_plans_ends_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_billing_plans');
    }
};
