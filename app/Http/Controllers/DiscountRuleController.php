<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Oldindan to'lov chegirmasi muddati (filial bo'yicha). */
class DiscountRuleController extends Controller
{
    public function edit()
    {
        $this->authorize('settings.branch');

        return view('settings.discount-rules', ['branch' => Branch::findOrFail(BranchContext::id())]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('settings.branch');

        $data = $request->validate([
            'discount_days_before' => ['required', 'integer', 'min:0', 'max:365'],
            'discount_days_after' => ['required', 'integer', 'min:0', 'max:365'],
        ], [], ['discount_days_before' => 'Boshlanishidan oldin (kun)', 'discount_days_after' => 'Boshlanishidan keyin (kun)']);

        $branch = Branch::findOrFail(BranchContext::id());
        $branch->update($data);
        AuditLog::record('settings.discount_rules', $branch, "Chegirma muddati: {$data['discount_days_before']} kun oldin, {$data['discount_days_after']} kun keyin");

        return back()->with('success', 'Chegirma qoidalari saqlandi.');
    }
}
