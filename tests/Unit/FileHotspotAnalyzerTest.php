<?php

namespace Tests\Unit;

use App\Dominio\Analises\Analisadores\FileHotspotAnalyzer;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FileHotspotAnalyzerTest extends TestCase
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
    public function teste_percorre_php_e_ignora_diretorios_e_arquivos_configurados(): void
    {
        $diretorio = $this->criarDiretorioTemporario();
        $this->criarArquivo($diretorio, 'app/Models/Usuario.php', "<?php\nclass Usuario {}\n");
        $this->criarArquivo($diretorio, 'vendor/Pacote.php', '<?php class Pacote {}');
        $this->criarArquivo($diretorio, 'storage/cache.php', '<?php return [];');
        $this->criarArquivo($diretorio, 'app/Models/Ignorado.php', '<?php class Ignorado {}');
        $this->criarArquivo($diretorio, 'app/Models/texto.txt', 'não é PHP');
        config()->set('legacylens.ignored_files', ['Ignorado.php']);

        $resultado = app(FileHotspotAnalyzer::class)->analisar($diretorio);

        $this->assertSame(['app/Models/Usuario.php'], array_column($resultado->metricas, 'caminho_arquivo'));
        $this->assertSame(2, $resultado->metricas[0]['total_linhas']);
    }

    #[Test]
    public function teste_identifica_tipos_provaveis(): void
    {
        $diretorio = $this->criarDiretorioTemporario();
        $esperados = [
            'app/Http/Controllers/Conta.php' => 'controller',
            'app/Models/Conta.php' => 'model',
            'app/Servicos/Cobranca.php' => 'service',
            'app/Console/Commands/Limpar.php' => 'command',
            'database/migrations/2026_criar_contas.php' => 'migration',
            'app/Http/Requests/ContaRequest.php' => 'request',
            'app/Policies/ContaPolicy.php' => 'policy',
            'app/Providers/AppServiceProvider.php' => 'provider',
            'routes/web.php' => 'outro',
            'modulos/RelatorioController.php' => 'controller',
        ];

        foreach (array_keys($esperados) as $caminho) {
            $this->criarArquivo($diretorio, $caminho, '<?php');
        }

        $resultado = app(FileHotspotAnalyzer::class)->analisar($diretorio);
        $tipos = array_column($resultado->metricas, 'tipo_arquivo', 'caminho_arquivo');

        $this->assertSame($esperados, array_intersect_key($esperados, $tipos));

        foreach ($esperados as $caminho => $tipo) {
            $this->assertSame($tipo, $tipos[$caminho]);
        }
    }

    #[Test]
    public function teste_gera_achados_para_tamanho_e_area_sensivel(): void
    {
        $diretorio = $this->criarDiretorioTemporario();
        $this->criarArquivo($diretorio, 'app/Http/Controllers/LegadoController.php', $this->conteudoComLinhas(500));

        $resultado = app(FileHotspotAnalyzer::class)->analisar($diretorio);
        $codigos = array_map(fn ($achado): string => $achado->codigo, $resultado->achados);

        $this->assertContains('arquivos.arquivo_grande', $codigos);
        $this->assertContains('arquivos.controller_grande', $codigos);
        $this->assertContains('arquivos.area_sensivel_muito_longa', $codigos);
    }

    #[Test]
    public function teste_calcula_complexidade_e_gera_achado_no_limite(): void
    {
        $diretorio = $this->criarDiretorioTemporario();
        $condicoes = implode("\n", array_fill(0, 25, 'if ($ativo) { $valor++; }'));
        $this->criarArquivo($diretorio, 'app/Regra.php', "<?php\n{$condicoes}\n");

        $resultado = app(FileHotspotAnalyzer::class)->analisar($diretorio);
        $achado = collect($resultado->achados)->firstWhere('codigo', 'arquivos.complexidade_elevada');

        $this->assertSame(25, $resultado->metricas[0]['complexidade_estimada']);
        $this->assertNotNull($achado);
        $this->assertSame(25, $achado->evidencia['complexidade_estimada']);
    }

    private function criarDiretorioTemporario(): string
    {
        $diretorio = sys_get_temp_dir().'/legacylens-hotspots-'.uniqid('', true);
        File::makeDirectory($diretorio, 0755, true);
        $this->diretoriosTemporarios[] = $diretorio;

        return $diretorio;
    }

    private function criarArquivo(string $raiz, string $caminho, string $conteudo): void
    {
        File::ensureDirectoryExists(dirname($raiz.'/'.$caminho));
        File::put($raiz.'/'.$caminho, $conteudo);
    }

    private function conteudoComLinhas(int $total): string
    {
        return "<?php\n".str_repeat("// linha\n", $total - 1);
    }
}
