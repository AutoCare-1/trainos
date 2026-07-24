<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('training_session_id')->constrained('training_sessions')->cascadeOnDelete();
            $table->foreignUuid('workout_exercise_id')->constrained('workout_exercises');
            $table->integer('set_number');
            $table->integer('reps_done')->nullable();
            $table->decimal('load_kg_done', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('training_session_id', 'idx_session_entries_session');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_entries');
    }
};
