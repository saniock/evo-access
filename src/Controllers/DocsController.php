<?php

namespace Saniock\EvoAccess\Controllers;

use Saniock\EvoAccess\Services\AccessService;
use Saniock\EvoAccess\Support\MarkdownRenderer;

class DocsController extends BaseController
{
    public function __construct(AccessService $access)
    {
        parent::__construct($access);
        // Permission check lives in the eaaccess.permission route middleware.
    }

    /**
     * Render the documentation page. Reads markdown files from
     * docs/{locale}/*.md inside the package. Sections are ordered
     * alphabetically by filename — use numeric prefixes to control
     * order (e.g. "01-overview.md", "02-roles.md").
     */
    public function index(?string $section = null)
    {
        $locale = $this->resolveLocale();
        $docsDir = dirname(__DIR__, 2) . '/docs/' . $locale;

        // Fallback to English if the requested locale has no docs
        if (! is_dir($docsDir)) {
            $docsDir = dirname(__DIR__, 2) . '/docs/en';
        }

        $sections = $this->loadSections($docsDir);

        if (empty($sections)) {
            return view('evoAccess::docs', [
                'sections' => [],
                'currentSlug' => null,
                'html' => '<p><em>No documentation available for this locale.</em></p>',
            ]);
        }

        $currentSlug = $section && isset($sections[$section])
            ? $section
            : array_key_first($sections);

        $file = $sections[$currentSlug]['file'];
        $markdown = (string) file_get_contents($file);
        $html = MarkdownRenderer::render($markdown);

        return view('evoAccess::docs', [
            'sections' => $sections,
            'currentSlug' => $currentSlug,
            'html' => $html,
        ]);
    }

    /**
     * Resolve the active docs locale. Maps 'ua' to 'uk' so the package
     * stays ISO-compliant even if the consumer still uses 'ua' as its
     * locale string. Falls back to 'uk' if the app locale is missing.
     */
    private function resolveLocale(): string
    {
        $locale = app()->getLocale() ?: 'uk';
        return $locale === 'ua' ? 'uk' : $locale;
    }

    /**
     * Load section metadata from the docs directory. Each .md file becomes
     * a section. Title is extracted from the first H1, falling back to the
     * slug if no H1 is found.
     *
     * @return array<string, array{slug: string, title: string, file: string}>
     */
    private function loadSections(string $docsDir): array
    {
        $files = glob($docsDir . '/*.md') ?: [];
        sort($files);

        $sections = [];
        foreach ($files as $file) {
            $slug = basename($file, '.md');
            $content = (string) file_get_contents($file);
            $title = $slug;
            if (preg_match('/^#\s+(.+)$/m', $content, $m)) {
                $title = trim($m[1]);
            }
            $sections[$slug] = [
                'slug' => $slug,
                'title' => $title,
                'file' => $file,
            ];
        }

        return $sections;
    }
}
