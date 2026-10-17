@extends('layouts.app')
@section('title', $person->name)

@section('content')
    <x-page-header :title="$person->name" :subtitle="$person->role->label().' · ish haqi'">
        <x-slot:actions><a href="{{ route('payroll.index', ['tab' => $isTeacher ? 'teachers' : 'staff']) }}" class="btn-secondary">Orqaga</a></x-slot:actions>
    </x-page-header>

    @if ($isTeacher)
        @php
            $fmU = fn ($n) => \App\Support\Format::money($n, false);
            $missingHire = collect($accruals)->contains(fn ($a) => $a['usluga']['state'] === \App\Services\UslugaService::STATE_NO_HIRE_DATE);
        @endphp
        <div class="mb-4 rounded-xl border border-ink-200 bg-white p-4 text-sm dark:border-ink-700 dark:bg-ink-900">
            <span class="text-ink-500">Ishga olingan sana:</span>
            <b>{{ $tenure['hired_at']?->format('d.m.Y') ?? 'kiritilmagan' }}</b>
            @if ($tenure['months'] !== null)
                · <span class="text-ink-500">staji:</span> <b>{{ intdiv($tenure['months'], 12) }} yil {{ $tenure['months'] % 12 }} oy</b>
                · <span class="text-ink-500">hozirgi usluga pog'onasi:</span> <b>{{ $tenure['tier_label'] }}</b>
            @endif
        </div>
        @if ($missingHire)
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">Bu o'qituvchining ishga olingan sanasi kiritilmagan, shuning uchun usluga hisoblanmayapti. Hodimni tahrirlash sahifasida «Ishga olingan sana»ni kiriting.</div>
        @endif
        <div class="card overflow-hidden">
            <h2 class="px-5 pt-5 text-lg font-semibold text-ink-900 dark:text-white sm:px-6">Guruhlar bo'yicha hisob</h2>
            <p class="px-5 text-sm text-ink-500 sm:px-6">Hisoblangan = o'quvchilar × stavka + bonusli o'quvchilar × bonus. Usluga (o'quvchi) = o'quvchilar × o'quvchi usluga; usluga (bonus) = bonusli o'quvchilar × bonus usluga. Jami = hisoblangan + usluga. «Davomad bo'yicha» — o'tkazilgan darslarga mutanosib (usluga bilan birga).</p>
            <div class="table-wrap mt-3">
                <table class="table">
                    <thead><tr><th>Guruh</th><th>O'quvchi</th><th>Bonusli</th><th>Darslar</th><th class="text-right">Hisoblangan</th><th class="text-right">Usluga (o'quvchi)</th><th class="text-right">Usluga (bonus)</th><th class="text-right">Jami</th><th class="text-right">Davomad bo'yicha</th><th class="text-right">To'langan</th><th class="text-right">Qoldiq</th></tr></thead>
                    <tbody>
                    @forelse ($accruals as $a)
                        @php $u = $a['usluga']; $ok = $u['state'] === \App\Services\UslugaService::STATE_OK; @endphp
                        <tr>
                            <td><a href="{{ route('groups.show', $a['group']) }}" class="font-medium hover:text-brand-600">{{ $a['group']->name }}</a><div class="text-xs text-ink-400">{{ $a['group']->status_label }}</div></td>
                            <td>{{ $a['students'] }}</td><td>{{ $a['bonus'] }}</td><td>{{ $a['held'] }} / {{ $a['group']->lesson_count }}</td>
                            <td class="text-right">{{ $fmU($a['accrued']) }}</td>
                            @if ($ok)
                                <td class="text-right whitespace-nowrap">{{ $a['students'] }} × {{ $fmU($u['student']) }} = <b>{{ $fmU($a['usluga_students']) }}</b><div class="text-xs text-ink-400">{{ $u['tier_label'] }}</div></td>
                                <td class="text-right whitespace-nowrap">{{ $a['bonus'] }} × {{ $fmU($u['bonus']) }} = <b>{{ $fmU($a['usluga_bonus']) }}</b></td>
                            @else
                                <td colspan="2" class="text-right text-xs text-ink-400">
                                    @if ($u['state'] === \App\Services\UslugaService::STATE_NO_HIRE_DATE)<span class="text-amber-600">Ishga olingan sana yo'q</span>
                                    @elseif ($u['state'] === \App\Services\UslugaService::STATE_NO_RATES)<span class="text-amber-600">Usluga summalari sozlanmagan</span>
                                    @else —@endif
                                </td>
                            @endif
                            <td class="text-right font-medium">{{ $fmU($a['accrued_total']) }}</td>
                            <td class="text-right">{{ $fmU($a['accrued_by_attendance']) }}</td>
                            <td class="text-right">{{ $fmU($a['paid']) }}</td>
                            <td class="text-right font-semibold {{ $a['remaining'] > 0 ? 'text-brand-600' : 'text-emerald-600' }}">{{ $fmU($a['remaining']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="py-8 text-center text-ink-500">So'nggi 31 kunda guruh yo'q.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($activity)
        @php $fm = fn ($n) => \App\Support\Format::money($n, false); @endphp
        <div class="card mb-6 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5 sm:px-6">
                <div>
                    <h2 class="text-lg font-semibold text-ink-900 dark:text-white">Faoliyat statistikasi</h2>
                    <p class="text-sm text-ink-500">Qabul qilgan to'lovlari (storno qilinganlarsiz), qo'shgan murojaatlari va o'quvchilari.</p>
                </div>
                <div class="flex gap-1 text-xs font-semibold">
                    <a href="{{ route('payroll.show', ['user' => $person, 'act' => 'day']) }}" class="rounded-lg px-3 py-1.5 {{ $act === 'day' ? 'bg-brand-600 text-white' : 'bg-ink-100 text-ink-600 dark:bg-ink-800 dark:text-ink-300' }}">Kunlik (30 kun)</a>
                    <a href="{{ route('payroll.show', ['user' => $person, 'act' => 'month']) }}" class="rounded-lg px-3 py-1.5 {{ $act === 'month' ? 'bg-brand-600 text-white' : 'bg-ink-100 text-ink-600 dark:bg-ink-800 dark:text-ink-300' }}">Oylik (12 oy)</a>
                </div>
            </div>
            <div class="mt-4 grid gap-3 px-5 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                <div class="rounded-xl bg-ink-50 p-3 dark:bg-ink-800/50"><div class="text-xs text-ink-500">Qabul qilgan summa</div><div class="text-lg font-bold">{{ $fm($activity['totals']['total']) }}</div></div>
                <div class="rounded-xl bg-ink-50 p-3 dark:bg-ink-800/50"><div class="text-xs text-ink-500">To'lovlar soni</div><div class="text-lg font-bold">{{ $activity['totals']['payments'] }}</div></div>
                <div class="rounded-xl bg-ink-50 p-3 dark:bg-ink-800/50"><div class="text-xs text-ink-500">Murojaatlar (qabul qilingan)</div><div class="text-lg font-bold">{{ $activity['totals']['leads'] }} ({{ $activity['totals']['converted'] }})</div></div>
                <div class="rounded-xl bg-ink-50 p-3 dark:bg-ink-800/50"><div class="text-xs text-ink-500">Yangi o'quvchilar</div><div class="text-lg font-bold">{{ $activity['totals']['students'] }}</div></div>
                <div class="rounded-xl bg-ink-50 p-3 dark:bg-ink-800/50 sm:col-span-2"><div class="text-xs text-ink-500">Ulardan to'lov qilganlar</div><div class="text-lg font-bold">{{ $activity['totals']['paid_students'] }} ta · {{ $fm($activity['totals']['paid_sum']) }}</div></div>
            </div>
            <div class="table-wrap mt-4 max-h-96 overflow-y-auto">
                <table class="table">
                    <thead><tr><th>{{ $act === 'month' ? 'Oy' : 'Kun' }}</th><th class="text-right">To'lovlar</th><th class="text-right">Naqt</th><th class="text-right">Plastik</th><th class="text-right">Jami</th><th class="text-right">Murojaat</th><th class="text-right">Qabul</th><th class="text-right">Izoh</th><th class="text-right">O'quvchi</th><th class="text-right">Shundan to'lagan</th><th class="text-right">To'lov summasi</th></tr></thead>
                    <tbody>
                    @foreach (array_reverse($activity['rows']) as $r)
                        <tr class="{{ $r['payments'] + $r['leads'] + $r['notes'] + $r['students'] === 0 ? 'text-ink-300' : '' }}">
                            <td class="whitespace-nowrap">{{ $r['label'] }}</td><td class="text-right">{{ $r['payments'] }}</td>
                            <td class="text-right">{{ $fm($r['cash']) }}</td><td class="text-right">{{ $fm($r['card']) }}</td><td class="text-right font-semibold">{{ $fm($r['total']) }}</td>
                            <td class="text-right">{{ $r['leads'] }}</td><td class="text-right">{{ $r['converted'] }}</td><td class="text-right">{{ $r['notes'] }}</td><td class="text-right">{{ $r['students'] }}</td>
                            <td class="text-right">{{ $r['paid_students'] }}</td><td class="text-right">{{ $fm($r['paid_sum']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        @if ($canPay)
            <form method="POST" action="{{ route('payroll.pay', $person) }}" class="card card-body h-fit space-y-4">
                @csrf
                <h2 class="text-base font-semibold text-ink-900 dark:text-white">Ish haqi to'lash</h2>
                <p class="text-sm text-ink-500">Moliya balansidan: naqt {{ \App\Support\Format::money($treasuryCash) }}, plastik {{ \App\Support\Format::money($treasuryCard) }}</p>
                @if ($isTeacher)
                    <x-select name="group_id" label="Guruh (ixtiyoriy)">
                        <option value="">Guruhga bog'lanmagan</option>
                        @foreach ($accruals as $a)<option value="{{ $a['group']->id }}">{{ $a['group']->name }} (qoldiq {{ \App\Support\Format::money($a['remaining'], false) }})</option>@endforeach
                    </x-select>
                @endif
                <x-select name="method" label="To'lov turi" required><option value="cash">Naqt</option><option value="card">Plastik</option></x-select>
                <x-input name="amount" money label="Summa (so'm)" required />
                <x-input name="description" label="Izoh" />
                <p class="text-xs text-ink-400">Keyingi sahifada ma'lumotlarni tekshirib, tasdiqlaysiz — shundan keyin to'lov amalga oshadi.</p>
                <button class="btn-primary w-full">Tekshirishga o'tish</button>
            </form>
        @endif

        <div class="card overflow-hidden {{ $canPay ? 'lg:col-span-2' : 'lg:col-span-3' }}">
            <h2 class="px-5 pt-5 text-lg font-semibold text-ink-900 dark:text-white sm:px-6">To'lovlar tarixi</h2>
            <div class="table-wrap mt-3">
                <table class="table">
                    <thead><tr><th>Sana</th>@if ($isTeacher)<th>Guruh</th>@endif<th>Turi</th><th class="text-right">Summa</th><th>Izoh</th><th>Kim to'ladi</th></tr></thead>
                    <tbody>
                    @forelse ($payouts as $p)
                        <tr>
                            <td class="whitespace-nowrap">{{ $p->created_at->format('d.m.Y H:i') }}</td>
                            @if ($isTeacher)<td>{{ $p->group?->name ?? '—' }}</td>@endif
                            <td>{{ $p->method->label() }}</td>
                            <td class="text-right font-semibold">{{ \App\Support\Format::money($p->amount, false) }}</td>
                            <td>{{ $p->description }}</td><td>{{ $p->creator?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-ink-500">Hali to'lov qilinmagan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
