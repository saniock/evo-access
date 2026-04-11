<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Routing\Controller;
use Saniock\EvoAccess\Services\AccessService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

abstract class BaseController extends Controller
{
    public function __construct(
        protected readonly AccessService $access,
    ) {
        // Subclasses call ensureAccess() with their page's permission.
        // We apply the locale override here so every page inherits it,
        // and the layout/views can rely on app()->getLocale() returning
        // the correct evo-access locale regardless of EVO's system locale.
        $this->applyLocaleOverride();
    }

    protected function currentUserId(): int
    {
        if (function_exists('evo')) {
            return (int) evo()->getLoginUserID('mgr');
        }
        return 0;
    }

    /**
     * Gate the current request: the caller must hold `view` on the
     * given permission slug. Manager authentication itself is handled
     * at the route level by the `managerauth` middleware — do not try
     * to re-implement it here.
     *
     * NOTE: throws Symfony HTTP exceptions directly instead of using
     * Laravel's abort() helper. Some consumer projects override the
     * global abort() function (or ship their own helper autoloaded
     * before Laravel's), turning it into a no-op and silently letting
     * unauthorised requests through. Direct throw is immune to that.
     */
    protected function ensureAccess(string $permission): void
    {
        $userId = $this->currentUserId();

        if ($userId <= 0) {
            throw new UnauthorizedHttpException('', 'Not authenticated.');
        }

        if (! $this->access->can($permission, 'view', $userId)) {
            throw new AccessDeniedHttpException('Access denied.');
        }
    }

    /**
     * Check whether the current user holds `edit` on the given permission.
     */
    protected function canEdit(string $permission): bool
    {
        return $this->access->can($permission, 'edit', $this->currentUserId());
    }

    /**
     * Read the `ea_locale` cookie and switch Laravel's app locale to it
     * (limited to the whitelist in config('evoAccess.available_locales')).
     * Maps legacy 'ua' to canonical 'uk' so the package always sees the
     * ISO-standard code when resolving translations and docs.
     *
     * Reads straight from $_COOKIE (not request()->cookie()) to bypass
     * Laravel's EncryptCookies middleware — the selector JS sets a plain
     * cookie, so the encrypted reader would always return null.
     */
    protected function applyLocaleOverride(): void
    {
        $available = array_keys((array) config('evoAccess.available_locales', []));
        if (empty($available)) {
            return;
        }

        $cookie = $_COOKIE['ea_locale'] ?? null;
        if (! is_string($cookie) || $cookie === '') {
            return;
        }

        // Map 'ua' → 'uk' for ISO compliance.
        $locale = $cookie === 'ua' ? 'uk' : $cookie;

        if (in_array($locale, $available, true)) {
            app()->setLocale($locale);
        }
    }
}
