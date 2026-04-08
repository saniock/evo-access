<?php

namespace Saniock\EvoAccess\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Models\Role;
use Saniock\EvoAccess\Models\RolePermissionAction;
use Saniock\EvoAccess\Models\UserOverride;
use Saniock\EvoAccess\Models\UserRole;
use Saniock\EvoAccess\Services\PermissionResolver;
use Saniock\EvoAccess\Tests\TestCase;

class ResolverTruthTableTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSION_NAME = 'orders.orders';
    private const ACTION = 'export';
    private const USER_ID = 7;

    /**
     * 16 truth-table cases from design.md §7.2
     *
     * Returns: [is_system, has_revoke, has_grant, has_role, expected]
     */
    public static function truthTableProvider(): array
    {
        return [
            'case 1:  sys=1 rev=1 grnt=1 role=1' => [true,  true,  true,  true,  true],
            'case 2:  sys=1 rev=1 grnt=1 role=0' => [true,  true,  true,  false, true],
            'case 3:  sys=1 rev=1 grnt=0 role=1' => [true,  true,  false, true,  true],
            'case 4:  sys=1 rev=1 grnt=0 role=0' => [true,  true,  false, false, true],
            'case 5:  sys=1 rev=0 grnt=1 role=1' => [true,  false, true,  true,  true],
            'case 6:  sys=1 rev=0 grnt=1 role=0' => [true,  false, true,  false, true],
            'case 7:  sys=1 rev=0 grnt=0 role=1' => [true,  false, false, true,  true],
            'case 8:  sys=1 rev=0 grnt=0 role=0' => [true,  false, false, false, true],
            'case 9:  sys=0 rev=1 grnt=1 role=1' => [false, true,  true,  true,  false],
            'case 10: sys=0 rev=1 grnt=1 role=0' => [false, true,  true,  false, false],
            'case 11: sys=0 rev=1 grnt=0 role=1' => [false, true,  false, true,  false],
            'case 12: sys=0 rev=1 grnt=0 role=0' => [false, true,  false, false, false],
            'case 13: sys=0 rev=0 grnt=1 role=1' => [false, false, true,  true,  true],
            'case 14: sys=0 rev=0 grnt=1 role=0' => [false, false, true,  false, true],
            'case 15: sys=0 rev=0 grnt=0 role=1' => [false, false, false, true,  true],
            'case 16: sys=0 rev=0 grnt=0 role=0' => [false, false, false, false, false],
        ];
    }

    /**
     * @dataProvider truthTableProvider
     */
    public function test_truth_table_case(
        bool $isSystem,
        bool $hasRevoke,
        bool $hasGrant,
        bool $hasRole,
        bool $expected,
    ): void {
        $role = $isSystem
            ? Role::where('name', 'superadmin')->firstOrFail()
            : Role::create(['name' => 'manager', 'label' => 'M']);

        UserRole::create(['user_id' => self::USER_ID, 'role_id' => $role->id]);

        $perm = Permission::create([
            'name'    => self::PERMISSION_NAME,
            'label'   => 'L',
            'module'  => 'orders',
            'actions' => ['view', self::ACTION],
        ]);

        if ($hasRole) {
            RolePermissionAction::create([
                'role_id'       => $role->id,
                'permission_id' => $perm->id,
                'action'        => self::ACTION,
            ]);
        }

        if ($hasGrant) {
            UserOverride::create([
                'user_id'       => self::USER_ID,
                'permission_id' => $perm->id,
                'action'        => self::ACTION,
                'mode'          => 'grant',
                'reason'        => 'test',
            ]);
        }

        if ($hasRevoke) {
            // Schema PK doesn't include mode — can't have grant+revoke for same
            // (user, perm, action) triple. Delete existing grant before inserting revoke.
            UserOverride::where([
                'user_id'       => self::USER_ID,
                'permission_id' => $perm->id,
                'action'        => self::ACTION,
            ])->delete();

            UserOverride::create([
                'user_id'       => self::USER_ID,
                'permission_id' => $perm->id,
                'action'        => self::ACTION,
                'mode'          => 'revoke',
                'reason'        => 'test',
            ]);
        }

        $resolver = $this->app->make(PermissionResolver::class);
        $result = $resolver->userHas(self::USER_ID, self::PERMISSION_NAME, self::ACTION);

        $this->assertSame($expected, $result);
    }
}
