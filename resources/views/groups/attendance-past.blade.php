@extends('layouts.app')
@section('title', "O'tgan kun davomati — ".$group->name)

@section('content')
    <x-page-header title="O'tgan kun davomatini tuzatish" :subtitle="$group->name">
        <x-slot:actions>
            <a href="{{ route('groups.show', $group) }}" class="btn-secondary">Guruhga qaytish</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
        <x-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0" />
        <div>Bu bo'lim faqat <b>o'tgan</b> dars kunlari uchun (bugungi davomad guruh sahifasidan olinadi). O'zgartirish harakatlar jurnaliga yoziladi. Kelmagan o'quvchilarga SMS <b>yuborilmaydi</b> (avtomatik xabar matni "bugun" deb yozilgan, o'tgan kun uchun noto'g'ri bo'lardi).</div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card card-body lg:col-span-1">
            <h2 class="mb-3 text-sm font-semibold text-ink-900 dark:text-white">Sanani tanlang</h2>
            <form method="GET" action="{{ route('attendance.past.show', $group) }}" class="mb-4 flex gap-2">
                <input type="date" name="date" value="{{ $date }}" max="{{ today()->subDay()->toDateString() }}" class="input">
                <button class="btn-secondary shrink-0">Ko'rish</button>
            </form>
            @if ($pastDays->isEmpty())
                <p class="text-sm text-ink-500">Bu guruhda hali o'tgan dars kuni yo'q.</p>
            @else
                <div class="max-h-96 space-y-1 overflow-y-auto">
                    @foreach ($pastDays as $d)
                        <a href="{{ route('attendance.past.show', ['group' => $group, 'date' => $d['date']]) }}"
                           class="flex items-center justify-between rounded-lg px-3 py-2 text-sm {{ $d['date'] === $date ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-300' : 'hover:bg-ink-50 dark:hover:bg-ink-800' }}">
                            <span>{{ \Carbon\Carbon::parse($d['date'])->format('d.m.Y, D') }}</span>
                            <span class="{{ $d['state'] === 'missed' ? 'badge-amber' : 'badge-green' }}">{{ $d['state'] === 'missed' ? 'Olinmagan' : 'Olingan' }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card card-body lg:col-span-2">
            @if (! $isPast)
                <p class="text-sm text-ink-500">{{ \Carbon\Carbon::parse($date)->format('d.m.Y') }} — bu sana bugun yoki kelajakda. Faqat o'tgan kunlar tuzatiladi.</p>
            @elseif (! $isLessonDay)
                <p class="text-sm text-ink-500">{{ \Carbon\Carbon::parse($date)->format('d.m.Y') }} kuni bu guruhda dars bo'lmagan.</p>
            @else
                <form method="POST" action="{{ route('attendance.past.store', $group) }}">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date }}">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-ink-900 dark:text-white">{{ \Carbon\Carbon::parse($date)->format('d.m.Y') }}</h2>
                        <span class="{{ $wasTaken ? 'badge-green' : 'badge-amber' }}">{{ $wasTaken ? 'Oldin olingan' : 'Olinmagan edi' }}</span>
                    </div>
                    @error('date')<p class="error-text mb-3">{{ $message }}</p>@enderror
                    @error('attendance')<p class="error-text mb-3">{{ $message }}</p>@enderror
                    <div class="grid gap-2 sm:grid-cols-2">
                        @forelse ($members as $m)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-200 px-3.5 py-2.5 text-sm hover:bg-ink-50 dark:border-ink-700 dark:hover:bg-ink-800">
                                <input type="checkbox" name="present[]" value="{{ $m->student_id }}" class="checkbox" @checked($existing[$m->student_id] ?? false)>
                                <span class="font-medium">{{ $m->student->name }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-ink-500">Guruhda faol o'quvchi yo'q.</p>
                        @endforelse
                    </div>
                    @if ($members->isNotEmpty())<div class="mt-4"><button class="btn-primary">Saqlash</button></div>@endif
                </form>
            @endif
        </div>
    </div>
@endsection
