<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Services\UslugaService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** v13: o'qituvchi «Usluga» (staj ustamasi) sozlamalari. Ko'rish - settings.branch, o'zgartirish - faqat sAdmin. */
class UslugaSettingsController extends Controller
{
    public function edit()
    {
        $this->authorize('settings.branch');

        $branch = Branch::findOrFail(BranchContext::id());

        return view('settings.usluga', [
            'branch' => $branch,
            'tiers' => UslugaService::TIERS,
            'rates' => $branch->usluga_rates ?? [],
            'canEdit' => auth()->user()->isSuperAdmin(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $rules = ['usluga_from' => ['nullable', 'date']];
        $attributes = ['usluga_from' => 'Usluga qo\'llanadigan sana'];
        foreach (UslugaService::TIERS as $i => $label) {
            $rules["usluga_student_{$i}"] = ['nullable', 'integer', 'min:0', 'max:100000000'];
            $rules["usluga_bonus_{$i}"] = ['nullable', 'integer', 'min:0', 'max:100000000'];
            $attributes["usluga_student_{$i}"] = "O'quvchi usluga ({$label})";
            $attributes["usluga_bonus_{$i}"] = "Bonus usluga ({$label})";
        }
        $data = $request->validate($rules, [], $attributes);

        $rates = [];
        foreach (UslugaService::TIERS as $i => $label) {
            $rates[$i] = ['student' => (int) ($data["usluga_student_{$i}"] ?? 0), 'bonus' => (int) ($data["usluga_bonus_{$i}"] ?? 0)];
        }

        $branch = Branch::findOrFail(BranchContext::id());
        $old = ['usluga_from' => $branch->usluga_from?->toDateString(), 'usluga_rates' => $branch->usluga_rates];
        $branch->update(['usluga_from' => $data['usluga_from'] ?? null, 'usluga_rates' => $rates]);

        AuditLog::record('settings.usluga', $branch, 'Usluga sozlamalari yangilandi', $old, ['usluga_from' => $data['usluga_from'] ?? null, 'usluga_rates' => $rates]);

        return back()->with('success', 'Usluga sozlamalari saqlandi. Boshlangan guruhlar summasi o\'zgarmaydi.');
    }
}
