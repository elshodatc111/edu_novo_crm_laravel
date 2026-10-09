<?php

namespace App\Http\Controllers\Api;

use App\Enums\PayMethod;
use App\Enums\Wallet;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Services\ConfirmationService;
use App\Services\FinanceService;
use App\Services\WalletService;
use App\Support\BranchContext;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Mobil Moliya.
 *
 * - overview / deposit: ko'rish va shaxsiy mablag' kiritish (pul ICHKARIGA kiradi).
 * - v13: outflowPreview / outflowConfirm - balansdan chiqim, xarajat, ehson chiqimi.
 *   Veb'dagi "Tekshiring" sahifasining mobil (sessiyasiz) ekvivalenti, IKKI BOSQICHLI:
 *     1) preview: maydonlar veb bilan bir xil tekshiriladi, mablag' yetarliligi tekshiriladi,
 *        ma'lumot SERVERDA (Cache) saqlanadi va bir martalik token qaytadi. Pul harakatlanmaydi.
 *     2) confirm: token + foydalanuvchining O'Z PAROLI. Token shu foydalanuvchi va shu filialniki,
 *        eskirmagan va ishlatilmagan bo'lishi shart. Amal FinanceService orqali (jurnalga yozilib) bajariladi.
 *   Tasdiqlashda summa/turi o'zgartirib bo'lmaydi - hammasi serverdagi saqlangan ma'lumotdan olinadi.
 */
class FinanceController extends Controller
{
    private const TYPES = ['withdraw', 'expense', 'charity'];
    private const CACHE = 'api-outflow:';
    private const USED = 'api-outflow-used:';
    private const MAX_PASSWORD_ATTEMPTS = 5;

    public function overview(Request $request, WalletService $wallets): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('finance.view'), 403);

        $balances = $wallets->balances(BranchContext::id());
        $branch = Branch::findOrFail(BranchContext::id());

        return response()->json(['success' => true, 'data' => [
            'treasury_cash' => $balances[Wallet::TreasuryCash->value],
            'treasury_card' => $balances[Wallet::TreasuryCard->value],
            'charity_cash' => $balances[Wallet::TreasuryCharityCash->value],
            'charity_card' => $balances[Wallet::TreasuryCharityCard->value],
            'charity_total' => $balances[Wallet::TreasuryCharityCash->value] + $balances[Wallet::TreasuryCharityCard->value] + $balances[Wallet::TreasuryCharity->value],
            'charity_percent' => (float) $branch->charity_percent,
            // v13: ilova chiqim tugmalarini ko'rsatishi uchun
            'can_manage' => (bool) $user->can('finance.manage'),
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

    /**
     * 1-bosqich. Body: type = withdraw | expense | charity, amount, description,
     * withdraw uchun `source` (cash|card); expense/charity uchun `method` (cash|card); expense uchun ixtiyoriy `category_id`.
     */
    public function outflowPreview(Request $request, FinanceService $finance): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('finance.manage'), 403);

        $type = $request->validate(['type' => ['required', Rule::in(self::TYPES)]], [], ['type' => 'Amal turi'])['type'];

        $common = [
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'description' => ['required', 'string', 'max:255'],
        ];
        $names = ['source' => 'Balans', 'method' => 'Turi', 'amount' => 'Summa', 'description' => 'Izoh', 'category_id' => 'Xarajat turi'];

        if ($type === 'withdraw') {
            $data = $request->validate(['source' => ['required', Rule::in(FinanceService::WITHDRAW_SOURCES)]] + $common, [], $names);
            $wallet = $finance->withdrawWallet($data['source']);
            $title = 'Balansdan chiqim';
            $label = 'Qaysi balansdan';
            $payload = ['source' => $data['source']];
            $extraRows = [];
        } elseif ($type === 'charity') {
            $data = $request->validate(['method' => ['required', Rule::enum(PayMethod::class)]] + $common, [], $names);
            $wallet = PayMethod::from($data['method'])->charity();
            $title = 'Ehson chiqimi';
            $label = 'Qaysi ehsondan';
            $payload = ['method' => $data['method']];
            $extraRows = [];
        } else {
            $data = $request->validate([
                'method' => ['required', Rule::enum(PayMethod::class)],
                'category_id' => ['nullable', BranchContext::exists('expense_categories')->where('is_active', true)],
            ] + $common, [], $names);
            $wallet = PayMethod::from($data['method'])->treasury();
            $title = 'Xarajat';
            $label = 'Qaysi balansdan';
            $categoryId = ! empty($data['category_id']) ? (int) $data['category_id'] : null;
            $categoryName = $categoryId ? ExpenseCategory::find($categoryId)?->name : null;
            $payload = ['method' => $data['method'], 'category_id' => $categoryId];
            $extraRows = $categoryName ? [['label' => 'Xarajat turi', 'value' => $categoryName]] : [];
        }

        $amount = (int) $data['amount'];
        $before = $finance->assertAvailable($wallet, $amount);

        $token = Str::random(40);
        $ttl = ConfirmationService::TTL_MINUTES * 60;
        Cache::put(self::CACHE.$token, [
            'type' => $type,
            'payload' => $payload + ['amount' => $amount, 'description' => $data['description']],
            'user_id' => $user->id,
            'branch_id' => BranchContext::id(),
            'expires_at' => now()->addSeconds($ttl)->timestamp,
        ], $ttl);

        return response()->json(['success' => true, 'data' => [
            'token' => $token,
            'type' => $type,
            'title' => $title,
            'rows' => array_merge(
                [['label' => $label, 'value' => $wallet->label()], ['label' => 'Summa', 'value' => Format::money($amount)]],
                $extraRows,
                [['label' => 'Izoh', 'value' => $data['description']]],
            ),
            'amount' => $amount,
            'balance' => ['label' => $wallet->label(), 'before' => $before, 'after' => $before - $amount],
            'expires_in' => $ttl,
        ]]);
    }

    /** 2-bosqich. Body: token, password (foydalanuvchining o'z paroli). */
    public function outflowConfirm(Request $request, FinanceService $finance, WalletService $wallets): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('finance.manage'), 403);

        $data = $request->validate([
            'token' => ['required', 'string', 'size:40'],
            'password' => ['required', 'string', 'max:255'],
        ], [], ['token' => 'Tasdiqlash kodi', 'password' => 'Parol']);

        // Parolni saralab topishdan himoya: foydalanuvchi bo'yicha urinishlar cheklangan.
        $rateKey = 'outflow-password:'.$user->id;
        if (RateLimiter::tooManyAttempts($rateKey, self::MAX_PASSWORD_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($rateKey) / 60);
            throw ValidationException::withMessages(['password' => "Urinishlar ko'p. {$minutes} daqiqadan keyin qayta urinib ko'ring."]);
        }

        $token = $data['token'];
        $item = Cache::get(self::CACHE.$token);
        if (! $item
            || $item['expires_at'] <= now()->timestamp
            || $item['user_id'] !== $user->id
            || $item['branch_id'] !== BranchContext::id()
            || Cache::has(self::USED.$token)) {
            throw ValidationException::withMessages(['token' => "Tasdiqlash muddati tugagan yoki yaroqsiz. Amalni qaytadan kiriting."]);
        }

        if (! is_string($user->password) || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($rateKey, 15 * 60);
            throw ValidationException::withMessages(['password' => "Parol noto'g'ri."]);
        }
        RateLimiter::clear($rateKey);

        // Bir martalik "olib qo'yish": parallel ikki so'rov ham amalni faqat bir marta bajaradi.
        $lock = Cache::lock('api-outflow-lock:'.$token, 20);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['token' => "Amal bajarilmoqda. Bir necha soniyadan keyin natijani tekshiring."]);
        }

        try {
            if (Cache::has(self::USED.$token)) {
                throw ValidationException::withMessages(['token' => "Bu amal allaqachon bajarilgan."]);
            }
            Cache::put(self::USED.$token, true, now()->addMinutes(ConfirmationService::TTL_MINUTES + 5));
            Cache::forget(self::CACHE.$token);

            $p = $item['payload'];
            $amount = (int) $p['amount'];

            $wallet = DB::transaction(function () use ($item, $p, $amount, $finance, $user) {
                switch ($item['type']) {
                    case 'withdraw':
                        $wallet = $finance->withdrawWallet($p['source']);
                        $finance->assertAvailable($wallet, $amount);
                        $finance->withdraw($p['source'], $amount, $p['description'], $user);
                        break;
                    case 'charity':
                        $method = PayMethod::from($p['method']);
                        $wallet = $method->charity();
                        $finance->assertAvailable($wallet, $amount);
                        $finance->charityWithdraw($method, $amount, $p['description'], $user);
                        break;
                    default:
                        $method = PayMethod::from($p['method']);
                        $wallet = $method->treasury();
                        $finance->assertAvailable($wallet, $amount);
                        $finance->expense($method, $amount, $p['description'], $user, $p['category_id'] ?? null);
                }

                return $wallet;
            });
        } finally {
            $lock->release();
        }

        $messages = ['withdraw' => 'Balansdan chiqim bajarildi.', 'expense' => 'Xarajat saqlandi.', 'charity' => 'Ehson chiqimi bajarildi.'];

        return response()->json([
            'success' => true,
            'message' => $messages[$item['type']],
            'data' => ['balance' => ['label' => $wallet->label(), 'after' => $wallets->balance(BranchContext::id(), $wallet)]],
        ]);
    }
}
