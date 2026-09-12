<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_contas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            // Só dígitos, sem o prefixo `act_` (o client adiciona).
            $table->string('ad_account_id');
            $table->string('business_id')->nullable();
            $table->string('moeda', 3)->nullable();
            $table->string('fuso_horario')->nullable();
            // Token de usuário de sistema com ads_read — cifrado em repouso pelo
            // cast App\Casts\SegredoMeta (mesma chave da CAPI).
            $table->text('access_token');
            // Em claro, apenas para exibição na UI (nunca decifra o token na tela).
            $table->string('token_ultimos4', 4)->nullable();
            $table->json('token_scopes')->nullable();
            $table->timestamp('token_verificado_em')->nullable();
            $table->boolean('token_valido')->nullable();
            $table->string('conta_status')->nullable();
            $table->foreignId('atualizado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamp('estrutura_sincronizada_em')->nullable();
            $table->timestamp('insights_sincronizados_em')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'ad_account_id']);
            $table->index(['tenant_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_contas');
    }
};
