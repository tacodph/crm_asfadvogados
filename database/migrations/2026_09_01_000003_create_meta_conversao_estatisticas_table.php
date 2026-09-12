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
        Schema::create('meta_conversao_estatisticas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meta_conversao_config_id')->constrained()->cascadeOnDelete();
            $table->date('referencia');
            $table->timestamp('pixel_last_fired_at')->nullable();
            $table->unsignedInteger('eventos_servidor')->nullable();
            $table->unsignedInteger('eventos_navegador')->nullable();
            $table->unsignedInteger('eventos_deduplicados')->nullable();
            $table->decimal('match_rate', 5, 2)->nullable();
            // Resposta crua da Graph API, para auditoria.
            $table->json('payload_bruto');
            $table->timestamps();

            $table->unique(['meta_conversao_config_id', 'referencia']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_conversao_estatisticas');
    }
};
