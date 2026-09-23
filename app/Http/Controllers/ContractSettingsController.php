<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Services\ContractService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** v8 B2: shartnoma matni va imzo ma'lumotlari (filial bo'yicha). */
class ContractSettingsController extends Controller
{
    public function edit(ContractService $contracts)
    {
        $this->authorize('settings.branch');

        $branch = Branch::findOrFail(BranchContext::id());

        return view('settings.contract', [
            'branch' => $branch,
            'template' => $contracts->template($branch),
            'isDefault' => blank($branch->contract_template),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('settings.branch');

        $data = $request->validate([
            'stir' => ['nullable', 'string', 'max:30'],
            'director_name' => ['nullable', 'string', 'max:120'],
            'director_title' => ['nullable', 'string', 'max:60'],
            'contract_template' => ['nullable', 'string', 'max:20000'],
        ], [], ['stir' => 'STIR', 'director_name' => 'Direktor F.I.O.', 'director_title' => 'Direktor lavozimi', 'contract_template' => 'Shartnoma matni']);

        $branch = Branch::findOrFail(BranchContext::id());
        $branch->update($data);

        AuditLog::record('settings.contract', $branch, 'Shartnoma sozlamalari yangilandi');

        return back()->with('success', 'Shartnoma sozlamalari saqlandi.');
    }

    /** Standart namunaga qaytaradi (o'zi yozgan matnni bekor qiladi). */
    public function reset(): RedirectResponse
    {
        $this->authorize('settings.branch');

        $branch = Branch::findOrFail(BranchContext::id());
        $branch->update(['contract_template' => null]);

        AuditLog::record('settings.contract', $branch, 'Shartnoma matni standart namunaga qaytarildi');

        return back()->with('success', 'Shartnoma matni standart namunaga qaytarildi.');
    }
}
