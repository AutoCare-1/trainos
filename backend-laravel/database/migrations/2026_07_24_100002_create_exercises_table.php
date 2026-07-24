<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('muscle_group');
            $table->string('equipment')->nullable();
            $table->text('instructions')->nullable();
            $table->text('video_url')->nullable();
            $table->text('image_url')->nullable();
            $table->text('image_credit')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('name', 'idx_exercises_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
