<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| evoAccess admin UI routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the service provider and handle the
| access-control admin UI (roles list, permission matrix, users,
| audit log). They live under the /access/ prefix and are gated by
| the manager session + the 'access.admin' permission.
|
| Intentionally empty for now — controllers are added during the
| implementation phase.
|
*/

Route::group([
    'prefix'     => 'access',
    'as'         => 'evoAccess.',
    'middleware' => ['web'],
], function () {
    // Route::get('/', [\Saniock\EvoAccess\Controllers\MatrixController::class, 'index'])
    //     ->name('matrix');
});
