# LegacyLens

Ferramenta Laravel para analisar sistemas PHP/Laravel legados e gerar plano de modernização.

## O que faz

O LegacyLens analisa projetos PHP/Laravel antigos e detecta:

- versões antigas de PHP/Laravel;
- dependências desatualizadas;
- vulnerabilidades conhecidas;
- pacotes abandonados;
- ausência de testes;
- ausência de CI;
- ausência de análise estática;
- controllers e arquivos grandes;
- queries potencialmente perigosas;
- funções de debug;
- documentação ausente.

Depois gera:

- score técnico;
- relatório executivo;
- plano técnico;
- roadmap de modernização;
- rascunhos de issues GitHub.

## Status

Fase 1 concluída: bootstrap Laravel 13, autenticação via Filament 5, Livewire 4,
fila em banco, PHPUnit e Pint. O painel fica disponível em `/admin` e permite
registro e login.

## Requisitos

- PHP 8.3 ou superior com as extensões `intl`, `mbstring`, `pdo`, `openssl` e `xml`;
- Composer 2;
- MySQL 8+ ou PostgreSQL 15+;
- Node.js 20+ (para os assets da aplicação).

## Instalação local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

Configure as credenciais do banco no `.env` local. Nunca use o `.env` de um
projeto que será analisado pelo LegacyLens.

## Qualidade

```bash
composer test
vendor/bin/pint --test
```

## Iniciar uma análise

No painel, acesse `/admin/projetos` e use a ação **Iniciar análise** no projeto.
A execução é enviada para a fila; mantenha um worker do próprio LegacyLens ativo:

```bash
php artisan queue:work
```

Também é possível iniciar o fluxo pelo Tinker:

```php
$projeto = App\Models\Projeto::query()->firstOrFail();
$analise = app(App\Dominio\Analises\Servicos\IniciadorAnalise::class)->iniciar($projeto);
```

Nesta fase, a análise apenas valida a infraestrutura e cria achados informativos.
Ela não executa analyzers reais nem modifica o projeto cadastrado.

## Documentação

- [Especificação](docs/LEGACYLENS_SPEC.md)
- [Plano de implementação](docs/IMPLEMENTATION_PLAN.md)
- [Checklist do MVP](checklists/MVP_ACCEPTANCE.md)
