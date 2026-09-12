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
        Schema::create('meta_conversao_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Nullable: evento "descartado" por campanha indefinida ainda tem valor de auditoria.
            $table->foreignId('meta_conversao_config_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('negociacao_id')->nullable()->constrained('negociacoes')->nullOnDelete();
            $table->foreignId('contato_id')->nullable()->constrained('contatos')->nullOnDelete();
            $table->string('event_name');
            $table->string('event_id');
            $table->timestamp('event_time');
            $table->string('action_source');
            $table->string('status')->default('pendente');
            $table->string('motivo_descarte')->nullable();
            $table->unsignedTinyInteger('tentativas')->default(0);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('events_received')->nullable();
            $table->string('fbtrace_id')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            // Payload já com PII em SHA-256 — nunca dado cru.
            $table->json('request_payload');
            $table->json('response_body')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->boolean('is_teste')->default(false);
            $table->timestamps();

            $table->unique(['meta_conversao_config_id', 'event_id']);
            $table->index(['tenant_id', 'event_name', 'created_at']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_conversao_eventos');
    }
};
