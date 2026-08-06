# USAGE.md — rosiumdata/laravel

> API de referência completa do pacote Laravel. Toda classe, todo método, toda opção.

---

## ÍNDICE

1. [Conceito geral](#1-conceito-geral)
2. [RosiumTable (classe base)](#2-rosiumtable-classe-base)
3. [Column (coluna de dados)](#3-column-coluna-de-dados)
4. [ActionColumn (botões de ação)](#4-actioncolumn-botões-de-ação)
5. [Eventos e manipuladores JS](#5-eventos-e-manipuladores-js)
6. [API auto-gerada (rotas e query params)](#6-api-auto-gerada-rotas-e-query-params)
7. [Operadores de filtro](#7-operadores-de-filtro)
8. [Tratamento de erros](#8-tratamento-de-erros)
9. [Configuração](#9-configuração)
10. [Artisan commands](#10-artisan-commands)
11. [Consultas avançadas](#11-consultas-avançadas)
12. [Detecção de schema](#12-detecção-de-schema)
13. [Arquivos JS gerados](#13-arquivos-js-gerados)
14. [Troubleshooting](#14-troubleshooting)

---

## 1. CONCEITO GERAL

Este pacote elimina todo o boilerplate de usar o Web Component `<rosium-table>` no Laravel Blade.

**Sem o pacote:** você escreve um controller (50+ linhas), uma rota manual, um arquivo JS com `column()` + `LaravelAdapter`, um import no `app.js`, e um tag Blade.

**Com o pacote:** uma classe PHP + um tag Blade. O pacote gera o controller, a rota, e o JavaScript automaticamente.

```php
// app/RosiumTables/ProdutosTable.php
class ProdutosTable extends RosiumTable
{
    public static function name(): string { return 'produtos'; }
    public function query(): Builder { return Produto::query(); }
    public function columns(): array { /* ... */ }
}
```

```blade
<rosium-table rosium="produtos" page-size="25" />
```

**O que você NUNCA escreve:**
- Controller (o pacote tem um genérico que atende todas as tabelas)
- Rota (registrada automaticamente em `/rosium-data/{table}`)
- Arquivo JS (gerado automaticamente de `columns()`)
- `import { LaravelAdapter } from '@rosiumdata/core'` no app.js

---

## 2. ROSIUMTABLE CLASSE BASE

```php
namespace Rosiumdata\Laravel;

use Illuminate\Database\Eloquent\Builder;

abstract class RosiumTable
{
    // ===== OBRIGATÓRIOS =====

    /** Identificador único. Usado na rota e no atributo rosium="" do Blade. */
    abstract public static function name(): string;

    /** Eloquent query builder. Suporta select, join, where, subqueries. */
    abstract public function query(): Builder;

    /** Array de Column ou ActionColumn. */
    abstract public function columns(): array;

    // ===== OPCIONAIS (já têm default) =====

    /** Itens por página. Default: 20 */
    public function defaultPageSize(): int;

    /** Limite máximo de itens por página. Default: 1000 */
    public function maxPageSize(): int;

    /** Locale para formatação de número/data. Default: 'pt-BR' */
    public function locale(): string;

    /** Chave de persistência no localStorage. null = desabilitado. Default: null */
    public function persistenceKey(): ?string;

    /** JavaScript inline para event listeners. Default: null */
    public function eventHandlers(): ?string;

    /** Resolve key de coluna → nome qualificado da coluna no banco. Default: retorna a própria key */
    public function qualifyColumn(string $key): string;
}
```

### 2.1 name()

O nome da tabela. Deve ser um identificador JavaScript válido: letras, números e underscore. **Sem hífens, sem acentos, sem espaços.**

```php
public static function name(): string
{
    return 'produtos';           // ✅
    // return 'meus-produtos';   // ❌ hífen não é válido em identificador JS
    // return 'meus_produtos';   // ✅ underline é válido
}
```

Este nome aparece em 3 lugares:
- No atributo Blade: `<rosium-table rosium="produtos" />`
- Na rota da API: `GET /rosium-data/produtos`
- No arquivo JS gerado: `resources/js/rosium/produtos.js`

### 2.2 query()

Retorna um **Eloquent Builder**. Tudo que o Eloquent suporta funciona aqui: `select()`, `join()`, `leftJoin()`, `where()`, subqueries com `DB::raw()`.

```php
public function query(): Builder
{
    return Produto::query()
        ->select(['produtos.*', 'categorias.nome as categoria_nome'])
        ->leftJoin('categorias', 'categorias.id', '=', 'produtos.categoria_id')
        ->where('produtos.ativo', true);
}
```

O controller chama `query()` e aplica filtro, ordenação e paginação em cima. O `where('ativo', true)` é um **pré-filtro** — o usuário nunca vê produtos inativos, independente dos filtros aplicados.

### 2.3 columns()

Array de `Column::make()` e/ou `ActionColumn::make()`. A ordem no array é a ordem das colunas na tabela.

```php
public function columns(): array
{
    return [
        Column::make('id', 'number')->label('ID'),
        Column::make('nome', 'text')->label('Produto')->sortable(),
        Column::make('preco', 'number')->label('Preço')->mask('R$ #,##0.00'),
        ActionColumn::make('acoes', [
            ['key' => 'editar', 'label' => 'Editar'],
            ['key' => 'excluir', 'label' => 'Excluir', 'danger' => true],
        ]),
    ];
}
```

### 2.4 defaultPageSize()

Controla quantos itens por página por padrão. O usuário pode mudar via atributo `page-size` no Blade.

```php
public function defaultPageSize(): int
{
    return 25;  // 25 itens por página em vez de 20
}
```

### 2.5 maxPageSize()

Limite máximo de itens por página. Protege o servidor de `?per_page=999999`.

```php
public function maxPageSize(): int
{
    return 500;  // nunca mais que 500 itens por request
}
```

### 2.6 locale()

Locale usado pelo Web Component para formatar números e datas.

```php
public function locale(): string
{
    return 'en-US';  // $1,000.00 | 12/25/2024
    // return 'pt-BR';  // R$ 1.000,00 | 25/12/2024 (default)
}
```

### 2.7 persistenceKey()

Se definida, o Web Component salva estado no `localStorage`: filtros, ordenação, página atual. Ao recarregar a página, o estado é restaurado.

```php
public function persistenceKey(): string
{
    return 'produtos';  // salva em localStorage['produtos']
}
```

Retorne `null` para desabilitar (default).

### 2.8 eventHandlers()

JavaScript **inline** injetado após a configuração da tabela. A variável `el` é o elemento `<rosium-table>`. Útil para actions, refresh programático, etc.

```php
public function eventHandlers(): ?string
{
    return "el.addEventListener('action', ({ detail: { key, row } }) => {
        if (key === 'editar') window.location.href = '/produtos/' + row.raw.id + '/editar'
        if (key === 'excluir' && confirm('Excluir?')) {
            fetch('/api/produtos/' + row.raw.id, { method: 'DELETE' })
                .then(() => el.refresh?.())
        }
    })";
}
```

**Cuidado:** é JavaScript inline. Use apenas para lógica simples. Para lógica complexa, prefira o arquivo JS externo.

### 2.9 qualifyColumn()

Quando sua query usa JOINs ou aliases, o nome da coluna no `columns()` pode não ser o nome real na tabela. Com `qualifyColumn()`, você mapeia o nome de exibição → nome qualificado no banco para filtros e ordenação.

```php
// Query com JOIN
public function query(): Builder
{
    return UserResponsavel::query()
        ->select(['users_responsavel.*', 'users.name as nome'])
        ->leftJoin('users', 'users.id', '=', 'users_responsavel.user_id');
}

// Colunas usam os aliases
public function columns(): array
{
    return [
        Column::make('id', 'number')->label('ID'),
        Column::make('nome', 'text')->label('Nome'),  // alias do JOIN
    ];
}

// qualifyColumn resolve para o nome real na tabela
public function qualifyColumn(string $key): string
{
    return match ($key) {
        'id'    => 'users_responsavel.id',
        'nome'  => 'users.name',
        default => $key,
    };
}
```

Sem `qualifyColumn()`, o controller faria `WHERE nome LIKE '%coca%'` e o SQL retornaria erro de coluna ambígua. Com `qualifyColumn()`, ele faz `WHERE users.name LIKE '%coca%'`.

---

## 3. COLUMN (COLUNA DE DADOS)

### 3.1 Column::make()

```php
public static function make(string $key, string $type): self
```

| Parâmetro | Descrição |
|---|---|
| `$key` | Nome da coluna no banco (ou alias da query). Ex: `'preco'`, `'categoria_nome'` |
| `$type` | Tipo da coluna. Um de: `text`, `number`, `date`, `datetime`, `boolean`, `select` |

**Defaults por tipo:**

| Tipo | `sortable` | `filterable` | `label` |
|---|---|---|---|
| `text` | `true` | `true` | ucfirst da key |
| `number` | `true` | `true` | ucfirst da key |
| `date` | `true` | `true` | ucfirst da key |
| `datetime` | `true` | `true` | ucfirst da key |
| `boolean` | `true` | `true` | ucfirst da key |
| `select` | `true` | `true` | ucfirst da key |

### 3.2 Métodos fluentes

Todos retornam `$this` para encadeamento:

| Método | Assinatura | Default | Descrição |
|---|---|---|---|
| `label()` | `(string $label)` | key da coluna | Texto do cabeçalho |
| `mask()` | `(string $mask)` | `null` | Máscara de exibição (number/date) |
| `sortable()` | `(bool $sortable = true)` | `true` | Permite ordenar por esta coluna |
| `filterable()` | `(bool $filterable = true)` | `true` | Mostra input de filtro para esta coluna |
| `visible()` | `(bool $visible = true)` | `true` | Coluna visível por padrão |
| `options()` | `(array $options)` | `null` | Opções do select `[value => label]` |
| `alignment()` | `(string $alignment)` | `null` | Alinhamento: `'left'`, `'center'`, `'right'` |

### 3.3 Exemplos por tipo

#### text

```php
Column::make('nome', 'text')
    ->label('Produto')
    ->sortable()
    ->filterable()        // input de texto: contém, começa com, termina com, igual
```

#### number

```php
Column::make('preco', 'number')
    ->label('Preço')
    ->mask('R$ #,##0.00')
    ->alignment('right')
    // filter: =, >, <, >=, <=, between (min + max)
```

A máscara `#` representa um dígito. `0` representa um dígito obrigatório (zero à esquerda). `,` é separador decimal no locale pt-BR.

#### date / datetime

```php
Column::make('criado_em', 'date')
    ->label('Data de Criação')
    ->mask('DD/MM/YYYY')
    // filter: between (start + end), before, after, equals

Column::make('atualizado_em', 'datetime')
    ->label('Última Atualização')
    // filter: between, before, after, equals
```

#### boolean

```php
Column::make('ativo', 'boolean')
    ->label('Ativo')
    // filter: select Sim/Não
```

#### select

```php
Column::make('status', 'select')
    ->label('Status')
    ->options([
        1 => 'Ativo',
        2 => 'Inativo',
        3 => 'Pendente',
    ])
    // filter: dropdown com as opções acima
```

**Importante:** as opções são exibidas como labels no Web Component, mas o valor enviado nos filtros é a key do array (1, 2, 3). Certifique-se de que a coluna no banco contém esses valores.

#### Desabilitando sort/filter

```php
// Coluna calculada não pode ser ordenada
Column::make('total_vendas', 'number')
    ->label('Total de Vendas')
    ->sortable(false)        // sem clique no cabeçalho
    ->filterable(false)      // sem input de filtro
```

#### Coluna oculta por padrão

```php
Column::make('id', 'number')
    ->label('ID')
    ->visible(false)         // escondida, mas dados ainda chegam na API
```

O usuário pode reexibir via menu de colunas no Web Component.

---

## 4. ACTIONCOLUMN (BOTÕES DE AÇÃO)

### 4.1 ActionColumn::make()

```php
public static function make(string $key, array $actions): self
```

| Parâmetro | Descrição |
|---|---|
| `$key` | Identificador único da coluna de ações. Ex: `'acoes'`, `'actions'` |
| `$actions` | Array de definições de ação. Cada ação é um array `['key' => ..., 'label' => ..., 'danger' => ...]` |

### 4.2 Definição de ação

```php
[
    'key'    => 'editar',         // string — identificador enviado no evento
    'label'  => 'Editar',         // string — texto do botão
    'danger' => false,            // bool (opcional) — estilo visual de ação destrutiva (vermelho)
]
```

> ⚠️ **Action keys must be valid JavaScript identifiers** (`[a-zA-Z_][a-zA-Z0-9_]*`).
> Use underscores (`minha_acao`), not hyphens (`minha-acao`). This is required for
> `row.raw.can_{key}` property access and for the `event.detail.key` emitted by
> the Web Component.

### 4.3 label()

```php
ActionColumn::make('acoes', [...])->label('Opções')  // cabeçalho customizado
// Default: 'Actions'
```

### 4.4 Comportamento visual

| Nº de ações | Comportamento |
|---|---|
| **1 ação** | Botão único renderizado diretamente na linha |
| **2+ ações** | Ícone ⋯ (três pontos) que abre dropdown com todas as opções |
| **danger: true** | Texto vermelho no dropdown |

### 4.5 Exemplo completo

```php
use Rosiumdata\Laravel\ActionColumn;

ActionColumn::make('acoes', [
    ['key' => 'visualizar', 'label' => 'Visualizar'],
    ['key' => 'editar',     'label' => 'Editar'],
    ['key' => 'excluir',    'label' => 'Excluir', 'danger' => true],
])->label('Ações')
```

### 4.6 Conditional visibility (actionRules)

Define per-row visibility of actions using Laravel Gates, Policies, or any business logic. Actions not listed in the returned array are always visible.

Override `actionRules()` in your table class:

```php
use App\Models\NaoConformidade;

public function actionRules(mixed $row): array
{
    $user = auth()->user();

    return [
        'editar' => $user->can('update', $row),
        'excluir' => $user->can('delete', $row) && $row->status === 'pendente',
        // 'visualizar' not listed — always visible
    ];
}
```

**How it works:**

1. You return `['action_key' => true/false]` from `actionRules($row)`
2. The controller calls this method for every row and injects `can_{key}` fields into the JSON response (e.g. `can_editar: true`)
3. The JsGenerator automatically adds a `visible` callback to each action in the generated JS:

```js
{
    "key": "editar",
    "label": "Editar",
    "visible": "(row) => row.raw.can_editar"
}
```

4. The Web Component evaluates the callback per row — hiding or showing the button

**What you can do inside `actionRules()`:**

| Approach | Example |
|---|---|
| Laravel Policies | `$user->can('update', $row)` |
| Laravel Gates | `Gate::allows('edit-post', $row)` |
| Direct permission check | `$user->can('posts.edit')` |
| Business logic | `$row->status === 'pendente'` |
| Mixed | `$user->can('update', $row) && $row->user_id === $user->id` |

**Defaults and safety:**

- If `actionRules()` is not overridden (returns `[]`) → **all actions are always visible** (backwards compatible)
- If an action key is not in the returned array → **visible by default** (`true`)
- The method is called once per row — keep it lightweight (no N+1 queries)

---

## 5. EVENTOS E MANIPULADORES JS

### 5.1 O evento `action`

Quando o usuário clica num botão de ação, o Web Component dispara um `CustomEvent` do tipo `action`.

**Forma 1 — eventHandlers() na classe PHP (inline):**

```php
public function eventHandlers(): ?string
{
    return "el.addEventListener('action', ({ detail: { key, row } }) => {
        if (key === 'editar') {
            window.location.href = '/produtos/' + row.raw.id + '/editar'
        }
        if (key === 'excluir' && confirm('Excluir?')) {
            fetch('/api/produtos/' + row.raw.id, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content,
                    'Accept': 'application/json',
                }
            }).then(() => el.refresh?.())
        }
    })";
}
```

**Forma 2 — JavaScript externo (script separado):**

```js
document.querySelector('rosium-table[rosium="produtos"]')
    .addEventListener('action', (event) => {
        const { key, row } = event.detail

        if (key === 'editar') {
            window.location.href = `/produtos/${row.raw.id}/editar`
        }

        if (key === 'excluir' && confirm('Excluir?')) {
            fetch(`/api/produtos/${row.raw.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                }
            }).then(() => {
                document.querySelector('rosium-table[rosium="produtos"]').refresh()
            })
        }
    })
```

### 5.2 Payload do evento `event.detail`

```json
{
    "key": "editar",
    "row": {
        "raw": { "id": 1, "nome": "Coca-Cola", "preco": 5.99, "status": 1 },
        "display": { "id": "1", "nome": "Coca-Cola", "preco": "R$ 5,99", "status": "Ativo" }
    }
}
```

| Campo | Descrição |
|---|---|
| `key` | A chave da ação clicada |
| `row.raw` | Dados brutos do banco (valores originais) |
| `row.display` | Dados formatados (máscaras aplicadas, opções de select resolvidas) |

### 5.3 Método refresh()

O elemento `<rosium-table>` expõe um método `.refresh()` que recarrega os dados da API.

```js
// Após excluir um item, recarrega a tabela
el.refresh?.()
```

**Actions são gatilhos, nunca executores.** O pacote renderiza o botão e emite o evento. O que acontece depois (editar, excluir, redirecionar) é 100% responsabilidade sua.

---

## 6. API AUTO-GERADA (ROTAS E QUERY PARAMS)

### 6.1 Rota

Toda tabela registrada ganha automaticamente uma rota `GET`:

```
GET /{route_prefix}/{table_name}
```

Com o prefixo padrão:

```
GET /rosium-data/produtos
GET /rosium-data/clientes
```

### 6.2 Query params que o Web Component envia

```
GET /rosium-data/produtos?page=1&per_page=20
GET /rosium-data/produtos?sort=nome&page=1&per_page=20
GET /rosium-data/produtos?sort=-preco&page=1&per_page=20
GET /rosium-data/produtos?filter[nome][like]=coca&page=1&per_page=20
GET /rosium-data/produtos?filter[preco][gt]=50&page=1&per_page=20
GET /rosium-data/produtos?filter[preco][between]=10,100&page=1&per_page=20
GET /rosium-data/produtos?filter[nome][like]=coca&filter[preco][gt]=50&sort=nome&page=2&per_page=25
```

| Parâmetro | Descrição | Exemplo |
|---|---|---|
| `page` | Número da página (1-based) | `page=2` |
| `per_page` | Itens por página (até `maxPageSize()`) | `per_page=25` |
| `sort` | Ordenação: `coluna` = asc, `-coluna` = desc | `sort=-preco` |
| `filter[coluna][operador]` | Filtro por coluna + operador | `filter[nome][like]=coca` |

### 6.3 Resposta da API

```json
{
    "data": [
        { "id": 1, "nome": "Coca-Cola", "preco": 5.99, "status": 1 },
        { "id": 2, "nome": "Pepsi", "preco": 4.99, "status": 2 }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 100
    }
}
```

Este é o formato que o `LaravelAdapter` (do `@rosiumdata/core`) espera. O controller usa `$query->paginate()` do Eloquent, que já retorna exatamente esta estrutura.

---

## 7. OPERADORES DE FILTRO

### 7.1 Todos os operadores suportados

| Operador | SQL equivalente | Exemplo de query param | Uso típico |
|---|---|---|---|
| `eq` | `WHERE col = ?` | `filter[status][eq]=1` | Igualdade exata |
| `like` | `WHERE col LIKE '%?%'` | `filter[nome][like]=coca` | Busca textual (contém) |
| `starts_with` | `WHERE col LIKE '?%'` | `filter[nome][starts_with]=coca` | Começa com |
| `ends_with` | `WHERE col LIKE '%?'` | `filter[nome][ends_with]=cola` | Termina com |
| `gt` | `WHERE col > ?` | `filter[preco][gt]=50` | Maior que |
| `gte` | `WHERE col >= ?` | `filter[preco][gte]=50` | Maior ou igual |
| `lt` | `WHERE col < ?` | `filter[preco][lt]=100` | Menor que |
| `lte` | `WHERE col <= ?` | `filter[preco][lte]=100` | Menor ou igual |
| `before` | `WHERE col < ?` | `filter[data][before]=2024-12-31` | Antes de (datas) |
| `after` | `WHERE col > ?` | `filter[data][after]=2024-01-01` | Depois de (datas) |
| `between` | `WHERE col BETWEEN ? AND ?` | `filter[preco][between]=10,100` | Intervalo (string "min,max" ou array [min, max]) |

### 7.2 Operadores recomendados por tipo de coluna

| Tipo de coluna | Operadores naturais |
|---|---|
| `text` | `like`, `starts_with`, `ends_with`, `eq` |
| `number` | `eq`, `gt`, `gte`, `lt`, `lte`, `between` |
| `date` / `datetime` | `between`, `before`, `after`, `eq` |
| `boolean` | `eq` |
| `select` | `eq` |

### 7.3 Detalhes do `between`

O valor pode ser enviado como string `"min,max"` ou array `[min, max]`:

```
?filter[preco][between]=10,100
// ou
?filter[preco][between][]=10&filter[preco][between][]=100
```

- Se apenas `min` for enviado → `WHERE col >= min`
- Se apenas `max` for enviado → `WHERE col <= max`
- Se ambos → `WHERE col BETWEEN min AND max`

---

## 8. TRATAMENTO DE ERROS

O controller genérico tem tratamento de erro em 3 níveis:

### 8.1 Segurança de filtro

- **Coluna não existe em `columns()`:** filtro é ignorado silenciosamente
- **Operador inválido:** filtro é ignorado silenciosamente
- **Coluna com `filterable: false`:** filtro é rejeitado
- **Coluna com `sortable: false`:** ordenação é rejeitada
- **Valor vazio (`''`, `null`, `[]`):** filtro é ignorado

### 8.2 Erros SQL

- `QueryException` → HTTP 500 com `{"error": "Database query error."}`
- `RuntimeException` (tabela não encontrada) → HTTP 404 com `{"error": "Table [x] not found..."}`
- Qualquer outro `Throwable` → HTTP 500 com `{"error": "Internal server error."}`

### 8.3 Proteção de página/per_page

```php
// per_page sempre entre 1 e maxPageSize()
$perPage = max(1, min($request->per_page, $instance->maxPageSize()));

// page sempre >= 1
$page = max(1, $request->page);
```

---

## 9. CONFIGURAÇÃO

Publique o arquivo de configuração:

```bash
php artisan vendor:publish --tag=rosiumdata-config
```

### 9.1 Todas as chaves (`config/rosiumdata.php`)

```php
return [

    // Diretório onde as classes de tabela são armazenadas
    // O ServiceProvider auto-descobre classes aqui (recursivo)
    'path' => app_path('RosiumTables'),

    // Diretório onde os arquivos JS auto-gerados são escritos
    'js_path' => resource_path('js/rosium'),

    // Caminho do arquivo rosium-init.js (importa e inicializa todas as tabelas)
    'init_path' => resource_path('js/rosium-init.js'),

    // Prefixo da URL para as rotas da API
    'route_prefix' => 'rosium-data',

    // Middleware aplicado às rotas da API
    'middleware' => ['api'],

    // Auto-gerar JS em desenvolvimento (padrão: true em local, false em produção)
    'auto_generate_js' => env('APP_ENV') === 'local',

];
```

### 9.2 Middleware por caso de uso

```php
// API pública (sem auth)
'middleware' => ['api'],

// SPA com Sanctum
'middleware' => ['api', 'auth:sanctum'],

// Cookie/session (web)
'middleware' => ['web', 'auth'],

// Sanitize
'middleware' => ['web', 'auth:sanctum'],
```

### 9.3 `auto_generate_js`

Quando `true` (default em ambiente `local`), o ServiceProvider gera/atualiza os arquivos JS automaticamente a cada request (com throttle de 5 segundos). Em produção, desligue e use o comando manual:

```bash
php artisan rosium:generate-js
```

---

## 10. ARTISAN COMMANDS

### 10.1 make:rosium-table

```bash
php artisan make:rosium-table Produtos --model=Produto
```

**Argumentos:**

| Argumento | Descrição |
|---|---|
| `name` | Nome da tabela em StudlyCase. Ex: `Produtos`, `ClientesAtivos` |

**Opções:**

| Opção | Descrição |
|---|---|
| `--model=` | Model Eloquent para introspecção. Ex: `Produto` ou `App\Models\Produto` |
| `--path=` | Caminho customizado para a classe. Default: `app/RosiumTables/` |

**O que o comando faz:**

1. Cria `app/RosiumTables/{Name}Table.php` a partir do stub
2. Cria `resources/js/rosium/{name}.js` (auto-gerado — **nunca edite manualmente**)
3. Cria/atualiza `resources/js/rosium-init.js` (importa e inicializa todas as tabelas)
4. Se `--model=` for fornecido:
   - Lê o schema da tabela do banco via `Schema::getColumnListing()`
   - Detecta tipos automaticamente (ver seção 12)
   - Pré-preenche `Column::make()` com os tipos corretos
5. Registra a classe no ServiceProvider
6. Gera os arquivos JS

**Exemplos:**

```bash
# Tabela simples sem modelo
php artisan make:rosium-table Produtos

# Com introspecção de schema
php artisan make:rosium-table Produtos --model=Produto

# Com FQCN do modelo
php artisan make:rosium-table Produtos --model=App\\Models\\Produto

# Em subdiretório customizado
php artisan make:rosium-table Admin/Produtos --model=Produto --path=app/RosiumTables/Admin
```

### 10.2 rosium:generate-js

```bash
php artisan rosium:generate-js
```

Regenera **todos** os arquivos JavaScript de **todas** as tabelas registradas. Execute após:

- Adicionar/remover colunas de uma tabela
- Mudar tipo, label, máscara, ou opções de uma coluna
- Adicionar/remover ações em ActionColumn
- Alterar `defaultPageSize()`, `locale()`, `persistenceKey()`, `eventHandlers()`
- Fazer deploy (primeira vez em produção)

**O comando gera:**

1. `resources/js/rosium/{name}.js` — um arquivo por tabela
2. `resources/js/rosium-init.js` — arquivo único que importa e inicializa todas as tabelas

**Se nenhuma tabela for encontrada:**
```
WARN  No RosiumData tables found. Create one with: php artisan make:rosium-table
```

---

## 11. CONSULTAS AVANÇADAS

### 11.1 JOIN com outra tabela

```php
use Illuminate\Database\Eloquent\Builder;

public function query(): Builder
{
    return Produto::query()
        ->select(['produtos.*', 'categorias.nome as categoria_nome'])
        ->leftJoin('categorias', 'categorias.id', '=', 'produtos.categoria_id');
}

public function columns(): array
{
    return [
        Column::make('id', 'number')->label('ID'),
        Column::make('nome', 'text')->label('Produto'),
        Column::make('categoria_nome', 'text')->label('Categoria')->sortable(),
    ];
}

// Necessário para evitar "column ambiguo" nos filtros
public function qualifyColumn(string $key): string
{
    return match ($key) {
        'id'              => 'produtos.id',
        'nome'            => 'produtos.nome',
        'categoria_nome'  => 'categorias.nome',
        default           => $key,
    };
}
```

### 11.2 Subquery com DB::raw()

```php
use Illuminate\Support\Facades\DB;

public function query(): Builder
{
    return UserResponsavel::query()
        ->select([
            'users_responsavel.*',
            'users.name as nome',
            DB::raw('(SELECT COUNT(*) FROM atendimentos WHERE atendimentos.responsavel_id = users_responsavel.id) as total_atendimentos')
        ])
        ->leftJoin('users', 'users.id', '=', 'users_responsavel.user_id');
}

public function columns(): array
{
    return [
        Column::make('nome', 'text')->label('Responsável'),
        Column::make('total_atendimentos', 'number')
            ->label('Total de Atendimentos')
            ->sortable(false),      // subquery não pode ser ordenada
    ];
}
```

### 11.3 Pré-filtro (scope fixo)

Filtros aplicados em `query()` funcionam como **pré-filtros** — o usuário nunca vê dados fora desse escopo, independente dos filtros que aplicar:

```php
public function query(): Builder
{
    return Produto::query()
        ->where('ativo', true)            // só produtos ativos
        ->where('empresa_id', auth()->id()); // só da empresa do usuário logado
}
```

### 11.4 Ordenação padrão

Defina uma ordenação padrão no `query()`. Quando o usuário clicar em outro cabeçalho, o `ORDER BY` do controller é adicionado depois:

```php
public function query(): Builder
{
    return Produto::query()->orderBy('nome');
}
```

---

## 12. DETECÇÃO DE SCHEMA

Quando você usa `--model=Produto`, o comando `make:rosium-table` lê o schema da tabela e detecta tipos automaticamente.

### 12.1 Requisito

A detecção de tipo usa `Schema::getColumnType()` que depende de `doctrine/dbal`:

```bash
composer require doctrine/dbal
```

Sem `doctrine/dbal`, a detecção retorna `'text'` para todas as colunas.

### 12.2 Regras de detecção

| Condição | Tipo detectado |
|---|---|
| Coluna é `id` | `number` (com label 'ID') |
| Nome termina com `_id` | `select` |
| Nome termina com `_at` | `date` |
| Tipo DB: `bigint`, `integer`, `smallint`, `tinyint` | `number` |
| Tipo DB: `decimal`, `float`, `double` | `number` |
| Tipo DB: `boolean` | `boolean` |
| Tipo DB: `date`, `datetime`, `datetimetz` | `date` |
| Tipo DB: `time` | `text` |
| Tipo DB: `json`, `text`, `guid`, `blob` | `text` |
| Qualquer outro tipo | `text` |

### 12.3 Colunas ignoradas

Estas colunas são automaticamente puladas (não aparecem no `columns()` gerado):

- `created_at`
- `updated_at`
- `deleted_at`
- `password`
- `remember_token`

### 12.4 Máscara automática

Se a coluna for do tipo `number` e o nome contiver uma destas palavras, uma máscara `R$ #,##0.00` é adicionada automaticamente:

`preco`, `price`, `valor`, `value`, `custo`, `cost`, `total`, `saldo`, `balance`, `montante`, `amount`

Exemplo: uma coluna chamada `preco_unitario` (contém "preco") recebe máscara de moeda.

### 12.5 Label automática

O nome da coluna é convertido: underscores → espaços, ucwords.

- `criado_em` → label `Criado Em`
- `preco_unitario` → label `Preco Unitario`

---

## 13. ARQUIVOS JS GERADOS

### 13.1 Estrutura de saída

Após rodar `make:rosium-table` ou `rosium:generate-js`:

```
resources/js/
├── rosium/
│   ├── produtos.js          # configuração da tabela "produtos"
│   └── clientes.js          # configuração da tabela "clientes"
└── rosium-init.js           # importa e inicializa todas as tabelas
```

### 13.2 Conteúdo do arquivo de tabela (`produtos.js`)

```js
import { LaravelAdapter } from '@rosiumdata/core'

export function initProdutosTable() {
  const el = document.querySelector('rosium-table[rosium="produtos"]')
  if (!el) return

  el.columns = [
    { key: "id", type: "number", label: "ID", sortable: true, filterable: true, visible: true },
    { key: "nome", type: "text", label: "Produto", sortable: true, filterable: true, visible: true }
  ]
  el.pageSize = 20
  el.locale = 'pt-BR'
  el.adapter = new LaravelAdapter('/rosium-data/produtos', {
    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' }
  })
}
```

### 13.3 Conteúdo do init (`rosium-init.js`)

```js
// Auto-generated by RosiumData. Do not edit manually.
import '@rosiumdata/vanilla'
import '@rosiumdata/vanilla/theme/default.css'
import { initProdutosTable } from './rosium/produtos.js'
import { initClientesTable } from './rosium/clientes.js'

initProdutosTable()
initClientesTable()
```

### 13.4 Import no app.js

Basta um único import:

```js
// resources/js/app.js — NÃO precisa importar @rosiumdata/vanilla aqui
import './rosium-init.js'
```

O `rosium-init.js` já contém os imports de `@rosiumdata/vanilla` e `@rosiumdata/vanilla/theme/default.css`.

### 13.5 Geração condicional (write-if-changed)

O JsGenerator só sobrescreve o arquivo se o conteúdo mudou. Isso evita disparar HMR do Vite a cada request em desenvolvimento.

---

## 14. TROUBLESHOOTING

### "rosium-table is not a known element"

**Causa:** o Web Component não foi registrado no navegador.

**Solução:**
```js
// Verifique se rosium-init.js está sendo importado:
// resources/js/app.js
import './rosium-init.js'

// Ou registre manualmente:
import '@rosiumdata/vanilla'
```

### "404 Not Found — rosium-data/produtos"

**Causa:** a classe da tabela não foi descoberta pelo ServiceProvider.

**Solução (na ordem):**
1. A classe está em `app/RosiumTables/`?
2. A classe estende `Rosiumdata\Laravel\RosiumTable`?
3. O método `name()` retorna exatamente `'produtos'`?
4. Rode `composer dump-autoload`
5. Rode `php artisan rosium:generate-js`
6. Se criou a classe manualmente (sem artisan), limpe o cache: `php artisan config:clear`

### "500 Internal Server Error"

**Causa provável:** exceção SQL (coluna não existe, nome ambíguo em JOIN).

**Solução:**
1. Verifique os logs em `storage/logs/laravel.log`
2. Confirme que toda coluna em `columns()` existe na query
3. Se usa JOIN, implemente `qualifyColumn()` para resolver ambiguidade
4. Verifique se `filterable: false` está em colunas que não deveriam receber filtro

### Filtros não funcionam

**Causa:** a key da `Column::make()` não bate com o nome da coluna no banco.

**Solução:** `Column::make('nome', 'text')` gera `WHERE nome LIKE ...`. O nome no banco precisa ser exatamente `nome`. Se a query usa alias, implemente `qualifyColumn()`.

### "Function name must be a string" no console JS

**Causa:** `name()` contém caracteres inválidos para identificador JavaScript (ex: hífen).

**Solução:** use apenas letras, números e underscore. `'meus_produtos'` ✅, `'meus-produtos'` ❌.

### Colunas com máscara de moeda mas sem doctrine/dbal

**Causa:** `Schema::getColumnType()` requer `doctrine/dbal` para detecção de tipos.

**Solução:**
```bash
composer require doctrine/dbal
```

### JS não atualiza após mudar colunas

**Causa:** o JS é gerado uma vez e cached. Mudanças em `columns()` precisam de regeneração.

**Solução:**
```bash
php artisan rosium:generate-js
```

Em ambiente `local`, se `auto_generate_js` estiver `true` (default), a regeneração é automática.

---

## EXEMPLO COMPLETO (TUDO JUNTO)

```php
<?php

namespace App\RosiumTables;

use Rosiumdata\Laravel\RosiumTable;
use Rosiumdata\Laravel\Column;
use Rosiumdata\Laravel\ActionColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Models\Produto;

class ProdutosTable extends RosiumTable
{
    public static function name(): string
    {
        return 'produtos';
    }

    public function query(): Builder
    {
        return Produto::query()
            ->select([
                'produtos.*',
                'categorias.nome as categoria_nome',
                DB::raw('(SELECT COUNT(*) FROM vendas WHERE vendas.produto_id = produtos.id) as total_vendas'),
            ])
            ->leftJoin('categorias', 'categorias.id', '=', 'produtos.categoria_id')
            ->where('produtos.ativo', true);
    }

    public function columns(): array
    {
        return [
            Column::make('id', 'number')->label('ID'),

            Column::make('nome', 'text')
                ->label('Produto')
                ->sortable(),

            Column::make('categoria_nome', 'text')
                ->label('Categoria')
                ->sortable(),

            Column::make('preco', 'number')
                ->label('Preço')
                ->mask('R$ #,##0.00')
                ->alignment('right'),

            Column::make('estoque', 'number')
                ->label('Estoque')
                ->alignment('right'),

            Column::make('total_vendas', 'number')
                ->label('Total de Vendas')
                ->sortable(false)
                ->filterable(false),

            Column::make('status', 'select')
                ->label('Status')
                ->options([
                    1 => 'Ativo',
                    2 => 'Inativo',
                    3 => 'Pendente',
                ]),

            Column::make('criado_em', 'date')
                ->label('Data de Criação'),

            ActionColumn::make('acoes', [
                ['key' => 'visualizar', 'label' => 'Visualizar'],
                ['key' => 'editar', 'label' => 'Editar'],
                ['key' => 'excluir', 'label' => 'Excluir', 'danger' => true],
            ])->label('Ações'),
        ];
    }

    public function defaultPageSize(): int
    {
        return 25;
    }

    public function maxPageSize(): int
    {
        return 500;
    }

    public function locale(): string
    {
        return 'pt-BR';
    }

    public function persistenceKey(): string
    {
        return 'produtos';
    }

    public function qualifyColumn(string $key): string
    {
        return match ($key) {
            'id'              => 'produtos.id',
            'nome'            => 'produtos.nome',
            'categoria_nome'  => 'categorias.nome',
            'preco'           => 'produtos.preco',
            'estoque'         => 'produtos.estoque',
            'status'          => 'produtos.status',
            'criado_em'       => 'produtos.criado_em',
            default           => $key,
        };
    }

    public function eventHandlers(): ?string
    {
        return "el.addEventListener('action', ({ detail: { key, row } }) => {
            if (key === 'visualizar') window.location.href = '/produtos/' + row.raw.id
            if (key === 'editar') window.location.href = '/produtos/' + row.raw.id + '/editar'
            if (key === 'excluir' && confirm('Excluir este produto?')) {
                fetch('/api/produtos/' + row.raw.id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content,
                        'Accept': 'application/json',
                    }
                }).then(() => el.refresh?.())
            }
        })";
    }
}
```

Blade:

```blade
@extends('layouts.app')

@section('content')
    <h1>Produtos</h1>
    <rosium-table rosium="produtos" page-size="25" />
@endsection
```

app.js:

```js
import './rosium-init.js'
```

---

> **Documentos relacionados:** `INSTALLATION.md` (instalação passo a passo), `README.md` (visão geral).
