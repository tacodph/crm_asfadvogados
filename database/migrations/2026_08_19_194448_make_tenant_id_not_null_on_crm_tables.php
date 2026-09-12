<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables scoped to a tenant that have no unique-index changes at all
     * (`contatos`, `users`, `empresas`, `funis` are handled individually
     * above since each also swaps a unique constraint).
     *
     * @var list<string>
     */
    private array $plainTables = [
        'negociacoes',
        'etapas_funil',
        'historicos_negociacao',
        'consentimentos_contato',
    ];

    /**
     * Catalog tables whose unique `slug` + `nome` become composite per tenant.
     *
     * @var list<string>
     */
    private array $catalogTables = [
        'setores',
        'canais_contato',
        'status_consentimentos',
        'finalidades_consentimento',
        'status_conflitos',
    ];

    /**
     * Run the migrations.
     *
     * Must run after `php artisan tenants:backfill-legacy` has populated
     * every `tenant_id` column, otherwise the NOT NULL constraint fails.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            $table->dropUnique(['email']);
            $table->unique(['tenant_id', 'email']);
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            $table->dropUnique(['cnpj']);
            $table->unique(['tenant_id', 'cnpj']);
        });

        Schema::table('funis', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            $table->dropUnique(['slug']);
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::table('contatos', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            $table->dropUnique(['cpf']);
            $table->unique(['tenant_id', 'cpf']);
        });

        foreach ($this->catalogTables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
                $table->dropUnique(['slug']);
                $table->dropUnique(['nome']);
                $table->unique(['tenant_id', 'slug']);
                $table->unique(['tenant_id', 'nome']);
            });
        }

        foreach ($this->plainTables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'email']);
            $table->unique('email');
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'cnpj']);
            $table->unique('cnpj');
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });

        Schema::table('funis', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->unique('slug');
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });

        Schema::table('contatos', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'cpf']);
            $table->unique('cpf');
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });

        foreach ($this->catalogTables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'slug']);
                $table->dropUnique(['tenant_id', 'nome']);
                $table->unique('slug');
                $table->unique('nome');
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }

        foreach ($this->plainTables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }
    }
};
