<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('provider');
            $table->string('external_id');
            $table->string('activity_type');
            $table->string('name')->nullable();
            $table->timestamp('started_at');
            $table->integer('duration_seconds')->nullable();
            $table->decimal('distance_meters', 10, 2)->nullable();
            $table->decimal('calories', 8, 2)->nullable();
            $table->decimal('avg_heart_rate', 8, 2)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider', 'external_id']);
            $table->index(['student_id', 'started_at'], 'idx_external_activities_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_activities');
    }
};
