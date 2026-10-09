<?php

namespace App\Http\Controllers;

use App\Enums\PayMethod;
use App\Models\Group;
use App\Models\Payment;
use App\Models\User;
use App\Services\ConfirmationService;
use App\Services\PaymentService;
use App\Support\BranchContext;
use App\Support\Format;
use App\Support\SafeInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function index(Request $request)
    {
        $this->authorize('payments.view');

        $from = SafeInput::date($request->input('from'), today()->subDays(6)->toDateString());
        $to = SafeInput::date($request->input('to'), today()->toDateString());

        $base = Payment::query()
            ->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->when(SafeInput::string($request->input('type')), fn ($q, $type) => $q->where('type', $type))
            ->when(SafeInput::string($request->input('method')), fn ($q, $method) => $q->where('method', $method))
            ->when($request->filled('created_by'), fn ($q) => $q->where('created_by', $request->integer('created_by')))
            ->when(SafeInput::string($request->input('q')), fn ($q, $term) => $q->whereIn('student_id', User::visibleToContext()->search($term)->select('users.id')));

        // v8: storno qilingan to'lov/chegirma/bonus va rad etilgan qaytarish jamiga qo'shilmaydi
        $totals = (clone $base)->selectRaw("
            coalesce(sum(case when type = 'payment' and method = 'cash' and reversed_at is null then amount end), 0) as cash,
            coalesce(sum(case when type = 'payment' and method = 'card' and reversed_at is null then amount end), 0) as card,
            coalesce(sum(case when type in ('discount', 'campaign_bonus') and reversed_at is null then amount end), 0) as discounts,
            coalesce(sum(case when type = 'refund' and refund_rejected_at is null then amount end), 0) as refunds
        ")->first();

        return view('payments.index', [
            'payments' => $base->with(['student', 'group', 'creator'])->orderByDesc('id')->paginate(30)->withQueryString(),
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
            'cashiers' => User::visibleToContext()->whereIn('role', ['admin', 'manager', 'operator'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, User $student): RedirectResponse
    {
        $this->authorize('payments.create');

        $data = $request->validate([
            'cash' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'card' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'group_id' => ['nullable', BranchContext::exists('groups')],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], ['cash' => 'Naqt', 'card' => 'Plastik', 'group_id' => 'Guruh', 'description' => 'Izoh']);

        $created = $this->payments->receive(
            $student,
            ['cash' => (int) ($data['cash'] ?? 0), 'card' => (int) ($data['card'] ?? 0)],
            isset($data['group_id']) ? Group::findOrFail($data['group_id']) : null,
            $data['description'] ?? null,
            $request->user(),
        );

        $bonus = collect($created)->whereIn('type', [Payment::DISCOUNT, Payment::CAMPAIGN_BONUS])->sum('amount');

        return back()->with('success', "To'lov qabul qilindi.".($bonus ? ' Chegirma/bonus: '.\App\Support\Format::money($bonus).'.' : ''));
    }

    public function discount(Request $request, User $student): RedirectResponse
    {
        $this->authorize('payments.discount');

        $data = $request->validate([
            'group_id' => ['required', BranchContext::exists('groups')],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
        ], [], ['group_id' => 'Guruh', 'amount' => 'Chegirma summasi', 'description' => 'Sabab']);

        $this->payments->manualDiscount($student, Group::findOrFail($data['group_id']), (int) $data['amount'], $data['description'], $request->user());

        return back()->with('success', 'Chegirma berildi.');
    }

    /** v13.2 (1-bosqich): faqat sAdmin. Summani tekshiradi va «Tekshiring» sahifasiga (parol bilan tasdiq) yo'naltiradi. */
    public function specialDiscountInitiate(Request $request, User $student, ConfirmationService $confirm): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'special_amount' => ['required', 'integer', 'min:1', 'max:'.PaymentService::MAX_SPECIAL_DISCOUNT],
            'special_description' => ['required', 'string', 'max:255'],
        ], [
            'special_amount.max' => "Maxsus chegirma ".Format::money(PaymentService::MAX_SPECIAL_DISCOUNT)." dan oshmasligi kerak.",
        ], ['special_amount' => 'Chegirma summasi', 'special_description' => 'Sabab']);

        $before = (int) $student->balance;
        $amount = (int) $data['special_amount'];

        $token = $confirm->stash(
            'payment.special_discount',
            ['student_id' => $student->id, 'amount' => $amount, 'description' => $data['special_description']],
            'Maxsus chegirma (balansga bonus)',
            [
                ["O'quvchi", $student->name],
                ['Summa', Format::money($amount)],
                ['Sabab', $data['special_description']],
            ],
            route('students.show', $student),
            ['label' => "O'quvchi balansi", 'before' => $before, 'after' => $before + $amount],
            "Bu chegirma o'quvchi balansiga to'g'ridan-to'g'ri qo'shiladi. Tasdiqlash uchun parolingizni kiriting.",
        );

        return redirect()->route('confirm.show', $token);
    }

    public function refund(Request $request, User $student): RedirectResponse
    {
        $this->authorize('payments.refund');

        $data = $request->validate([
            'method' => ['required', Rule::enum(PayMethod::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
        ], [], ['method' => "To'lov turi", 'amount' => 'Summa', 'description' => 'Sabab']);

        $this->payments->refund($student, PayMethod::from($data['method']), (int) $data['amount'], $data['description'], $request->user());

        return back()->with('success', "To'lov qaytarildi. Admin tasdiqlashi kutilmoqda.");
    }

    /** v8 B2: to'lov cheki - A5 yoki 80mm (termoprinter) formatda chop etish uchun. */
    public function receipt(Request $request, Payment $payment)
    {
        $this->authorize('payments.view');

        $payment->load(['student', 'group', 'creator', 'branch']);

        return view('payments.receipt', [
            'payment' => $payment,
            'format' => $request->query('format') === '80mm' ? '80mm' : 'a5',
        ]);
    }

    /** 1-bosqich (storno): sababni tekshiradi va «Tekshiring» sahifasiga yo'naltiradi — 2-bosqichda ConfirmationController bajaradi. */
    public function reverseInitiate(Request $request, Payment $payment, ConfirmationService $confirm): RedirectResponse
    {
        $this->authorize('payments.reverse');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [], ['reason' => 'Sabab']);

        if (! $payment->isReversible()) {
            throw ValidationException::withMessages(['payment' => "Bu yozuvni storno qilib bo'lmaydi (turi mos emas yoki allaqachon storno qilingan)."]);
        }

        $student = $payment->student;
        $before = (int) $student->balance;

        $token = $confirm->stash(
            'payment.reverse',
            ['payment_id' => $payment->id, 'reason' => $data['reason']],
            "To'lovni storno qilish",
            [
                ["O'quvchi", $student->name],
                ['Turi', $payment->typeLabel()],
                ['Summa', Format::money($payment->amount)],
                ['Sabab', $data['reason']],
            ],
            route('students.show', $student),
            ['label' => "O'quvchi balansi", 'before' => $before, 'after' => $before - $payment->amount],
            $this->payments->reversalWarning($payment),
        );

        return redirect()->route('confirm.show', $token);
    }
}
