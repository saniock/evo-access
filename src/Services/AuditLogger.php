<?php

namespace Saniock\EvoAccess\Services;

/**
 * Writes audit entries for access-control mutations.
 *
 * Hooked in via Eloquent observers on Role, RolePermissionAction,
 * and UserOverride models — every grant/revoke/rename/delete goes
 * through one of those models, which notifies this logger.
 *
 * Entries land in ea_audit_log and are surfaced in the admin UI
 * audit section with filters by actor/target/date.
 */
class AuditLogger
{
    /**
     * Record a generic audit entry.
     *
     * $action: 'grant' | 'revoke' | 'create' | 'delete' | 'update' | 'rename'
     *
     * @param  array<string, mixed>  $details  Structured payload (old/new values, reason, etc).
     */
    public function log(
        int $actorUserId,
        string $action,
        ?int $targetRoleId = null,
        ?int $targetUserId = null,
        ?int $permissionId = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        array $details = [],
    ): void {
        // TODO: insert row into ea_audit_log.
    }
}
