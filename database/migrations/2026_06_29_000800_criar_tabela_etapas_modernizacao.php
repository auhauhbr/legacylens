<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etapas_modernizacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analise_id')->constrained('analises')->cascadeOnDelete();
            $table->unsignedSmallInteger('fase');
            $table->unsignedSmallInteger('posicao');
            $table->string('titulo');
            $table->text('descricao');
            $table->string('prioridade', 20);
            $table->string('esforco', 20)->nullable();
            $table->string('risco', 20)->nullable();
            $table->json('criterios_aceite')->nullable();
            $table->json('ids_achados_relacionados')->nullable();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->unique(['analise_id', 'fase', 'posicao']);
            $table->index(['analise_id', 'prioridade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etapas_modernizacao');
    }
};
