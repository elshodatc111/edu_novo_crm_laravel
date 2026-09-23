<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Services\LeadService;
use App\Support\BranchContext;
use App\Support\Format;
use App\Support\SafeInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    public function __construct(private LeadService $leads) {}

    public function index(Request $request)
    {
        $this->authorize('leads.view');

        $status = SafeInput::string($request->input('status'), Lead::NEW, 20);
        $counts = Lead::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $total = (int) $counts->sum();

        // Voronka: davr bo'yicha (30/90/365 kun yoki hammasi)
        $days = in_array((int) $request->input('days'), [30, 90, 365], true) ? (int) $request->input('days') : 0;
        $fq = fn () => Lead::query()->when($days, fn ($q) => $q->where('created_at', '>=', now()->subDays($days)->startOfDay()));
        $fc = $fq()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $fAll = (int) $fc->sum();
        // "Ko'rib chiqilgan" = jarayonda + qabul qilingan (yangi va bekor qilinganlar hisobga olinmaydi)
        $funnel = [
            ['label' => 'Jami murojaat', 'value' => $fAll],
            ['label' => "Ko'rib chiqilgan", 'value' => (int) ($fc[Lead::IN_PROGRESS] ?? 0) + (int) ($fc[Lead::CONVERTED] ?? 0)],
            ['label' => 'Qabul qilingan', 'value' => (int) ($fc[Lead::CONVERTED] ?? 0)],
        ];

        $leads = Lead::with(['source', 'student'])
            ->where('status', $status)
            ->when($request->filled('source'), fn ($q) => $q->where('lead_source_id', $request->integer('source')))
            ->when(SafeInput::string($request->input('q')), function ($q, $term) {
                $digits = Format::digits($term);
                $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->when($digits !== '', fn ($w2) => $w2->orWhere('phone', 'like', "%{$digits}%")));
            })
            ->orderByDesc('id')->paginate(25)->withQueryString();

        $bySource = Lead::query()->leftJoin('lead_sources', 'lead_sources.id', '=', 'leads.lead_source_id')
            ->selectRaw("coalesce(lead_sources.name, 'Ko''rsatilmagan') as source, count(*) as total, sum(case when leads.status = 'converted' then 1 else 0 end) as converted")
            ->groupBy('lead_sources.name')->orderByDesc('total')->get();

        return view('leads.index', [
            'leads' => $leads, 'status' => $status, 'counts' => $counts, 'total' => $total,
            'rate' => $total ? round(($counts[Lead::CONVERTED] ?? 0) * 100 / $total) : 0,
            'funnel' => $funnel, 'funnelCancelled' => (int) ($fc[Lead::CANCELLED] ?? 0), 'funnelNew' => (int) ($fc[Lead::NEW] ?? 0), 'days' => $days,
            'bySource' => $bySource, 'sources' => LeadSource::orderBy('name')->get(['id', 'name']),
            'formBranch' => BranchContext::id() ? Branch::find(BranchContext::id()) : null,
        ]);
    }

    public function create()
    {
        $this->authorize('leads.manage');

        return view('leads.form', ['sources' => LeadSource::active()->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('leads.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new \App\Rules\UzPhone],
            'phone2' => ['nullable', 'string', new \App\Rules\UzPhone],
            'address' => ['nullable', 'string', 'max:255'],
            'lead_source_id' => ['nullable', BranchContext::exists('lead_sources')],
        ], [], ['name' => 'F.I.O', 'phone' => 'Telefon', 'phone2' => "Qo'shimcha telefon", 'address' => 'Manzil', 'lead_source_id' => 'Manba']);

        $lead = $this->leads->register(\App\Models\Branch::findOrFail(BranchContext::id()), $data, $request->user());

        return redirect()->route('leads.show', $lead)->with('success', "Murojat qo'shildi.");
    }

    public function show(Lead $lead)
    {
        $this->authorize('leads.view');

        return view('leads.show', [
            'lead' => $lead->load(['source', 'student', 'notes.user']),
            'existing' => $lead->isOpen() ? $this->leads->findStudentByPhone($lead->branch_id, $lead->phone) : null,
            'groups' => auth()->user()->can('groups.members')
                ? \App\Models\Group::whereDate('ends_on', '>=', today()->subDays(\App\Services\EnrollmentService::LATE_ENROLL_DAYS))->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function note(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('leads.manage');

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']], [], ['body' => 'Izoh']);
        $this->leads->addNote($lead, $data['body'], $request->user());

        return back()->with('success', 'Izoh saqlandi.');
    }

    public function cancel(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('leads.manage');

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->leads->cancel($lead, $data['reason'] ?? null, $request->user());

        return back()->with('success', 'Murojat bekor qilindi.');
    }

    public function reopen(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('leads.manage');

        $this->leads->reopen($lead, $request->user());

        return back()->with('success', 'Murojat qayta ochildi.');
    }

    public function convert(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('leads.manage');
        abort_unless($request->user()->can('students.create'), 403);

        $data = $request->validate([
            'about' => ['nullable', 'string', 'max:2000'],
            'group_id' => $request->user()->can('groups.members') ? ['nullable', BranchContext::exists('groups')] : ['prohibited'],
        ], [], ['about' => 'Eslatma', 'group_id' => 'Guruh']);

        [, $student, $password] = DB::transaction(fn () => $this->leads->convert($lead, $request->user(), $data['about'] ?? null, $data['group_id'] ?? null));

        $redirect = redirect()->route('students.show', $student)->with('success', $password ? "O'quvchi yaratildi: {$student->name}." : "Mavjud o'quvchi bilan bog'landi: {$student->name}.");

        return $password ? $redirect->with('credentials', ['login' => $student->username, 'password' => $password]) : $redirect;
    }

    /** AI tahlilini qayta bajarish. */
    public function analyze(Lead $lead, \App\Services\AiLeadService $ai): RedirectResponse
    {
        $this->authorize('leads.manage');

        try {
            $ai->analyze($lead, force: true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'AI tahlili yangilandi.');
    }
}
