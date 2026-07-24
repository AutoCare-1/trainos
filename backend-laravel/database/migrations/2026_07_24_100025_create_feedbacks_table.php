<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('training_session_id')->unique()->constrained('training_sessions')->cascadeOnDelete();
            $table->integer('effort_rpe')->nullable();
            $table->integer('satisfaction')->nullable();
            $table->text('discomfort')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
