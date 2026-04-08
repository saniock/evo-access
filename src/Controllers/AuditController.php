<?php

namespace Saniock\EvoAccess\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Saniock\EvoAccess\Services\AuditLogger;

class AuditController extends BaseController
{
    public function index()
    {
        return view('evoAccess::audit');
    }

    public function data(Request $request, AuditLogger $audit): JsonResponse
    {
        $filters = $request->only(['actor_user_id', 'target_user_id', 'target_role_id', 'action', 'from', 'to']);
        $limit = (int) ($request->input('limit', 100));
        $offset = (int) ($request->input('offset', 0));

        return response()->json($audit->search($filters, $limit, $offset));
    }
}
