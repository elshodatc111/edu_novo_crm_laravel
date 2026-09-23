@extends('layouts.app')
@section('title', 'Bugungi davomad')

@section('content')
    <x-page-header title="Bugungi davomad" :subtitle="today()->translatedFormat('l, d.m.Y')" />

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($groups as $g)
            @php $row = $byGroup[$g->id] ?? null; @endphp
            <div class="card card-body flex flex-col">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <a href="{{ route('groups.show', $g) }}" class="text-lg font-semibold text-ink-900 hover:text-brand-600 dark:text-white">{{ $g->name }}</a>
                        <p class="text-sm text-ink-500">{{ $g->course->name }} · {{ $g->teacher->name }}</p>
                    </div>
                    <span class="{{ $row && $row['taken'] ? 'badge-green' : 'badge-amber' }}">{{ $row && $row['taken'] ? 'Olingan' : 'Olinmagan' }}</span>
                </div>
                <div class="mt-3 text-sm text-ink-600 dark:text-ink-300">{{ $g->lessonTime->label }} · {{ $g->room->name }} · {{ $g->students_count }} o'quvchi</div>
                @if ($row && $row['taken'])
                    <div class="mt-3 text-sm">Keldi: <b class="text-emerald-600">{{ $row['present'] }}</b> · Kelmadi: <b class="text-brand-600">{{ $row['absent'] }}</b> · {{ $row['rate'] }}%</div>
                @endif
                <div class="mt-4 border-t border-ink-100 pt-4 dark:border-ink-800">
                    <a href="{{ route('groups.show', $g) }}" class="btn-primary btn-sm">{{ $row && $row['taken'] ? 'Tahrirlash' : 'Davomad olish' }}</a>
                </div>
            </div>
        @empty
            <div class="card card-body col-span-full py-12 text-center text-ink-500">Bugun dars bo'ladigan guruh yo'q.</div>
        @endforelse
    </div>
@endsection
