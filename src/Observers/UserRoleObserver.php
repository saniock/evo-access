<?php

namespace Saniock\EvoAccess\Observers;

use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Services\AuditLogger;
use Saniock\EvoAccess\Services\PermissionResolver;

class UserRoleObserver
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    public function created(UserRole $assignment): void
    {
        $this->audit->logUserAssigned(
            $this->actorId(),
            $assignment->user_id,
            $assignment->role_id,
        );
        $this->resolver->forgetUser($assignment->user_id);
    }

    public function updated(UserRole $assignment): void
    {
        if ($assignment->wasChanged('role_id')) {
            $this->audit->logUserRoleChanged(
                $this->actorId(),
                $assignment->user_id,
                (int) $assignment->getOriginal('role_id'),
                $assignment->role_id,
            );
            $this->resolver->forgetUser($assignment->user_id);
        }
    }

    public function deleted(UserRole $assignment): void
    {
        $this->audit->logUserUnassigned(
            $this->actorId(),
            $assignment->user_id,
            $assignment->role_id,
        );
        $this->resolver->forgetUser($assignment->user_id);
    }

    private function actorId(): int
    {
        if (function_exists('evo')) {
            return (int) evo()->getLoginUserID('mgr');
        }
        return 0;
    }
}
