<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meta_conversao_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nome_campanha');
            $table->string('slug');
            $table->string('pixel_id');
            // Cifrados em repouso pelo cast App\Casts\SegredoMeta (chave dedicada).
            $table->text('access_token');
            $table->text('test_event_code')->nullable();
            // Em claro, apenas para exibição na UI (nunca decifra o token na tela).
            $table->string('token_ultimos4', 8)->nullable();
            $table->timestamp('token_verificado_em')->nullable();
            $table->boolean('token_valido')->nullable();
            $table->string('api_version')->default('v21.0');
            $table->string('action_source')->default('system_generated');
            $table->string('origem_url')->nullable();
            $table->string('finalidade_consentimento_slug')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('ultimo_evento_em')->nullable();
            $table->string('ultimo_status')->nullable();
            $table->foreignId('atualizado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'pixel_id']);
            $table->index(['tenant_id', 'ativo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_conversao_configs');
    }
};
