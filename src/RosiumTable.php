<?php

namespace Rosiumdata\Laravel;

use Illuminate\Database\Eloquent\Builder;

abstract class RosiumTable
{
    /**
     * Unique name for this table — used in Blade and route.
     * Example: 'produtos', 'clientes'
     */
    abstract public static function name(): string;

    /**
     * Eloquent query builder — may include joins, subqueries, where clauses.
     */
    abstract public function query(): Builder;

    /**
     * Columns — array of Column or ActionColumn instances.
     *
     * @return array<int, Column|ActionColumn>
     */
    abstract public function columns(): array;

    /**
     * Default items per page.
     */
    public function defaultPageSize(): int
    {
        return 20;
    }

    /**
     * Maximum page size allowed for pagination. Caps user-provided per_page.
     */
    public function maxPageSize(): int
    {
        return 1000;
    }

    /**
     * Display locale for date/number formatting.
     */
    public function locale(): string
    {
        return 'pt-BR';
    }

    /**
     * localStorage persistence key. Null disables persistence.
     */
    public function persistenceKey(): ?string
    {
        return null;
    }

    /**
     * Optional JavaScript event handlers for the &lt;rosium-table&gt; element.
     * Return raw JS code that will be injected after the table is configured.
     *
     * The element is available as the variable `el`.
     * The event detail is `{ key, row }` where `row.raw` gives the raw data.
     *
     * Example:
     *   return "el.addEventListener('action', ({ detail: { key, row } }) => {
     *     if (key === 'editar') window.location.href = '/edit/' + row.raw.id
     *     if (key === 'deletar') el.refresh?.()
     *   })"
     *
     * Return null if no custom handlers are needed.
     */
    public function eventHandlers(): ?string
    {
        return null;
    }

    /**
     * Resolve a display column key to a qualified database column name.
     *
     * Override this when your query uses joins or aliases.
     * The controller uses this for filters and sort — never raw keys.
     *
     * Example:
     *   return match ($key) {
     *       'id' => 'users_responsavel.id',
     *       'nome' => 'users.name',
     *       default => $key,
     *   };
     */
    public function qualifyColumn(string $key): string
    {
        return $key;
    }

    /**
     * Define per-row visibility of action buttons.
     *
     * Returns an associative array mapping action keys to booleans.
     * Actions not listed in the returned array are always visible.
     *
     * Use Laravel Gates, Policies, or any business logic.
     *
     * Example:
     *   return [
     *       'editar' => $user->can('update', $row),
     *       'excluir' => $user->can('delete', $row) && $row->status === 'pendente',
     *   ];
     *
     * @param  mixed  $row  A single row from the query result (Model instance or stdClass)
     * @return array<string, bool>
     */
    public function actionRules(mixed $row): array
    {
        return [];
    }
}
