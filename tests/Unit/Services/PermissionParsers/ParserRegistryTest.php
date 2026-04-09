<?php

namespace Saniock\EvoAccess\Tests\Unit\Services\PermissionParsers;

use PHPUnit\Framework\TestCase;
use Saniock\EvoAccess\Services\PermissionParsers\MenuTreeParser;
use Saniock\EvoAccess\Services\PermissionParsers\ParserInterface;
use Saniock\EvoAccess\Services\PermissionParsers\ParserRegistry;

class ParserRegistryTest extends TestCase
{
    public function test_menu_tree_parser_is_registered_by_default(): void
    {
        $registry = new ParserRegistry();
        $parser = $registry->get('menu_tree');

        $this->assertInstanceOf(MenuTreeParser::class, $parser);
    }

    public function test_unknown_parser_name_returns_null(): void
    {
        $registry = new ParserRegistry();

        $this->assertNull($registry->get('does_not_exist'));
    }

    public function test_custom_parser_can_be_registered_and_retrieved(): void
    {
        $registry = new ParserRegistry();

        $custom = new class implements ParserInterface {
            public function extract(array $config, string $moduleSlug): array
            {
                return [];
            }
        };

        $registry->register('custom', $custom);

        $this->assertSame($custom, $registry->get('custom'));
    }

    public function test_register_overrides_existing_parser(): void
    {
        $registry = new ParserRegistry();

        $replacement = new class implements ParserInterface {
            public function extract(array $config, string $moduleSlug): array
            {
                return [];
            }
        };

        $registry->register('menu_tree', $replacement);

        $this->assertSame($replacement, $registry->get('menu_tree'));
    }
}
