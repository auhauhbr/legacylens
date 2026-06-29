<?php

namespace Tests\Unit;

use App\Dominio\Analises\Analisadores\CiAnalyzer;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CiAnalyzerTest extends TestCase
{
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
    public function teste_detecta_github_actions_rodando_testes_e_qualidade(): void
    {
        $diretorio = $this->criarDiretorioTemporario();
        File::makeDirectory($diretorio.'/.github/workflows', 0755, true);
        File::put($diretorio.'/.github/workflows/ci.yml', <<<'YAML'
name: CI
jobs:
  testes:
    steps:
      - run: php artisan test
      - run: ./vendor/bin/pint --test
      - run: ./vendor/bin/phpstan analyse
      - run: composer validate --strict
YAML);

        $achados = app(CiAnalyzer::class)->analisar($diretorio);
        $avaliacao = collect($achados)->firstWhere('codigo', 'ci.comandos_detectados');

        $this->assertNotNull($avaliacao);
        $this->assertTrue($avaliacao->evidencia['comandos']['artisan_test']);
        $this->assertTrue($avaliacao->evidencia['comandos']['pint']);
        $this->assertTrue($avaliacao->evidencia['comandos']['phpstan']);
        $this->assertNotContains('ci.testes_ausentes', $this->codigos($achados));
    }

    #[Test]
    public function teste_registra_ci_sem_testes(): void
    {
        $diretorio = $this->criarDiretorioTemporario();
        File::put($diretorio.'/.gitlab-ci.yml', "build:\n  script: composer validate\n");

        $achados = app(CiAnalyzer::class)->analisar($diretorio);

        $this->assertContains('ci.configuracao_detectada', $this->codigos($achados));
        $this->assertContains('ci.testes_ausentes', $this->codigos($achados));
    }

    #[Test]
    public function teste_registra_projeto_sem_ci(): void
    {
        $achados = app(CiAnalyzer::class)->analisar($this->criarDiretorioTemporario());

        $this->assertSame(['ci.configuracao_ausente'], $this->codigos($achados));
    }

    #[Test]
    public function teste_detecta_bitbucket_com_pest_e_larastan(): void
    {
        $diretorio = $this->criarDiretorioTemporario();
        File::put($diretorio.'/bitbucket-pipelines.yml', "script:\n  - ./vendor/bin/pest\n  - ./vendor/bin/larastan analyse\n");

        $achados = app(CiAnalyzer::class)->analisar($diretorio);
        $avaliacao = collect($achados)->firstWhere('codigo', 'ci.comandos_detectados');

        $this->assertTrue($avaliacao->evidencia['comandos']['pest']);
        $this->assertTrue($avaliacao->evidencia['comandos']['larastan']);
    }

    /** @return list<string> */
    private function codigos(array $achados): array
    {
        return array_map(fn ($achado): string => $achado->codigo, $achados);
    }

    private function criarDiretorioTemporario(): string
    {
        $diretorio = sys_get_temp_dir().'/legacylens-ci-'.uniqid('', true);
        File::makeDirectory($diretorio, 0755, true);
        $this->diretoriosTemporarios[] = $diretorio;

        return $diretorio;
    }
}
