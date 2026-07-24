<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultor_ia_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->string('role');
            $table->text('content');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['professional_id', 'created_at'], 'idx_consultor_ia_messages_professional');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultor_ia_messages');
    }
};
