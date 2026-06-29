<?php

namespace Tests\Feature;

use App\Dominio\Analises\DTO\ResultadoProcesso;
use App\Dominio\Analises\Servicos\ExecutorAnalise;
use App\Dominio\Analises\Servicos\ExecutorProcessos;
use App\Dominio\Analises\Servicos\IniciadorAnalise;
use App\Dominio\Analises\Servicos\ResolvedorCaminhoSeguro;
use App\Enums\StatusAnalise;
use App\Jobs\ExecutarAnaliseProjeto;
use App\Models\Analise;
use App\Models\Projeto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InfraestruturaAnaliseTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $diretoriosTemporarios = [];

    protected function tearDown(): void
    {
        foreach ($this->diretoriosTemporarios as $diretorio) {
            File::deleteDirectory($diretorio);
        }

        parent::tearDown();
    }

    #[Test]
    public function teste_resolvedor_aceita_diretorio_temporario_valido(): void
    {
        $diretorio = $this->criarDiretorioTemporario();

        $resolvido = app(ResolvedorCaminhoSeguro::class)->resolver($diretorio);

        $this->assertSame(realpath($diretorio), $resolvido);
    }

    #[Test]
    public function teste_resolvedor_rejeita_diretorio_inexistente(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ResolvedorCaminhoSeguro::class)->resolver('/tmp/legacylens-inexistente-'.uniqid());
    }

    #[Test]
    public function teste_resolvedor_rejeita_arquivo_no_lugar_de_diretorio(): void
    {
        $diretorio = $this->criarDiretorioTemporario();
        $arquivo = $diretorio.'/arquivo.php';
        File::put($arquivo, '<?php');

        $this->expectException(InvalidArgumentException::class);

        app(ResolvedorCaminhoSeguro::class)->resolver($arquivo);
    }

    #[Test]
    public function teste_resolvedor_rejeita_diretorios_sensiveis(): void
    {
        foreach (['/', '/etc', '/home', '/root', '/var', '/usr'] as $diretorio) {
            try {
                app(ResolvedorCaminhoSeguro::class)->resolver($diretorio);
                $this->fail("O diretório sensível {$diretorio} foi aceito.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function teste_resolvedor_rejeita_travessia_de_diretorio(): void
    {
        $diretorio = $this->criarDiretorioTemporario();

        $this->expectException(InvalidArgumentException::class);

        app(ResolvedorCaminhoSeguro::class)->resolver($diretorio.'/../'.basename($diretorio));
    }

    #[Test]
    public function teste_executor_processos_rejeita_chave_desconhecida(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ExecutorProcessos::class)->executar('comando_livre', $this->criarDiretorioTemporario());
    }

    #[Test]
    public function teste_executor_processos_retorna_resultado_para_comando_permitido(): void
    {
        Process::fake([
            '*' => Process::result(output: 'main', errorOutput: '', exitCode: 0),
        ]);

        $resultado = app(ExecutorProcessos::class)->executar(
            'git_current_branch',
            $this->criarDiretorioTemporario(),
        );

        $this->assertInstanceOf(ResultadoProcesso::class, $resultado);
        $this->assertSame('git_current_branch', $resultado->chaveComando);
        $this->assertSame(0, $resultado->codigoSaida);
        $this->assertTrue($resultado->sucesso);
        $this->assertSame("main\n", $resultado->saidaPadrao);
        $this->assertFalse($resultado->tempoExcedido);
        Process::assertRan(fn (PendingProcess $processo): bool => $processo->command === [
            'git', 'rev-parse', '--abbrev-ref', 'HEAD',
        ]);
    }

    #[Test]
    public function teste_executor_analise_conclui_fluxo_feliz_e_preserva_projeto(): void
    {
        $diretorio = $this->criarDiretorioTemporario();
        $arquivo = $diretorio.'/marcador.txt';
        File::put($arquivo, 'conteúdo imutável');
        $hashAntes = hash_file('sha256', $arquivo);
        $analise = $this->criarAnalise($diretorio);

        $resultado = app(ExecutorAnalise::class)->executar($analise);

        $this->assertSame(StatusAnalise::Concluida, $resultado->status);
        $this->assertSame(100, $resultado->pontuacao);
        $this->assertNotNull($resultado->iniciado_em);
        $this->assertNotNull($resultado->finalizado_em);
        $this->assertNotNull($resultado->duracao_segundos);
        $this->assertSame($hashAntes, hash_file('sha256', $arquivo));
    }

    #[Test]
    public function teste_executor_analise_marca_falha_para_caminho_invalido(): void
    {
        $analise = $this->criarAnalise('/tmp/legacylens-inexistente-'.uniqid());

        $resultado = app(ExecutorAnalise::class)->executar($analise);

        $this->assertSame(StatusAnalise::Falhou, $resultado->status);
        $this->assertNotNull($resultado->mensagem_erro);
        $this->assertNotNull($resultado->finalizado_em);
    }

    #[Test]
    public function teste_executor_analise_cria_achados_informativos_temporarios(): void
    {
        $analise = $this->criarAnalise($this->criarDiretorioTemporario());

        app(ExecutorAnalise::class)->executar($analise);

        $this->assertSame([
            'analisadores.pendentes',
            'analise.iniciada',
            'projeto.caminho_validado',
        ], $analise->achados()->orderBy('codigo')->pluck('codigo')->all());
    }

    #[Test]
    public function teste_iniciador_cria_analise_pendente_e_dispara_job(): void
    {
        Bus::fake();
        $projeto = Projeto::factory()->create([
            'caminho_local' => $this->criarDiretorioTemporario(),
        ]);

        $analise = app(IniciadorAnalise::class)->iniciar($projeto);

        $this->assertSame(StatusAnalise::Pendente, $analise->status);
        $this->assertTrue($projeto->analises()->whereKey($analise->getKey())->exists());
        Bus::assertDispatched(
            ExecutarAnaliseProjeto::class,
            fn (ExecutarAnaliseProjeto $job): bool => $job->analiseId === $analise->id,
        );
    }

    private function criarDiretorioTemporario(): string
    {
        $diretorio = sys_get_temp_dir().'/legacylens-teste-'.uniqid('', true);
        File::makeDirectory($diretorio, 0755, true);
        $this->diretoriosTemporarios[] = $diretorio;

        return $diretorio;
    }

    private function criarAnalise(string $caminho): Analise
    {
        $projeto = Projeto::factory()->create(['caminho_local' => $caminho]);

        return Analise::factory()->for($projeto)->create([
            'status' => StatusAnalise::Pendente,
            'iniciado_em' => null,
            'finalizado_em' => null,
            'duracao_segundos' => null,
            'pontuacao' => null,
            'nivel_risco' => null,
        ]);
    }
}
