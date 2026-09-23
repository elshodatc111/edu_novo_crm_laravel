<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    /** Davomad jadvali. O'quvchi faqat o'z davomadini ko'radi. */
    public function index(Request $request, int $group): JsonResponse
    {
        $user = $request->user();

        if ($user->role === Role::Student) {
            $group = Group::whereIn('id', $user->memberships()->select('group_id'))->findOrFail($group);
            $matrix = $this->attendance->matrix($group);
            $matrix['rows'] = array_values(array_filter($matrix['rows'], fn ($r) => $r['student']->id === $user->id));
        } else {
            $group = Group::findOrFail($group);
            $this->authorize('view', $group);
            $matrix = $this->attendance->matrix($group);
        }

        return response()->json(['success' => true, 'data' => [
            'days' => $matrix['days'],
            'students' => array_map(fn ($r) => [
                'id' => $r['student']->id,
                'name' => $r['student']->name,
                'active' => $r['active'],
                'present' => $r['present'],
                'total' => $r['total'],
                'rate' => $r['rate'],
                'cells' => (object) $r['cells'],
            ], $matrix['rows']),
        ]]);
    }

    /** Bugungi davomadni saqlash yoki tahrirlash. */
    public function store(Request $request, int $group): JsonResponse
    {
        $group = Group::findOrFail($group);
        $this->authorize('takeAttendance', $group);

        $data = $request->validate([
            'present' => ['nullable', 'array'],
            'present.*' => ['integer'],
        ], [], ['present' => 'Kelganlar']);

        $this->attendance->takeToday($group, $data['present'] ?? [], $request->user());

        return response()->json(['success' => true, 'message' => 'Davomad saqlandi.']);
    }
}
