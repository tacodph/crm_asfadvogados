<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables scoped to a tenant. `ufs` and `tipos_pessoa` stay global on purpose.
     *
     * @var list<string>
     */
    private array $tables = [
        'users',
        'empresas',
        'contatos',
        'negociacoes',
        'funis',
        'etapas_funil',
        'historicos_negociacao',
        'consentimentos_contato',
        'setores',
        'canais_contato',
        'status_consentimentos',
        'finalidades_consentimento',
        'status_conflitos',
    ];

    /**
     * Run the migrations.
     *
     * The column starts nullable so `migrate` can run before the legacy data
     * is backfilled (`php artisan tenants:backfill-legacy`). A follow-up
     * migration flips it to NOT NULL and swaps the affected unique indexes
     * for tenant-scoped composites.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->index()
                    ->after('id')
                    ->constrained('tenants')
                    ->cascadeOnDelete();
            });
        }

        Schema::table('empresas', function (Blueprint $table) {
            $table->index(['tenant_id', 'nome']);
            $table->index(['tenant_id', 'cnpj']);
        });

        Schema::table('contatos', function (Blueprint $table) {
            $table->index(['tenant_id', 'nome']);
            $table->index(['tenant_id', 'cpf']);
        });

        Schema::table('negociacoes', function (Blueprint $table) {
            $table->index(['tenant_id', 'funil_id']);
            $table->index(['tenant_id', 'etapa_funil_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['tenant_id', 'email']);
            $table->string('role')->default('member')->index()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'nome']);
            $table->dropIndex(['tenant_id', 'cnpj']);
        });

        Schema::table('contatos', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'nome']);
            $table->dropIndex(['tenant_id', 'cpf']);
        });

        Schema::table('negociacoes', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'funil_id']);
            $table->dropIndex(['tenant_id', 'etapa_funil_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'email']);
        });

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};
