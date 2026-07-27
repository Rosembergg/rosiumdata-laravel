<?php

namespace Rosiumdata\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Rosiumdata\Laravel\RosiumdataServiceProvider;
use Rosiumdata\Laravel\Support\JsGenerator;

class MakeTableCommand extends Command
{
    protected $signature = 'make:rosium-table
                            {name : Table name (e.g. Produtos)}
                            {--model= : Eloquent model to introspect (e.g. Produto or App\\Models\\Produto)}
                            {--path= : Custom path for the table class}';

    protected $description = 'Create a new RosiumData table class';

    public function handle(): int
    {
        $name = $this->argument('name');
        $className = str_ends_with($name, 'Table') ? $name : $name . 'Table';
        $tableName = $this->deriveTableName($className);

        $modelOption = $this->option('model');
        $modelClass = null;
        $modelTable = null;

        if ($modelOption) {
            $modelClass = $this->resolveModelClass($modelOption);
            if ($modelClass && class_exists($modelClass)) {
                /** @var \Illuminate\Database\Eloquent\Model $instance */
                $instance = new $modelClass;
                $modelTable = $instance->getTable();
            }
        }

        $columnsSnippet = $this->generateColumns($modelTable);

        $stub = file_get_contents(__DIR__ . '/../../stubs/table.stub');

        $path = $this->option('path') ?? app_path('RosiumTables');
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $namespace = $this->deriveNamespace($path);

        $tableClass = $modelClass ? "\\{$modelClass}" : '\\App\\Models\\User';

        $content = str_replace(
            ['{{ namespace }}', '{{ className }}', '{{ tableName }}', '{{ model }}', '{{ columns }}'],
            [$namespace, $className, $tableName, $tableClass, $columnsSnippet],
            $stub
        );

        $filePath = "{$path}/{$className}.php";

        if (file_exists($filePath)) {
            $this->error("Table class [{$className}] already exists at [{$filePath}].");

            return Command::FAILURE;
        }

        file_put_contents($filePath, $content);

        $this->info("Table [{$className}] created successfully at [{$filePath}].");

        $fullClass = $this->fqcn($namespace, $className);

        try {
            RosiumdataServiceProvider::registerTable($fullClass);
        } catch (\InvalidArgumentException $e) {
            $this->warn("Table class created. Run: composer dump-autoload");
        }

        $generator = new JsGenerator();
        $generator->generateAll();

        $this->info("JS files generated at [" . config('rosiumdata.js_path') . "].");
        $this->info("JS init file generated at [" . config('rosiumdata.init_path') . "].");

        $this->newLine();
        $this->info('Import the init file in your app.js:');
        $this->line("  import './rosium-init.js'");
        $this->newLine();
        $this->info('Use in Blade:');
        $this->line("  <rosium-table rosium=\"{$tableName}\" />");

        return Command::SUCCESS;
    }

    private function deriveTableName(string $className): string
    {
        $withoutSuffix = preg_replace('/Table$/', '', $className);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $withoutSuffix));
    }

    private function deriveNamespace(string $path): string
    {
        $appPath = rtrim(app_path(), '/');

        if (str_starts_with($path, $appPath)) {
            $relative = ltrim(substr($path, strlen($appPath)), '/');
            $ns = 'App' . ($relative ? '\\' . str_replace('/', '\\', $relative) : '');
        } else {
            $ns = 'App\\RosiumTables';
        }

        return $ns;
    }

    private function fqcn(string $namespace, string $className): string
    {
        return '\\' . $namespace . '\\' . $className;
    }

    private function resolveModelClass(string $model): string
    {
        if (str_contains($model, '\\')) {
            return $model;
        }

        return "App\\Models\\{$model}";
    }

    private function generateColumns(?string $table): string
    {
        if (!$table || !Schema::hasTable($table)) {
            return "            // Add your columns here\n            // Column::make('id', 'number')->label('ID'),";
        }

        $schemaColumns = Schema::getColumnListing($table);
        $lines = [];
        $hasPrimaryId = false;

        $skip = ['created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'];

        foreach ($schemaColumns as $column) {
            if (in_array($column, $skip, true)) {
                continue;
            }
            if ($column === 'id') {
                $hasPrimaryId = true;
                $lines[] = "Column::make('id', 'number')->label('ID')";
                continue;
            }

            $detectedType = $this->detectColumnType($table, $column);
            $label = $this->generateLabel($column);

            $col = "Column::make('{$column}', '{$detectedType}')";

            if ($label !== $column) {
                $col .= "->label('{$label}')";
            }

            if ($this->shouldAddMask($column, $detectedType)) {
                $col .= "->mask('R\$ #,##0.00')";
            }

            $lines[] = $col;
        }

        if (empty($lines)) {
            $lines[] = "Column::make('id', 'number')->label('ID')";
        }

        return '            ' . implode(",\n            ", $lines) . ',';
    }

    private function detectColumnType(string $table, string $column): string
    {
        if (str_ends_with($column, '_id')) {
            return 'select';
        }

        if (str_ends_with($column, '_at')) {
            return 'date';
        }

        try {
            $doctrineType = Schema::getColumnType($table, $column);

            return match ($doctrineType) {
                'bigint', 'integer', 'smallint', 'tinyint' => 'number',
                'decimal', 'float', 'double' => 'number',
                'boolean' => 'boolean',
                'date', 'datetime', 'datetimetz' => 'date',
                'time' => 'text',
                'json', 'text', 'guid', 'blob' => 'text',
                default => 'text',
            };
        } catch (\Throwable) {
            return 'text';
        }
    }

    private function generateLabel(string $column): string
    {
        $label = str_replace('_', ' ', $column);

        return ucwords($label);
    }

    private function shouldAddMask(string $column, string $type): bool
    {
        if ($type !== 'number') {
            return false;
        }

        $moneyColumns = ['preco', 'price', 'valor', 'value', 'custo', 'cost', 'total', 'saldo', 'balance', 'montante', 'amount'];

        foreach ($moneyColumns as $mc) {
            if (str_contains(strtolower($column), $mc)) {
                return true;
            }
        }

        return false;
    }
}
