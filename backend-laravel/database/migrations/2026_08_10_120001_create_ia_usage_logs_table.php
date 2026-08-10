<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_usage_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Nullable de propósito: parte das chamadas de IA nasce no portal do
            // aluno (autenticado por invite_token, sem JWT de profissional). Nesses
            // casos o custo existe e precisa entrar no total, mesmo sem dono direto.
            $table->foreignUuid('professional_id')->nullable()->constrained('professionals')->nullOnDelete();

            // Chave do pipeline (config/ia_pipelines.php): chat, consultor_ia,
            // conteudo, evolucao_fisica, academia, forma, postural, recomendacao.
            $table->string('pipeline');
            $table->string('model');

            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_creation_input_tokens')->default(0);
            $table->unsignedInteger('cache_read_input_tokens')->default(0);
            // Buscas da tool nativa web_search (ConteudoIdeias) — cobradas por
            // busca, não por token, então não dá pra derivar do resto.
            $table->unsignedInteger('web_searches')->default(0);

            // Custo congelado no momento da chamada, em USD (moeda em que a
            // Anthropic cobra). Gravado e nunca recalculado: se a tabela de preços
            // mudar, o histórico continua refletindo o que foi realmente gasto.
            // 8 casas porque uma chamada barata custa fração de centavo.
            $table->decimal('custo_usd', 12, 8)->default(0);

            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at', 'pipeline'], 'idx_ia_usage_created_pipeline');
            $table->index('professional_id', 'idx_ia_usage_professional');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_usage_logs');
    }
};
