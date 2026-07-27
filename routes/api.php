<?php

use Illuminate\Support\Facades\Route;
use Rosiumdata\Laravel\Http\RosiumTableController;

Route::prefix(config('rosiumdata.route_prefix', 'rosium-data'))
    ->middleware(config('rosiumdata.middleware', ['api']))
    ->group(function () {
        Route::get('{table}', [RosiumTableController::class, 'index']);
    });
