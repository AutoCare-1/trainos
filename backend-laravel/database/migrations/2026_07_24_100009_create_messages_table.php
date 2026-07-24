<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->string('sender');
            $table->text('content');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'created_at'], 'idx_messages_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
