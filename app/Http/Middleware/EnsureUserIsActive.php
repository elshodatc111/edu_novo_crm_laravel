<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloklangan foydalanuvchi, yopilgan filial xodimi va (veb uchun) o'quvchilarni chiqarib yuboradi.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $message = null;

        if (! $user->isActive()) {
            $message = 'Akkauntingiz bloklangan. Administrator bilan bog\'laning.';
        } elseif (! $user->isSuperAdmin() && (! $user->branch || ! $user->branch->isActive())) {
            $message = 'Sizning filialingiz yopilgan yoki biriktirilmagan.';
        } elseif (! $request->expectsJson() && ! $request->is('api/*') && ! $user->role->canUsePanel()) {
            $message = "O'quvchilar faqat mobil ilovadan foydalanadi.";
        }

        if ($message === null) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            $user->currentAccessToken()?->delete();

            return response()->json(['success' => false, 'message' => $message], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['login' => $message]);
    }
}
