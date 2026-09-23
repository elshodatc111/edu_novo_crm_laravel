<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Rules\UniquePhonePerRole;
use App\Rules\UzPhone;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()->load('branch')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => [$user->isSuperAdmin() ? 'nullable' : 'required', 'string', new UzPhone, new UniquePhonePerRole($user->branch_id, $user->role, $user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], [], ['name' => 'F.I.O', 'phone' => 'Telefon', 'email' => 'Email']);

        $user->update($data);

        return back()->with('success', "Ma'lumotlaringiz saqlandi.");
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ], [], ['current_password' => 'Joriy parol', 'password' => 'Yangi parol']);

        $user = $request->user();
        $user->update(['password' => $data['password']]);

        // v8 A5: parol o'zgarganda eski kirishlar bekor qilinadi (mobil token bo'lsa) va joriy
        // sessiya identifikatori yangilanadi (o'zi hozir chiqarilmaydi, faqat xavfsizlik uchun yangilanadi).
        $user->tokens()->delete();
        $request->session()->regenerate();

        AuditLog::record('auth.password_changed', $user, "Parolni o'zgartirdi");

        return back()->with('success', 'Parol yangilandi.');
    }
}
