<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Veb va mobil API uchun umumiy kirish qoidalari. */
class AuthService
{
    private const MAX_ATTEMPTS = 5;

    /** v12: bitta kod uchun ruxsat etilgan noto'g'ri urinishlar soni. */
    private const MAX_CODE_ATTEMPTS = 5;

    public function __construct(private SmsService $sms) {}

    /**
     * Login (username yoki email) va parol bo'yicha foydalanuvchini tekshiradi.
     *
     * @throws ValidationException
     */
    public function authenticate(string $login, string $password, Request $request): User
    {
        $key = Str::lower($login).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'login' => "Juda ko'p urinish. {$seconds} soniyadan keyin qayta urinib ko'ring.",
            ]);
        }

        $user = User::with('branch')
            ->where('username', $login)
            ->orWhere('email', Str::lower($login))
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['login' => "Login yoki parol noto'g'ri."]);
        }

        RateLimiter::clear($key);

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['login' => "Akkauntingiz bloklangan. Administrator bilan bog'laning."]);
        }

        if (! $user->isSuperAdmin() && (! $user->branch || ! $user->branch->isActive())) {
            throw ValidationException::withMessages(['login' => 'Sizning filialingiz yopilgan yoki biriktirilmagan.']);
        }

        return $user;
    }

    /**
     * v12: "Parolni unutdingizmi" - login (username/email) bo'yicha hisobni topib, uning
     * ro'yxatdan o'tgan telefon raqamiga SMS orqali 6 xonali tasdiqlash kodi yuboradi.
     *
     * Xavfsizlik: hisob mavjud-yo'qligini oshkor qilmaslik uchun bu metod hech qachon
     * xato/istisno tashlamaydi va chaqiruvchi (controller) har doim bir xil umumiy javob
     * qaytaradi - kod faqat shartlar bajarilganda (hisob faol, filialga biriktirilgan,
     * telefon bor) haqiqatda yuboriladi. sAdmin (filialsiz) uchun bu yo'l ishlamaydi - u
     * boshqa sAdmin yoki server administratori orqali tiklanadi (API_DOC.md'da yozilgan).
     */
    public function requestPasswordReset(string $login): void
    {
        $user = $this->findByLogin($login);

        if (! $user || ! $user->isActive() || $user->isSuperAdmin() || ! $user->branch_id || blank($user->phone)) {
            return;
        }

        if (! $user->branch || ! $user->branch->isActive()) {
            return;
        }

        $code = (string) random_int(100000, 999999);

        // Eski, hali ishlatilmagan kodlar bekor qilinadi - bir vaqtda faqat oxirgi kod amal qiladi.
        PasswordResetCode::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);

        PasswordResetCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        $text = "Hurmatli {$user->name}, tizimga kirish parolingizni tiklash kodi: {$code}. Kodni hech kimga aytmang, 10 daqiqa amal qiladi.";
        $masked = "Hurmatli {$user->name}, tizimga kirish parolingizni tiklash kodi: ••••••. Kodni hech kimga aytmang, 10 daqiqa amal qiladi.";

        // v8 A5'dagi kabi: SMS tarixida haqiqiy kod saqlanmaydi, faqat yuborish uchun $secret sifatida beriladi.
        $this->sms->queue($user->branch, $user->phone, $masked, 'password_reset_otp', $user, null, $text);
    }

    /**
     * v12: kod to'g'ri va muddati o'tmagan bo'lsa, parolni yangilaydi va foydalanuvchining
     * BARCHA mobil seanslarini (tokenlarini) tugatadi (hisobni tiklash - eski qurilmalar
     * ishonchsiz deb hisoblanadi).
     *
     * @throws ValidationException
     */
    public function resetPasswordWithCode(string $login, string $code, string $newPassword): User
    {
        $fail = fn () => throw ValidationException::withMessages(['code' => "Kod noto'g'ri yoki muddati o'tgan."]);

        $user = $this->findByLogin($login);

        if (! $user) {
            $fail();
        }

        $reset = PasswordResetCode::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $reset || $reset->attempts >= self::MAX_CODE_ATTEMPTS) {
            $fail();
        }

        if (! Hash::check($code, $reset->code_hash)) {
            $reset->increment('attempts');
            $fail();
        }

        $reset->update(['used_at' => now()]);

        $user->update(['password' => $newPassword]);
        $user->tokens()->delete();

        AuditLog::record('auth.password_reset_otp', $user, "Parol SMS kod orqali tiklandi (mobil ilova): {$user->name}");

        return $user;
    }

    private function findByLogin(string $login): ?User
    {
        return User::where('username', $login)->orWhere('email', Str::lower($login))->first();
    }
}
