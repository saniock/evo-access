<?php

namespace Saniock\EvoAccess\Observers;

use Saniock\EvoAccess\Models\UserOverride;
use Saniock\EvoAccess\Services\AuditLogger;
use Saniock\EvoAccess\Services\PermissionResolver;

class UserOverrideObserver
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    public function created(UserOverride $override): void
    {
        $this->audit->logOverrideAdded(
            $this->actorId(),
            $override->user_id,
            $override->permission_id,
            $override->action,
            $override->mode,
            $override->reason,
        );
        $this->resolver->forgetUser($override->user_id);
    }

    public function deleted(UserOverride $override): void
    {
        $this->audit->logOverrideRemoved(
            $this->actorId(),
            $override->user_id,
            $override->permission_id,
            $override->action,
        );
        $this->resolver->forgetUser($override->user_id);
    }

    private function actorId(): int
    {
        if (function_exists('evo')) {
            return (int) evo()->getLoginUserID('mgr');
        }
        return 0;
    }
}
