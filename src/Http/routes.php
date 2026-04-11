<?php

use Illuminate\Support\Facades\Route;
use Saniock\EvoAccess\Controllers\AuditController;
use Saniock\EvoAccess\Controllers\DebugController;
use Saniock\EvoAccess\Controllers\DocsController;
use Saniock\EvoAccess\Controllers\MatrixController;
use Saniock\EvoAccess\Controllers\RolesController;
use Saniock\EvoAccess\Controllers\UsersController;

/*
|--------------------------------------------------------------------------
| evoAccess admin UI routes
|--------------------------------------------------------------------------
|
| Routes are grouped by permission. Each group applies two middlewares:
|   - `managerauth`  — EVO's built-in gate, requires a validated manager
|                      session (anyone who is not logged into the manager
|                      gets "No Manager Access" back).
|   - `eaaccess.permission:<slug>` — per-page evo-access permission check;
|                      returns a pretty 403 HTML page (or JSON for AJAX)
|                      when the manager lacks the listed permission.
|
| Permission checks happen in middleware rather than in BaseController
| constructors so the resulting 403 response is handled by the normal
| Laravel response pipeline and does NOT escape into EVO's top-level
| exception handler (which would otherwise render a giant "Parse Error"
| page with a full backtrace).
|
*/

Route::group([
    'prefix'     => 'access',
    'as'         => 'evoAccess.',
    'middleware' => ['managerauth'],
], function () {
    // ----- Roles CRUD + Matrix (access.roles) -----
    Route::middleware('eaaccess.permission:access.roles')->group(function () {
        Route::get('roles', [RolesController::class, 'index'])->name('roles.index');
        Route::get('roles/data', [RolesController::class, 'data'])->name('roles.data');
        Route::post('roles', [RolesController::class, 'store'])->name('roles.store');
        Route::put('roles/{id}', [RolesController::class, 'update'])->name('roles.update');
        Route::delete('roles/{id}', [RolesController::class, 'destroy'])->name('roles.destroy');
        Route::post('roles/{id}/clone', [RolesController::class, 'clone'])->name('roles.clone');
        Route::post('roles/{id}/reassign-and-delete', [RolesController::class, 'reassignAndDelete'])->name('roles.reassignAndDelete');

        Route::get('matrix', [MatrixController::class, 'index'])->name('matrix.index');
        Route::get('matrix/data/{role_id}', [MatrixController::class, 'data'])->name('matrix.data');
        Route::post('matrix/grant', [MatrixController::class, 'grant'])->name('matrix.grant');
        Route::delete('matrix/revoke', [MatrixController::class, 'revoke'])->name('matrix.revoke');
    });

    // ----- Users (access.users) -----
    Route::middleware('eaaccess.permission:access.users')->group(function () {
        Route::get('users', [UsersController::class, 'index'])->name('users.index');
        Route::get('users/data', [UsersController::class, 'data'])->name('users.data');
        Route::get('users/search', [UsersController::class, 'search'])->name('users.search');
        Route::get('users/{user_id}/effective', [UsersController::class, 'effective'])->name('users.effective');
        Route::get('users/{user_id}/matrix', [UsersController::class, 'matrix'])->name('users.matrix');
        Route::post('users/{user_id}/assign', [UsersController::class, 'assign'])->name('users.assign');
        Route::post('users/{user_id}/overrides', [UsersController::class, 'addOverride'])->name('users.overrides.add');
        Route::post('users/{user_id}/overrides/batch', [UsersController::class, 'batchOverrides'])->name('users.overrides.batch');
        Route::delete('users/{user_id}/overrides/{override_id}', [UsersController::class, 'removeOverride'])->name('users.overrides.remove');
    });

    // ----- Audit (access.audit) -----
    Route::middleware('eaaccess.permission:access.audit')->group(function () {
        Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('audit/data', [AuditController::class, 'data'])->name('audit.data');
    });

    // ----- Docs (access.docs) -----
    Route::middleware('eaaccess.permission:access.docs')->group(function () {
        Route::get('docs', [DocsController::class, 'index'])->name('docs.index');
        Route::get('docs/{section}', [DocsController::class, 'index'])->name('docs.section');
    });

    // TEMP: diagnostic — remove when the "full access" bug is resolved.
    // Deliberately NOT under eaaccess.permission so we can diagnose
    // even when permissions are broken.
    Route::get('_diag', [DebugController::class, 'diag'])->name('_diag');
    Route::get('_diag/gate', [DebugController::class, 'gate'])->name('_diag.gate');
});
