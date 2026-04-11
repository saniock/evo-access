<?php

namespace Saniock\EvoAccess\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Saniock\EvoAccess\Services\AccessService;

/**
 * Route middleware gating /access/* pages by an evo-access permission.
 *
 * Usage in routes.php:
 *     Route::get('roles', ...)->middleware('eaaccess.permission:access.roles');
 *
 * Why a middleware and not a constructor check:
 *
 *  - The permission check was previously inside BaseController
 *    constructors via ensureAccess(). When it threw HttpException,
 *    the exception fired *during* controller construction — which in
 *    EVO happens inside `gatherRouteMiddleware()`, but EVO's own
 *    Core::processRoutes() wraps the entire dispatch in its own
 *    try/catch and renders a full "Evolution CMS Parse Error" page
 *    instead of a clean 403. Very ugly.
 *
 *  - Returning a Response from a middleware short-circuits the route
 *    stack cleanly. No exception escapes, no EVO catch-all ever sees
 *    it, and the consumer gets a proper 403 response.
 */
class AccessGate
{
    public function __construct(
        private readonly AccessService $access,
    ) {
    }

    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        $userId = function_exists('evo') ? (int) evo()->getLoginUserID('mgr') : 0;

        if ($userId <= 0) {
            return $this->denied($request, 401, 'Not authenticated.');
        }

        if (! $this->access->can($permission, 'view', $userId)) {
            return $this->denied($request, 403, 'Access denied.');
        }

        return $next($request);
    }

    /**
     * Return a 401/403 response shaped for the caller: JSON for AJAX
     * (XMLHttpRequest, Accept: application/json, or non-GET), HTML
     * for plain page loads so the manager sees a readable error.
     */
    private function denied(Request $request, int $status, string $message): mixed
    {
        $isJson = $request->expectsJson()
            || $request->ajax()
            || ! $request->isMethod('GET');

        if ($isJson) {
            return new JsonResponse(
                ['success' => false, 'error' => $message],
                $status,
            );
        }

        $html = $this->renderErrorPage($status, $message);
        return new Response($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function renderErrorPage(int $status, string $message): string
    {
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $title = $status === 401 ? 'Not authenticated' : 'Access denied';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$status} — {$title}</title>
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
               background: #1a1a2e; color: #e0e0e0; display: flex; align-items: center;
               justify-content: center; min-height: 100vh; }
        .card { max-width: 480px; padding: 40px; background: #16213e; border-radius: 12px;
                border: 1px solid #333; text-align: center; }
        .code { font-size: 72px; font-weight: 700; margin: 0; color: #e94560; line-height: 1; }
        .title { font-size: 22px; margin: 16px 0 12px; color: #7eb8da; }
        .msg { font-size: 14px; color: #999; margin: 0 0 24px; }
        .btn { display: inline-block; padding: 10px 24px; background: #0f3460; color: #7eb8da;
               border-radius: 6px; text-decoration: none; font-size: 13px; border: 1px solid #444;
               transition: all 0.15s; }
        .btn:hover { background: #e94560; color: #fff; border-color: #e94560; }
    </style>
</head>
<body>
    <div class="card">
        <p class="code">{$status}</p>
        <p class="title">{$title}</p>
        <p class="msg">{$safeMessage}</p>
        <a href="/" class="btn">&larr; Back to site</a>
    </div>
</body>
</html>
HTML;
    }
}
