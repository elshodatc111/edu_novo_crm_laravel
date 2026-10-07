<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StudentNote;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\SafeInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * v8 B8: xodimlar o'rtasidagi ichki eslatmalar (`students.notes` ruxsatini ishga tushiradi).
 * v13: yuqori paneldagi qo'ng'iroqcha (feed) va eslatmani faolsizlantirish / qayta faollashtirish.
 */
class StudentNoteController extends Controller
{
    public const FEED_LIMIT = 40;

    public function store(Request $request, User $student): RedirectResponse
    {
        $this->authorize('students.notes');

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [], ['body' => 'Eslatma matni']);

        StudentNote::create([
            'branch_id' => $student->branch_id,
            'student_id' => $student->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        AuditLog::record('student.note_added', $student, "Ichki eslatma qo'shildi: {$student->name}");

        return back()->with('success', "Eslatma qo'shildi.");
    }

    public function destroy(User $student, StudentNote $note): RedirectResponse
    {
        $this->authorize('students.notes');

        abort_unless($note->student_id === $student->id, 404);

        $note->delete();

        AuditLog::record('student.note_deleted', $student, "Ichki eslatma o'chirildi: {$student->name}");

        return back()->with('success', "Eslatma o'chirildi.");
    }

    /**
     * Qo'ng'iroqcha uchun ma'lumot: faol eslatmalar soni va ro'yxat (faol yoki yopilgan).
     * Filial doirasi `BelongsToBranch` orqali avtomatik: xodim - o'z filiali, sAdmin - tanlangan filial
     * (tanlanmagan bo'lsa barcha filiallar, har bir eslatmada filial nomi ko'rsatiladi).
     */
    public function feed(Request $request): JsonResponse
    {
        $this->authorize('students.notes');

        $closed = SafeInput::string($request->input('status')) === 'closed';

        $notes = StudentNote::query()
            ->with(['student:id,name', 'user:id,name', 'closer:id,name', 'branch:id,name'])
            ->when($closed, fn ($q) => $q->closed()->orderByDesc('closed_at'), fn ($q) => $q->active()->orderByDesc('id'))
            ->limit(self::FEED_LIMIT)
            ->get();

        return response()->json([
            'count' => StudentNote::active()->count(),
            'closed' => $closed,
            'show_branch' => BranchContext::id() === null,
            'items' => $notes->map(fn (StudentNote $n) => [
                'id' => $n->id,
                'body' => $n->body,
                'student' => $n->student?->name,
                'student_url' => route('students.show', $n->student_id),
                'author' => $n->user?->name,
                'branch' => $n->branch?->name,
                'created_at' => $n->created_at?->format('d.m.Y H:i'),
                'closed_at' => $n->closed_at?->format('d.m.Y H:i'),
                'closed_by' => $n->closer?->name,
            ])->values(),
        ]);
    }

    public function close(Request $request, StudentNote $note)
    {
        return $this->toggle($request, $note, true);
    }

    public function reopen(Request $request, StudentNote $note)
    {
        return $this->toggle($request, $note, false);
    }

    private function toggle(Request $request, StudentNote $note, bool $close): JsonResponse|RedirectResponse
    {
        $this->authorize('students.notes');

        $note->forceFill([
            'closed_at' => $close ? now() : null,
            'closed_by' => $close ? $request->user()->id : null,
        ])->save();

        $student = User::find($note->student_id);
        AuditLog::record($close ? 'student.note_closed' : 'student.note_reopened', $student, ($close ? 'Eslatma faolsizlantirildi' : 'Eslatma qayta faollashtirildi').": {$student?->name}");

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'count' => StudentNote::active()->count()]);
        }

        return back()->with('success', $close ? 'Eslatma faolsizlantirildi.' : 'Eslatma qayta faollashtirildi.');
    }
}
