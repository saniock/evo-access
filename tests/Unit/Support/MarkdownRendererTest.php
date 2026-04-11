<?php

namespace Saniock\EvoAccess\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Saniock\EvoAccess\Support\MarkdownRenderer;

class MarkdownRendererTest extends TestCase
{
    public function test_renders_headings(): void
    {
        $html = MarkdownRenderer::render("# H1\n## H2\n### H3");
        $this->assertStringContainsString('<h1>H1</h1>', $html);
        $this->assertStringContainsString('<h2>H2</h2>', $html);
        $this->assertStringContainsString('<h3>H3</h3>', $html);
    }

    public function test_renders_bold_and_italic(): void
    {
        $html = MarkdownRenderer::render('This is **bold** and *italic*.');
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<em>italic</em>', $html);
    }

    public function test_renders_inline_code(): void
    {
        $html = MarkdownRenderer::render('Use `some code` here.');
        $this->assertStringContainsString('<code>some code</code>', $html);
    }

    public function test_inline_code_is_not_further_parsed(): void
    {
        // **bold** inside backticks should stay literal
        $html = MarkdownRenderer::render('Example: `**not bold**`');
        $this->assertStringContainsString('<code>**not bold**</code>', $html);
        $this->assertStringNotContainsString('<strong>', $html);
    }

    public function test_renders_fenced_code_block(): void
    {
        $md = "```php\n<?php echo 'hi';\n```";
        $html = MarkdownRenderer::render($md);
        $this->assertStringContainsString('<pre><code class="language-php">', $html);
        $this->assertStringContainsString("&lt;?php echo &#039;hi&#039;;", $html);
    }

    public function test_renders_unordered_list(): void
    {
        $html = MarkdownRenderer::render("- one\n- two\n- three");
        $this->assertStringContainsString('<ul><li>one</li><li>two</li><li>three</li></ul>', $html);
    }

    public function test_renders_ordered_list(): void
    {
        $html = MarkdownRenderer::render("1. first\n2. second\n3. third");
        $this->assertStringContainsString('<ol><li>first</li><li>second</li><li>third</li></ol>', $html);
    }

    public function test_renders_link(): void
    {
        $html = MarkdownRenderer::render('See [example](https://example.com) for more.');
        $this->assertStringContainsString('<a href="https://example.com"', $html);
        $this->assertStringContainsString('>example</a>', $html);
    }

    public function test_renders_blockquote(): void
    {
        $html = MarkdownRenderer::render("> quoted text");
        $this->assertStringContainsString('<blockquote>quoted text</blockquote>', $html);
    }

    public function test_renders_horizontal_rule(): void
    {
        $html = MarkdownRenderer::render("---");
        $this->assertStringContainsString('<hr>', $html);
    }

    public function test_renders_paragraph(): void
    {
        $html = MarkdownRenderer::render("Just a plain paragraph.");
        $this->assertStringContainsString('<p>Just a plain paragraph.</p>', $html);
    }

    public function test_escapes_html_in_content(): void
    {
        $html = MarkdownRenderer::render('Some <script>alert(1)</script> text');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_renders_complex_document(): void
    {
        $md = "# Title\n\nFirst paragraph with **bold**.\n\n## Section\n\n- Item 1\n- Item 2 with `code`\n\nAnother paragraph.";
        $html = MarkdownRenderer::render($md);
        $this->assertStringContainsString('<h1>Title</h1>', $html);
        $this->assertStringContainsString('<h2>Section</h2>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<ul><li>Item 1</li>', $html);
        $this->assertStringContainsString('<code>code</code>', $html);
    }
}
