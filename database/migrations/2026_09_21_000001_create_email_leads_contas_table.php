<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_leads_contas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('host')->default('imap.gmail.com');
            $table->unsignedSmallInteger('port')->default(993);
            $table->string('encryption')->default('ssl');
            $table->string('username');
            // Cifrada em repouso pelo cast App\Casts\SegredoMeta (mesma chave da CAPI).
            $table->text('password');
            $table->string('pasta')->default('INBOX');
            $table->foreignId('funil_id')->constrained('funis')->restrictOnDelete();
            $table->string('finalidade_consentimento_slug');
            $table->boolean('ativo')->default(true);
            $table->timestamp('ultima_captura_em')->nullable();
            $table->string('ultimo_status')->nullable();
            $table->text('ultimo_erro')->nullable();
            $table->foreignId('atualizado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'username']);
            $table->index(['tenant_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_leads_contas');
    }
};
