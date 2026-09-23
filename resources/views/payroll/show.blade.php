@extends('layouts.app')
@section('title', $person->name)

@section('content')
    <x-page-header :title="$person->name" :subtitle="$person->role->label().' · ish haqi'">
        <x-slot:actions><a href="{{ route('payroll.index', ['tab' => $isTeacher ? 'teachers' : 'staff']) }}" class="btn-secondary">Orqaga</a></x-slot:actions>
    </x-page-header>

    @if ($isTeacher)
        <div class="card overflow-hidden">
            <h2 class="px-5 pt-5 text-lg font-semibold text-ink-900 dark:text-white sm:px-6">Guruhlar bo'yicha hisob</h2>
            <p class="px-5 text-sm text-ink-500 sm:px-6">Hisoblangan = o'quvchilar × stavka + bonusli o'quvchilar × bonus. «Davomad bo'yicha» — o'tkazilgan darslarga mutanosib.</p>
            <div class="table-wrap mt-3">
                <table class="table">
                    <thead><tr><th>Guruh</th><th>O'quvchi</th><th>Bonusli</th><th>Darslar</th><th class="text-right">Hisoblangan</th><th class="text-right">Davomad bo'yicha</th><th class="text-right">To'langan</th><th class="text-right">Qoldiq</th></tr></thead>
                    <tbody>
                    @forelse ($accruals as $a)
                        <tr>
                            <td><a href="{{ route('groups.show', $a['group']) }}" class="font-medium hover:text-brand-600">{{ $a['group']->name }}</a><div class="text-xs text-ink-400">{{ $a['group']->status_label }}</div></td>
                            <td>{{ $a['students'] }}</td><td>{{ $a['bonus'] }}</td><td>{{ $a['held'] }} / {{ $a['group']->lesson_count }}</td>
                            <td class="text-right">{{ \App\Support\Format::money($a['accrued'], false) }}</td>
                            <td class="text-right">{{ \App\Support\Format::money($a['accrued_by_attendance'], false) }}</td>
                            <td class="text-right">{{ \App\Support\Format::money($a['paid'], false) }}</td>
                            <td class="text-right font-semibold {{ $a['remaining'] > 0 ? 'text-brand-600' : 'text-emerald-600' }}">{{ \App\Support\Format::money($a['remaining'], false) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-8 text-center text-ink-500">So'nggi 31 kunda guruh yo'q.</td></tr>
                    @endforelse
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
