<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Group;
use App\Models\SmsMessage;
use App\Services\SmsService;
use App\Support\BranchContext;
use App\Support\Format;
use App\Support\SafeInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * v11: mobil ilovada SMS - tarix (ko'rish) va ommaviy yuborishni OLDINDAN KO'RISH.
 * Haqiqiy ommaviy yuborish ataylab mobilga chiqarilmagan - veb'da ikki bosqichli
 * tasdiqlash (segment/narx/qabul qiluvchilar sonini ko'rib chiqib tasdiqlash) bilan
 * himoyalangan, mobil ilova buni chetlab o'tmasligi kerak.
 */
class SmsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['sms.view', 'sms.send', 'sms.manage']), 403);

        $messages = SmsMessage::with(['recipient:id,name', 'creator:id,name'])
            ->when(SafeInput::string($request->input('status')), fn ($q, $status) => $q->where('status', $status))
            ->when(SafeInput::string($request->input('q')), fn ($q, $term) => $q->where('phone', 'like', '%'.Format::digits($term).'%'))
            ->orderByDesc('id')->paginate(min(50, max(5, $request->integer('per_page', 20))));

        return response()->json(['success' => true, 'data' => $messages->getCollection()->map(fn ($m) => [
            'id' => $m->id, 'phone' => $m->phone, 'message' => $m->message, 'status' => $m->status,
            'recipient' => $m->recipient?->name, 'created_at' => $m->created_at->toIso8601String(),
        ]), 'meta' => ['page' => $messages->currentPage(), 'last_page' => $messages->lastPage(), 'total' => $messages->total()]]);
    }

    /** Ommaviy yuborishdan OLDIN ko'rish (haqiqiy yuborish veb orqali, tasdiqlashdan keyin). */
    public function bulkPreview(Request $request, SmsService $sms): JsonResponse
    {
        abort_unless($request->user()->can('sms.send'), 403);

        $data = $request->validate([
            'audience' => ['required', Rule::in(['group', 'debtors', 'all'])],
            'group_id' => ['required_if:audience,group', 'nullable', BranchContext::exists('groups')],
            'message' => ['required', 'string', 'max:500'],
        ], [], ['audience' => 'Kimga', 'group_id' => 'Guruh', 'message' => 'Xabar matni']);

        $branch = Branch::findOrFail(BranchContext::id());

        if (! $branch->sms_enabled) {
            throw ValidationException::withMessages(['message' => "Bu filialda SMS yoqilmagan."]);
        }

        $preview = $sms->previewBulk($branch, $data['audience'], $data['group_id'] ?? null, $data['message']);

        return response()->json(['success' => true, 'data' => $preview, 'message' => "Haqiqiy yuborish uchun veb-panelga o'ting (tasdiqlash talab qilinadi)."]);
    }
}
