<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_photos', function (Blueprint $table) {
            // $table->primary('id') explícito (em vez de ->primary() encadeado) para
            // garantir que a PK seja compilada ANTES da FK auto-referenciada abaixo —
            // ->primary() encadeado só vira comando implícito no fim do bloco, depois
            // do ->constrained(), e o Postgres rejeita a FK se a PK ainda não existir.
            $table->uuid('id');
            $table->primary('id');
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->text('file_path');
            $table->timestamp('taken_at')->useCurrent();
            $table->text('ai_feedback')->nullable();
            $table->foreignUuid('compared_to_photo_id')->nullable()->constrained('body_photos')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'taken_at'], 'idx_body_photos_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_photos');
    }
};
