<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Rules\UzPhone;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * v13: bir nechta sAdmin - qo'shish, bloklash/faollashtirish va olib tashlash. Faqat sAdmin (route `role:sadmin`).
 * sAdmin filialga bog'liq emas, shuning uchun bu yerda User'ni filial konteksti bilan emas, rol bo'yicha olamiz.
 * Himoya: o'zini bloklab/o'chirib bo'lmaydi, oxirgi faol sAdmin'ni olib tashlab bo'lmaydi.
 */
class SuperAdminController extends Controller
{
    public function index()
    {
        return view('superadmins.index', [
            'admins' => User::where('role', Role::SAdmin)->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('superadmins.form');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z0-9._@+-]+$/', Rule::unique('users', 'username')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', new UzPhone],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['name' => 'F.I.O', 'username' => 'Login', 'email' => 'Email', 'phone' => 'Telefon', 'password' => 'Parol']);

        $phone = Format::canonicalPhone($data['phone']) ?: $data['phone'];
        if (User::where('role', Role::SAdmin)->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'Bu telefon raqami bilan sAdmin allaqachon mavjud.']);
        }

        $admin = new User(['name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'] ?? null, 'phone' => $phone, 'password' => $data['password']]);
        $admin->role = Role::SAdmin;
        $admin->branch_id = null;
        $admin->status = UserStatus::Active;
        $admin->save();

        AuditLog::record('superadmin.created', $admin, "Yangi sAdmin qo'shildi: {$admin->name}");

        return redirect()->route('superadmins.index')->with('success', "{$admin->name} sAdmin sifatida qo'shildi.");
    }

    /** Bloklash yoki qayta faollashtirish. */
    public function toggle(Request $request, int $id): RedirectResponse
    {
        $admin = $this->find($id);
        if ($admin->is($request->user())) {
            return back()->with('error', "O'zingizni bloklab bo'lmaydi.");
        }

        if ($admin->isActive()) {
            if ($this->isLastActive($admin)) {
                return back()->with('error', "Oxirgi faol sAdmin'ni bloklab bo'lmaydi.");
            }
            $admin->status = UserStatus::Blocked;
            $admin->save();
            $admin->tokens()->delete();
            AuditLog::record('superadmin.blocked', $admin, "sAdmin bloklandi: {$admin->name}");
            $msg = "{$admin->name} bloklandi.";
        } else {
            $admin->status = UserStatus::Active;
            $admin->save();
            AuditLog::record('superadmin.unblocked', $admin, "sAdmin faollashtirildi: {$admin->name}");
            $msg = "{$admin->name} qayta faollashtirildi.";
        }

        return redirect()->route('superadmins.index')->with('success', $msg);
    }

    /**
     * sAdmin'ni olib tashlash: tarix (jurnal, to'lovlar) saqlanishi uchun yozuv o'chirilmaydi - bloklanadi va
     * tizimga kira olmaydigan holatga keltiriladi (parol almashtiriladi, tokenlar o'chadi), ro'yxatdan yashiriladi.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $admin = $this->find($id);
        if ($admin->is($request->user())) {
            return back()->with('error', "O'zingizni olib tashlab bo'lmaydi.");
        }
        if ($this->isLastActive($admin)) {
            return back()->with('error', "Oxirgi faol sAdmin'ni olib tashlab bo'lmaydi.");
        }

        $admin->status = UserStatus::Blocked;
        $admin->archived_at = now();
        $admin->password = bin2hex(random_bytes(16));
        $admin->save();
        $admin->tokens()->delete();

        AuditLog::record('superadmin.removed', $admin, "sAdmin olib tashlandi: {$admin->name}");

        return redirect()->route('superadmins.index')->with('success', "{$admin->name} olib tashlandi.");
    }

    private function find(int $id): User
    {
        return User::where('role', Role::SAdmin)->whereNull('archived_at')->findOrFail($id);
    }

    /** Oxirgi faol sAdmin bloklanmasligi/olib tashlanmasligi kerak (tizimga hech kim kira olmay qoladi). */
    private function isLastActive(User $admin): bool
    {
        return $admin->isActive() && User::where('role', Role::SAdmin)->whereNull('archived_at')
            ->where('status', UserStatus::Active)->where('id', '!=', $admin->id)->doesntExist();
    }
}
