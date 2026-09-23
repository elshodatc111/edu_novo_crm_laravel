<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v7:
 *  1) Ehson balansi naqt va plastikka bo'linadi (mavjud qoldiq jurnalga yozilgan holda ko'chiriladi);
 *  2) balans harakati to'lov yozuviga bog'lanadi (naqt/plastik ko'rinishi uchun), eski yozuvlar moslab to'ldiriladi;
 *  3) filialsiz jurnal yozuvlari foydalanuvchi filialiga biriktiriladi;
 *  4) mavjud adminlarga «qarzdor o'quvchini guruhga qo'shish» ruxsati beriladi.
 * Hammasi qo'shimcha (additive): mavjud ma'lumot o'chirilmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balance_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_id')->nullable()->after('group_id');
            $table->index('payment_id');
        });

        $this->splitCharity();
        $this->linkPayments();
        $this->backfillAuditBranches();

        $now = now();
        $rows = DB::table('users')->where('role', 'admin')->pluck('id')->map(fn ($id) => [
            'user_id' => $id, 'permission' => 'groups.enroll_debtor', 'created_at' => $now, 'updated_at' => $now,
        ])->all();
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('user_permissions')->insertOrIgnore($chunk);
        }
    }

    /**
     * Eski umumiy ehson hamyoni (treasury_charity) qoldig'i naqt va plastik ehsonga bo'linadi.
     * Nisbat: shu filialda ehsonga tushgan summalarning qaysi kassadan (naqt/plastik) kelganiga qarab;
     * ma'lumot bo'lmasa hammasi naqt ehsonga o'tadi.
     */
    private function splitCharity(): void
    {
        $accounts = DB::table('wallets')->where('code', 'treasury_charity')->where('balance', '>', 0)->get();

        foreach ($accounts as $account) {
            $in = DB::table('wallet_transactions as w')
                ->join('cash_requests as r', function ($join) {
                    $join->on('r.id', '=', 'w.subject_id')->where('w.subject_type', '=', 'CashRequest');
                })
                ->where('w.branch_id', $account->branch_id)->where('w.wallet', 'treasury_charity')->where('w.type', 'charity_in')
                ->selectRaw("coalesce(sum(case when r.method = 'cash' then w.amount end), 0) as cash, coalesce(sum(case when r.method = 'card' then w.amount end), 0) as card")
                ->first();

            $total = (int) $in->cash + (int) $in->card;
            $balance = (int) $account->balance;
            $cash = $total > 0 ? (int) round($balance * (int) $in->cash / $total) : $balance;
            $card = $balance - $cash;

            DB::transaction(function () use ($account, $balance, $cash, $card) {
                $now = now();
                $this->move($account->branch_id, 'treasury_charity', -$balance, 0, $now);
                foreach (['treasury_charity_cash' => $cash, 'treasury_charity_card' => $card] as $code => $amount) {
                    if ($amount > 0) {
                        $this->move($account->branch_id, $code, $amount, null, $now);
                    }
                }
            });
        }
    }

    private function move(int $branchId, string $code, int $amount, ?int $forceBalance, $now): void
    {
        $existing = DB::table('wallets')->where('branch_id', $branchId)->where('code', $code)->lockForUpdate()->first();
        $new = $forceBalance ?? ((int) ($existing->balance ?? 0) + $amount);

        if ($existing) {
            DB::table('wallets')->where('id', $existing->id)->update(['balance' => $new, 'updated_at' => $now]);
        } else {
            DB::table('wallets')->insert(['branch_id' => $branchId, 'code' => $code, 'balance' => $new, 'created_at' => $now, 'updated_at' => $now]);
        }

        DB::table('wallet_transactions')->insert([
            'branch_id' => $branchId, 'wallet' => $code, 'amount' => $amount, 'balance_after' => $new, 'type' => 'charity_split',
            'description' => "Ehson balansi naqt va plastikka bo'lindi", 'created_at' => $now,
        ]);
    }

    /**
     * Filialsiz qolgan jurnal yozuvlari: filialga bog'langan foydalanuvchi (admin, menejer va h.k.) harakatlari
     * o'sha filialga biriktiriladi, shunda filial jurnalida ko'rinadi. sAdmin harakatlari o'zgarmaydi.
     */
    private function backfillAuditBranches(): void
    {
        DB::statement('UPDATE audit_logs SET branch_id = (SELECT users.branch_id FROM users WHERE users.id = audit_logs.user_id)
            WHERE branch_id IS NULL AND user_id IS NOT NULL AND (SELECT users.branch_id FROM users WHERE users.id = audit_logs.user_id) IS NOT NULL');
    }

    /**
     * Eski balans yozuvlarini to'lov yozuvlari bilan moslaydi: bir xil o'quvchi, summa, tur, kim kiritgan va vaqt.
     * Har bir to'lov faqat bitta balans yozuviga bog'lanadi.
     */
    private function linkPayments(): void
    {
        $map = [
            'payment' => 'payment', 'payment_refund' => 'refund',
            'discount' => 'discount', 'manual_discount' => 'discount', 'campaign_bonus' => 'campaign_bonus',
        ];

        DB::table('balance_transactions')->whereIn('type', array_keys($map))->whereNull('payment_id')->orderBy('id')
            ->select('id', 'student_id', 'type', 'amount', 'created_by', 'created_at')
            ->chunkById(500, function ($rows) use ($map) {
                foreach ($rows as $row) {
                    $time = \Carbon\Carbon::parse($row->created_at);
                    $payment = DB::table('payments')
                        ->where('student_id', $row->student_id)->where('type', $map[$row->type])->where('amount', abs($row->amount))
                        ->where('created_by', $row->created_by)
                        ->whereBetween('created_at', [$time->copy()->subSeconds(3)->toDateTimeString(), $time->copy()->addSeconds(3)->toDateTimeString()])
                        ->whereNotIn('id', DB::table('balance_transactions')->whereNotNull('payment_id')->where('student_id', $row->student_id)->select('payment_id'))
                        ->orderBy('id')->value('id');

                    if ($payment) {
                        DB::table('balance_transactions')->where('id', $row->id)->update(['payment_id' => $payment]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Ehson qoldig'i (naqt + plastik) eski umumiy ehson hamyoniga qaytariladi, shunda v6 kodida ham to'g'ri ko'rinadi
        foreach (DB::table('wallets')->whereIn('code', ['treasury_charity_cash', 'treasury_charity_card'])->where('balance', '>', 0)->get()->groupBy('branch_id') as $branchId => $accounts) {
            DB::transaction(function () use ($branchId, $accounts) {
                $now = now();
                $sum = (int) $accounts->sum('balance');
                foreach ($accounts as $a) {
                    $this->move((int) $branchId, $a->code, -(int) $a->balance, 0, $now);
                }
                $this->move((int) $branchId, 'treasury_charity', $sum, null, $now);
            });
        }

        Schema::table('balance_transactions', function (Blueprint $table) {
            $table->dropIndex(['payment_id']);
            $table->dropColumn('payment_id');
        });
        DB::table('user_permissions')->where('permission', 'groups.enroll_debtor')->delete();
    }
};
