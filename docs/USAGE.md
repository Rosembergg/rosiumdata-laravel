# USAGE.md — rosiumdata/laravel

> Complete API reference for the Laravel integration package.

---

## INDEX

1. [RosiumTable base class](#1-rosiumtable-base-class)
2. [Column helper](#2-column-helper)
3. [ActionColumn](#3-actioncolumn)
4. [Events and Actions](#4-events-and-actions)
5. [Configuration](#5-configuration)
6. [Auto-generated API](#6-auto-generated-api)
7. [Artisan commands](#7-artisan-commands)
8. [Advanced queries](#8-advanced-queries)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. ROSIUMTABLE BASE CLASS

All table classes extend `Rosiumdata\Laravel\RosiumTable`.

### Required methods

```php
abstract class RosiumTable
{
    /** Unique identifier. Used in Blade tag and route. */
    abstract public static function name(): string;

    /** Eloquent query builder. Supports select, join, where, subqueries. */
    abstract public function query(): Builder;

    /** Column definitions. Array of Column or ActionColumn. */
    abstract public function columns(): array;
}
```

### Optional methods

```php
/** Items per page. Default: 20 */
public function defaultPageSize(): int
{
    return 20;
}

/** Locale for number/date formatting. Default: 'pt-BR' */
public function locale(): string
{
    return 'pt-BR';
}

/** localStorage persistence key. null = disabled */
public function persistenceKey(): ?string
{
    return null;  // Set to 'produtos' to enable persistence
}
```

### Complete example

```php
<?php

namespace App\RosiumTables;

use Rosiumdata\Laravel\RosiumTable;
use Rosiumdata\Laravel\Column;
use Rosiumdata\Laravel\ActionColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Produto;

class ProdutosTable extends RosiumTable
{
    public static function name(): string
    {
        return 'produtos';
    }

    public function query(): Builder
    {
        return Produto::query();
    }

    public function columns(): array
    {
        return [
            Column::make('id', 'number')->label('ID'),
            Column::make('nome', 'text')->label('Produto')->sortable(),
            Column::make('preco', 'number')
                ->label('Preço')
                ->mask('R$ #,##0.00'),
            Column::make('status', 'select')
                ->label('Status')
                ->options([1 => 'Ativo', 2 => 'Inativo', 3 => 'Pendente']),
            Column::make('criado_em', 'date')->label('Data'),
            ActionColumn::make('acoes', [
                ['key' => 'editar', 'label' => 'Editar'],
                ['key' => 'excluir', 'label' => 'Excluir', 'danger' => true],
            ]),
        ];
    }

    public function defaultPageSize(): int
    {
        return 25;
    }

    public function persistenceKey(): string
    {
        return 'produtos';
    }
}
```

---

## 2. COLUMN HELPER

Fluent API for defining columns.

### `Column::make(string $key, string $type)`

Creates a new column. `$type` must be one of: `text`, `number`, `date`, `datetime`, `boolean`, `select`, `action`.

### Available methods

| Method | Description | Example |
|---|---|---|
| `->label(string $label)` | Header text (default: ucfirst of key) | `->label('Preço')` |
| `->mask(string $mask)` | Display mask for number/date columns | `->mask('R$ #,##0.00')` |
| `->sortable(bool $sortable = true)` | Allow sorting (default: true) | `->sortable(false)` |
| `->filterable(bool $filterable = true)` | Show filter input (default: true) | `->filterable(false)` |
| `->visible(bool $visible = true)` | Column visible by default | `->visible(false)` |
| `->options(array $options)` | Options for select columns | `->options([1 => 'Ativo', 2 => 'Inativo'])` |
| `->alignment(string $alignment)` | Cell alignment | `->alignment('right')` |

### Chaining example

```php
Column::make('preco', 'number')
    ->label('Preço Unitário')
    ->mask('R$ #,##0.00')
    ->sortable()
    ->filterable()
    ->alignment('right')
```

---

## 3. ACTIONCOLUMN

Defines a column of action buttons.

### `ActionColumn::make(string $key, array $actions)`

`$actions` is an array of action definitions:

```php
[
    ['key' => 'editar', 'label' => 'Editar'],
    ['key' => 'excluir', 'label' => 'Excluir', 'danger' => true],
]
```

| Field | Type | Description |
|---|---|---|
| `key` | string | Action identifier (sent in the event) |
| `label` | string | Button text |
| `danger` | bool? | Visual danger styling (red) — optional |

### Label

```php
// Default label — 'Actions'
ActionColumn::make('acoes', [...])

// Custom label
ActionColumn::make('acoes', [...])->label('Opções')
```

### Visual behavior
- **1 action:** single button rendered directly on the row
- **2+ actions:** ⋯ icon that opens a dropdown with all options
- **`danger: true`:** red text in the dropdown

---

## 4. EVENTS AND ACTIONS

Actions are **triggers, never executors.** The Web Component emits a JavaScript `CustomEvent` with the action data. The package does NOT implement destroy/create/update — that's your responsibility.

### Capturing action clicks

```js
// In a script file loaded on the page
document.querySelector('rosium-table[rosium="produtos"]')
  .addEventListener('action', (event) => {
    const { key, row } = event.detail
    // row.raw = raw database values
    // row.display = formatted display values

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
        // Refresh the table data
        document.getElementById('rosium-table-produtos').refresh()
      })
    }
  })
```

### Event payload (`event.detail`)

```json
{
  "key": "editar",
  "row": {
    "raw": { "id": 1, "nome": "Coca-Cola", "preco": 5.99 },
    "display": { "id": "1", "nome": "Coca-Cola", "preco": "R$ 5,99" }
  }
}
```

---

## 5. CONFIGURATION

Publish the config:

```bash
php artisan vendor:publish --tag=rosiumdata-config
```

### `config/rosiumdata.php`

```php
return [

    // Directory where table classes are stored
    'path' => app_path('RosiumTables'),

    // Directory where auto-generated JS files are written
    'js_path' => resource_path('js/rosium'),

    // URL prefix for the auto-generated API routes
    'route_prefix' => 'rosium-data',

    // Middleware applied to the auto-generated routes
    'middleware' => ['api'],

];
```

### Route middleware

The default `['api']` middleware applies API rate limiting and Sanctum auth if configured. Change to `['web', 'auth']` for cookie-based auth:

```php
'middleware' => ['web', 'auth'],
```

---

## 6. AUTO-GENERATED API

Every registered table gets a `GET` endpoint automatically.

### Route format

```
GET /{prefix}/{table}
```

With default config:
```
GET /rosium-data/produtos
```

### Query parameters the table sends automatically

```
GET /rosium-data/produtos?page=1&per_page=20
GET /rosium-data/produtos?sort=nome&page=1&per_page=20
GET /rosium-data/produtos?sort=-preco&page=1&per_page=20
GET /rosium-data/produtos?filter[nome][like]=coca&page=1&per_page=20
GET /rosium-data/produtos?filter[preco][gt]=50&page=1&per_page=20
GET /rosium-data/produtos?filter[preco][between]=10,100&page=1&per_page=20
GET /rosium-data/produtos?filter[nome][like]=coca&filter[preco][gt]=50&sort=nome&page=2&per_page=25
```

### Response format

```json
{
  "data": [
    { "id": 1, "nome": "Coca-Cola", "preco": 5.99, "status": 1 },
    { "id": 2, "nome": "Pepsi", "preco": 4.99, "status": 2 }
  ],
  "meta": {
    "current_page": 1,
    "total": 100,
    "per_page": 20
  }
}
```

### Filter operators

| Operator | SQL | Example query param | Example value |
|---|---|---|---|
| `eq` | `WHERE col = ?` | `filter[status][eq]` | `1` |
| `like` | `WHERE col LIKE '%?%'` | `filter[nome][like]` | `coca` |
| `gt` | `WHERE col > ?` | `filter[preco][gt]` | `50` |
| `gte` | `WHERE col >= ?` | `filter[preco][gte]` | `50` |
| `lt` | `WHERE col < ?` | `filter[preco][lt]` | `100` |
| `lte` | `WHERE col <= ?` | `filter[preco][lte]` | `100` |
| `between` | `WHERE col BETWEEN ? AND ?` | `filter[preco][between]` | `10,100` |

### Error safety

- **Invalid column names** (not in `columns()`) are silently ignored
- **Invalid operator** is silently ignored
- **Partial `between`** (only min or only max) uses `>=` or `<=` instead of failing
- **SQL exceptions** return 500 with error message instead of crashing
- **Missing table** returns 404

---

## 7. ARTISAN COMMANDS

### `make:rosium-table`

```bash
php artisan make:rosium-table Produtos --model=Produto
```

**What it does:**

1. Creates `app/RosiumTables/ProdutosTable.php` from a stub
2. Creates `resources/js/rosium/produtos.js` (auto-generated JS — never edit manually)
3. If `--model=Produto` is provided:
   - Reads the `produtos` table schema from the database
   - Auto-detects column types:
     - `id` → `number`
     - `varchar`/`text`/`string` → `text`
     - `decimal`/`float`/`double`/`integer` → `number`
     - `timestamp`/`datetime` → `date`
     - `boolean` → `boolean`
     - Columns ending with `_id` → `select`
   - Skips: `created_at`, `updated_at`, `deleted_at`, `password`, `remember_token`
4. Registers the table in the ServiceProvider
5. Generates the JS file for the browser

**Arguments:**

| Argument | Description |
|---|---|
| `name` | Table name (StudlyCase, e.g. `Produtos`) |

**Options:**

| Option | Description |
|---|---|
| `--model=` | Eloquent model to read schema from (e.g. `Produto`) |

### `rosium:generate-js`

```bash
php artisan rosium:generate-js
```

Regenerates ALL JavaScript files from ALL registered table classes. Run this after:
- Adding a new column to an existing table class
- Changing a column type, label, or mask
- Deploying to production (first time)

**This is only needed when table definitions change.** The JS is generated once and cached — it doesn't change at runtime.

---

## 8. ADVANCED QUERIES

### JOIN with another table

```php
public function query(): Builder
{
    return Produto::query()
        ->select(['produtos.*', 'categorias.nome as categoria_nome'])
        ->leftJoin('categorias', 'categorias.id', '=', 'produtos.categoria_id');
}
```

```php
public function columns(): array
{
    return [
        // ...
        Column::make('categoria_nome', 'text')->label('Categoria'),
    ];
}
```

### Subquery

```php
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
```

### Scopes and pre-filtering

```php
public function query(): Builder
{
    return Produto::query()->where('ativo', true);
}
```

This pre-filters every request — the user's table always shows only active products.

### Custom sort logic

```php
public function columns(): array
{
    return [
        Column::make('total', 'number')
            ->label('Total de Vendas')
            ->sortable(false),  // disable sorting for computed columns
    ];
}
```

Set `->sortable(false)` for columns that don't map directly to a database column. The user won't be able to click the header to sort.

---

## 9. TROUBLESHOOTING

### "rosium-table is not a known element"

**Cause:** `@rosiumdata/vanilla` Web Component is not registered.

**Fix:**
```js
// resources/js/app.js
import '@rosiumdata/vanilla'
```

### "404 Not Found — rosium-data/produtos"

**Cause:** Table class not discovered by the ServiceProvider.

**Fix:**
1. Check the class is in `app/RosiumTables/`
2. Check the class extends `Rosiumdata\Laravel\RosiumTable`
3. Run `php artisan rosium:generate-js`
4. Run `composer dump-autoload`

### "500 Internal Server Error"

**Cause:** SQL exception, invalid column name, or malformed filter.

**Fix:** Check Laravel logs (`storage/logs/laravel.log`). Common causes:
- Column name in `columns()` doesn't exist in the database
- Filter operator not supported
- `between` with non-numeric values

### Filters not applying

**Cause:** Column key in `columns()` doesn't match the database column.

**Fix:** `Column::make('nome', 'text')` uses `nome` as the key. The controller applies `where('nome', ...)`. Make sure the key matches the actual column name in the query.

### "Function name must be a string" or JS SyntaxError

**Cause:** `name()` contains characters that aren't valid JavaScript identifiers (e.g. `meus-produtos`).

**Fix:** Use underscores, not hyphens: `meus_produtos`, not `meus-produtos`.
