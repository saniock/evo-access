<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Routing\Controller;
use Saniock\EvoAccess\Services\AccessService;

abstract class BaseController extends Controller
{
    public function __construct(
        protected readonly AccessService $access,
    ) {
        // Subclasses call ensureAccess() with their page's permission
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
}
