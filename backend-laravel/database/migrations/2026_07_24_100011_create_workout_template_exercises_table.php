<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_template_exercises', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('workout_templates')->cascadeOnDelete();
            $table->foreignUuid('exercise_id')->constrained('exercises');
            $table->integer('order_index')->default(0);
            $table->integer('sets');
            $table->string('reps');
            $table->decimal('load_kg', 8, 2)->nullable();
            $table->integer('rest_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->string('structure_type')->default('tradicional');
            $table->string('group_label')->nullable();

            $table->index('template_id', 'idx_workout_template_exercises_template');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_template_exercises');
    }
};
