<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estrutura do Meta Ads (campanha → conjunto → anúncio), snapshot diário de
 * insights e auditoria de sincronização. Ver documents/api_meta/arquitetura_ads.md §3.
 * Todo valor monetário é gravado em centavos (nunca float).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_campanhas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meta_ads_conta_id')->constrained()->cascadeOnDelete();
            $table->string('meta_campaign_id');
            $table->string('nome');
            $table->string('objetivo')->default('OUTRO');
            $table->string('status')->default('PAUSED');
            $table->string('effective_status')->nullable();
            $table->unsignedBigInteger('orcamento_diario_centavos')->nullable();
            $table->unsignedBigInteger('orcamento_total_centavos')->nullable();
            $table->timestamp('inicio_em')->nullable();
            $table->timestamp('fim_em')->nullable();
            $table->json('bruto');
            $table->timestamp('sincronizado_em');
            $table->timestamp('arquivado_em')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'meta_campaign_id']);
            $table->index(['meta_ads_conta_id', 'arquivado_em']);
        });

        Schema::create('meta_ads_conjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meta_ads_conta_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meta_ads_campanha_id')->constrained()->cascadeOnDelete();
            $table->string('meta_adset_id');
            $table->string('nome');
            $table->string('optimization_goal')->nullable();
            $table->string('status')->default('PAUSED');
            $table->string('effective_status')->nullable();
            $table->unsignedBigInteger('orcamento_diario_centavos')->nullable();
            $table->unsignedBigInteger('orcamento_total_centavos')->nullable();
            $table->json('bruto');
            $table->timestamp('sincronizado_em');
            $table->timestamp('arquivado_em')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'meta_adset_id']);
            $table->index(['meta_ads_conta_id', 'arquivado_em']);
        });

        Schema::create('meta_ads_anuncios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meta_ads_conta_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meta_ads_campanha_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meta_ads_conjunto_id')->constrained()->cascadeOnDelete();
            $table->string('meta_ad_id');
            $table->string('nome');
            $table->string('status')->default('PAUSED');
            $table->string('effective_status')->nullable();
            $table->json('criativo_resumo')->nullable();
            $table->json('bruto');
            $table->timestamp('sincronizado_em');
            $table->timestamp('arquivado_em')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'meta_ad_id']);
            $table->index(['meta_ads_conta_id', 'arquivado_em']);
        });

        Schema::create('meta_ads_insights_diarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meta_ads_conta_id')->constrained()->cascadeOnDelete();
            $table->string('nivel')->default('anuncio');
            $table->string('objeto_id');
            $table->date('referencia');
            $table->unsignedBigInteger('investimento_centavos')->default(0);
            $table->unsignedBigInteger('impressoes')->default(0);
            $table->unsignedBigInteger('cliques')->default(0);
            $table->unsignedBigInteger('cliques_link')->default(0);
            $table->unsignedBigInteger('alcance')->default(0);
            $table->unsignedBigInteger('cpc_centavos')->nullable();
            $table->unsignedBigInteger('cpm_centavos')->nullable();
            $table->unsignedBigInteger('custo_por_resultado_centavos')->nullable();
            $table->decimal('ctr', 8, 4)->nullable();
            $table->decimal('frequencia', 8, 2)->nullable();
            $table->unsignedInteger('resultados')->default(0);
            $table->json('acoes')->nullable();
            $table->json('bruto');
            $table->timestamps();

            $table->unique(['objeto_id', 'nivel', 'referencia'], 'maid_objeto_nivel_ref_unique');
            $table->index(['tenant_id', 'referencia']);
            $table->index(
                ['meta_ads_conta_id', 'nivel', 'referencia'],
                'maid_conta_nivel_ref_index',
            );
        });

        Schema::create('meta_ads_sync_execucoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meta_ads_conta_id')->constrained()->cascadeOnDelete();
            $table->string('tipo');
            $table->string('status')->default('ok');
            $table->date('janela_inicio')->nullable();
            $table->date('janela_fim')->nullable();
            $table->unsignedInteger('objetos_afetados')->default(0);
            $table->unsignedInteger('duracao_ms')->nullable();
            $table->text('erro')->nullable();
            $table->timestamp('iniciado_em');
            $table->timestamp('concluido_em')->nullable();
            $table->timestamps();

            $table->index(['meta_ads_conta_id', 'tipo', 'iniciado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_sync_execucoes');
        Schema::dropIfExists('meta_ads_insights_diarios');
        Schema::dropIfExists('meta_ads_anuncios');
        Schema::dropIfExists('meta_ads_conjuntos');
        Schema::dropIfExists('meta_ads_campanhas');
    }
};
