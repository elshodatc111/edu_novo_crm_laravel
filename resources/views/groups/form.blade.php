@extends('layouts.app')
@section('title', 'Yangi guruh')

@section('content')
    <x-page-header title="Yangi guruh" subtitle="Dars kunlari dam olish kunlarini hisobga olib avtomatik tuziladi" />

    @if ($courses->isEmpty() || $teachers->isEmpty() || $rooms->isEmpty() || $times->isEmpty() || $plans->isEmpty())
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            Guruh ochishdan oldin kamida bittadan <b>kurs, o'qituvchi, xona, dars vaqti va narx rejasi</b> kiritilgan bo'lishi kerak.
        </div>
    @endif

    <form method="POST" action="{{ route('groups.store') }}" class="max-w-3xl space-y-6">
        @csrf
        <div class="card card-body grid gap-5 sm:grid-cols-2">
            @include('groups._fields', ['withSchedule' => true])
        </div>
        <div class="flex gap-2">
            <button class="btn-primary">Guruhni yaratish</button>
            <a href="{{ route('groups.index') }}" class="btn-secondary">Bekor qilish</a>
        </div>
    </form>
@endsection
