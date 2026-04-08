<?php

namespace Saniock\EvoAccess\Observers;

use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Services\AuditLogger;
use Saniock\EvoAccess\Services\PermissionResolver;

class RoleObserver
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    public function created(Role $role): void
    {
        $this->audit->logRoleCreated($this->actorId(), $role);
    }

    public function updated(Role $role): void
    {
        if ($role->wasChanged('label')) {
            $this->audit->logRoleRenamed(
                $this->actorId(),
                $role,
                $role->getOriginal('label'),
                $role->label,
            );
        }

        if ($role->wasChanged('is_system')) {
            $this->resolver->forgetAll();
        }
    }

    public function deleted(Role $role): void
    {
        $this->audit->logRoleDeleted($this->actorId(), $role);
        $this->resolver->forgetAll();
    }

    private function actorId(): int
    {
        if (function_exists('evo')) {
            return (int) evo()->getLoginUserID('mgr');
        }
        return 0;  // CLI / test environment
    }
}
