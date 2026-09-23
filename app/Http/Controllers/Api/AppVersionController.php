<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * v12: mobil ilova ochilganda (login talab qilmasdan) darhol so'raladi - eski, endi
 * qo'llab-quvvatlanmaydigan versiyalarni serverni qayta joylashtirmasdan MAJBURIY
 * yangilashga undash uchun (`min_version`dan past bo'lsa `force_update = true`).
 */
class AppVersionController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', 'string', 'in:android,ios'],
            'version' => ['required', 'string', 'max:20', 'regex:/^\d+(\.\d+){0,3}$/'],
        ], [], ['platform' => 'Platforma', 'version' => 'Ilova versiyasi']);

        $row = AppVersion::where('platform', $data['platform'])->first();

        // Qator hech qachon yo'q bo'lmasligi kerak (migratsiya standart qiymat bilan yaratadi),
        // lekin bo'lmasa ham ilova ishlashda davom etadi - hech narsani majburlamaymiz.
        if (! $row) {
            return response()->json(['success' => true, 'data' => [
                'force_update' => false, 'update_available' => false,
                'min_version' => $data['version'], 'latest_version' => $data['version'],
                'message' => null, 'update_url' => null,
            ]]);
        }

        $current = $data['version'];

        return response()->json(['success' => true, 'data' => [
            'force_update' => version_compare($current, $row->min_version, '<'),
            'update_available' => version_compare($current, $row->latest_version, '<'),
            'min_version' => $row->min_version,
            'latest_version' => $row->latest_version,
            'message' => $row->message,
            'update_url' => $row->update_url,
        ]]);
    }
}
