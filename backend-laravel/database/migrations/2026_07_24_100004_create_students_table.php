<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('objective')->nullable();
            $table->string('invite_token')->unique();
            $table->string('status')->default('active');
            $table->boolean('ai_autopilot')->default(true);
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
            $table->json('par_q_answers')->nullable();
            $table->text('health_notes')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->text('photo_url')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('professional_id', 'idx_students_professional');
            $table->index('invite_token', 'idx_students_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
