<?php

use Illuminate\Support\Facades\Route;
use Saniock\EvoAccess\Controllers\AuditController;
use Saniock\EvoAccess\Controllers\DocsController;
use Saniock\EvoAccess\Controllers\MatrixController;
use Saniock\EvoAccess\Controllers\RolesController;
use Saniock\EvoAccess\Controllers\UsersController;

/*
|--------------------------------------------------------------------------
| evoAccess admin UI routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the service provider and handle the
| access-control admin UI (roles list, permission matrix, users,
| audit log). They live under the /access/ prefix and are gated by
| the manager session + the 'access.admin' permission (enforced in
| BaseController).
|
*/

Route::group([
    'prefix'     => 'access',
    'as'         => 'evoAccess.',
    'middleware' => ['web'],
], function () {
    // Roles CRUD
    Route::get('roles', [RolesController::class, 'index'])->name('roles.index');
    Route::get('roles/data', [RolesController::class, 'data'])->name('roles.data');
    Route::post('roles', [RolesController::class, 'store'])->name('roles.store');
    Route::put('roles/{id}', [RolesController::class, 'update'])->name('roles.update');
    Route::delete('roles/{id}', [RolesController::class, 'destroy'])->name('roles.destroy');
    Route::post('roles/{id}/clone', [RolesController::class, 'clone'])->name('roles.clone');
    Route::post('roles/{id}/reassign-and-delete', [RolesController::class, 'reassignAndDelete'])->name('roles.reassignAndDelete');

    // Matrix
    Route::get('matrix', [MatrixController::class, 'index'])->name('matrix.index');
    Route::get('matrix/data/{role_id}', [MatrixController::class, 'data'])->name('matrix.data');
    Route::post('matrix/grant', [MatrixController::class, 'grant'])->name('matrix.grant');
    Route::delete('matrix/revoke', [MatrixController::class, 'revoke'])->name('matrix.revoke');

    // Users
    Route::get('users', [UsersController::class, 'index'])->name('users.index');
    Route::get('users/data', [UsersController::class, 'data'])->name('users.data');
    Route::get('users/search', [UsersController::class, 'search'])->name('users.search');
    Route::get('users/{user_id}/effective', [UsersController::class, 'effective'])->name('users.effective');
    Route::get('users/{user_id}/matrix', [UsersController::class, 'matrix'])->name('users.matrix');
    Route::post('users/{user_id}/assign', [UsersController::class, 'assign'])->name('users.assign');
    Route::post('users/{user_id}/overrides', [UsersController::class, 'addOverride'])->name('users.overrides.add');
    Route::post('users/{user_id}/overrides/batch', [UsersController::class, 'batchOverrides'])->name('users.overrides.batch');
    Route::delete('users/{user_id}/overrides/{override_id}', [UsersController::class, 'removeOverride'])->name('users.overrides.remove');

    // Audit
    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
    Route::get('audit/data', [AuditController::class, 'data'])->name('audit.data');

    // Docs
    Route::get('docs', [DocsController::class, 'index'])->name('docs.index');
    Route::get('docs/{section}', [DocsController::class, 'index'])->name('docs.section');
});
