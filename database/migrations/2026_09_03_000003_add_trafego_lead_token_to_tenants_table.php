<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Segredo de ingestão de leads pelo site/landing (endpoint público
     * POST /api/trafego/leads). Guardado só como hash SHA-256 — o valor cru é
     * exibido uma vez na geração (`php artisan meta:trafego-token`).
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('trafego_lead_token_hash', 64)->nullable()->unique();
            $table->string('trafego_lead_token_ultimos4', 4)->nullable();
            $table->timestamp('trafego_lead_token_gerado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'trafego_lead_token_hash',
                'trafego_lead_token_ultimos4',
                'trafego_lead_token_gerado_em',
            ]);
        });
    }
};
