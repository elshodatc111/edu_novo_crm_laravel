<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Services\AttendanceService;
use App\Support\SafeInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $groups = $this->visibleGroups($request)
            ->with(['course', 'teacher', 'room', 'lessonTime'])
            ->withCount(['activeMembers as students_count'])
            ->status(SafeInput::string($request->input('status'), 'current', 20))
            ->orderByDesc('starts_on')
            ->get();

        return response()->json(['success' => true, 'data' => GroupResource::collection($groups)]);
    }

    public function show(Request $request, int $group, AttendanceService $attendance): JsonResponse
    {
        $group = $this->visibleGroups($request)->with(['course', 'teacher', 'room', 'lessonTime'])->findOrFail($group);
        $user = $request->user();

        $data = [
            'group' => new GroupResource($group),
            'days' => $group->days()->pluck('date')->map->toDateString(),
            'lesson_today' => $attendance->isLessonDay($group, today()),
            'attendance_taken_today' => $attendance->isTaken($group, today()),
        ];

        if ($user->role !== Role::Student) {
            $data['students'] = $group->activeMembers()->with('student:id,name,phone')->get()
                ->map(fn ($m) => ['id' => $m->student_id, 'name' => $m->student->name, 'phone' => $m->student->phone]);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /** Foydalanuvchi roliga ko'ra ko'ra oladigan guruhlar. */
    private function visibleGroups(Request $request)
    {
        $user = $request->user();

        if ($user->role === Role::Student) {
            return Group::whereIn('id', $user->memberships()->where('is_active', true)->select('group_id'));
        }

        if ($user->role === Role::Teacher) {
            return Group::where('teacher_id', $user->id);
        }

        abort_unless($user->hasPermission('groups.view'), 403);

        return Group::query();
    }
}
