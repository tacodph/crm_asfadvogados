<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parâmetros capturados pelo Pixel do navegador quando o lead entra por
     * origem web. São reenviados na CAPI para deduplicação (`meta_event_id`
     * == `eventID` do Pixel) e Advanced Matching (`fbp`/`fbc`/IP/UA).
     */
    public function up(): void
    {
        Schema::table('negociacoes', function (Blueprint $table) {
            $table->string('meta_event_id')->nullable()->after('proxima_tarefa_hora');
            $table->string('meta_fbp')->nullable()->after('meta_event_id');
            $table->string('meta_fbc')->nullable()->after('meta_fbp');
            $table->string('meta_event_source_url')->nullable()->after('meta_fbc');
            $table->string('meta_client_ip', 45)->nullable()->after('meta_event_source_url');
            $table->string('meta_client_user_agent')->nullable()->after('meta_client_ip');
            $table->timestamp('meta_captado_em')->nullable()->after('meta_client_user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('negociacoes', function (Blueprint $table) {
            $table->dropColumn([
                'meta_event_id',
                'meta_fbp',
                'meta_fbc',
                'meta_event_source_url',
                'meta_client_ip',
                'meta_client_user_agent',
                'meta_captado_em',
            ]);
        });
    }
};
