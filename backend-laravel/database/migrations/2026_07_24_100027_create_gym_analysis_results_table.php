<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_analysis_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('gym_media_submissions')->cascadeOnDelete();
            $table->json('machines_json');
            // Postgres text[] no original — armazenado como json por portabilidade (MySQL não tem tipo array).
            $table->json('zones_identified')->default(new \Illuminate\Database\Query\Expression("('[]')"));
            $table->integer('total_unique_machines')->default(0);
            $table->text('coverage_estimate')->nullable();
            $table->json('gaps')->default(new \Illuminate\Database\Query\Expression("('[]')"));
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('submission_id', 'idx_gym_analysis_submission');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_analysis_results');
    }
};
