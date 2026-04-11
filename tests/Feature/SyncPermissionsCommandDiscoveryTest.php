<?php

namespace Saniock\EvoAccess\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Models\Permission;
use Saniock\EvoAccess\Tests\TestCase;

class SyncPermissionsCommandDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private string $tmpModulesDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Scratch directory simulating a consumer's modules directory
        $this->tmpModulesDir = sys_get_temp_dir() . '/evoaccess-sync-test-' . uniqid();
        mkdir($this->tmpModulesDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpModulesDir)) {
            $this->deleteDirectory($this->tmpModulesDir);
        }
        parent::tearDown();
    }

    public function test_command_discovers_permissions_from_menu_tree_config_files(): void
    {
        $this->writeModuleConfig('Orders', <<<'PHP'
<?php
return [
    'menu' => [
        [
            'id' => 'orders',
            'title' => 'Orders',
            'actions' => ['view'],
        ],
        [
            'id' => 'sales',
            'title' => 'Sales',
            'items' => [
                [
                    'id' => 'invoices',
                    'title' => 'Invoices',
                    'actions' => ['view', 'create', 'void'],
                ],
                [
                    'id' => 'gift-cards',
                    'title' => 'Gift cards',
                    'actions' => ['view', 'edit'],
                ],
            ],
        ],
    ],
];
PHP);

        $this->writeModuleConfig('Inventory', <<<'PHP'
<?php
return [
    'menu' => [
        [
            'id' => 'stock',
            'title' => 'Stock levels',
            'actions' => ['view', 'export'],
        ],
    ],
];
PHP);

        config([
            'evoAccess.permission_discovery' => [
                [
                    'path'   => $this->tmpModulesDir,
                    'glob'   => '*/config/config.php',
                    'parser' => 'menu_tree',
                ],
            ],
        ]);

        $this->artisan('evoaccess:sync-permissions')
            ->assertExitCode(0);

        $this->assertDatabaseHas('ea_permissions', [
            'name'  => 'orders.orders',
            'label' => 'Orders',
        ]);
        $this->assertDatabaseHas('ea_permissions', [
            'name'  => 'orders.sales.invoices',
            'label' => 'Sales → Invoices',
        ]);
        $this->assertDatabaseHas('ea_permissions', [
            'name'  => 'orders.sales.gift-cards',
            'label' => 'Sales → Gift cards',
        ]);
        $this->assertDatabaseHas('ea_permissions', [
            'name'  => 'inventory.stock',
            'label' => 'Stock levels',
        ]);

        $this->assertSame(3, Permission::where('module', 'orders')->count());
        $this->assertSame(1, Permission::where('module', 'inventory')->count());

        // Sanity: no stray rows accidentally landed under our test module slugs.
        // The 4 discovered rows are the only ones that should carry these modules.
        // Additional rows the SP may have seeded will be under OTHER modules (e.g. 'access'),
        // which is fine and expected.
        $this->assertSame(
            4,
            Permission::whereIn('module', ['orders', 'inventory'])->count()
        );
    }

    public function test_command_handles_empty_discovery_config_gracefully(): void
    {
        config(['evoAccess.permission_discovery' => []]);

        $this->artisan('evoaccess:sync-permissions')
            ->expectsOutputToContain('No permission_discovery rules configured')
            ->assertExitCode(0);
    }

    public function test_command_warns_on_unknown_parser_name(): void
    {
        $this->writeModuleConfig('Mod', "<?php return ['menu' => []];");

        config([
            'evoAccess.permission_discovery' => [
                [
                    'path'   => $this->tmpModulesDir,
                    'glob'   => '*/config/config.php',
                    'parser' => 'does_not_exist',
                ],
            ],
        ]);

        $this->artisan('evoaccess:sync-permissions')
            ->expectsOutputToContain("unknown parser 'does_not_exist'")
            ->assertExitCode(0);
    }

    public function test_command_skips_missing_path_gracefully(): void
    {
        config([
            'evoAccess.permission_discovery' => [
                [
                    'path'   => '/nonexistent/directory/' . uniqid(),
                    'glob'   => '*/config/config.php',
                    'parser' => 'menu_tree',
                ],
            ],
        ]);

        $this->artisan('evoaccess:sync-permissions')
            ->expectsOutputToContain('path not found')
            ->assertExitCode(0);
    }

    private function writeModuleConfig(string $moduleName, string $contents): void
    {
        $dir = $this->tmpModulesDir . '/' . $moduleName . '/config';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/config.php', $contents);
    }

    private function deleteDirectory(string $dir): void
    {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
