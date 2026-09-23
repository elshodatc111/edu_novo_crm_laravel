<?php

namespace App\Http\Controllers\Api;

use App\Enums\PayMethod;
use App\Enums\Wallet;
use App\Http\Controllers\Controller;
use App\Models\CashRequest;
use App\Models\ExpenseCategory;
use App\Services\CashboxService;
use App\Services\WalletService;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * v11: mobil ilovada Kassa. Veb'dagi CashboxController bilan bir xil (u ham single-step,
 * ConfirmationService ishlatmaydi) - farqi shuki, kassa smenasini yopish va qaytarishni
 * tasdiqlash/rad etish (moliyaviy solishtirish, ko'proq e'tibor talab qiladi) ataylab
 * mobilga chiqarilmagan - bular veb orqali qoladi.
 */
class CashboxController extends Controller
{
    public function __construct(private CashboxService $cashbox) {}

    public function index(Request $request, WalletService $wallets): JsonResponse
    {
        abort_unless($request->user()->can('cashbox.view'), 403);

        $branchId = BranchContext::id();
        $balances = $wallets->balances($branchId);

        return response()->json(['success' => true, 'data' => [
            'cash' => $balances[Wallet::TillCash->value],
            'card' => $balances[Wallet::TillCard->value],
            'pending' => CashRequest::with('requester:id,name')->where('status', CashRequest::PENDING)->orderBy('id')->get()->map(fn ($r) => $this->row($r)),
            'history' => $request->user()->can('cashbox.history')
                ? CashRequest::with(['requester:id,name', 'decider:id,name', 'category:id,name'])->where('status', '!=', CashRequest::PENDING)
                    ->whereDate('updated_at', '>=', today()->subDays(30))->orderByDesc('id')->limit(50)->get()->map(fn ($r) => $this->row($r))
                : [],
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('cashbox.request'), 403);

        $data = $request->validate([
            'kind' => ['required', Rule::in([CashRequest::WITHDRAWAL, CashRequest::EXPENSE])],
            'method' => ['required', Rule::enum(PayMethod::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'description' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
        ], [], ['kind' => 'Turi', 'method' => "To'lov turi", 'amount' => 'Summa', 'description' => 'Izoh', 'category_id' => 'Xarajat turi']);

        $created = $this->cashbox->request($data['kind'], PayMethod::from($data['method']), (int) $data['amount'], $data['description'], $request->user(), isset($data['category_id']) ? (int) $data['category_id'] : null);

        return response()->json(['success' => true, 'message' => "So'rov yaratildi. Pul kassadan yechildi, admin tasdiqlashi kutilmoqda.", 'data' => $this->row($created)], 201);
    }

    public function approve(Request $request, CashRequest $cashRequest): JsonResponse
    {
        abort_unless($request->user()->can('cashbox.approve'), 403);

        $this->cashbox->approve($cashRequest, $request->user());

        return response()->json(['success' => true, 'message' => 'Tasdiqlandi.']);
    }

    public function cancel(Request $request, CashRequest $cashRequest): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('cashbox.approve') || ($user->can('cashbox.request') && $cashRequest->requested_by === $user->id), 403);

        $this->cashbox->cancel($cashRequest, $user);

        return response()->json(['success' => true, 'message' => "So'rov bekor qilindi, pul kassaga qaytdi."]);
    }

    public function expenseCategories(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('cashbox.request'), 403);

        return response()->json(['success' => true, 'data' => ExpenseCategory::where('branch_id', BranchContext::id())->active()->orderBy('name')->get(['id', 'name'])]);
    }

    private function row(CashRequest $r): array
    {
        return [
            'id' => $r->id, 'kind' => $r->kind, 'method' => $r->method->label(), 'amount' => $r->amount, 'description' => $r->description,
            'status' => $r->status, 'category' => $r->category?->name, 'requester' => $r->requester?->name, 'decider' => $r->decider?->name,
            'created_at' => $r->created_at->toIso8601String(),
        ];
    }
}
