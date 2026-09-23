<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DeviceToken;
use App\Models\Group;
use App\Models\User;
use App\Rules\UniquePhonePerRole;
use App\Rules\UzPhone;
use App\Services\AttendanceService;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MeController extends Controller
{
    /** O'z ma'lumotini ko'rish (barcha rollar). */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load('branch:id,name');

        return response()->json(['success' => true, 'data' => $this->profileRow($user)]);
    }

    /**
     * v12: profilni tahrirlash - faqat telefon va rasm (ism/login/email xodim boshqaruvi
     * orqali o'zgaradi, o'z-o'zini emas). Rasm yuborilsa eskisi almashtiriladi (fayl o'chiriladi);
     * `remove_photo=1` yuborilsa, rasm o'chiriladi.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'phone' => ['sometimes', 'required', 'string', new UzPhone, new UniquePhonePerRole($user->branch_id, $user->role, $user->id)],
            'photo' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_photo' => ['sometimes', 'boolean'],
        ], [], ['phone' => 'Telefon', 'photo' => 'Profil rasmi']);

        $update = [];

        if (array_key_exists('phone', $data)) {
            $update['phone'] = Format::canonicalPhone($data['phone']);
        }

        if ($request->hasFile('photo')) {
            if ($user->photo_path) {
                Storage::disk('public')->delete($user->photo_path);
            }
            $update['photo_path'] = $request->file('photo')->store("profile-photos/{$user->id}", 'public');
        } elseif ($request->boolean('remove_photo') && $user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
            $update['photo_path'] = null;
        }

        if ($update) {
            $user->update($update);
            AuditLog::record('profile.updated', $user, "O'z profilini yangiladi (mobil ilova)");
        }

        return response()->json(['success' => true, 'message' => 'Profil yangilandi.', 'data' => $this->profileRow($user->fresh('branch'))]);
    }

    private function profileRow(User $user): array
    {
        return [
            'id' => $user->id, 'name' => $user->name, 'role' => $user->role->value, 'role_label' => $user->role->label(),
            'phone' => $user->phone, 'phone2' => $user->phone2, 'photo_url' => $user->photoUrl(), 'branch' => $user->branch?->name,
        ];
    }

    /**
     * v11: mobil qurilma FCM tokenini ro'yxatdan o'tkazish/yangilash. `device_name` login paytida
     * berilgan bilan bir xil bo'lishi kerak (bitta qurilma = bitta yozuv) - shu bilan foydalanuvchi
     * chiqib-kirsa ham eski token qatori yangilanadi, dublikat qolmaydi.
     */
    public function storeDeviceToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [], ['token' => 'Qurilma tokeni', 'platform' => 'Platforma', 'device_name' => 'Qurilma nomi']);

        $device = $data['device_name'] ?? $request->user()->currentAccessToken()?->name ?? 'mobile';

        DeviceToken::updateOrCreate(
            ['user_id' => $request->user()->id, 'device_name' => $device],
            ['token' => $data['token'], 'platform' => $data['platform'] ?? 'android']
        );

        return response()->json(['success' => true, 'message' => 'Qurilma ro\'yxatdan o\'tdi.']);
    }

    /** Chiqishda (yoki foydalanuvchi so'rasa) shu qurilmaga push kelishini to'xtatish. */
    public function destroyDeviceToken(Request $request): JsonResponse
    {
        $device = $request->input('device_name') ?? $request->user()->currentAccessToken()?->name;
        DeviceToken::where('user_id', $request->user()->id)->when($device, fn ($q) => $q->where('device_name', $device))->delete();

        return response()->json(['success' => true, 'message' => "Qurilma o'chirildi."]);
    }

    /** v11: o'quvchi uchun "mening guruhlarim" - barchasi bitta ro'yxatda, holati bilan. */
    public function groups(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === Role::Student, 403);

        $groups = Group::whereIn('id', $user->memberships()->select('group_id'))
            ->with(['course:id,name', 'teacher:id,name'])->orderByDesc('starts_on')->get();

        return response()->json(['success' => true, 'data' => $groups->map(fn ($g) => [
            'id' => $g->id, 'name' => $g->name, 'course' => $g->course?->name, 'teacher' => $g->teacher?->name,
            'status' => $g->status, 'starts_on' => $g->starts_on?->toDateString(), 'ends_on' => $g->ends_on?->toDateString(),
            'is_active_member' => $user->memberships()->where('group_id', $g->id)->where('is_active', true)->exists(),
        ])]);
    }

    /** v11: o'quvchi uchun barcha guruhlar bo'yicha umumiy davomat foizi va so'nggi yozuvlar. */
    public function attendanceHistory(Request $request, AttendanceService $attendance): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === Role::Student, 403);

        $groups = Group::whereIn('id', $user->memberships()->select('group_id'))->get();

        // v12: har bir guruh uchun BUTUN guruh matritsasini qurish o'rniga (barcha o'quvchilar,
        // barcha kunlar - guruh kattalashgan sari sekinlashadi), faqat shu o'quvchining o'zi
        // uchun yengil agregat so'rov (AttendanceService::studentSummary).
        $summary = $groups->map(function ($group) use ($user, $attendance) {
            $row = $attendance->studentSummary($group, $user->id);

            return $row ? [
                'group_id' => $group->id, 'group' => $group->name,
                'present' => $row['present'], 'total' => $row['total'], 'rate' => $row['rate'],
            ] : null;
        })->filter()->values();

        return response()->json(['success' => true, 'data' => [
            'groups' => $summary,
            'overall_rate' => $summary->count() ? (int) round($summary->avg('rate')) : null,
        ]]);
    }

    /** O'quvchining balansi, qarzi va so'nggi 50 ta harakati. */
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === Role::Student, 403);

        $transactions = $user->balanceTransactions()->with('group:id,name')->latest('id')->limit(50)->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'type_label' => $t->typeLabel(),
                'amount' => $t->amount,
                'balance_after' => $t->balance_after,
                'group' => $t->group?->name,
                'note' => $t->note,
                'created_at' => $t->created_at->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => [
            'balance' => $user->balance,
            'debt' => max(0, -$user->balance),
            'transactions' => $transactions,
        ]]);
    }

    /** O'qituvchining hisoblangan ish haqi (guruhlar bo'yicha) va to'lovlar tarixi. */
    public function payroll(Request $request, \App\Services\PayrollService $payroll): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === Role::Teacher, 403);

        return response()->json(['success' => true, 'data' => [
            'groups' => array_map(fn ($a) => [
                'group_id' => $a['group']->id, 'group' => $a['group']->name, 'students' => $a['students'], 'bonus_students' => $a['bonus'],
                'lessons_held' => $a['held'], 'lessons_total' => $a['group']->lesson_count, 'accrued' => $a['accrued_by_attendance'], 'paid' => $a['paid'], 'remaining' => $a['remaining'],
            ], $payroll->teacherAccruals($user)),
            'payouts' => \App\Models\Payout::where('recipient_id', $user->id)->latest('id')->limit(50)->get()
                ->map(fn ($p) => ['id' => $p->id, 'amount' => $p->amount, 'method' => $p->method->label(), 'description' => $p->description, 'created_at' => $p->created_at->toIso8601String()]),
        ]]);
    }
}
