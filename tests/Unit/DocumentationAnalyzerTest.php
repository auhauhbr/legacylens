<?php

namespace Tests\Unit;

use App\Dominio\Analises\Analisadores\DocumentationAnalyzer;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentationAnalyzerTest extends TestCase
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
    public function teste_detecta_readme_completo_e_arquivo_de_ambiente(): void
    {
        $diretorio = $this->criarDiretorioTemporario();
        File::put($diretorio.'/README.md', <<<'MARKDOWN'
# Projeto
## Instalação
Execute composer install e configure as variáveis de ambiente no .env.
## Desenvolvimento
Execute php artisan serve.
## Testes
Execute php artisan test.
## Deploy
Consulte o procedimento de produção.
MARKDOWN);
        File::put($diretorio.'/.env.example', 'APP_ENV=local');

        $achados = app(DocumentationAnalyzer::class)->analisar($diretorio);

        $this->assertContains('documentacao.readme_detectado', $this->codigos($achados));
        $this->assertContains('documentacao.env_exemplo_detectado', $this->codigos($achados));
        $this->assertNotContains('documentacao.instrucoes_testes_ausentes', $this->codigos($achados));
        $this->assertNotContains('documentacao.readme_incompleto', $this->codigos($achados));
    }

    #[Test]
    public function teste_registra_readme_e_env_exemplo_ausentes(): void
    {
        $achados = app(DocumentationAnalyzer::class)->analisar($this->criarDiretorioTemporario());

        $this->assertContains('documentacao.readme_ausente', $this->codigos($achados));
        $this->assertContains('documentacao.env_exemplo_ausente', $this->codigos($achados));
    }

    #[Test]
    public function teste_registra_readme_sem_instrucoes_de_testes(): void
    {
        $diretorio = $this->criarDiretorioTemporario();
        File::put($diretorio.'/README.rst', "Instalação\nExecute composer install.");

        $achados = app(DocumentationAnalyzer::class)->analisar($diretorio);

        $this->assertContains('documentacao.instrucoes_testes_ausentes', $this->codigos($achados));
    }

    #[Test]
    public function teste_detecta_documentacao_publica_equivalente(): void
    {
        $diretorio = $this->criarDiretorioTemporario();
        File::makeDirectory($diretorio.'/public/docs', 0755, true);

        $achados = app(DocumentationAnalyzer::class)->analisar($diretorio);
        $achado = collect($achados)->firstWhere('codigo', 'documentacao.publica_detectada');

        $this->assertNotNull($achado);
        $this->assertSame('public/docs', $achado->evidencia['diretorio_detectado']);
    }

    /** @return list<string> */
    private function codigos(array $achados): array
    {
        return array_map(fn ($achado): string => $achado->codigo, $achados);
    }

    private function criarDiretorioTemporario(): string
    {
        $diretorio = sys_get_temp_dir().'/legacylens-documentacao-'.uniqid('', true);
        File::makeDirectory($diretorio, 0755, true);
        $this->diretoriosTemporarios[] = $diretorio;

        return $diretorio;
    }
}
