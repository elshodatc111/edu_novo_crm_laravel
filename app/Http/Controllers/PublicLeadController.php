<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\LeadSource;
use App\Services\LeadService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Saytdan murojaat qoldirish (login talab qilinmaydi). */
class PublicLeadController extends Controller
{
    public function index()
    {
        $branches = Branch::active()->orderBy('name')->get(['id', 'name', 'code', 'address']);

        return $branches->count() === 1
            ? redirect()->route('apply.show', $branches->first()->code)
            : view('apply.index', compact('branches'));
    }

    public function show(Branch $branch)
    {
        abort_unless($branch->isActive(), 404);

        return view('apply.show', [
            'embed' => request()->boolean('embed'),
            'branch' => $branch,
            'sources' => LeadSource::withoutGlobalScopes()->where('branch_id', $branch->id)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Branch $branch, LeadService $leads): RedirectResponse
    {
        abort_unless($branch->isActive(), 404);

        // Botlardan himoya: yashirin maydon to'ldirilgan bo'lsa, jimgina rad etiladi
        if ($request->filled('website')) {
            return redirect()->route('apply.show', [$branch->code, 'embed' => $request->boolean('embed') ?: null])->with('sent', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new \App\Rules\UzPhone],
            'address' => ['nullable', 'string', 'max:255'],
            'lead_source_id' => ['nullable', \Illuminate\Validation\Rule::exists('lead_sources', 'id')->where('branch_id', $branch->id)->where('is_active', true)],
        ], [], ['name' => 'Ism familiya', 'phone' => 'Telefon', 'address' => 'Manzil', 'lead_source_id' => 'Manba']);

        $leads->register($branch, $data);

        return redirect()->route('apply.show', [$branch->code, 'embed' => $request->boolean('embed') ?: null])->with('sent', true);
    }
}
