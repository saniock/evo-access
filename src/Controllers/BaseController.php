<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Routing\Controller;
use Saniock\EvoAccess\Services\AccessService;

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
     * Gate the current request: the caller must have an active EVO
     * manager session AND hold `view` on the given permission slug.
     *
     * Explicitly checks $_SESSION['mgrValidated'] rather than relying
     * solely on evo()->getLoginUserID('mgr'), which can return a stale
     * ID from a dead session and let unauthenticated requests through.
     */
    protected function ensureAccess(string $permission): void
    {
        if (! $this->isManagerAuthenticated()) {
            abort(401, 'Not authenticated.');
        }

        $userId = $this->currentUserId();

        if ($userId <= 0) {
            abort(401, 'Not authenticated.');
        }

        if (! $this->access->can($permission, 'view', $userId)) {
            abort(403, 'Access denied.');
        }
    }

    /**
     * Check that there is an active, validated EVO manager session.
     * Uses the session superglobal directly so we don't depend on any
     * helper that might return cached values from a dead session.
     *
     * EVO sets 'mgrValidated' = 1 after a successful manager login
     * and clears it on logout. Absence of this key (or a falsy value)
     * means the user is not logged in as a manager. 'mgrInternalKey'
     * is the EVO user id — required so the permission resolver has
     * a real principal to check.
     */
    protected function isManagerAuthenticated(): bool
    {
        return ! empty($_SESSION['mgrValidated'])
            && ! empty($_SESSION['mgrInternalKey']);
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
