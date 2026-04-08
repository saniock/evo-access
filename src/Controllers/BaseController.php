<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Routing\Controller;
use Saniock\EvoAccess\Exceptions\AccessDeniedException;
use Saniock\EvoAccess\Services\AccessService;

abstract class BaseController extends Controller
{
    public function __construct(
        protected readonly AccessService $access,
    ) {
        $this->ensureAdminAccess();
    }

    /**
     * The Access admin UI is gated by its own permission `access.admin` action `view`.
     * Superadmin (is_system=1) bypasses everything via the resolver short-circuit.
     */
    private function ensureAdminAccess(): void
    {
        $userId = $this->currentUserId();

        if ($userId === 0) {
            abort(401, 'Login required.');
        }

        if (!$this->access->can('access.admin', 'view', $userId)) {
            throw new AccessDeniedException(
                'Access denied — you do not have permission to manage access control.',
                permission: 'access.admin',
                action: 'view',
                userId: $userId,
            );
        }
    }

    protected function currentUserId(): int
    {
        if (function_exists('evo')) {
            return (int) evo()->getLoginUserID('mgr');
        }
        return 0;
    }
}
