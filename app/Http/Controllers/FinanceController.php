<?php

namespace App\Http\Controllers;

use App\Enums\PayMethod;
use App\Enums\Wallet;
use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\WalletTransaction;
use App\Services\ConfirmationService;
use App\Services\FinanceService;
use App\Services\WalletService;
use App\Support\BranchContext;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    public function __construct(private FinanceService $finance) {}

    public function index(Request $request, WalletService $wallets)
    {
        $this->authorize('finance.view');

        $balances = $wallets->balances(BranchContext::id());
        $treasury = [Wallet::TreasuryCash->value, Wallet::TreasuryCard->value, Wallet::TreasuryCharityCash->value, Wallet::TreasuryCharityCard->value, Wallet::TreasuryCharity->value];

        $filter = $request->input('wallet');
        $filterWallets = match ($filter) {
            'treasury_charity' => [Wallet::TreasuryCharityCash->value, Wallet::TreasuryCharityCard->value, Wallet::TreasuryCharity->value],
            Wallet::TreasuryCash->value, Wallet::TreasuryCard->value, Wallet::TreasuryCharityCash->value, Wallet::TreasuryCharityCard->value => [$filter],
            default => null,
        };

        return view('finance.index', [
            'balances' => $balances,
            'charityTotal' => $balances[Wallet::TreasuryCharityCash->value] + $balances[Wallet::TreasuryCharityCard->value] + $balances[Wallet::TreasuryCharity->value],
            'percent' => (float) Branch::findOrFail(BranchContext::id())->charity_percent,
            'expenseCategories' => ExpenseCategory::where('branch_id', BranchContext::id())->active()->orderBy('name')->get(),
            'history' => WalletTransaction::with(['creator', 'category:id,name'])->whereIn('wallet', $treasury)
                ->whereDate('created_at', '>=', today()->subDays(90))
                ->when($filterWallets, fn ($q) => $q->whereIn('wallet', $filterWallets))
                ->orderByDesc('id')->paginate(30)->withQueryString(),
        ]);
    }

    /** 1-bosqich: balansdan chiqimni tekshiradi va «Tekshiring» sahifasiga yo'naltiradi. */
    public function withdraw(Request $request, ConfirmationService $confirm): RedirectResponse
    {
        $this->authorize('finance.manage');

        $data = $request->validate([
            'source' => ['required', Rule::in(FinanceService::WITHDRAW_SOURCES)],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'description' => ['required', 'string', 'max:255'],
        ], [], ['source' => 'Balans', 'amount' => 'Summa', 'description' => 'Izoh']);

        $wallet = $this->finance->withdrawWallet($data['source']);
        $before = $this->finance->assertAvailable($wallet, (int) $data['amount']);

        $token = $confirm->stash('finance.withdraw', ['source' => $data['source'], 'amount' => (int) $data['amount'], 'description' => $data['description']],
            'Balansdan chiqim',
            [['Qaysi balansdan', $wallet->label()], ['Summa', Format::money($data['amount'])], ['Izoh', $data['description']]],
            route('finance.index'),
            ['label' => $wallet->label(), 'before' => $before, 'after' => $before - (int) $data['amount']]);

        return redirect()->route('confirm.show', $token);
    }

    /** 1-bosqich: xarajat. */
    public function expense(Request $request, ConfirmationService $confirm): RedirectResponse
    {
        $this->authorize('finance.manage');

        $data = $request->validate([
            'method' => ['required', Rule::enum(PayMethod::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'description' => ['required', 'string', 'max:255'],
            // v10: ixtiyoriy xarajat turi (Sozlamalar → Xarajat turlari).
            'category_id' => ['nullable', BranchContext::exists('expense_categories')->where('is_active', true)],
        ], [], ['method' => 'Turi', 'amount' => 'Summa', 'description' => 'Izoh', 'category_id' => 'Xarajat turi']);

        $method = PayMethod::from($data['method']);
        $before = $this->finance->assertAvailable($method->treasury(), (int) $data['amount']);
        $categoryId = ! empty($data['category_id']) ? (int) $data['category_id'] : null;
        $categoryName = $categoryId ? ExpenseCategory::find($categoryId)?->name : null;

        $token = $confirm->stash('finance.expense', ['method' => $data['method'], 'amount' => (int) $data['amount'], 'description' => $data['description'], 'category_id' => $categoryId],
            'Xarajat',
            array_filter([
                ['Qaysi balansdan', $method->treasury()->label()],
                ['Summa', Format::money($data['amount'])],
                $categoryName ? ['Xarajat turi', $categoryName] : null,
                ['Izoh', $data['description']],
            ]),
            route('finance.index'),
            ['label' => $method->treasury()->label(), 'before' => $before, 'after' => $before - (int) $data['amount']]);

        return redirect()->route('confirm.show', $token);
    }

    /** 1-bosqich: ehson balansidan chiqim (naqt ehsondan naqt, plastik ehsondan plastik). */
    public function charityWithdraw(Request $request, ConfirmationService $confirm): RedirectResponse
    {
        $this->authorize('finance.manage');

        $data = $request->validate([
            'method' => ['required', Rule::enum(PayMethod::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'description' => ['required', 'string', 'max:255'],
        ], [], ['method' => 'Ehson turi', 'amount' => 'Summa', 'description' => 'Izoh']);

        $method = PayMethod::from($data['method']);
        $before = $this->finance->assertAvailable($method->charity(), (int) $data['amount']);

        $token = $confirm->stash('finance.charity', ['method' => $data['method'], 'amount' => (int) $data['amount'], 'description' => $data['description']],
            'Ehson chiqimi',
            [['Qaysi ehsondan', $method->charity()->label()], ['Summa', Format::money($data['amount'])], ['Izoh', $data['description']]],
            route('finance.index'),
            ['label' => $method->charity()->label(), 'before' => $before, 'after' => $before - (int) $data['amount']]);

        return redirect()->route('confirm.show', $token);
    }

    /**
     * v9: egasi shaxsiy mablag'ini moliyaga kiritadi. Kirim xavfli emas, shuning uchun
     * chiqimlardagi kabi "Tekshiring" bosqichisiz darhol yoziladi.
     */
    public function deposit(Request $request): RedirectResponse
    {
        $this->authorize('finance.deposit');

        $data = $request->validate([
            'method' => ['required', Rule::enum(PayMethod::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'description' => ['required', 'string', 'max:255'],
        ], [], ['method' => 'Turi', 'amount' => 'Summa', 'description' => 'Izoh (manba)']);

        $this->finance->deposit(PayMethod::from($data['method']), (int) $data['amount'], $data['description'], $request->user());

        return back()->with('success', "Shaxsiy mablag' moliyaga kiritildi.");
    }

    public function charity(Request $request): RedirectResponse
    {
        $this->authorize('finance.manage');

        $data = $request->validate(['percent' => ['required', 'numeric', 'min:0', 'max:100']], [], ['percent' => 'Ehson foizi']);

        $this->finance->setCharityPercent((float) $data['percent']);

        return back()->with('success', 'Ehson foizi saqlandi.');
    }
}
