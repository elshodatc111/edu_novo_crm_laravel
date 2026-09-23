<?php

namespace Tests\Unit;

use App\Support\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

/** v12: `/docs` sahifasi uchun o'zimizning Markdown → HTML render'i. */
class MarkdownRendererTest extends TestCase
{
    public function test_renders_headers(): void
    {
        $html = MarkdownRenderer::toHtml("# Sarlavha 1\n\n## Sarlavha 2\n\n### Sarlavha 3");

        $this->assertStringContainsString('<h1>Sarlavha 1</h1>', $html);
        $this->assertStringContainsString('<h2>Sarlavha 2</h2>', $html);
        $this->assertStringContainsString('<h3>Sarlavha 3</h3>', $html);
    }

    public function test_renders_bold_and_inline_code(): void
    {
        $html = MarkdownRenderer::toHtml("Bu **muhim** va `kod` matni.");

        $this->assertStringContainsString('<strong>muhim</strong>', $html);
        $this->assertStringContainsString('<code>kod</code>', $html);
    }

    public function test_renders_links(): void
    {
        $html = MarkdownRenderer::toHtml('[Firebase](https://console.firebase.google.com)');

        $this->assertStringContainsString('<a href="https://console.firebase.google.com" target="_blank" rel="noopener">Firebase</a>', $html);
    }

    public function test_renders_fenced_code_block_verbatim_and_escaped(): void
    {
        $html = MarkdownRenderer::toHtml("```json\n{ \"a\": \"<b>\" }\n```");

        $this->assertStringContainsString('<pre><code>', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function test_renders_unordered_and_ordered_lists(): void
    {
        $html = MarkdownRenderer::toHtml("- Birinchi\n- Ikkinchi");
        $this->assertStringContainsString('<ul><li>Birinchi</li><li>Ikkinchi</li></ul>', $html);

        $html = MarkdownRenderer::toHtml("1. Birinchi\n2. Ikkinchi");
        $this->assertStringContainsString('<ol><li>Birinchi</li><li>Ikkinchi</li></ol>', $html);
    }

    public function test_renders_table(): void
    {
        $html = MarkdownRenderer::toHtml("| Metod | URL |\n|---|---|\n| GET | /leads |\n| POST | /leads |");

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th>Metod</th><th>URL</th>', $html);
        $this->assertStringContainsString('<td>GET</td><td>/leads</td>', $html);
    }

    public function test_renders_blockquote(): void
    {
        $html = MarkdownRenderer::toHtml('> Diqqat: bu muhim eslatma.');

        $this->assertStringContainsString('<blockquote>', $html);
        $this->assertStringContainsString('Diqqat: bu muhim eslatma.', $html);
    }

    public function test_renders_horizontal_rule(): void
    {
        $html = MarkdownRenderer::toHtml("Matn\n\n---\n\nBoshqa matn");

        $this->assertStringContainsString('<hr>', $html);
    }

    public function test_escapes_html_in_plain_text(): void
    {
        $html = MarkdownRenderer::toHtml('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
