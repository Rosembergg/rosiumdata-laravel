# CONTRIBUTING.md — rosiumdata/laravel

> How to contribute to the Laravel integration package.

---

## Before contributing

This package is part of the RosiumData ecosystem. Before diving in, understand:

1. **[RosiumData Core](https://www.npmjs.com/package/rosiumdata)** — the JavaScript engine that powers the table
2. **[USAGE.md](docs/USAGE.md)** — this package's API reference

---

## Setup

```bash
git clone https://github.com/Rosembergg/rosiumdata-laravel
cd rosiumdata-laravel
composer install
```

---

## Project structure

```
src/
├── RosiumTable.php                  # Base class users extend
├── Column.php                       # Fluent column helper
├── ActionColumn.php                 # Action button column
├── Console/
│   ├── MakeTableCommand.php         # artisan make:rosium-table
│   └── GenerateJsCommand.php        # artisan rosium:generate-js
├── Http/
│   └── RosiumTableController.php    # Generic controller (handles ALL tables)
├── RosiumdataServiceProvider.php    # ServiceProvider
└── Support/
    └── JsGenerator.php              # Generates JS from PHP classes
```

---

## What to contribute

| Type | Examples |
|---|---|
| 🐛 Bug fix | Filter not applying, 500 errors, JS generation broken |
| ✨ Feature | New column types, new operators, better schema detection |
| 📝 Docs | Fixes, improvements, translations |

---

## Pull request checklist

- [ ] Code follows PSR-12
- [ ] No breaking changes to the public API (`RosiumTable`, `Column::make()`, `ActionColumn::make()`)
- [ ] Tested with a real Laravel project (create a table, render it, filter, sort, paginate)
- [ ] The `JsGenerator` still produces valid JavaScript
- [ ] No new Composer dependencies added without discussion
