<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * v8 A5: barcha javoblarga asosiy xavfsizlik sarlavhalarini qo'shadi
 * (MIME-sniffing, clickjacking, ortiqcha referrer oqishi, kerak bo'lmagan brauzer ruxsatlari).
 * Diqqat: "Varonka" bo'limidagi ochiq ariza shakli (apply.*) boshqa saytlarga <iframe> orqali
 * joylashtiriladi - shu sahifalarda X-Frame-Options qo'yilmaydi, aks holda embed ishlamay qoladi.
 * HSTS faqat so'rov haqiqatan https bo'lganda qo'shiladi (http'da hali ishlamayotgan saytni
 * "faqat https" deb belgilab qo'ymaslik uchun) - trustProxies tufayli Nginx orqasida ham to'g'ri aniqlanadi.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (! $request->routeIs('apply.*')) {
            $response->headers->set('X-Frame-Options', 'DENY');
        }

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000; includeSubDomains');
        }

        return $response;
    }
}
