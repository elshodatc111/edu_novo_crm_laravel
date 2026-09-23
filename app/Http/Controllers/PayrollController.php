<?php

namespace App\Http\Controllers;

use App\Enums\PayMethod;
use App\Enums\Role;
use App\Enums\Wallet;
use App\Models\Group;
use App\Models\Payout;
use App\Models\User;
use App\Services\ConfirmationService;
use App\Services\PayrollService;
use App\Services\WalletService;
use App\Support\BranchContext;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** O'qituvchi va hodimlarning ish haqi. */
class PayrollController extends Controller
{
    public function __construct(private PayrollService $payroll) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $canTeachers = $user->can('teachers.view');
        $canStaff = $user->can('staff.view');
        abort_unless($canTeachers || $canStaff, 403);

        $tab = $request->input('tab', $canTeachers ? 'teachers' : 'staff');
        abort_unless(($tab === 'teachers' && $canTeachers) || ($tab === 'staff' && $canStaff), 403);

        $people = User::visibleToContext()
            ->whereIn('role', $tab === 'teachers' ? [Role::Teacher] : [Role::Admin, Role::Manager, Role::Operator])
            ->where('status', 'active')->orderBy('name')->get();

        $rows = $people->map(fn (User $p) => [
            'user' => $p,
            'remaining' => $tab === 'teachers' ? collect($this->payroll->teacherAccruals($p))->sum(fn ($r) => max(0, $r['remaining'])) : null,
            'paid_month' => (int) Payout::where('recipient_id', $p->id)->whereDate('created_at', '>=', today()->startOfMonth())->sum('amount'),
        ]);

        return view('payroll.index', ['rows' => $rows, 'tab' => $tab, 'canTeachers' => $canTeachers, 'canStaff' => $canStaff]);
    }

    public function show(User $user, WalletService $wallets)
    {
        $this->authorizeView($user);

        $isTeacher = $user->role === Role::Teacher;
        $balances = $wallets->balances(BranchContext::id());

        return view('payroll.show', [
            'person' => $user,
            'isTeacher' => $isTeacher,
            'accruals' => $isTeacher ? $this->payroll->teacherAccruals($user) : [],
            'payouts' => Payout::with(['group', 'creator'])->where('recipient_id', $user->id)->orderByDesc('id')->limit(50)->get(),
            'canPay' => request()->user()->can($isTeacher ? 'teachers.pay' : 'staff.pay'),
            'treasuryCash' => $balances[Wallet::TreasuryCash->value],
            'treasuryCard' => $balances[Wallet::TreasuryCard->value],
        ]);
    }

    /** 1-bosqich: ish haqini tekshiradi va «Tekshiring» sahifasiga yo'naltiradi (2-bosqich: ConfirmationController). */
    public function pay(Request $request, User $user, ConfirmationService $confirm, WalletService $wallets): RedirectResponse
    {
        $this->authorizeView($user);
        $isTeacher = $user->role === Role::Teacher;
        $this->authorize($isTeacher ? 'teachers.pay' : 'staff.pay');

        $data = $request->validate([
            'method' => ['required', Rule::enum(PayMethod::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'group_id' => ['nullable', BranchContext::exists('groups')],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], ['method' => "To'lov turi", 'amount' => 'Summa', 'group_id' => 'Guruh', 'description' => 'Izoh']);

        $method = PayMethod::from($data['method']);
        $amount = (int) $data['amount'];
        $group = $isTeacher && ! empty($data['group_id']) ? Group::findOrFail($data['group_id']) : null;

        if ($group && $group->teacher_id !== $user->id) {
            throw ValidationException::withMessages(['group_id' => "Bu guruh shu o'qituvchiga tegishli emas."]);
        }

        $before = $wallets->balance(BranchContext::id(), $method->treasury());
        if ($amount > $before) {
            throw ValidationException::withMessages(['amount' => "{$method->treasury()->label()} da mablag' yetarli emas (mavjud: ".Format::money($before).').']);
        }

        $rows = [[$isTeacher ? "O'qituvchi" : 'Hodim', $user->name], ['Summa', Format::money($amount)], ["To'lov turi", $method->label()]];
        if ($group) {
            $rows[] = ['Guruh', $group->name];
        }
        $rows[] = ['Izoh', $data['description'] ?? '—'];

        $token = $confirm->stash('payroll.pay',
            ['user_id' => $user->id, 'group_id' => $group?->id, 'method' => $method->value, 'amount' => $amount, 'description' => $data['description'] ?? null],
            "Ish haqi to'lash", $rows, route('payroll.show', $user),
            ['label' => $method->treasury()->label(), 'before' => $before, 'after' => $before - $amount]);

        return redirect()->route('confirm.show', $token);
    }

    private function authorizeView(User $person): void
    {
        $this->authorize($person->role === Role::Teacher ? 'teachers.view' : 'staff.view');
        abort_unless(in_array($person->role, [Role::Teacher, Role::Admin, Role::Manager, Role::Operator], true), 404);
    }
}
