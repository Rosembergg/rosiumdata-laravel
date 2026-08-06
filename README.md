<p align="center">
  <h1 align="center">rosiumdata/laravel</h1>
  <p align="center">
    RosiumData for Laravel — one class, one Blade tag. Zero JavaScript.
  </p>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/php-^8.1-blue" alt="PHP">
  <img src="https://img.shields.io/badge/laravel-^10.0|^11.0|^12.0-red" alt="Laravel">
  <img src="https://img.shields.io/badge/license-MIT-green" alt="License">
</p>

---

## What is it?

A Laravel package that eliminates all the boilerplate of using RosiumData in Blade. Define your table in **one PHP class** and use it with **one Blade tag.** No JavaScript. No controllers. No manual routes.

```php
// app/RosiumTables/ProdutosTable.php
use Rosiumdata\Laravel\RosiumTable;
use Rosiumdata\Laravel\Column;
use Illuminate\Database\Eloquent\Builder;

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
            Column::make('nome', 'text')->label('Produto'),
            Column::make('preco', 'number')
                ->label('Preço')
                ->mask('R$ #,##0.00'),
            Column::make('status', 'select')
                ->label('Status')
                ->options([1 => 'Ativo', 2 => 'Inativo']),
        ];
    }
}
```

```blade
<rosium-table rosium="produtos" page-size="25" />
```

**That's it.** Filters, sorting, pagination, and action buttons — all working. Zero JavaScript.

---

## Why it exists

Using RosiumData in Laravel Blade typically requires writing:

- A controller (50 lines of filter/sort/paginate logic)
- A route (manual registration)
- A JavaScript file (`tables/produtos.js` with columns + adapter config)
- An `app.js` import
- A Blade tag

This package reduces all of that to **one PHP class + one Blade tag.**

It's the same experience as PowerGrid — but without Livewire. The table is a native Web Component (`<rosium-table>`) that runs in the browser. No server round-trips for rendering.

---

## Installation

```bash
composer require rosiumdata/laravel
npm install rosiumdata
```

Add to your Blade layout:

```blade
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

Register the Web Component in `resources/js/app.js`:

```js
import './rosium-init.js'
```

---

## Quick start

### 1. Create your first table

```bash
php artisan make:rosium-table Produtos --model=Produto
```

This creates:
- `app/RosiumTables/ProdutosTable.php` — your table class
- `resources/js/rosium/produtos.js` — auto-generated JS (never edit manually)
- Reads your database schema and pre-fills column types

### 2. Edit the table class

```php
// app/RosiumTables/ProdutosTable.php
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
        Column::make('nome', 'text')->label('Produto')->sortable(),
        Column::make('categoria_nome', 'text')->label('Categoria'),
        Column::make('preco', 'number')
            ->label('Preço')
            ->mask('R$ #,##0.00'),
        Column::make('estoque', 'number')->label('Estoque'),
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
```

> ⚠️ **After any change to your table class (columns, labels, types, masks,
> actions, page size, locale), regenerate the JavaScript:**
> ```bash
> php artisan rosium:generate-js
> ```

### 3. Use in Blade

```blade
<rosium-table rosium="produtos" page-size="25" />
```

---

## How it works

```
┌─ Browser ────────────────────────────────────┐
│                                                │
│  <rosium-table rosium="produtos" />            │
│         │                                      │
│         ▼                                      │
│  @rosiumdata/vanilla (Web Component)           │
│         │                                      │
│         ▼                                      │
│  @rosiumdata/core (Data Engine)               │
│         │                                      │
│         ▼                                      │
│  LaravelAdapter.fetch()                        │
│         │                                      │
└─────────┼──────────────────────────────────────┘
          │
          ▼
┌─ Laravel ─────────────────────────────────────┐
│                                                │
│  GET /rosium-data/produtos                    │
│         │                                      │
│         ▼                                      │
│  RosiumTableController (auto-generated)       │
│         │                                      │
│         ▼                                      │
│  ProdutosTable::query() (YOUR code)           │
│         │                                      │
│         ▼                                      │
│  { data: [...], meta: { total: N } }          │
│                                                │
└────────────────────────────────────────────────┘
```

**The controller is generic.** One controller handles ALL tables. You never write a controller. You never register a route. You define `query()` and `columns()` — the package does the rest.

---

## Key features

### 🧩 Eloquent-first
Your `query()` returns a `Builder`. Use `select()`, `join()`, `leftJoin()`, `where()`, subqueries — anything Eloquent supports.

### 🔌 Automatic API
Every table gets a `GET /rosium-data/{table}` endpoint automatically. Filters, sorting, and pagination are applied on top of your query. Zero manual wiring.

### 📦 Schema auto-detection
`php artisan make:rosium-table Produtos --model=Produto` reads your database columns and pre-fills types, labels, and masks. No guesswork.

### 🚨 Error-safe
Invalid filters (column that doesn't exist, malformed value) are silently ignored instead of throwing SQL exceptions. The controller wraps everything in try/catch.

### 🔐 Row-level permissions
Define `actionRules($row)` — hide or show action buttons per row. Use Laravel Policies, Gates, or any business logic. Same API as PowerGrid.

### 🎨 Same theme as Nuxt
The Web Component uses the same CSS variables (`--rosium-*`) and classes (`.rosium-*`) as the Nuxt renderer. Same visual identity everywhere.

---

## Column types

| Type | Filter | What it renders | Options |
|---|---|---|---|
| `text` | contains, equals, starts with, ends with | `<input type="text">` | — |
| `number` | =, >, <, >=, <=, between | `<input type="number">` (min + max) | — |
| `date` | between, before, after, equals | `<input type="date">` (start + end) | — |
| `datetime` | same as date | `<input type="datetime-local">` | — |
| `boolean` | equals | `<select>` (Yes/No) | — |
| `select` | equals | `<select>` (dropdown) | `options([1 => 'Ativo', 2 => 'Inativo'])` |
| `action` | — | Button or ⋯ menu | `ActionColumn::make('key', [...])` |

---

## Actions (buttons)

Actions are **triggers, never executors.** The Web Component emits a `detail` event with `{ key, row }` — you decide what to do:

```js
document.querySelector('rosium-table[rosium="produtos"]')
  .addEventListener('action', (event) => {
    const { key, row } = event.detail
    if (key === 'editar') {
      window.location.href = `/produtos/${row.raw.id}/editar`
    }
    if (key === 'excluir' && confirm('Excluir?')) {
      fetch(`/api/produtos/${row.raw.id}`, { method: 'DELETE' })
    }
  })
```

---

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=rosiumdata-config
```

`config/rosiumdata.php`:

```php
return [
    'path' => app_path('RosiumTables'),
    'js_path' => resource_path('js/rosium'),
    'route_prefix' => 'rosium-data',
    'middleware' => ['api'],
];
```

---

## Commands

| Command | What it does |
|---|---|
| `make:rosium-table {name} --model={model}` | Create table class, detect schema, generate JS |
| `rosium:generate-js` | Regenerate all JS files from PHP classes |

---

## Documentation

| Document | What it covers |
|---|---|
| [USAGE.md](docs/USAGE.md) | Complete API reference — `RosiumTable`, `Column`, `ActionColumn`, controller |
| [INSTALLATION.md](docs/INSTALLATION.md) | Step-by-step setup, requirements, troubleshooting |
| [PRESENTATION.md](docs/PRESENTATION.md) | **How it works** — simple explanation, no jargon |

---

## Requirements

- PHP 8.1+
- Laravel 10 or 11
- npm package `rosiumdata` (installs `@rosiumdata/vanilla` Web Component)
- Database with Eloquent models

---

## License

MIT

---

## Related

- [RosiumData npm](https://www.npmjs.com/package/rosiumdata) — JavaScript packages

---

<p align="center">
  <sub>Built for Laravel developers who want the PowerGrid experience — without the Livewire dependency.</sub>
</p>
