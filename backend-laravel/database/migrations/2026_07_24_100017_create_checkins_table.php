<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('checkin_date')->useCurrent();
            $table->text('file_path');
            $table->timestamp('created_at')->useCurrent();
            $table->text('comment')->nullable();

            $table->unique(['student_id', 'checkin_date']);
            $table->index(['student_id', 'checkin_date'], 'idx_checkins_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkins');
    }
};
