<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metricas_arquivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analise_id')->constrained('analises')->cascadeOnDelete();
            $table->text('caminho_arquivo');
            $table->string('tipo_arquivo', 50)->nullable();
            $table->unsignedInteger('total_linhas')->default(0);
            $table->unsignedInteger('total_metodos')->default(0);
            $table->unsignedInteger('total_classes')->default(0);
            $table->unsignedInteger('complexidade_estimada')->default(0);
            $table->boolean('controlador')->default(false);
            $table->boolean('modelo')->default(false);
            $table->boolean('servico')->default(false);
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->index(['analise_id', 'tipo_arquivo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metricas_arquivos');
    }
};
