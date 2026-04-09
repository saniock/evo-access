<?php

namespace Saniock\EvoAccess\Tests\Unit\Services\PermissionParsers;

use PHPUnit\Framework\TestCase;
use Saniock\EvoAccess\Services\PermissionParsers\MenuTreeParser;

class MenuTreeParserTest extends TestCase
{
    private MenuTreeParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MenuTreeParser();
    }

    public function test_empty_config_returns_empty_array(): void
    {
        $this->assertSame([], $this->parser->extract([], 'orders'));
        $this->assertSame([], $this->parser->extract(['menu' => []], 'orders'));
    }

    public function test_top_level_leaf_with_actions_produces_prefixed_slug(): void
    {
        $config = [
            'menu' => [
                [
                    'id'      => 'orders',
                    'title'   => 'Orders',
                    'actions' => ['view'],
                ],
            ],
        ];

        $permissions = $this->parser->extract($config, 'orders');

        $this->assertCount(1, $permissions);
        $this->assertSame('orders.orders', $permissions[0]['name']);
        $this->assertSame('Orders', $permissions[0]['label']);
        $this->assertSame(['view'], $permissions[0]['actions']);
    }

    public function test_group_with_nested_leaves_emits_dotted_slugs(): void
    {
        $config = [
            'menu' => [
                [
                    'id'    => 'sales',
                    'title' => 'Sales',
                    'items' => [
                        [
                            'id'      => 'invoices',
                            'title'   => 'Invoices',
                            'actions' => ['view', 'create', 'void'],
                        ],
                        [
                            'id'      => 'gift-cards',
                            'title'   => 'Gift cards',
                            'actions' => ['view', 'edit'],
                        ],
                    ],
                ],
            ],
        ];

        $permissions = $this->parser->extract($config, 'orders');

        $this->assertCount(2, $permissions);

        $this->assertSame('orders.sales.invoices', $permissions[0]['name']);
        $this->assertSame('Invoices', $permissions[0]['label']);
        $this->assertSame(['view', 'create', 'void'], $permissions[0]['actions']);

        $this->assertSame('orders.sales.gift-cards', $permissions[1]['name']);
        $this->assertSame('Gift cards', $permissions[1]['label']);
        $this->assertSame(['view', 'edit'], $permissions[1]['actions']);
    }

    public function test_group_itself_does_not_become_a_permission(): void
    {
        $config = [
            'menu' => [
                [
                    'id'    => 'sales',
                    'title' => 'Sales',
                    'items' => [
                        [
                            'id'      => 'invoices',
                            'title'   => 'Invoices',
                            'actions' => ['view'],
                        ],
                    ],
                ],
            ],
        ];

        $permissions = $this->parser->extract($config, 'orders');

        $this->assertCount(1, $permissions);
        $this->assertSame('orders.sales.invoices', $permissions[0]['name']);
    }

    public function test_declared_but_empty_items_array_is_treated_as_group(): void
    {
        $config = [
            'menu' => [
                [
                    'id'      => 'ghost_group',
                    'title'   => 'Empty group',
                    'items'   => [],
                    'actions' => ['view', 'edit'],
                ],
                [
                    'id'      => 'visible',
                    'title'   => 'Visible leaf',
                    'actions' => ['view'],
                ],
            ],
        ];

        $permissions = $this->parser->extract($config, 'mod');

        // The empty-items entry must be treated as a group (recurse into
        // nothing, emit nothing) rather than as a leaf. Even though it
        // declares 'actions', the presence of the 'items' key — even if
        // the array is empty — marks it as a container.
        $this->assertCount(1, $permissions);
        $this->assertSame('mod.visible', $permissions[0]['name']);
    }

    public function test_leaf_without_actions_is_skipped(): void
    {
        $config = [
            'menu' => [
                [
                    'id'    => 'settings',
                    'title' => 'Settings',
                ],
                [
                    'id'      => 'products',
                    'title'   => 'Products',
                    'actions' => ['view'],
                ],
            ],
        ];

        $permissions = $this->parser->extract($config, 'mymod');

        $this->assertCount(1, $permissions);
        $this->assertSame('mymod.products', $permissions[0]['name']);
    }

    public function test_item_without_id_is_skipped(): void
    {
        $config = [
            'menu' => [
                [
                    'title'   => 'Broken',
                    'actions' => ['view'],
                ],
                [
                    'id'      => 'good',
                    'title'   => 'Good',
                    'actions' => ['view'],
                ],
            ],
        ];

        $permissions = $this->parser->extract($config, 'mymod');

        $this->assertCount(1, $permissions);
        $this->assertSame('mymod.good', $permissions[0]['name']);
    }

    public function test_multi_level_nesting_produces_dotted_path(): void
    {
        $config = [
            'menu' => [
                [
                    'id'    => 'level1',
                    'items' => [
                        [
                            'id'    => 'level2',
                            'items' => [
                                [
                                    'id'      => 'level3',
                                    'title'   => 'Deep leaf',
                                    'actions' => ['view'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $permissions = $this->parser->extract($config, 'mod');

        $this->assertCount(1, $permissions);
        $this->assertSame('mod.level1.level2.level3', $permissions[0]['name']);
    }

    public function test_label_falls_back_to_slug_when_title_missing(): void
    {
        $config = [
            'menu' => [
                [
                    'id'      => 'headless',
                    'actions' => ['view'],
                ],
            ],
        ];

        $permissions = $this->parser->extract($config, 'mod');

        $this->assertCount(1, $permissions);
        $this->assertSame('mod.headless', $permissions[0]['label']);
    }
}
