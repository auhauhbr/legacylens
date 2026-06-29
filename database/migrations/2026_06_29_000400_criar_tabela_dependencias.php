<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dependencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analise_id')->constrained('analises')->cascadeOnDelete();
            $table->string('nome_pacote');
            $table->string('versao_atual')->nullable();
            $table->string('versao_mais_recente')->nullable();
            $table->string('restricao')->nullable();
            $table->string('escopo', 30)->nullable();
            $table->boolean('direta')->default(true);
            $table->boolean('desenvolvimento')->default(false);
            $table->boolean('abandonada')->default(false);
            $table->boolean('possui_alerta_seguranca')->default(false);
            $table->json('metadados')->nullable();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->unique(['analise_id', 'nome_pacote']);
            $table->index(['analise_id', 'escopo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependencias');
    }
};
