<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Notification;
use App\Services\NotificationService;
use App\Support\NotificationLinkTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * v11: sAdmin uchun mobil ilova foydalanuvchilariga bildirishnoma (push + ilova ichi) yuborish.
 * Faqat sAdmin (route: role:sadmin) - boshqa hech kim boshqa foydalanuvchilarga ommaviy xabar yubora olmaydi.
 */
class NotificationController extends Controller
{
    public function index()
    {
        return view('notifications.index', [
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'linkTypes' => NotificationLinkTypes::options(),
            'history' => Notification::with('branch:id,name')->withCount(['recipients as read_count' => fn ($q) => $q->whereNotNull('read_at')])
                ->orderByDesc('id')->paginate(20),
        ]);
    }

    public function store(Request $request, NotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:1000'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            // v12: ilova ichida bosilganda qayerga o'tishi ("deep link") - to'liq kontrakt API_DOC.md'da
            'link_type' => ['nullable', Rule::in(NotificationLinkTypes::keys())],
            'link_id' => ['nullable', 'integer', 'min:1', Rule::requiredIf(in_array($request->input('link_type'), NotificationLinkTypes::typesRequiringId(), true))],
        ], [], ['title' => 'Sarlavha', 'body' => 'Matn', 'branch_id' => 'Filial', 'link_type' => "O'tish turi", 'link_id' => 'ID']);

        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;

        $linkType = $data['link_type'] ?? NotificationLinkTypes::NONE;
        $payload = $linkType === NotificationLinkTypes::NONE ? [] : ['link_type' => $linkType, 'link_id' => (int) $data['link_id']];

        [$notification, $count] = $notifications->send($data['title'], $data['body'], $branchId, $request->user(), $payload);

        // v12: ommaviy bildirishnoma yuborish ham jurnalga yoziladi (kim, qachon, qaysi filialga/barchaga, nechta kishiga)
        AuditLog::record(
            'notification.sent',
            $notification,
            "Bildirishnoma yuborildi: \"{$data['title']}\" - ".($branchId ? "1 ta filialga" : 'barcha filiallarga')." ({$count} ta foydalanuvchi)",
            branchId: $branchId,
        );

        return back()->with('success', "Bildirishnoma {$count} ta foydalanuvchiga yuborildi.");
    }
}
