<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_exercises', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workout_id')->constrained('workouts')->cascadeOnDelete();
            $table->foreignUuid('exercise_id')->constrained('exercises');
            $table->integer('order_index')->default(0);
            $table->integer('sets');
            $table->string('reps');
            $table->decimal('load_kg', 8, 2)->nullable();
            $table->integer('rest_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->string('structure_type')->default('tradicional');
            $table->string('group_label')->nullable();

            $table->index('workout_id', 'idx_workout_exercises_workout');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_exercises');
    }
};
