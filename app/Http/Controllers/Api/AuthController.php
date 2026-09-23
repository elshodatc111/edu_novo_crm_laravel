<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\DeviceToken;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(LoginRequest $request, AuthService $auth): JsonResponse
    {
        $user = $auth->authenticate($request->input('login'), $request->input('password'), $request);

        $device = $request->input('device_name', 'mobile');

        // Bir xil qurilma nomi bilan eski token almashtiriladi
        $user->tokens()->where('name', $device)->delete();
        $token = $user->createToken($device)->plainTextToken;

        $user->forceFill(['last_login_at' => now()])->save();
        AuditLog::record('auth.login_api', $user, "Mobil ilovadan kirdi ({$device})");

        return response()->json([
            'success' => true,
            'message' => 'Tizimga muvaffaqiyatli kirdingiz.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user->load('branch')),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($request->user()->load('branch')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $device = $user->currentAccessToken()?->name;

        // v11: shu qurilmaga endi push kelmasin
        DeviceToken::where('user_id', $user->id)->when($device, fn ($q) => $q->where('device_name', $device))->delete();

        $user->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Tizimdan chiqdingiz.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ], [], ['current_password' => 'Joriy parol', 'password' => 'Yangi parol']);

        $user = $request->user();
        $user->update(['password' => $data['password']]);
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['success' => true, 'message' => 'Parol yangilandi.']);
    }

    /**
     * v12: "Parolni unutdingizmi" - 1-qadam. Login (username/email) bo'yicha ro'yxatdan
     * o'tgan telefon raqamiga SMS kod yuboriladi. Hisob mavjud yoki yo'qligini oshkor
     * qilmaslik uchun natija har doim bir xil umumiy xabar bo'ladi.
     */
    public function forgotPassword(Request $request, AuthService $auth): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
        ], [], ['login' => 'Login']);

        $auth->requestPasswordReset($data['login']);

        return response()->json([
            'success' => true,
            'message' => "Agar bunday hisob mavjud bo'lsa, telefon raqamiga tasdiqlash kodi yuborildi.",
        ]);
    }

    /** v12: "Parolni unutdingizmi" - 2-qadam. SMS kod + yangi parol. */
    public function resetPassword(Request $request, AuthService $auth): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['login' => 'Login', 'code' => 'Tasdiqlash kodi', 'password' => 'Yangi parol']);

        $auth->resetPasswordWithCode($data['login'], $data['code'], $data['password']);

        return response()->json([
            'success' => true,
            'message' => 'Parolingiz muvaffaqiyatli tiklandi. Endi yangi parol bilan tizimga kiring.',
        ]);
    }
}
