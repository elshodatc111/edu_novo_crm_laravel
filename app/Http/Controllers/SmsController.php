<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Group;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Services\ConfirmationService;
use App\Services\SmsService;
use App\Support\BranchContext;
use App\Support\Format;
use App\Support\SafeInput;
use App\Support\SmsTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SmsController extends Controller
{
    public function index(Request $request, SmsService $sms)
    {
        $user = $request->user();
        abort_unless($user->canany(['sms.view', 'sms.send', 'sms.manage']), 403);

        $branch = BranchContext::id() ? Branch::find(BranchContext::id()) : null;
        $since = today()->subDays(30);

        $stats = SmsMessage::query()->whereDate('created_at', '>=', $since)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        $templates = [];
        if ($branch) {
            foreach (SmsTemplates::all() as $key => $def) {
                $templates[$key] = ['label' => $def['label']] + $sms->template($branch, $key);
            }
        }

        return view('sms.index', [
            'branch' => $branch,
            'stats' => $stats,
            'messages' => SmsMessage::with(['recipient', 'creator'])
                ->when(SafeInput::string($request->input('status')), fn ($q, $status) => $q->where('status', $status))
                ->when(SafeInput::string($request->input('q')), fn ($q, $term) => $q->where('phone', 'like', '%'.Format::digits($term).'%'))
                ->orderByDesc('id')->paginate(30)->withQueryString(),
            'templates' => $templates,
            'groups' => $branch ? Group::status('current')->orderBy('name')->get(['id', 'name']) : collect(),
            'canManage' => $user->can('sms.manage') && $branch,
            'canSend' => $user->can('sms.send') && $branch,
        ]);
    }

    public function settings(Request $request): RedirectResponse
    {
        $this->authorize('sms.manage');

        $data = $request->validate([
            'sms_enabled' => ['nullable', 'boolean'],
            'sms_auto_debt' => ['nullable', 'boolean'],
            'sms_auto_absent' => ['nullable', 'boolean'],
            'templates' => ['required', 'array'],
            'templates.*.body' => ['required', 'string', 'max:500'],
            'templates.*.enabled' => ['nullable', 'boolean'],
        ], [], ['templates.*.body' => 'Shablon matni']);

        Branch::findOrFail(BranchContext::id())->update([
            'sms_enabled' => $request->boolean('sms_enabled'),
            'sms_auto_debt' => $request->boolean('sms_auto_debt'),
            'sms_auto_absent' => $request->boolean('sms_auto_absent'),
        ]);

        foreach (array_keys(SmsTemplates::all()) as $key) {
            if (isset($data['templates'][$key])) {
                SmsTemplate::updateOrCreate(
                    ['branch_id' => BranchContext::id(), 'key' => $key],
                    ['body' => $data['templates'][$key]['body'], 'is_enabled' => (bool) ($data['templates'][$key]['enabled'] ?? false)],
                );
            }
        }

        return back()->with('success', 'SMS sozlamalari saqlandi.');
    }

    /** v8 B9: ommaviy yuborishdan oldin ko'rish (qabul qiluvchilar soni, namuna matn, taxminiy segment) va tasdiqqa o'tkazish. */
    public function bulk(Request $request, SmsService $sms, ConfirmationService $confirmations): RedirectResponse
    {
        $this->authorize('sms.send');

        $data = $request->validate([
            'audience' => ['required', Rule::in(['group', 'debtors', 'all'])],
            'group_id' => ['required_if:audience,group', 'nullable', BranchContext::exists('groups')],
            'message' => ['required', 'string', 'max:500'],
        ], [], ['audience' => 'Kimga', 'group_id' => 'Guruh', 'message' => 'Xabar matni']);

        $branch = Branch::findOrFail(BranchContext::id());

        if (! $branch->sms_enabled) {
            throw ValidationException::withMessages(['message' => "Bu filialda SMS yoqilmagan. Avval SMS sozlamalarida yoqing."]);
        }

        $groupId = $data['group_id'] ?? null;
        $preview = $sms->previewBulk($branch, $data['audience'], $groupId, $data['message']);

        if ($preview['count'] === 0) {
            throw ValidationException::withMessages(['message' => "Tanlangan toifada telefon raqami to'g'ri bo'lgan o'quvchi topilmadi."]);
        }

        $audienceLabel = match ($data['audience']) {
            'debtors' => "Qarzdor o'quvchilar",
            'group' => 'Guruh: '.(Group::find($groupId)->name ?? '—'),
            default => "Barcha faol o'quvchilar",
        };

        $token = $confirmations->stash(
            'sms.bulk',
            ['audience' => $data['audience'], 'group_id' => $groupId, 'message' => $data['message']],
            'Ommaviy SMS yuborish',
            [
                ['Kimga', $audienceLabel],
                ['Qabul qiluvchilar soni', "{$preview['count']} ta"],
                ['Namuna matn (birinchi qabul qiluvchi bo\'yicha)', $preview['sample']],
                ['SMS segment (taxminiy)', "{$preview['segments']} segment/xabar × {$preview['count']} = ".($preview['segments'] * $preview['count'])." ta"],
            ],
            route('sms.index'),
        );

        return redirect()->route('confirm.show', $token);
    }
}
