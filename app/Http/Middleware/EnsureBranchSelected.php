<?php

namespace App\Http\Middleware;

use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * sAdmin "Barcha filiallar" rejimida bo'lsa, yozuv yaratadigan bo'limlarga kirishdan oldin
 * filial tanlashni so'raydi. Veb'da sessiya orqali tanlangan filial tekshiriladi; mobil
 * API'da (v11) sAdmin `X-Branch-Id` sarlavhasi orqali filialni ko'rsatishi kerak - bu
 * middleware ikkalasini ham `BranchContext::id()` orqali bir xil tekshiradi.
 */
class EnsureBranchSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        if (BranchContext::id() === null) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Bu amal uchun filialni ko'rsating (X-Branch-Id sarlavhasi).",
                ], 422);
            }

            return redirect()->route('dashboard')->with('error', "Bu bo'lim bilan ishlash uchun yuqoridan filialni tanlang.");
        }

        return $next($request);
    }
}
