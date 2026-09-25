<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_leads_processados', function (Blueprint $table): void {
            $table->id();
            $table->string('message_id')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_lead_conta_id')->nullable()->constrained('email_leads_contas')->nullOnDelete();
            $table->foreignId('contato_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('negociacao_id')->nullable()->constrained('negociacoes')->nullOnDelete();
            $table->string('status')->default('pendente'); // pendente|processado|erro
            $table->text('erro')->nullable();
            $table->text('payload_html')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_leads_processados');
    }
};
