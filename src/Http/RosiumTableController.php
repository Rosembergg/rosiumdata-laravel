<?php

namespace Rosiumdata\Laravel\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Rosiumdata\Laravel\ActionColumn;
use Rosiumdata\Laravel\RosiumTable;
use Rosiumdata\Laravel\RosiumdataServiceProvider;

class RosiumTableController extends Controller
{
    /**
     * GET /rosium-data/{table}
     *
     * Generic endpoint for all RosiumData tables. Handles filtering,
     * sorting, and pagination — the contract expected by LaravelAdapter.
     */
    public function index(Request $request, string $table): JsonResponse
    {
        try {
            $instance = RosiumdataServiceProvider::findTable($table);
            $query = $instance->query();

            // Cache columns() call — called once, reused everywhere.
            $columns = $instance->columns();
            $validColumns = [];
            $colMap = [];

            foreach ($columns as $col) {
                $def = $col->toArray();
                $validColumns[] = $def['key'];
                $colMap[$def['key']] = $def;
            }

            $actionColumns = array_filter($columns, fn($col) => $col instanceof ActionColumn);

            $this->applyFilters($query, $request->input('filter', []), $instance, $validColumns, $colMap);
            $this->applySort($query, $request->input('sort'), $instance, $validColumns, $colMap);

            $perPage = max(1, min(
                (int) $request->input('per_page', $instance->defaultPageSize()),
                $instance->maxPageSize()
            ));

            $page = max(1, (int) $request->input('page', 1));

            $paginator = $query->paginate(perPage: $perPage, page: $page);

            $rows = array_map(function ($row) use ($instance, $actionColumns) {
                $rules = $instance->actionRules($row);

                $rowArray = $row instanceof \Illuminate\Database\Eloquent\Model
                    ? $row->toArray()
                    : (array) $row;

                foreach ($actionColumns as $col) {
                    $def = $col->toArray();
                    $actions = $def['options']['actions'] ?? [];

                    foreach ($actions as $action) {
                        $key = $action['key'];
                        $rowArray['can_' . $key] = $rules[$key] ?? true;
                    }
                }

                return $rowArray;
            }, $paginator->items());

            return response()->json([
                'data' => $rows,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['error' => 'Database query error.'], 500);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Internal server error.'], 500);
        }
    }

    private function applyFilters($query, array $filters, RosiumTable $instance, array $validColumns, array $colMap): void
    {
        foreach ($filters as $column => $operators) {
            if (!is_array($operators)) continue;
            if (!in_array($column, $validColumns, true)) continue;

            $def = $colMap[$column] ?? null;
            if ($def && ($def['filterable'] ?? true) === false) continue;

            foreach ($operators as $operator => $rawValue) {
                $this->applySingleFilter($query, $column, $operator, $rawValue, $instance);
            }
        }
    }

    private function applySingleFilter($query, string $column, string $operator, mixed $rawValue, RosiumTable $instance): void
    {
        if ($rawValue === '' || $rawValue === null || $rawValue === []) {
            return;
        }

        $col = $instance->qualifyColumn($column);

        match ($operator) {
            'eq' => $query->where($col, $rawValue),
            'like' => $query->where($col, 'like', '%' . addcslashes($this->filterString($rawValue), '%_') . '%'),
            'starts_with' => $query->where($col, 'like', addcslashes($this->filterString($rawValue), '%_') . '%'),
            'ends_with' => $query->where($col, 'like', '%' . addcslashes($this->filterString($rawValue), '%_')),
            'gt' => $query->where($col, '>', $rawValue),
            'gte' => $query->where($col, '>=', $rawValue),
            'lt' => $query->where($col, '<', $rawValue),
            'lte' => $query->where($col, '<=', $rawValue),
            'before' => $query->where($col, '<', $rawValue),
            'after' => $query->where($col, '>', $rawValue),
            'between' => $this->applyBetweenFilter($query, $col, $rawValue),
            default => null,
        };
    }

    private function applyBetweenFilter($query, string $column, mixed $rawValue): void
    {
        [$min, $max] = $this->parseBetweenValues($rawValue);

        if ($min === null && $max === null) {
            return;
        }

        $bothNumeric = $min !== null && $max !== null && is_numeric($min) && is_numeric($max);

        if ($bothNumeric) {
            $query->whereBetween($column, [(float) $min, (float) $max]);
        } elseif ($min !== null && $max !== null) {
            $query->whereBetween($column, [$min, $max]);
        } elseif ($min !== null) {
            $query->where($column, '>=', $min);
        } elseif ($max !== null) {
            $query->where($column, '<=', $max);
        }
    }

    /**
     * @return array{0: mixed, 1: mixed}
     */
    private function parseBetweenValues(mixed $rawValue): array
    {
        $min = null;
        $max = null;

        if (is_array($rawValue) && count($rawValue) === 2) {
            $min = ($rawValue[0] !== '' && $rawValue[0] !== null) ? $rawValue[0] : null;
            $max = ($rawValue[1] !== '' && $rawValue[1] !== null) ? $rawValue[1] : null;

            return [$min, $max];
        }

        if (is_string($rawValue) && str_contains($rawValue, ',')) {
            $parts = array_map('trim', explode(',', $rawValue, 2));
            $min = ($parts[0] ?? '') !== '' ? $parts[0] : null;
            $max = ($parts[1] ?? '') !== '' ? $parts[1] : null;

            return [$min, $max];
        }

        return [null, null];
    }

    private function applySort($query, ?string $sort, RosiumTable $instance, array $validColumns, array $colMap): void
    {
        if (empty($sort)) return;

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (!in_array($column, $validColumns, true)) return;

        $def = $colMap[$column] ?? null;
        if ($def && ($def['sortable'] ?? true) === false) return;

        $col = $instance->qualifyColumn($column);
        $query->orderBy($col, $direction);
    }

    /**
     * Cast a filter value to string, guarding against non-stringable types.
     */
    private function filterString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value) && !is_bool($value)) {
            return (string) $value;
        }

        return '';
    }
}
