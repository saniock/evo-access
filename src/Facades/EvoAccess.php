<?php

namespace Saniock\EvoAccess\Facades;

use Illuminate\Support\Facades\Facade;
use Saniock\EvoAccess\Services\AccessService;

/**
 * Static facade for the access service.
 *
 * Usage:
 *   use Saniock\EvoAccess\Facades\EvoAccess;
 *
 *   if (!EvoAccess::can('orders.orders', 'refund', $userId)) {
 *       abort(403);
 *   }
 *
 * @method static bool can(string $permission, string $action, int $userId)
 * @method static bool canView(array $menu, string $actionId, int $userId)
 * @method static bool canEdit(array $menu, string $actionId, int $userId)
 * @method static array filterMenu(array $menu, int $userId)
 * @method static array actionsFor(string $permission, int $userId)
 */
class EvoAccess extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AccessService::class;
    }
}
