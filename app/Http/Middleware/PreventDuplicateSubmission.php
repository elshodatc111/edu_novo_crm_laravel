<?php

namespace App\Http\Middleware;

use App\Models\SubmissionToken;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bir xil so'rovni ikki marta (tez ketma-ket bosish, sekin internet, brauzerning
 * qayta yuborishi) yuborilishidan himoya qiladi.
 *
 * Web forma "_once" yashirin maydonini, API esa "Idempotency-Key" sarlavhasini yuborishi mumkin.
 * Token bo'lmasa - himoyasiz o'tkaziladi (eski formalar buzilib qolmasligi uchun); token bo'lsa,
 * "submission_tokens" jadvalidagi unique indeks orqali faqat BIRINCHI so'rov o'tadi.
 */
class PreventDuplicateSubmission
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->input('_once') ?: $request->header('Idempotency-Key');

        if (! $token || ! is_string($token) || strlen($token) > 64) {
            return $next($request);
        }

        try {
            SubmissionToken::create(['token' => $token, 'user_id' => $request->user()?->id]);
        } catch (UniqueConstraintViolationException) {
            $message = "Bu amal allaqachon bajarilgan yoki yuborilmoqda. Iltimos, sahifani yangilab qaytadan urinib ko'ring.";

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 409);
            }

            return back()->with('error', $message);
        }

        return $next($request);
    }
}
