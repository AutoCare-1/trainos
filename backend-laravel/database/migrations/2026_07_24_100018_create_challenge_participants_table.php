<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();

            $table->unique(['challenge_id', 'student_id']);
            $table->index('challenge_id', 'idx_challenge_participants_challenge');
            $table->index('student_id', 'idx_challenge_participants_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_participants');
    }
};
