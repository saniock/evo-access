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
     * Gate the current request: the caller must be authenticated and
     * hold `view` on the given permission slug.
     */
    protected function ensureAccess(string $permission): void
    {
        $userId = $this->currentUserId();

        if ($userId <= 0) {
            abort(401, 'Not authenticated.');
        }

        if (! $this->access->can($permission, 'view', $userId)) {
            abort(403, 'Access denied.');
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
     */
    protected function applyLocaleOverride(): void
    {
        $available = array_keys((array) config('evoAccess.available_locales', []));
        if (empty($available)) {
            return;
        }

        $cookie = request()->cookie('ea_locale');
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
