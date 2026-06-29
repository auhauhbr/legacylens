<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relatorios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analise_id')->constrained('analises')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('titulo');
            $table->longText('conteudo_markdown');
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->unique(['analise_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relatorios');
    }
};
