<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RosiumData Table Path
    |--------------------------------------------------------------------------
    |
    | Directory where table classes are stored. The ServiceProvider
    | auto-discovers classes extending RosiumTable in this path.
    |
    */
    'path' => app_path('RosiumTables'),

    /*
    |--------------------------------------------------------------------------
    | JavaScript Output Path
    |--------------------------------------------------------------------------
    |
    | Directory where auto-generated JS files are written.
    | These files configure the <rosium-table> Web Component.
    |
    */
    'js_path' => resource_path('js/rosium'),

    /*
    |--------------------------------------------------------------------------
    | Init File Path
    |--------------------------------------------------------------------------
    |
    | Path to the auto-generated rosium-init.js file that imports and
    | initializes all discovered tables. Import this in your app.js.
    |
    */
    'init_path' => resource_path('js/rosium-init.js'),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | URL prefix for the auto-loaded API routes. All table data endpoints
    | will be served under {route_prefix}/{table-name}.
    |
    */
    'route_prefix' => 'rosium-data',

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to the auto-loaded API routes.
    | For Sanctum SPA auth, use ['web', 'auth:sanctum'].
    | For API tokens, use ['auth:sanctum'].
    | For public data, use ['api'] or ['web'].
    |
    */
    'middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Auto-generate JS in Development
    |--------------------------------------------------------------------------
    |
    | When true (default), the ServiceProvider automatically generates
    | or refreshes all JS files on every request in local environment.
    | Set to false if you prefer manual generation via artisan command.
    |
    */
    'auto_generate_js' => env('APP_ENV') === 'local',

];
