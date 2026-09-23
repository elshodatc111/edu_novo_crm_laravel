@extends('layouts.app')
@section('title', $group->name)

@section('content')
@php
    $me = auth()->user();
    $todayRow = collect($matrix['rows'])->mapWithKeys(fn ($r) => [$r['student']->id => $r['cells'][today()->toDateString()] ?? null]);
    $canTake = $me->can('takeAttendance', $group) && $lessonToday;
@endphp
<div x-data="{ tab: 'attendance' }">
    <x-page-header :title="$group->name" :subtitle="$group->course->name.' · '.$group->teacher->name">
        <x-slot:actions>
            <span class="{{ ['new' => 'badge-blue', 'active' => 'badge-green', 'finished' => 'badge-gray'][$group->status] }}">{{ $group->status_label }}</span>
            @can('update', $group)<a href="{{ route('groups.edit', $group) }}" class="btn-secondary"><x-icon name="edit" class="h-4 w-4" /> Tahrirlash</a>@endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card card-body"><div class="text-sm text-ink-500">Dars vaqti</div><div class="mt-1 font-semibold">{{ $group->lessonTime->label }}</div><div class="text-xs text-ink-400">{{ $group->room->name }} · {{ $group->schedule->label() }}</div></div>
        <div class="card card-body"><div class="text-sm text-ink-500">Muddat</div><div class="mt-1 font-semibold">{{ $group->starts_on->format('d.m.Y') }} — {{ $group->ends_on->format('d.m.Y') }}</div><div class="text-xs text-ink-400">{{ $group->lesson_count }} ta dars</div></div>
        <div class="card card-body"><div class="text-sm text-ink-500">O'quvchilar</div><div class="mt-1 text-2xl font-bold">{{ $members->count() }}</div></div>
        @unless ($isTeacher)
            <div class="card card-body"><div class="text-sm text-ink-500">Narxi</div><div class="mt-1 font-semibold">{{ \App\Support\Format::money($group->price) }}</div>
                @if ($group->early_discount)<div class="text-xs text-ink-400">Oldindan to'lovga chegirma: {{ \App\Support\Format::money($group->early_discount) }}</div>@endif</div>
        @endunless
    </div>

    <div class="mt-6 flex gap-1 overflow-x-auto rounded-xl bg-ink-100 p-1 dark:bg-ink-800">
        @foreach (['attendance' => 'Davomad', 'students' => "O'quvchilar", 'days' => 'Dars kunlari'] + (($continueData && $group->status !== 'new') ? ['continue' => 'Davom ettirish'] : []) as $k => $label)
            <button type="button" @click="tab = '{{ $k }}'" :class="tab === '{{ $k }}' ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900 dark:text-brand-300' : 'text-ink-600 dark:text-ink-300'" class="whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-medium">{{ $label }}</button>
        @endforeach
    </div>

    {{-- DAVOMAD --}}
    <div x-show="tab === 'attendance'" class="mt-4 space-y-6">
        @can('editPastAttendance', $group)
            <div class="flex justify-end">
                <a href="{{ route('attendance.past.show', $group) }}" class="btn-ghost btn-sm">O'tgan kunni tuzatish</a>
            </div>
        @endcan
        @if ($lessonToday)
            @if ($canTake)
                <form method="POST" action="{{ route('attendance.take', $group) }}" class="card card-body">
                    @csrf
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-ink-900 dark:text-white">Bugungi davomad — {{ today()->format('d.m.Y') }}</h2>
                            <p class="text-sm text-ink-500">{{ $takenToday ? "Davomad olingan. Bugun uchun tahrirlashingiz mumkin." : 'Kelgan o\'quvchilarni belgilang.' }}</p>
                        </div>
                        <span class="{{ $takenToday ? 'badge-green' : 'badge-amber' }}">{{ $takenToday ? 'Olingan' : 'Olinmagan' }}</span>
                    </div>
                    @error('attendance')<p class="error-text mb-3">{{ $message }}</p>@enderror
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @forelse ($members as $m)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-200 px-3.5 py-2.5 text-sm hover:bg-ink-50 dark:border-ink-700 dark:hover:bg-ink-800">
                                <input type="checkbox" name="present[]" value="{{ $m->student_id }}" class="checkbox" @checked($takenToday ? ($todayRow[$m->student_id] ?? false) : true)>
                                <span class="font-medium">{{ $m->student->name }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-ink-500">Guruhda faol o'quvchi yo'q.</p>
                        @endforelse
                    </div>
                    @if ($members->isNotEmpty())<div class="mt-4"><button class="btn-primary">Davomadni saqlash</button></div>@endif
                </form>
            @endif
        @else
            <div class="rounded-xl bg-ink-100 px-4 py-3 text-sm text-ink-600 dark:bg-ink-800 dark:text-ink-300">Bugun bu guruhda dars kuni emas. Davomad faqat dars kunlarida olinadi.</div>
        @endif

        <div class="card overflow-hidden">@include('groups._matrix')</div>
    </div>

    {{-- O'QUVCHILAR --}}
    <div x-show="tab === 'students'" x-cloak class="mt-4 space-y-4">
        @can('manageMembers', $group)
            <form method="POST" action="{{ route('groups.students.store', $group) }}" class="card card-body"
                  x-data="{ q: '', found: [], picked: null, allowDebt: false, canDebt: @js(auth()->user()->can('groups.enroll_debtor')), async find() { if (this.q.length < 2) { this.found = []; return } const r = await fetch('{{ route('students.search') }}?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } }); this.found = await r.json() } }">
                @csrf
                <h2 class="mb-3 text-base font-semibold text-ink-900 dark:text-white">O'quvchi qo'shish</h2>
                @error('student_id')<p class="error-text mb-2">{{ $message }}</p>@enderror
                @error('group_id')<p class="error-text mb-2">{{ $message }}</p>@enderror
                <input type="hidden" name="student_id" :value="picked?.id">
                <div class="relative">
                    <input x-model="q" @input.debounce.300ms="find(); picked = null" placeholder="Ism yoki telefon bo'yicha qidiring..." class="input" autocomplete="off">
                    <ul x-show="found.length && !picked" x-cloak class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl border border-ink-200 bg-white shadow-pop dark:border-ink-700 dark:bg-ink-900">
                        <template x-for="s in found" :key="s.id">
                            <li><button type="button" :disabled="s.balance < 0 && !canDebt" :class="s.balance < 0 && !canDebt ? 'cursor-not-allowed opacity-60' : ''" class="flex w-full items-center justify-between gap-3 px-3.5 py-2 text-left text-sm hover:bg-ink-50 dark:hover:bg-ink-800" @click="picked = s; allowDebt = false; q = s.name; found = []">
                                <span><span x-text="s.name"></span> <span class="text-ink-400" x-text="s.phone"></span></span>
                                <span class="whitespace-nowrap text-xs font-semibold" :class="s.balance < 0 ? 'text-brand-600' : 'text-emerald-600'" x-text="new Intl.NumberFormat('ru-RU').format(s.balance) + (s.balance < 0 ? ' · qarzi bor' : '')"></span></button></li>
                        </template>
                    </ul>
                </div>
                <template x-if="picked && picked.balance < 0 && canDebt">
                    <label class="mt-3 flex items-start gap-3 rounded-xl border border-brand-200 bg-brand-50 p-3 text-sm text-brand-800 dark:border-brand-900 dark:bg-brand-950/40 dark:text-brand-200">
                        <input type="checkbox" name="allow_debt" value="1" x-model="allowDebt" class="checkbox mt-0.5">
                        <span><b>Qarzga qo'shish (istisno).</b> Bu o'quvchining qarzi <b x-text="new Intl.NumberFormat('ru-RU').format(-picked.balance) + ' so\'m'"></b>. Guruh narxi yechilgach qarz yanada oshadi. Tasdiqlayman.</span>
                    </label>
                </template>
                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                    <input name="note" placeholder="Izoh (ixtiyoriy)" class="input">
                    <button class="btn-primary" :disabled="!picked || (picked.balance < 0 && !allowDebt)">Qo'shish ({{ \App\Support\Format::money($group->price) }})</button>
                </div>
            </form>
        @endcan

        <div class="card overflow-hidden">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>F.I.O</th>@unless ($isTeacher)<th>Telefon</th><th class="text-right">Balans</th><th>Qo'shilgan</th><th></th>@endunless</tr></thead>
                    <tbody>
                    @forelse ($members as $m)
                        <tr>
                            @if ($isTeacher)
                                <td class="font-medium text-ink-900 dark:text-white">{{ $m->student->name }}</td>
                            @else
                                <td><a href="{{ route('students.show', $m->student) }}" class="font-medium text-ink-900 hover:text-brand-600 dark:text-white">{{ $m->student->name }}</a></td>
                                <td>{{ $m->student->phone }}</td>
                                <td class="text-right font-semibold {{ $m->student->balance < 0 ? 'text-brand-600' : 'text-emerald-600' }}">{{ \App\Support\Format::money($m->student->balance) }}</td>
                                <td>{{ $m->created_at->format('d.m.Y') }}</td>
                                <td class="text-right space-x-1">
                                    <a href="{{ route('students.show', $m->student) }}" class="btn-ghost btn-sm">Ochish</a>
                                    @can('manageMembers', $group)
                                        <a href="{{ route('groups.students.contract', [$group, $m->student]) }}" target="_blank" class="btn-ghost btn-sm">Shartnoma</a>
                                    @endcan
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-ink-500">Guruhda o'quvchi yo'q.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- DARS KUNLARI --}}
    <div x-show="tab === 'days'" x-cloak class="card card-body mt-4">
        <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($days as $i => $d)
                <div class="rounded-xl border px-3 py-2 text-sm {{ $d->date->isToday() ? 'border-brand-500 bg-brand-50 dark:bg-brand-950/40' : 'border-ink-200 dark:border-ink-700' }}">
                    <div class="text-xs text-ink-400">{{ $i + 1 }}-dars</div>
                    <div class="font-semibold">{{ $d->date->format('d.m.Y') }}</div>
                    <div class="text-xs text-ink-500">{{ $d->date->translatedFormat('l') }}</div>
                </div>
            @endforeach
        </div>
        @if ($group->nextGroup)<p class="mt-4 text-sm text-ink-500">Davomi: <a class="font-semibold text-brand-600" href="{{ route('groups.show', $group->nextGroup) }}">{{ $group->nextGroup->name }}</a></p>@endif
    </div>

    {{-- DAVOM ETTIRISH --}}
    @if ($continueData && $group->status !== 'new')
        <form x-show="tab === 'continue'" x-cloak method="POST" action="{{ route('groups.continue', $group) }}" class="mt-4 space-y-4">
            @csrf
            <div class="card card-body">
                <h2 class="text-lg font-semibold text-ink-900 dark:text-white">Guruhni davom ettirish</h2>
                <p class="mb-5 mt-1 text-sm text-ink-500">Yangi guruh ochiladi va tanlangan o'quvchilar unga qo'shiladi (narx balansdan yechiladi).</p>
                <div class="grid gap-5 sm:grid-cols-2">
                    @include('groups._fields', ['withSchedule' => true] + $continueData + ['group' => null])
                </div>
            </div>
            <div class="card card-body">
                <h3 class="mb-1 text-base font-semibold text-ink-900 dark:text-white">Qaysi o'quvchilar o'tadi?</h3>
                @if ($canEnrollDebtor)
                    <p class="mb-3 text-sm text-ink-500">Qarzi bor (balansi manfiy) o'quvchilarni ham belgilab o'tkazishingiz mumkin (istisno) — guruh narxi baribir balansdan yechiladi, qarz oshadi.</p>
                @else
                    <p class="mb-3 text-sm text-ink-500">Qarzi bor (balansi manfiy) o'quvchilarni keyingi guruhga qo'shib bo'lmaydi — avval to'lov qabul qiling.</p>
                @endif
                @error('students')<p class="error-text mb-3">{{ $message }}</p>@enderror
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($members as $m)
                        @php $debt = $m->student->balance < 0; $locked = $debt && ! $canEnrollDebtor; @endphp
                        <label class="flex items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5 text-sm {{ $debt ? 'border-brand-200 bg-brand-50/50 dark:border-brand-900 dark:bg-brand-950/30' : 'border-ink-200 dark:border-ink-700' }}">
                            <span class="flex items-center gap-3"><input type="checkbox" name="students[]" value="{{ $m->student_id }}" class="checkbox" @disabled($locked) @checked(! $debt)> {{ $m->student->name }}</span>
                            <span class="whitespace-nowrap text-xs font-semibold {{ $debt ? 'text-brand-600' : 'text-emerald-600' }}">{{ \App\Support\Format::money($m->student->balance, false) }}@if ($debt) · qarz{{ $canEnrollDebtor ? ' (istisno)' : '' }} @endif</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <button class="btn-primary">Davom ettirish</button>
        </form>
    @endif
</div>
@endsection
