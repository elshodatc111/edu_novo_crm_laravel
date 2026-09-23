<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StudentNote;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** v8 B8: xodimlar o'rtasidagi ichki eslatmalar (`students.notes` ruxsatini ishga tushiradi). */
class StudentNoteController extends Controller
{
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
}
