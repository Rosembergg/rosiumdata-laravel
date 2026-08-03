# ARCHITECTURE.md — rosiumdata/laravel

> Como o pacote funciona internamente. Fluxo de dados, responsabilidades e
> integração com o ecossistema RosiumData.

---

## FLUXO COMPLETO

```
┌─ Browser ───────────────────────────────────────────┐
│                                                       │
│  <rosium-table rosium="produtos" />                   │
│       │                                               │
│       ▼                                               │
│  @rosiumdata/vanilla (Web Component)                  │
│       │  JS auto-gerado pelo pacote (JsGenerator)     │
│       ▼                                               │
│  @rosiumdata/core (RosiumTable + LaravelAdapter)     │
│       │                                               │
│       ▼  GET /rosium-data/produtos?filter[...]&sort=.│
└───────┼───────────────────────────────────────────────┘
        │
┌─ Laravel ────────────────────────────────────────────┐
│                                                       │
│  routes/api.php                                       │
│       │                                               │
│       ▼                                               │
│  RosiumTableController::index()                       │
│       │                                               │
│       ├── findTable('produtos')                       │
│       │   └── ServiceProvider::findTable()            │
│       │       └── ServiceProvider::$tables['produtos']│
│       │           └── App\RosiumTables\ProdutosTable  │
│       │                                               │
│       ├── $instance->query()                          │
│       │   └── Eloquent Builder (SUA query)            │
│       │                                               │
│       ├── applyFilters()  ── where(), whereBetween()  │
│       ├── applySort()     ── orderBy()                │
│       └── paginate()      ── paginate(per_page, page) │
│                                                       │
│  → { data: [...], meta: { total: N } }               │
│                                                       │
└───────────────────────────────────────────────────────┘
```

---

## RESPONSABILIDADES

### RosiumTable (classe base)

O usuário estende esta classe. Define:
- `name()` — identificador único (usado na Blade e na rota)
- `query()` — query Eloquent base
- `columns()` — definição das colunas (Column | ActionColumn)

### RosiumTableController

Controller genérico. **Um controller para TODAS as tabelas.**
- Recebe `GET /rosium-data/{table}`
- Encontra a classe registrada via ServiceProvider
- Aplica filtros (where, whereBetween)
- Aplica ordenação (orderBy)
- Pagina (paginate)
- Retorna JSON no formato esperado pelo LaravelAdapter

### JsGenerator

Converte uma classe PHP em um arquivo JavaScript.
- Lê `columns()` → gera array JS com as definições
- Lê `defaultPageSize()`, `locale()`, `persistenceKey()`
- Lê `eventHandlers()` → injeta código JS customizado
- Gera `resources/js/rosium/{nome}.js`

### RosiumdataServiceProvider

- Registra o pacote no Laravel (auto-discovery)
- Carrega rotas de `routes/api.php`
- Descobre classes em `app/RosiumTables/` automaticamente
- Registra comandos Artisan
- Gerencia o registro de tabelas (`$tables`)

### MakeTableCommand

- `php artisan make:rosium-table Nome --model=Model`
- Cria a classe PHP do stub
- Detecta schema do banco (se --model passado)
- Gera o JS automaticamente
- Registra a tabela no ServiceProvider

---

## DEPENDÊNCIAS

### PHP (Composer)

```
rosiumdata/laravel
├── illuminate/support   (ServiceProvider, config)
├── illuminate/http      (Request, JsonResponse, Controller)
├── illuminate/database  (Builder, QueryException)
├── illuminate/routing   (Route)
├── illuminate/console   (Command, artisan)
└── illuminate/filesystem (JsGenerator write)
```

### JavaScript (npm — instalado separadamente)

```
rosiumdata (meta-pacote npm)
├── @rosiumdata/core     (RosiumTable, column(), LaravelAdapter)
└── @rosiumdata/vanilla  (<rosium-table> Web Component)
```

---

## CICLO DE VIDA DE UMA TABELA

### Criação

```
1. php artisan make:rosium-table Produtos --model=Produto
2. MakeTableCommand cria app/RosiumTables/ProdutosTable.php
3. MakeTableCommand gera resources/js/rosium/produtos.js
4. ServiceProvider::registerTable('App\RosiumTables\ProdutosTable')
```

### Requisição (a cada acesso)

```
1. Browser carrega a página Blade
2. Vite carrega resources/js/rosium/produtos.js
3. JS configura <rosium-table> com columns + LaravelAdapter
4. Web Component chama GET /rosium-data/produtos?page=1&per_page=20
5. Controller processa e retorna JSON
6. Web Component renderiza a tabela
```

---

## SEGURANÇA

- **Filtro seguro:** colunas que não estão em `columns()` são ignoradas
- **Operador seguro:** operadores desconhecidos são ignorados
- **between parcial:** só min ou só max funciona (usa >= ou <=)
- **Try/catch:** SQL exception não expõe detalhes internos
- **CSRF:** token injetado automaticamente no JS gerado
- **Rate limiting:** configurável via middleware (`api`, `throttle`)
