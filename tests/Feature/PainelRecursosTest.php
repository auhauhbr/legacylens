<?php

namespace Tests\Feature;

use App\Filament\Resources\Projetos\ProjetoResource;
use App\Models\Projeto;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PainelRecursosTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function teste_usuario_demo_acessa_os_recursos_da_fase_dois(): void
    {
        $this->seed(DatabaseSeeder::class);
        $usuario = User::query()->where('email', 'demo@legacylens.local')->firstOrFail();

        $this->actingAs($usuario);

        foreach (['projetos', 'analises', 'achados', 'relatorios'] as $recurso) {
            $this->get("/admin/{$recurso}")->assertOk();
        }
    }

    #[Test]
    public function teste_consulta_de_projetos_e_isolada_por_usuario(): void
    {
        $usuarioAtual = User::factory()->create();
        $outroUsuario = User::factory()->create();
        Projeto::factory()->for($usuarioAtual, 'usuario')->create();
        Projeto::factory()->for($outroUsuario, 'usuario')->create();

        $this->actingAs($usuarioAtual);

        $this->assertSame(1, ProjetoResource::getEloquentQuery()->count());
        $this->assertSame($usuarioAtual->id, ProjetoResource::getEloquentQuery()->firstOrFail()->usuario_id);
    }
}
