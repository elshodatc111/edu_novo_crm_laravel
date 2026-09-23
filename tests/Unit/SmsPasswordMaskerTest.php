<?php

namespace Tests\Unit;

use App\Support\SmsPasswordMasker;
use PHPUnit\Framework\TestCase;

class SmsPasswordMaskerTest extends TestCase
{
    public function test_masks_password_in_default_welcome_template_shape(): void
    {
        $text = "Hurmatli ALI, Toshkent o'quv markaziga xush kelibsiz! Mobil ilova uchun login: 998901234567, parol: a3f9k2m1.";

        $masked = SmsPasswordMasker::mask($text);

        $this->assertStringNotContainsString('a3f9k2m1', $masked);
        $this->assertStringContainsString('parol: ••••••••.', $masked);
        $this->assertStringContainsString('login: 998901234567', $masked);   // login o'zgarmaydi
    }

    public function test_masks_password_in_default_reset_template_shape(): void
    {
        $text = 'Hurmatli ALI, parolingiz yangilandi. Login: 998901234567, yangi parol: b7h2n9p4.';

        $masked = SmsPasswordMasker::mask($text);

        $this->assertStringNotContainsString('b7h2n9p4', $masked);
        $this->assertStringContainsString('yangi parol: ••••••••.', $masked);
        $this->assertStringContainsString('Login: 998901234567', $masked);
    }

    public function test_leaves_text_without_password_unchanged(): void
    {
        $text = "Hurmatli ALI, 50 000 so'm to'lovingiz qabul qilindi.";

        $this->assertSame($text, SmsPasswordMasker::mask($text));
    }

    public function test_is_idempotent_on_already_masked_text(): void
    {
        $once = SmsPasswordMasker::mask('Login: 123, parol: a3f9k2m1.');
        $twice = SmsPasswordMasker::mask($once);

        $this->assertSame($once, $twice);
    }
}
