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
        Schema::create('canais_contato', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nome')->unique();
            $table->string('cor', 9);
            $table->unsignedSmallInteger('ordem')->default(0)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('canais_contato');
    }
};
