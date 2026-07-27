# LARAVEL.md — RosiumData no Laravel Blade

> Como usar a RosiumData em projetos Laravel com Blade puro. Sem Vue, sem Nuxt,
> sem framework JavaScript adicional. Apenas o Web Component `<rosium-table>`.

---

## ÍNDICE

1. [Instalação](#1-instalação)
2. [Primeira tabela em Blade](#2-primeira-tabela-em-blade)
3. [Arquivo separado (estilo Livewire)](#21-arquivo-separado-estilo-livewire)
4. [Conectando no backend Laravel](#3-conectando-no-backend-laravel)
4. [Colunas e tipos](#4-colunas-e-tipos)
5. [Filtros, ordenação e paginação](#5-filtros-ordenação-e-paginação)
6. [Actions (botões de ação)](#6-actions-botões-de-ação)
7. [Estilização e tema](#7-estilização-e-tema)
8. [Exemplo completo](#8-exemplo-completo)

---

## 1. INSTALAÇÃO

```bash
npm install rosiumdata
```

No seu `vite.config.js` (Laravel com Vite):

```js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js'],
      refresh: true,
    }),
  ],
})
```

No `resources/js/app.js`:

```js
import '@rosiumdata/vanilla'            // registra <rosium-table>
import '@rosiumdata/vanilla/theme/default.css'  // tema padrão
```

No seu layout Blade (`resources/views/layouts/app.blade.php`):

```blade
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

---

## 2. PRIMEIRA TABELA EM BLADE

Crie o arquivo JS com a configuração da tabela:

```js
// resources/js/tables/produtos.js
import { column, LocalAdapter } from '@rosiumdata/core'

export function initProdutosTable() {
  const table = document.getElementById('tabela-produtos')
  if (!table) return

  table.columns = [
    column('id',     { type: 'number',  label: 'ID' }),
    column('nome',   { type: 'text',    label: 'Nome' }),
    column('preco',  { type: 'number',  label: 'Preço' }),
    column('status', { type: 'select',  label: 'Status', options: { 1: 'Ativo', 2: 'Inativo' } }),
  ]
  table.adapter = new LocalAdapter([
    { id: 1, nome: 'Coca-Cola', preco: 5.99, status: 1 },
    { id: 2, nome: 'Pepsi',     preco: 4.99, status: 2 },
    { id: 3, nome: 'Guaraná',   preco: 3.50, status: 1 },
  ])
}
```

Importe no `app.js`:

```js
// resources/js/app.js
import '@rosiumdata/vanilla'
import '@rosiumdata/vanilla/theme/default.css'

import { initProdutosTable } from './tables/produtos'

initProdutosTable()
```

O Blade (só HTML):

```blade
{{-- resources/views/produtos.blade.php --}}
@extends('layouts.app')

@section('content')
  <rosium-table id="tabela-produtos"></rosium-table>
@endsection
```

Nada de `<script>` inline. O Vite processa `app.js`, resolve os imports, e o
componente é registrado. Toda a lógica fica em `resources/js/tables/`.

**Resultado:** tabela com filtro, ordenação e paginação — em Blade puro. Zero Vue.

Estrutura final:

```
resources/
├── views/
│   └── produtos.blade.php          ← só HTML (id="tabela-produtos")
└── js/
    ├── app.js                      ← registra o componente
    └── tables/
        └── produtos.js             ← columns + adapter
```

Mesmo princípio do Livewire: **Blade limpo, lógica isolada em arquivo próprio.**

### 2.1 Exemplo completo com LaravelAdapter + Actions

Use o `LaravelAdapter` quando os dados vêm do backend Laravel (filtro,
ordenação e paginação processados no servidor):

**O arquivo JS:**

```js
// resources/js/tables/produtos.js
import { column, actionColumn, LaravelAdapter } from '@rosiumdata/core'

export function initProdutosTable() {
  const table = document.getElementById('tabela-produtos')
  if (!table) return // página não carregou essa tabela

  table.columns = [
    column('id',       { type: 'number', label: 'ID' }),
    column('nome',     { type: 'text',   label: 'Produto' }),
    column('preco',    { type: 'number', label: 'Preço', mask: 'R$ #,##0,00' }),
    column('estoque',  { type: 'number', label: 'Estoque' }),
    column('status',   { type: 'select', label: 'Status', options: { 1: 'Ativo', 2: 'Inativo', 3: 'Pendente' } }),
    column('criado_em', { type: 'date', label: 'Data' }),
    actionColumn('acoes', [
      { key: 'editar',  label: 'Editar' },
      { key: 'excluir', label: 'Excluir', danger: true },
    ]),
  ]

  table.adapter = new LaravelAdapter('/api/produtos', {
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    }
  })

  table.addEventListener('action', ({ detail: { key, row } }) => {
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content
    if (key === 'editar') window.location.href = `/produtos/${row.raw.id}/editar`
    if (key === 'excluir' && confirm('Excluir?')) {
      fetch(`/api/produtos/${row.raw.id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf() }
      }).then(() => table.refresh())
    }
  })
}
```

**O app.js (registra o componente 1 vez, inicializa as tabelas):**

```js
// resources/js/app.js
import '@rosiumdata/vanilla'
import '@rosiumdata/vanilla/theme/default.css'

import { initProdutosTable } from './tables/produtos'
import { initClientesTable } from './tables/clientes'

// Cada init roda só se o elemento existir na página
initProdutosTable()
initClientesTable()
```

**O Vite (já configurado no Laravel):**

```js
// vite.config.js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js'],
      refresh: true,
    }),
  ],
})
```

**Comparação com Livewire:**

| Livewire | RosiumData Vanilla |
|---|---|
| Classe PHP `ProdutosTable` | Arquivo JS `tables/produtos.js` |
| Blade `<livewire:produtos-table />` | Blade `<rosium-table id="tabela-produtos">` |
| `render()` retorna view | `columns` + `adapter` configuram o componente |
| Backend processa requisição | `LaravelAdapter` chama a API, servidor processa |

Mesmo princípio: **Blade limpo, lógica isolada em arquivo próprio.**

---

## 3. CONECTANDO NO BACKEND LARAVEL

### 3.1 O controller

```php
// app/Http/Controllers/ProdutoController.php
public function index(Request $request)
{
    $query = Produto::query();

    // Filtros
    foreach ($request->input('filter', []) as $coluna => $operadores) {
        foreach ($operadores as $operador => $valor) {
            match ($operador) {
                'gt'      => $query->where($coluna, '>', $valor),
                'gte'     => $query->where($coluna, '>=', $valor),
                'lt'      => $query->where($coluna, '<', $valor),
                'lte'     => $query->where($coluna, '<=', $valor),
                'eq'      => $query->where($coluna, $valor),
                'like'    => $query->where($coluna, 'like', "%{$valor}%"),
                'between' => $query->whereBetween($coluna, $valor),
                default   => null,
            };
        }
    }

    // Ordenação
    if ($sort = $request->input('sort')) {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $coluna = ltrim($sort, '-');
        $query->orderBy($coluna, $direction);
    }

    return $query->paginate($request->input('per_page', 20));
}
```

### 3.2 A rota

```php
Route::get('/api/produtos', [ProdutoController::class, 'index']);
```

### 3.3 O componente

```js
// resources/js/tables/produtos.js
import { column, LaravelAdapter } from '@rosiumdata/core'

export function initProdutosTable() {
  const table = document.getElementById('tabela-produtos')
  if (!table) return

  table.columns = [
    column('id',       { type: 'number',  label: 'ID' }),
    column('nome',     { type: 'text',    label: 'Produto' }),
    column('preco',    { type: 'number',  label: 'Preço', mask: 'R$ #,##0.00' }),
    column('estoque',  { type: 'number',  label: 'Estoque' }),
    column('status',   { type: 'select',  label: 'Status', options: { 1: 'Ativo', 2: 'Inativo', 3: 'Pendente' } }),
    column('criado_em', { type: 'date',   label: 'Data' }),
  ]
  table.adapter = new LaravelAdapter('/api/produtos')
}
```

O Blade:

```blade
<rosium-table id="tabela-produtos"></rosium-table>
```

Importe `initProdutosTable()` no `app.js` como nos exemplos anteriores.

O `LaravelAdapter` faz fetch para `/api/produtos?filter[preco][gt]=50&sort=nome&page=1&per_page=20` automaticamente. O servidor responde com o `paginate()` do Laravel — exatamente o formato esperado.

---

## 4. COLUNAS E TIPOS

Mesma API do Core. Consulte `USAGE.md` para referência completa.

```js
import { column } from '@rosiumdata/core'

// Texto
column('nome', { type: 'text', label: 'Nome' })

// Número com máscara
column('preco', { type: 'number', label: 'Preço', mask: 'R$ #,##0.00' })

// Data (formato DD/MM/AAAA — default pt-BR)
column('criado_em', { type: 'date', label: 'Data' })

// Seleção (enum)
column('status', {
  type: 'select',
  label: 'Status',
  options: { 1: 'Ativo', 2: 'Inativo' }
})

// Booleano
column('ativo', { type: 'boolean', label: 'Ativo' })

// Moeda em dólar (sobrescreve locale)
column('preco_usd', {
  type: 'number',
  label: 'Preço (USD)',
  locale: 'en-US',
  currency: 'USD',
  mask: '$ #,##0.00'
})
```

---

## 5. FILTROS, ORDENAÇÃO E PAGINAÇÃO

**Tudo automático.** O Web Component já renderiza:

- **Filtros:** inputs por tipo de coluna (texto, número min/max, data início/fim, select, booleano Sim/Não)
- **Ordenação:** clique no cabeçalho alterna asc/desc
- **Paginação:** botões Previous/Next, "Página X de Y"

Zero código adicional — funciona out-of-the-box.

---

## 6. ACTIONS (BOTÕES DE AÇÃO)

```js
// resources/js/tables/produtos.js
import { column, actionColumn, LaravelAdapter } from '@rosiumdata/core'

export function initProdutosTable() {
  const table = document.getElementById('tabela-produtos')
  if (!table) return

  table.columns = [
    column('id',   { type: 'number', label: 'ID' }),
    column('nome', { type: 'text',   label: 'Nome' }),
    actionColumn('acoes', [
      { key: 'editar',  label: 'Editar' },
      { key: 'excluir', label: 'Excluir', danger: true },
    ]),
  ]
  table.adapter = new LaravelAdapter('/api/produtos')

  // Capturar clique da action
  table.addEventListener('action', (event) => {
    const { key, row } = event.detail
    if (key === 'editar') {
      window.location.href = `/produtos/${row.raw.id}/editar`
    } else if (key === 'excluir') {
      if (confirm('Excluir este produto?')) {
        fetch(`/api/produtos/${row.raw.id}`, { method: 'DELETE' })
          .then(() => table.refresh())
      }
    }
  })
}
```

O Blade:

```blade
<rosium-table id="tabela-produtos"></rosium-table>
```

---

## 7. ESTILIZAÇÃO E TEMA

### Tema padrão (já incluído)

```js
import '@rosiumdata/vanilla/theme/default.css'
```

### Customizando

Mesmas variáveis CSS do Nuxt:

```css
:root {
  --rosium-primary: #1c203f;
  --rosium-accent:  #65ba88;
  --rosium-light:   #cde9f2;
  --rosium-success: #66b32e;
}
```

Coloque no seu `resources/css/app.css` ou diretamente no Blade:

```blade
<style>
  :root {
    --rosium-primary: #6d28d9;
    --rosium-accent:  #f59e0b;
    --rosium-row-height: 44px;
  }
</style>
```

Para o guia completo de estilização, veja `THEMING.md`.

---

## 8. EXEMPLO COMPLETO

```js
// resources/js/tables/produtos.js
import { column, actionColumn, LaravelAdapter } from '@rosiumdata/core'

export function initProdutosTable() {
  const table = document.getElementById('tabela-produtos')
  if (!table) return

  table.columns = [
    column('id',         { type: 'number',  label: 'ID' }),
    column('nome',       { type: 'text',    label: 'Produto' }),
    column('categoria',  { type: 'select',  label: 'Categoria', options: { 1: 'Bebidas', 2: 'Alimentos', 3: 'Higiene', 4: 'Limpeza' } }),
    column('preco',      { type: 'number',  label: 'Preço', mask: 'R$ #,##0.00' }),
    column('estoque',    { type: 'number',  label: 'Estoque' }),
    column('status',     { type: 'select',  label: 'Status', options: { 1: 'Ativo', 2: 'Inativo', 3: 'Pendente' } }),
    column('criado_em',  { type: 'date',    label: 'Data' }),
    actionColumn('acoes', [
      { key: 'editar',  label: 'Editar' },
      { key: 'excluir', label: 'Excluir', danger: true },
    ]),
  ]

  table.adapter = new LaravelAdapter('/api/produtos', {
    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
  })

  table.addEventListener('action', ({ detail: { key, row } }) => {
    if (key === 'editar') {
      window.location.href = `/produtos/${row.raw.id}/editar`
    }
    if (key === 'excluir') {
      if (confirm('Excluir este produto?')) {
        fetch(`/api/produtos/${row.raw.id}`, {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        }).then(() => table.refresh())
      }
    }
  })
}
```

```blade
{{-- resources/views/produtos.blade.php --}}
@extends('layouts.app')

@section('content')
  <div style="padding: 24px; max-width: 1200px; margin: 0 auto;">
    <h1>Produtos</h1>

    <rosium-table
      id="tabela-produtos"
      page-size="25"
      debug
    ></rosium-table>
  </div>
@endsection
```

```js
// resources/js/app.js
import '@rosiumdata/vanilla'
import '@rosiumdata/vanilla/theme/default.css'

import { initProdutosTable } from './tables/produtos'

initProdutosTable()
```

Toda a lógica está em `resources/js/tables/produtos.js`. O Blade é só HTML.
O Vite processa `app.js`, resolve os imports, e registra o componente.

---

> **Documentos relacionados:** `USAGE.md` (API completa), `THEMING.md` (estilização), `ARCHITECTURE.md` (estrutura interna).
