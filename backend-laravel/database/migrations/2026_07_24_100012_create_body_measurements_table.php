<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_measurements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('recorded_at')->useCurrent();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->decimal('waist_cm', 8, 2)->nullable();
            $table->decimal('hip_cm', 8, 2)->nullable();
            $table->decimal('body_fat_pct', 8, 2)->nullable();

            $table->index(['student_id', 'recorded_at'], 'idx_body_measurements_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_measurements');
    }
};
