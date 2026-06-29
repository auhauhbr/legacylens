<?php

namespace Database\Seeders;

use App\Enums\CategoriaAchado;
use App\Enums\NivelConfianca;
use App\Enums\NivelRisco;
use App\Enums\SeveridadeAchado;
use App\Enums\StatusAnalise;
use App\Enums\StatusRascunhoIssue;
use App\Enums\TipoOrigemProjeto;
use App\Enums\TipoProjeto;
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
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Preenche o banco da aplicação com dados demonstrativos fictícios.
     */
    public function run(): void
    {
        $usuario = User::query()->updateOrCreate([
            'email' => 'demo@legacylens.local',
        ], [
            'name' => 'Usuário de demonstração',
            'password' => 'legacylens-demo',
        ]);

        $projeto = Projeto::query()->updateOrCreate([
            'usuario_id' => $usuario->id,
            'nome' => 'Sistema de Estoque Legado',
        ], [
            'descricao' => 'Projeto inteiramente fictício usado para demonstrar o LegacyLens.',
            'tipo' => TipoProjeto::Laravel,
            'tipo_origem' => TipoOrigemProjeto::Local,
            'caminho_local' => '/srv/projetos/sistema-estoque-legado',
            'url_repositorio' => null,
            'ramo' => 'main',
            'configuracao_padrao_analise' => [
                'tempo_limite_segundos' => 30,
                'analisadores' => ['composer', 'arquivos', 'testes'],
            ],
        ]);

        $analise = Analise::query()->updateOrCreate([
            'projeto_id' => $projeto->id,
            'resumo' => 'Análise fictícia para demonstração.',
        ], [
            'status' => StatusAnalise::Concluida,
            'iniciado_em' => now()->subMinutes(4),
            'finalizado_em' => now()->subMinutes(2),
            'duracao_segundos' => 120,
            'pontuacao' => 64,
            'nivel_risco' => NivelRisco::Moderado,
            'mensagem_erro' => null,
            'configuracao_analise' => ['tempo_limite_segundos' => 30],
            'versoes_analisadores' => ['demonstracao' => '1.0.0'],
        ]);

        $achados = collect([
            [
                'codigo' => 'testes.ausentes',
                'categoria' => CategoriaAchado::Testes,
                'severidade' => SeveridadeAchado::Alta,
                'confianca' => NivelConfianca::Alta,
                'titulo' => 'Projeto sem testes automatizados',
                'descricao' => 'Não foi localizada uma suíte de testes no projeto fictício.',
                'recomendacao' => 'Criar testes de caracterização para os fluxos críticos.',
                'caminho_arquivo' => 'tests',
                'evidencia' => ['diretorio_testes' => false],
            ],
            [
                'codigo' => 'arquitetura.controlador_grande',
                'categoria' => CategoriaAchado::Arquitetura,
                'severidade' => SeveridadeAchado::Media,
                'confianca' => NivelConfianca::Alta,
                'titulo' => 'Controlador com responsabilidade excessiva',
                'descricao' => 'O controlador de estoque possui 742 linhas.',
                'recomendacao' => 'Extrair regras de negócio após criar testes de caracterização.',
                'caminho_arquivo' => 'app/Http/Controllers/EstoqueController.php',
                'evidencia' => ['total_linhas' => 742],
            ],
            [
                'codigo' => 'dependencias.framework_desatualizado',
                'categoria' => CategoriaAchado::Dependencias,
                'severidade' => SeveridadeAchado::Alta,
                'confianca' => NivelConfianca::Media,
                'titulo' => 'Framework fora da linha de suporte atual',
                'descricao' => 'A massa demonstrativa representa uma aplicação Laravel 8.',
                'recomendacao' => 'Planejar atualização incremental após estabilizar os testes.',
                'caminho_arquivo' => 'composer.json',
                'evidencia' => ['versao_atual' => '8.0.0'],
            ],
        ])->map(function (array $dados) use ($analise): Achado {
            return Achado::query()->updateOrCreate([
                'analise_id' => $analise->id,
                'impressao_digital' => hash('sha256', $dados['codigo']),
            ], $dados + [
                'linha_inicial' => null,
                'linha_final' => null,
                'metadados' => ['demonstracao' => true],
            ]);
        });

        foreach ([
            ['laravel/framework', '8.0.0', '13.0.0', '^8.0', 'producao', true, false, false, false],
            ['guzzlehttp/guzzle', '7.4.0', '7.10.0', '^7.0', 'producao', true, false, false, false],
            ['phpunit/phpunit', '9.5.0', '12.0.0', '^9.5', 'desenvolvimento', true, true, false, false],
        ] as [$nome, $atual, $recente, $restricao, $escopo, $direta, $desenvolvimento, $abandonada, $alerta]) {
            Dependencia::query()->updateOrCreate([
                'analise_id' => $analise->id,
                'nome_pacote' => $nome,
            ], [
                'versao_atual' => $atual,
                'versao_mais_recente' => $recente,
                'restricao' => $restricao,
                'escopo' => $escopo,
                'direta' => $direta,
                'desenvolvimento' => $desenvolvimento,
                'abandonada' => $abandonada,
                'possui_alerta_seguranca' => $alerta,
                'metadados' => ['demonstracao' => true],
            ]);
        }

        foreach ([
            ['app/Http/Controllers/EstoqueController.php', 'controlador', 742, 28, 1, 44, true, false, false],
            ['app/Models/Produto.php', 'modelo', 286, 14, 1, 18, false, true, false],
            ['app/Services/EstoqueService.php', 'servico', 418, 19, 1, 32, false, false, true],
        ] as [$caminho, $tipo, $linhas, $metodos, $classes, $complexidade, $controlador, $modelo, $servico]) {
            MetricaArquivo::query()->updateOrCreate([
                'analise_id' => $analise->id,
                'caminho_arquivo' => $caminho,
            ], [
                'tipo_arquivo' => $tipo,
                'total_linhas' => $linhas,
                'total_metodos' => $metodos,
                'total_classes' => $classes,
                'complexidade_estimada' => $complexidade,
                'controlador' => $controlador,
                'modelo' => $modelo,
                'servico' => $servico,
            ]);
        }

        RegistroRota::query()->updateOrCreate([
            'analise_id' => $analise->id,
            'uri' => 'estoque/produtos',
        ], [
            'metodo' => 'GET',
            'nome' => 'estoque.produtos',
            'controlador' => 'EstoqueController',
            'acao' => 'EstoqueController@index',
            'middlewares' => ['web', 'auth'],
            'arquivo_origem' => 'routes/web.php',
            'metadados' => ['demonstracao' => true],
        ]);

        LacunaTeste::query()->updateOrCreate([
            'analise_id' => $analise->id,
            'tipo_alvo' => 'controlador',
            'nome_alvo' => 'EstoqueController',
        ], [
            'caminho_alvo' => 'app/Http/Controllers/EstoqueController.php',
            'caminho_teste_esperado' => 'tests/Feature/EstoqueControllerTest.php',
            'confianca' => 90,
        ]);

        $etapas = collect([
            [1, 1, 'critica', 'Criar testes de caracterização', 'Cobrir entradas e saídas dos fluxos críticos.', 'medio', 'alto'],
            [2, 1, 'alta', 'Atualizar dependências de segurança', 'Atualizar pacotes com risco sem realizar salto de framework.', 'medio', 'alto'],
            [3, 1, 'media', 'Decompor o controlador de estoque', 'Extrair regras de negócio para serviços menores.', 'alto', 'medio'],
        ])->map(function (array $dados) use ($analise, $achados): EtapaModernizacao {
            [$fase, $posicao, $prioridade, $titulo, $descricao, $esforco, $risco] = $dados;

            return EtapaModernizacao::query()->updateOrCreate([
                'analise_id' => $analise->id,
                'fase' => $fase,
                'posicao' => $posicao,
            ], [
                'prioridade' => $prioridade,
                'titulo' => $titulo,
                'descricao' => $descricao,
                'esforco' => $esforco,
                'risco' => $risco,
                'criterios_aceite' => ['Mudança validada por testes automatizados'],
                'ids_achados_relacionados' => $achados->pluck('id')->all(),
            ]);
        });

        foreach ([
            TipoRelatorio::Executivo->value => ['Relatório executivo', "# Relatório executivo\n\nO sistema apresenta risco moderado e requer modernização incremental."],
            TipoRelatorio::Tecnico->value => ['Plano técnico', "# Plano técnico\n\n1. Criar testes.\n2. Atualizar dependências.\n3. Reduzir hotspots."],
        ] as $tipo => [$titulo, $conteudo]) {
            Relatorio::query()->updateOrCreate([
                'analise_id' => $analise->id,
                'tipo' => $tipo,
            ], [
                'titulo' => $titulo,
                'conteudo_markdown' => $conteudo,
            ]);
        }

        foreach ($achados->take(2) as $indice => $achado) {
            RascunhoIssueGithub::query()->updateOrCreate([
                'analise_id' => $analise->id,
                'achado_id' => $achado->id,
            ], [
                'etapa_modernizacao_id' => $etapas->get($indice)?->id,
                'titulo' => $achado->titulo,
                'corpo' => "## Problema\n\n{$achado->descricao}\n\n## Recomendação\n\n{$achado->recomendacao}",
                'rotulos' => [$achado->categoria->value, $achado->severidade->value],
                'status' => StatusRascunhoIssue::Rascunho,
                'url_issue_github' => null,
                'publicado_em' => null,
                'metadados' => ['demonstracao' => true],
            ]);
        }
    }
}
