<?php

namespace Tests\Unit;

use App\Dominio\Analises\Analisadores\ComposerAnalyzer;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComposerAnalyzerTest extends TestCase
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
    public function teste_analisa_manifesto_valido_e_versoes_do_lock(): void
    {
        $diretorio = $this->criarProjetoFake([
            'require' => ['php' => '^8.2', 'laravel/framework' => '^11.0', 'guzzlehttp/guzzle' => '^7.0'],
            'require-dev' => ['pestphp/pest' => '^3.0', 'larastan/larastan' => '^3.0', 'laravel/pint' => '^1.0'],
        ], [
            'packages' => [
                ['name' => 'laravel/framework', 'version' => 'v11.20.0'],
                ['name' => 'guzzlehttp/guzzle', 'version' => '7.9.0'],
            ],
            'packages-dev' => [['name' => 'pestphp/pest', 'version' => 'v3.5.0']],
        ]);

        $resultado = app(ComposerAnalyzer::class)->analisar($diretorio);

        $this->assertSame([
            'composer.laravel_detectado',
            'composer.php_detectado',
        ], $this->codigos($resultado->achados));
        $this->assertCount(5, $resultado->dependencias);
        $laravel = collect($resultado->dependencias)->firstWhere('nome_pacote', 'laravel/framework');
        $this->assertSame('^11.0', $laravel['restricao']);
        $this->assertSame('v11.20.0', $laravel['versao_atual']);
        $this->assertFalse($laravel['desenvolvimento']);
    }

    #[Test]
    public function teste_registra_lock_ausente_sem_interromper_inventario(): void
    {
        $diretorio = $this->criarProjetoFake([
            'require' => ['php' => '^8.1', 'monolog/monolog' => '^3.0'],
            'require-dev' => ['phpunit/phpunit' => '^10.0', 'phpstan/phpstan' => '^1.0', 'friendsofphp/php-cs-fixer' => '^3.0'],
        ]);

        $resultado = app(ComposerAnalyzer::class)->analisar($diretorio);

        $this->assertContains('composer.lock_ausente', $this->codigos($resultado->achados));
        $this->assertCount(4, $resultado->dependencias);
        $this->assertNull($resultado->dependencias[0]['versao_atual']);
    }

    #[Test]
    public function teste_registra_manifesto_ausente(): void
    {
        $resultado = app(ComposerAnalyzer::class)->analisar($this->criarDiretorioTemporario());

        $this->assertSame(['composer.manifesto_ausente'], $this->codigos($resultado->achados));
        $this->assertSame([], $resultado->dependencias);
    }

    #[Test]
    public function teste_registra_ferramentas_de_qualidade_ausentes(): void
    {
        $diretorio = $this->criarProjetoFake([
            'require' => ['php' => '^8.0'],
            'require-dev' => [],
        ], ['packages' => [], 'packages-dev' => []]);

        $resultado = app(ComposerAnalyzer::class)->analisar($diretorio);

        $this->assertContains('composer.ferramenta_testes_ausente', $this->codigos($resultado->achados));
        $this->assertContains('composer.analise_estatica_ausente', $this->codigos($resultado->achados));
        $this->assertContains('composer.formatador_ausente', $this->codigos($resultado->achados));
    }

    #[Test]
    public function teste_registra_manifesto_json_invalido_sem_expor_conteudo(): void
    {
        $diretorio = $this->criarDiretorioTemporario();
        File::put($diretorio.'/composer.json', '{invalido');

        $resultado = app(ComposerAnalyzer::class)->analisar($diretorio);

        $this->assertSame(['composer.manifesto_invalido'], $this->codigos($resultado->achados));
        $this->assertStringNotContainsString('{invalido', json_encode($resultado->achados[0]->evidencia));
    }

    /** @return list<string> */
    private function codigos(array $achados): array
    {
        $codigos = array_map(fn ($achado): string => $achado->codigo, $achados);
        sort($codigos);

        return $codigos;
    }

    private function criarProjetoFake(array $manifesto, ?array $lock = null): string
    {
        $diretorio = $this->criarDiretorioTemporario();
        File::put($diretorio.'/composer.json', json_encode($manifesto, JSON_THROW_ON_ERROR));

        if ($lock !== null) {
            File::put($diretorio.'/composer.lock', json_encode($lock, JSON_THROW_ON_ERROR));
        }

        return $diretorio;
    }

    private function criarDiretorioTemporario(): string
    {
        $diretorio = sys_get_temp_dir().'/legacylens-composer-'.uniqid('', true);
        File::makeDirectory($diretorio, 0755, true);
        $this->diretoriosTemporarios[] = $diretorio;

        return $diretorio;
    }
}
