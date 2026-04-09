<?php

namespace Saniock\EvoAccess\Services\PermissionParsers;

/**
 * Named registry of permission-discovery parsers. Consumers look up
 * parsers by short name via `get()` when processing the
 * `config('evoAccess.permission_discovery')` rules.
 *
 * Built-in parsers:
 *   - 'menu_tree' → MenuTreeParser (tree-shaped module config, see
 *                   MenuTreeParser's class docblock for an example)
 *
 * Custom parsers can be added by the consumer project at any time via
 * `register()` — typically from a service provider boot() method:
 *
 *     app(ParserRegistry::class)->register('my_format', new MyFormatParser());
 */
class ParserRegistry
{
    /**
     * @var array<string, ParserInterface>
     */
    private array $parsers = [];

    public function __construct()
    {
        // Built-in parsers.
        $this->parsers['menu_tree'] = new MenuTreeParser();
    }

    public function register(string $name, ParserInterface $parser): void
    {
        $this->parsers[$name] = $parser;
    }

    public function get(string $name): ?ParserInterface
    {
        return $this->parsers[$name] ?? null;
    }
}
