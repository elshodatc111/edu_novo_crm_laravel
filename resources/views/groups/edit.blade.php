@extends('layouts.app')
@section('title', $group->name)

@section('content')
    <x-page-header :title="$group->name" subtitle="Jadval (kunlar, xona, vaqt) o'zgarmaydi. Nom, kurs, o'qituvchi va stavkalar tahrirlanadi." />

    <form method="POST" action="{{ route('groups.update', $group) }}" class="max-w-3xl space-y-6">
        @csrf @method('PUT')
        <div class="card card-body grid gap-5 sm:grid-cols-2">
            @include('groups._fields', ['withSchedule' => false])
        </div>
        <div class="flex gap-2">
            <button class="btn-primary">Saqlash</button>
            <a href="{{ route('groups.show', $group) }}" class="btn-secondary">Bekor qilish</a>
        </div>
    </form>
@endsection
