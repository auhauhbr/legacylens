<?php

namespace Tests\Unit;

use App\Dominio\Analises\Analisadores\QueryRiskAnalyzer;
use App\Dominio\Analises\DTO\DadosAchado;
use App\Enums\SeveridadeAchado;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QueryRiskAnalyzerTest extends TestCase
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
    #[DataProvider('chamadasRawLaravel')]
    public function teste_detecta_chamadas_raw_do_laravel(string $chamada): void
    {
        $resultado = $this->analisar("<?php\n{$chamada};\n");
        $achado = collect($resultado)->firstWhere('codigo', 'consultas.sql_raw');

        $this->assertNotNull($achado);
        $this->assertSame(SeveridadeAchado::Baixa, $achado->severidade);
    }

    /** @return iterable<string, array{string}> */
    public static function chamadasRawLaravel(): iterable
    {
        yield 'DB::raw' => ["DB::raw('COUNT(*)')"];
        yield 'selectRaw' => ["Usuario::query()->selectRaw('COUNT(*) AS total')"];
        yield 'whereRaw' => ["Usuario::query()->whereRaw('ativo = 1')"];
        yield 'orWhereRaw' => ["Usuario::query()->orWhereRaw('admin = 1')"];
        yield 'havingRaw' => ["Usuario::query()->havingRaw('COUNT(*) > 1')"];
        yield 'orderByRaw' => ["Usuario::query()->orderByRaw('nome DESC')"];
        yield 'groupByRaw' => ["Usuario::query()->groupByRaw('tipo')"];
    }

    #[Test]
    #[DataProvider('chamadasDiretasDb')]
    public function teste_detecta_execucao_direta_pela_facade_db(string $chamada): void
    {
        $resultado = $this->analisar("<?php\n{$chamada};\n");
        $achado = collect($resultado)->firstWhere('codigo', 'consultas.execucao_direta');

        $this->assertNotNull($achado);
        $this->assertSame(SeveridadeAchado::Media, $achado->severidade);
    }

    /** @return iterable<string, array{string}> */
    public static function chamadasDiretasDb(): iterable
    {
        yield 'statement' => ["DB::statement('ANALYZE TABLE usuarios')"];
        yield 'unprepared' => ["DB::unprepared('DELETE FROM logs')"];
        yield 'select' => ["DB::select('SELECT * FROM usuarios')"];
    }

    #[Test]
    public function teste_detecta_mysqli_query_e_consultas_pdo(): void
    {
        $conteudo = <<<'PHP'
<?php
mysqli_query($conexao, 'SELECT * FROM usuarios');
$pdo = new PDO($dsn);
$pdo->query('SELECT * FROM pedidos');
PDO::query('SELECT * FROM auditoria');
PHP;

        $resultado = $this->analisar($conteudo);
        $achados = collect($resultado)->where('codigo', 'consultas.consulta_raw_revisao');

        $this->assertCount(3, $achados);
        $this->assertContains('mysqli_query', $achados->pluck('evidencia')->pluck('api')->all());
        $this->assertContains('$pdo->query', $achados->pluck('evidencia')->pluck('api')->all());
        $this->assertContains('PDO::query', $achados->pluck('evidencia')->pluck('api')->all());
    }

    #[Test]
    public function teste_detecta_consulta_query_por_conteudo_sql_quando_receptor_nao_eh_conhecido(): void
    {
        $resultado = $this->analisar("<?php\n\$banco->query('SELECT * FROM clientes');\n");

        $this->assertNotNull(collect($resultado)->firstWhere('codigo', 'consultas.consulta_raw_revisao'));
    }

    #[Test]
    public function teste_detecta_concatenacao_e_interpolacao_em_sql_bruto(): void
    {
        $conteudo = <<<'PHP'
<?php
DB::select('SELECT * FROM usuarios WHERE id = '.$id);
DB::raw("status = '$status'");
PHP;

        $resultado = collect($this->analisar($conteudo));

        $this->assertSame(
            SeveridadeAchado::Alta,
            $resultado->firstWhere('codigo', 'consultas.sql_raw_concatenacao')->severidade,
        );
        $this->assertSame(
            SeveridadeAchado::Alta,
            $resultado->firstWhere('codigo', 'consultas.sql_raw_interpolacao')->severidade,
        );
    }

    #[Test]
    #[DataProvider('fontesEntradaUsuario')]
    public function teste_detecta_entrada_do_usuario_proxima_de_consulta_raw(string $fonte): void
    {
        $conteudo = "<?php\n\$valor = {$fonte};\nDB::select('SELECT * FROM usuarios WHERE id = '.\$valor);\n";
        $resultado = collect($this->analisar($conteudo));
        $achado = $resultado->firstWhere('codigo', 'consultas.possivel_entrada_usuario');

        $this->assertNotNull($achado);
        $this->assertSame(SeveridadeAchado::Alta, $achado->severidade);
    }

    /** @return iterable<string, array{string}> */
    public static function fontesEntradaUsuario(): iterable
    {
        yield 'GET' => ['$_GET[\'id\']'];
        yield 'POST' => ['$_POST[\'id\']'];
        yield 'REQUEST' => ['$_REQUEST[\'id\']'];
        yield 'request' => ['request(\'id\')'];
        yield 'input' => ['input(\'id\')'];
        yield 'query do request' => ['$request->query(\'id\')'];
    }

    #[Test]
    public function teste_ignora_comentarios_e_strings_soltas(): void
    {
        $conteudo = <<<'PHP'
<?php
// DB::raw('SELECT * FROM usuarios');
/* DB::select($_GET['sql']); */
$texto = "DB::unprepared('DELETE FROM usuarios')";
$outro = 'mysqli_query($conexao, "SELECT 1")';
PHP;

        $this->assertSame([], $this->analisar($conteudo));
    }

    #[Test]
    public function teste_ignora_diretorios_e_arquivos_configurados(): void
    {
        $diretorio = $this->criarDiretorioTemporario();
        $this->criarArquivo($diretorio, 'app/Valido.php', "<?php\nDB::raw('id');\n");
        $this->criarArquivo($diretorio, 'vendor/Pacote.php', "<?php\nDB::raw('id');\n");
        $this->criarArquivo($diretorio, 'personalizado/Ignorado.php', "<?php\nDB::raw('id');\n");
        $this->criarArquivo($diretorio, 'app/LegadoIgnorado.php', "<?php\nDB::raw('id');\n");
        config()->set('legacylens.ignored_directories', ['vendor', 'personalizado']);
        config()->set('legacylens.ignored_files', ['LegadoIgnorado.php']);

        $resultado = app(QueryRiskAnalyzer::class)->analisar($diretorio);

        $this->assertCount(1, $resultado);
        $this->assertSame('app/Valido.php', $resultado[0]->caminhoArquivo);
    }

    #[Test]
    public function teste_gera_localizacao_e_evidencia_curta_sanitizada_sem_duplicar_tipo(): void
    {
        $conteudo = "<?php\n\nDB::raw(  'SELECT   *   FROM usuarios'  ); DB::raw('SELECT 1');\n";
        $resultado = $this->analisar($conteudo, 'app/Consultas.php');
        $achado = collect($resultado)->firstWhere('codigo', 'consultas.sql_raw');

        $this->assertCount(1, $resultado);
        $this->assertSame('app/Consultas.php', $achado->caminhoArquivo);
        $this->assertSame(3, $achado->linhaInicial);
        $this->assertSame(3, $achado->evidencia['linha']);
        $this->assertLessThanOrEqual(240, mb_strlen($achado->evidencia['trecho']));
        $this->assertStringNotContainsString("\n", $achado->evidencia['trecho']);
    }

    /** @return list<DadosAchado> */
    private function analisar(string $conteudo, string $caminho = 'app/Consulta.php'): array
    {
        $diretorio = $this->criarDiretorioTemporario();
        $this->criarArquivo($diretorio, $caminho, $conteudo);

        return app(QueryRiskAnalyzer::class)->analisar($diretorio);
    }

    private function criarDiretorioTemporario(): string
    {
        $diretorio = sys_get_temp_dir().'/legacylens-consultas-'.uniqid('', true);
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
