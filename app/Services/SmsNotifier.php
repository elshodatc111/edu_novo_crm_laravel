<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Throwable;

/**
 * Tizim voqealari uchun SMS: filialda SMS yoqilgan va shablon faol bo'lsagina yuboriladi.
 * Hech qachon asosiy jarayonni to'xtatmaydi.
 */
class SmsNotifier
{
    public function __construct(private SmsService $sms) {}

    /**
     * $phoneOverride berilsa, xabar $recipient (yozuv/hisobot uchun) emas, shu raqamga yuboriladi
     * (masalan v8 B1: darsga kelmaganlik xabari o'quvchiga emas, ota-ona raqamiga — $recipient->phone2).
     */
    public function notify(string $key, User $recipient, array $vars = [], ?string $phoneOverride = null): void
    {
        try {
            $branch = Branch::find($recipient->branch_id);
            $phone = $phoneOverride ?? $recipient->phone;

            if (! $branch || ! $branch->sms_enabled || blank($phone)) {
                return;
            }

            $template = $this->sms->template($branch, $key);

            if (! $template['enabled']) {
                return;
            }

            $allVars = ['name' => $recipient->name, 'branch' => $branch->name] + $vars;
            $text = $this->sms->render($template['body'], $allVars);

            // v8 A5: parol o'z ichiga olgan xabarlarda SMS tarixida ochiq parol saqlanmaydi -
            // haqiqiy matn faqat yuborish uchun $secret sifatida beriladi, tarixda berkitilgani ko'rinadi.
            $secret = null;
            if (array_key_exists('password', $vars) && $vars['password'] !== null) {
                $masked = $this->sms->render($template['body'], ['password' => '••••••••'] + $allVars);
                $secret = $text;
                $text = $masked;
            }

            $this->sms->queue($branch, $phone, $text, $key, $recipient, auth()->user(), $secret);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
