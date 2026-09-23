<?php

namespace App\Http\Controllers;

use App\Support\MarkdownRenderer;
use Illuminate\Support\Facades\File;

/**
 * v12: mobil ilova dasturchisi uchun API hujjatini (`API_DOC.md`) onlayn ko'rsatadi (`/docs`).
 * Ataylab LOGIN TALAB QILMAYDI — dasturchida CRM hisobi bo'lmaydi, hujjatning o'zida esa
 * hech qanday sir (parol, kalit, mijoz ma'lumoti) yo'q, faqat API tuzilishi tavsiflangan.
 * Qidiruv tizimlariga chiqmasligi uchun `noindex` meta-tegi qo'yilgan (resources/views/docs/index.blade.php).
 */
class DocsController extends Controller
{
    public function index()
    {
        $path = base_path('API_DOC.md');
        $markdown = File::exists($path) ? File::get($path) : "# API hujjati\n\nHujjat fayli topilmadi.";

        return view('docs.index', ['html' => MarkdownRenderer::toHtml($markdown)]);
    }
}
