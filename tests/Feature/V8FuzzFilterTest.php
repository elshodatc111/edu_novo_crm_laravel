<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * v8 A6: "hech qachon 500 emas". Filtr/ro'yxat sahifalariga massiv (`?x[]=y`), yaroqsiz sana/oy va
 * juda uzun matn parametr sifatida yuborilganda hech biri 500 (server xatosi) qaytarmasligini tekshiradi.
 *
 * Sabab (tuzatilgan): ko'p joyda $request->input('x') natijasi to'g'ridan-to'g'ri `?string` kutgan
 * funksiyaga (masalan App\Models\User::scopeSearch(), App\Models\Group::scopeStatus()) yoki
 * trim()/CarbonImmutable::parse() ga uzatilardi - massiv kelsa PHP TypeError bilan 500 qaytarardi.
 * Endi hammasi App\Support\SafeInput orqali o'tadi.
 */
class V8FuzzFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = $this->branch('Fuzz filiali');
        $this->admin = $this->user(Role::Admin, $branch, [
            'groups.view', 'staff.view', 'students.view', 'attendance.stats', 'payments.view',
            'leads.view', 'audit.view', 'sms.view', 'statistics.view', 'reports.view', 'reports.export',
            'finance.view', 'teachers.view', 'ai.chat',
        ]);
    }

    /** @return array<int, array{0: string, 1: string[]}> [to'liq URL, shu sahifadagi filtr parametrlari] */
    private function pages(): array
    {
        return [
            [route('groups.index'), ['status', 'q', 'teacher_id']],
            [route('staff.index'), ['role', 'status', 'q']],
            [route('students.index'), ['status', 'q', 'group_id']],
            [route('attendance.stats'), ['date', 'month']],
            [route('payments.index'), ['from', 'to', 'type', 'method', 'created_by', 'q']],
            [route('leads.index'), ['status', 'days', 'source', 'q']],
            [route('audit.index'), ['branch', 'action', 'user', 'from', 'to']],
            [route('sms.index'), ['status', 'q']],
            [route('statistics.index'), ['from', 'to']],
            [route('reports.show', 'payments'), ['from', 'to']],
            [route('finance.index'), ['wallet']],
            [route('payroll.index'), ['tab']],
            [route('help.index'), ['mode']],
            [route('dashboard'), ['cal']],
        ];
    }

    /** @return array<string, mixed> */
    private function fuzzValues(): array
    {
        return [
            'massiv' => ['fuzz1', 'fuzz2'],
            'yaroqsiz sana' => 'not-a-real-date-@@@',
            'yaroqsiz oy' => '2026-13',
            'juda uzun matn' => str_repeat('a', 5000),
        ];
    }

    public function test_list_pages_never_500_on_fuzzed_query_params(): void
    {
        foreach ($this->pages() as [$uri, $params]) {
            foreach ($params as $param) {
                foreach ($this->fuzzValues() as $label => $value) {
                    $url = $uri.'?'.http_build_query([$param => $value]);
                    $response = $this->actingAs($this->admin)->get($url);

                    $this->assertLessThan(
                        500,
                        $response->getStatusCode(),
                        "{$uri} ?{$param}=<{$label}> -> HTTP {$response->getStatusCode()} (500 kutilmagan edi)"
                    );
                }
            }
        }
    }

    public function test_api_list_pages_never_500_on_fuzzed_query_params(): void
    {
        $token = $this->admin->createToken('fuzz')->plainTextToken;

        $pages = [
            ['/api/v1/groups', ['status']],
            ['/api/v1/students', ['q', 'debtors', 'per_page']],
        ];

        foreach ($pages as [$uri, $params]) {
            foreach ($params as $param) {
                foreach ($this->fuzzValues() as $label => $value) {
                    $url = $uri.'?'.http_build_query([$param => $value]);
                    $response = $this->api($token)->getJson($url);

                    $this->assertLessThan(
                        500,
                        $response->getStatusCode(),
                        "{$uri} ?{$param}=<{$label}> -> HTTP {$response->getStatusCode()} (500 kutilmagan edi)"
                    );
                }
            }
        }
    }
}
