<?php

namespace App\Http\Controllers;

use App\Enums\PayMethod;
use App\Enums\Wallet;
use App\Models\CashClosing;
use App\Models\CashRequest;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Services\CashboxService;
use App\Services\PaymentService;
use App\Services\WalletService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashboxController extends Controller
{
    public function __construct(private CashboxService $cashbox) {}

    public function index(WalletService $wallets)
    {
        $this->authorize('cashbox.view');

        $branchId = \App\Support\BranchContext::id();
        $balances = $wallets->balances($branchId);

        $today = Payment::whereDate('created_at', today())->selectRaw("
            coalesce(sum(case when type = 'payment' and method = 'cash' and reversed_at is null then amount end), 0) as cash,
            coalesce(sum(case when type = 'payment' and method = 'card' and reversed_at is null then amount end), 0) as card
        ")->first();

        return view('cashbox.index', [
            'cash' => $balances[Wallet::TillCash->value],
            'card' => $balances[Wallet::TillCard->value],
            'today' => $today,
            'pending' => CashRequest::with('requester')->where('status', CashRequest::PENDING)->orderBy('id')->get(),
            'history' => auth()->user()->can('cashbox.history')
                ? CashRequest::with(['requester', 'decider', 'category'])->where('status', '!=', CashRequest::PENDING)->whereDate('updated_at', '>=', today()->subDays(30))->orderByDesc('id')->limit(50)->get()
                : null,
            'refunds' => Payment::with(['student', 'creator'])->where('type', Payment::REFUND)->whereNull('refund_confirmed_at')->orderBy('id')->get(),
            'expenseCategories' => auth()->user()->can('cashbox.request') ? ExpenseCategory::where('branch_id', \App\Support\BranchContext::id())->active()->orderBy('name')->get() : null,
            'closings' => auth()->user()->can('cashbox.close')
                ? CashClosing::with('closer')->orderByDesc('id')->limit(20)->get()
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('cashbox.request') || $user->can('cashbox.withdraw'), 403);

        $data = $request->validate([
            'kind' => ['required', Rule::in([CashRequest::WITHDRAWAL, CashRequest::EXPENSE])],
            'method' => ['required', Rule::enum(PayMethod::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'description' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
        ], [], ['kind' => 'Turi', 'method' => "To'lov turi", 'amount' => 'Summa', 'description' => 'Izoh', 'category_id' => 'Xarajat turi']);

        // v13: tur bo'yicha alohida ruxsat - xarajat `cashbox.request`, chiqim `cashbox.withdraw`
        abort_unless($user->can(CashRequest::permissionFor($data['kind'])), 403);

        $this->cashbox->request($data['kind'], PayMethod::from($data['method']), (int) $data['amount'], $data['description'], $request->user(), isset($data['category_id']) ? (int) $data['category_id'] : null);

        return back()->with('success', "So'rov yaratildi. Pul kassadan yechildi, admin tasdiqlashi kutilmoqda.");
    }

    /** v8 B3: kassa smenasini yopish - farq faqat yoziladi, kassa qo'lda o'zgartirilmaydi. */
    public function closeShift(Request $request): RedirectResponse
    {
        $this->authorize('cashbox.close');

        $data = $request->validate([
            'actual_cash' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['actual_cash' => 'Haqiqiy naqt', 'note' => 'Izoh']);

        $closing = $this->cashbox->close((int) $data['actual_cash'], $data['note'] ?? null, $request->user());

        $message = $closing->difference === 0
            ? "Smena yopildi, farq yo'q."
            : 'Smena yopildi. Farq: '.($closing->difference > 0 ? '+' : '').Format::money($closing->difference).'.';

        return back()->with('success', $message);
    }

    public function approve(Request $request, CashRequest $cashRequest): RedirectResponse
    {
        $this->authorize('cashbox.approve');

        $this->cashbox->approve($cashRequest, $request->user());

        return back()->with('success', 'Tasdiqlandi.');
    }

    public function cancel(Request $request, CashRequest $cashRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('cashbox.approve') || ($user->can($cashRequest->permission()) && $cashRequest->requested_by === $user->id), 403);

        $this->cashbox->cancel($cashRequest, $user);

        return back()->with('success', "So'rov bekor qilindi, pul kassaga qaytdi.");
    }

    public function confirmRefund(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $this->authorize('cashbox.approve');

        $payments->confirmRefund($payment, $request->user());

        return back()->with('success', 'Qaytarish tasdiqlandi.');
    }

    public function rejectRefund(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $this->authorize('cashbox.approve');

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'Sabab']);

        $payments->rejectRefund($payment, $data['reason'], $request->user());

        return back()->with('success', 'Qaytarish rad etildi, pul qaytarildi.');
    }
}
