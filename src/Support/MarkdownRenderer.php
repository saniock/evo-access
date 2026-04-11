<?php

namespace Saniock\EvoAccess\Support;

/**
 * Minimal Markdown renderer for evo-access documentation.
 *
 * Supports:
 * - Headings (# to ######)
 * - Bold (**text**) and italic (*text*)
 * - Inline code (`code`)
 * - Fenced code blocks (```lang ... ```)
 * - Unordered lists (- or *)
 * - Ordered lists (1. 2. 3.)
 * - Links [text](url)
 * - Blockquotes (> text)
 * - Horizontal rules (---)
 * - Paragraphs
 *
 * Not a full CommonMark implementation — deliberately minimal so the
 * package has zero external markdown dependencies. Good enough for
 * the admin documentation pages.
 */
class MarkdownRenderer
{
    public static function render(string $markdown): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $markdown));
        $html = [];
        $n = count($lines);
        $i = 0;

        while ($i < $n) {
            $line = $lines[$i];

            // Fenced code block
            if (preg_match('/^```(\w*)$/', $line, $m)) {
                $lang = $m[1];
                $i++;
                $code = [];
                while ($i < $n && !preg_match('/^```\s*$/', $lines[$i])) {
                    $code[] = htmlspecialchars($lines[$i], ENT_QUOTES, 'UTF-8');
                    $i++;
                }
                $i++; // skip closing fence
                $classAttr = $lang !== '' ? ' class="language-' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"' : '';
                $html[] = '<pre><code' . $classAttr . '>' . implode("\n", $code) . '</code></pre>';
                continue;
            }

            // Heading
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
                $level = strlen($m[1]);
                $text = self::renderInline(trim($m[2]));
                $html[] = "<h{$level}>{$text}</h{$level}>";
                $i++;
                continue;
            }

            // Horizontal rule
            if (preg_match('/^-{3,}\s*$/', $line)) {
                $html[] = '<hr>';
                $i++;
                continue;
            }

            // Blockquote (consumes consecutive > lines)
            if (preg_match('/^>\s?(.*)$/', $line, $m)) {
                $quoteLines = [$m[1]];
                $i++;
                while ($i < $n && preg_match('/^>\s?(.*)$/', $lines[$i], $m2)) {
                    $quoteLines[] = $m2[1];
                    $i++;
                }
                $html[] = '<blockquote>' . self::renderInline(implode(' ', $quoteLines)) . '</blockquote>';
                continue;
            }

            // Unordered list
            if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
                $items = [$m[1]];
                $i++;
                while ($i < $n && preg_match('/^[-*]\s+(.+)$/', $lines[$i], $m2)) {
                    $items[] = $m2[1];
                    $i++;
                }
                $html[] = '<ul>' . implode('', array_map(
                    fn ($item) => '<li>' . self::renderInline($item) . '</li>',
                    $items,
                )) . '</ul>';
                continue;
            }

            // Ordered list
            if (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
                $items = [$m[1]];
                $i++;
                while ($i < $n && preg_match('/^\d+\.\s+(.+)$/', $lines[$i], $m2)) {
                    $items[] = $m2[1];
                    $i++;
                }
                $html[] = '<ol>' . implode('', array_map(
                    fn ($item) => '<li>' . self::renderInline($item) . '</li>',
                    $items,
                )) . '</ol>';
                continue;
            }

            // Blank line — paragraph separator
            if (trim($line) === '') {
                $i++;
                continue;
            }

            // Paragraph: collect consecutive non-block lines
            $paraLines = [$line];
            $i++;
            while ($i < $n && trim($lines[$i]) !== '' && ! self::isBlockStart($lines[$i])) {
                $paraLines[] = $lines[$i];
                $i++;
            }
            $html[] = '<p>' . self::renderInline(implode(' ', $paraLines)) . '</p>';
        }

        return implode("\n", $html);
    }

    private static function isBlockStart(string $line): bool
    {
        return (bool) preg_match('/^(#{1,6}\s|-{3,}\s*$|[-*]\s|\d+\.\s|>|```)/', $line);
    }

    private static function renderInline(string $text): string
    {
        // Escape HTML before inserting markup
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // Inline code — protect contents from further substitution
        $codeMap = [];
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codeMap) {
            $key = "\x00CODE" . count($codeMap) . "\x00";
            $codeMap[$key] = '<code>' . $m[1] . '</code>';
            return $key;
        }, $text);

        // Images ![alt](url) — must come before links
        $text = preg_replace(
            '/!\[([^\]]*)\]\(([^)]+)\)/',
            '<img src="$2" alt="$1">',
            $text,
        );

        // Links [text](url)
        $text = preg_replace(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            '<a href="$2" target="_blank" rel="noopener">$1</a>',
            $text,
        );

        // Bold **text**
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);

        // Italic *text* (avoid interfering with bold by using negative lookarounds)
        $text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $text);

        // Restore inline code
        foreach ($codeMap as $key => $value) {
            $text = str_replace($key, $value, $text);
        }

        return $text;
    }
}
