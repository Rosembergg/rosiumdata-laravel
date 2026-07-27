<?php

namespace Rosiumdata\Laravel;

use Illuminate\Support\ServiceProvider;
use Rosiumdata\Laravel\Console\GenerateJsCommand;
use Rosiumdata\Laravel\Console\MakeTableCommand;
use Rosiumdata\Laravel\Support\JsGenerator;

class RosiumdataServiceProvider extends ServiceProvider
{
    /** @var array<string, class-string<RosiumTable>> */
    private static array $tables = [];

    /** @var int|null Last JS generation timestamp. */
    private static ?int $lastGenerateAt = null;

    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/rosiumdata.php', 'rosiumdata');

        $this->publishes([
            __DIR__ . '/../config/rosiumdata.php' => config_path('rosiumdata.php'),
        ], 'rosiumdata-config');

        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeTableCommand::class,
                GenerateJsCommand::class,
            ]);
        }

        $this->discoverTables();

        if (config('rosiumdata.auto_generate_js', false)) {
            $now = time();
            if (static::$lastGenerateAt === null || ($now - static::$lastGenerateAt) >= 5) {
                static::$lastGenerateAt = $now;
                try {
                    $generator = new JsGenerator();
                    $generator->generateAll();
                } catch (\Throwable) {
                    // Silently ignore — tables may not be ready yet
                }
            }
        }
    }

    public function register(): void
    {
        //
    }

    /**
     * Register a table class by its name.
     *
     * @param class-string<RosiumTable> $class
     */
    public static function registerTable(string $class): void
    {
        if (!class_exists($class) || !is_subclass_of($class, RosiumTable::class)) {
            throw new \InvalidArgumentException("Class [{$class}] must exist and extend RosiumTable. Run: composer dump-autoload");
        }

        $name = $class::name();

        if ($name === '' || $name === null) {
            throw new \InvalidArgumentException("Table name must not be empty.");
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException(
                "Table name [{$name}] must be a valid JavaScript identifier (letters, numbers, underscore only — no hyphens or special chars)."
            );
        }

        static::$tables[$name] = $class;
    }

    /**
     * Get all registered tables.
     *
     * @return array<string, class-string<RosiumTable>>
     */
    public static function tables(): array
    {
        return static::$tables;
    }

    /**
     * Find a table class by its name.
     *
     * @throws \RuntimeException if table not found
     */
    public static function findTable(string $name): RosiumTable
    {
        $class = static::$tables[$name] ?? null;

        if (!$class || !class_exists($class)) {
            throw new \RuntimeException("RosiumTable [{$name}] not found. Create it with: php artisan make:rosium-table " . ucfirst($name));
        }

        return new $class;
    }

    /**
     * Auto-discover table classes in app/RosiumTables/ (recursive).
     */
    private function discoverTables(): void
    {
        $path = rtrim(config('rosiumdata.path', app_path('RosiumTables')), '/');

        if (!is_dir($path)) {
            return;
        }

        $baseLen = strlen($path) + 1;

        foreach ($this->phpFilesInDir($path) as $file) {
            $relative = substr($file, $baseLen);
            $relative = basename($relative, '.php');

            // Rebuild directory prefix: Admin/ProdutosTable → Admin\ProdutosTable
            $dir = dirname(substr($file, $baseLen));
            if ($dir === '.') {
                $nsSuffix = $relative;
            } else {
                $nsSuffix = str_replace('/', '\\', $dir) . '\\' . $relative;
            }

            $class = '\\App\\RosiumTables\\' . $nsSuffix;

            if (class_exists($class) && is_subclass_of($class, RosiumTable::class)) {
                try {
                    static::registerTable($class);
                } catch (\InvalidArgumentException) {
                    // Skip tables with invalid names
                }
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function phpFilesInDir(string $path): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && $item->getExtension() === 'php') {
                $files[] = $item->getPathname();
            }
        }

        return $files;
    }
}
