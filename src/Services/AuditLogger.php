<?php

namespace Saniock\EvoAccess\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Saniock\EvoAccess\Models\AuditLog;
use Saniock\EvoAccess\Models\Role;

/**
 * Writes audit entries for access-control mutations.
 *
 * Hooked in via Eloquent observers on Role, RolePermissionAction,
 * UserRole, and UserOverride models — every grant/revoke/rename/delete
 * goes through one of those models, which notifies this logger.
 *
 * Entries land in ea_audit_log and are surfaced in the admin UI
 * audit section with filters by actor/target/date.
 */
class AuditLogger
{
    /**
     * Generic write — used directly only for non-standard events.
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
        AuditLog::create([
            'actor_user_id'  => $actorUserId,
            'action'         => $action,
            'target_role_id' => $targetRoleId,
            'target_user_id' => $targetUserId,
            'permission_id'  => $permissionId,
            'old_value'      => $oldValue,
            'new_value'      => $newValue,
            'details'        => $details ?: null,
        ]);
    }

    // ─── Type-safe wrappers ──────────────────────────────────────────────

    public function logRoleCreated(int $actorId, Role $role): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'role_created',
            targetRoleId: $role->id,
            newValue: $role->name,
            details: ['label' => $role->label],
        );
    }

    public function logRoleRenamed(int $actorId, Role $role, string $oldLabel, string $newLabel): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'role_renamed',
            targetRoleId: $role->id,
            oldValue: $oldLabel,
            newValue: $newLabel,
        );
    }

    public function logRoleDeleted(int $actorId, Role $role): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'role_deleted',
            targetRoleId: $role->id,
            oldValue: $role->name,
        );
    }

    public function logRoleCloned(int $actorId, Role $sourceRole, Role $newRole): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'role_cloned',
            targetRoleId: $newRole->id,
            details: ['source_role_id' => $sourceRole->id, 'source_name' => $sourceRole->name],
        );
    }

    public function logGrant(int $actorId, int $roleId, int $permissionId, string $action): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'grant',
            targetRoleId: $roleId,
            permissionId: $permissionId,
            newValue: $action,
        );
    }

    public function logRevoke(int $actorId, int $roleId, int $permissionId, string $action): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'revoke',
            targetRoleId: $roleId,
            permissionId: $permissionId,
            oldValue: $action,
        );
    }

    public function logUserAssigned(int $actorId, int $userId, int $roleId): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'user_assigned',
            targetRoleId: $roleId,
            targetUserId: $userId,
        );
    }

    public function logUserUnassigned(int $actorId, int $userId, int $oldRoleId): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'user_unassigned',
            targetRoleId: $oldRoleId,
            targetUserId: $userId,
        );
    }

    public function logUserRoleChanged(int $actorId, int $userId, int $oldRoleId, int $newRoleId): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'user_role_changed',
            targetRoleId: $newRoleId,
            targetUserId: $userId,
            details: ['old_role_id' => $oldRoleId, 'new_role_id' => $newRoleId],
        );
    }

    public function logOverrideAdded(int $actorId, int $userId, int $permissionId, string $action, string $mode, ?string $reason): void
    {
        $this->log(
            actorUserId: $actorId,
            action: "override_$mode",
            targetUserId: $userId,
            permissionId: $permissionId,
            newValue: $action,
            details: ['reason' => $reason],
        );
    }

    public function logOverrideRemoved(int $actorId, int $userId, int $permissionId, string $action): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'override_removed',
            targetUserId: $userId,
            permissionId: $permissionId,
            oldValue: $action,
        );
    }

    public function logPermissionsSync(int $actorId, int $created, int $updated, int $orphaned): void
    {
        $this->log(
            actorUserId: $actorId,
            action: 'permissions_sync',
            details: ['created' => $created, 'updated' => $updated, 'orphaned' => $orphaned],
        );
    }

    // ─── Read API ────────────────────────────────────────────────────────

    public function entriesForUser(int $userId, int $limit = 100, int $offset = 0): Collection
    {
        return AuditLog::where('target_user_id', $userId)
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    public function entriesForRole(int $roleId, int $limit = 100, int $offset = 0): Collection
    {
        return AuditLog::where('target_role_id', $roleId)
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    public function entriesByActor(int $actorId, int $limit = 100, int $offset = 0): Collection
    {
        return AuditLog::where('actor_user_id', $actorId)
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    public function search(array $filters, int $limit = 100, int $offset = 0): Collection
    {
        $query = AuditLog::query();

        if (!empty($filters['actor_user_id'])) {
            $query->where('actor_user_id', $filters['actor_user_id']);
        }
        if (!empty($filters['target_user_id'])) {
            $query->where('target_user_id', $filters['target_user_id']);
        }
        if (!empty($filters['target_role_id'])) {
            $query->where('target_role_id', $filters['target_role_id']);
        }
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    public function recent(int $limit = 100): Collection
    {
        return AuditLog::orderByDesc('created_at')->limit($limit)->get();
    }
}
