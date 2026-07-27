<?php

namespace Tests\Unit;

use App\Dominio\Analises\Analisadores\DebugCodeAnalyzer;
use App\Dominio\Analises\DTO\DadosAchado;
use App\Enums\SeveridadeAchado;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DebugCodeAnalyzerTest extends TestCase
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
    #[DataProvider('chamadasDebugPhp')]
    public function teste_detecta_chamadas_de_debug_php(
        string $chamada,
        string $codigo,
        SeveridadeAchado $severidade,
    ): void {
        $resultado = $this->analisar("<?php\n{$chamada};\n");
        $achado = collect($resultado)->firstWhere('codigo', $codigo);

        $this->assertNotNull($achado);
        $this->assertSame($severidade, $achado->severidade);
    }

    /** @return iterable<string, array{string, string, SeveridadeAchado}> */
    public static function chamadasDebugPhp(): iterable
    {
        yield 'dd' => ['dd($usuario)', 'debug.interrupcao_execucao', SeveridadeAchado::Alta];
        yield 'dump' => ['dump($usuario)', 'debug.possivel_vazamento_informacao', SeveridadeAchado::Media];
        yield 'var_dump' => ['var_dump($usuario)', 'debug.possivel_vazamento_informacao', SeveridadeAchado::Media];
        yield 'print_r' => ['print_r($usuario)', 'debug.possivel_vazamento_informacao', SeveridadeAchado::Media];
        yield 'die' => ['die($mensagem)', 'debug.interrupcao_execucao', SeveridadeAchado::Alta];
        yield 'exit' => ['exit($codigo)', 'debug.interrupcao_execucao', SeveridadeAchado::Alta];
        yield 'ray' => ['ray($usuario)', 'debug.possivel_vazamento_informacao', SeveridadeAchado::Media];
    }

    #[Test]
    public function teste_detecta_diretivas_de_debug_em_blade(): void
    {
        $conteudo = <<<'BLADE'
<main>
    @dd($usuario)
    @dump($pedidos)
</main>
BLADE;

        $resultado = collect($this->analisar($conteudo, 'resources/views/usuarios.blade.php'));
        $achados = $resultado->where('codigo', 'debug.template_blade')->values();

        $this->assertCount(2, $achados);
        $this->assertSame(SeveridadeAchado::Alta, $achados[0]->severidade);
        $this->assertSame('@dd', $achados[0]->evidencia['chamada']);
        $this->assertSame(SeveridadeAchado::Media, $achados[1]->severidade);
        $this->assertSame('@dump', $achados[1]->evidencia['chamada']);
    }

    #[Test]
    public function teste_detecta_chamada_php_dentro_de_template_blade(): void
    {
        $conteudo = "<?php\ndump(\$usuario);\n?>";
        $resultado = $this->analisar($conteudo, 'resources/views/php.blade.php');

        $this->assertNotNull(
            collect($resultado)->firstWhere('codigo', 'debug.possivel_vazamento_informacao'),
        );
    }

    #[Test]
    public function teste_ignora_ocorrencias_em_comentarios_strings_declaracoes_e_metodos(): void
    {
        $conteudo = <<<'PHP'
<?php
// dd($usuario);
/* dump($usuario); */
$texto = 'var_dump($usuario)';
$outro = "exit($codigo)";
function print_r($valor) {}
$objeto->dump($valor);
Depurador::ray($valor);
PHP;

        $this->assertSame([], $this->analisar($conteudo));
    }

    #[Test]
    public function teste_ignora_diretivas_blade_em_comentarios_e_diretiva_escapada(): void
    {
        $conteudo = <<<'BLADE'
{{-- @dd($usuario) --}}
<!-- @dump($usuario) -->
@@dd($usuario)
<?php // @dump($usuario) ?>
BLADE;

        $this->assertSame([], $this->analisar($conteudo, 'resources/views/ignorada.blade.php'));
    }

    #[Test]
    public function teste_ignora_diretorios_e_arquivos_configurados(): void
    {
        $diretorio = $this->criarDiretorioTemporario();
        $this->criarArquivo($diretorio, 'app/Valido.php', "<?php\ndd('teste');\n");
        $this->criarArquivo($diretorio, 'vendor/Pacote.php', "<?php\ndd('teste');\n");
        $this->criarArquivo($diretorio, 'personalizado/Ignorado.php', "<?php\ndd('teste');\n");
        $this->criarArquivo($diretorio, 'app/LegadoIgnorado.php', "<?php\ndd('teste');\n");
        $this->criarArquivo($diretorio, 'storage/views/cache.blade.php', '@dd($valor)');
        config()->set('legacylens.ignored_directories', ['vendor', 'storage', 'personalizado']);
        config()->set('legacylens.ignored_files', ['LegadoIgnorado.php']);

        $resultado = app(DebugCodeAnalyzer::class)->analisar($diretorio);

        $this->assertCount(1, $resultado);
        $this->assertSame('app/Valido.php', $resultado[0]->caminhoArquivo);
    }

    #[Test]
    public function teste_gera_localizacao_e_evidencia_curta_sanitizada_sem_duplicar_tipo(): void
    {
        $conteudo = "<?php\n\ndump(  \$usuario  ); var_dump(\$usuario);\n";
        $resultado = $this->analisar($conteudo, 'app/Depuracao.php');
        $achado = collect($resultado)->firstWhere('codigo', 'debug.possivel_vazamento_informacao');

        $this->assertCount(1, $resultado);
        $this->assertSame('app/Depuracao.php', $achado->caminhoArquivo);
        $this->assertSame(3, $achado->linhaInicial);
        $this->assertSame(3, $achado->evidencia['linha']);
        $this->assertLessThanOrEqual(240, mb_strlen($achado->evidencia['trecho']));
        $this->assertStringNotContainsString("\n", $achado->evidencia['trecho']);
    }

    /** @return list<DadosAchado> */
    private function analisar(string $conteudo, string $caminho = 'app/Depuracao.php'): array
    {
        $diretorio = $this->criarDiretorioTemporario();
        $this->criarArquivo($diretorio, $caminho, $conteudo);

        return app(DebugCodeAnalyzer::class)->analisar($diretorio);
    }

    private function criarDiretorioTemporario(): string
    {
        $diretorio = sys_get_temp_dir().'/legacylens-debug-'.uniqid('', true);
        File::makeDirectory($diretorio, 0755, true);
        $this->diretoriosTemporarios[] = $diretorio;

        return $diretorio;
    }

    private function criarArquivo(string $raiz, string $caminho, string $conteudo): void
    {
        File::ensureDirectoryExists(dirname($raiz.'/'.$caminho));
        File::put($raiz.'/'.$caminho, $conteudo);
    }
}
