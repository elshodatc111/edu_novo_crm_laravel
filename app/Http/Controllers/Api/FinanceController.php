<?php

namespace App\Http\Controllers\Api;

use App\Enums\PayMethod;
use App\Enums\Wallet;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\FinanceService;
use App\Services\WalletService;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * v11: mobil ilovada Moliya - FAQAT ko'rish + shaxsiy mablag' kiritish (pul ICHKARIGA kiradi,
 * xavfsiz). Balansdan chiqim/xarajat/ehson chiqimi ataylab mobilga chiqarilmagan - bular
 * veb'da ikki bosqichli tasdiqlash ("Tekshiring" sahifasi) bilan himoyalangan, mobil ilova
 * bu qo'shimcha xavfsizlik qatlamisiz pul CHIQARISHga imkon bermasligi kerak.
 */
class FinanceController extends Controller
{
    public function overview(Request $request, WalletService $wallets): JsonResponse
    {
        abort_unless($request->user()->can('finance.view'), 403);

        $balances = $wallets->balances(BranchContext::id());
        $branch = Branch::findOrFail(BranchContext::id());

        return response()->json(['success' => true, 'data' => [
            'treasury_cash' => $balances[Wallet::TreasuryCash->value],
            'treasury_card' => $balances[Wallet::TreasuryCard->value],
            'charity_cash' => $balances[Wallet::TreasuryCharityCash->value],
            'charity_card' => $balances[Wallet::TreasuryCharityCard->value],
            'charity_total' => $balances[Wallet::TreasuryCharityCash->value] + $balances[Wallet::TreasuryCharityCard->value] + $balances[Wallet::TreasuryCharity->value],
            'charity_percent' => (float) $branch->charity_percent,
        ]]);
    }

    public function deposit(Request $request, FinanceService $finance): JsonResponse
    {
        abort_unless($request->user()->can('finance.deposit'), 403);

        $data = $request->validate([
            'method' => ['required', Rule::enum(PayMethod::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'description' => ['required', 'string', 'max:255'],
        ], [], ['method' => 'Turi', 'amount' => 'Summa', 'description' => 'Izoh (manba)']);

        $finance->deposit(PayMethod::from($data['method']), (int) $data['amount'], $data['description'], $request->user());

        return response()->json(['success' => true, 'message' => "Shaxsiy mablag' moliyaga kiritildi."]);
    }
}
