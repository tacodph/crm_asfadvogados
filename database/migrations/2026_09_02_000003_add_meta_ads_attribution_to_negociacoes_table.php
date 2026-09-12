<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atribuição da negociação ao anúncio do Meta Ads. `origem_utm` guarda os
 * parâmetros crus capturados na entrada; `meta_*_id` guardam a resolução feita
 * por App\Actions\Meta\AtribuirNegociacaoAnuncioMeta. Ver arquitetura_ads.md §5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negociacoes', function (Blueprint $table) {
            $table->string('meta_ad_id')->nullable()->after('meta_captado_em');
            $table->string('meta_adset_id')->nullable()->after('meta_ad_id');
            $table->string('meta_campaign_id')->nullable()->after('meta_adset_id');
            $table->string('meta_atribuicao_origem')->nullable()->after('meta_campaign_id');
            $table->timestamp('meta_atribuido_em')->nullable()->after('meta_atribuicao_origem');
            $table->json('origem_utm')->nullable()->after('meta_atribuido_em');

            $table->index('meta_campaign_id');
            $table->index('meta_ad_id');
        });
    }

    public function down(): void
    {
        Schema::table('negociacoes', function (Blueprint $table) {
            $table->dropIndex(['meta_campaign_id']);
            $table->dropIndex(['meta_ad_id']);
            $table->dropColumn([
                'meta_ad_id',
                'meta_adset_id',
                'meta_campaign_id',
                'meta_atribuicao_origem',
                'meta_atribuido_em',
                'origem_utm',
            ]);
        });
    }
};
