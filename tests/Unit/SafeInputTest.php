<?php

namespace Tests\Unit;

use App\Support\SafeInput;
use PHPUnit\Framework\TestCase;

/** v8 A6: SafeInput hech qachon xato bermasligini (massiv, son, juda uzun matn, yaroqsiz sana/oy) tekshiradi. */
class SafeInputTest extends TestCase
{
    public function test_string_passes_through_and_trims_plain_strings(): void
    {
        $this->assertSame('salom', SafeInput::string('  salom  '));
        $this->assertSame('salom', SafeInput::string('salom'));
    }

    public function test_string_coerces_scalars(): void
    {
        $this->assertSame('30', SafeInput::string(30));
        $this->assertSame('1.5', SafeInput::string(1.5));
    }

    public function test_string_returns_default_for_array(): void
    {
        $this->assertNull(SafeInput::string(['x', 'y']));
        $this->assertSame('current', SafeInput::string(['x'], 'current'));
    }

    public function test_string_returns_default_for_null_and_blank(): void
    {
        $this->assertNull(SafeInput::string(null));
        $this->assertSame('def', SafeInput::string(null, 'def'));
        $this->assertSame('def', SafeInput::string('   ', 'def'));
        $this->assertSame('def', SafeInput::string('', 'def'));
    }

    public function test_string_truncates_to_max_length(): void
    {
        $long = str_repeat('a', 300);

        $this->assertSame(str_repeat('a', 20), SafeInput::string($long, null, 20));
    }

    public function test_date_accepts_valid_date(): void
    {
        $this->assertSame('2026-05-01', SafeInput::date('2026-05-01'));
    }

    public function test_date_accepts_other_carbon_parseable_shapes(): void
    {
        $this->assertSame('2026-05-01', SafeInput::date('2026-05-01 10:30:00'));
    }

    public function test_date_returns_default_for_garbage_string(): void
    {
        $this->assertNull(SafeInput::date('abc'));
        $this->assertSame('2026-01-01', SafeInput::date('not-a-date', '2026-01-01'));
    }

    public function test_date_returns_default_for_array(): void
    {
        $this->assertNull(SafeInput::date(['from' => '2026-01-01']));
        $this->assertSame('2026-01-01', SafeInput::date(['x'], '2026-01-01'));
    }

    public function test_date_returns_default_for_absent_value(): void
    {
        $this->assertNull(SafeInput::date(null));
    }

    public function test_month_accepts_valid_months(): void
    {
        $this->assertSame('2026-01', SafeInput::month('2026-01'));
        $this->assertSame('2026-12', SafeInput::month('2026-12'));
    }

    public function test_month_rejects_invalid_month_number(): void
    {
        $this->assertNull(SafeInput::month('2026-13'));
        $this->assertNull(SafeInput::month('2026-00'));
        $this->assertSame('2026-09', SafeInput::month('2026-13', '2026-09'));
    }

    public function test_month_rejects_malformed_strings(): void
    {
        $this->assertNull(SafeInput::month('2026/01'));
        $this->assertNull(SafeInput::month('abc'));
        $this->assertNull(SafeInput::month('2026-1'));
    }

    public function test_month_returns_default_for_array(): void
    {
        $this->assertNull(SafeInput::month(['2026-01']));
        $this->assertSame('2026-09', SafeInput::month(['x'], '2026-09'));
    }
}
