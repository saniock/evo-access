<?php

namespace Saniock\EvoAccess\Observers;

use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Services\AuditLogger;
use Saniock\EvoAccess\Services\PermissionResolver;

class RolePermissionActionObserver
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    public function created(RolePermissionAction $grant): void
    {
        $this->audit->logGrant(
            $this->actorId(),
            $grant->role_id,
            $grant->permission_id,
            $grant->action,
        );
        $this->resolver->forgetAll();
    }

    public function deleted(RolePermissionAction $grant): void
    {
        $this->audit->logRevoke(
            $this->actorId(),
            $grant->role_id,
            $grant->permission_id,
            $grant->action,
        );
        $this->resolver->forgetAll();
    }

    private function actorId(): int
    {
        if (function_exists('evo')) {
            return (int) evo()->getLoginUserID('mgr');
        }
        return 0;
    }
}
