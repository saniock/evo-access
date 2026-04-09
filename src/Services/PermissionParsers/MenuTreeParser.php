<?php

namespace Saniock\EvoAccess\Services\PermissionParsers;

/**
 * Extracts evo-access permissions from a tree-shaped module config:
 *
 *     return [
 *         'menu' => [
 *             [
 *                 'id' => 'sales',
 *                 'title' => 'Sales',
 *                 'items' => [
 *                     ['id' => 'invoices', 'title' => 'Invoices', 'actions' => ['view', 'create', 'void']],
 *                 ],
 *             ],
 *         ],
 *     ];
 *
 * Rules:
 *   - Leaves (entries without `items`) that declare `actions` become
 *     permissions. Slug is joined with dots from `<moduleSlug>` down to
 *     the leaf's `id`.
 *   - Entries with `items` are grouping folders — they do NOT become
 *     permissions themselves; the parser recurses into children with
 *     the group's id appended to the path.
 *   - Missing/empty `actions` on a leaf means "open for all managers"
 *     and the parser simply skips it (no permission row emitted).
 *   - Items without an `id` are malformed and silently skipped.
 *   - The top-level recursion is seeded with the module slug as the
 *     starting path, so a first-level item with `id === moduleSlug`
 *     produces '<module>.<module>' (required by the evo-access
 *     min-two-segments regex).
 */
class MenuTreeParser implements ParserInterface
{
    public function extract(array $config, string $moduleSlug): array
    {
        $items = $config['menu'] ?? [];
        $out = [];
        $this->walk($items, $moduleSlug, $out);
        return $out;
    }

    /**
     * @param  array  $items       menu subtree being walked
     * @param  string $pathSoFar   slug accumulated from module down to
     *                             (but not including) the current item
     * @param  array  $out         collector, passed by reference
     */
    private function walk(array $items, string $pathSoFar, array &$out): void
    {
        foreach ($items as $item) {
            if (!isset($item['id']) || $item['id'] === '') {
                continue;
            }

            $slug = $pathSoFar . '.' . $item['id'];

            if (array_key_exists('items', $item) && is_array($item['items'])) {
                $this->walk($item['items'], $slug, $out);
                continue;
            }

            if (empty($item['actions'])) {
                continue;
            }

            $out[] = [
                'name'    => $slug,
                'label'   => $item['title'] ?? $slug,
                'actions' => array_values($item['actions']),
            ];
        }
    }
}
