<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Holiday;
use App\Services\HolidayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index()
    {
        $this->authorize('settings.branch');

        $holidays = Holiday::whereDate('date', '>=', today()->subDays(30))->orderBy('date')->paginate(40);

        return view('holidays.index', compact('holidays'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('settings.branch');

        $data = $request->validate([
            'date' => ['required', 'date'],
            'comment' => ['nullable', 'string', 'max:255'],
        ], [], ['date' => 'Sana', 'comment' => 'Izoh']);

        if (Holiday::whereDate('date', $data['date'])->exists()) {
            return back()->withErrors(['date' => 'Bu sana allaqachon dam olish kuni sifatida kiritilgan.'])->withInput();
        }

        $holiday = Holiday::create($data);
        AuditLog::record('holiday.created', $holiday, "Dam olish kuni qo'shildi: {$holiday->date->format('d.m.Y')}");

        return back()->with('success', "Dam olish kuni qo'shildi. Yangi guruhlar shu kunni o'tkazib yuboradi.");
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $this->authorize('settings.branch');

        $holiday->delete();

        return back()->with('success', "Dam olish kuni o'chirildi.");
    }

    public function generate(HolidayService $service): RedirectResponse
    {
        $this->authorize('settings.branch');

        $added = $service->generateYear();

        return back()->with('success', "{$added} ta dam olish kuni qo'shildi (yakshanba va rasmiy bayramlar, 1 yil).");
    }
}
