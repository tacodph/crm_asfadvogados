<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contatos', function (Blueprint $table) {
            $table->string('cidade')->nullable()->after('cpf');
            $table->foreignId('uf_id')->nullable()->after('cidade')->constrained('ufs');
            $table->unsignedInteger('municipio_id')->nullable()->after('uf_id')->index();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM pg_constraint
                        WHERE conname = \'contatos_municipio_id_foreign\'
                    ) THEN
                        ALTER TABLE contatos
                        ADD CONSTRAINT contatos_municipio_id_foreign
                        FOREIGN KEY (municipio_id)
                        REFERENCES base_dados_ibge.tab_municipios (id);
                    END IF;
                END $$;
            ');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE contatos DROP CONSTRAINT IF EXISTS contatos_municipio_id_foreign');
        }

        Schema::table('contatos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uf_id');
            $table->dropColumn(['cidade', 'municipio_id']);
        });
    }
};
