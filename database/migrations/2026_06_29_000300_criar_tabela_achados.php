<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analise_id')->constrained('analises')->cascadeOnDelete();
            $table->string('codigo');
            $table->string('categoria', 40);
            $table->string('severidade', 20);
            $table->string('confianca', 20)->default('media');
            $table->string('titulo');
            $table->text('descricao');
            $table->text('recomendacao')->nullable();
            $table->text('caminho_arquivo')->nullable();
            $table->unsignedInteger('linha_inicial')->nullable();
            $table->unsignedInteger('linha_final')->nullable();
            $table->json('evidencia')->nullable();
            $table->json('metadados')->nullable();
            $table->string('impressao_digital', 64);
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->unique(['analise_id', 'impressao_digital']);
            $table->index(['analise_id', 'severidade']);
            $table->index(['analise_id', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achados');
    }
};
