<?php

namespace Rosiumdata\Laravel\Console;

use Illuminate\Console\Command;
use Rosiumdata\Laravel\Support\JsGenerator;

class GenerateJsCommand extends Command
{
    protected $signature = 'rosium:generate-js';

    protected $description = 'Generate JavaScript files for all registered RosiumData tables';

    public function handle(): int
    {
        $tables = \Rosiumdata\Laravel\RosiumdataServiceProvider::tables();

        if (empty($tables)) {
            $this->warn('No RosiumData tables found. Create one with: php artisan make:rosium-table');

            return Command::SUCCESS;
        }

        $generator = new JsGenerator();
        $generator->generateAll();

        $this->info('JS files generated at [' . config('rosiumdata.js_path') . '].');
        $this->info('JS init file generated at [' . config('rosiumdata.init_path') . '].');

        $this->newLine();
        $this->info('Import the init file in your app.js:');
        $this->line("  import './rosium-init.js'");

        return Command::SUCCESS;
    }
}
