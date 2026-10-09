@extends('layouts.app')
@section('title', $group->name)

@section('content')
    <x-page-header :title="$group->name" subtitle="Nom, kurs, o'qituvchi, xona, dars vaqti va kunlari, boshlanish sanasi, darslar soni, narx va stavkalar tahrirlanadi." />

    @if ($groupStatus === \App\Models\Group::FINISHED)
        <div class="mb-6 max-w-3xl rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            Guruh <b>yakunlangan</b>: xona, dars vaqti, dars kunlari, boshlanish sanasi va darslar soni o'zgartirilmaydi. Nom, kurs, o'qituvchi, stavkalar va narx tahrirlanadi.
        </div>
    @elseif ($lockedCount > 0)
        <div class="mb-6 max-w-3xl rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            O'tgan yoki davomad olingan <b>{{ $lockedCount }} ta dars</b> ({{ $lockedUntil?->format('d.m.Y') }} gacha) o'zgarmaydi. Boshlanish sanasi o'zgarmaydi. Xona, vaqt yoki kunlar o'zgarsa, faqat <b>bugungi (davomad olinmagan) va kelgusi darslar</b> yangilanadi; o'tgan darslar eski vaqt va xonasida qoladi.
        </div>
    @else
        <div class="mb-6 max-w-3xl rounded-xl border border-ink-200 bg-ink-50 p-4 text-sm text-ink-600 dark:border-ink-800 dark:bg-ink-900 dark:text-ink-300">
            Dars hali boshlanmagan: jadvalning barcha parametrlarini o'zgartirish mumkin (boshlanish sanasi faqat bugun yoki keyingi kunlarga), darslar yangidan tuziladi. Xona va o'qituvchi bandligi tekshiriladi.
        </div>
    @endif

    <form method="POST" action="{{ route('groups.update', $group) }}" class="max-w-3xl space-y-6">
        @csrf @method('PUT')
        <div class="card card-body grid gap-5 sm:grid-cols-2">
            @include('groups._fields', ['withSchedule' => true])
        </div>
        <div class="flex gap-2">
            <button class="btn-primary">Saqlash</button>
            <a href="{{ route('groups.show', $group) }}" class="btn-secondary">Bekor qilish</a>
        </div>
    </form>
@endsection
