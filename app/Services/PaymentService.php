<?php

namespace App\Services;

use App\Enums\PayMethod;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\BalanceTransaction;
use App\Models\DiscountCampaign;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Payment;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** O'quvchi to'lovlari: qabul qilish, chegirma, aksiya va qaytarish. */
class PaymentService
{
    public function __construct(private BalanceService $balance, private WalletService $wallets, private EarlyDiscountService $early) {}

    /**
     * To'lov qabul qiladi. Kassaga tushadi, o'quvchi balansi oshadi.
     * Guruh ko'rsatilgan bo'lsa va shartlar bajarilsa - oldindan to'lov chegirmasi ham beriladi.
     * Shartlariga mos aksiya (eng katta bonusli, o'quvchi hali foydalanmagan) avtomatik qo'shiladi.
     *
     * @param  array{cash?:int,card?:int}  $amounts
     * @return array<int, Payment>
     */
    public function receive(User $student, array $amounts, ?Group $group, ?string $description, User $actor): array
    {
        $this->assertStudent($student);

        $cash = (int) ($amounts['cash'] ?? 0);
        $card = (int) ($amounts['card'] ?? 0);

        if ($cash < 0 || $card < 0 || $cash + $card <= 0) {
            throw ValidationException::withMessages(['cash' => "To'lov summasini kiriting."]);
        }

        $created = DB::transaction(function () use ($student, $cash, $card, $group, $description, $actor) {
            User::whereKey($student->id)->lockForUpdate()->firstOrFail();

            $created = [];
            foreach ([PayMethod::Cash->value => $cash, PayMethod::Card->value => $card] as $method => $amount) {
                if ($amount > 0) {
                    $created[] = $this->recordPayment($student, PayMethod::from($method), $amount, $group, $description, $actor);
                }
            }

            $total = $cash + $card;

            // Oldindan to'lov chegirmasi: bir nechta to'lov jamlanib hisobga olinadi (EarlyDiscountService)
            foreach ($this->early->grantDue($student, $actor) as $discount) {
                $created[] = $discount;
            }

            if ($campaign = $this->bestCampaign($student, $total)) {
                $created[] = $this->applyCampaign($student, $campaign, $actor);
            }

            AuditLog::record('payment.received', $student, "To'lov qabul qilindi: ".Format::money($total)." (naqt ".Format::money($cash, false).", plastik ".Format::money($card, false).')');

            return $created;
        });

        $this->notifyPayment($student, $created, $cash + $card);

        return $created;
    }

    /** @param array<int,Payment> $created */
    private function notifyPayment(User $student, array $created, int $total): void
    {
        $sms = app(SmsNotifier::class);
        $fresh = $student->fresh();

        $sms->notify('payment_received', $fresh, ['amount' => Format::money($total), 'balance' => Format::money($fresh->balance)]);

        foreach ($created as $payment) {
            if (in_array($payment->type, [Payment::DISCOUNT, Payment::CAMPAIGN_BONUS], true)) {
                $sms->notify('discount_given', $fresh, ['amount' => Format::money($payment->amount)]);
            }
        }
    }

    /** Admin chegirmasi: narx rejasida belgilangan chegaradan oshmasligi va guruh uchun bir marta berilishi kerak. */
    public function manualDiscount(User $student, Group $group, int $amount, string $description, User $actor): Payment
    {
        $this->assertStudent($student);

        if ($group->max_discount <= 0) {
            throw ValidationException::withMessages(['discount' => "Bu guruh uchun admin chegirmasi belgilanmagan."]);
        }
        if ($amount <= 0 || $amount > $group->max_discount) {
            throw ValidationException::withMessages(['discount' => "Chegirma 1 dan ".Format::money($group->max_discount)." gacha bo'lishi kerak."]);
        }

        $payment = DB::transaction(function () use ($student, $group, $amount, $description, $actor) {
            User::whereKey($student->id)->lockForUpdate()->firstOrFail();

            $this->assertActiveMember($student, $group);

            if ($this->hasDiscount($student, $group)) {
                throw ValidationException::withMessages(['discount' => "Bu o'quvchi bu guruh uchun allaqachon chegirma olgan."]);
            }

            $payment = $this->recordDiscount($student, $group, $amount, $description, BalanceTransaction::MANUAL_DISCOUNT, $actor);
            AuditLog::record('payment.discount', $student, "Chegirma berildi: ".Format::money($amount)." ({$description})");

            return $payment;
        });

        app(SmsNotifier::class)->notify('discount_given', $student->fresh(), ['amount' => Format::money($amount)]);

        return $payment;
    }

    /** v13.2: bitta maxsus chegirmaning yuqori chegarasi (so'm). */
    public const MAX_SPECIAL_DISCOUNT = 1_000_000;

    /**
     * v13.2: sAdmin'ning MAXSUS chegirmasi: guruhga bog'lanmagan, narx rejasidagi `max_discount` bilan cheklanmaydi
     * (kam ta'minlangan o'quvchilar va h.k.). Faqat o'quvchi balansini oshiradi, kassadan pul chiqmaydi, SMS yuborilmaydi.
     * Bitta chegirma {@see self::MAX_SPECIAL_DISCOUNT} dan oshmaydi. `Payment::DISCOUNT` turida yoziladi, shuning uchun
     * statistika/hisobotlarda «Chegirmalar» qatoriga kiradi va storno qilinadi.
     */
    public function specialDiscount(User $student, int $amount, string $description, User $actor): Payment
    {
        if (! $actor->isSuperAdmin()) {
            throw new \Illuminate\Auth\Access\AuthorizationException("Maxsus chegirmani faqat superadmin bera oladi.");
        }

        $this->assertStudent($student);

        $description = trim($description);
        if ($description === '') {
            throw ValidationException::withMessages(['special_description' => 'Sababni kiriting.']);
        }
        if ($amount <= 0 || $amount > self::MAX_SPECIAL_DISCOUNT) {
            throw ValidationException::withMessages(['special_amount' => "Maxsus chegirma 1 dan ".Format::money(self::MAX_SPECIAL_DISCOUNT)." gacha bo'lishi kerak."]);
        }

        return DB::transaction(function () use ($student, $amount, $description, $actor) {
            User::whereKey($student->id)->lockForUpdate()->firstOrFail();

            $note = 'Maxsus chegirma: '.$description;
            $payment = Payment::create([
                'branch_id' => $student->branch_id, 'student_id' => $student->id, 'type' => Payment::DISCOUNT,
                'amount' => $amount, 'description' => $note, 'created_by' => $actor->id,
            ]);

            $this->balance->post($student, $amount, BalanceTransaction::SPECIAL_DISCOUNT, null, $note, $actor, $payment);

            AuditLog::record('payment.special_discount', $student, 'Maxsus chegirma berildi: '.Format::money($amount)." ({$description})");

            return $payment;
        });
    }

    /** To'lovni qaytaradi: balansdan va kassadan yechiladi. Keyin admin tasdiqlaydi. */
    public function refund(User $student, PayMethod $method, int $amount, string $description, User $actor): Payment
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => "Summani kiriting."]);
        }

        $payment = DB::transaction(function () use ($student, $method, $amount, $description, $actor) {
            $locked = User::whereKey($student->id)->lockForUpdate()->firstOrFail();

            if ($amount > $locked->balance) {
                throw ValidationException::withMessages(['amount' => "O'quvchi balansida faqat ".Format::money(max(0, $locked->balance))." bor, ko'proq qaytarib bo'lmaydi."]);
            }

            $payment = Payment::create([
                'branch_id' => $student->branch_id, 'student_id' => $student->id, 'type' => Payment::REFUND,
                'method' => $method, 'amount' => $amount, 'description' => $description, 'created_by' => $actor->id,
            ]);

            $this->wallets->post($student->branch_id, $method->till(), -$amount, 'refund_out', $description, $payment, $actor);
            $this->balance->post($student, -$amount, BalanceTransaction::PAYMENT_REFUND, null, $description, $actor, $payment);

            AuditLog::record('payment.refund', $student, "To'lov qaytarildi: ".Format::money($amount)." ({$description})");

            return $payment;
        });

        app(SmsNotifier::class)->notify('refund_made', $student->fresh(), ['amount' => Format::money($amount)]);

        return $payment;
    }

    public function confirmRefund(Payment $payment, User $actor): Payment
    {
        if ($payment->type !== Payment::REFUND || $payment->refund_confirmed_at !== null || $payment->refund_rejected_at !== null) {
            throw ValidationException::withMessages(['payment' => "Bu qaytarish allaqachon tasdiqlangan, rad etilgan yoki qaytarish emas."]);
        }

        $payment->update(['refund_confirmed_by' => $actor->id, 'refund_confirmed_at' => now()]);

        return $payment;
    }

    /**
     * Qaytarishni RAD ETADI: qaytarish so'ralganda darhol yechilgan pul kassaga va o'quvchi balansiga qaytadi.
     * Sabab majburiy (masalan, qaytarish xato so'ralgan bo'lsa).
     */
    public function rejectRefund(Payment $payment, string $reason, User $actor): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $actor) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->type !== Payment::REFUND || $locked->refund_confirmed_at !== null || $locked->refund_rejected_at !== null) {
                throw ValidationException::withMessages(['payment' => "Bu qaytarish allaqachon tasdiqlangan, rad etilgan yoki qaytarish emas."]);
            }

            $student = User::whereKey($locked->student_id)->lockForUpdate()->firstOrFail();

            if ($locked->method) {
                $this->wallets->post($student->branch_id, $locked->method->till(), $locked->amount, 'refund_rejected', $reason, $locked, $actor);
            }
            $this->balance->post($student, $locked->amount, BalanceTransaction::PAYMENT_REFUND_REJECTED, null, $reason, $actor, $locked);

            $locked->update(['refund_rejected_by' => $actor->id, 'refund_rejected_at' => now(), 'refund_reject_reason' => $reason]);

            AuditLog::record('payment.refund_rejected', $student, "Qaytarish rad etildi, pul qaytarildi: ".Format::money($locked->amount)." ({$reason})");

            return $locked->fresh();
        });
    }

    /**
     * Xato kiritilgan to'lov/chegirma/bonusni STORNO qiladi: asl yozuv o'chirilmaydi, faqat teskari
     * (mirror) yozuv qo'shiladi va asl yozuv "stornolangan" deb belgilanadi — audit iz to'liq saqlanadi.
     * To'lov stornosi kassadan xuddi shu summani yechadi (WalletService orqali), shuning uchun kassada
     * mablag' yetarli bo'lmasa amalga oshmaydi.
     */
    public function reverse(Payment $payment, string $reason, User $actor): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $actor) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isReversible()) {
                throw ValidationException::withMessages(['payment' => "Bu yozuvni storno qilib bo'lmaydi (turi mos emas yoki allaqachon storno qilingan)."]);
            }

            $student = User::whereKey($locked->student_id)->lockForUpdate()->firstOrFail();

            $reversal = Payment::create([
                'branch_id' => $locked->branch_id, 'student_id' => $locked->student_id, 'group_id' => $locked->group_id,
                'campaign_id' => $locked->campaign_id, 'type' => Payment::REVERSAL, 'method' => $locked->method,
                'amount' => $locked->amount, 'description' => $reason, 'created_by' => $actor->id,
                'reversal_of_id' => $locked->id,
            ]);

            if ($locked->method) {
                $this->wallets->post($student->branch_id, $locked->method->till(), -$locked->amount, 'payment_reversal', $reason, $reversal, $actor);
            }
            $this->balance->post($student, -$locked->amount, BalanceTransaction::PAYMENT_REVERSAL, $locked->group, $reason, $actor, $reversal);

            $locked->update(['reversed_by' => $actor->id, 'reversed_at' => now(), 'reverse_reason' => $reason]);

            AuditLog::record('payment.reversed', $student, "Storno: {$locked->typeLabel()} ".Format::money($locked->amount)." bekor qilindi ({$reason})");

            return $reversal;
        });
    }

    /**
     * To'lovni storno qilishdan oldin ko'rsatiladigan ogohlantirish: shu kuni shu o'quvchiga chegirma
     * yoki aksiya bonusi berilgan bo'lsa (balki shu to'lov sabab bo'lgan), storno ularni AVTOMATIK
     * bekor qilmasligini eslatadi.
     */
    public function reversalWarning(Payment $payment): ?string
    {
        if ($payment->type !== Payment::PAYMENT) {
            return null;
        }

        $hasRelated = Payment::where('student_id', $payment->student_id)
            ->whereIn('type', [Payment::DISCOUNT, Payment::CAMPAIGN_BONUS])
            ->whereNull('reversed_at')
            ->whereDate('created_at', $payment->created_at->toDateString())
            ->exists();

        return $hasRelated
            ? "Diqqat: shu kuni bu o'quvchiga chegirma yoki aksiya bonusi berilgan — balki shu to'lov sabab bo'lgandir. Storno uni avtomatik bekor qilmaydi, kerak bo'lsa alohida tekshirib, o'zi ham storno qiling."
            : null;
    }

    private function recordPayment(User $student, PayMethod $method, int $amount, ?Group $group, ?string $description, User $actor): Payment
    {
        $payment = Payment::create([
            'branch_id' => $student->branch_id, 'student_id' => $student->id, 'group_id' => $group?->id, 'type' => Payment::PAYMENT,
            'method' => $method, 'amount' => $amount, 'description' => $description, 'created_by' => $actor->id,
        ]);

        $this->wallets->post($student->branch_id, $method->till(), $amount, 'payment_in', $description, $payment, $actor);
        $this->balance->post($student, $amount, BalanceTransaction::PAYMENT, $group, $description, $actor, $payment);

        return $payment;
    }

    private function recordDiscount(User $student, Group $group, int $amount, string $description, string $balanceType, User $actor): Payment
    {
        $payment = Payment::create([
            'branch_id' => $student->branch_id, 'student_id' => $student->id, 'group_id' => $group->id, 'type' => Payment::DISCOUNT,
            'amount' => $amount, 'description' => $description, 'created_by' => $actor->id,
        ]);

        $this->balance->post($student, $amount, $balanceType, $group, $description, $actor, $payment);

        return $payment;
    }

    /** Shu to'lov summasiga mos, hozir amal qiladigan va o'quvchi hali olmagan (yoki bonusi storno qilingan) eng katta bonusli aksiya. */
    private function bestCampaign(User $student, int $paid): ?DiscountCampaign
    {
        $used = Payment::where('student_id', $student->id)->where('type', Payment::CAMPAIGN_BONUS)->whereNull('reversed_at')->whereNotNull('campaign_id')->select('campaign_id');

        return DiscountCampaign::running()->where('amount', '<=', $paid)->whereNotIn('id', $used)->orderByDesc('bonus')->orderBy('id')->first();
    }

    private function applyCampaign(User $student, DiscountCampaign $campaign, User $actor): Payment
    {
        $payment = Payment::create([
            'branch_id' => $student->branch_id, 'student_id' => $student->id, 'campaign_id' => $campaign->id, 'type' => Payment::CAMPAIGN_BONUS,
            'amount' => $campaign->bonus, 'description' => $campaign->name, 'created_by' => $actor->id,
        ]);

        $this->balance->post($student, $campaign->bonus, BalanceTransaction::CAMPAIGN_BONUS, null, $campaign->name, $actor, $payment);

        return $payment;
    }

    private function hasDiscount(User $student, Group $group): bool
    {
        return Payment::where('student_id', $student->id)->where('group_id', $group->id)->where('type', Payment::DISCOUNT)->whereNull('reversed_at')->exists();
    }

    private function isActiveMember(User $student, Group $group): bool
    {
        return GroupStudent::where('student_id', $student->id)->where('group_id', $group->id)->where('is_active', true)->exists();
    }

    private function assertActiveMember(User $student, Group $group): void
    {
        if (! $this->isActiveMember($student, $group)) {
            throw ValidationException::withMessages(['group_id' => "O'quvchi bu guruhda faol emas."]);
        }
    }

    private function assertStudent(User $student): void
    {
        if ($student->role !== Role::Student) {
            throw ValidationException::withMessages(['student' => "Faqat o'quvchi uchun to'lov qabul qilinadi."]);
        }
    }
}
