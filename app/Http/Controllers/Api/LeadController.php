<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Lead;
use App\Services\LeadService;
use App\Support\BranchContext;
use App\Support\Format;
use App\Support\SafeInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** v11: mobil ilovada Varonka (lidlar/CRM) bilan ishlash - veb'dagi LeadController bilan bir xil servis. */
class LeadController extends Controller
{
    public function __construct(private LeadService $leads) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('leads.view'), 403);

        $status = SafeInput::string($request->input('status'), Lead::NEW, 20);

        $leads = Lead::with(['source', 'student'])
            ->where('status', $status)
            ->when($request->filled('source'), fn ($q) => $q->where('lead_source_id', $request->integer('source')))
            ->when(SafeInput::string($request->input('q')), function ($q, $term) {
                $digits = Format::digits($term);
                $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->when($digits !== '', fn ($w2) => $w2->orWhere('phone', 'like', "%{$digits}%")));
            })
            ->orderByDesc('id')->paginate(min(50, max(5, $request->integer('per_page', 20))));

        return response()->json(['success' => true, 'data' => $leads->getCollection()->map(fn ($l) => $this->row($l)), 'meta' => [
            'page' => $leads->currentPage(), 'last_page' => $leads->lastPage(), 'total' => $leads->total(),
        ]]);
    }

    public function show(Request $request, Lead $lead): JsonResponse
    {
        abort_unless($request->user()->can('leads.view'), 403);

        $lead->load(['source', 'student', 'notes.user']);

        return response()->json(['success' => true, 'data' => $this->row($lead) + [
            'notes' => $lead->notes->map(fn ($n) => ['id' => $n->id, 'body' => $n->body, 'user' => $n->user?->name, 'created_at' => $n->created_at->toIso8601String()]),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('leads.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new \App\Rules\UzPhone],
            'phone2' => ['nullable', 'string', new \App\Rules\UzPhone],
            'address' => ['nullable', 'string', 'max:255'],
            'lead_source_id' => ['nullable', BranchContext::exists('lead_sources')],
        ], [], ['name' => "F.I.O", 'phone' => 'Telefon', 'phone2' => "Qo'shimcha telefon", 'address' => 'Manzil', 'lead_source_id' => 'Manba']);

        $lead = $this->leads->register(Branch::findOrFail(BranchContext::id()), $data, $request->user());

        return response()->json(['success' => true, 'message' => "Murojaat qo'shildi.", 'data' => $this->row($lead)], 201);
    }

    public function note(Request $request, Lead $lead): JsonResponse
    {
        abort_unless($request->user()->can('leads.manage'), 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']], [], ['body' => 'Izoh']);
        $this->leads->addNote($lead, $data['body'], $request->user());

        return response()->json(['success' => true, 'message' => 'Izoh saqlandi.']);
    }

    public function cancel(Request $request, Lead $lead): JsonResponse
    {
        abort_unless($request->user()->can('leads.manage'), 403);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->leads->cancel($lead, $data['reason'] ?? null, $request->user());

        return response()->json(['success' => true, 'message' => 'Murojat bekor qilindi.']);
    }

    public function reopen(Request $request, Lead $lead): JsonResponse
    {
        abort_unless($request->user()->can('leads.manage'), 403);

        $this->leads->reopen($lead, $request->user());

        return response()->json(['success' => true, 'message' => 'Murojat qayta ochildi.']);
    }

    public function convert(Request $request, Lead $lead): JsonResponse
    {
        abort_unless($request->user()->can('leads.manage') && $request->user()->can('students.create'), 403);

        $data = $request->validate([
            'about' => ['nullable', 'string', 'max:2000'],
            'group_id' => $request->user()->can('groups.members') ? ['nullable', BranchContext::exists('groups')] : ['prohibited'],
        ], [], ['about' => 'Eslatma', 'group_id' => 'Guruh']);

        [, $student, $password] = DB::transaction(fn () => $this->leads->convert($lead, $request->user(), $data['about'] ?? null, $data['group_id'] ?? null));

        return response()->json(['success' => true, 'message' => $password ? "O'quvchi yaratildi." : "Mavjud o'quvchi bilan bog'landi.", 'data' => [
            'student_id' => $student->id, 'name' => $student->name,
            'credentials' => $password ? ['login' => $student->username, 'password' => $password] : null,
        ]]);
    }

    private function row(Lead $lead): array
    {
        return [
            'id' => $lead->id, 'name' => $lead->name, 'phone' => $lead->phone, 'phone2' => $lead->phone2, 'address' => $lead->address,
            'status' => $lead->status, 'status_label' => $lead->status_label, 'source' => $lead->source?->name,
            'is_repeat' => (bool) $lead->is_repeat, 'created_at' => $lead->created_at->toIso8601String(),
        ];
    }
}
