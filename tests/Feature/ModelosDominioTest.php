<?php

namespace Tests\Feature;

use App\Enums\CategoriaAchado;
use App\Enums\SeveridadeAchado;
use App\Enums\StatusAnalise;
use App\Enums\StatusRascunhoIssue;
use App\Enums\TipoRelatorio;
use App\Models\Achado;
use App\Models\Analise;
use App\Models\Dependencia;
use App\Models\EtapaModernizacao;
use App\Models\LacunaTeste;
use App\Models\MetricaArquivo;
use App\Models\Projeto;
use App\Models\RascunhoIssueGithub;
use App\Models\RegistroRota;
use App\Models\Relatorio;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModelosDominioTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function teste_migrations_criam_as_tabelas_do_dominio(): void
    {
        foreach ([
            'projetos', 'analises', 'achados', 'dependencias', 'metricas_arquivos',
            'registros_rotas', 'lacunas_testes', 'etapas_modernizacao', 'relatorios',
            'rascunhos_issues_github',
        ] as $tabela) {
            $this->assertTrue(Schema::hasTable($tabela));
        }
    }

    #[Test]
    public function teste_factories_criam_o_grafo_completo_do_dominio(): void
    {
        $analise = Analise::factory()->create();
        $achado = Achado::factory()->for($analise)->create();
        $dependencia = Dependencia::factory()->for($analise)->create();
        $metrica = MetricaArquivo::factory()->for($analise)->create();
        $rota = RegistroRota::factory()->for($analise)->create();
        $lacuna = LacunaTeste::factory()->for($analise)->create();
        $etapa = EtapaModernizacao::factory()->for($analise)->create();
        $relatorio = Relatorio::factory()->for($analise)->create();
        $rascunho = RascunhoIssueGithub::factory()->for($analise)->create([
            'achado_id' => $achado->id,
            'etapa_modernizacao_id' => $etapa->id,
        ]);

        $this->assertInstanceOf(Projeto::class, $analise->projeto);
        $this->assertInstanceOf(User::class, $analise->projeto->usuario);
        $this->assertTrue($analise->achados->contains($achado));
        $this->assertTrue($analise->dependencias->contains($dependencia));
        $this->assertTrue($analise->metricasArquivos->contains($metrica));
        $this->assertTrue($analise->registrosRotas->contains($rota));
        $this->assertTrue($analise->lacunasTestes->contains($lacuna));
        $this->assertTrue($analise->etapasModernizacao->contains($etapa));
        $this->assertTrue($analise->relatorios->contains($relatorio));
        $this->assertTrue($analise->rascunhosIssuesGithub->contains($rascunho));
    }

    #[Test]
    public function teste_json_datas_e_enums_sao_convertidos_para_tipos_do_dominio(): void
    {
        $achado = Achado::factory()->create();

        $this->assertSame(StatusAnalise::Concluida, $achado->analise->status);
        $this->assertSame(CategoriaAchado::Testes, $achado->categoria);
        $this->assertSame(SeveridadeAchado::Alta, $achado->severidade);
        $this->assertIsArray($achado->evidencia);
        $this->assertIsArray($achado->analise->configuracao_analise);
        $this->assertNotNull($achado->analise->iniciado_em);
    }

    #[Test]
    public function teste_excluir_analise_exclui_seus_registros_derivados(): void
    {
        $analise = Analise::factory()->create();
        Achado::factory()->for($analise)->create();
        Dependencia::factory()->for($analise)->create();
        MetricaArquivo::factory()->for($analise)->create();
        RegistroRota::factory()->for($analise)->create();
        LacunaTeste::factory()->for($analise)->create();
        EtapaModernizacao::factory()->for($analise)->create();
        Relatorio::factory()->for($analise)->create();
        RascunhoIssueGithub::factory()->for($analise)->create();

        $analise->delete();

        $this->assertDatabaseCount('achados', 0);
        $this->assertDatabaseCount('dependencias', 0);
        $this->assertDatabaseCount('metricas_arquivos', 0);
        $this->assertDatabaseCount('registros_rotas', 0);
        $this->assertDatabaseCount('lacunas_testes', 0);
        $this->assertDatabaseCount('etapas_modernizacao', 0);
        $this->assertDatabaseCount('relatorios', 0);
        $this->assertDatabaseCount('rascunhos_issues_github', 0);
    }

    #[Test]
    public function teste_seeder_demo_e_idempotente_e_cria_apenas_rascunho(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('projetos', 1);
        $this->assertDatabaseCount('analises', 1);
        $this->assertDatabaseCount('achados', 3);
        $this->assertDatabaseCount('dependencias', 3);
        $this->assertDatabaseCount('metricas_arquivos', 3);
        $this->assertDatabaseCount('etapas_modernizacao', 3);
        $this->assertDatabaseCount('relatorios', 2);
        $this->assertDatabaseCount('rascunhos_issues_github', 2);
        $this->assertSame(StatusRascunhoIssue::Rascunho, RascunhoIssueGithub::firstOrFail()->status);
        $this->assertSame(TipoRelatorio::Executivo, Relatorio::firstOrFail()->tipo);
    }
}
