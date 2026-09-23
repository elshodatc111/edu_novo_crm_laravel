<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v12: mobil ilova dasturchisi uchun ochiq (login talab qilmaydigan) API hujjati sahifasi. */
class V12DocsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_docs_page_is_publicly_accessible(): void
    {
        $this->get('/docs')->assertOk();
    }

    public function test_docs_page_renders_api_doc_content(): void
    {
        $response = $this->get('/docs');

        $response->assertSee('Edunova CRM', false);
        $response->assertSee('X-Branch-Id', false);
        $response->assertSee('force_update', false);
        $response->assertSee('<table>', false);
    }

    public function test_docs_page_is_not_indexable(): void
    {
        $this->get('/docs')->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_docs_page_does_not_render_raw_html_from_source_as_script(): void
    {
        $this->get('/docs')->assertDontSee('<script>alert', false);
    }
}
