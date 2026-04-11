<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Http\JsonResponse;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Services\AccessService;

/**
 * TEMPORARY diagnostic endpoint. Returns the effective state of the
 * currently-logged-in user so we can debug "why do I have access" bugs.
 *
 * DELETE THIS CLASS BEFORE PRODUCTION. It leaks session internals
 * and should not ship to real users.
 */
class DebugController extends BaseController
{
    public function __construct(AccessService $access)
    {
        parent::__construct($access);
        // NOTE: deliberately NO ensureAccess() — we want this to run
        // for anyone so we can see what state they actually have.
    }

    public function diag(): JsonResponse
    {
        $evoUserId = function_exists('evo') ? (int) evo()->getLoginUserID('mgr') : 0;
        $resolver = $this->access->getResolver();

        $role = $resolver->roleOf($evoUserId);
        $map = $resolver->loadForUser($evoUserId);

        $assignmentRow = UserRole::where('user_id', $evoUserId)->first();

        return response()->json([
            'evo_login_user_id'  => $evoUserId,
            'session_keys' => [
                'mgrInternalKey' => $_SESSION['mgrInternalKey'] ?? null,
                'mgrValidated'   => $_SESSION['mgrValidated'] ?? null,
                'mgrRole'        => $_SESSION['mgrRole'] ?? null,
            ],
            'user_role_row' => $assignmentRow ? [
                'user_id' => $assignmentRow->user_id,
                'role_id' => $assignmentRow->role_id,
            ] : null,
            'resolved_role' => $role ? [
                'id'        => $role->id,
                'name'      => $role->name,
                'is_system' => (bool) $role->is_system,
            ] : null,
            'permission_map' => $map,
            'can_access_users_view' => $this->access->can('access.users', 'view', $evoUserId),
            'can_access_users_edit' => $this->access->can('access.users', 'edit', $evoUserId),
            'can_access_roles_view' => $this->access->can('access.roles', 'view', $evoUserId),
            'can_access_audit_view' => $this->access->can('access.audit', 'view', $evoUserId),
            'can_access_docs_view'  => $this->access->can('access.docs', 'view', $evoUserId),
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Actually invokes ensureAccess('access.docs'). If the gate is
     * working correctly, this endpoint aborts with 403 before ever
     * returning — so if you see the "gate_bypassed" message, it means
     * ensureAccess() ran, can() returned true (or silently threw and
     * was caught), which means the real gate is broken.
     */
    public function gate(): JsonResponse
    {
        try {
            $this->ensureAccess('access.docs');
            return response()->json([
                'result' => 'gate_bypassed',
                'message' => 'ensureAccess() did NOT abort — either can() returned true, or abort() is being suppressed.',
                'can_access_docs_view' => $this->access->can('access.docs', 'view', $this->currentUserId()),
                'current_user_id' => $this->currentUserId(),
            ], 200, [], JSON_PRETTY_PRINT);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'result' => 'gate_aborted',
                'status' => $e->getStatusCode(),
                'message' => $e->getMessage(),
            ], $e->getStatusCode(), [], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return response()->json([
                'result' => 'gate_threw',
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ], 500, [], JSON_PRETTY_PRINT);
        }
    }
}
