<?php

namespace Saniock\EvoAccess;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Saniock\EvoAccess\Console\BootstrapCommand;
use Saniock\EvoAccess\Console\MigrateLegacyEvoRolesCommand;
use Saniock\EvoAccess\Console\SyncPermissionsCommand;
use Saniock\EvoAccess\Contracts\AccessServiceInterface;
use Saniock\EvoAccess\Contracts\PermissionCatalogInterface;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Models\UserOverride;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Observers\RoleObserver;
use Saniock\EvoAccess\Observers\RolePermissionActionObserver;
use Saniock\EvoAccess\Observers\UserOverrideObserver;
use Saniock\EvoAccess\Observers\UserRoleObserver;
use Saniock\EvoAccess\Services\AccessService;
use Saniock\EvoAccess\Services\AuditLogger;
use Saniock\EvoAccess\Services\PermissionCatalog;
use Saniock\EvoAccess\Services\PermissionResolver;

/**
 * Main service provider for the saniock/evo-access package.
 *
 * Wires the access-control services into the container, loads
 * migrations/views/translations/routes, and exposes publishable
 * config + assets for consumer projects.
 */
class EvoAccessServiceProvider extends ServiceProvider
{
    /**
     * Register package services in the container.
     *
     * Bound as singletons so per-request caches inside the resolver
     * and the catalog survive across all call sites within a request.
     *
     * register() is side-effect-free — DI bindings only.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/config/evoAccess.php',
            'evoAccess'
        );

        $this->app->singleton(PermissionCatalog::class);
        $this->app->singleton(PermissionResolver::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(AccessService::class);

        $this->app->alias(AccessService::class, AccessServiceInterface::class);
        $this->app->alias(PermissionCatalog::class, PermissionCatalogInterface::class);

        $this->app->alias(AccessService::class, 'evoAccess');
    }

    /**
     * Bootstrap the package: migrations, views, translations, routes,
     * observers, console commands, EVO manager-menu plugin.
     *
     * Routes/views/observers always run — the actual access control
     * for the admin UI lives in BaseController::ensureAdminAccess(),
     * which 401/403's any caller without the access.admin permission.
     *
     * The "soft rollout" gate sits inside the EVO manager-menu plugin
     * (plugins/evoAccessPlugin.php) and reads
     * config('evoAccess.web_user_whitelist') — only whitelisted EVO
     * user IDs see the new "Access" entry in the manager top menu, so
     * a half-finished package can ship alongside live managers without
     * disturbing them. Empty whitelist = general rollout, everyone
     * gets the menu entry.
     */
    public function boot(): void
    {
        $this->registerRequestValidateMacro();

        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->registerConsoleCommands();
            $this->registerPublishing();
        }

        $this->loadViewsFrom(
            dirname(__DIR__) . '/views',
            'evoAccess'
        );

        $this->loadTranslationsFrom(
            dirname(__DIR__) . '/lang',
            'evoAccess'
        );

        $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');

        $this->loadEvoManagerPlugin();

        Role::observe(RoleObserver::class);
        RolePermissionAction::observe(RolePermissionActionObserver::class);
        UserRole::observe(UserRoleObserver::class);
        UserOverride::observe(UserOverrideObserver::class);

        $this->app->make(PermissionCatalog::class)
            ->registerPermissions('access', [
                [
                    'name' => 'access.admin',
                    'label' => 'Access — administration',
                    'actions' => ['view', 'update'],
                ],
            ]);
    }

    /**
     * Polyfill `Illuminate\Http\Request::validate()` for hosts that
     * don't ship Laravel's FoundationServiceProvider.
     *
     * In a full Laravel app, the macro is registered automatically by
     * Illuminate\Foundation\Providers\FoundationServiceProvider — but
     * EVO 3.5 has a custom bootstrap that doesn't register that
     * provider, so `$request->validate([...])` would normally throw
     * "Method Illuminate\Http\Request::validate does not exist". The
     * underlying validator package IS available, just not the sugar.
     *
     * The hasMacro() guard makes this a no-op in environments where
     * the macro already exists, so consumers running on full Laravel
     * are unaffected.
     */
    private function registerRequestValidateMacro(): void
    {
        if (Request::hasMacro('validate')) {
            return;
        }

        Request::macro('validate', function (array $rules, array $messages = [], array $customAttributes = []) {
            /** @var Request $this */
            return Validator::make($this->all(), $rules, $messages, $customAttributes)->validate();
        });
    }

    /**
     * Register console commands that ship with the package.
     */
    private function registerConsoleCommands(): void
    {
        $this->commands([
            BootstrapCommand::class,
            SyncPermissionsCommand::class,
            MigrateLegacyEvoRolesCommand::class,
        ]);
    }

    /**
     * Declare publishable assets so consumer projects can override
     * config + views + public assets via `php artisan vendor:publish`.
     */
    private function registerPublishing(): void
    {
        $this->publishes([
            dirname(__DIR__) . '/config/evoAccess.php' => config_path('evoAccess.php'),
        ], 'evo-access-config');

        $this->publishes([
            dirname(__DIR__) . '/views' => resource_path('views/vendor/evoAccess'),
        ], 'evo-access-views');

        $this->publishes([
            dirname(__DIR__) . '/css' => public_path('vendor/evoAccess/css'),
            dirname(__DIR__) . '/js' => public_path('vendor/evoAccess/js'),
            dirname(__DIR__) . '/images' => public_path('vendor/evoAccess/images'),
        ], 'evo-access-assets');
    }

    /**
     * Include the EVO manager-menu plugin if the plugins directory
     * ships one. Keeps the EVO manager menu integration optional.
     */
    private function loadEvoManagerPlugin(): void
    {
        $plugin = dirname(__DIR__) . '/plugins/evoAccessPlugin.php';

        if (is_file($plugin)) {
            require_once $plugin;
        }
    }
}
