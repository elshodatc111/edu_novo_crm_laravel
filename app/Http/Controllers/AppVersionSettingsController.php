<?php

namespace App\Http\Controllers;

use App\Models\AppVersion;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** v12: sAdmin mobil ilovaning eng kam (majburiy yangilash) va so'nggi versiyasini sozlaydi. */
class AppVersionSettingsController extends Controller
{
    private const PLATFORMS = ['android', 'ios'];

    public function edit()
    {
        $rows = AppVersion::whereIn('platform', self::PLATFORMS)->get()->keyBy('platform');

        return view('app-version.edit', ['rows' => $rows, 'platforms' => self::PLATFORMS]);
    }

    public function update(Request $request): RedirectResponse
    {
        $versionRule = ['required', 'string', 'max:20', 'regex:/^\d+(\.\d+){0,3}$/'];

        $data = $request->validate([
            'platform' => ['required', Rule::in(self::PLATFORMS)],
            'min_version' => $versionRule,
            'latest_version' => $versionRule,
            'update_url' => ['nullable', 'url', 'max:255'],
            'message' => ['nullable', 'string', 'max:255'],
        ], [], [
            'min_version' => 'Eng kam versiya', 'latest_version' => "So'nggi versiya",
            'update_url' => 'Yuklab olish havolasi', 'message' => 'Xabar matni',
        ]);

        if (version_compare($data['min_version'], $data['latest_version'], '>')) {
            return back()->withErrors(['min_version' => "Eng kam versiya so'nggi versiyadan katta bo'lishi mumkin emas."])->withInput();
        }

        $row = AppVersion::updateOrCreate(['platform' => $data['platform']], [
            'min_version' => $data['min_version'],
            'latest_version' => $data['latest_version'],
            'update_url' => $data['update_url'] ?? null,
            'message' => $data['message'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        AuditLog::record('app_version.updated', $row, "Mobil ilova versiyasi yangilandi ({$data['platform']}): min {$data['min_version']}, so'nggi {$data['latest_version']}");

        return back()->with('success', ucfirst($data['platform'])." uchun sozlamalar saqlandi.");
    }
}
