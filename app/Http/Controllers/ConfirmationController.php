<?php

namespace App\Http\Controllers;

use App\Enums\PayMethod;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Group;
use App\Models\Payment;
use App\Models\User;
use App\Services\ConfirmationService;
use App\Services\FinanceService;
use App\Services\PaymentService;
use App\Services\PayrollService;
use App\Services\SmsService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** Ikki bosqichli tasdiqlash: «Tekshiring» sahifasi va amalni bajarish. */
class ConfirmationController extends Controller
{
    public function __construct(private ConfirmationService $confirmations) {}

    public function show(string $token)
    {
        $item = $this->confirmations->peek($token);

        if (! $item) {
            return redirect()->route('dashboard')->with('error', "Tasdiqlash muddati tugagan yoki allaqachon bajarilgan. Ma'lumotni qaytadan kiriting.");
        }

        return view('confirm.show', ['token' => $token, 'item' => $item]);
    }

    /** Parol bilan tasdiqlanadigan amallar (brauzer ochiq qolsa, boshqa odam bajara olmasligi uchun). */
    public const PASSWORD_KINDS = ['payment.special_discount', 'group.archive'];

    public function store(Request $request, string $token, FinanceService $finance, PayrollService $payroll, PaymentService $payments, SmsService $sms): RedirectResponse
    {
        $pending = $this->confirmations->peek($token);

        if ($pending && in_array($pending['kind'], self::PASSWORD_KINDS, true)) {
            $request->validate(['password' => ['required', 'string', 'max:255']], [], ['password' => 'Parol']);

            $rateKey = 'confirm-password:'.$request->user()->id;
            if (RateLimiter::tooManyAttempts($rateKey, 5)) {
                $minutes = (int) ceil(RateLimiter::availableIn($rateKey) / 60);

                return back()->withErrors(['password' => "Urinishlar ko'p. {$minutes} daqiqadan keyin qayta urinib ko'ring."]);
            }

            if (! Hash::check((string) $request->input('password'), (string) $request->user()->password)) {
                RateLimiter::hit($rateKey, 600);

                return back()->withErrors(['password' => "Parol noto'g'ri."]);
            }

            RateLimiter::clear($rateKey);
        }

        $item = $this->confirmations->claim($token);

        if (! $item) {
            return redirect()->route('dashboard')->with('error', "Tasdiqlash muddati tugagan yoki allaqachon bajarilgan. Ma'lumotni qaytadan kiriting.");
        }

        $user = $request->user();
        $p = $item['payload'];

        try {
            $message = match ($item['kind']) {
                'finance.withdraw' => $this->run('finance.manage', fn () => $finance->withdraw($p['source'], $p['amount'], $p['description'], $user), 'Chiqim amalga oshirildi.'),
                'finance.charity' => $this->run('finance.manage', fn () => $finance->charityWithdraw(PayMethod::from($p['method']), $p['amount'], $p['description'], $user), 'Ehson chiqimi amalga oshirildi.'),
                'finance.expense' => $this->run('finance.manage', fn () => $finance->expense(PayMethod::from($p['method']), $p['amount'], $p['description'], $user, $p['category_id'] ?? null), 'Xarajat yozildi.'),
                'payroll.pay' => $this->payroll($payroll, $p, $user),
                'payment.reverse' => $this->run('payments.reverse', fn () => $payments->reverse(Payment::findOrFail($p['payment_id']), $p['reason'], $user), 'Storno bajarildi.'),
                'group.archive' => $this->archiveGroup($p, $user),
                'payment.special_discount' => $this->specialDiscount($payments, $p, $user),
                'sms.bulk' => $this->smsBulk($sms, $p, $user),
                default => abort(404),
            };
        } catch (ValidationException $e) {
            return redirect($item['back'])->withErrors($e->errors())->withInput();
        }

        // Arxivlangan guruh sahifasi endi yo'q - ro'yxatga qaytamiz
        $to = $item['kind'] === 'group.archive' ? route('groups.index') : $item['back'];

        return redirect($to)->with('success', $message);
    }

    private function archiveGroup(array $p, User $actor): string
    {
        $group = Group::findOrFail($p['group_id']);
        $this->authorize('delete', $group);

        app(\App\Services\GroupService::class)->archive($group, $p['reason'], $actor);

        return "Guruh o'chirildi (arxivlandi): «{$group->name}».";
    }

    private function specialDiscount(PaymentService $payments, array $p, User $actor): string
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $student = User::visibleToContext()->ofRole(Role::Student)->findOrFail($p['student_id']);
        $payments->specialDiscount($student, (int) $p['amount'], $p['description'], $actor);

        return "Maxsus chegirma berildi: ".\App\Support\Format::money((int) $p['amount']).'.';
    }

    private function run(string $permission, callable $action, string $message): string
    {
        $this->authorize($permission);
        $action();

        return $message;
    }

    private function payroll(PayrollService $payroll, array $p, User $actor): string
    {
        $recipient = User::visibleToContext()->findOrFail($p['user_id']);
        $isTeacher = $recipient->role === Role::Teacher;
        abort_unless(in_array($recipient->role, [Role::Teacher, Role::Admin, Role::Manager, Role::Operator], true), 404);
        $this->authorize($isTeacher ? 'teachers.pay' : 'staff.pay');

        $method = PayMethod::from($p['method']);

        if ($isTeacher) {
            $payroll->payTeacher($recipient, $p['group_id'] ? Group::findOrFail($p['group_id']) : null, $method, $p['amount'], $p['description'], $actor);
        } else {
            $payroll->payStaff($recipient, $method, $p['amount'], $p['description'], $actor);
        }

        return "Ish haqi to'landi.";
    }

    /**
     * v8 B9: tasdiqlangandan keyin haqiqiy yuborish. `sms_enabled` va qabul qiluvchilar ro'yxati
     * qayta tekshiriladi (ko'rib chiqish paytidan beri o'zgargan bo'lishi mumkin).
     */
    private function smsBulk(SmsService $sms, array $p, User $actor): string
    {
        $this->authorize('sms.send');

        $branch = Branch::findOrFail(BranchContext::id());

        if (! $branch->sms_enabled) {
            throw ValidationException::withMessages(['message' => "Bu filialda SMS yoqilmagan."]);
        }

        $queued = $sms->sendBulk($branch, $p['audience'], $p['group_id'] ?? null, $p['message'], $actor);

        if ($queued === 0) {
            throw ValidationException::withMessages(['message' => "Yuborish vaqtida mos o'quvchi topilmadi (ro'yxat o'zgargan bo'lishi mumkin)."]);
        }

        return "{$queued} ta SMS navbatga qo'yildi.";
    }
}
