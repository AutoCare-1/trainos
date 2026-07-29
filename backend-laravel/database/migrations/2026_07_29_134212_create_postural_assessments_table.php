<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avaliação postural — envio opcional de 3 fotos (frente/lado/costas) num único
     * registro, separado da "Evolução física" (1 foto por vez): aqui a comparação é
     * ângulo-com-ângulo (frente da vez passada vs frente de agora, etc.), então precisa
     * de 3 caminhos de arquivo por registro em vez de 1. Mesmo padrão de storage privado
     * e de "comparado com o anterior" que body_photos já usa.
     */
    public function up(): void
    {
        Schema::create('postural_assessments', function (Blueprint $table) {
            $table->uuid('id');
            $table->primary('id');
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->text('front_photo_path');
            $table->text('side_photo_path');
            $table->text('back_photo_path');
            $table->timestamp('taken_at')->useCurrent();
            $table->text('ai_feedback')->nullable();
            $table->foreignUuid('compared_to_assessment_id')->nullable()->constrained('postural_assessments')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'taken_at'], 'idx_postural_assessments_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postural_assessments');
    }
};
